export const metadata = { title: 'Delivery & payment | Al-Asra Store' }
const Q = [['How much is delivery?', 'Delivery is free on orders over PKR 10,000. Smaller orders have a delivery charge shown at checkout.'], ['How can I pay?', 'Cash on delivery or bank transfer.'], ['Do you deliver across Pakistan?', 'Yes. We ship from Karachi to all of Pakistan.'], ['Are the products original?', 'Every item is organic, original and imported.'], ['How can I track my order?', 'Use the Track order page with your order number and phone number.'], ['When can I reach support?', 'Monday to Saturday, 11:00am to 8:30pm, on +92 315 9259927 or support@alasra.online.']]
export default function Page() {
  return <section className="sec narrow"><h1 className="ptitle">Delivery & payment</h1>{Q.map(([q, a]) => <details key={q}><summary>{q}</summary><p>{a}</p></details>)}</section>
}
