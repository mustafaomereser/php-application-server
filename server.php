<?php

require 'App.php';

$host        = "0.0.0.0";
$port        = $argv[1] ?? 8080;
$workers     = $argv[2] ?? 20;
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

echo "🚀 Server started on http://$host:$port (workers: $workers, maxRequests: $maxRequests)\n";

function read_request($sock): string
{
    $data  = '';
    $start = microtime(true);

    while (true) {
        $r = [$sock];
        $w = $e = [];
        if (!stream_select($r, $w, $e, 0, 1000)) break;

        $chunk = fread($sock, 65536);
        if ($chunk === false || $chunk === '') break;

        $data .= $chunk;

        if (str_contains($data, "\r\n\r\n")) {
            preg_match('/Content-Length:\s*(\d+)/i', $data, $cl);
            if (!$cl) break;
            [, $body] = explode("\r\n\r\n", $data, 2);
            if (strlen($body) >= (int) $cl[1]) break;
        }

        if ((microtime(true) - $start) > 30) break;
    }

    return $data;
}

function parse_request_raw(string $raw): object
{
    preg_match('#^(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) (.*?) HTTP/([\d.]+)#', $raw, $m);

    $method  = $m[1] ?? 'GET';
    $fullUri = $m[2] ?? '/';
    $version = $m[3] ?? '1.1';

    $headers = [];
    foreach (array_slice(explode("\r\n", $raw), 1) as $line) {
        if ($line === '') break;
        if (!str_contains($line, ': ')) continue;
        [$k, $v]              = explode(': ', $line, 2);
        $headers[strtolower($k)] = $v;
    }

    $uri   = $fullUri;
    $query = [];
    if (str_contains($fullUri, '?')) {
        [$uri, $qs] = explode('?', $fullUri, 2);
        parse_str($qs, $query);
    }

    $body = '';
    if (str_contains($raw, "\r\n\r\n")) {
        [, $body] = explode("\r\n\r\n", $raw, 2);
        $contentLength = (int) ($headers['content-length'] ?? 0);
        if ($contentLength > 0) $body = substr($body, 0, $contentLength);
    }

    $contentType = $headers['content-type'] ?? '';

    // Superglobals — global scope'ta set et
    global $_GET, $_POST, $_FILES, $_REQUEST, $_SERVER;

    $_GET   = $query;
    $_POST  = [];
    $_FILES = [];

    if ($method !== 'GET' && $method !== 'HEAD') {
        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($body, $_POST);
        } elseif (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) $_POST = $decoded;
        } elseif (str_contains($contentType, 'multipart/form-data')) {
            preg_match('/boundary=([^\s;]+)/', $contentType, $bm);
            if (!empty($bm[1])) parse_multipart($body, $bm[1], $_POST, $_FILES);
        }
    }

    $_REQUEST = array_merge($_GET, $_POST);

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

    foreach ($headers as $k => $v) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
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
    $parts = explode('--' . $boundary, $body);
    array_shift($parts);
    array_pop($parts);

    foreach ($parts as $part) {
        if (str_starts_with($part, '--')) continue;
        if (!str_contains($part, "\r\n\r\n")) continue;

        [$partHeaders, $partBody] = explode("\r\n\r\n", ltrim($part, "\r\n"), 2);
        $partBody = rtrim($partBody, "\r\n");

        preg_match('/Content-Disposition:[^\r\n]*name="([^"]+)"/', $partHeaders, $nm);
        $name = $nm[1] ?? '';
        if (!$name) continue;

        preg_match('/filename="([^"]*)"/', $partHeaders, $fm);
        $filename = $fm[1] ?? '';

        $isArray  = str_ends_with($name, '[]') || preg_match('/\[\d*\]$/', $name);
        $baseName = $isArray ? preg_replace('/\[\d*\]$|\[\]$/', '', $name) : $name;

        if ($filename !== '') {
            preg_match('/Content-Type:\s*([^\r\n]+)/i', $partHeaders, $ctm);
            $tmpFile = tempnam(sys_get_temp_dir(), 'php_upload_');
            file_put_contents($tmpFile, $partBody);

            $fileEntry = [
                'name'     => $filename,
                'type'     => trim($ctm[1] ?? 'application/octet-stream'),
                'tmp_name' => $tmpFile,
                'error'    => UPLOAD_ERR_OK,
                'size'     => strlen($partBody),
            ];

            if ($isArray) {
                foreach ($fileEntry as $k => $v) $files[$baseName][$k][] = $v;
            } else {
                $files[$baseName] = $fileEntry;
            }
        } else {
            if ($isArray) $post[$baseName][] = $partBody;
            else $post[$name] = $partBody;
        }
    }
}

