'use client'
import Link from 'next/link'
import Hero from '../components/Hero'
import Featured from '../components/Featured'
import HScroll from '../components/HScroll'
import Cinema from '../components/Cinema'
import Shelf from '../components/Shelf'
import Story from '../components/Story'
import Img from '../components/Img'
import { useCatalog } from '../components/Catalog'
const TRUST = [['Free delivery', 'On orders over PKR 10,000'], ['Cash on delivery', 'Or bank transfer'], ['Track your order', 'Real-time updates'], ['Always here', 'Mon–Sat, 11:00am–8:30pm']]
const TICK = 'ORIGINAL ✦ DAILY ESSENTIALS ✦ FAST DELIVERY ✦ CASH ON DELIVERY ✦ DELIVERED ACROSS PAKISTAN ✦ '
export default function Home() {
  const { products, categories, loading } = useCatalog()
  const cats = categories.filter((c) => c !== 'All').slice(0, 4).map((c) => {
    const list = products.filter((p) => p.cat === c)
    return [c, `${list.length} products`, list[0]?.image || '']
  }).filter((c) => c[2])
  const two = products.slice(0, 2), strip = products.slice(0, 12)
  return (
    <>
      <Hero />
      <section className="trust">{TRUST.map(([a, b]) => <div key={a}><b>{a}</b><span>{b}</span></div>)}</section>
      <section className="sec">
        <div className="center"><span className="kick">Shop by category</span><h2>Find what you need</h2></div>
        {cats.length ? <div className="cats">{cats.map(([c, d, img]) => (
          <Link key={c} href={'/shop?cat=' + encodeURIComponent(c)} className="cat"><Img src={img} alt={c} /><div><h3>{c}</h3><p>{d}</p></div></Link>))}
        </div> : <p className="empty">{loading ? 'Loading categories…' : 'New categories are coming soon.'}</p>}
      </section>
      <Cinema />
      <Featured />
      <section className="obnd">
        <div className="obt"><span className="kick w">Why Al-Asra</span><h2>Original products you can trust.</h2><p>Every item is checked before it ships from Karachi to your door.</p><Link className="btn white" href="/shop">Browse the shop</Link></div>
        <div className="obv">{two.map((p, n) => <div key={p.id} className={'ob ob' + n}><Img src={p.image} alt={p.name} /></div>)}</div>
      </section>
      <HScroll />
      <Story />
      <Shelf />
      <div className="marquee"><div>{TICK.repeat(6)}</div></div>
      {!!strip.length && <section className="pmq"><div>{Array.from({ length: 2 * Math.ceil(10 / strip.length) }, () => strip).flat().map((p, i) => <Link key={i} href={'/product/' + p.slug}><img src={p.image} alt={p.name} /></Link>)}</div></section>}
    </>
  )
}
