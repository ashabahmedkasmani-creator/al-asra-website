'use client'
import { useState } from 'react'
import Link from 'next/link'
import { useCart } from '../../components/Cart'
import { fmt, shipping } from '../../lib/data'
import { placeOrder } from '../../lib/checkout'
export default function Page() {
  const { items, total, clear } = useCart(); const [done, setDone] = useState(null), [busy, setBusy] = useState(false), [error, setError] = useState('')
  const fee = shipping(total)
  const submit = async (e) => {
    e.preventDefault(); setBusy(true)
    const form = Object.fromEntries(new FormData(e.target))
    setError('')
    try {
      const r = await placeOrder({ items, total: total + fee, form })
      if (r.ok) { setDone(r.id); clear() }
    } catch (err) {
      setError(err?.message || 'We could not place your order. Please try again.')
    } finally {
      setBusy(false)
    }
  }
  if (done) return <section className="sec narrow"><h1 className="ptitle">Order placed</h1><p>Thank you. Your order number is <b>{done}</b>. We will confirm it on the phone number you gave.</p><Link className="btn" href="/shop">Continue shopping</Link></section>
  if (!items.length) return <section className="sec"><h1 className="ptitle">Checkout</h1><p className="empty">Your bag is empty. <Link href="/shop" className="lnk">Go to shop</Link></p></section>
  return (
    <section className="sec">
      <h1 className="ptitle">Checkout</h1>
      <form className="two" onSubmit={submit}>
        <div className="fields">
          <input name="name" placeholder="Full name" required /><input name="phone" placeholder="Phone number" required inputMode="tel" />
          <input name="email" type="email" placeholder="Email (optional)" /><input name="city" placeholder="City" required />
          <textarea name="address" placeholder="Full delivery address" required rows={3} />
          <fieldset><legend>Payment</legend><label><input type="radio" name="pay" value="cod" defaultChecked /> Cash on delivery</label><label><input type="radio" name="pay" value="bank" /> Bank transfer</label></fieldset>
        </div>
        <aside className="box"><h3>Your order</h3>{items.map((i) => <div className="sr" key={i.id}><span>{i.name} × {i.qty}</span><b>{fmt(i.price * i.qty)}</b></div>)}
          <div className="sr"><span>Delivery</span><b>{fee ? fmt(fee) : 'Free'}</b></div><div className="sr tot"><span>Total</span><b>{fmt(total + fee)}</b></div>
          {error && <p role="alert" className="checkout-error">{error}</p>}
          <button className="btn" disabled={busy}>{busy ? 'Placing order…' : 'Place order'}</button></aside>
      </form>
    </section>
  )
}
