'use client'
import { useEffect, useState } from 'react'

// load (cart rides the line) -> hold -> open (full maroon screen slides up) -> done
export default function Loader() {
  const [n, setN] = useState(0)
  const [phase, setPhase] = useState('load')
  useEffect(() => {
    document.documentElement.classList.add('lock')
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches
    const min = reduce ? 400 : 2400
    const t0 = performance.now()
    let loaded = document.readyState === 'complete', fonts = false, raf
    addEventListener('load', () => (loaded = true), { once: true })
    document.fonts ? document.fonts.ready.then(() => (fonts = true)) : (fonts = true)
    const tick = (t) => {
      const time = Math.min(1, (t - t0) / min)
      const ease = 1 - Math.pow(1 - time, 3)
      const cap = loaded && fonts ? 100 : 92 // hold at 92 until page is really ready
      const v = Math.min(cap, Math.round(ease * 100))
      setN(v)
      if (v >= 100) { setPhase('hold'); return }
      raf = requestAnimationFrame(tick)
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [])
  useEffect(() => {
    if (phase === 'hold') { const a = setTimeout(() => setPhase('open'), 380); return () => clearTimeout(a) }
    if (phase === 'open') {
      document.documentElement.classList.add('ready')
      const b = setTimeout(() => { setPhase('done'); document.documentElement.classList.remove('lock') }, 900)
      return () => clearTimeout(b)
    }
  }, [phase])
  if (phase === 'done') return null
  return (
    <div className={'loader ' + phase} role="status" aria-label="Loading">
      <div className="lmid">
        <div className="lwrap">
          <svg className="lcart" style={{ left: n + '%' }} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M2 3h3l2.6 12.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 7H6.2" /><circle cx="9.5" cy="20" r="1.4" /><circle cx="17" cy="20" r="1.4" /></svg>
          <div className="lbar"><i style={{ transform: `scaleX(${n / 100})` }} /></div>
          <div className="lnum">{n}%</div>
        </div>
      </div>
    </div>
  )
}