function cleanup_tmp_files(): void
{
    global $_FILES;
    foreach ($_FILES as $file) {
        if (!empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
            unlink($file['tmp_name']);
        }
    }
}

function reset_superglobals(): void
{
    global $_GET, $_POST, $_FILES, $_REQUEST, $_SERVER;
    $_GET = $_POST = $_FILES = $_REQUEST = $_SERVER = [];
}


$app = new App(); // once boot
for ($i = 0; $i < $workers; $i++) {
    if (pcntl_fork() === 0) {
        // $app = new App(); // boot for every worker

        define('WORKER_ID', $i);
        echo "👷 Worker #$i started (PID: " . getmypid() . ")\n";

        $connections  = []; // [socket_int_key => ['socket' => resource, 'time' => int]]
        $socketMap    = []; // stream_select sonuçlarını hızlı lookup için
        $requestCount = 0;
        $lastCleanup  = time();

        while (true) {
            // stream_select için socket listesi — socketMap'ten al
            $readSockets = array_merge([$server], array_values($socketMap));
            $w = $e = [];

            // Bağlantı varsa 0 timeout (hızlı dön), yoksa 100ms bekle
            $tv = empty($socketMap) ? 100000 : 0;
            if (stream_select($readSockets, $w, $e, 0, $tv) === false) continue;

            foreach ($readSockets as $sock) {
                // Yeni bağlantı
                if ($sock === $server) {
                    $conn = @stream_socket_accept($server, 0);
                    if ($conn) {
                        stream_set_blocking($conn, false);
                        $key               = (int) $conn;
                        $connections[$key] = ['socket' => $conn, 'time' => time()];
                        $socketMap[$key]   = $conn;
                    }
                    continue;
                }

                $key = (int) $sock;
                if (!isset($connections[$key])) continue;

                $data = read_request($sock);

                if (!$data) {
                    @fclose($sock);
                    unset($connections[$key], $socketMap[$key]);
                    continue;
                }

                try {
                    $req       = parse_request_raw($data);
                    $keepAlive = strtolower($req->headers['connection'] ?? 'keep-alive') !== 'close'
                        && !isset($req->query['_close']);

                    $res = $app->handle($req, $keepAlive);
                    fwrite($sock, $res);

                    if ($keepAlive) {
                        $connections[$key]['time'] = time();
                    } else {
                        @fclose($sock);
                        unset($connections[$key], $socketMap[$key]);
                    }
                } catch (\Throwable $ex) {
                    $error = "500 Internal Server Error\n" . $ex->getMessage();
                    fwrite(
                        $sock,
                        "HTTP/1.1 500 Internal Server Error\r\n" .
                            "Content-Type: text/plain\r\n" .
                            "Connection: close\r\n" .
                            "Content-Length: " . strlen($error) . "\r\n\r\n" .
                            $error
                    );
                    @fclose($sock);
                    unset($connections[$key], $socketMap[$key]);
                } finally {
                    cleanup_tmp_files();
                    reset_superglobals();
                    $app->reset();
                }

                $requestCount++;
                if ($requestCount >= $maxRequests) {
                    echo "♻️ Worker #$i restarting after $maxRequests requests\n";
                    foreach ($connections as $c) @fclose($c['socket']);
                    exit;
                }
            }

            // Her 5 saniyede bir idle bağlantıları temizle
            $now = time();
            if ($now - $lastCleanup >= 5) {
                foreach ($connections as $key => $c) {
                    if (($now - $c['time']) > 5 || !is_resource($c['socket']) || feof($c['socket'])) {
                        @fclose($c['socket']);
                        unset($connections[$key], $socketMap[$key]);
                    }
                }
                $lastCleanup = $now;
            }
        }

        exit;
    }
}

while (pcntl_wait($status) > 0);
