@extends('storefront.layout')
@section('title','Al-Asra Store | Organic & Original, Delivered in Pakistan')
@section('content')
@php
$visualProducts = collect($products)->values();
$imgFor = function($p) { $slug = $p->slug ?? ''; $map = ['portable-juice-blender'=>'/images/products/portable-blender.png','ice-cream-tumbler-with-straw'=>'/images/products/ice-cream-tumbler.png','electric-milk-frother'=>'/images/products/milk-frother.png']; return $p->images?->first()?->url ?? ($map[$slug] ?? '/logo.png'); };
$heroItems = $visualProducts->take(3);
$categoryItems = $categories->take(4);
@endphp
@if($heroItems->isNotEmpty())
<section class="ah ah-static" id="home-hero" style="background:#F4EEE9">
 <div class="ah-stage"><div class="ah-glow" style="background:radial-gradient(ellipse 50% 60% at 50% 60%,#fff,transparent 72%)"></div><div class="ah-grain"></div><div class="ah-brand"><i></i>AL ASRA</div>
  <div class="ah-carousel">
   @foreach($heroItems as $p)<div class="ah-item"><img src="{{ $imgFor($p) }}" alt="{{ $p->name }}"></div>@endforeach
  </div>
  <div class="ah-info"><div class="ah-name">{{ $heroItems->first()->name }}</div><p class="ah-desc">Discover useful products, everyday essentials and carefully selected finds — all in one place.</p><div class="ah-nav"><button class="ah-btn" type="button" onclick="heroShift(-1)" aria-label="Previous product">←</button><button class="ah-btn" type="button" onclick="heroShift(1)" aria-label="Next product">→</button></div></div>
  <a class="ah-cta" href="/shop">SHOP NOW <span class="ah-cta-ico">↗</span></a>
 </div>
