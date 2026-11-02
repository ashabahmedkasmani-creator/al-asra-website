'use client'
import { Canvas, useFrame, useLoader } from '@react-three/fiber'
import { Suspense, useRef } from 'react'
import { useRouter } from 'next/navigation'
import * as THREE from 'three'
import { S } from '../lib/store'
function Item({ p, x, y, on }) {
  const g = useRef(), r = useRouter(), tex = useLoader(THREE.TextureLoader, p.image)
  tex.colorSpace = THREE.SRGBColorSpace
  const h = useRef(false)
  useFrame((_, dt) => { const k = 1 - Math.pow(0.001, dt), t = h.current; g.current.position.z += ((t ? 0.6 : 0) - g.current.position.z) * k; g.current.scale.setScalar(g.current.scale.x + ((t ? 1.12 : 1) - g.current.scale.x) * k); g.current.rotation.y += ((t ? 0.15 : 0) - g.current.rotation.y) * k })
  return (
    <group position={[x, y, 0]}>
      <group ref={g} onPointerOver={(e) => { e.stopPropagation(); h.current = true; on(p); document.body.style.cursor = 'pointer' }} onPointerOut={() => { h.current = false; on(null); document.body.style.cursor = '' }} onClick={() => r.push('/product/' + p.slug)}>
        <mesh position={[0, 0.78, -0.02]}><planeGeometry args={[1.5, 1.5]} /><meshBasicMaterial color="#fff" /></mesh>
        <mesh position={[0, 0.78, 0]}><planeGeometry args={[1.4, 1.4]} /><meshBasicMaterial map={tex} toneMapped={false} /></mesh>
      </group>
    </group>
  )
}
function Rig({ items, on }) {
  const per = Math.ceil(items.length / 2), W = per * 1.9
  useFrame(({ camera }) => { camera.position.x += ((-W / 2 + 1 + S.sp * (W - 3) + S.mx * 0.8) - camera.position.x) * 0.06; camera.position.y += ((0.2 - S.my * 0.6) - camera.position.y) * 0.06; camera.lookAt(camera.position.x * 0.9, 0.2, 0) })
  return (
    <>
      <ambientLight intensity={1} /><pointLight position={[0, 3, 4]} intensity={30} color="#ffffff" />
      {[0, 1].map((row) => <group key={row}><mesh position={[0, row ? -1.25 : 1.05 - 0.04, -0.15]}><boxGeometry args={[W + 8, 0.07, 1]} /><meshStandardMaterial color="#D9D3CC" /></mesh><mesh position={[0, row ? -1.2 : 1.1, 0.34]}><boxGeometry args={[W + 8, 0.012, 0.012]} /><meshBasicMaterial color="#F24C00" /></mesh></group>)}
      {items.map((p, i) => <Item key={p.id} p={p} on={on} x={(i % per) * 1.9 - W / 2 + 1 + (i >= per ? 0.95 : 0)} y={i < per ? 1.1 : -1.2} />)}
    </>
  )
}
export default function Shelf3D({ items, on }) {
  return <Canvas dpr={[1, 2]} camera={{ position: [0, 0.2, 6.5], fov: 42 }} gl={{ alpha: true, antialias: true }}><Suspense fallback={null}><Rig items={items} on={on} /></Suspense></Canvas>
}
