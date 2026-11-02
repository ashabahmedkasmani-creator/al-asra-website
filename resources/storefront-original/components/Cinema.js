'use client'
import Link from 'next/link'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useCart } from './Cart'
import { fmt } from '../lib/data'
import { useCatalog } from './Catalog'
export default function Cinema() {
  const { products: PRODUCTS } = useCatalog()
  const list = useMemo(() => PRODUCTS.filter((p) => p.image).slice(0, 5), [PRODUCTS])
  const sec = useRef(), pin = useRef(), { add } = useCart(), [k, setK] = useState(0), [cols, setCols] = useState([])
  useEffect(() => {
    if (list.length < 3) return
    list.forEach((p, i) => { const im = new Image(); im.onload = () => { const c = document.createElement('canvas'); c.width = c.height = 8; const x = c.getContext('2d'); x.drawImage(im, 0, 0, 8, 8); const d = x.getImageData(0, 0, 8, 8).data; let r = 0, g = 0, b = 0, n = 0; for (let j = 0; j < d.length; j += 4) { if (d[j + 3] < 200 || d[j] + d[j + 1] + d[j + 2] > 690) continue; r += d[j]; g += d[j + 1]; b += d[j + 2]; n++ } if (n) setCols((a) => { const z = [...a]; z[i] = `rgb(${r / n | 0},${g / n | 0},${b / n | 0})`; return z }) }; im.src = p.image })
    if (list.length < 3) return
    const f = () => { if (!sec.current || !pin.current) return; const r = sec.current.getBoundingClientRect(), p = Math.min(0.999, Math.max(0, -r.top / (r.height - innerHeight))), s = p * list.length; pin.current.style.setProperty('--lp', s % 1); setK(Math.floor(s)) }
    addEventListener('scroll', f, { passive: true }); f(); return () => removeEventListener('scroll', f)
  }, [list])
  if (list.length < 3) return null
  return (
    <section ref={sec} className="cin" style={{ height: list.length * 100 + 'vh' }}>
      <div ref={pin} className="cinp" style={{ background: `radial-gradient(circle at 68% 50%, color-mix(in srgb, ${cols[k] || '#F24C00'} 14%, #ffffff), #F4F0EB 76%)` }}>
        {list.map((p, i) => (
          <div key={p.id} className={'cs' + (i === k ? ' on' : '')}>
            <h3 className="cbig">{p.name}</h3>
            <img src={p.image} alt={p.name} />
            <div className="cmeta"><small>{p.cat} · {i + 1}/{list.length}</small><b>{fmt(p.price)}</b><button className="btn" onClick={() => add(p)}>Add to bag</button><Link href={'/product/' + p.slug}>View product →</Link></div>
          </div>
        ))}
      </div>
    </section>
  )
}
