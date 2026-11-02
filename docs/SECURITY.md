# Security notes and deployment checklist

## Implemented defences (map to the 20-point brief)
1. **AuthN/AuthZ** — Sanctum tokens, `active` middleware (deactivated users lose access at once), `permission:*` middleware on every admin route.
2. **RBAC / no privilege escalation** — `role`/`is_active` are not mass-assignable; role changes need admin + current password, can't target yourself, revoke tokens.
3. **Object-level auth** — customer orders are fetched through `$user->orders()` (other people's orders = 404); addresses via `AddressPolicy`; cart items scoped to the resolved cart; image/variant routes verify the child belongs to the product.
4. **Validation / mass assignment** — Form Requests or inline `validate()` everywhere; Order/OrderItem/Payment/History/AuditLog are fully guarded and created only by services.
5. **SQL injection** — Eloquent / bound parameters only; `LIKE` wildcards escaped; sort/filter values whitelisted.
6. **XSS** — API returns JSON; all free text is `strip_tags`-ed on input (plain text only); banner links limited to relative or http(s) URLs. The frontend must still render text as text (never `dangerouslySetInnerHTML`).
7. **CSRF** — not needed for bearer tokens (no cookie auth). Required if you later enable cookie/SPA mode.
8. **CORS** — allow-list from `FRONTEND_URLS`, no wildcard.
9. **Rate limits** — login (5/min per email+IP), register, password reset, tracking (5/min, 30/h per IP, 10/h per reference), checkout, quote, uploads, sensitive admin actions.
10. **Passwords/reset** — bcrypt via Laravel `hashed` cast; min 10 chars, mixed case, number, breach-check (`uncompromised`) in production; reset is single-use, generic response, revokes all tokens.
11. **Uploads** — type/size/dimension validation + content sniffing + GD re-encode to WebP/PNG + UUID filename; SVG/PHP/PDF rejected.
12. **Path traversal** — client filenames never used; deletes refuse `..`/absolute paths.
13. **Errors** — generic JSON 404/500 messages; production must have `APP_DEBUG=false`.
15. **Concurrency** — checkout in a DB transaction with `lockForUpdate` on products/variants/coupons, deadlock retry, unique `idempotency_key`.
16. **Price/coupon/order tampering** — server-side pricing; coupon limits enforced inside the locked transaction; status machine; payment changes only via `PaymentService`.
17. **Headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `CSP default-src 'none'`, `Cache-Control: no-store`, HSTS in production. Also set them at the web server.
18. **Audit trail** — `audit_logs` for product/category/variant/image/order/payment/coupon/settings/user/banner/FAQ/review changes; secret keys are scrubbed.
19. **Dependency audit** — run `composer audit` (not yet run).
20. **Secret leakage** — `.env` is git-ignored by Laravel's skeleton; `.env.example` placeholders only; password-reset failures log only the exception class.

21. **Operational notifications and support** — database notifications are stored per account; customer support ticket queries are scoped to the authenticated owner, and internal staff notes are filtered from customer responses.

## Residual risks (be aware)
* Registration reveals whether an email exists (needed for usable sign-up without email verification).
* Bearer tokens are only as safe as the place the frontend stores them — use an httpOnly cookie via Next.js server code.
* Rate limits use the cache; in production use Redis and set `TRUSTED_PROXIES` so the real client IP is used.
* Guest order tracking depends on reference + phone; phone numbers are low-entropy, so the reference (≈50 bits) is the real secret. Limits above make guessing impractical but not impossible in theory.
* Runtime boot, migrations, PHPUnit and `composer audit` still need to be run in the target Laragon environment (see README).

## Production checklist
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, new `APP_KEY` (never reuse the dev key), HTTPS only, HSTS at the web server.
- [ ] Dedicated MySQL user with only needed privileges on `al_asra_befedb` (not `root`), strong password, DB not exposed to the internet.
- [ ] `FRONTEND_URLS` = exact production origin(s); remove localhost.
- [ ] `TRUSTED_PROXIES` set; Redis for cache/rate-limits/queues; real mail provider configured.
- [ ] `php artisan config:cache route:cache` ; `composer install --no-dev --optimize-autoloader`.
- [ ] Web root = `public/`; deny dotfiles, `storage/`, `vendor/`; disable PHP execution in `storage/app/public` (`/storage`) — e.g. nginx `location ~* ^/storage/.*\.php$ { deny all; }`.
- [ ] Change the seeded admin password immediately; use a unique long password; consider adding 2FA later.
- [ ] Remove the sample products/FAQ/banner you do not want; set the real bank-transfer instructions (`payment_methods.instructions`).
- [ ] Automated DB backups + restore test; log rotation; monitor `storage/logs`.
- [ ] Run `composer audit` on every deploy and keep Laravel/PHP patched.
- [ ] Never run `migrate:fresh` or `db:wipe` in production.
