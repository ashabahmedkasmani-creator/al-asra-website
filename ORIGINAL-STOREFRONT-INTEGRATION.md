# Original Al Asra storefront integration

The original storefront source is preserved under `resources/storefront-original/`, including its
Next.js App Router pages, React components, CSS, product data, images and build scripts.

`npm run build:laravel` exports the site and copies the generated HTML and `_next` bundles into
Laravel's `public/` directory. `StorefrontStaticController` serves the exported pages from
`public/storefront-pages/`. This keeps the original React interactions and bundled visual effects
without requiring a separate Next.js server at runtime.

Customer pages include `/`, `/shop/`, `/product/{slug}/`, `/cart/`, `/checkout/`, `/track/`,
`/about/`, `/contact/` and `/faq/`. Customer account pages use Laravel routes:
`/login`, `/register`, `/my-account` and `/my-orders`.

The admin portal is served from `/admin/*`; its API calls use `/api/admin/*`. Public and authenticated
API endpoints use the `/api/*` prefix. Legacy unprefixed API aliases remain available for compatibility.

## Live product catalogue

The static Next.js export is now only the page shell and animation bundle. The React storefront fetches published products from Laravel's `/api/products` endpoint at runtime for the homepage, shop, category filters, product recommendations and product detail pages. Laravel serves a generated product shell for newly imported slugs, so adding products does not require another Next.js build.

## WooCommerce CSV import

From WordPress, export Products using the WooCommerce CSV export tool. In Al Asra Admin → Products, upload that CSV. The importer supports standard WooCommerce simple-product rows, SKU-based updates, categories, descriptions, regular/sale prices, published/visibility state, stock fields and image URLs. Images are validated and re-encoded before being stored on Laravel's public disk. Variable products and variation rows are skipped and reported.
