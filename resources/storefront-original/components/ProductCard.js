'use client'
import Link from 'next/link'
import Img from './Img'
import { useCart } from './Cart'
import { fmt } from '../lib/data'
export default function ProductCard({ p }) {
  const { add } = useCart()
  return (
    <article className="pcard">
      <Link href={`/product/${p.slug}`} className="pic" onMouseMove={(e) => { const r = e.currentTarget.getBoundingClientRect(); const x = (e.clientX - r.left) / r.width - 0.5, y = (e.clientY - r.top) / r.height - 0.5; e.currentTarget.style.transform = `perspective(700px) rotateY(${x * 14}deg) rotateX(${-y * 14}deg) scale(1.03)` }} onMouseLeave={(e) => (e.currentTarget.style.transform = '')}>
        <Img src={p.image} color={p.color} alt={p.name} />
        {p.oldPrice && <em>Sale</em>}
      </Link>
      <small>{p.cat}</small>
      <Link href={`/product/${p.slug}`}><h3>{p.name}</h3></Link>
      <div className="prow"><b>{fmt(p.price)}{p.oldPrice && <s>{fmt(p.oldPrice)}</s>}</b><button disabled={p.isPurchasable === false} onClick={() => add(p)}>{p.isPurchasable === false ? 'Unavailable' : 'Add'}</button></div>
    </article>
  )
}
