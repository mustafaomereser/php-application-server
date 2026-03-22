<?php

class App
{
    protected array $data = [];

    private const STATUS_TEXTS = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
    ];

    public function __construct()
    {
        echo "🔥 App boot edildi (1 kere)\n";
    }

    public function handle(object $req): string
    {
        if ($req->uri === '/') {
            return $this->view('home', [
                'time' => date('H:i:s'),
            ]);
        }

        if ($req->uri === '/test') {
            return $this->response("TEST OK: " . rand());
        }

        return $this->response("404 Not Found", 404);
    }

    public function view(string $file, array $data = []): string
    {
        extract($data);
        ob_start();
        require __DIR__ . "/views/$file.php";
        $html = ob_get_clean();
        return $this->response($html);
    }

    public function response(string $content, int $status = 200): string
    {
        $text = self::STATUS_TEXTS[$status] ?? 'OK';
        return "HTTP/1.1 $status $text\r\n" .
               "Content-Type: text/html; charset=utf-8\r\n" .
               "Content-Length: " . strlen($content) . "\r\n" .
               "\r\n" .
               $content;
    }

    public function reset(): void
    {
        $this->data = [];
    }
}