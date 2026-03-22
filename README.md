<<<<<<< HEAD
=======
---
>>>>>>> c3f523c0d8fae5548d8c0287a2a88e543c8ea267
# Mini PHP Application Server / Mini PHP Uygulama Sunucusu

## English Version

### Description
> **Mini PHP Application Server** – A lightweight, long-running PHP server built for your custom framework. The application boots once and stays in memory, handling requests at high performance. It uses a multi-worker, event-loop architecture, providing a minimalist alternative to Laravel Octane or RoadRunner.

### Features
- Long-running PHP application (boot once)
- Multi-worker support for concurrent requests
- Event-loop architecture for non-blocking connections
- Request reset mechanism to prevent state leakage
- Minimalist and lightweight

### Requirements
- PHP 8.x or higher
- Linux / macOS (Windows not fully supported due to `pcntl_fork`)
- Optional: Nginx for reverse proxy

### Installation
```bash
# Clone your project
git clone <your-repo-url> myapp
cd myapp

# Make server script executable
chmod +x server.php

# Start the server
php server.php
```

### Production Setup (Recommended)
1. **Systemd service**
```bash
sudo nano /etc/systemd/system/myapp.service
```
Paste:
```
[Unit]
Description=Mini PHP App Server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/myapp/server.php
WorkingDirectory=/var/www/myapp
Restart=always
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```
Enable and start:
```bash
sudo systemctl daemon-reload
sudo systemctl enable myapp
sudo systemctl start myapp
```

2. **Optional Nginx Reverse Proxy**
```nginx
server {
    listen 80;
    server_name yourdomain.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### Usage
- Access in browser: `http://localhost:8080` or your domain if using Nginx
- Works with your existing framework views and controllers

### Notes
- Restart workers periodically to prevent memory leaks
- Windows is not fully supported (use WSL or Linux/macOS)

---

## Türkçe Versiyon

### Tanım
> **Mini PHP Uygulama Sunucusu** – Kendi frameworkünüz için geliştirilmiş, uzun ömürlü ve hafif bir PHP sunucusudur. Uygulama bir kez boot edilir ve RAM’de kalarak yüksek performanslı istek yönetimi sağlar. Multi-worker ve event-loop mimarisi ile Laravel Octane veya RoadRunner’a minimalist bir alternatiftir.

### Özellikler
- Uzun ömürlü PHP uygulaması (bir kez boot)
- Çoklu worker desteği ile paralel istekler
- Event-loop mimarisi ile non-blocking bağlantılar
- Request sonrası reset mekanizması ile state sızıntısını önler
- Minimalist ve hafif

### Gereksinimler
- PHP 8.x veya üstü
- Linux / macOS (Windows tam desteklenmez, `pcntl_fork` nedeniyle)
- Opsiyonel: Reverse proxy için Nginx

### Kurulum
```bash
# Projeyi klonla
git clone <repo-url> myapp
cd myapp

# Server script’i çalıştırılabilir yap
chmod +x server.php

# Server’ı başlat
php server.php
```

### Üretim Ortamı Kurulumu (Önerilen)
1. **Systemd servisi**
```bash
sudo nano /etc/systemd/system/myapp.service
```
İçeriğe yapıştır:
```
[Unit]
Description=Mini PHP App Server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/myapp/server.php
WorkingDirectory=/var/www/myapp
Restart=always
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```
Aktifleştir ve başlat:
```bash
sudo systemctl daemon-reload
sudo systemctl enable myapp
sudo systemctl start myapp
```

2. **Opsiyonel Nginx Reverse Proxy**
```nginx
server {
    listen 80;
    server_name siteadresiniz.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### Kullanım
- Tarayıcıdan erişim: `http://localhost:8080` veya Nginx ile domain
- Mevcut framework view ve controller’larınızla çalışır

### Notlar
- Worker’ları periyodik olarak restart et, RAM sızıntısını önler
- Windows tam desteklenmez (WSL veya Linux/macOS önerilir)

