export const metadata = { title: 'Contact | Al-Asra Store' }
export default function Page() {
  return (
    <section className="sec narrow">
      <h1 className="ptitle">Contact us</h1>
      <p className="lead">Questions about an order or a product? We reply Monday to Saturday, 11:00am to 8:30pm.</p>
      <div className="cinfo"><div><b>Phone</b><a href="tel:+923159259927">+92 315 9259927</a></div><div><b>Email</b><a href="mailto:support@alasra.online">support@alasra.online</a></div><div><b>Location</b><span>Karachi, Pakistan</span></div><div><b>Instagram</b><a href="https://instagram.com/alasra.onlinestore">@alasra.onlinestore</a></div></div>
      <form className="fields" action="mailto:support@alasra.online" method="post" encType="text/plain">
        <input name="name" placeholder="Your name" required /><input name="email" type="email" placeholder="Your email" required />
        <textarea name="message" placeholder="How can we help?" rows={5} required /><button className="btn">Send message</button>
      </form>
    </section>
  )
}
