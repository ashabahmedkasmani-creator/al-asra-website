'use client'
import { useEffect } from 'react'
import { usePathname } from 'next/navigation'
import { CartProvider } from './Cart'
import { CatalogProvider } from './Catalog'
import Loader from './Loader'
import FX from './FX'
import Header from './Header'
import Footer from './Footer'
import { S } from '../lib/store'
const SEL = '.pcard,.cat,.trust>div,.sec>h2,.shead,.insta a,.ptitle,.box,.crow,.pdp>div,.cinfo>div,.hcard'
export default function Root({ children }) {
  const path = usePathname()
  useEffect(() => {
    let lenis, raf
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) import('lenis').then(({ default: L }) => { lenis = new L({ lerp: 0.09 }); const loop = (t) => { lenis.raf(t); raf = requestAnimationFrame(loop) }; raf = requestAnimationFrame(loop) })
    const sc = () => {
      const max = document.documentElement.scrollHeight - innerHeight
      S.p = max > 0 ? Math.min(1, scrollY / max) : 0
      document.documentElement.style.setProperty('--sy', Math.min(scrollY, 900))
    }
    const mm = (e) => { S.mx = e.clientX / innerWidth - 0.5; S.my = e.clientY / innerHeight - 0.5 }
    addEventListener('scroll', sc, { passive: true }); addEventListener('resize', sc); addEventListener('mousemove', mm); sc()
    return () => { cancelAnimationFrame(raf); lenis && lenis.destroy(); removeEventListener('scroll', sc); removeEventListener('resize', sc); removeEventListener('mousemove', mm) }
  }, [])
  useEffect(() => {
    const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target) } }), { threshold: 0.12 })
    const seen = new WeakSet() // per-effect (NOT dataset): React StrictMode runs this effect twice in dev, a dataset flag made the 2nd run skip every element
    const scan = () => document.querySelectorAll(SEL).forEach((el) => {
      if (seen.has(el)) return
      seen.add(el); el.style.setProperty('--d', ((Array.prototype.indexOf.call(el.parentNode.children, el) % 4) * 0.09) + 's'); el.classList.add('rv'); io.observe(el)
    })
    scan(); const mo = new MutationObserver(scan); mo.observe(document.querySelector('main'), { childList: true, subtree: true })
    return () => { io.disconnect(); mo.disconnect() }
  }, [path])
  return <CatalogProvider><CartProvider><Loader /><FX /><Header /><main>{children}</main><Footer /></CartProvider></CatalogProvider>
}
