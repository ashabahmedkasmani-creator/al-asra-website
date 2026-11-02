'use client'
import { useEffect, useRef } from 'react'
import ProductCard from './ProductCard'
import { useCatalog } from './Catalog'
export default function HScroll() {
  const { products: PRODUCTS } = useCatalog()
  const sec = useRef(), track = useRef()
  useEffect(() => {
    const f = () => {
      if (!sec.current || !track.current) return
      const r = sec.current.getBoundingClientRect()
      const max = Math.max(0, track.current.scrollWidth - innerWidth + innerWidth * 0.08)
      const p = Math.min(1, Math.max(0, -r.top / (r.height - innerHeight)))
      track.current.style.transform = `translateX(${-p * max}px)`
    }
    addEventListener('scroll', f, { passive: true }); addEventListener('resize', f); f()
    return () => { removeEventListener('scroll', f); removeEventListener('resize', f) }
  }, [PRODUCTS.length])
  return (
    <section ref={sec} className="hs">
      <div className="hsin">
        <h2>Customer favourites</h2>
        <div ref={track} className="htrack">{PRODUCTS.slice(0, 8).map((p) => <div className="hcard" key={p.id}><ProductCard p={p} /></div>)}</div>
      </div>
    </section>
  )
}
