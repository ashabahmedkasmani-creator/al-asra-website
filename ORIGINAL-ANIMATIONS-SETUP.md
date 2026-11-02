# Original Al Asra storefront animations

The original Next.js storefront source is preserved in `resources/storefront-original`. The build uses
the original React components and bundles the client-side animation libraries and effects, including
the loader, page transitions, Lenis smooth scrolling, scroll reveals, and Three.js scenes.

## Build on Windows / Laragon

From the project root:

```bat
cd /d E:\\laragon\\www\\al_asra_bckend\\resources\\storefront-original
npm ci
npm run build:laravel
cd /d E:\\laragon\\www\\al_asra_bckend
php artisan optimize:clear
```

Then start Laravel:

```bat
php artisan serve
```

- Storefront: http://127.0.0.1:8000/
- Admin: http://127.0.0.1:8000/admin/login

Node/npm is required only for the one-time build and subsequent storefront rebuilds. Do not run
`next dev` or `next start` for the Laravel application.

The cart UI stores its current contents in browser localStorage. Checkout submits item slugs and
quantities to the Laravel catalogue and order API; the server resolves product IDs and calculates
authoritative prices, discounts and shipping.
