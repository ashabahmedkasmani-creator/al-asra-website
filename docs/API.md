# Al Asra Store — API reference

Base URL (local): `http://127.0.0.1:8000` · JSON in/out · send `Accept: application/json`.
Authenticated calls: `Authorization: Bearer <token>`. Guest carts: `X-Cart-Token: <cart_token>`.
Full request list: import `postman_collection.json` (Login stores `{{token}}` automatically).

## Conventions
* Success: `{ "data": ... }`; lists add `links` and `meta` (`current_page,last_page,per_page,total`).
* Validation error `422`: `{ "message": "...", "errors": { "field": ["..."] } }`.
* `401` not signed in/expired · `403` signed in but not allowed · `404` not found (also used for other people's orders) · `409` idempotency-key reuse by a different customer · `429` rate limited · `5xx` generic message only.
* Money is a JSON number in PKR (e.g. `2499`), computed from DB values; never send prices.
* Phone numbers are accepted as `03xx xxxxxxx`, `+92 3xx xxxxxxx`, etc. and stored as `03XXXXXXXXX`.

## Endpoints

### Public
| Method | Path | Notes |
|---|---|---|
| GET | `/health` | `{status:"ok"}`; 503 `{status:"degraded"}` if DB down. No config exposed |
| GET | `/home` | banners, featured, home_products, categories |
| GET | `/products` | `search, category(slug, includes children), min_price, max_price, availability, featured, home, sort(newest\|price_asc\|price_desc\|name), per_page(≤48)` |
| GET | `/products/{slug}` | published only |
| GET/POST | `/products/{slug}/reviews` | list approved / create (auth, delivered purchase only, moderated) |
| GET | `/categories` · `/banners` · `/faqs` · `/settings` · `/payment-methods` · `/shipping-zones` | Public store content and delivery methods/zones |

### Auth & account
| Method | Path | Notes |
|---|---|---|
| POST | `/auth/register` | 201 → `{user, token}`; rate-limited |
| POST | `/auth/login` | 200 → `{user, token}`; 401 generic on any failure; 5/min per email+IP |
| POST | `/auth/forgot-password` | always 200 same message |
| POST | `/auth/reset-password` | single-use token; revokes all tokens |
| POST | `/auth/logout` · `/auth/logout-all` | revoke current / all tokens |
| GET/PATCH | `/me` | name, phone only |
| POST | `/me/password` | needs `current_password`; signs out other sessions |
| CRUD | `/addresses` | own addresses only (403 otherwise) |
| GET | `/orders`, `/orders/{reference}` | own orders only |
| POST | `/orders/{reference}/cancel` | only while `pending`/`awaiting_supplier` |

### Cart, checkout, tracking (guests allowed)
| Method | Path | Notes |
|---|---|---|
| GET/POST/PATCH/DELETE | `/cart`, `/cart/items[/{id}]` | guest cart token is returned in `cart_token` and `X-Cart-Token` on first add |
| POST | `/checkout/quote` | prices a bag; `items` or the cart; optional `coupon_code` |
| POST | `/checkout` | creates the order (see below); optional `Idempotency-Key` (16–80 chars) |
| POST | `/orders/track` | `{reference, phone}` both required; generic 404; 5/min, 30/h per IP; 10/h per reference |

### Admin (all need a token; permission in brackets)
`GET /admin/dashboard` [dashboard.view] · products CRUD + `publish`/`unpublish` + images + variants [products.manage] ·
categories [categories.manage] · `GET /admin/orders[/{id}]` [orders.view] · `PATCH /admin/orders/{id}/status`, `PUT .../shipment` [orders.update] ·
`POST .../payment/verify|refund` [payments.verify, admin] · customers [customers.view, admin] · `/admin/users` [users.manage, admin; needs current_password] ·
coupons [coupons.manage, admin] · `/admin/settings` [settings.manage, admin] · banners [banners.manage] · faqs [faqs.manage] ·
reviews [reviews.moderate] · `GET /admin/audit-logs` [audit.view, admin].
Staff = dashboard, products, categories, orders view/status/shipment, banners, FAQs, reviews and support tickets. Everything else is admin-only.

## Examples

**Quote** `POST /checkout/quote`
```json
{ "items": [{ "product_id": 1, "quantity": 2 }], "coupon_code": "WELCOME10" }
```
```json
{ "data": { "items": [{"product_id":1,"variant_id":null,"name":"Portable Juice Blender","quantity":2,"unit_price":2499,"line_total":4998,"availability":"available","availability_label":"In stock"}],
  "subtotal":4998,"discount_total":499.8,"shipping_fee":250,"grand_total":4748.2,
  "free_shipping_threshold":10000,"amount_to_free_shipping":5501.8,"coupon_code":"WELCOME10",
  "requires_supplier_confirmation":false,"currency":"PKR" } }
```

**Place order** `POST /checkout` (header `Idempotency-Key: 3f1c0b9e-6c1e-4a0c-9a53-0e8f7d2b1c44`)
```json
{ "customer_name":"Ali Khan","customer_phone":"0300 1234567","address_line":"House 12, Street 4, Gulshan",
  "city":"Karachi","province":"Sindh","payment_method":"cod","items":[{"product_id":1,"quantity":1}] }
```
→ `201` (`200` + `Idempotent-Replayed: true` if repeated)
```json
{ "data": { "reference":"ASR-7K3M9QXD4P","status":"pending","payment_method":"cod","payment_status":"pending",
  "subtotal":2499,"discount_total":0,"shipping_fee":250,"grand_total":2749,"currency":"PKR","items":[ ... ],"history":[{"status":"pending","at":"..."}] },
  "meta": { "payment_instructions":"Pay in cash when your order is delivered.","track_with":"order reference + phone number" } }
```
Supplier-dependent items start as `awaiting_supplier`. Bank-transfer orders stay `payment_status: pending` until an admin verifies.

**Track** `POST /orders/track` `{ "reference":"ASR-7K3M9QXD4P","phone":"03001234567" }` → status, totals, items, status history, courier/tracking number, city.
No phone, e-mail or street address is returned. Any mismatch → `404 {"message":"We could not find an order matching those details."}`.

**Order status** `PATCH /admin/orders/{id}/status` `{ "status":"confirmed","note":"Called customer" }`
Allowed: pending→confirmed|awaiting_supplier|cancelled · awaiting_supplier→confirmed|cancelled · confirmed→processing|cancelled · processing→shipped|cancelled · shipped→delivered|returned · delivered→returned. Anything else → `422`.

**Verify payment** `POST /admin/orders/{id}/payment/verify` `{ "reference":"BANK-TXN-123" }` (admin). COD only after `delivered`; bank transfer requires the reference; cancelled orders cannot be paid; double-verify → 422.

**Validation error**
```json
{ "message": "The customer phone field format is invalid.", "errors": { "customer_phone": ["Enter a valid Pakistani mobile number, e.g. 03XX XXXXXXX."] } }
```

### Additional management and store operations

| Method | Path | Notes |
|---|---|---|
| GET | `/shipping-zones` | Public active delivery zones and estimated delivery windows |
| GET | `/admin/inventory` | Inventory snapshot including tracked products/variants and low-stock flags |
| GET | `/admin/inventory/movements` | Stock movement ledger, optional `search` and `per_page` |
| POST | `/admin/products/{product}/inventory/adjust` | `{ "variant_id": null, "quantity_change": 8, "note": "Supplier restock" }`; positive receives stock, negative removes stock; negative stock is rejected |
| PATCH | `/admin/orders/{order}/assign` | `{ "assigned_to": 15 }` or `null`; only active staff/admin accounts can be assigned |
| GET/POST/PATCH/DELETE | `/admin/shipping-zones[/{shippingZone}]` | Manage delivery cities, provinces, fee, free-shipping threshold, and estimated delivery days |
| GET | `/admin/payments` | Payment ledger with status/method/search filters; admin-only |
| GET | `/admin/reports?from=2026-10-01&to=2026-10-31` | Paid/pending revenue, order status totals, daily sales and top products; admin-only |
| GET/PATCH/POST | `/admin/support/tickets[/{ticket}]` and `/{ticket}/messages` | Staff/admin ticket inbox, assignment, priority, status, public replies and internal notes |
| GET | `/notifications` | Current account's notifications, paginated (new orders notify admins/staff; order/payment/support events notify customers) |
| POST | `/notifications/{id}/read` · `/notifications/read-all` | Mark notifications read |
| GET | `/orders/{reference}/invoice` | Printable invoice HTML; owner or staff/admin only |
| GET/POST | `/support/tickets` | Customer's tickets; create with `subject`, `message`, optional own `order_reference` |
| GET | `/support/tickets/{ticket}` | Own ticket and public conversation messages |
| POST | `/support/tickets/{ticket}/messages` | Add a reply while ticket is open/pending |

### Customer demonstration web routes

`GET/POST /demo/register`, `GET/POST /demo/login`, `POST /demo/logout`, `GET /account`, `POST /account/profile`. These use Laravel's session guard and CSRF protection; the Next.js client should use the documented Sanctum API instead.

### Shipping-zone pricing

When a checkout quote/place-order request includes `city`, optionally `province`, and optionally `postal_code`, the pricing service selects the most specific active city/province zone (and matches configured postal-code lists when present); a zone with no city can act as the nationwide fallback. The quote returns the matched `shipping_zone` and the order stores `shipping_zone_id`. If no matching zone exists, the store-wide `shipping_fee` and `free_shipping_threshold` settings are used.

### Guest cart handoff

When a guest registers or signs in with the same `X-Cart-Token` header, the guest cart is merged into that customer's cart in a database transaction. Quantities are merged up to the configured per-line maximum, and only server-side catalogue pricing is used at checkout.
