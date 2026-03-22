#!/bin/bash

echo "🚀 Starting full automatic setup..."

read -p "Enter app name (default: myphpapp): " APP_NAME
APP_NAME=${APP_NAME:-myphpapp}

read -p "Enter backend port (default: 8080): " PORT
PORT=${PORT:-8080}

read -p "Enter your domain (e.g., example.com): " DOMAIN
if [ -z "$DOMAIN" ]; then
    echo "❌ Domain is required!"
    exit 1
fi

APP_DIR="/var/www/$APP_NAME"
SERVICE_NAME="$APP_NAME"

# PHP
PHP_BIN=$(which php)
if [ -z "$PHP_BIN" ]; then
    echo "⚠️ PHP not found. Installing..."
    sudo apt update
    sudo apt install php-cli -y
    PHP_BIN=$(which php)
fi
echo "✅ PHP found at $PHP_BIN"

# systemd
SYSTEMCTL_BIN=$(which systemctl)
if [ -z "$SYSTEMCTL_BIN" ]; then
    echo "❌ systemd/systemctl not found. Daemon setup won't work."
    DAEMON_SUPPORT=false
else
    DAEMON_SUPPORT=true
    echo "✅ systemd found"
fi

# Nginx
NGINX_BIN=$(which nginx)
if [ -z "$NGINX_BIN" ]; then
    echo "⚠️ Nginx not found. Installing..."
    sudo apt install nginx -y
fi
echo "✅ Nginx installed"

# Certbot
CERTBOT_BIN=$(which certbot)
if [ -z "$CERTBOT_BIN" ]; then
    echo "⚠️ Certbot not found. Installing..."
    sudo apt install certbot python3-certbot-nginx -y
fi
echo "✅ Certbot installed"

# App dizini
echo "📁 Creating app directory: $APP_DIR"
sudo mkdir -p $APP_DIR
sudo cp -r . $APP_DIR
sudo chown -R www-data:www-data $APP_DIR

# ACME challenge dizini
echo "🔑 Creating ACME challenge directory"
sudo mkdir -p $APP_DIR/.well-known/acme-challenge
sudo chown -R www-data:www-data $APP_DIR/.well-known

# systemd service
if [ "$DAEMON_SUPPORT" = true ]; then
    echo "⚙️ Creating systemd service..."
    SERVICE_FILE="/etc/systemd/system/$SERVICE_NAME.service"

    sudo bash -c "cat > $SERVICE_FILE" <<EOL
[Unit]
Description=Mini PHP App Server ($APP_NAME)
After=network.target

[Service]
ExecStart=$PHP_BIN $APP_DIR/server.php $PORT
WorkingDirectory=$APP_DIR
Restart=always
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
EOL

    sudo systemctl daemon-reload
    sudo systemctl enable $SERVICE_NAME
    sudo systemctl restart $SERVICE_NAME
    echo "✅ Service started. Check status: sudo systemctl status $SERVICE_NAME"
else
    echo "⚠️ Daemon setup skipped. Run manually: php $APP_DIR/server.php $PORT"
fi

# Nginx config — upstream keepalive ile
NGINX_CONF="/etc/nginx/sites-available/$APP_NAME"

sudo bash -c "cat > $NGINX_CONF" <<EOL
upstream ${APP_NAME}_backend {
    server 127.0.0.1:$PORT;
    keepalive 32;
}

server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;

    location /.well-known/acme-challenge/ {
        root $APP_DIR;
        allow all;
    }

    location / {
        proxy_pass http://${APP_NAME}_backend;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_buffering off;
        proxy_request_buffering off;
        proxy_read_timeout 10s;
    }
}
EOL

sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
echo "✅ Nginx configured for $DOMAIN on port 80"

# SSL
sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos -m admin@$DOMAIN

# SSL session cache için nginx.conf'a snippet ekle
sudo bash -c "cat >> /etc/nginx/conf.d/ssl_session.conf" <<EOL
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;
ssl_session_tickets on;
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
EOL

sudo systemctl reload nginx
echo "🔥 Full installation complete! Your app is running at https://$DOMAIN"