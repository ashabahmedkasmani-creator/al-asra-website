'use client'
import { useEffect, useRef, useState } from 'react'
import Link from 'next/link'

// TinyTrails 404 composition, Al-Asra branding/palette. The global Header/Footer are hidden on this page only (html.nf).
const VIDEO = 'https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260713_234424_b1332b69-2e69-4302-8dbc-40f86846afbd.mp4'
const NAV = [['Home', '/'], ['Shop', '/shop'], ['About', '/about'], ['Track order', '/track'], ['FAQ', '/faq'], ['Contact', '/contact']]
const I = { fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', className: 'nf-ic', 'aria-hidden': true, viewBox: '0 0 24 24' }
const MenuI = () => <svg {...I}><path d="M4 12h16M4 6h16M4 18h16" /></svg>
const XI = () => <svg {...I}><path d="M18 6 6 18M6 6l12 12" /></svg>
const ArrowI = () => <svg {...I}><path d="m12 19-7-7 7-7M19 12H5" /></svg>

export default function NotFoundView() {
  const stage = useRef(null), num = useRef(null)
  const [open, setOpen] = useState(false)
  useEffect(() => {
    const root = document.documentElement, prev = document.title
    document.title = '404 — Page Not Found | Al-Asra'
    root.classList.add('nf')
    const fit = () => {
      const el = num.current; if (!el || !stage.current || !el.offsetHeight) return
      stage.current.style.setProperty('--nfy', String((innerHeight / el.offsetHeight) * 1.4))
    }
    fit(); addEventListener('resize', fit)
    document.fonts?.ready.then(fit)
    const t = setTimeout(fit, 300)
    const esc = (e) => e.key === 'Escape' && setOpen(false)
    addEventListener('keydown', esc)
    return () => { root.classList.remove('nf'); document.title = prev; removeEventListener('resize', fit); removeEventListener('keydown', esc); clearTimeout(t) }
  }, [])
  return (
    <div className="nf-stage" ref={stage}>
      <div className="nf-bgl" aria-hidden="true">
        <span className="nf-404" ref={num}>404</span>
        <div className="nf-oval" />
      </div>
      <div className="nf-vid" aria-hidden="true">
        <div><video src={VIDEO} autoPlay loop muted playsInline preload="auto" onError={(e) => (e.currentTarget.style.display = 'none')} /></div>
      </div>
      <header className="nf-nav">
        <Link href="/" className="nf-logo" aria-label="Al-Asra Store home"><img src="/logo.png" alt="Al-Asra Store" /></Link>
        <nav className="nf-links" aria-label="Main">{NAV.map(([t, h]) => <Link key={h} href={h} className="nf-pill">{t}</Link>)}</nav>
        <button className="nf-pill nf-menubtn" onClick={() => setOpen(true)} aria-label="Open menu" aria-expanded={open}><MenuI />Menu</button>
      </header>
      <div className="nf-bottom">
        <h1>Oops, something went wrong!</h1>
        <Link href="/" className="nf-cta"><ArrowI />Back to Home</Link>
      </div>
      <div className={'nf-ov' + (open ? ' on' : '')} aria-hidden={!open}>
        <div className="nf-back" onClick={() => setOpen(false)} />
        <aside className="nf-panel">
          <div className="nf-ph">
            <span className="nf-logo"><img src="/logo.png" alt="Al-Asra Store" /></span>
            <button className="nf-x" onClick={() => setOpen(false)} aria-label="Close menu"><XI /></button>
          </div>
          <nav className="nf-items">{NAV.map(([t, h], i) => <Link key={h} href={h} style={{ '--i': i }} onClick={() => setOpen(false)}>{t}</Link>)}</nav>
          <div className="nf-pb"><Link href="/" onClick={() => setOpen(false)}><ArrowI />Back to Home</Link></div>
        </aside>
      </div>
    </div>
  )
}
