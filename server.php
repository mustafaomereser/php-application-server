<?php

require 'App.php';

$host = "0.0.0.0";
$port = 8080;
$workers = 4;

// socket oluştur
$server = stream_socket_server(
    "tcp://$host:$port",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if (!$server) {
    die("❌ Socket error: $errstr ($errno)\n");
}

// 🔥 NON BLOCKING
stream_set_blocking($server, false);

echo "🚀 Server started on http://$host:$port\n";

function parse_request_raw($raw) {

    preg_match('#(GET|POST) (.*?) HTTP#', $raw, $m);

    return (object)[
        'method' => $m[1] ?? 'GET',
        'uri'    => $m[2] ?? '/',
        'raw'    => $raw
    ];
}

// worker fork
for ($i = 0; $i < $workers; $i++) {

    if (pcntl_fork() === 0) {

        echo "👷 Worker #$i started (PID: " . getmypid() . ")\n";

        $app = new App();

        $connections = [];

        while (true) {

            $readSockets = array_merge([$server], $connections);
            $write = $except = [];

            // 🔥 event loop
            if (stream_select($readSockets, $write, $except, null) === false) {
                continue;
            }

            foreach ($readSockets as $sock) {

                // yeni bağlantı
                if ($sock === $server) {

                    $conn = stream_socket_accept($server, 0);

                    if ($conn) {
                        stream_set_blocking($conn, false);
                        $connections[(int)$conn] = $conn;
                    }

                    continue;
                }

                // mevcut bağlantıdan veri oku
                $data = fread($sock, 1024);

                if (!$data) {
                    fclose($sock);
                    unset($connections[(int)$sock]);
                    continue;
                }

                try {
                    $req = parse_request_raw($data);

                    $res = $app->handle($req);

                    fwrite($sock, $res);

                } catch (\Throwable $e) {

                    $error = "500 Internal Server Error\n" . $e->getMessage();

                    fwrite($sock,
                        "HTTP/1.1 500 Internal Server Error\r\n" .
                        "Content-Type: text/plain\r\n" .
                        "Content-Length: " . strlen($error) . "\r\n\r\n" .
                        $error
                    );
                }

                fclose($sock);
                unset($connections[(int)$sock]);

                $app->reset(); // 💣
            }
        }

        exit;
    }
}

// parent beklesin
while (pcntl_wait($status) > 0);