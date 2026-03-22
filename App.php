<?php

class App {

    protected $data = [];

    public function __construct() {
        echo "🔥 App boot edildi (1 kere)\n";
    }

    public function handle($req) {

        // basit router
        if ($req->uri == '/') {
            return $this->view('home', [
                'time' => date('H:i:s')
            ]);
        }

        if ($req->uri == '/test') {
            return $this->response("TEST OK: " . rand());
        }

        return $this->response("404 Not Found", 404);
    }

    public function view($file, $data = []) {
        extract($data);

        ob_start();
        require __DIR__ . "/views/$file.php";
        $html = ob_get_clean();

        return $this->response($html);
    }

    public function response($content, $status = 200) {

        return "HTTP/1.1 $status OK\r\n" .
               "Content-Type: text/html\r\n" .
               "Content-Length: " . strlen($content) . "\r\n" .
               "\r\n" .
               $content;
    }

    public function reset() {
        // burada state temizlenecek
        $this->data = [];
    }
}