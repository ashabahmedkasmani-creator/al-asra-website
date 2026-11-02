'use client'
import { useEffect, useState } from 'react'
import ProductCard from './ProductCard'
import { useCatalog } from './Catalog'
export default function ShopView() {
  const [cat, setCat] = useState('All'), [q, setQ] = useState(''), [sort, setSort] = useState('new')
  const { products: PRODUCTS, categories: CATEGORIES, loading, error } = useCatalog()
  useEffect(() => { const u = new URLSearchParams(location.search); setCat(u.get('cat') || 'All'); setQ(u.get('q') || '') }, [])
  let list = PRODUCTS.filter((p) => (cat === 'All' || p.cat === cat) && p.name.toLowerCase().includes(q.toLowerCase()))
  if (sort === 'lo') list = [...list].sort((a, b) => a.price - b.price)
  if (sort === 'hi') list = [...list].sort((a, b) => b.price - a.price)
  return (
    <section className="sec">
      <h1 className="ptitle">{q ? `Results for “${q}”` : cat === 'All' ? 'All products' : cat}</h1>
      <div className="toolbar">
        <div className="chips">{CATEGORIES.map((c) => <button key={c} className={c === cat ? 'on' : ''} onClick={() => setCat(c)}>{c}</button>)}</div>
        <select value={sort} onChange={(e) => setSort(e.target.value)} aria-label="Sort"><option value="new">Newest</option><option value="lo">Price: low to high</option><option value="hi">Price: high to low</option></select>
      </div>
      {loading && !PRODUCTS.length ? <p className="empty">Loading products…</p> : error ? <p className="empty">{error}</p> : list.length ? <div className="pgrid">{list.map((p) => <ProductCard key={p.id} p={p} />)}</div> : <p className="empty">No products match. Try another category or search.</p>}
    </section>
  )
}
