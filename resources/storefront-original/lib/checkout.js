// Checkout bridge for the Laravel API. Product prices and totals are always recalculated server-side.
const API = '/api'

async function jsonOrError(response) {
  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    const validation = payload.errors
      ? Object.values(payload.errors).flat().join(' ')
      : ''
    throw new Error(validation || payload.message || `Request failed (${response.status})`)
  }
  return payload
}

async function findLaravelProducts(items) {
  const wanted = new Set(items.map((item) => item.slug))
  const found = new Map()
  let page = 1
  let lastPage = 1

  do {
    const response = await fetch(`${API}/products?per_page=48&page=${page}`, {
      headers: { Accept: 'application/json' },
      cache: 'no-store',
    })
    const payload = await jsonOrError(response)

    for (const product of payload.data || []) {
      if (wanted.has(product.slug)) found.set(product.slug, product)
    }

    lastPage = Number(payload.meta?.last_page || page)
    page += 1
  } while (page <= lastPage && found.size < wanted.size)

  const missing = [...wanted].filter((slug) => !found.has(slug))
  if (missing.length) {
    throw new Error(`Some products are not available in the store catalogue: ${missing.join(', ')}. Please contact the store.`)
  }

  return found
}

export async function placeOrder({ items, form }) {
  if (!Array.isArray(items) || items.length === 0) {
    throw new Error('Your bag is empty.')
  }

  const products = await findLaravelProducts(items)
  const lines = items.map((item) => {
    const product = products.get(item.slug)
    if (!product?.is_purchasable) {
      throw new Error(`${item.name || 'A product'} is currently unavailable.`)
    }

    return {
      product_id: Number(product.id),
      quantity: Number(item.qty),
    }
  })

  const idempotencyKey = globalThis.crypto?.randomUUID
    ? globalThis.crypto.randomUUID().replaceAll('-', '')
    : `alasra-${Date.now()}-${Math.random().toString(36).slice(2)}`

  const response = await fetch(`${API}/checkout`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'Idempotency-Key': idempotencyKey,
    },
    body: JSON.stringify({
      items: lines,
      customer_name: form.name,
      customer_phone: form.phone,
      customer_email: form.email || null,
      address_line: form.address,
      city: form.city,
      province: form.province || null,
      payment_method: form.pay === 'bank' ? 'bank_transfer' : 'cod',
    }),
  })

  const payload = await jsonOrError(response)
  const reference = payload.data?.reference
  if (!reference) throw new Error('The order was submitted, but the server did not return an order reference.')

  return { ok: true, id: reference, order: payload.data }
}
