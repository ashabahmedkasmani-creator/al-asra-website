# Al Asra brand and API notes

## Demonstration palette

The Al Asra brief specifies these colors for the Laravel Blade demonstration UI:

| Role | Hex |
|---|---|
| Primary orange | `#F15A24` |
| Secondary burgundy | `#4A151B` |
| Background | `#F7F6F5` |
| Surface | `#FFFFFF` |
| Text | `#292929` |
| Muted text | `#777777` |

The original Next.js frontend is intentionally not changed in this backend task. Its own `globals.css` can be aligned to the same brand palette during the separate frontend integration.

## Store conventions

- Store name: Al Asra / AL ASRA
- Currency: PKR
- Payment methods: Cash on Delivery and manual bank transfer
- Store-wide default shipping fee: PKR 250
- Store-wide free-shipping threshold: PKR 10,000
- Store timezone: Asia/Karachi
- Public contact and social settings are editable through the settings API.

## API content

`GET /settings` exposes only the whitelisted public settings. `GET /home` returns active banners, featured products, homepage products and categories. `GET /shipping-zones` returns active delivery zones and their estimated delivery windows.
