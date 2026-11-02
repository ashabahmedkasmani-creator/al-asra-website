import './globals.css'
import Root from '../components/Root'
export const metadata = {
  title: 'Al-Asra Store | Organic & Original, Delivered in Pakistan',
  description: 'Premium organic, imported and natural care products. Free delivery over PKR 10,000.',
  icons: { icon: '/logo.png' },
}
export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" /><link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
        <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
      </head>
      <body><Root>{children}</Root></body>
    </html>
  )
}
