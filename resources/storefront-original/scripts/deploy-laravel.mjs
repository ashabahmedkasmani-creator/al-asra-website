import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const here = path.dirname(fileURLToPath(import.meta.url))
const appRoot = path.resolve(here, '../../..')
const exportDir = path.resolve(here, '../out')
const publicDir = path.join(appRoot, 'public')
const pagesDir = path.join(publicDir, 'storefront-pages')

if (!fs.existsSync(path.join(exportDir, 'index.html'))) {
  console.error('Next static export not found. Run this with: npm run build:laravel')
  process.exit(1)
}

fs.rmSync(path.join(publicDir, '_next'), { recursive: true, force: true })
fs.rmSync(pagesDir, { recursive: true, force: true })
fs.mkdirSync(pagesDir, { recursive: true })

function copyDir(src, dst) {
  fs.mkdirSync(dst, { recursive: true })
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    const from = path.join(src, entry.name)
    const to = path.join(dst, entry.name)
    if (entry.isDirectory()) copyDir(from, to)
    else fs.copyFileSync(from, to)
  }
}

for (const entry of fs.readdirSync(exportDir, { withFileTypes: true })) {
  const src = path.join(exportDir, entry.name)
  if (entry.name === '_next' && entry.isDirectory()) {
    copyDir(src, path.join(publicDir, '_next'))
  } else if (entry.isDirectory()) {
    copyDir(src, path.join(pagesDir, entry.name))
  } else if (entry.name.endsWith('.html')) {
    fs.copyFileSync(src, path.join(pagesDir, entry.name))
  } else {
    // Next public assets (logo, product imagery, fonts, etc.) stay at their original root URLs.
    fs.copyFileSync(src, path.join(publicDir, entry.name))
  }
}
console.log('Original Al-Asra storefront exported into Laravel public assets.')
console.log('React hydration, Lenis smooth scrolling, scroll reveals, and Three.js scenes are bundled in public/_next.')
