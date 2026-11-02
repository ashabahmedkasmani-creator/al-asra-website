'use client'
import Link from 'next/link'
import Img from '../../components/Img'
import { useCart } from '../../components/Cart'
import { fmt, shipping } from '../../lib/data'
export default function Page() {
  const { items, total, qty, remove } = useCart(); const fee = shipping(total)
  return (
    <section className="sec">
      <h1 className="ptitle">Your bag</h1>
      {!items.length ? <p className="empty">Your bag is empty. <Link href="/shop" className="lnk">Continue shopping</Link></p> : (
        <div className="two">
          <div>{items.map((i) => (
            <div className="crow" key={i.id}>
              <Img className="lt" src={i.image} color={i.color} alt={i.name} />
              <div className="ci"><Link href={'/product/' + i.slug}><b>{i.name}</b></Link><small>{fmt(i.price)}</small><button className="rm" onClick={() => remove(i.id)}>Remove</button></div>
              <div className="qty"><button onClick={() => qty(i.id, -1)}>−</button><span>{i.qty}</span><button onClick={() => qty(i.id, 1)}>+</button></div>
              <b>{fmt(i.price * i.qty)}</b>
            </div>))}
          </div>
          <aside className="box"><h3>Summary</h3><div className="sr"><span>Subtotal</span><b>{fmt(total)}</b></div><div className="sr"><span>Delivery</span><b>{fee ? fmt(fee) : 'Free'}</b></div><div className="sr tot"><span>Total</span><b>{fmt(total + fee)}</b></div><Link className="btn" href="/checkout">Checkout</Link></aside>
        </div>)}
    </section>
  )
}
