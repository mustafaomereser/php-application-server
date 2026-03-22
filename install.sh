#!/bin/bash

echo "🚀 Starting full automatic setup..."

read -p "Enter app name (default: myphpapp): " APP_NAME
APP_NAME=${APP_NAME:-myphpapp}

read -p "Enter backend port (default: 8080): " PORT
PORT=${PORT:-8080}

read -p "Enter number of workers (default: 4): " WORKERS
WORKERS=${WORKERS:-4}

read -p "Enter max requests per worker (default: 1000): " MAX_REQUESTS
MAX_REQUESTS=${MAX_REQUESTS:-1000}

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

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# systemd
SYSTEMCTL_BIN=$(which systemctl)
if [ -z "$SYSTEMCTL_BIN" ]; then
    echo "❌ systemd/systemctl not found."
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

# OPcache aktif et
echo "⚡ Enabling OPcache for CLI..."
PHP_INI="/etc/php/$PHP_VERSION/cli/php.ini"
if [ -f "$PHP_INI" ]; then
    sudo sed -i 's/;opcache.enable=.*/opcache.enable=1/' $PHP_INI
    sudo sed -i 's/;opcache.enable_cli=.*/opcache.enable_cli=1/' $PHP_INI
    sudo sed -i 's/;opcache.memory_consumption=.*/opcache.memory_consumption=128/' $PHP_INI
    sudo sed -i 's/;opcache.interned_strings_buffer=.*/opcache.interned_strings_buffer=8/' $PHP_INI
    sudo sed -i 's/;opcache.max_accelerated_files=.*/opcache.max_accelerated_files=10000/' $PHP_INI
    sudo sed -i 's/;opcache.revalidate_freq=.*/opcache.revalidate_freq=0/' $PHP_INI
    sudo sed -i 's/;opcache.validate_timestamps=.*/opcache.validate_timestamps=0/' $PHP_INI

    # Eğer satır yoksa ekle
    grep -q "opcache.enable_cli" $PHP_INI || echo "
[opcache]
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=0
opcache.validate_timestamps=0" | sudo tee -a $PHP_INI > /dev/null

    echo "✅ OPcache enabled"
else
    echo "⚠️ php.ini not found at $PHP_INI, skipping OPcache"
fi

# App dizini
echo "📁 Creating app directory: $APP_DIR"
sudo mkdir -p $APP_DIR
sudo cp -rf . $APP_DIR
sudo chown -R www-data:www-data $APP_DIR

# ACME challenge dizini
echo "🔑 Creating ACME challenge directory"
sudo mkdir -p $APP_DIR/.well-known/acme-challenge
sudo chown -R www-data:www-data $APP_DIR/.well-known

# SSL session ticket key
echo "🔐 Generating SSL session ticket key..."
sudo openssl rand 80 | sudo tee /etc/nginx/ssl_ticket.key > /dev/null
sudo chmod 600 /etc/nginx/ssl_ticket.key
sudo chown root:root /etc/nginx/ssl_ticket.key

# systemd service
if [ "$DAEMON_SUPPORT" = true ]; then
    echo "⚙️ Creating systemd service..."
    SERVICE_FILE="/etc/systemd/system/$SERVICE_NAME.service"

    sudo bash -c "cat > $SERVICE_FILE" <<EOL
[Unit]
Description=Mini PHP App Server ($APP_NAME)
After=network.target

[Service]
ExecStart=$PHP_BIN $APP_DIR/server.php $PORT $WORKERS $MAX_REQUESTS
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
    echo "✅ Service started. Check: sudo systemctl status $SERVICE_NAME"
else
    echo "⚠️ Daemon setup skipped. Run manually: php $APP_DIR/server.php $PORT $WORKERS $MAX_REQUESTS"
fi

# Nginx config
NGINX_CONF="/etc/nginx/sites-available/$APP_NAME"

sudo bash -c "cat > $NGINX_CONF" <<EOL
upstream ${APP_NAME}_backend {
    server 127.0.0.1:$PORT;
    keepalive 32;
    keepalive_requests 1000;
    keepalive_timeout 65s;
}

server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;

    location /.well-known/acme-challenge/ {
        root $APP_DIR;
        allow all;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

server {
    listen 443 ssl;
    server_name $DOMAIN www.$DOMAIN;

    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;
    ssl_session_ticket_key /etc/nginx/ssl_ticket.key;

    location / {
        proxy_pass http://${APP_NAME}_backend;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_buffering off;
        proxy_request_buffering off;
    }
}
EOL

# options-ssl-nginx.conf içinde session tickets'ı aç
sudo sed -i 's/ssl_session_tickets off/ssl_session_tickets on/' /etc/letsencrypt/options-ssl-nginx.conf 2>/dev/null || true

sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
echo "✅ Nginx configured for $DOMAIN"

# SSL
echo "🔒 Obtaining SSL certificate..."
sudo certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos -m admin@$DOMAIN

sudo systemctl reload nginx

echo ""
echo "🔥 Done! Your app is running at https://$DOMAIN"
echo ""
echo "Useful commands:"
echo "  sudo systemctl status $SERVICE_NAME"
echo "  sudo systemctl restart $SERVICE_NAME"
echo "  sudo journalctl -u $SERVICE_NAME -f"