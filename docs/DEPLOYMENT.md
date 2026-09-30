# Deployment

Run this on Ubuntu with Nginx or Apache. Use one of them, not both. Nginx and PHP-FPM, or Apache and PHP-FPM, stay running as system services. `php artisan serve` and `npm run dev` are for a development machine only.

Put the screens and the API on the same HTTPS address. Nginx or Apache serves the built Vue files. Requests to `/api`, `/sanctum`, and `/up` go to Laravel. Login uses cookies, and those cookies are sent only when the browser and the API share one host.

The examples use `https://dpps.example.com` and `/var/www/dpps`. Replace both with the real domain and directory.

## Packages

```bash
sudo apt update
sudo apt install -y nginx mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd \
  unzip git
```

Skip the `nginx` package when the server will use Apache. Install Apache instead, in the Apache section below.

Production PHP is 8.3. Node.js is needed only while building the screens. Install Node.js 22 on the server, or build on another machine and copy `frontend/dist` across.

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
```

Install Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Open only ports 80 and 443:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80
sudo ufw allow 443
sudo ufw enable
```

The default upload limit in Settings is 10 MB. PHP must allow at least that much. In `/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 20M
```

Then restart PHP-FPM after the web server is installed: `sudo systemctl restart php8.3-fpm`.

## Database

Create the database and an application user. Do not use `root` in `backend/.env`.

```sql
CREATE DATABASE dpp_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dpps_app'@'localhost' IDENTIFIED BY 'a-long-password';
GRANT ALL PRIVILEGES ON dpp_management_system.* TO 'dpps_app'@'localhost';
FLUSH PRIVILEGES;
```

After `php artisan migrate --seed`, run `docs/DB_GRANTS.sql` so this account can only insert and read `activity_logs`. Change the account name in that file first if it is not `dpps_app`. Do not run that file with the development root account.

Take a database backup every day and keep 30 days. Back up `backend/storage/app/private` with it, and turn on versioning for that file storage. Restore a backup once a month and confirm the application opens. Keep staging and production separate. Do not copy production data into staging.

## Application

```bash
sudo mkdir -p /var/www/dpps
sudo chown $USER:www-data /var/www/dpps
cd /var/www/dpps
git clone <your-repo-url> .
```

Backend:

```bash
cd /var/www/dpps/backend
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
```

Set these in `backend/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dpps.example.com
FRONTEND_URL=https://dpps.example.com

DB_DATABASE=dpp_management_system
DB_USERNAME=dpps_app
DB_PASSWORD=a-long-password

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_DOMAIN=dpps.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=dpps.example.com

FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
```

`SESSION_DOMAIN` must be the public host. Left as `localhost`, the browser will not send the login cookie.

`routes/web.php` defines `/` with a closure, so `php artisan route:cache` and `php artisan optimize` will fail. Cache config, events, and views only:

```bash
php artisan migrate --seed --force
php artisan config:cache
php artisan event:cache
php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwx storage bootstrap/cache
```

Run `php artisan config:cache` again after any later change to `.env`.

Uploaded files stay on the private disk, `backend/storage/app/private`. Downloads use a one-time link that lasts 5 minutes. There is no public storage link to create.

Frontend. `VITE_API_URL` is copied into the built files, so set it before the build:

```bash
cd /var/www/dpps/frontend
cp .env.example .env
```

```env
VITE_API_URL=https://dpps.example.com
```

```bash
npm ci
npm run build
```

That writes `frontend/dist`. The web server serves those files. Rebuild whenever the screens change, because the API address is fixed at build time.

## Nginx

