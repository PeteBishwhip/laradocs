---
title: Deploy on Forge
description: Provision a server and deploy your Laradocs site with Laravel Forge.
order: 1
---

# Deploying on Laravel Forge

Laravel Forge handles server provisioning and deployment so you can focus on your docs. This guide walks from a fresh Forge account to a live docs site.

## 1. Provision a server

From the Forge dashboard, create a new **App Server**. An App Server gives you PHP, Nginx, and database support on a single machine — the right choice for most Laradocs setups.

Select your cloud provider, region, and instance size, then let Forge provision it. Provisioning typically takes a few minutes.

## 2. Create a site

Once your server is ready, go to **Sites** and create a new site:

- **Domain** — your docs domain, or use the free `on-forge.com` subdomain for staging.
- **Web Directory** — `/public`.
- **PHP version** — 8.2 or higher (see [System Requirements](/docs/system-requirements)).

Connect your Git repository during site creation. Forge adds a deploy key to the repository automatically.

> [!TIP]
> The free `on-forge.com` subdomain is proxied through Cloudflare with HTTPS already enabled — useful for staging without any DNS setup.

## 3. Configure environment variables

Open the **Environment** tab for your site and fill in your production values. At minimum:

```dotenv
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...

LARADOCS_ENABLED=true
LARADOCS_ROUTE_PREFIX=docs
```

Generate `APP_KEY` locally with `php artisan key:generate --show` and paste it in.

See [Configuration](/docs/configuration) for the full list of Laradocs options.

## 4. Set up a database

If you're using a Scout search driver or your app needs a database for other reasons, go to **Storage → Database** on your server and create a database. Then add the connection details to your site's environment:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=your_database
DB_USERNAME=forge
DB_PASSWORD=your_password
```

Forge shows the `forge` user password in the database panel.

> [!NOTE]
> Laradocs itself doesn't require a database when using the default JSON search engine. You only need one if your app uses Scout (Meilisearch, Algolia, Typesense) or has other database-backed features.

## 5. Customise the deploy script

Forge generates a deploy script for you. A solid baseline for a Laradocs site:

```bash
cd $FORGE_SITE_PATH

git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize

$RESTART_QUEUES
```

If you build frontend assets, add your build step before `artisan optimize`:

```bash
npm ci && npm run build
```

### Zero-downtime deployments

Enable zero-downtime deployments in **Site Settings** for production. Forge clones each release into a `releases/` directory and only updates the symlink after every step completes — the previous release stays live if anything fails. Your deploy script gains the `$CREATE_RELEASE()` and `$ACTIVATE_RELEASE()` macros:

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci && npm run build

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize

$ACTIVATE_RELEASE()
$RESTART_QUEUES
```

## 6. Queue workers

If your app uses a Scout search driver or dispatches background jobs, set up a queue worker under **Site → Queue Workers**:

| Setting | Value |
|---|---|
| Connection | `redis` or `database` (match your `QUEUE_CONNECTION`) |
| Queue | `default` |
| Timeout | `90` |
| Maximum Tries | `3` |

Forge manages workers under Supervisor, which restarts them automatically on crash or server reboot.

> [!NOTE]
> The `$RESTART_QUEUES` macro in your deploy script runs `php artisan queue:restart` so workers reload your updated code after each deployment.

## 7. Push to deploy

Push to Deploy is enabled by default — every push to your configured branch triggers a deployment. Disable it in **Site → Deployments** if you prefer manual or CI-driven deploys instead.

For CI integration, grab the deployment webhook URL from the Deployments tab and POST to it from your pipeline.

## 8. SSL and custom domains

1. Point your domain's DNS **A record** to your Forge server's IP address.
2. In Forge, go to **Site → SSL** and issue a **Let's Encrypt** certificate.

Forge handles renewal automatically.