</section>
@else
<section class="hero-fallback"><div><h1>AL ASRA</h1><p>Discover useful products, everyday essentials and carefully selected finds.</p><a class="btn" href="/shop">SHOP NOW</a></div></section>
@endif
<section class="trust"><div><b>Free delivery</b><span>On orders over PKR 10,000</span></div><div><b>Cash on delivery</b><span>Or bank transfer</span></div><div><b>Track your order</b><span>Real-time updates</span></div><div><b>Always here</b><span>Mon–Sat, 11:00am–8:30pm</span></div></section>
<section class="sec"><div class="center"><span class="kick">Shop by category</span><h2>Find what you need</h2></div><div class="cats">
@forelse($categoryItems as $category)
<a class="cat" href="/shop?category={{ $category->slug }}"><img src="{{ $category->slug==='drinkware'?'/images/products/ice-cream-tumbler.png':'/images/products/portable-blender.png' }}" alt="{{ $category->name }}"><div><h3>{{ $category->name }}</h3><p>{{ $category->products_count ?? 'Explore' }} products</p></div></a>
@empty
<a class="cat" href="/shop"><img src="/images/products/portable-blender.png" alt="Kitchen"><div><h3>Kitchen</h3><p>Explore products</p></div></a><a class="cat" href="/shop"><img src="/images/products/ice-cream-tumbler.png" alt="Drinkware"><div><h3>Drinkware</h3><p>Explore products</p></div></a>
@endforelse
</div></section>
<section class="sec"><div class="center"><span class="kick">Our selection</span><h2>Fresh from our shelves</h2></div><div class="chips mid" id="home-cats"><button class="on" data-cat="all">All</button>@foreach($categories as $c)<button data-cat="{{ $c->slug }}">{{ $c->name }}</button>@endforeach</div><div class="pgrid" id="home-products">
@forelse($products as $p)
@php
$cartItem = ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->effective_price, 'image' => $imgFor($p)];
@endphp
<article class="pcard" data-category="{{ $p->category?->slug }}"><a class="pic" href="/product/{{ $p->slug }}"><img src="{{ $imgFor($p) }}" alt="{{ $p->name }}"></a><small>{{ $p->category?->name ?? 'Al-Asra' }}</small><h3><a href="/product/{{ $p->slug }}">{{ $p->name }}</a></h3><div class="prow"><b>PKR {{ number_format((float)$p->effective_price) }}</b><button class="addbag" type="button" onclick='AlAsraCart.add(@json($cartItem))'>Add to bag</button></div></article>
@empty
<div class="empty">Our collection is being refreshed. Please check back soon.</div>
@endforelse
</div></section>
<section class="obnd"><div class="obt"><span class="kick w">Why Al-Asra</span><h2>Original products you can trust.</h2><p>Every item is checked before it ships from Karachi to your door.</p><a class="btn white" href="/shop">Browse the shop</a></div><div class="obv">@foreach($heroItems->take(2) as $p)<div class="ob"><img src="{{ $imgFor($p) }}" alt="{{ $p->name }}"></div>@endforeach</div></section>
<section class="story"><div class="story-head"><span class="kick">Discover</span><h2>Explore Al-Asra</h2><p>Discover useful products, everyday essentials and carefully selected finds — all in one place.</p></div><div class="story-cards">
@foreach([['01 / Featured Finds','Useful products, picked for everyday life.','Explore some of the products currently catching attention at Al-Asra — practical, useful and easy to love.','Explore Collection'],['02 / Everyday Essentials','Small upgrades that make everyday life easier.','From kitchen essentials to drinkware and useful everyday finds, discover products selected with practicality in mind.','Shop Essentials'],['03 / Customer Favourites','Some of the products worth discovering.','Take a closer look at popular Al-Asra picks and discover something useful for yourself.','Shop Favourites']] as $i=>$card)
<div class="ex-wrap"><article class="ex ex{{ $i }}"><div class="ex-txt"><small>{{ $card[0] }}</small><h3>{{ $card[1] }}</h3><p>{{ $card[2] }}</p><a class="btn" href="/shop">{{ $card[3] }}</a></div><div class="ex-art">@foreach($heroItems->take(3) as $p)<a href="/product/{{ $p->slug }}"><img src="{{ $imgFor($p) }}" alt="{{ $p->name }}"></a>@endforeach</div></article></div>
@endforeach
</div></section>
<div class="marquee"><div>{{ str_repeat('ORIGINAL ✦ DAILY ESSENTIALS ✦ FAST DELIVERY ✦ CASH ON DELIVERY ✦ DELIVERED ACROSS PAKISTAN ✦ ',5) }}</div></div>
<section class="pmq"><div>@foreach($products->concat($products)->take(12) as $p)<a href="/product/{{ $p->slug }}"><img src="{{ $imgFor($p) }}" alt="{{ $p->name }}"></a>@endforeach</div></section>
@push('scripts')<script>
let heroAt=0;function heroShift(d){const items=[...document.querySelectorAll('.ah-item')];if(!items.length)return;heroAt=(heroAt+d+items.length)%items.length;items.forEach((el,i)=>{let pos=(i-heroAt+items.length)%items.length;el.style.left=pos===0?'50%':pos===1?'70%':'30%';el.style.height=pos===0?'82%':'30%';el.style.bottom=pos===0?'0':'12%';el.style.zIndex=pos===0?'20':'10';el.style.opacity=pos===0?'1':'.85';el.style.filter=pos===0?'none':'blur(2px)';el.style.transform=`translateX(-50%) scale(${pos===0?1.12:1})`});const active=items[heroAt].querySelector('img');document.querySelector('.ah-name').textContent=active.alt}
document.querySelectorAll('#home-cats button').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('#home-cats button').forEach(x=>x.classList.remove('on'));b.classList.add('on');document.querySelectorAll('#home-products .pcard').forEach(p=>p.hidden=b.dataset.cat!=='all'&&p.dataset.category!==b.dataset.cat)}));
</script>@endpush
@endsection
