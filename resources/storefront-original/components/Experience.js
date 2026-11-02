'use client'
import { useEffect, useRef } from 'react'
import Link from 'next/link'
import Img from './Img'
import { useCatalog } from './Catalog'

// "Why Al-Asra Feels Different": sticky, stacking customer-journey cards on the About page.
// Uses only live catalogue products from Laravel. No new image assets.
export default function Experience() {
  const { products: PRODUCTS } = useCatalog()
  const n = PRODUCTS.length
  const wraps = useRef([])
  useEffect(() => {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches
    let raf = 0
    const f = () => {
      raf = 0
      const els = wraps.current.filter(Boolean)
      const desktop = innerWidth > 720 && !reduce
      els.forEach((el, i) => {
        const card = el.firstElementChild
        if (!card) return
        const next = els[i + 1]
        if (!desktop || !next) { card.style.transform = ''; card.style.opacity = ''; return }
        const stick = parseFloat(getComputedStyle(el).top) || 0
        const d = next.getBoundingClientRect().top - (stick + 18)
        const p = Math.min(1, Math.max(0, 1 - d / (innerHeight * 0.8)))
        card.style.transform = `scale(${1 - p * 0.06}) translateY(${-p * 8}px)`
        card.style.opacity = String(1 - p * 0.18)
      })
    }
    const on = () => { if (!raf) raf = requestAnimationFrame(f) }
    f(); addEventListener('scroll', on, { passive: true }); addEventListener('resize', on)
    return () => { removeEventListener('scroll', on); removeEventListener('resize', on); if (raf) cancelAnimationFrame(raf) }
  }, [])
  if (!n) return null
  const P = (k) => PRODUCTS[((k % n) + n) % n]
  // each card: [main, floating A, floating B] drawn from the real catalogue, in a different order per step
  const CARDS = [
    { label: '01 / DISCOVER', title: "Find something you didn't know you needed.", text: 'Explore a growing collection of practical products and interesting everyday finds, all brought together in one place.', cta: 'Explore Products', href: '/shop', imgs: [P(0), P(1), P(2)], layout: 'a' },
    { label: '02 / CHOOSE', title: 'Simple choices. Useful products.', text: 'We focus on products that can genuinely add convenience to everyday life — without making the shopping experience complicated.', cta: 'Browse the Store', href: '/shop', imgs: [P(1), P(2), P(0)], layout: 'b' },
    { label: '03 / EXPERIENCE', title: 'From our store to your doorstep.', text: "Your journey doesn't end at checkout. We aim to make every step from discovering a product to receiving your order feel straightforward.", cta: 'Start Shopping', href: '/shop', imgs: [P(2), P(0), P(1)], layout: 'c' },
  ]
  return (
    <section className="xp" aria-labelledby="xp-title">
      <div className="xp-head">
        <span className="xp-eye">THE AL-ASRA EXPERIENCE</span>
        <h2 id="xp-title">Why Al-Asra Feels Different</h2>
        <p className="xp-sub">More than just an online store.</p>
        <p className="xp-txt">From discovering something useful to receiving it at your doorstep, every part of the Al-Asra experience is built around simplicity, practicality and products worth discovering.</p>
      </div>
      <div className="xp-cards">
        {CARDS.map((c, k) => (
          <div key={c.label} className="xp-wrap" style={{ '--i': k, zIndex: k + 1 }} ref={(el) => (wraps.current[k] = el)}>
            <article className={'xp-card xp-' + c.layout}>
              <div className="xp-copy">
                <small>{c.label}</small>
                <h3>{c.title}</h3>
                <p>{c.text}</p>
                <Link className="btn" href={c.href}>{c.cta}</Link>
              </div>
              <div className="xp-art" aria-hidden="false">
                <span className="xp-dot" aria-hidden="true" />
                <Link href={'/product/' + c.imgs[0].slug} className="xp-m" aria-label={c.imgs[0].name}><Img src={c.imgs[0].image} color={c.imgs[0].color} alt={c.imgs[0].name} /></Link>
                <Link href={'/product/' + c.imgs[1].slug} className="xp-f1" aria-label={c.imgs[1].name}><Img src={c.imgs[1].image} color={c.imgs[1].color} alt={c.imgs[1].name} /></Link>
                <Link href={'/product/' + c.imgs[2].slug} className="xp-f2" aria-label={c.imgs[2].name}><Img src={c.imgs[2].image} color={c.imgs[2].color} alt={c.imgs[2].name} /></Link>
              </div>
            </article>
          </div>
        ))}
      </div>
      <div className="xp-end">
        <h3>Ready to discover something useful?</h3>
        <Link className="btn" href="/shop">Shop Al-Asra</Link>
      </div>
    </section>
  )
}
