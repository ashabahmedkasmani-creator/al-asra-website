'use client'
import Link from 'next/link'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useCatalog } from './Catalog'

// ---------------------------------------------------------------------------
// Backgrounds are clean off-whites. The Al Asra logo colours (orange #F24C00, maroon #5F1E29) are used
// only as accents in the CSS (.ah-*): title, arrows, SHOP NOW, brand label.
// Each slide's off-white is gently tinted from the product's own colour (see tintFromImage) so it complements
// the product; the fallback below is used until the image has been sampled.
// ---------------------------------------------------------------------------
const PALETTE = [
  { bg: '#F6F1EA', panel: '#FFFFFF' }, // warm ivory
  { bg: '#F1F2F2', panel: '#FFFFFF' }, // neutral
  { bg: '#F4EEE9', panel: '#FFFFFF' }, // soft blush
  { bg: '#EFF1F2', panel: '#FFFFFF' }, // cool light
]
const COUNT = 4
const DURATION = 650
const EASE = 'cubic-bezier(0.4,0,0.2,1)'

// Pick up to 4 products from the live Laravel catalogue. Prefer different categories so the
// carousel shows variety, then top up in catalogue order. Falls back gracefully if the catalogue is small/empty.
function pickFeatured(products = []) {
  const withImg = products.filter((p) => p.image)
  const picked = []
  const seen = new Set()
  for (const p of withImg) { if (picked.length < COUNT && !seen.has(p.cat)) { seen.add(p.cat); picked.push(p) } }
  for (const p of withImg) { if (picked.length < COUNT && !picked.includes(p)) picked.push(p) }
  const base = picked.length ? picked : [{ image: '/logo.png', name: 'Al-Asra Store', cat: 'Shop' }]
  return base.slice(0, COUNT).map((p, i) => ({ src: p.image, name: p.name, category: p.cat, ...PALETTE[i] }))
}

// Average colour of the product (ignoring transparent / near-white pixels) -> a very light, low-saturation tint.
// Saturation and lightness are clamped so every slide stays a clean off-white and the hero looks consistent.
function tintFromImage(img) {
  try {
    const c = document.createElement('canvas'); c.width = c.height = 32
    const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(img, 0, 0, 32, 32)
    const d = x.getImageData(0, 0, 32, 32).data
    let r = 0, g = 0, b = 0, n = 0
    for (let i = 0; i < d.length; i += 4) {
      if (d[i + 3] < 200) continue
      const mx = Math.max(d[i], d[i + 1], d[i + 2]), mn = Math.min(d[i], d[i + 1], d[i + 2])
      if (mn > 232 || mx < 25) continue // background white / pure black
      r += d[i]; g += d[i + 1]; b += d[i + 2]; n++
    }
    if (n < 20) return null
    r /= n; g /= n; b /= n
    const mx = Math.max(r, g, b) / 255, mn = Math.min(r, g, b) / 255, l = (mx + mn) / 2, dl = mx - mn
    let h = 0
    if (dl) { h = mx === r / 255 ? ((g - b) / 255 / dl) % 6 : mx === g / 255 ? (b - r) / 255 / dl + 2 : (r - g) / 255 / dl + 4; h = (h * 60 + 360) % 360 }
    const sat = dl ? dl / (1 - Math.abs(2 * l - 1)) : 0
    const s = sat < 0.12 ? 0.1 : Math.min(0.14, 0.07 + sat * 0.1) // greyish products -> warm neutral
    if (sat < 0.12) h = 30
    return `hsl(${h.toFixed(0)} ${(s * 100).toFixed(0)}% 94.5%)`
  } catch { return null }
}

const GRAIN = "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4'/></filter><rect width='100%' height='100%' filter='url(%23n)' opacity='0.08'/></svg>\")"

// lucide-react "arrow-left" / "arrow-right" (inlined so no new dependency is needed)
const Arrow = ({ dir = 'right', size = 26, className }) => (
  <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
    strokeWidth="2.25" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
    {dir === 'left' ? <><path d="m12 19-7-7 7-7" /><path d="M19 12H5" /></> : <><path d="M5 12h14" /><path d="m12 5 7 7-7 7" /></>}
  </svg>
)

// Per-role layout. d = desktop, m = mobile. (left/height/bottom as % of the hero.)
const ROLE = {
  center: { z: 20, blur: 0, op: 1, d: { left: 50, h: 92, b: 0 }, m: { left: 50, h: 60, b: 22 }, scaleD: 1.68, scaleM: 1.25 },
  left: { z: 10, blur: 2, op: 0.85, d: { left: 30, h: 28, b: 12 }, m: { left: 20, h: 16, b: 32 }, scaleD: 1, scaleM: 1 },
  right: { z: 10, blur: 2, op: 0.85, d: { left: 70, h: 28, b: 12 }, m: { left: 80, h: 16, b: 32 }, scaleD: 1, scaleM: 1 },
  back: { z: 5, blur: 4, op: 0,  d: { left: 50, h: 22, b: 12 }, m: { left: 50, h: 13, b: 32 }, scaleD: 1, scaleM: 1 },
}
const BOX_AR = 0.6 // item box aspect-ratio (width / height)

