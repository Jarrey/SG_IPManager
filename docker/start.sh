#!/bin/sh
set -e

DATA_DIR=/var/www/html/data
mkdir -p "$DATA_DIR"
chown -R www-data:www-data "$DATA_DIR"

load_env() {
  if [ -f /var/www/html/.env ]; then
    while IFS='=' read -r key value; do
      case "$key" in
        ''|\#*) continue ;;
        export*) key=${key#export } ;;
      esac
      export "$key=$value"
    done < /var/www/html/.env
  fi
}

load_env

HTTP_PORT=${HTTP_PORT:-8080}
HTTPS_PORT=${HTTPS_PORT:-}
SSL_CERT_PATH=${SSL_CERT_PATH:-/etc/nginx/ssl/server.crt}
SSL_KEY_PATH=${SSL_KEY_PATH:-/etc/nginx/ssl/server.key}

generate_nginx_config() {
  cat > /etc/nginx/nginx.conf <<EOF
worker_processes 1;
error_log /var/log/nginx/error.log warn;
pid /tmp/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;
    sendfile      on;
    keepalive_timeout 65;
    client_max_body_size 10m;

    server {
        listen ${HTTP_PORT};
        server_name localhost;
        root /var/www/html;
        index index.php index.html;

        access_log /dev/stdout;
        error_log  /dev/stderr warn;

        # Deny direct access to data directory
        location ^~ /data/ {
            deny all;
            return 404;
        }

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php\$ {
            fastcgi_pass  127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include       fastcgi_params;
        }

        location ~ /\. {
            deny all;
        }
    }
EOF

  if [ -n "$HTTPS_PORT" ] && [ -f "$SSL_CERT_PATH" ] && [ -f "$SSL_KEY_PATH" ]; then
    cat >> /etc/nginx/nginx.conf <<EOF

    server {
        listen ${HTTPS_PORT} ssl;
        http2 on;
        server_name localhost;
        root /var/www/html;
        index index.php index.html;

        ssl_certificate     ${SSL_CERT_PATH};
        ssl_certificate_key ${SSL_KEY_PATH};
        ssl_session_cache   shared:SSL:10m;
        ssl_session_timeout 1d;
        ssl_protocols       TLSv1.2 TLSv1.3;
        ssl_ciphers         HIGH:!aNULL:!MD5;
        ssl_prefer_server_ciphers on;
        add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

        access_log /dev/stdout;
        error_log  /dev/stderr warn;

        location ^~ /data/ {
            deny all;
            return 404;
        }

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php\$ {
            fastcgi_pass  127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include       fastcgi_params;
        }

        location ~ /\. {
            deny all;
        }
    }
EOF
  fi

  echo '}' >> /etc/nginx/nginx.conf
}

generate_nginx_config

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx on port ${HTTP_PORT}..."
exec nginx -g 'daemon off;'
