# Mini PHP Application Server / Mini PHP Uygulama Sunucusu

## English Version

### Description
> **Mini PHP Application Server** – A lightweight, long-running PHP server built for your custom framework. The application boots once and stays in memory, handling requests at high performance. It uses a multi-worker, event-loop architecture, providing a minimalist alternative to Laravel Octane or RoadRunner.

### Features
- Long-running PHP application (boot once)
- Multi-worker support for concurrent requests
- Event-loop architecture (stream_select, non-blocking)
- Keep-alive connection support
- Automatic superglobal population ($_GET, $_POST, $_FILES, $_SERVER, $_REQUEST)
- File upload support (multipart/form-data)
- JSON and form-urlencoded body parsing
- Request reset mechanism to prevent state leakage
- Memory leak prevention via configurable max requests per worker
- ACME challenge support for automatic SSL via Certbot
- Systemd service for daemonized backend
- Nginx upstream keepalive for maximum performance

### Requirements
- PHP 8.x or higher
- Linux / macOS (Windows not fully supported due to `pcntl_fork`)
- Nginx for reverse proxy
- Domain with DNS A/CNAME pointing to your server

### Installation
```bash
# Clone your project
git clone <your-repo-url> myapp
cd myapp

# Make scripts executable
chmod +x server.php install.sh

# Start the server manually (development)
php server.php
# or with custom options:
php server.php [port] [workers] [maxRequests]
# example: php server.php 8080 4 1000
```

### Parameters
| Parameter | Default | Description |
|-----------|---------|-------------|
| port | 8080 | TCP port to listen on |
| workers | 4 | Number of worker processes |
| maxRequests | 1000 | Requests per worker before restart (memory leak prevention) |

### Production Setup (Recommended)
```bash
# Run the automated installer
bash install.sh
```

The installer will prompt for:
- App name (used for directory, service, and Nginx config names)
- Backend port
- Domain name

It will automatically:
1. Install PHP, Nginx, Certbot if missing
2. Copy app to `/var/www/<appname>/`
3. Create and enable systemd service
4. Configure Nginx with upstream keepalive
5. Obtain SSL certificate via Certbot
6. Enable SSL session tickets for faster reconnects

### Manual Production Setup

1. **Systemd service**
```bash
sudo nano /etc/systemd/system/myapp.service
```
```ini
[Unit]
Description=Mini PHP App Server
After=network.target

[Service]
ExecStart=/usr/bin/php /var/www/myapp/server.php 8080 4 1000
WorkingDirectory=/var/www/myapp
Restart=always
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemctl daemon-reload
sudo systemctl enable myapp
sudo systemctl start myapp
```

2. **Nginx config**
```nginx
upstream myapp_backend {
    server 127.0.0.1:8080;
    keepalive 32;
    keepalive_requests 1000;
    keepalive_timeout 65s;
}

server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    location /.well-known/acme-challenge/ {
        root /var/www/myapp;
        allow all;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl;
    server_name yourdomain.com www.yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    ssl_session_ticket_key /etc/nginx/ssl_ticket.key;

    location / {
        proxy_pass http://myapp_backend;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_buffering off;
        proxy_request_buffering off;
    }
}
```

3. **SSL session ticket key**
```bash
sudo openssl rand 80 | sudo tee /etc/nginx/ssl_ticket.key > /dev/null
sudo chmod 600 /etc/nginx/ssl_ticket.key
```

4. **Enable SSL session tickets in Certbot config**
```bash
sudo sed -i 's/ssl_session_tickets off/ssl_session_tickets on/' /etc/letsencrypt/options-ssl-nginx.conf
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

### Service Management

```bash
# Start
sudo systemctl start myapp

# Stop
sudo systemctl stop myapp

# Restart
sudo systemctl restart myapp

# Reload Nginx
sudo systemctl reload nginx

# Restart both
sudo systemctl restart myapp && sudo systemctl reload nginx

# Status
sudo systemctl status myapp

# Live logs
sudo journalctl -u myapp -f
```

---

### Testing & Debugging

**Basic response test (bypass Nginx):**
```bash
curl -w "\nTTFB: %{time_starttransfer}s | Total: %{time_total}s\n" http://localhost:8080/ -s -o /dev/null
```

**Keep-alive test (bypass Nginx):**
```bash
curl -w "\nTTFB: %{time_starttransfer}s | Total: %{time_total}s\n" \
  -H "Connection: keep-alive" http://localhost:8080/ -s -o /dev/null
```

**Full HTTPS test:**
```bash
curl -w "\nTTFB: %{time_starttransfer}s | Total: %{time_total}s\n" \
  https://yourdomain.com/ -s -o /dev/null
```

**Detailed timing breakdown:**
```bash
curl -w "\ndns: %{time_namelookup}s | connect: %{time_connect}s | ssl: %{time_appconnect}s | ttfb: %{time_starttransfer}s | total: %{time_total}s\n" \
  https://yourdomain.com/ -s -o /dev/null
```

**Keep-alive with multiple requests:**
```bash
curl -w "\nTTFB: %{time_starttransfer}s\n" https://yourdomain.com/ -s -o /dev/null
curl -w "\nTTFB: %{time_starttransfer}s\n" https://yourdomain.com/ -s -o /dev/null --keepalive-time 30
curl -w "\nTTFB: %{time_starttransfer}s\n" https://yourdomain.com/ -s -o /dev/null --keepalive-time 30
```

**Check response headers:**
```bash
curl -v http://localhost:8080/ 2>&1 | grep "< "
curl -v -H "Connection: keep-alive" http://localhost:8080/ 2>&1 | grep "< "
```

**Check Nginx upstream keepalive (new TCP connections per request):**
```bash
# Terminal 1 - watch for SYN packets
sudo tcpdump -i lo port 8080 -n 2>/dev/null | grep "Flags \[S\]"