export default function Hero() {
  const { products: catalogue } = useCatalog()
  const PRODUCTS = useMemo(() => pickFeatured(catalogue), [catalogue])
  const [activeIndex, setActiveIndex] = useState(0)
  const [isAnimating, setIsAnimating] = useState(false)
  const [isMobile, setIsMobile] = useState(false)
  const [ready, setReady] = useState(false) // transitions stay off until the first client measurement (no mount "slide-in")
  const [ratios, setRatios] = useState({}) // natural width/height of each product image
  const [tints, setTints] = useState({}) // sampled background tint per image
  const [offset, setOffset] = useState(0) // header height above the hero, so the hero == exactly the visible viewport
  const lock = useRef(false)
  const timer = useRef()
  const root = useRef()

  // Preload all four featured images (and read their aspect so the hero scale can never crop a product)
  useEffect(() => {
    PRODUCTS.forEach((product) => {
      const img = new Image()
      img.onload = () => {
        setRatios((r) => (r[product.src] ? r : { ...r, [product.src]: img.naturalWidth / img.naturalHeight }))
        const t = tintFromImage(img)
        if (t) setTints((m) => (m[product.src] ? m : { ...m, [product.src]: t }))
      }
      img.src = product.src
    })
  }, [PRODUCTS])

  useEffect(() => {
    const measure = () => {
      setIsMobile(window.innerWidth < 640)
      if (root.current) setOffset(Math.max(0, Math.round(root.current.getBoundingClientRect().top + window.scrollY)))
    }
    measure()
    const raf = requestAnimationFrame(() => setReady(true))
    window.addEventListener('resize', measure)
    window.addEventListener('load', measure)
    return () => { cancelAnimationFrame(raf); window.removeEventListener('resize', measure); window.removeEventListener('load', measure) }
  }, [])

  useEffect(() => () => clearTimeout(timer.current), [])

  const N = PRODUCTS.length // 1-4 products; roles adapt so a product never appears twice
  const navigate = useCallback((dir) => {
    if (lock.current || N < 2) return
    lock.current = true
    setIsAnimating(true)
    setActiveIndex((prev) => (dir === 'next' ? (prev + 1) % N : (prev + N - 1) % N))
    timer.current = setTimeout(() => { lock.current = false; setIsAnimating(false) }, DURATION)
  }, [N])

  const center = activeIndex
  const left = (activeIndex + N - 1) % N
  const right = (activeIndex + 1) % N
  const roleOf = (i) => (i === center ? 'center' : i === left ? 'left' : i === right ? 'right' : 'back')
  const active = PRODUCTS[activeIndex]
  const bg = tints[active.src] || active.bg

  return (
    <div
      ref={root}
      className="ah"
      data-animating={isAnimating ? 'true' : 'false'}
      style={{ backgroundColor: bg, transition: `background-color ${DURATION}ms ${EASE}`, fontFamily: 'Inter, sans-serif', '--ah-offset': offset + 'px' }}
    >
      <div className="ah-stage">
        {/* soft light tint of the active hue behind the hero product */}
        <div className="ah-glow" aria-hidden style={{ background: `radial-gradient(ellipse 50% 60% at 50% 60%, ${active.panel}, transparent 72%)` }} />

        {/* grain */}
        <div className="ah-grain" aria-hidden style={{ backgroundImage: GRAIN }} />

        {/* brand label */}
        <div className="ah-brand"><i aria-hidden />AL ASRA</div>

        {/* carousel */}
        <div className="ah-carousel">
          {PRODUCTS.map((product, i) => {
            const role = roleOf(i)
            const R = ROLE[role]
            const pos = isMobile ? R.m : R.d
            let scale = isMobile ? R.scaleM : R.scaleD
            if (role === 'center' && ratios[product.src]) {
              // the product's real on-screen height = box height * min(1, boxAR / imageAR); keep it inside the hero
              const fit = Math.min(1, BOX_AR / ratios[product.src])
              const maxFrac = isMobile ? 0.96 - R.m.b / 100 : 0.9
              scale = Math.min(scale, maxFrac / ((pos.h / 100) * fit))
            }
            return (
              <div
                key={i}
                className="ah-item"
                style={{
                  left: pos.left + '%',
                  height: pos.h + '%',
                  bottom: pos.b + '%',
                  zIndex: R.z,
                  opacity: R.op,
                  filter: R.blur ? `blur(${R.blur}px)` : 'none',
                  transform: `translateX(-50%) scale(${scale})`,
                  transition: ready
                    ? ['transform', 'filter', 'opacity', 'left', 'height', 'bottom'].map((p) => `${p} ${DURATION}ms ${EASE}`).join(',')
                    : 'none',
                }}
              >
                <img src={product.src} alt={product.name} draggable={false} />
              </div>
            )
          })}
        </div>

        {/* bottom-left info + controls */}
        <div className="ah-info">
          <div className="ah-name" aria-live="polite">{active.name}</div>
          <p className="ah-desc">Discover useful products, everyday essentials and carefully selected finds — all in one place.</p>
          <div className="ah-nav">
            <button type="button" className="ah-btn" aria-label="Previous product" onClick={() => navigate('prev')}><Arrow dir="left" /></button>
            <button type="button" className="ah-btn" aria-label="Next product" onClick={() => navigate('next')}><Arrow dir="right" /></button>
          </div>
        </div>

        {/* bottom-right CTA */}
        <Link href="/shop" className="ah-cta">
          SHOP NOW
          <Arrow dir="right" size={32} className="ah-cta-ico" />
        </Link>
      </div>
    </div>
  )
}
