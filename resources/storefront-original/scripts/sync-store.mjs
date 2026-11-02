// Run on YOUR computer:  npm run sync
// Pulls every product (name, price, category, description, images) from your WooCommerce site
// into lib/products.json and downloads images to public/images/products
import fs from 'node:fs'
import path from 'node:path'
const BASE = process.env.STORE_URL || 'https://alasra.online'
const out = path.join(process.cwd(), 'public/images/products')
fs.mkdirSync(out, { recursive: true })
const H = { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36', Accept: 'application/json' }
const strip = (h = '') => h.replace(/<[^>]+>/g, ' ').replace(/&amp;/g, '&').replace(/&#8217;/g, "'").replace(/\s+/g, ' ').trim()
async function dl(url, file) {
  try { const r = await fetch(url, { headers: H }); if (!r.ok) return null; fs.writeFileSync(path.join(out, file), Buffer.from(await r.arrayBuffer())); return '/images/products/' + file } catch { return null }
}
const all = []
for (let page = 1; ; page++) {
  const u = `${BASE}/wp-json/wc/store/v1/products?per_page=100&page=${page}`
  let r; try { r = await fetch(u, { headers: H }) } catch (e) { console.error('Cannot reach', u, '-', e.message, '(internet/VPN/firewall?)'); break }
  if (!r.ok) { console.error('Store API failed:', r.status, u, '\n', (await r.text()).slice(0, 300)); break }
  const list = await r.json(); if (!list.length) break; all.push(...list); if (list.length < 100) break
}
const products = []
for (const p of all) {
  const minor = p.prices?.currency_minor_unit ?? 0, div = 10 ** minor
  const imgs = []
  for (const [i, im] of (p.images || []).entries()) { const f = await dl(im.src, `${p.slug}-${i}${path.extname(new URL(im.src).pathname) || '.jpg'}`); if (f) imgs.push(f) }
  products.push({
    id: String(p.id), slug: p.slug, name: strip(p.name), price: Number(p.prices?.price || 0) / div,
    oldPrice: p.on_sale ? Number(p.prices?.regular_price || 0) / div : null,
    cat: strip(p.categories?.[0]?.name || 'Shop'), image: imgs[0] || '', images: imgs,
    desc: strip(p.short_description || p.description), size: '', color: '#F05A1A',
  })
  console.log('✓', p.name)
}
fs.writeFileSync(path.join(process.cwd(), 'lib/products.json'), JSON.stringify(products, null, 2))
console.log(`\nDone: ${products.length} products.`); if (!products.length) console.error('0 products saved - send me the error printed above.')