# Terminal 2 - send requests
curl -s -o /dev/null https://yourdomain.com/
curl -s -o /dev/null https://yourdomain.com/
curl -s -o /dev/null https://yourdomain.com/
# If keepalive works: only 1 SYN per session, not per request
```

**Check what Nginx sends upstream:**
```bash
sudo tcpdump -i lo port 8080 -A -n 2>/dev/null | grep "Connection:"
# Should be empty (Connection header stripped) not "Connection: close"
```

**Monitor worker CPU and memory:**
```bash
top -p $(pgrep -d',' php)
# All workers should show ~0% CPU when idle, S (sleeping) state
```

**Nginx timing log setup:**
```bash
# Add to http{} block in /etc/nginx/nginx.conf:
# log_format timing '$remote_addr "$request" $status upstream_response=$upstream_response_time request=$request_time';
# access_log /var/log/nginx/timing.log timing;

sudo tail -f /var/log/nginx/timing.log
```

**Check Nginx config:**
```bash
sudo nginx -T | grep -E "upstream|keepalive|proxy_http_version|Connection"
```

**Check SSL session tickets:**
```bash
curl -v https://yourdomain.com/ 2>&1 | grep -i "SSL\|reuse\|ticket\|session"
```

---

### Notes
- TTFB from PHP server itself should be under 2ms
- TTFB including Nginx proxy overhead should be under 5ms
- Remaining latency is network round-trip (unavoidable)
- Workers restart automatically after `maxRequests` to prevent memory leaks
- Systemd `Restart=always` ensures workers are always running
- Windows is not supported (use WSL or Linux/macOS)
- `$_FILES` tmp files are automatically cleaned up after each request

---

## Türkçe Versiyon

### Tanım
> **Mini PHP Uygulama Sunucusu** – Kendi frameworkünüz için geliştirilmiş, uzun ömürlü ve hafif bir PHP sunucusudur. Uygulama bir kez boot edilir ve RAM'de kalarak yüksek performanslı istek yönetimi sağlar. Multi-worker ve event-loop mimarisi ile Laravel Octane veya RoadRunner'a minimalist bir alternatiftir.

### Özellikler
- Uzun ömürlü PHP uygulaması (bir kez boot)
- Çoklu worker desteği ile paralel istekler
- Event-loop mimarisi (stream_select, non-blocking)
- Keep-alive bağlantı desteği
- Otomatik superglobal doldurma ($_GET, $_POST, $_FILES, $_SERVER, $_REQUEST)
- Dosya yükleme desteği (multipart/form-data)
- JSON ve form-urlencoded body parse
- Request sonrası reset mekanizması ile state sızıntısını önler
- Yapılandırılabilir max request ile memory leak önlemi
- Certbot ile otomatik SSL desteği (ACME challenge)
- Systemd servisi ile daemon backend
- Maksimum performans için Nginx upstream keepalive

### Gereksinimler
- PHP 8.x veya üstü
- Linux / macOS (Windows tam desteklenmez, `pcntl_fork` nedeniyle)
- Reverse proxy için Nginx
- Domain ve DNS A/CNAME kaydı sunucuya yönlendirilmiş olmalı

### Kurulum
```bash
# Projeyi klonla
git clone <repo-url> myapp
cd myapp

# Script'leri çalıştırılabilir yap
chmod +x server.php install.sh

# Server'ı manuel başlat (geliştirme)
php server.php
# veya özel parametrelerle:
php server.php [port] [worker_sayisi] [maxIstek]
# örnek: php server.php 8080 4 1000
```

### Parametreler
| Parametre | Varsayılan | Açıklama |
|-----------|------------|----------|
| port | 8080 | Dinlenecek TCP portu |
| workers | 4 | Worker process sayısı |
| maxRequests | 1000 | Worker başına max istek (memory leak önlemi) |

### Üretim Ortamı Kurulumu (Önerilen)
```bash
bash install.sh
```

### Servis Yönetimi

```bash
# Başlat
sudo systemctl start myapp

# Durdur
sudo systemctl stop myapp

# Yeniden başlat
sudo systemctl restart myapp

# Nginx yenile
sudo systemctl reload nginx

# İkisini birden yeniden başlat
sudo systemctl restart myapp && sudo systemctl reload nginx

# Durum
sudo systemctl status myapp

# Canlı log
sudo journalctl -u myapp -f
```

### Test ve Hata Ayıklama

**Temel test (Nginx bypass):**
```bash
curl -w "\nTTFB: %{time_starttransfer}s | Total: %{time_total}s\n" http://localhost:8080/ -s -o /dev/null
```

**Detaylı zamanlama:**
```bash
curl -w "\ndns: %{time_namelookup}s | connect: %{time_connect}s | ssl: %{time_appconnect}s | ttfb: %{time_starttransfer}s | total: %{time_total}s\n" \
  https://yourdomain.com/ -s -o /dev/null
```

**Worker CPU/RAM izleme:**
```bash
top -p $(pgrep -d',' php)
```

**Nginx upstream keepalive kontrolü:**
```bash
sudo tcpdump -i lo port 8080 -n 2>/dev/null | grep "Flags \[S\]"
```

### Notlar
- PHP server TTFB 2ms altında olmalı
- Nginx dahil TTFB 5ms altında olmalı
- Kalan gecikme network round-trip (önlenemez)
- Worker'lar `maxRequests` sonrası otomatik restart atar
- Windows desteklenmez (WSL veya Linux/macOS kullanın)
- `$_FILES` tmp dosyaları her request sonrası otomatik temizlenir