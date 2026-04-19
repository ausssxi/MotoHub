# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

MotoHub (motohub.jp) is a Japanese motorcycle marketplace and information platform. It aggregates used bike listings from GooBike, BDS, and Webike, and provides model catalogs, shop/parking maps, reviews, rankings, blog, and browser games. All user-facing text is in Japanese.

## Repository Structure

```
motohub/
├── backend/           # Laravel 12 app (PHP 8.3) — this is the Laravel root
├── scraper/           # Python scrapers (Scrapy/httpx) for listing collection
├── bot/               # Twitter/X bot service
├── docker/            # nginx + php-fpm config
└── docker-compose.yml # MySQL 8, Meilisearch, Redis, MailHog
```

Two `.env` files: root `.env` (Docker Compose ports/DB) and `backend/.env` (Laravel app config).

## Common Commands

All Laravel/frontend commands run from `backend/` directory:

```bash
# Start all dev services concurrently (server, queue, logs, vite)
composer dev

# Or individually
php artisan serve
npm run dev

# Tests (Pest v3, SQLite in-memory)
composer test                          # all tests
php artisan test --filter=TestName     # single test
php artisan test --testsuite=Feature   # by suite

# Code style
./vendor/bin/pint

# Frontend build
npm run build

# Cache management
php artisan optimize:clear   # clear all caches
php artisan optimize         # cache config/routes/views for production

# Meilisearch
php artisan scout:import "App\Models\Listing"
php artisan scout:sync-index-settings

# Docker
docker compose up -d
docker compose exec app bash
```

## Architecture

### Backend (Laravel 12)

**Pattern**: Repository + Service layer. Controllers are thin, delegating to Services (business logic) and Repositories (data access).

- `app/Repositories/` — Bike, MyBike, Parking, Shop
- `app/Services/` — Domain services organized by feature (Bike/, Line/, MyBike/, Parts/, Shop/, etc.)
- `app/Http/Controllers/` — Organized by domain: `Admin/`, `Api/`, `Bike/`, `Shop/`, `Parking/`, `Blog/`, etc.
- `app/Console/Commands/` — 30+ Artisan commands for scrapers, AI content generation, Twitter bot, notifications
- `app/Filament/` — Admin panel (Filament v3, requires `is_admin` on User)

**Key models**: `BikeModel`, `Listing` (Scout/Meilisearch searchable), `Manufacturer`, `Shop`, `BikeParking`, `Review`, `BlogPost` (SoftDeletes), `User`

**Routes** are split across: `web.php`, `api.php`, `blog.php`, `auth.php`, `console.php`

**Auth**: Breeze (email/password) + Socialite (Google, LINE OAuth). Roles: `admin`, `writer`, regular user. Blog management uses `manage-blog` gate.

**Caching**: Heavy `Cache::remember()` usage for rankings, popular bikes, market stats. N+1 prevention with eager loading throughout.

### Frontend

- **Primary**: Blade templates + Alpine.js + Tailwind CSS v3
- **React islands**: Quiz game (`quiz-app.jsx`) and Warashibe game (`warashibe-app.jsx`) — separate Vite entry points rendered into Blade via `@vite()`
- Vite 7 with `laravel-vite-plugin`

### Scraper (Python)

Runs inside the same Docker container at `/var/scraper`. Orchestrated by `scraper/main.py`. Sources: GooBike, BDS, Webike, Bikebros (specs).

### Key Integrations

- **Anthropic Claude** — AI-generated bike model content/history
- **OpenAI GPT-4 Vision** — Bike identification from photos
- **Meilisearch** — Full-text listing search (filterable/sortable attributes in `config/scout.php`)
- **Cloudflare** — CDN with cache purging on blog post updates (via `CloudflareCacheService`)
- **Twitter/X API** — Auto-posting bargains, stock, price drops
- **LINE Messaging** — Push notifications for price drops

### SEO

Programmatic landing pages (`/bikes/area/{pref}/{slug}`, `/bikes/catalog/{slug}`), multiple XML sitemaps, JSON-LD structured data, dynamic OGP images for blog posts.

## Code Conventions

- `declare(strict_types=1)` and `final` classes for controllers/services
- Constructor dependency injection via Laravel container
- 4-space indentation (PHP, JS), LF line endings
- Japanese locale — all UI strings and domain concepts in Japanese
