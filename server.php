<?php

require 'App.php';

$host        = "0.0.0.0";
$port        = $argv[1] ?? 8080;
$workers     = $argv[2] ?? 4;
$maxRequests = $argv[3] ?? 1000;

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

function read_request($sock): string
{
    $data  = '';
    $start = microtime(true);

    while (true) {
        $r = [$sock];
        $w = $e = [];
        if (!stream_select($r, $w, $e, 0, 5000)) break;

        $chunk = fread($sock, 65536);
        if ($chunk === false || $chunk === '') break;

        $data .= $chunk;

        if (str_contains($data, "\r\n\r\n")) {
            preg_match('/Content-Length:\s*(\d+)/i', $data, $cl);
            if (!$cl) break; // GET - body yok

            [, $body] = explode("\r\n\r\n", $data, 2);
            if (strlen($body) >= (int) $cl[1]) break; // body tamam
        }

        // 30 saniye hard limit (büyük upload için)
        if ((microtime(true) - $start) > 30) break;
    }

    return $data;
}

function parse_request_raw(string $raw): object
{
    // Method, URI, HTTP version
    preg_match('#^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) (.*?) HTTP/([\d.]+)#', $raw, $m);

    $method  = $m[1] ?? 'GET';
    $fullUri = $m[2] ?? '/';
    $version = $m[3] ?? '1.1';

    // Headers
    $headers     = [];
    $headerLines = explode("\r\n", $raw);
    foreach (array_slice($headerLines, 1) as $line) {
        if ($line === '') break;
        if (!str_contains($line, ': ')) continue;
        [$k, $v]              = explode(': ', $line, 2);
        $headers[strtolower($k)] = $v;
    }

    // URI ve query string
    $uri   = $fullUri;
    $query = [];
    if (str_contains($fullUri, '?')) {
        [$uri, $qs] = explode('?', $fullUri, 2);
        parse_str($qs, $query);
    }

    // Body
    $body = '';
    if (str_contains($raw, "\r\n\r\n")) {
        [, $body] = explode("\r\n\r\n", $raw, 2);
        $contentLength = (int) ($headers['content-length'] ?? 0);
        if ($contentLength > 0) {
            $body = substr($body, 0, $contentLength);
        }
    }

    $contentType = $headers['content-type'] ?? '';

    // $_GET
    $_GET = $query;

    // $_POST ve $_FILES
    $_POST  = [];
    $_FILES = [];

    if ($method !== 'GET' && $method !== 'HEAD') {
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $_POST);
        } elseif (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) $_POST = $decoded;
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            // Boundary al
            preg_match('/boundary=([^\s;]+)/', $contentType, $bm);
            $boundary = $bm[1] ?? '';
            if ($boundary) {
                parse_multipart($body, $boundary, $_POST, $_FILES);
            }
        }
    }

    // $_REQUEST
    $_REQUEST = array_merge($_GET, $_POST);

    // $_SERVER
    $_SERVER = [
        'REQUEST_METHOD'  => $method,
        'REQUEST_URI'     => $fullUri,
        'PATH_INFO'       => $uri,
        'QUERY_STRING'    => http_build_query($query),
        'HTTP_HOST'       => $headers['host'] ?? '',
        'HTTP_USER_AGENT' => $headers['user-agent'] ?? '',
        'HTTP_ACCEPT'     => $headers['accept'] ?? '',
        'CONTENT_TYPE'    => $contentType,
        'CONTENT_LENGTH'  => $headers['content-length'] ?? '',
        'REMOTE_ADDR'     => $headers['x-real-ip'] ?? '127.0.0.1',
        'SERVER_PROTOCOL' => "HTTP/$version",
        'HTTPS'           => 'on',
        'SERVER_PORT'     => '443',
    ];

    // Tüm HTTP header'larını $_SERVER'a ekle
    foreach ($headers as $k => $v) {
        $key             = 'HTTP_' . strtoupper(str_replace('-', '_', $k));
        $_SERVER[$key]   = $v;
    }

    return (object) [
        'method'      => $method,
        'uri'         => $uri,
        'fullUri'     => $fullUri,
        'headers'     => $headers,
        'query'       => $query,
        'body'        => $body,
        'contentType' => $contentType,
    ];
}

function parse_multipart(string $body, string $boundary, array &$post, array &$files): void
{
    $boundary  = '--' . $boundary;
    $parts     = explode($boundary, $body);

    // İlk ve son parçayı atla
    array_shift($parts);
    array_pop($parts);

    foreach ($parts as $part) {
        if (str_starts_with($part, '--')) continue;

        // Header ve body ayır
        if (!str_contains($part, "\r\n\r\n")) continue;
        [$partHeaders, $partBody] = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
        $partBody = rtrim($partBody, "\r\n");

        // Content-Disposition parse et
        preg_match('/Content-Disposition:[^\r\n]*name="([^"]+)"/', $partHeaders, $nm);
        $name = $nm[1] ?? '';
        if (!$name) continue;

        // Dosya mı alan mı?
        preg_match('/filename="([^"]*)"/', $partHeaders, $fm);
        $filename = $fm[1] ?? '';

        if ($filename !== '') {
            // Dosya — tmp'ye kaydet
            $tmpFile = tempnam(sys_get_temp_dir(), 'php_upload_');
            file_put_contents($tmpFile, $partBody);

            preg_match('/Content-Type:\s*([^\r\n]+)/i', $partHeaders, $ctm);
            $mimeType = trim($ctm[1] ?? 'application/octet-stream');

            $files[$name] = [
                'name'     => $filename,
                'type'     => $mimeType,
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($partBody),
            ];
        } else {
            // Normal alan
            $post[$name] = $partBody;
        }
    }
}

function reset_superglobals(): void
{
    $_GET     = [];
    $_POST    = [];
    $_FILES   = [];
    $_REQUEST = [];
    $_SERVER  = [];
}

function cleanup_tmp_files(): void
{
    foreach ($_FILES as $file) if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) unlink($file['tmp_name']);
}

for ($i = 0; $i < $workers; $i++) {
    if (pcntl_fork() === 0) {
        echo "👷 Worker #$i started (PID: " . getmypid() . ")\n";

        $app          = new App();
        $connections  = [];
        $requestCount = 0;

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

                // Request oku
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
                } finally {
                    cleanup_tmp_files();
                    reset_superglobals();
                    $app->reset();
                }

                $requestCount++;
                if ($requestCount >= $maxRequests) {
                    echo "♻️ Worker #$i restarting after $maxRequests requests\n";
                    exit;
                }
            }
        }

        exit;
    }
}

while (pcntl_wait($status) > 0);