```bash
sudo nano /etc/nginx/sites-available/dpps
```

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name dpps.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name dpps.example.com;

    ssl_certificate     /etc/letsencrypt/live/dpps.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dpps.example.com/privkey.pem;

    root /var/www/dpps/backend/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20m;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ^~ /api {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ^~ /sanctum {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ^~ /up {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location / {
        root /var/www/dpps/frontend/dist;
        try_files $uri $uri/ /index.html;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

`/api`, `/sanctum`, and `/up` go to Laravel through PHP-FPM. Everything else is the Vue app. `try_files ... /index.html` lets a refresh of a screen such as `/companies` load the app. The site block is the TLS endpoint, so PHP sees the request as HTTPS and the API sends `Strict-Transport-Security`. Plain HTTP does not send that header.

Obtain the certificate before reloading Nginx if those certificate files are not there yet:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot certonly --nginx -d dpps.example.com
sudo ln -s /etc/nginx/sites-available/dpps /etc/nginx/sites-enabled/dpps
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
sudo systemctl enable --now php8.3-fpm nginx
```

## Apache

Install Apache and the modules that proxy the API to PHP-FPM on this machine:

```bash
sudo apt install -y apache2
sudo a2enmod rewrite proxy proxy_http headers remoteip ssl
sudo a2enconf php8.3-fpm
sudo a2dissite 000-default.conf
```

The public site listens on 443 and serves `frontend/dist`. A second virtual host listens only on `127.0.0.1:8080` and serves Laravel from `backend/public`, which is the normal Laravel document root. The public host forwards `/api`, `/sanctum`, and `/up` to that local host. Port 8080 is not opened in the firewall and is not reachable from other machines.

Add this line to `/etc/apache2/ports.conf`:

```apache
Listen 127.0.0.1:8080
```

API host, `/etc/apache2/sites-available/dpps-api.conf`:

```apache
<VirtualHost 127.0.0.1:8080>
    ServerName dpps.example.com
    DocumentRoot /var/www/dpps/backend/public

    RemoteIPHeader X-Forwarded-For
    RemoteIPInternalProxy 127.0.0.1
    SetEnv HTTPS on

    <Directory /var/www/dpps/backend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require local
        CGIPassAuth On
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/dpps-api-error.log
    CustomLog ${APACHE_LOG_DIR}/dpps-api-access.log combined
</VirtualHost>
```

`AllowOverride All` lets `backend/public/.htaccess` send each API request to `index.php` and pass the `Authorization` and `X-XSRF-TOKEN` headers through to PHP. `SetEnv HTTPS on` is safe here because this host only receives requests from the public HTTPS site. PHP then treats the request as secure, and the API sends `Strict-Transport-Security`. `mod_remoteip` puts the browser address in `REMOTE_ADDR`, which login limits and the activity log both use.

Public host, `/etc/apache2/sites-available/dpps.conf`:

```apache
<VirtualHost *:80>
    ServerName dpps.example.com
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/.well-known/
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [L,R=301]
</VirtualHost>

<VirtualHost *:443>
    ServerName dpps.example.com
    DocumentRoot /var/www/dpps/frontend/dist

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/dpps.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/dpps.example.com/privkey.pem

    LimitRequestBody 20971520

    <Directory /var/www/dpps/frontend/dist>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted

        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ /index.html [L]
    </Directory>

    ProxyPreserveHost On
    ProxyPass /api http://127.0.0.1:8080/api
    ProxyPassReverse /api http://127.0.0.1:8080/api
    ProxyPass /sanctum http://127.0.0.1:8080/sanctum
    ProxyPassReverse /sanctum http://127.0.0.1:8080/sanctum
    ProxyPass /up http://127.0.0.1:8080/up
    ProxyPassReverse /up http://127.0.0.1:8080/up
    RequestHeader set X-Forwarded-Proto "https"

    ErrorLog ${APACHE_LOG_DIR}/dpps-error.log
    CustomLog ${APACHE_LOG_DIR}/dpps-access.log combined
</VirtualHost>
```

The rewrite to `/index.html` is the same screen-refresh behaviour as the Nginx `try_files` line. `LimitRequestBody` is 20 MB.

Obtain the certificate, then enable both sites:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot certonly --apache -d dpps.example.com
sudo a2ensite dpps.conf dpps-api.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
sudo systemctl enable --now php8.3-fpm apache2
```

If Certbot rewrites `dpps.conf`, put the `ProxyPass` lines back on the port 443 virtual host.

## Scheduler

The status refresh is scheduled for 00:30. Cron starts it. Do not leave `php artisan schedule:work` running in a terminal.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/dpps/backend && php artisan schedule:run >> /dev/null 2>&1
```

The queue connection is `database`. The application does not dispatch queued jobs, so the screens and the nightly refresh do not need `php artisan queue:work`.

## What stays running

| Piece | How it stays up |
|---|---|
| Vue screens | Nginx or Apache serves `frontend/dist`. No Node process. |
| Laravel API | PHP-FPM. No `php artisan serve`. |
| Nightly status refresh | Cron, once a minute. |
| MySQL | `mysql` service. |

## After a code update

```bash
cd /var/www/dpps
git pull
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan view:cache
cd ../frontend
npm ci
npm run build
```

Reload Nginx with `sudo systemctl reload nginx`, or Apache with `sudo systemctl reload apache2`, after the build.
