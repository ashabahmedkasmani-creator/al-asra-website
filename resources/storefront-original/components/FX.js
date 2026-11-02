'use client'
import { useEffect, useRef, useState } from 'react'
import { usePathname } from 'next/navigation'
// cursor spotlight + magnetic buttons + page-wipe transition
export default function FX() {
  const path = usePathname(), sp = useRef(), [k, setK] = useState(0)
  useEffect(() => { setK((n) => n + 1) }, [path])
  useEffect(() => {
    if (matchMedia('(hover:none)').matches) return
    let cur
    const mm = (e) => {
      if (!sp.current) return
      sp.current.style.transform = `translate(${e.clientX - 300}px,${e.clientY - 300}px)`
      const t = e.target.closest && e.target.closest('.btn,.pcard button,.cartbtn')
      if (cur && cur !== t) cur.style.transform = ''
      if (t) { const r = t.getBoundingClientRect(); t.style.transform = `translate(${(e.clientX - r.left - r.width / 2) * 0.25}px,${(e.clientY - r.top - r.height / 2) * 0.35}px)` }
      cur = t
    }
    addEventListener('mousemove', mm); return () => removeEventListener('mousemove', mm)
  }, [])
  return <><div className="spot" ref={sp} /><div key={k} className="wipe" /></>
}
