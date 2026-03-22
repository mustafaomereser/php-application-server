#!/bin/bash

# Ask user for app name and port
read -p "Enter app name (default: myphpapp): " APP_NAME
APP_NAME=${APP_NAME:-myphpapp}

read -p "Enter port (default: 8080): " PORT
PORT=${PORT:-8080}

APP_DIR="/var/www/$APP_NAME"
SERVICE_NAME="$APP_NAME"
PHP_BIN=$(which php)
SYSTEMCTL_BIN=$(which systemctl)
NGINX_BIN=$(which nginx)

echo "Starting installation..."
echo "App Name: $APP_NAME"
echo "Port: $PORT"

# Check PHP
if [ -z "$PHP_BIN" ]; then
    echo "❌ PHP not found. Please install PHP first."
    exit 1
fi
echo "✅ PHP found at $PHP_BIN"

# Check systemctl (daemon support)
if [ -z "$SYSTEMCTL_BIN" ]; then
    echo "❌ systemd/systemctl not found. Daemon setup won't work."
    DAEMON_SUPPORT=false
else
    echo "✅ systemd found"
    DAEMON_SUPPORT=true
fi

# Optional Nginx check
if [ -z "$NGINX_BIN" ]; then
    echo "⚠️ Nginx not found. Reverse proxy won't be available."
    NGINX_SUPPORT=false
else
    echo "✅ Nginx found"
    NGINX_SUPPORT=true
fi

# Create application directory
echo "📁 Creating app directory: $APP_DIR"
sudo mkdir -p $APP_DIR
sudo cp -r . $APP_DIR
sudo chown -R www-data:www-data $APP_DIR

# Create systemd service if available
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
    echo "⚠️ Daemon setup skipped. You can run manually: php $APP_DIR/server.php $PORT"
fi

# Optional Nginx setup
if [ "$NGINX_SUPPORT" = true ]; then
    read -p "Do you want to set up Nginx reverse proxy? (y/n): " setup_nginx
    if [ "$setup_nginx" == "y" ]; then
        read -p "Enter your domain (e.g., site.com): " DOMAIN
        NGINX_CONF="/etc/nginx/sites-available/$APP_NAME"

        sudo bash -c "cat > $NGINX_CONF" <<EOL
server {
    listen 80;
    server_name $DOMAIN;

    location / {
        proxy_pass http://127.0.0.1:$PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
    }
}
EOL

        sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
        sudo nginx -t && sudo systemctl restart nginx
        echo "✅ Nginx configured for $DOMAIN"
    fi
else
    echo "⚠️ Skipping Nginx configuration because it's not installed."
fi

echo "🔥 Installation complete."