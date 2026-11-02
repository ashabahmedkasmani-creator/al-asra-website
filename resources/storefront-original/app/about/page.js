import Img from '../../components/Img'
import Experience from '../../components/Experience'
export const metadata = { title: 'About | Al-Asra Store' }
export default function Page() {
  return (
    <>
      <section className="sec narrow">
        <h1 className="ptitle">About Al-Asra</h1>
        <p className="lead">Welcome to Al-Asra Online Store. We bring you a wide range of high-quality products for everyday needs, shipped from Karachi to all of Pakistan.</p>
        <h3>Pure and organic</h3><p>Every item is organic, original and imported.</p>
        <h3>Natural care</h3><p>Rooted in nature, our Flawless range gives clean, natural care that restores your beauty.</p>
        <h3>Easy ordering</h3><p>Free delivery on orders up to PKR 10,000 and above, cash on delivery or bank transfer, and real-time order tracking.</p>
      </section>
      <Experience />
    </>
  )
}
