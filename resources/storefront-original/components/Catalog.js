'use client'
import { createContext, useContext, useEffect, useMemo, useState } from 'react'

const CatalogContext = createContext({ products: [], categories: [], loading: true, error: null })

function normalizeProduct(p) {
  const images = (p.images || []).map((image) => image.url).filter(Boolean)
  const regular = Number(p.price || 0)
  const effective = Number(p.effective_price ?? p.sale_price ?? p.price ?? 0)
  return {
    id: String(p.id),
    slug: p.slug,
    name: p.name,
    price: effective,
    oldPrice: p.on_sale ? regular : null,
    cat: p.category?.name || 'Shop',
    image: images[0] || '',
    images,
    desc: p.short_description || p.description || '',
    size: '',
    color: '#F15A24',
    availability: p.availability,
    isPurchasable: p.is_purchasable !== false,
    sku: p.sku,
    isFeatured: Boolean(p.is_featured),
    showOnHome: Boolean(p.show_on_home),
  }
}

export function CatalogProvider({ children }) {
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    let active = true
    async function load() {
      try {
        const all = []
        let page = 1
        let lastPage = 1
        do {
          const response = await fetch(`/api/products?per_page=48&page=${page}`, {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
          })
          const payload = await response.json().catch(() => ({}))
          if (!response.ok) throw new Error(payload.message || `Catalogue request failed (${response.status})`)
          all.push(...(payload.data || []))
          lastPage = Number(payload.meta?.last_page || page)
          page += 1
        } while (page <= lastPage && active)
        if (active) {
          setProducts(all.map(normalizeProduct))
          setError(null)
        }
      } catch (e) {
        if (active) setError(e.message || 'Could not load the product catalogue.')
      } finally {
        if (active) setLoading(false)
      }
    }
    load()
    return () => { active = false }
  }, [])

  const categories = useMemo(() => ['All', ...Array.from(new Set(products.map((p) => p.cat).filter(Boolean)))], [products])
  return <CatalogContext.Provider value={{ products, categories, loading, error }}>{children}</CatalogContext.Provider>
}

export function useCatalog() {
  return useContext(CatalogContext)
}

export function mapApiProduct(p) {
  return normalizeProduct(p)
}
