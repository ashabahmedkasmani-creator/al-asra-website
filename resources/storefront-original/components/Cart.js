'use client'
import { createContext, useContext, useEffect, useReducer, useState } from 'react'
import Link from 'next/link'
import Img from './Img'
import { FREE_FROM, fmt } from '../lib/data'

const Ctx = createContext(null)
export const useCart = () => useContext(Ctx)
function reducer(s, a) {
  switch (a.type) {
    case 'load': return a.items
    case 'add': return s.find((i) => i.id === a.p.id) ? s.map((i) => (i.id === a.p.id ? { ...i, qty: i.qty + a.n } : i)) : [...s, { ...a.p, qty: a.n }]
    case 'qty': return s.map((i) => (i.id === a.id ? { ...i, qty: i.qty + a.d } : i)).filter((i) => i.qty > 0)
    case 'remove': return s.filter((i) => i.id !== a.id)
    default: return []
  }
}
export function CartProvider({ children }) {
  const [items, dispatch] = useReducer(reducer, [])
  const [open, setOpen] = useState(false)
  const [ready, setReady] = useState(false)
  useEffect(() => { try { dispatch({ type: 'load', items: JSON.parse(localStorage.getItem('alasra-cart') || '[]') }) } catch {} setReady(true) }, [])
  useEffect(() => { if (ready) localStorage.setItem('alasra-cart', JSON.stringify(items)) }, [items, ready])
  const total = items.reduce((t, i) => t + i.price * i.qty, 0)
  const count = items.reduce((t, i) => t + i.qty, 0)
  const add = (p, n = 1) => { dispatch({ type: 'add', n, p: { id: p.id, slug: p.slug, name: p.name, size: p.size, price: p.price, color: p.color, image: p.image } }); setOpen(true) }
  const qty = (id, d) => dispatch({ type: 'qty', id, d }), remove = (id) => dispatch({ type: 'remove', id }), clear = () => dispatch({ type: 'clear' })
  const left = Math.max(0, FREE_FROM - total)
  return (
    <Ctx.Provider value={{ items, total, count, add, qty, remove, clear, open, setOpen }}>
      {children}
      <div className={'scrim' + (open ? ' on' : '')} onClick={() => setOpen(false)} />
      <aside className={'drawer' + (open ? ' on' : '')} aria-hidden={!open}>
        <header><h3>Your bag ({count})</h3><button onClick={() => setOpen(false)} aria-label="Close">✕</button></header>
        <div className="ship"><p>{left ? `Add ${fmt(left)} more for free delivery` : 'You get free delivery'}</p><div className="bar"><i style={{ width: Math.min(100, (total / FREE_FROM) * 100) + '%' }} /></div></div>
        <div className="lines">
          {!items.length && <p className="empty">Your bag is empty. Add something from the shop.</p>}
          {items.map((i) => (
            <div className="line" key={i.id}>
              <Img className="lt" src={i.image} color={i.color} alt={i.name} />
              <div><b>{i.name}</b><small>{fmt(i.price)}</small></div>
              <div className="qty"><button onClick={() => qty(i.id, -1)}>−</button><span>{i.qty}</span><button onClick={() => qty(i.id, 1)}>+</button></div>
            </div>
          ))}
        </div>
        <footer>
          <div className="sum"><span>Subtotal</span><b>{fmt(total)}</b></div>
          <Link className={'btn' + (items.length ? '' : ' off')} href="/checkout" onClick={() => setOpen(false)}>Checkout</Link>
          <Link className="btn ghost" href="/cart" onClick={() => setOpen(false)}>View bag</Link>
        </footer>
      </aside>
    </Ctx.Provider>
  )
}
