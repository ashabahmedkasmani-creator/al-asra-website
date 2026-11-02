'use client'
import { useEffect, useRef, useState } from 'react'
// src -> fallback -> neutral placeholder (never an empty/broken box)
export default function Img({ src, fallback = '', alt = '', color = '#F24C00', className = '' }) {
  const [cur, setCur] = useState(src || fallback)
  const [tried, setTried] = useState(!src && fallback ? 1 : 0)
  const ref = useRef()
  const fail = () => { if (!tried && fallback && cur !== fallback) { setCur(fallback); setTried(1) } else setCur('') }
  useEffect(() => { setCur(src || fallback); setTried(!src && fallback ? 1 : 0) }, [src, fallback])
  // slow disk / fast SSR: the image can fail before React attaches onError, so re-check after mount
  useEffect(() => { const i = ref.current; if (i && i.complete && i.naturalWidth === 0) fail() }, [cur])
  if (!cur) return <div className={'ph ' + className} style={{ '--c': color }} role="img" aria-label={alt}><i /></div>
  return <img ref={ref} className={className} src={cur} alt={alt} onError={fail} />
}
