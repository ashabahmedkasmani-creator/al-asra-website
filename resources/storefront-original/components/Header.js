'use client'
import Link from 'next/link'
import { useEffect, useState } from 'react'
import { usePathname } from 'next/navigation'
import { useCart } from './Cart'
const NAV = [['Shop', '/shop'], ['About', '/about'], ['Track order', '/track'], ['FAQ', '/faq'], ['Contact', '/contact']]
export default function Header() {
  const { count, setOpen } = useCart(); const [m, setM] = useState(false); const [sc, setSc] = useState(false); const path = usePathname()
  useEffect(() => { const f = () => setSc(scrollY > 40); f(); addEventListener('scroll', f, { passive: true }); return () => removeEventListener('scroll', f) }, [])
  const go = (e) => { e.preventDefault(); const q = new FormData(e.target).get('q'); location.href = '/shop?q=' + encodeURIComponent(q) }
  return (
    <>
      <div className="announce">Free delivery on orders over PKR 10,000 · Cash on delivery across Pakistan</div>
      <header className={'nav' + (sc ? ' sc' : '')}>
        <button className="burger" onClick={() => setM(!m)} aria-label="Menu">☰</button>
        <Link href="/" className="brand"><img src="/logo.png" alt="Al-Asra Store" /></Link>
        <nav className={m ? 'on' : ''} onClick={() => setM(false)}>{NAV.map(([t, h]) => <Link key={h} href={h} className={path === h || path.startsWith(h + '/') ? 'act' : ''}>{t}</Link>)}</nav>
        <form onSubmit={go} className="search"><input name="q" placeholder="Search products" aria-label="Search" /></form>
        <button className="cartbtn" onClick={() => setOpen(true)}>Bag <span>{count}</span></button>
      </header>
    </>
  )
}
