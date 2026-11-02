# Al Asra — Laravel 10 Store Backend

This is the main Laravel project. The original animated Next.js storefront source is kept in
`resources/storefront-original` and is exported into Laravel's `public` directory at build time.
Laravel is the only web server needed after the build; Next.js is not run as a server.

## Project location

Extract the ZIP to:

```text
E:\\laragon\\www\\al_asra_bckend
```

## Requirements

- PHP 8.1 or newer (PHP 8.2/8.3 recommended for a Laravel 10 setup)
- Composer 2
- MySQL 8 or MariaDB supported by Laragon
- Node.js 18+ and npm (only needed to build the storefront)
- PHP extensions required by Laravel and image uploads, including `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `gd`, `xml`, `ctype`, `tokenizer`, and `bcmath`

## 1. Create the database

Create this database in Navicat/phpMyAdmin:

```text
al_asra_befedb
```

Or run `database/create_database.sql`.

## 2. Configure Laravel

From Git Bash / terminal:

```bash
cd /e/laragon/www/al_asra_bckend
cp .env.example .env
```

On Windows CMD, use `copy .env.example .env` instead of `cp`.

Set these values in `.env`:

```env
APP_NAME="Al Asra"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=al_asra_befedb
DB_USERNAME=root
DB_PASSWORD=
```

Set a private admin password in `.env` before seeding. The local example credentials are for development only.

## 3. Install Laravel dependencies and prepare the database

```bash
composer install
php artisan key:generate
php artisan optimize:clear
php artisan migrate --seed
php artisan storage:link
```

The development seeder creates the initial administrator using `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`.
Default local example (change it immediately): `admin@alasra.local` / `ChangeMe!AlAsra2026`.

## 4. Build the original animated storefront

```bash
cd resources/storefront-original
npm ci
npm run build:laravel
cd ../..
php artisan optimize:clear
```

This exports the Next.js storefront, including its React hydration bundles, Lenis smooth scrolling,
scroll reveals, transitions and Three.js scenes, into Laravel's `public` folder. It is a build step only;
do not run `next dev` or `next start` for normal Laravel use.

Run the build again whenever storefront source files change. `node_modules` and generated build output
are intentionally not bundled; `npm ci` restores the exact dependencies from `package-lock.json`.

## 5. Run the application

```bash
php artisan serve
```

Open:

- Storefront: http://127.0.0.1:8000/
- Shop: http://127.0.0.1:8000/shop/
- Cart: http://127.0.0.1:8000/cart/
- Checkout: http://127.0.0.1:8000/checkout/
- Customer login: http://127.0.0.1:8000/login
- Customer registration: http://127.0.0.1:8000/register
- Customer account: http://127.0.0.1:8000/my-account
- Admin login: http://127.0.0.1:8000/admin/login
- Admin dashboard: http://127.0.0.1:8000/admin/dashboard

## API routing

The canonical API URLs use `/api`, for example:

- `GET /api/health`
- `GET /api/products`
- `POST /api/auth/login`
- `POST /api/checkout`
- `GET /api/admin/dashboard`

Legacy unprefixed API aliases are retained for compatibility with existing tests/integrations.

## Important notes

- Checkout sends product identifiers and quantities; Laravel calculates all prices, discounts and shipping.
- Customer accounts use Laravel session authentication for the Blade account pages and Sanctum tokens for API access.
- Admin/staff access is checked by role and permission middleware.
- Product CSV import is available in Admin → Products. Export a WooCommerce Products CSV and upload it there. The importer creates/updates simple products by SKU (or slug), creates missing categories, imports price/sale/stock/published fields and downloads product images. Variable products and variations are not imported; convert them to simple products first. The import limit is 1,000 rows per CSV.
- Storefront product listings, homepage product sections and product details read the live published catalogue from Laravel's `/api/products` endpoints. New product slugs use a Laravel fallback shell, so you do not need to rebuild Next.js after every product import. Refresh the storefront after importing to load the updated catalogue.
- The importer stores the first WooCommerce category because this backend currently assigns one category per product. It imports up to 10 image URLs per product; images that cannot be fetched from their public host are skipped.
- Configure real mail delivery, trusted origins, production credentials and HTTPS before deployment.
- Do not expose `APP_DEBUG=true` or the example admin password on a live server.
- The storefront source is under `resources/storefront-original`; the static export is generated under `public/`.
