# Al Asra original storefront source

This is the original Next.js storefront source used by the Laravel application. Its React components,
styling, product imagery and animation code are intentionally preserved.

## Build for Laravel

Run from this directory:

```bash
npm ci
npm run build:laravel
```

The command runs the static Next.js export and copies the HTML pages and client-side bundles into the
parent Laravel project's `public` directory. Then, from the Laravel project root, run:

```bash
php artisan optimize:clear
php artisan serve
```

Open `http://127.0.0.1:8000/`. Next.js is not started as a separate web server.

## Source pages

- `/`
- `/shop`
- `/product/[slug]`
- `/cart`
- `/checkout`
- `/about`
- `/contact`
- `/track`
- `/faq`

## Catalogue and checkout

The bundled catalogue currently provides the storefront's build-time product cards. Before checkout,
the storefront resolves each product slug against Laravel's public catalogue API and submits only
product IDs and quantities. The corresponding products must exist and be published in the Laravel
database. Laravel recalculates the order prices, discounts and shipping.

The development build requires internet access for npm package installation. `node_modules`, `.next`,
and `out` are generated locally and are not included in the delivered source ZIP.
