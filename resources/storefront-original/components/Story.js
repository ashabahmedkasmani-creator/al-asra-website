'use client'
import { useEffect, useRef } from 'react'
import Link from 'next/link'
import Img from './Img'
import { useCatalog } from './Catalog'

// "Explore Al-Asra": three sticky, stacking editorial cards. Uses only the live catalogue.
export default function Story() {
  const { products: PRODUCTS } = useCatalog()
  const n = PRODUCTS.length
  const topCat = () => { const c = {}; PRODUCTS.forEach((p) => (c[p.cat] = (c[p.cat] || 0) + 1)); return Object.keys(c).sort((a, b) => c[b] - c[a])[0] }
  const pick = (k) => n ? [0, 1, 2].map((j) => PRODUCTS[(k + j) % n]) : []
  const cat = topCat()
  const CARDS = [
    { label: '01 / Featured Finds', title: 'Useful products, picked for everyday life.', text: 'Explore some of the products currently catching attention at Al-Asra — practical, useful and easy to love.', cta: 'Explore Collection', href: '/shop' },
    { label: '02 / Everyday Essentials', title: 'Small upgrades that make everyday life easier.', text: 'From kitchen essentials to drinkware and useful everyday finds, discover products selected with practicality in mind.', cta: 'Shop Essentials', href: cat ? '/shop?cat=' + encodeURIComponent(cat) : '/shop' },
    { label: '03 / Customer Favourites', title: 'Some of the products worth discovering.', text: 'Take a closer look at popular Al-Asra picks and discover something useful for yourself.', cta: 'Shop Favourites', href: '/shop' },
  ]
  const wraps = useRef([])
  useEffect(() => {
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches
    let raf = 0
    const f = () => {
      raf = 0
      const els = wraps.current.filter(Boolean)
      if (!els.length) return
      const desktop = innerWidth > 720 && !reduce
      els.forEach((el, i) => {
        const card = el.firstElementChild
        if (!card) return
        const next = els[i + 1]
        if (!desktop || !next) { card.style.transform = ''; return }
        const stick = parseFloat(getComputedStyle(el).top) || 0
        const d = next.getBoundingClientRect().top - (stick + 18)
        const p = Math.min(1, Math.max(0, 1 - d / (innerHeight * 0.7)))
        card.style.transform = `scale(${1 - p * 0.05}) translateY(${-p * 6}px)`
      })
    }
    const on = () => { if (!raf) raf = requestAnimationFrame(f) }
    f(); addEventListener('scroll', on, { passive: true }); addEventListener('resize', on)
    return () => { removeEventListener('scroll', on); removeEventListener('resize', on); if (raf) cancelAnimationFrame(raf) }
  }, [])
  if (!n) return null
  return (
    <section className="story">
      <div className="story-head"><span className="kick">Discover</span><h2>Explore Al-Asra</h2><p>Discover useful products, everyday essentials and carefully selected finds — all in one place.</p></div>
      <div className="story-cards">
        {CARDS.map((c, k) => {
          const [a, b, d] = pick(k)
          return (
            <div key={c.label} className="ex-wrap" style={{ '--i': k }} ref={(el) => (wraps.current[k] = el)}>
              <article className={'ex ex' + k}>
                <div className="ex-txt">
                  <small>{c.label}</small>
                  <h3>{c.title}</h3>
                  <p>{c.text}</p>
                  <Link className="btn" href={c.href}>{c.cta}</Link>
                </div>
                <div className="ex-art">
                  <Link href={'/product/' + a.slug} className="ex-a" aria-label={a.name}><Img src={a.image} color={a.color} alt={a.name} /></Link>
                  <Link href={'/product/' + b.slug} className="ex-b" aria-label={b.name}><Img src={b.image} color={b.color} alt={b.name} /></Link>
                  <Link href={'/product/' + d.slug} className="ex-c" aria-label={d.name}><Img src={d.image} color={d.color} alt={d.name} /></Link>
                </div>
              </article>
            </div>
          )
        })}
      </div>
    </section>
  )
}
