'use client'
import { Canvas, useFrame, useThree } from '@react-three/fiber'
import { forwardRef, useEffect, useMemo, useRef } from 'react'
import * as THREE from 'three'
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js'
import { S } from '../lib/store'

function Env() {
  const { gl, scene } = useThree()
  useEffect(() => {
    const pm = new THREE.PMREMGenerator(gl), t = pm.fromScene(new RoomEnvironment(), 0.03).texture
    scene.environment = t
    return () => { scene.environment = null; t.dispose(); pm.dispose() }
  }, [gl, scene])
  return null
}
const lathe = (pts) => new THREE.LatheGeometry(pts.map(([x, y]) => new THREE.Vector2(x, y)), 96)
const BOTTLE = lathe([[0, 0], [.5, 0], [.55, .06], [.55, 1.25], [.46, 1.5], [.2, 1.68], [.2, 1.85], [0, 1.85]])
const JAR = lathe([[0, 0], [.72, 0], [.78, .06], [.78, .9], [.72, .96], [0, .96]])
function labelTex(bg, fg, sub) {
  const c = document.createElement('canvas'); c.width = 1024; c.height = 512
  const x = c.getContext('2d'); x.fillStyle = bg; x.fillRect(0, 0, 1024, 512); x.fillStyle = fg; x.textAlign = 'center'
  x.font = '700 150px DM Sans, Arial, sans-serif'; x.fillText('Al-Asra', 512, 270)
  x.font = '500 46px DM Sans, Arial, sans-serif'; x.fillText(sub, 512, 350)
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.wrapS = THREE.RepeatWrapping; t.offset.x = 0.5; t.anisotropy = 8; return t
}
// glossy (NOT matte): strong clearcoat + bright environment reflections
const gloss = { roughness: 0.08, metalness: 0, clearcoat: 1, clearcoatRoughness: 0.03, envMapIntensity: 1.9 }
const metal = { roughness: 0.18, metalness: 0.85, envMapIntensity: 2 }
const Bottle = forwardRef(function Bottle({ color, bg, fg, sub, cap, ...p }, ref) {
  const tex = useMemo(() => labelTex(bg, fg, sub), [bg, fg, sub])
  return (
    <group ref={ref} {...p}>
      <mesh geometry={BOTTLE}><meshPhysicalMaterial color={color} {...gloss} /></mesh>
      <mesh position={[0, .72, 0]}><cylinderGeometry args={[.56, .56, .8, 96, 1, true]} /><meshPhysicalMaterial map={tex} roughness={0.2} clearcoat={1} clearcoatRoughness={0.05} envMapIntensity={1.4} /></mesh>
      <mesh position={[0, 2.0, 0]}><cylinderGeometry args={[.23, .25, .34, 48]} /><meshPhysicalMaterial color={cap} {...metal} /></mesh>
    </group>
  )
})
const Jar = forwardRef(function Jar({ color, bg, fg, sub, lid, ...p }, ref) {
  const tex = useMemo(() => labelTex(bg, fg, sub), [bg, fg, sub])
  return (
    <group ref={ref} {...p}>
      <mesh geometry={JAR}><meshPhysicalMaterial color={color} {...gloss} /></mesh>
      <mesh position={[0, .48, 0]}><cylinderGeometry args={[.785, .785, .55, 96, 1, true]} /><meshPhysicalMaterial map={tex} roughness={0.2} clearcoat={1} clearcoatRoughness={0.05} envMapIntensity={1.4} /></mesh>
      <mesh position={[0, 1.1, 0]}><cylinderGeometry args={[.8, .8, .3, 64]} /><meshPhysicalMaterial color={lid} {...metal} /></mesh>
    </group>
  )
})
// used when real product photos (transparent PNG) exist
const RR = (() => { const s = new THREE.Shape(), w = 1.3, r = .24; s.moveTo(-w + r, -w); s.lineTo(w - r, -w); s.quadraticCurveTo(w, -w, w, -w + r); s.lineTo(w, w - r); s.quadraticCurveTo(w, w, w - r, w); s.lineTo(-w + r, w); s.quadraticCurveTo(-w, w, -w, w - r); s.lineTo(-w, -w + r); s.quadraticCurveTo(-w, -w, -w + r, -w); return new THREE.ShapeGeometry(s) })()
const Card = forwardRef(function Card({ src, ...p }, ref) {
  const tex = useMemo(() => { const t = new THREE.TextureLoader().load(src); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8; return t }, [src])
  return (
    <group ref={ref} {...p}>
      <mesh position={[0, 1, 0]} geometry={RR}><meshBasicMaterial color="#fff" side={THREE.DoubleSide} /></mesh>
      <mesh position={[0, 1, 0.012]}><planeGeometry args={[2.25, 2.25]} /><meshBasicMaterial map={tex} toneMapped={false} side={THREE.DoubleSide} /></mesh>
    </group>
  )
})
// soft contact shadow so products sit on a surface
function Shadow() {
  const tex = useMemo(() => {
    const c = document.createElement('canvas'); c.width = c.height = 256
    const x = c.getContext('2d'), g = x.createRadialGradient(128, 128, 0, 128, 128, 128)
    g.addColorStop(0, 'rgba(60,8,22,.5)'); g.addColorStop(1, 'rgba(60,8,22,0)'); x.fillStyle = g; x.fillRect(0, 0, 256, 256)
    return new THREE.CanvasTexture(c)
  }, [])
  return <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, -1.13, 0]}><planeGeometry args={[9, 4]} /><meshBasicMaterial map={tex} transparent depthWrite={false} /></mesh>
}
// scroll keyframes: [progress, x, y, rotY, scale]
const KF = [[0, 1.55, 0, -0.45, 1], [0.34, -1.55, 0, 1.0, 1.12], [0.67, 1.55, 0.05, 2.35, 1.05], [1, 0, 0.2, 3.7, 0.92]]
const sm = (t) => t * t * (3 - 2 * t)
function kf(p) {
  for (let i = 0; i < KF.length - 1; i++) {
    const a = KF[i], b = KF[i + 1]
    if (p <= b[0]) { const t = sm(Math.max(0, (p - a[0]) / (b[0] - a[0]))); return a.map((v, j) => v + (b[j] - v) * t).slice(1) }
  }
  return KF[KF.length - 1].slice(1)
}
function Rig({ imgs }) {
  const g = useRef(), a = useRef(), b = useRef(), c = useRef(), l = useRef()
  useFrame(({ clock, size, camera }, dt) => {
    const t = clock.elapsedTime, k = 1 - Math.pow(0.0008, dt), m = size.width < 700
    const [x, y, r, s] = kf(S.hp), G = g.current
    G.position.x += ((m ? 0 : x) - G.position.x) * k
    G.position.y += ((m ? 1.0 + y : y) - G.position.y) * k
    G.rotation.y += ((r + S.mx * 0.5) - G.rotation.y) * k
    G.rotation.x += (S.my * 0.12 - G.rotation.x) * k
    const sc = (m ? 0.56 : 1) * s; G.scale.setScalar(G.scale.x + (sc - G.scale.x) * k)
    ;[a, b, c].forEach((o, i) => {
      o.current.position.y = -1.1 + Math.sin(t * 0.9 + i * 2) * 0.08
      if (imgs) o.current.rotation.y = -G.rotation.y * 0.85 + Math.sin(t * .6 + i) * .08
    })
    l.current.position.set(Math.sin(t * 0.7) * 5, 2.5, Math.cos(t * 0.7) * 4 + 2) // moving highlight
    camera.position.x += (S.mx * 0.5 - camera.position.x) * 0.05; camera.lookAt(0, 0.25, 0)
  })
  return (
    <>
      <pointLight ref={l} intensity={40} color="#fff2e6" />
      <group ref={g} rotation={[0, -0.45, 0]} position={[1.55, 0, 0]}>
        <Shadow />
        {imgs ? <>
          <Card ref={a} src={imgs[0]} position={[-1.7, -1.1, 0.2]} />
          <Card ref={b} src={imgs[1]} position={[0.2, -1.1, 0.6]} scale={1.15} />
          <Card ref={c} src={imgs[2]} position={[1.9, -1.1, -0.1]} />
        </> : <>
          <Bottle ref={a} position={[-1.7, -1.1, 0.2]} color="#5A1024" bg="#FFF4EA" fg="#5A1024" sub="CLEANSER" cap="#F05A1A" />
          <Jar ref={b} position={[0.2, -1.1, 0.6]} color="#FFF4EA" bg="#5A1024" fg="#FFF4EA" sub="ORGANIC HONEY" lid="#F05A1A" />
          <Bottle ref={c} position={[1.9, -1.1, -0.1]} scale={1.1} color="#FFFFFF" bg="#F05A1A" fg="#FFFFFF" sub="OLIVE OIL" cap="#5A1024" />
        </>}
      </group>
    </>
  )
}
export default function Hero3D({ imgs }) {
  return (
    <Canvas dpr={[1, 2]} camera={{ position: [0, 0.6, 8.5], fov: 30 }} gl={{ antialias: true, alpha: true }}>
      <Env /><ambientLight intensity={0.5} /><directionalLight position={[3, 5, 4]} intensity={2.2} /><pointLight position={[-4, 1, 3]} intensity={22} color="#ff8a4a" /><pointLight position={[4, 2, -4]} intensity={30} color="#ffffff" />
      <Rig imgs={imgs} />
    </Canvas>
  )
}
