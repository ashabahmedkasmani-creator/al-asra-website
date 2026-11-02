'use client'
import dynamic from 'next/dynamic'
import { useEffect, useRef, useState } from 'react'
import { fmt } from '../lib/data'
import { useCatalog } from './Catalog'
import { S } from '../lib/store'
const Shelf3D = dynamic(() => import('./Shelf3D'), { ssr: false })
export default function Shelf() {
  const { products: PRODUCTS } = useCatalog()
  const items = PRODUCTS.filter((p) => p.image).slice(0, 12)
  const sec = useRef(), [h, setH] = useState(null)
  useEffect(() => {
    if (items.length < 4) return
    const f = () => { if (!sec.current) return; const r = sec.current.getBoundingClientRect(); S.sp = Math.min(1, Math.max(0, -r.top / (r.height - innerHeight))) }
    addEventListener('scroll', f, { passive: true }); f(); return () => removeEventListener('scroll', f)
  }, [items.length])
  if (items.length < 4) return null
  return (
    <section ref={sec} className="shelf">
      <div className="shelfp">
        <div className="shead2"><span className="kick">Virtual store</span><h2>Walk the shelf</h2><p>Scroll to move. Click any product.</p></div>
        <Shelf3D items={items} on={setH} />
        <div className={'shtip' + (h ? ' on' : '')}>{h && <><b>{h.name}</b><span>{fmt(h.price)}</span></>}</div>
      </div>
    </section>
  )
}
