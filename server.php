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

    // Headers
    $headers = [];
    $lines   = explode("\r\n", $raw);
    foreach (array_slice($lines, 1) as $line) {
        if (!str_contains($line, ': ')) continue;
        [$k, $v]    = explode(': ', $line, 2);
        $headers[strtolower($k)] = $v;
    }

    // Body
    $body = '';
    if (str_contains($raw, "\r\n\r\n")) {
        [, $body] = explode("\r\n\r\n", $raw, 2);
    }

    // Query string
    $uri      = $m[2] ?? '/';
    $query    = [];
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
        // 5ms bekle, gelmezse çık
        if (!stream_select($r, $w, $e, 0, 5000)) break;

        $chunk = fread($sock, 8192);
        if ($chunk === false || $chunk === '') break;

        $data .= $chunk;

        if (str_contains($data, "\r\n\r\n")) {
            preg_match('/Content-Length:\s*(\d+)/i', $data, $cl);
            if (!$cl) break; // GET request, headers tamam, çık

            [, $body] = explode("\r\n\r\n", $data, 2);
            if (strlen($body) >= (int) $cl[1]) break; // POST body tamam, çık
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
                // Yeni bağlantı
                if ($sock === $server) {
                    $conn = stream_socket_accept($server, 0);
                    if ($conn) {
                        stream_set_blocking($conn, false);
                        $connections[(int) $conn] = $conn;
                    }
                    continue;
                }

                // Mevcut bağlantıdan veri oku
                $data = read_request($sock);

                if (!$data) {
                    fclose($sock);
                    unset($connections[(int) $sock]);
                    continue;
                }

                try {
                    $req       = parse_request_raw($data);
                    $keepAlive = ($req->headers['connection'] ?? '') === 'keep-alive';

                    $res = $app->handle($req);

                    // Keep-alive header ekle
                    if ($keepAlive) {
                        $res = str_replace(
                            "HTTP/1.1 ",
                            "HTTP/1.1 ",
                            $res
                        );
                        // Connection header'ı response'a enjekte et
                        $res = preg_replace(
                            '/(\r\nContent-Length:)/',
                            "\r\nConnection: keep-alive\r\nKeep-Alive: timeout=5, max=1000$1",
                            $res
                        );
                        fwrite($sock, $res);
                        // Bağlantıyı kapatma, bir sonraki request için bekle
                    } else {
                        fwrite($sock, $res);
                        fclose($sock);
                        unset($connections[(int) $sock]);
                    }
                } catch (\Throwable $e) {
                    $error = "500 Internal Server Error\n" . $e->getMessage();
                    fwrite(
                        $sock,
                        "HTTP/1.1 500 Internal Server Error\r\n" .
                            "Content-Type: text/plain\r\n" .
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

// Parent beklesin
while (pcntl_wait($status) > 0);
