# Deploy on Hostinger shared hosting

This app is a Laravel API in `backend` and a Vue app in `frontend`. Hostinger shared hosting serves one web folder, `public_html`. The API stays outside that folder. The web folder serves the built screens, and sends `/api` and `/sanctum` to Laravel.

Use a plan with SSH. Select PHP 8.3. Do not use the Laravel Auto Installer. That installs an empty Laravel and does not deploy this project.

Skip `docs/DB_GRANTS.sql` on Hostinger. The database user created in hPanel cannot change its own privileges.

Replace `your-domain.com` and `u123456789` below with the real domain and the Hostinger username.

## 1. Prepare the hosting account

In hPanel, for the domain:

1. Set the PHP version to **8.3**.
2. Enable the extensions `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `fileinfo`, and `gd`.
3. Create a MySQL database and a user. Write down the host (usually `localhost`), database name, username, and password.
4. Turn on the free SSL certificate and force HTTPS.
5. Turn on SSH.

## 2. Build the screens on your computer

The API address is stored in the frontend at build time. Set it to the live site before building.

```bash
cd frontend
printf '%s\n' 'VITE_API_URL=https://your-domain.com' > .env.production
npm install
npm run build
```

The files to upload are in `frontend/dist`.

## 3. Upload the API

Connect with SSH. The domain folder is similar to `/home/u123456789/domains/your-domain.com/`.

Upload the `backend` folder there and name it `dpps`. Keep it next to `public_html`, not inside it.

Do not upload `backend/.env`, `backend/vendor`, `frontend/node_modules`, or `backend/node_modules`.

On the server:

```bash
cd ~/domains/your-domain.com/dpps
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

If `php` is not 8.3, use the binary shown in hPanel. It is often `/opt/alt/php83/usr/bin/php`.

Edit `dpps/.env`:

```env
APP_NAME="Digital Plant Protection System"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
FRONTEND_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=the_database_from_hpanel
DB_USERNAME=the_user_from_hpanel
DB_PASSWORD=the_password_from_hpanel

SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=your-domain.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=your-domain.com

FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database
```

`APP_URL`, `FRONTEND_URL`, `SESSION_DOMAIN`, and `SANCTUM_STATEFUL_DOMAINS` must all be that same HTTPS domain, with no port. Login cookies will not stick if one of them still says `localhost`.

Then:

```bash
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R ug+rwx storage bootstrap/cache
```

`migrate --seed` creates the Super Admin and the reference data. Run it once. The password is in `docs/CLARIFICATIONS.md`. Later updates use `php artisan migrate` only, without `--seed`.

## 4. Publish the site into public_html

Copy the contents of `dpps/public` into `public_html`. Then copy the contents of `frontend/dist` into the same `public_html`.

The folder then contains both `index.php` (Laravel) and `index.html` (the screens).

Replace `public_html/index.php` with:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../dpps/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../dpps/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../dpps/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

Replace `public_html/.htaccess` with:

```apache
<IfModule mod_rewrite.c>
    Options -MultiViews -Indexes
    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    RewriteRule ^api(/|$) index.php [L]
    RewriteRule ^sanctum(/|$) index.php [L]
    RewriteRule ^up$ index.php [L]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.html [L]
</IfModule>
```

`/api` and `/sanctum` are handled by Laravel. Every other address loads the Vue app.

## 5. Nightly status job

In hPanel, add a cron job that runs every minute:

```bash
/opt/alt/php83/usr/bin/php /home/u123456789/domains/your-domain.com/dpps/artisan schedule:run
```

Use the PHP 8.3 path from hPanel if it differs. This runs the status refresh at 00:30. This app does not need a queue worker.

## 6. Check the site

1. Open `https://your-domain.com`.
2. Sign in as the Super Admin.
3. Open Companies, then Dealers. Both should load.
4. Open `https://your-domain.com/api/v1/health`. The response should say the API and database are connected.

If the login form returns with no message, check the four domain settings in `dpps/.env`, then run:

```bash
cd ~/domains/your-domain.com/dpps
php artisan config:cache
```

## 7. Later updates

1. Upload the changed files under `dpps`. Do not replace `dpps/.env`.
2. On the server, run `composer install --no-dev --optimize-autoloader` and `php artisan migrate`.
3. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
4. On your computer, run `npm run build` in `frontend` with `VITE_API_URL=https://your-domain.com`.
5. Replace the built files in `public_html`. Keep the edited `index.php` and `.htaccess`.

Do not upload the local `.env`. Do not run `migrate --seed` again.
