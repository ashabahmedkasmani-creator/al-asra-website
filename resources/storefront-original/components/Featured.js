'use client'
import { useState } from 'react'
import ProductCard from './ProductCard'
import { useCatalog } from './Catalog'
export default function Featured() {
  const [cat, setCat] = useState('All')
  const { products: PRODUCTS, categories: CATEGORIES, loading } = useCatalog()
  const source = [...PRODUCTS].sort((a, b) => Number(b.showOnHome || b.isFeatured) - Number(a.showOnHome || a.isFeatured))
  const list = source.filter((p) => cat === 'All' || p.cat === cat).slice(0, 8)
  return (
    <section className="sec">
      <div className="center"><span className="kick">Our selection</span><h2>Fresh from our shelves</h2></div>
      <div className="chips mid">{CATEGORIES.map((c) => <button key={c} className={c === cat ? 'on' : ''} onClick={() => setCat(c)}>{c}</button>)}</div>
      {loading && !PRODUCTS.length ? <p className="empty">Loading products…</p> : list.length ? <div className="pgrid">{list.map((p) => <ProductCard key={p.id} p={p} />)}</div> : <p className="empty">New products are coming soon.</p>}
    </section>
  )
}
