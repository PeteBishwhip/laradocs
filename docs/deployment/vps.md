---
title: Deploy on a VPS
description: Deployment checklist for self-hosted Laradocs on any server you manage.
order: 3
---

# Deploying on a VPS

This guide covers the Laradocs-specific steps for deploying to a self-managed server. It assumes PHP, a web server (Nginx or Apache), Composer, and Node.js are already installed and configured. See [System Requirements](/docs/system-requirements) for the full list of prerequisites.

## 1. Clone and install

```bash
git clone git@github.com:your-org/your-repo.git /var/www/yoursite
cd /var/www/yoursite

composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
```

Point your web server's document root at `/var/www/yoursite/public`.

## 2. Set up the environment file

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your production values. At minimum:

```dotenv
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

LARADOCS_ENABLED=true
LARADOCS_ROUTE_PREFIX=docs
```

See [Configuration](/docs/configuration) for the full list of Laradocs options.

## 3. Migrate and optimise

```bash
php artisan migrate --force
php artisan optimize
php artisan storage:link
```

`php artisan optimize` caches your config, routes, views, and events — always run it on production.

## 4. Queue workers

If you're using a Scout search driver or your app dispatches background jobs, you need a persistent worker process. The standard tool for this is [Supervisor](http://supervisord.org/).

Create a config file at `/etc/supervisor/conf.d/yoursite-worker.conf`:

```ini
[program:yoursite-worker]
command=php /var/www/yoursite/artisan queue:work --sleep=3 --tries=3 --timeout=90
directory=/var/www/yoursite
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/yoursite-worker.log
```

Load it:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start yoursite-worker:*
```

> [!NOTE]
> Laradocs doesn't require a queue worker when using the default JSON search engine. You only need one if your app uses Scout (Meilisearch, Algolia, Typesense) or dispatches other background jobs.

## 5. Task scheduler

Add a single cron entry to run Laravel's scheduler every minute:

```
* * * * * www-data php /var/www/yoursite/artisan schedule:run >> /dev/null 2>&1
```

## 6. Deploying updates

A typical update sequence:

```bash
cd /var/www/yoursite
git pull origin main
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Wrap this in a shell script and trigger it via a CI/CD webhook to automate the process.
