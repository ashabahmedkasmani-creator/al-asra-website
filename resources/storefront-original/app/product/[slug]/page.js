import { notFound } from 'next/navigation'
import ProductView from '../../../components/ProductView'
import { PRODUCTS } from '../../../lib/data'
export const generateStaticParams = () => PRODUCTS.map((p) => ({ slug: p.slug }))
export const dynamicParams = false
export function generateMetadata({ params }) { const p = PRODUCTS.find((x) => x.slug === params.slug); return { title: p ? p.name + ' | Al-Asra Store' : 'Product' } }
export default function Page({ params }) { const p = PRODUCTS.find((x) => x.slug === params.slug); if (!p) notFound(); return <ProductView p={p} /> }
