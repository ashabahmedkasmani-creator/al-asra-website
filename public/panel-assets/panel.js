
const API=location.origin+'/api'; // Laravel API routes are mounted under /api
const ST=['pending','awaiting_supplier','confirmed','processing','shipped','delivered','cancelled','returned'];
const AV=['available','limited','supplier_confirmation_required','unavailable','discontinued'];
let token=sessionStorage.getItem('tk')||'',view='dash',cats=[];
const $=s=>document.querySelector(s),pkr=n=>'PKR '+Number(n||0).toLocaleString();
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function toast(t,err){const m=$('#msg');m.textContent=t;m.className=err?'toast err':'toast';m.style.display='block';clearTimeout(m.t);m.t=setTimeout(()=>m.style.display='none',3500)}
async function api(path,method='GET',body){
  const o={method,headers:{Accept:'application/json',Authorization:'Bearer '+token}};
  if(body instanceof FormData)o.body=body;else if(body){o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
  const r=await fetch(API+path,o);
  if(r.status===401&&token){logout();throw new Error('Session expired, login again')}
  if(r.status===204)return {};
  const j=await r.json().catch(()=>({}));
  if(!r.ok){const e=j.errors?Object.values(j.errors).flat().join(' '):(j.message||'Error '+r.status);throw new Error(e)}
  return j;
}
const run=async f=>{try{await f()}catch(e){toast(e.message,1)}};
function logout(){token='';sessionStorage.removeItem('tk');go('/admin/login')}
const PG=document.body.dataset.page,ID=document.body.dataset.id,go=u=>location.href=u;
function render(){
  document.body.classList.toggle('guest',!token);
  if(!token){
    $('#main').innerHTML=`<section class="simpleLogin" aria-labelledby="admin-login-title">
      <header class="simpleLoginBrand"><span class="simpleLoginMark">A</span><span><b>AL ASRA</b><small>ADMIN PANEL</small></span></header>
      <div class="simpleLoginIntro"><p class="simpleEyebrow">ADMINISTRATOR ACCESS</p><h1 id="admin-login-title">Welcome back</h1><p>Sign in to manage your store.</p></div>
      <form id="adminLoginForm" autocomplete="on" novalidate>
        <label for="em">Email address</label>
        <input id="em" name="email" type="email" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="admin@alasra.com" required>
        <div class="simplePasswordLabel"><label for="pw">Password</label><span>Forgot password?</span></div>
        <div class="simplePasswordWrap"><input id="pw" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required><button class="simplePasswordToggle" id="togglePassword" type="button" aria-label="Show password">Show</button></div>
        <button class="simpleLoginSubmit" id="go" type="submit">Sign in <span aria-hidden="true">→</span></button>
      </form>
      <p id="loginError" class="loginError" role="alert" aria-live="polite"></p>
      <footer class="simpleLoginFoot">Al Asra · Store administration</footer>
    </section>`;
    const form=$('#adminLoginForm'),email=$('#em'),password=$('#pw'),submit=$('#go'),error=$('#loginError'),toggle=$('#togglePassword');
    toggle.addEventListener('click',()=>{const show=password.type==='password';password.type=show?'text':'password';toggle.textContent=show?'Hide':'Show';toggle.setAttribute('aria-label',show?'Hide password':'Show password');password.focus()});
    // Keep the fields directly interactive and submit their actual current values.
    [email,password].forEach(input=>input.addEventListener('input',()=>{error.textContent=''}));
    form.addEventListener('submit',e=>{e.preventDefault();run(async()=>{
      const credentials={email:email.value.trim(),password:password.value};
      if(!credentials.email||!credentials.password){error.textContent='Enter both your email address and password.';return}
      submit.disabled=true;submit.textContent='Signing in…';error.textContent='';
      try{
        const j=await api('/auth/login','POST',credentials);
        if(!j.data?.user||!j.data?.token)throw new Error('Login response was incomplete.');
        if(!['admin','staff'].includes(j.data.user.role))throw new Error('This account is not admin/staff.');
        token=j.data.token;sessionStorage.setItem('tk',token);go('/admin/dashboard');
      }catch(err){error.textContent=err.message;throw err}
      finally{submit.disabled=false;submit.innerHTML='Sign in <span aria-hidden="true">→</span>'}
    })});
    return;
  }
  const isId=ID&&ID!=='new';
  const pages={dashboard:dash,orders:()=>isId?orderDetail(ID):orders(),products:()=>ID?productForm(isId?ID:undefined):products(),categories,coupons,banners,faqs,reviews,customers,staff,settings,audit};
  pages[PG]();
}
$('#lo').onclick=()=>{const finish=()=>{token='';sessionStorage.removeItem('tk');go('/admin/login')};if(token){api('/auth/logout','POST',{}).catch(()=>{}).finally(finish)}else{finish()}};
async function dash(){run(async()=>{const d=(await api('/admin/dashboard')).data;
  $('#main').innerHTML=`<div class="grid">${[['Orders total',d.orders_total],['Orders today',d.orders_today],['Products',d.products_total],['Published',d.products_published]].map(s=>`<div class="card"><div class="muted">${s[0]}</div><div class="stat">${s[1]}</div></div>`).join('')}</div>
  <div class="card"><h2>Orders by status</h2>${Object.entries(d.orders_by_status).map(([k,v])=>`<span class="badge b-${k}" style="margin:3px">${k.replace('_',' ')}: ${v}</span>`).join('')}</div>
  <div class="card"><h2>Recent orders</h2><table><tr><th>Ref</th><th>Customer</th><th>Total</th><th>Status</th></tr>${d.recent_orders.map(o=>`<tr><td>${esc(o.reference)}</td><td>${esc(o.customer_name)}</td><td>${pkr(o.grand_total)}</td><td><span class="badge b-${o.status}">${esc(o.status)}</span></td></tr>`).join('')}</table></div>`})}
async function orders(){run(async()=>{const st=window._os||'';const j=await api('/admin/orders?per_page=50'+(st?'&status='+st:''));
  $('#main').innerHTML=`<div class="card"><div class="row"><h2 style="flex:3">Orders</h2><select id="fs"><option value="">All statuses</option>${ST.map(s=>`<option ${s===st?'selected':''}>${s}</option>`).join('')}</select></div>
  <table><tr><th>Ref</th><th>Customer</th><th>Phone</th><th>Total</th><th>Payment</th><th>Status</th></tr>${j.data.map(o=>`<tr class="click" data-id="${o.id}"><td>${esc(o.reference)}</td><td>${esc(o.customer_name)}</td><td>${esc(o.customer_phone)}</td><td>${pkr(o.grand_total)}</td><td><span class="badge b-${o.payment_status}">${esc(o.payment_status)}</span></td><td><span class="badge b-${o.status}">${esc(o.status)}</span></td></tr>`).join('')||'<tr><td colspan=6 class="muted">No orders yet</td></tr>'}</table></div>`;
  $('#fs').onchange=e=>{window._os=e.target.value;orders()};document.querySelectorAll('tr.click').forEach(r=>r.onclick=()=>go('/admin/orders/'+r.dataset.id))})}
async function orderDetail(id){run(async()=>{const o=(await api('/admin/orders/'+id)).data;
  $('#main').innerHTML=`<p><button class="alt" id="bk">← Back</button></p><div class="card"><h2>${esc(o.reference)} <span class="badge b-${o.status}">${esc(o.status)}</span></h2>
  <p>${esc(o.customer_name)} · ${esc(o.customer_phone)} ${o.customer_email?'· '+esc(o.customer_email):''}<br>${esc(o.address_line)}, ${esc(o.city)}, ${esc(o.province)}<br>${o.notes?'Note: '+esc(o.notes):''}</p>
  <table><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>${o.items.map(i=>`<tr><td>${esc(i.product_name)} ${esc(i.variant_name||'')}</td><td>${i.quantity}</td><td>${pkr(i.unit_price)}</td><td>${pkr(i.line_total)}</td></tr>`).join('')}</table>
  <p class="right">Subtotal ${pkr(o.subtotal)} · Shipping ${pkr(o.shipping_fee)} · Discount ${pkr(o.discount_total)}<br><b>Total ${pkr(o.grand_total)}</b> · ${esc(o.payment_method)} · <span class="badge b-${o.payment_status}">${esc(o.payment_status)}</span></p></div>
  <div class="card"><h3>Change status</h3><div class="row"><select id="ns">${ST.map(s=>`<option ${s===o.status?'selected':''}>${s}</option>`).join('')}</select><input id="nn" placeholder="Note (optional)"><button class="fit" id="su">Update</button></div></div>
  <div class="card"><h3>Shipment</h3><div class="row"><input id="co" placeholder="Courier" value="${esc(o.shipment?.courier||'')}"><input id="tn" placeholder="Tracking number" value="${esc(o.shipment?.tracking_number||'')}"><button class="fit" id="ss">Save</button></div></div>
  <div class="card"><h3>Payment</h3><div class="row"><input id="pr" placeholder="Bank/transaction reference (if bank transfer)"><button class="fit" id="pv">Mark paid (verify)</button><button class="fit del" id="pf">Refund</button></div></div>
  <div class="card"><h3>History</h3>${(o.history||[]).map(h=>`<div class="muted">${esc(h.at?.slice(0,16).replace('T',' '))} · ${esc(h.from||'-')} → ${esc(h.to)} ${esc(h.note||'')}</div>`).join('')}</div>`;
  $('#bk').onclick=()=>go('/admin/orders');
  $('#su').onclick=()=>run(async()=>{await api(`/admin/orders/${id}/status`,'PATCH',{status:$('#ns').value,note:$('#nn').value||null});toast('Status updated');orderDetail(id)});
  $('#ss').onclick=()=>run(async()=>{await api(`/admin/orders/${id}/shipment`,'PUT',{courier:$('#co').value||null,tracking_number:$('#tn').value||null});toast('Shipment saved');orderDetail(id)});
  $('#pv').onclick=()=>run(async()=>{await api(`/admin/orders/${id}/payment/verify`,'POST',{reference:$('#pr').value||null});toast('Payment verified');orderDetail(id)});
  $('#pf').onclick=()=>confirm('Refund this payment?')&&run(async()=>{await api(`/admin/orders/${id}/payment/refund`,'POST',{});toast('Refunded');orderDetail(id)})})}
async function products(){return run(async()=>{const q=window._pq||'';const j=await api('/admin/products?per_page=50'+(q?'&search='+encodeURIComponent(q):''));
  $('#main').innerHTML=`<div class="card"><div class="row"><h2 style="flex:3">Products</h2><input id="sq" placeholder="Search name / SKU" value="${esc(q)}"><button class="fit" id="np">+ Add product</button></div>
  <table><tr><th></th><th>Name</th><th>SKU</th><th>Price</th><th>Availability</th><th>Live</th></tr>${j.data.map(p=>`<tr class="click" data-id="${p.id}"><td><img class="thumb" src="${esc(p.images?.[0]?.url||'')}" onerror="this.style.visibility='hidden'"></td><td>${esc(p.name)}</td><td>${esc(p.sku)}</td><td>${pkr(p.effective_price)}</td><td>${esc(p.availability)}</td><td><span class="badge ${p.is_published?'b-published':''}">${p.is_published?'published':'draft'}</span></td></tr>`).join('')||'<tr><td colspan=6 class="muted">No products</td></tr>'}</table></div>
  <div class="card"><h3>Import products from WooCommerce CSV</h3><p class="muted">Export Products from WordPress → WooCommerce → Products → Export, then upload the CSV here. Existing products are updated by SKU (or slug); new products are created. Simple products only. Published status, prices, categories, stock and product images are imported.</p><div class="row"><input type="file" id="productCsv" accept=".csv,text/csv"><button class="fit" id="importCsv">Import CSV</button></div><div id="importResult" class="muted" aria-live="polite"></div></div>`;
  $('#sq').onkeydown=e=>{if(e.key==='Enter'){window._pq=e.target.value;products()}};$('#np').onclick=()=>go('/admin/products/new');
  $('#importCsv').onclick=()=>run(async()=>{const file=$('#productCsv').files[0];if(!file)throw new Error('Choose the WooCommerce CSV file first.');const fd=new FormData();fd.append('csv',file);$('#importCsv').disabled=true;$('#importCsv').textContent='Importing…';try{const r=await api('/admin/products/import-csv','POST',fd);const d=r.data||{};$('#importResult').innerHTML=`<b>Import finished:</b> ${d.created||0} created · ${d.updated||0} updated · ${d.skipped||0} skipped · ${d.failed||0} failed${(d.errors||[]).length?'<ul>'+d.errors.map(x=>`<li>Row ${x.row}: ${esc(x.name||'')} — ${esc(x.message)}</li>`).join('')+'</ul>':''}`;toast('CSV import finished');const summary=$('#importResult').innerHTML;await products();$('#importResult').innerHTML=summary}finally{$('#importCsv').disabled=false;$('#importCsv').textContent='Import CSV'}});
  document.querySelectorAll('tr.click').forEach(r=>r.onclick=()=>go('/admin/products/'+r.dataset.id))})}
async function productForm(id){run(async()=>{
  const c=await api('/admin/categories');cats=c.data;const p=id?(await api('/admin/products/'+id)).data:{};
  const catOpts=(l,pre='')=>l.map(x=>`<option value="${x.id}" ${p.category?.id===x.id?'selected':''}>${pre}${esc(x.name)}</option>${catOpts(x.children||[],pre+'— ')}`).join('');
  $('#main').innerHTML=`<p><button class="alt" id="bk">← Back</button></p><div class="card"><h2>${id?'Edit':'Add'} product</h2>
  <div class="row"><div><label>Name *</label><input id="f_name" value="${esc(p.name)}"></div><div><label>SKU * (letters, numbers, - _ .)</label><input id="f_sku" value="${esc(p.sku)}"></div></div>
  <div class="row"><div><label>Price (PKR) *</label><input id="f_price" type="number" step="0.01" value="${p.price??''}"></div><div><label>Sale price (optional, lower than price)</label><input id="f_sale" type="number" step="0.01" value="${p.sale_price??''}"></div>
  <div><label>Category</label><select id="f_cat"><option value="">None</option>${catOpts(cats)}</select></div><div><label>Availability</label><select id="f_av">${AV.map(a=>`<option ${a===(p.availability||'available')?'selected':''}>${a}</option>`).join('')}</select></div></div>
  <label>Short description</label><input id="f_sd" value="${esc(p.short_description)}"><label>Description</label><textarea id="f_d" rows="4">${esc(p.description)}</textarea>
  <div class="row" style="margin-top:10px"><label class="fit"><input type="checkbox" id="f_feat" style="width:auto" ${p.is_featured?'checked':''}> Featured</label><label class="fit"><input type="checkbox" id="f_home" style="width:auto" ${p.show_on_home?'checked':''}> Show on home</label>
  <label class="fit"><input type="checkbox" id="f_inv" style="width:auto" ${p.track_inventory?'checked':''}> Track stock</label><input id="f_stock" type="number" placeholder="Stock qty" value="${p.stock_quantity??''}" class="fit" style="width:120px"></div>
  <p><button id="sv">Save product</button> ${id?`<button class="alt" id="pb">${p.is_published?'Unpublish (hide)':'Publish (make live)'}</button> <button class="del" id="dl">Delete</button>`:''}</p></div>
  ${id?`<div class="card"><h3>Images (JPG/PNG/WEBP, max 3MB, min 200×200)</h3><div class="imgs">${(p.images||[]).map(i=>`<div><img src="${esc(i.url)}"><span>${i.is_primary?'main':''}</span><br><a style="cursor:pointer;color:var(--bad)" data-di="${i.id}">remove</a></div>`).join('')}</div><p><input type="file" id="fi" accept="image/*"> <button id="ui">Upload</button></p></div>`:'<p class="muted">Save the product first, then you can upload images.</p>'}`;
  $('#bk').onclick=()=>go('/admin/products');
  $('#sv').onclick=()=>run(async()=>{const b={name:$('#f_name').value,sku:$('#f_sku').value,price:$('#f_price').value,sale_price:$('#f_sale').value||null,category_id:$('#f_cat').value||null,
    availability:$('#f_av').value,short_description:$('#f_sd').value||null,description:$('#f_d').value||null,is_featured:$('#f_feat').checked,show_on_home:$('#f_home').checked,track_inventory:$('#f_inv').checked,stock_quantity:$('#f_stock').value===''?null:+$('#f_stock').value};
    const r=await api(id?'/admin/products/'+id:'/admin/products',id?'PATCH':'POST',b);toast('Saved');go('/admin/products/'+r.data.id)});
  if(id){
    $('#pb').onclick=()=>run(async()=>{await api(`/admin/products/${id}/${p.is_published?'unpublish':'publish'}`,'POST',{});toast('Done');productForm(id)});
    $('#dl').onclick=()=>confirm('Delete this product?')&&run(async()=>{await api('/admin/products/'+id,'DELETE');toast('Deleted');go('/admin/products')});
    $('#ui').onclick=()=>run(async()=>{const f=$('#fi').files[0];if(!f)throw new Error('Choose an image first');const fd=new FormData();fd.append('image',f);fd.append('is_primary',(p.images||[]).length?'0':'1');await api(`/admin/products/${id}/images`,'POST',fd);toast('Uploaded');productForm(id)});
    document.querySelectorAll('[data-di]').forEach(a=>a.onclick=()=>run(async()=>{await api(`/admin/products/${id}/images/${a.dataset.di}`,'DELETE');productForm(id)}));
  }})}
async function categories(){run(async()=>{const j=await api('/admin/categories');
  const rows=(l,pre='')=>l.map(c=>`<tr><td>${pre}${esc(c.name)}</td><td>${c.products_count??0}</td><td>${c.is_active?'active':'hidden'}</td><td class="right"><a style="cursor:pointer;color:var(--bad)" data-dc="${c.id}">delete</a></td></tr>${rows(c.children||[],pre+'— ')}`).join('');
  $('#main').innerHTML=`<div class="card"><h2>Categories</h2><div class="row"><input id="cn" placeholder="New category name"><button class="fit" id="ac">Add</button></div><table><tr><th>Name</th><th>Products</th><th>Status</th><th></th></tr>${rows(j.data)}</table></div>`;
  $('#ac').onclick=()=>run(async()=>{await api('/admin/categories','POST',{name:$('#cn').value});toast('Added');categories()});
  document.querySelectorAll('[data-dc]').forEach(a=>a.onclick=()=>confirm('Delete category?')&&run(async()=>{await api('/admin/categories/'+a.dataset.dc,'DELETE');categories()}))})}
/* ---------- generic CRUD (coupons, banners, FAQs) ---------- */
const dt=v=>v?String(v).slice(0,16).replace(' ','T'):'';
function crud(c){run(async()=>{const j=await api(c.path);const rows=j.data||j;
  $('#main').innerHTML=`<div class="card"><div class="row"><h2 style="flex:3">${c.title}</h2><button class="fit" id="add">+ Add</button></div><table><tr>${c.cols.map(x=>`<th>${x[0]}</th>`).join('')}</tr>${rows.map(r=>`<tr class="click" data-id="${r.id}">${c.cols.map(x=>`<td>${x[1](r)}</td>`).join('')}</tr>`).join('')||'<tr><td class="muted">Nothing yet</td></tr>'}</table></div>`;
  $('#add').onclick=()=>go('/admin/'+c.slug+'/new');document.querySelectorAll('tr.click').forEach(t=>t.onclick=()=>go('/admin/'+c.slug+'/'+t.dataset.id))})}
function crudPage(c){if(ID==='new')return crudForm(c,{});if(ID)return run(async()=>{const j=await api(c.path);crudForm(c,(j.data||j).find(x=>x.id==ID)||{})});crud(c)}
function crudForm(c,r){const id=r.id;
  const inp=f=>{const v=r[f.k]??f.def??'';
    if(f.t==='select')return `<select id="x_${f.k}">${f.o.map(o=>`<option ${o===v?'selected':''}>${o}</option>`).join('')}</select>`;
    if(f.t==='area')return `<textarea id="x_${f.k}" rows="4">${esc(v)}</textarea>`;
    if(f.t==='check')return `<input type="checkbox" id="x_${f.k}" style="width:auto" ${(id?r[f.k]:f.def??true)?'checked':''}>`;
    if(f.t==='file')return `<input type="file" id="x_${f.k}" accept="image/*">${r.image_url?`<div class="imgs"><img src="${esc(r.image_url)}"></div>`:''}`;
    if(f.t==='datetime-local')return `<input type="datetime-local" id="x_${f.k}" value="${dt(v)}">`;
    return `<input id="x_${f.k}" type="${f.t||'text'}" step="any" value="${esc(v)}">`};
  $('#main').innerHTML=`<p><button class="alt" id="bk">← Back</button></p><div class="card"><h2>${id?'Edit':'Add'} ${c.one}</h2><div class="row">${c.fields.map(f=>`<div style="${f.t==='area'?'flex:1 1 100%':''}"><label>${f.l}</label>${inp(f)}</div>`).join('')}</div>
  <p><button id="sv">Save</button> ${id?'<button class="del" id="dl">Delete</button>':''}</p></div>`;
  $('#bk').onclick=()=>c.back();
  $('#sv').onclick=()=>run(async()=>{const multi=c.fields.some(f=>f.t==='file');const b=multi?new FormData():{};
    c.fields.forEach(f=>{const e=$('#x_'+f.k);let v=f.t==='check'?e.checked:f.t==='file'?e.files[0]:e.value;
      if(f.t==='file'){if(v)b.append(f.k,v);return}
      if(v===''&&f.t!=='select')v=null;if(f.up&&v)v=v.toUpperCase();
      if(multi){if(v!==null)b.append(f.k,v===true?'1':v===false?'0':v)}else b[f.k]=v});
    await api(id?`${c.path}/${id}`:c.path,(id&&!multi)?'PATCH':'POST',b);toast('Saved');c.back()});
  if(id)$('#dl').onclick=()=>confirm('Delete?')&&run(async()=>{await api(`${c.path}/${id}`,'DELETE');toast('Deleted');c.back()})}
const yn=v=>v?'<span class="badge b-published">yes</span>':'<span class="badge">no</span>';
function coupons(){crudPage({slug:'coupons',title:'Coupons',one:'coupon',path:'/admin/coupons',back:()=>go('/admin/coupons'),
  cols:[['Code',r=>esc(r.code)],['Type',r=>esc(r.type)],['Value',r=>r.type==='percent'?r.value+'%':pkr(r.value)],['Used',r=>(r.used_count||0)+(r.usage_limit?' / '+r.usage_limit:'')],['Expires',r=>esc(String(r.expires_at||'-').slice(0,10))],['Active',r=>yn(r.is_active)]],
  fields:[{k:'code',l:'Code (A-Z, 0-9, - _)',up:1},{k:'type',l:'Type',t:'select',o:['percent','fixed']},{k:'value',l:'Value (% or PKR)',t:'number'},{k:'min_subtotal',l:'Min order subtotal',t:'number',def:0},{k:'max_discount',l:'Max discount (optional)',t:'number'},{k:'usage_limit',l:'Total uses limit',t:'number'},{k:'per_customer_limit',l:'Per-customer limit',t:'number'},{k:'starts_at',l:'Starts',t:'datetime-local'},{k:'expires_at',l:'Expires',t:'datetime-local'},{k:'description',l:'Description'},{k:'is_active',l:'Active',t:'check'}]})}
function banners(){crudPage({slug:'banners',title:'Banners',one:'banner',path:'/admin/banners',back:()=>go('/admin/banners'),
  cols:[['',r=>`<img class="thumb" src="${esc(r.image_url||'')}" onerror="this.style.visibility='hidden'">`],['Title',r=>esc(r.title)],['Placement',r=>esc(r.placement)],['Order',r=>r.sort_order],['Active',r=>yn(r.is_active)]],
  fields:[{k:'title',l:'Title'},{k:'subtitle',l:'Subtitle'},{k:'image',l:'Image (JPG/PNG/WEBP, max 4MB)',t:'file'},{k:'link_url',l:'Link (e.g. /shop or https://...)'},{k:'button_text',l:'Button text'},{k:'placement',l:'Placement',t:'select',o:['home_hero','home_promo','announcement']},{k:'sort_order',l:'Sort order',t:'number',def:0},{k:'starts_at',l:'Starts',t:'datetime-local'},{k:'ends_at',l:'Ends',t:'datetime-local'},{k:'is_active',l:'Active',t:'check'}]})}
function faqs(){crudPage({slug:'faqs',title:'FAQs',one:'FAQ',path:'/admin/faqs',back:()=>go('/admin/faqs'),
  cols:[['Question',r=>esc(r.question)],['Order',r=>r.sort_order],['Active',r=>yn(r.is_active)]],
  fields:[{k:'question',l:'Question'},{k:'answer',l:'Answer',t:'area'},{k:'sort_order',l:'Sort order',t:'number',def:0},{k:'is_active',l:'Active',t:'check'}]})}
/* ---------- reviews ---------- */
function reviews(){run(async()=>{const j=await api('/admin/reviews');
  $('#main').innerHTML=`<div class="card"><h2>Reviews</h2><table><tr><th>Product</th><th>By</th><th>Rating</th><th>Review</th><th>Status</th><th></th></tr>${j.data.map(r=>`<tr><td>${esc(r.product?.name)}</td><td>${esc(r.user?.name)}</td><td>${'★'.repeat(r.rating)}</td><td>${esc(r.title||'')} ${esc(r.body||'')}</td><td>${r.is_approved?'<span class="badge b-published">approved</span>':'<span class="badge b-pending">pending</span>'}</td><td>${r.is_approved?'':`<button data-ap="${r.id}">Approve</button> `}<button class="del" data-rm="${r.id}">Delete</button></td></tr>`).join('')||'<tr><td colspan=6 class="muted">No reviews</td></tr>'}</table></div>`;
  document.querySelectorAll('[data-ap]').forEach(b=>b.onclick=()=>run(async()=>{await api(`/admin/reviews/${b.dataset.ap}/approve`,'POST',{});reviews()}));
  document.querySelectorAll('[data-rm]').forEach(b=>b.onclick=()=>confirm('Delete review?')&&run(async()=>{await api('/admin/reviews/'+b.dataset.rm,'DELETE');reviews()}))})}
/* ---------- customers ---------- */
function customers(){run(async()=>{const q=window._cq||'';const j=await api('/admin/customers?per_page=50'+(q?'&search='+encodeURIComponent(q):''));
  $('#main').innerHTML=`<div class="card"><div class="row"><h2 style="flex:3">Customers</h2><input id="cq" placeholder="Search name / email" value="${esc(q)}"></div><table><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Active</th></tr>${j.data.map(u=>`<tr class="click" data-id="${u.id}"><td>${esc(u.name)}</td><td>${esc(u.email)}</td><td>${esc(u.phone||'')}</td><td>${u.orders_count??0}</td><td>${yn(u.is_active)}</td></tr>`).join('')||'<tr><td colspan=5 class="muted">No customers</td></tr>'}</table></div>`;
  $('#cq').onkeydown=e=>{if(e.key==='Enter'){window._cq=e.target.value;customers()}};
  document.querySelectorAll('tr.click').forEach(t=>t.onclick=()=>run(async()=>{const d=(await api('/admin/customers/'+t.dataset.id)).data;
    $('#main').innerHTML=`<p><button class="alt" id="bk">← Back</button></p><div class="card"><h2>${esc(d.customer.name)}</h2><p>${esc(d.customer.email)} · ${esc(d.customer.phone||'')}</p><table><tr><th>Ref</th><th>Total</th><th>Status</th></tr>${d.orders.map(o=>`<tr><td>${esc(o.reference)}</td><td>${pkr(o.grand_total)}</td><td><span class="badge b-${o.status}">${esc(o.status)}</span></td></tr>`).join('')||'<tr><td class="muted">No orders</td></tr>'}</table></div>`;$('#bk').onclick=customers}))})}
/* ---------- staff (needs your own password for safety) ---------- */
function staff(){run(async()=>{const j=await api('/admin/users');
  $('#main').innerHTML=`<div class="card"><h2>Staff &amp; admins</h2><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr>${j.data.map(u=>`<tr><td>${esc(u.name)}</td><td>${esc(u.email)}</td><td>${esc(u.role)}</td><td>${yn(u.is_active)}</td><td><button class="alt" data-r="${u.id}" data-role="${u.role==='admin'?'staff':'admin'}">Make ${u.role==='admin'?'staff':'admin'}</button> <button class="alt" data-a="${u.id}" data-on="${u.is_active?0:1}">${u.is_active?'Deactivate':'Activate'}</button></td></tr>`).join('')}</table></div>
  <div class="card"><h3>Add staff member</h3><div class="row"><input id="un" placeholder="Name"><input id="ue" placeholder="Email"><input id="up" type="password" placeholder="Password (10+ chars, Aa1)"><select id="ur"><option>staff</option><option>admin</option></select></div><p class="muted">Every change here asks for YOUR admin password.</p><button id="ua">Add</button></div>`;
  const cp=()=>prompt('Enter YOUR admin password to confirm:');
  $('#ua').onclick=()=>{const c=cp();if(c)run(async()=>{await api('/admin/users','POST',{name:$('#un').value,email:$('#ue').value,password:$('#up').value,role:$('#ur').value,current_password:c});toast('Added');staff()})};
  document.querySelectorAll('[data-r]').forEach(b=>b.onclick=()=>{const c=cp();if(c)run(async()=>{await api(`/admin/users/${b.dataset.r}/role`,'PATCH',{role:b.dataset.role,current_password:c});toast('Role changed');staff()})});
  document.querySelectorAll('[data-a]').forEach(b=>b.onclick=()=>{const c=cp();if(c)run(async()=>{await api(`/admin/users/${b.dataset.a}/active`,'PATCH',{is_active:b.dataset.on==='1',current_password:c});toast('Updated');staff()})})})}
/* ---------- settings ---------- */
function settings(){run(async()=>{const d=(await api('/admin/settings')).data;
  $('#main').innerHTML=`<div class="card"><h2>Store settings</h2><div class="row">${Object.entries(d).map(([k,v])=>`<div><label>${k.replace(/_/g,' ')}</label><input data-k="${k}" value="${esc(v)}"></div>`).join('')}</div><p><button id="ss">Save settings</button></p></div>`;
  $('#ss').onclick=()=>run(async()=>{const b={};document.querySelectorAll('[data-k]').forEach(i=>b[i.dataset.k]=i.value);await api('/admin/settings','PATCH',{settings:b});toast('Settings saved')})})}
/* ---------- audit log ---------- */
function audit(){run(async()=>{const j=await api('/admin/audit-logs');
  $('#main').innerHTML=`<div class="card"><h2>Audit log</h2><table><tr><th>When</th><th>Action</th><th>User</th><th>Target</th></tr>${j.data.map(a=>`<tr><td>${esc(String(a.created_at||'').slice(0,16).replace('T',' '))}</td><td>${esc(a.action)}</td><td>${esc(a.user_id??'')}</td><td>${esc((a.auditable_type||'').split('\\').pop())} ${esc(a.auditable_id??'')}</td></tr>`).join('')||'<tr><td class="muted">Empty</td></tr>'}</table></div>`})}
render();
