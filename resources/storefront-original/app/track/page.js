'use client'
import { useState } from 'react'
export default function Page() {
  const [msg, setMsg] = useState('')
  return (
    <section className="sec narrow">
      <h1 className="ptitle">Track your order</h1>
      <p className="lead">Enter your order number and phone number.</p>
      <form className="fields" onSubmit={(e) => { e.preventDefault(); setMsg('Order tracking will work once your backend is connected (app/track/page.js).') }}>
        <input placeholder="Order number" required /><input placeholder="Phone number" required inputMode="tel" /><button className="btn">Track order</button>
      </form>
      {msg && <p className="empty">{msg}</p>}
    </section>
  )
}
