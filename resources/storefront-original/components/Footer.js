import Link from 'next/link'
import { SOCIAL } from '../lib/data'
export default function Footer() {
  return (
    <footer className="foot">
      <div className="fgrid">
        <div><img src="/logo.png" alt="Al-Asra Store" className="flogo" /><p>Organic, original and imported products. Delivered across Pakistan.</p></div>
        <div><h4>Shop</h4><Link href="/shop">All products</Link><Link href="/shop?cat=Organic">Organic</Link><Link href="/shop?cat=Natural%20Care">Natural care</Link><Link href="/shop?cat=Grocery">Grocery</Link></div>
        <div><h4>Help</h4><Link href="/track">Track order</Link><Link href="/faq">Delivery & payment</Link><Link href="/about">About us</Link><Link href="/contact">Contact</Link></div>
        <div><h4>Reach us</h4><p>Karachi, Pakistan<br /><a href="tel:+923159259927">+92 315 9259927</a><br /><a href="mailto:support@alasra.online">support@alasra.online</a></p><p>Mon–Sat, 11:00am–8:30pm</p><a href={SOCIAL}>Instagram @alasra.onlinestore</a></div>
      </div>
      <div className="fbase"><span>© {new Date().getFullYear()} Al-Asra Store. All rights reserved.</span><span>Cash on delivery · Bank transfer</span></div>
    </footer>
  )
}
