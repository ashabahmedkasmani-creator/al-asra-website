'use client'
import { useEffect, useState } from 'react'
import { usePathname } from 'next/navigation'
import Img from './Img'
import ProductCard from './ProductCard'
import { useCart } from './Cart'
import { fmt } from '../lib/data'
import { mapApiProduct, useCatalog } from './Catalog'
export default function ProductView({ p }) {
  const pathname = usePathname()
  const slug = (pathname || '').split('/').filter(Boolean).pop() || p.slug
  const { products: catalogue } = useCatalog()
  const { add } = useCart()
  const [product, setProduct] = useState(p)
  const [loading, setLoading] = useState(true)
  const [notFound, setNotFound] = useState(false)
  const [n, setN] = useState(1), [k, setK] = useState(0)

  useEffect(() => {
    let active = true
    setLoading(true); setNotFound(false); setK(0)
    fetch(`/api/products/${encodeURIComponent(slug)}`, { headers: { Accept: 'application/json' }, cache: 'no-store' })
      .then(async (r) => { const payload = await r.json().catch(() => ({})); if (!r.ok) throw new Error(payload.message || 'Product not found'); return payload.data })
      .then((data) => { if (active) setProduct(mapApiProduct(data)) })
      .catch(() => { if (active) setNotFound(true) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [slug])

  const pNow = product?.slug === slug ? product : null
  useEffect(() => { if (pNow?.name) document.title = `${pNow.name} | Al-Asra Store` }, [pNow?.name])
  const imgs = pNow?.images?.length ? pNow.images : (pNow?.image ? [pNow.image] : [])
  const more = catalogue.filter((x) => x.id !== pNow?.id && x.cat === pNow?.cat).slice(0, 4)
  if (loading && !pNow) return <section className="sec"><p className="empty">Loading product…</p></section>
  if (notFound || !pNow) return <section className="sec"><h1 className="ptitle">Product not found</h1><p className="empty">This product may be unavailable or no longer published.</p><a className="btn" href="/shop">Back to shop</a></section>

  return (
    <section className="sec">
      <div className="pdp">
        <div className="gal"><Img className="main" src={imgs[k]} color={pNow.color} alt={pNow.name} />
          {imgs.length > 1 && <div className="thumbs">{imgs.map((src, i) => <button key={i} className={i === k ? 'on' : ''} onClick={() => setK(i)}><Img src={src} alt="" /></button>)}</div>}</div>
        <div className="info">
          <small>{pNow.cat}</small><h1>{pNow.name}</h1>
          <p className="price">{fmt(pNow.price)}{pNow.oldPrice && <s>{fmt(pNow.oldPrice)}</s>}</p>
          <p className="desc">{pNow.desc}</p>
          <div className="buy"><div className="qty"><button onClick={() => setN(Math.max(1, n - 1))}>−</button><span>{n}</span><button onClick={() => setN(n + 1)}>+</button></div>
            <button className="btn" disabled={!pNow.isPurchasable} onClick={() => add(pNow, n)}>{pNow.isPurchasable ? 'Add to bag' : 'Unavailable'}</button></div>
          <ul className="perks"><li>Free delivery over PKR 10,000</li><li>Cash on delivery available</li><li>Original and imported</li></ul>
        </div>
      </div>
      {!!more.length && <><h2 className="mh">You may also like</h2><div className="pgrid">{more.map((x) => <ProductCard key={x.id} p={x} />)}</div></>}
    </section>
  )
}
