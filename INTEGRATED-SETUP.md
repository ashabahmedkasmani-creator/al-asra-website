# Al Asra — Integrated Laravel application

Laravel serves both the original animated storefront and the admin portal. Next.js is used only to build
static storefront assets; it is not a runtime web server.

## Main routes

| URL | Purpose |
|---|---|
| `/` | Storefront home |
| `/shop/` | Product catalogue |
| `/product/{slug}/` | Product detail |
| `/cart/`, `/checkout/` | Cart and checkout |
| `/track/`, `/about/`, `/contact/`, `/faq/` | Store information |
| `/login`, `/register` | Customer authentication |
| `/my-account`, `/my-orders` | Customer account and orders |
| `/admin/login` | Admin/staff sign-in |
| `/admin/dashboard` | Admin dashboard |
| `/api/*` | Canonical JSON API |
| `/*` legacy API aliases | Backward compatibility |

Admin JSON endpoints are under `/api/admin/*`. They require a valid Sanctum bearer token and the
appropriate role/permission.

## Local installation

1. Extract the project to `E:\\laragon\\www\\al_asra_bckend`.
2. Create the MySQL database `al_asra_befedb` in Navicat.
3. Copy `.env.example` to `.env` and configure MySQL.
4. Run:

```bash
composer install
php artisan key:generate
php artisan optimize:clear
php artisan migrate --seed
php artisan storage:link
```

5. Build the original storefront:

```bash
cd resources/storefront-original
npm ci
npm run build:laravel
cd ../..
php artisan optimize:clear
```

6. Start Laravel:

```bash
php artisan serve
```

The storefront is available at `http://127.0.0.1:8000/`; admin sign-in is at
`http://127.0.0.1:8000/admin/login`.

The first build requires internet access for npm to download packages. `node_modules` and generated
static output are not included in the source ZIP.

## Database and seeded admin

Database name: `al_asra_befedb`.

The admin seeder reads `ADMIN_NAME`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` from `.env`. Change the example
password before use. Never use the example credentials in production.
