'use client'
import { useState } from 'react'
import { CATEGORIES, PRODUCTS } from '../lib/products'
import { fmt, useCart } from './Cart'

function Card({ p }) {
  const { add } = useCart()
  const tilt = (e) => {
    const r = e.currentTarget.getBoundingClientRect()
    const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5
    e.currentTarget.style.transform = `perspective(700px) rotateY(${x * 14}deg) rotateX(${-y * 14}deg) translateY(-6px)`
  }
  return (
    <article className="card" onMouseMove={tilt} onMouseLeave={(e) => (e.currentTarget.style.transform = '')}>
      <div className="pic">
        <div className="bottle" style={{ background: p.color }}><i /></div>
        {p.image && <img src={p.image} alt={p.name} onError={(e) => (e.currentTarget.style.display = 'none')} />}
      </div>
      <h3>{p.name}</h3>
      <p>{p.size} · {p.cat}</p>
      <div className="row"><b>{fmt(p.price)}</b><button onClick={() => add(p)}>Add to bag</button></div>
    </article>
  )
}

export default function Shop() {
  const [cat, setCat] = useState('All')
  const list = PRODUCTS.filter((p) => cat === 'All' || p.cat === cat)
  return (
    <section id="shop" className="shop">
      <h2>Shop the store</h2>
      <div className="chips">
        {CATEGORIES.map((c) => <button key={c} className={c === cat ? 'on' : ''} onClick={() => setCat(c)}>{c}</button>)}
      </div>
      <div className="grid">{list.map((p) => <Card key={p.id} p={p} />)}</div>
    </section>
  )
}
