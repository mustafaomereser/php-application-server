<?php

require 'App.php';

$host    = "0.0.0.0";
$port    = $argv[1] ?? 8080;
$workers = 4;

$server = stream_socket_server(
    "tcp://$host:$port",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if (!$server) {
    die("❌ Socket error: $errstr ($errno)\n");
}

stream_set_blocking($server, false);

echo "🚀 Server started on http://$host:$port\n";

function parse_request_raw(string $raw): object
{
    preg_match('#(GET|POST|PUT|PATCH|DELETE) (.*?) HTTP#', $raw, $m);

    $headers = [];
    $lines   = explode("\r\n", $raw);
    foreach (array_slice($lines, 1) as $line) {
        if (!str_contains($line, ': ')) continue;
        [$k, $v]              = explode(': ', $line, 2);
        $headers[strtolower($k)] = $v;
    }

    $body = '';
    if (str_contains($raw, "\r\n\r\n")) {
        [, $body] = explode("\r\n\r\n", $raw, 2);
    }

    $uri   = $m[2] ?? '/';
    $query = [];
    if (str_contains($uri, '?')) {
        [$uri, $qs] = explode('?', $uri, 2);
        parse_str($qs, $query);
    }

    return (object) [
        'method'  => $m[1] ?? 'GET',
        'uri'     => $uri,
        'headers' => $headers,
        'query'   => $query,
        'body'    => $body,
        'raw'     => $raw,
    ];
}

function read_request($sock): string
{
    $data = '';

    while (true) {
        $r = [$sock];
        $w = $e = [];
        if (!stream_select($r, $w, $e, 0, 5000)) break;

        $chunk = fread($sock, 8192);
        if ($chunk === false || $chunk === '') break;

        $data .= $chunk;

        if (str_contains($data, "\r\n\r\n")) {
            preg_match('/Content-Length:\s*(\d+)/i', $data, $cl);
            if (!$cl) break;

            [, $body] = explode("\r\n\r\n", $data, 2);
            if (strlen($body) >= (int) $cl[1]) break;
        }
    }

    return $data;
}

for ($i = 0; $i < $workers; $i++) {
    if (pcntl_fork() === 0) {
        echo "👷 Worker #$i started (PID: " . getmypid() . ")\n";

        $app         = new App();
        $connections = [];

        while (true) {
            $readSockets = array_merge([$server], $connections);
            $write = $except = [];

            if (stream_select($readSockets, $write, $except, null) === false) {
                continue;
            }

            foreach ($readSockets as $sock) {
                if ($sock === $server) {
                    $conn = stream_socket_accept($server, 0);
                    if ($conn) {
                        stream_set_blocking($conn, false);
                        $connections[(int) $conn] = $conn;
                    }
                    continue;
                }

                $data = read_request($sock);

                if (!$data) {
                    fclose($sock);
                    unset($connections[(int) $sock]);
                    continue;
                }

                try {
                    $req       = parse_request_raw($data);
                    $keepAlive = strtolower($req->headers['connection'] ?? 'keep-alive') !== 'close';

                    $res = $app->handle($req, $keepAlive);

                    fwrite($sock, $res);

                    if (!$keepAlive) {
                        fclose($sock);
                        unset($connections[(int) $sock]);
                    }
                } catch (\Throwable $e) {
                    $error = "500 Internal Server Error\n" . $e->getMessage();
                    fwrite(
                        $sock,
                        "HTTP/1.1 500 Internal Server Error\r\n" .
                            "Content-Type: text/plain\r\n" .
                            "Connection: close\r\n" .
                            "Content-Length: " . strlen($error) . "\r\n\r\n" .
                            $error
                    );
                    fclose($sock);
                    unset($connections[(int) $sock]);
                }

                $app->reset();
            }
        }

        exit;
    }
}

while (pcntl_wait($status) > 0);
