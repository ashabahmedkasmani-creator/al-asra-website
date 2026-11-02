import synced from './products.json'
import MANUAL from './manual-products'
export const FREE_FROM = 10000, FEE = 250 // FEE = placeholder delivery charge, set your real one
export const shipping = (t) => (t === 0 || t >= FREE_FROM ? 0 : FEE)
// REAL products only (from `npm run sync`). Anything without a real photo is hidden - no fake/demo images anywhere.
export const PRODUCTS = (MANUAL.length ? MANUAL : synced).filter((p) => p.image)
export const CATEGORIES = ['All', ...Array.from(new Set(PRODUCTS.map((p) => p.cat)))]
export const fmt = (n) => 'PKR ' + Math.round(n).toLocaleString('en-PK')
export const SOCIAL = 'https://instagram.com/alasra.onlinestore'
