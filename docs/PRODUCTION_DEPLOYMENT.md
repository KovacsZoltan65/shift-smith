# Production Deployment Útmutató

## Biztonsági Beállítások Checklist

### 1. Environment Konfiguráció

#### Kötelező Változtatások `.env` fájlban:

```env
# Debug Mode - KRITIKUS!
APP_DEBUG=false
APP_ENV=production

# Session Security
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

# HTTPS
APP_URL=https://yourdomain.com

# Logging
LOG_LEVEL=error
LOG_STACK=daily
```

### 2. Session Konfiguráció

**Fájl**: `config/session.php`

Production környezetben győződj meg róla, hogy:

```php
'encrypt' => env('SESSION_ENCRYPT', true),  // Production: true
'secure' => env('SESSION_SECURE_COOKIE', true),  // Production: true
'http_only' => env('SESSION_HTTP_ONLY', true),  // Mindig true
'same_site' => env('SESSION_SAME_SITE', 'lax'),  // lax vagy strict
```

### 3. HTTPS Beállítás

#### Apache (.htaccess)

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

#### Nginx

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
}
```

### 4. Database Konfiguráció

```env
# Production Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=production_db
DB_USERNAME=secure_user
DB_PASSWORD=very_strong_password_here
```

**Biztonsági Tippek**:

- Használj erős jelszót (min 20 karakter, vegyes)
- Ne használd a root felhasználót
- Korlátozd a felhasználó jogosultságait csak a szükségesre

### 5. Cache Konfiguráció

```env
# Production Cache
CACHE_STORE=redis
CACHE_PREFIX=shiftsmith_prod_

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=strong_redis_password
REDIS_PORT=6379
```

### 6. Deployment Lépések

#### 1. Kód Frissítés

```bash
git pull origin main
```

#### 2. Composer Függőségek

```bash
composer install --no-dev --optimize-autoloader
```

#### 3. NPM Build

```bash
npm ci
npm run build
```

#### 4. Laravel Optimalizálás

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

#### 5. Migrációk

```bash
php artisan migrate --force
```

#### 6. Storage Link

```bash
php artisan storage:link
```

#### 7. Jogosultságok

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. Biztonsági Headers

**Fájl**: `app/Http/Middleware/SecurityHeaders.php` (létrehozandó)

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
```

Regisztráld a middleware-t `bootstrap/app.php`-ban:

```php
$middleware->web(append: [
    \App\Http\Middleware\SecurityHeaders::class,
]);
```

### 8. Monitoring és Logging

#### Log Rotation

```bash
# /etc/logrotate.d/laravel
/path/to/laravel/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

#### Error Tracking

Fontold meg egy error tracking szolgáltatás használatát:

- Sentry
- Bugsnag
- Rollbar

### 9. Backup Stratégia

#### Database Backup

```bash
# Cron job - naponta 2:00-kor
0 2 * * * /usr/bin/mysqldump -u user -p'password' database > /backups/db_$(date +\%Y\%m\%d).sql
```

#### Application Backup

```bash
# Teljes alkalmazás backup
tar -czf /backups/app_$(date +\%Y\%m\%d).tar.gz /path/to/laravel
```

### 10. Performance Optimalizálás

#### OPcache Beállítások (php.ini)

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
```

#### Redis Cache

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 11. Security Checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] HTTPS bekapcsolva
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `SESSION_ENCRYPT=true`
- [ ] Erős database jelszó
- [ ] Redis jelszó beállítva
- [ ] CSRF védelem aktív
- [ ] Rate limiting beállítva
- [ ] Security headers beállítva
- [ ] File permissions helyesek (755/644)
- [ ] `.env` fájl nem elérhető kívülről
- [ ] Backup rendszer működik
- [ ] Monitoring beállítva
- [ ] SSL certificate érvényes
- [ ] Firewall konfigurálva

### 12. Post-Deployment Ellenőrzés

```bash
# Ellenőrizd a konfigurációt
php artisan config:show

# Ellenőrizd a route-okat
php artisan route:list

# Ellenőrizd a queue-t
php artisan queue:work --once

# Ellenőrizd a cache-t
php artisan cache:clear
php artisan config:cache
```

### 13. Rollback Terv

Ha valami elromlik:

```bash
# 1. Állítsd vissza a kódot
git reset --hard HEAD~1

# 2. Töröld a cache-t
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Állítsd vissza a database-t
mysql -u user -p database < /backups/db_backup.sql

# 4. Restart services
sudo systemctl restart php8.2-fpm
sudo systemctl restart nginx
```

## Támogatás

Ha problémád van a deployment során, ellenőrizd:

1. Laravel log fájlokat: `storage/logs/laravel.log`
2. Web server log fájlokat: `/var/log/nginx/error.log`
3. PHP-FPM log fájlokat: `/var/log/php8.2-fpm.log`
