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
- ACME challenge support for automatic SSL via Certbot
- Systemd service for daemonized backend

### Requirements
- PHP 8.x or higher
- Linux / macOS (Windows not fully supported due to `pcntl_fork`)
- Optional: Nginx for reverse proxy
- Domain with DNS A/CNAME pointing to your server

### Installation
```bash
# Clone your project
git clone <your-repo-url> myapp
cd myapp

# Make server script executable
chmod +x server.php

# Start the server manually (development)
php server.php
```

### Production Setup (Recommended)

1. **Systemd service for backend server**
```bash
sudo nano /etc/systemd/system/myapp.service
```
Paste:
```
[Unit]
Description=Mini PHP App Server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/myapp/server.php 8080
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
sudo systemctl status myapp
```

2. **Nginx Reverse Proxy with ACME challenge support**
```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    # ACME challenge directory for Certbot
    location /.well-known/acme-challenge/ {
        root /var/www/myapp;
        allow all;
    }

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

3. **Enable firewall**
```bash
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
sudo ufw status
```

4. **Install Certbot and obtain SSL certificate**
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com --non-interactive --agree-tos -m admin@yourdomain.com
sudo systemctl reload nginx
```

### Usage
- Access in browser: `http://localhost:8080` (dev) or `https://yourdomain.com` (production)
- Works with your existing framework views and controllers

### Notes
- Restart workers periodically to prevent memory leaks
- ACME challenge `.well-known/acme-challenge` directory must be accessible for SSL
- Windows is not fully supported (use WSL or Linux/macOS)
- Ensure your DNS records for `domain.com` and `www.domain.com` point to your server

---

## Türkçe Versiyon

### Tanım
> **Mini PHP Uygulama Sunucusu** – Kendi frameworkünüz için geliştirilmiş, uzun ömürlü ve hafif bir PHP sunucusudur. Uygulama bir kez boot edilir ve RAM'de kalarak yüksek performanslı istek yönetimi sağlar. Multi-worker ve event-loop mimarisi ile Laravel Octane veya RoadRunner'a minimalist bir alternatiftir.

### Özellikler
- Uzun ömürlü PHP uygulaması (bir kez boot)
- Çoklu worker desteği ile paralel istekler
- Event-loop mimarisi ile non-blocking bağlantılar
- Request sonrası reset mekanizması ile state sızıntısını önler
- Minimalist ve hafif
- Certbot ile otomatik SSL desteği (ACME challenge)
- Systemd servisi ile daemon backend

### Gereksinimler
- PHP 8.x veya üstü
- Linux / macOS (Windows tam desteklenmez, `pcntl_fork` nedeniyle)
- Opsiyonel: Reverse proxy için Nginx
- Domain ve DNS A/CNAME kaydı sunucuya yönlendirilmiş olmalı

### Kurulum
```bash
# Projeyi klonla
git clone <repo-url> myapp
cd myapp

# Server script'i çalıştırılabilir yap
chmod +x server.php

# Server'ı manuel başlat (geliştirme)
php server.php
```

### Üretim Ortamı Kurulumu (Önerilen)

1. **Systemd servisi oluştur**
```bash
sudo nano /etc/systemd/system/myapp.service
```
İçeriğe yapıştır:
```
[Unit]
Description=Mini PHP App Server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/myapp/server.php 8080
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
sudo systemctl status myapp
```

2. **Nginx Reverse Proxy + ACME challenge**
```nginx
server {
    listen 80;
    server_name siteadresiniz.com www.siteadresiniz.com;

    # Certbot doğrulama klasörü
    location /.well-known/acme-challenge/ {
        root /var/www/myapp;
        allow all;
    }

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

3. **Firewall açma**
```bash
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
sudo ufw status
```

4. **Certbot ile SSL alma**
```bash
sudo apt install certbot python3-certbot-nginx -y
sudo certbot --nginx -d siteadresiniz.com -d www.siteadresiniz.com --non-interactive --agree-tos -m admin@siteadresiniz.com
sudo systemctl reload nginx
```

### Kullanım
- Tarayıcıdan erişim: `http://localhost:8080` (dev) veya `https://siteadresiniz.com` (prod)
- Mevcut framework view ve controller'larınızla çalışır

### Notlar
- Worker'ları periyodik olarak restart et, RAM sızıntısını önler
- ACME challenge `.well-known/acme-challenge` dizini erişilebilir olmalı
- Windows tam desteklenmez (WSL veya Linux/macOS önerilir)
- DNS kayıtlarının `domain.com` ve `www.domain.com` sunucuya yönlendiğinden emin ol