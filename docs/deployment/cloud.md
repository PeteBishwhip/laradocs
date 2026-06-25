---
title: Deploy on Laravel Cloud
description: Deploy your Laradocs site to Laravel Cloud's fully managed platform.
order: 2
---

# Deploying on Laravel Cloud

Laravel Cloud is a fully managed platform — it handles infrastructure, autoscaling, zero-downtime deployments, and SSL automatically. This guide covers everything from creating an application to going live.

> [!NOTE]
> Laravel Cloud requires Laravel 9.x or higher (minimum patch versions: 11.41.3, 10.48.28, 9.52.20) and PHP 8.2+. See [System Requirements](/docs/system-requirements).

## 1. Create an application

In the [Laravel Cloud dashboard](https://cloud.laravel.com), click **New Application**. Connect your GitHub, GitLab, or Bitbucket account, select your repository, name the application, and pick a **Region** close to your users.

Cloud creates a default environment for you automatically and sets it up ready to configure.

## 2. Select a PHP version

In your environment's **General Settings**, choose PHP 8.2, 8.3, 8.4, or 8.5. Use whatever matches your local setup or your `composer.json` minimum.

## 3. Configure environment variables

Add your variables under **Environment → General**. At minimum:

```dotenv
APP_URL=https://your-app.laravel.cloud
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

LARADOCS_ENABLED=true
LARADOCS_ROUTE_PREFIX=docs
```

Generate `APP_KEY` locally with `php artisan key:generate --show` and paste it in.

See [Configuration](/docs/configuration) for the full list of Laradocs options.

> [!IMPORTANT]
> Environment variable changes require a redeployment to take effect. Use **Save & Deploy** after making changes.

## 4. Attach a database

In your environment, go to **Infrastructure → Add database**. Select **MySQL** or **PostgreSQL**, choose an instance size, and name your database.

Once attached, Cloud **automatically injects** the connection credentials into your environment — `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` are set for you with no manual configuration required.

> [!NOTE]
> Laradocs itself doesn't require a database when using the default JSON search engine. Attach one if your app uses Scout (Meilisearch, Algolia, Typesense) or has other database-backed features.

## 5. Configure build and deploy commands

Cloud runs two distinct command stages per deployment.

**Build commands** execute during the image build — dependency installation, asset compilation, and config caching:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan optimize
```

**Deploy commands** run on live infrastructure before traffic switches over. Use this for migrations only:

```bash
php artisan migrate --force
```

> [!WARNING]
> Don't add `queue:restart` to deploy commands — Cloud handles this automatically. Don't add `storage:link` either; the Cloud filesystem is ephemeral and symlinks won't persist across requests.

## 6. Queue workers

If you're using a Scout search driver or your app dispatches background jobs, you'll need queue workers. Cloud offers three approaches:

### Managed Queues (recommended)

Under **Infrastructure → Add queue**, Cloud provisions dedicated worker instances that scale from zero automatically. You only pay for the seconds workers are actively running — a queue at rest costs nothing.

Key settings:

| Setting | Description |
|---|---|
| Queue name | Match your `QUEUE_CONNECTION` driver (`redis` is typical) |
| Polling interval | How often workers check for new jobs (default: 5 seconds) |
| Shutdown timeout | Grace period for in-flight jobs to finish (default: 90 seconds) |

Set `QUEUE_CONNECTION=redis` in your environment variables and Cloud injects the Redis connection details automatically when you also attach a cache.

### App cluster background process

For low-volume workloads, add a background process directly to your app cluster:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

This is simpler but shares resources with web traffic — fine for staging, less ideal for production under load.

## 7. Deploy

Click **Deploy** on your environment overview. Cloud builds a Docker image, runs your build commands, executes deploy commands against your live infrastructure, then brings the new version online with zero downtime.

**Push to Deploy** is on by default — subsequent pushes to your configured branch trigger deployments automatically. Disable it under **Settings → Deployments** and use a deploy hook for CI-driven workflows:

```yaml
# .github/workflows/deploy.yml
- name: Deploy to Cloud
  run: curl -X POST "${{ secrets.CLOUD_DEPLOY_HOOK }}"
```

## 8. Custom domains

After your first successful deployment, your app gets a free `*.laravel.cloud` subdomain. To use a custom domain:

1. Go to **Environment → Domains** and add your domain.
2. Add the DNS record shown (CNAME or TXT for verification).
3. Cloud provisions and renews the SSL certificate automatically.

Your `APP_URL` environment variable should be updated to match the custom domain after it's verified.
