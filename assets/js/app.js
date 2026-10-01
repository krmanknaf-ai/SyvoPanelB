(function(window,document){'use strict';
const C=window.SyvoBDConfig||{};
const q=(s,c=document)=>c.querySelector(s), qa=(s,c=document)=>Array.from(c.querySelectorAll(s));
const esc=t=>String(t??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
const icon=n=>({arrow:'<svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>',check:'<svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>',search:'<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',pin:'<svg viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-12A7 7 0 0 0 5 9c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg>'}[n]||'');
const api=(path,opts={})=>fetch((C.rest||'')+path,Object.assign({credentials:'same-origin',headers:Object.assign({'Accept':'application/json','Content-Type':'application/json','X-WP-Nonce':C.nonce||''},opts.headers||{})},opts)).then(async r=>{const j=await r.json().catch(()=>({}));if(!r.ok)throw new Error(j.message||'خطا در ارتباط با سرور');return j;});
function msg(el,text,ok=false){if(!el)return;el.textContent=text;el.className=text?(ok?'syvo-bd-success syvo-bd-message':'syvo-bd-notice syvo-bd-message'):'syvo-bd-message';}
function setOptions(sel,rows,placeholder,disabled=false){if(!sel)return;sel.innerHTML='<option value="">'+placeholder+'</option>';rows.forEach(r=>{const o=document.createElement('option');o.value=r.id;o.textContent=r.name_fa;sel.appendChild(o)});sel.disabled=disabled;}
async function provinces(sel){try{const rows=await api('locations/provinces');setOptions(sel,rows,'انتخاب استان',false);return rows}catch(e){setOptions(sel,[],'استان‌ها بارگذاری نشدند',true);throw e}}
async function counties(sel,provinceId){setOptions(sel,[],'در حال بارگذاری...',true);if(!provinceId){setOptions(sel,[],'ابتدا استان را انتخاب کنید',true);return[]}try{const rows=await api('locations/counties?province_id='+encodeURIComponent(provinceId));setOptions(sel,rows,'انتخاب شهرستان',false);return rows}catch(e){setOptions(sel,[],'خطا در بارگذاری شهرستان‌ها',true);throw e}}
async function cities(sel,countyId){setOptions(sel,[],'در حال بارگذاری...',true);if(!countyId){setOptions(sel,[],'ابتدا شهرستان را انتخاب کنید',true);return[]}try{const rows=await api('locations/cities?county_id='+encodeURIComponent(countyId));setOptions(sel,rows,'انتخاب شهر',false);return rows}catch(e){setOptions(sel,[],'خطا در بارگذاری شهرها',true);throw e}}
async function initLocationChain(root,mode){
 const p=q(mode==='register'?'[data-province]':'[data-'+mode+'-province]',root);
 const c=q(mode==='register'?'[data-county]':'[data-'+mode+'-county]',root);
 const city=q(mode==='register'?'[data-city]':'[data-'+mode+'-city]',root);
 if(!p||!c||!city)return;
 try{
  await provinces(p);
  p.addEventListener('change',async()=>{setOptions(city,[],'ابتدا شهرستان را انتخاب کنید',true);await counties(c,p.value)});
  c.addEventListener('change',async()=>{await cities(city,c.value)});
 }catch(e){console.error(e)}
}
function initAccount(){
 const f=q('[data-register-account]');if(!f)return;
 f.addEventListener('submit',async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(f).entries());const box=q('[data-account-message]',f);
  if(String(d.password||'').length<10)return msg(box,'رمز عبور باید حداقل ۱۰ کاراکتر باشد.');
  if(d.password!==d.password_confirm)return msg(box,'تکرار رمز عبور با رمز عبور یکسان نیست.');
  try{await api('register',{method:'POST',body:JSON.stringify(d)});msg(box,'حساب با موفقیت ساخته شد؛ در حال ورود به فرم ثبت کسب‌وکار…',true);setTimeout(()=>location.reload(),650)}catch(e){msg(box,e.message)}
 });
}
function selectedCategoryCount(form){return qa('input[name="category_ids[]"]:checked',form).length}
function initCategoryTree(root){
 if(!root)return;
 api('categories').then(nodes=>{
  root.innerHTML='';
  const walk=(items,parent,depth=0)=>items.forEach(n=>{
   const card=document.createElement('div');card.className='syvo-bd-cat-row depth-'+depth;
   const label=document.createElement('label');label.className='syvo-bd-cat-label';
   const cb=document.createElement('input');cb.type='checkbox';cb.name='category_ids[]';cb.value=n.id;
   label.append(cb,document.createTextNode(n.name));card.appendChild(label);
   if(n.children&&n.children.length){const child=document.createElement('div');child.className='syvo-bd-cat-children';walk(n.children,child,depth+1);card.appendChild(child)}
   parent.appendChild(card);
  });
  walk(nodes,root);
 }).catch(()=>{root.innerHTML='<div class="syvo-bd-notice">دسته‌بندی‌ها بارگذاری نشدند.</div>'});
}
function initWizard(root){
 const form=q('[data-business-form]',root);if(!form)return;
 const steps=qa('[data-step]',form),total=steps.length,stepper=q('[data-stepper]',root),next=q('[data-next]',root),prev=q('[data-prev]',root),submit=q('[data-submit]',root),label=q('[data-step-label]',root),notice=q('[data-step-message]',root);
 let step=1;
 stepper.innerHTML='';for(let i=1;i<=total;i++){const s=document.createElement('span');s.dataset.step=i;s.setAttribute('aria-label','مرحله '+i);stepper.appendChild(s)}
 function validate(){
   if(step===1){if(!String(form.elements.name.value||'').trim())return msg(notice,'نام کسب‌وکار را وارد کنید.'),false;if(!String(form.elements.business_mobile.value||'').trim())return msg(notice,'شماره موبایل کسب‌وکار را وارد کنید.'),false}
   if(step===2&&!selectedCategoryCount(form))return msg(notice,'حداقل یک دسته‌بندی انتخاب کنید.'),false;
   if(step===3){if(!form.elements.province_id.value)return msg(notice,'استان را انتخاب کنید.'),false;if(!form.elements.county_id.value)return msg(notice,'شهرستان را انتخاب کنید.'),false;if(!form.elements.city_id.value)return msg(notice,'شهر را انتخاب کنید.'),false}
   if(step===4&&!String(form.elements.address.value||'').trim())return msg(notice,'آدرس کامل کسب‌وکار را وارد کنید.'),false;
   if(step===6&&!String(form.elements.description.value||'').trim())return msg(notice,'معرفی کسب‌وکار را وارد کنید.'),false;
   return true;
 }
 function review(){
  const out=q('[data-review]',root);if(!out)return;
  const fd=new FormData(form),rows=[];
  const map={name:'نام کسب‌وکار',brand:'برند',business_mobile:'موبایل',province_id:'استان',county_id:'شهرستان',city_id:'شهر',address:'آدرس',description:'معرفی'};
  Object.entries(map).forEach(([k,l])=>{const el=form.elements[k];if(el&&String(el.value||'').trim()){let v=el.value;if(el.tagName==='SELECT')v=el.options[el.selectedIndex]?.text||v;rows.push('<div><span>'+esc(l)+'</span><strong>'+esc(v)+'</strong></div>')}});
  rows.push('<div><span>دسته‌بندی</span><strong>'+esc(qa('input[name="category_ids[]"]:checked',form).map(i=>i.parentElement.textContent.trim()).join('، '))+'</strong></div>');
  out.innerHTML=rows.join('');
 }
 function render(){
  steps.forEach((el,i)=>el.classList.toggle('is-active',i===step-1));
  qa('[data-stepper] span',root).forEach((el,i)=>{el.classList.toggle('done',i<step);el.classList.toggle('current',i===step-1)});
  label.textContent='مرحله '+step+' از '+total;
  prev.hidden=step===1;next.hidden=step===total;submit.hidden=step!==total;
  if(step===total)review();
  root.scrollIntoView({behavior:'smooth',block:'start'});
 }
 next.addEventListener('click',()=>{msg(notice,'');if(validate()){step++;render()}});
 prev.addEventListener('click',()=>{if(step>1){msg(notice,'');step--;render()}});
 q('[data-draft]',root)?.addEventListener('click',()=>{localStorage.setItem('syvo_bd_draft',JSON.stringify(Object.fromEntries(new FormData(form).entries())));msg(notice,'پیش‌نویس این فرم روی این دستگاه ذخیره شد.',true)});
 initCategoryTree(q('[data-category-tree]',root));
 initLocationChain(root,'register');
 restoreDraft(form);
 q('[data-current-location]',root)?.addEventListener('click',()=>navigator.geolocation?.getCurrentPosition(p=>{const el=q('[data-form-map]',root);if(el&&el.__map){el.__map.setCenter(p.coords.latitude,p.coords.longitude)}},()=>msg(notice,'اجازه موقعیت مکانی داده نشد.')));
 attachUploads(root);
 initMap(q('[data-form-map]',root),form);
 render();
 form.addEventListener('submit',async e=>{e.preventDefault();if(!validate())return;const data=Object.fromEntries(new FormData(form).entries());data.category_ids=qa('input[name="category_ids[]"]:checked',form).map(x=>Number(x.value));data.services=(data.services_text||'').split(/[,،\n]+/).map(x=>x.trim()).filter(Boolean);submit.disabled=true;try{const res=await api('businesses',{method:'POST',body:JSON.stringify(data)});localStorage.removeItem('syvo_bd_draft');msg(notice,'کسب‌وکار با موفقیت ثبت شد. صفحه در حال انتقال به پروفایل شماست.',true);setTimeout(()=>{location.href=res.url||C.home||'/dashboard/'},900)}catch(e){msg(notice,e.message)}finally{submit.disabled=false}});
}
function restoreDraft(form){try{const d=JSON.parse(localStorage.getItem('syvo_bd_draft')||'{}');Object.entries(d).forEach(([k,v])=>{const el=form.elements[k];if(el&&typeof v==='string'&&!el.value)el.value=v})}catch(e){}}
function attachUploads(root){
 const form=q('[data-business-form]',root);qa('input[type=file]',root).forEach(input=>input.addEventListener('change',async()=>{for(const file of input.files){const fd=new FormData();fd.append('file',file);try{const res=await fetch((C.rest||'')+'upload',{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':C.nonce||''},body:fd});const j=await res.json();if(!res.ok)throw new Error(j.message||'آپلود ناموفق');const hidden=document.createElement('input');hidden.type='hidden';hidden.name=input.hasAttribute('data-upload-logo')?'logo_id':(input.hasAttribute('data-upload-featured')?'featured_id':'gallery_ids[]');hidden.value=j.id;form.appendChild(hidden);const img=document.createElement('img');img.src=j.url||'';q('[data-media-preview]',root)?.appendChild(img)}catch(e){msg(q('[data-step-message]',root),e.message)}}}));
}
function initSearchPage(root){
 if(!root)return;
 initLocationChain(root,'search');
 const p=q('[data-search-province]',root),c=q('[data-search-county]',root),city=q('[data-search-city]',root),term=q('[data-search-q]',root),results=q('[data-search-results]',root),count=q('[data-results-count]',root);
 async function run(){
  results.innerHTML='<div class="syvo-bd-loading">در حال جستجو…</div>';
  try{
   const sp=new URLSearchParams();if(term.value)sp.set('q',term.value);if(p.value)sp.set('province_id',p.value);if(c.value)sp.set('county_id',c.value);if(city.value)sp.set('city_id',city.value);
   const preset=new URLSearchParams(location.search).get('category');if(preset)sp.set('category_slug',preset);sp.set('per_page','30');
   const rows=await api('search?'+sp.toString());count.textContent=rows.length+' نتیجه';
   results.innerHTML='';if(!rows.length){results.innerHTML='<div class="syvo-bd-empty-state"><div class="syvo-bd-empty-icon">'+icon('search')+'</div><h3>نتیجه‌ای پیدا نشد</h3><p>فیلترها یا عبارت جستجو را تغییر دهید.</p></div>';return}
   rows.forEach(item=>{const a=document.createElement('article');a.className='syvo-bd-business-card';a.innerHTML='<div class="syvo-bd-business-main"><div class="syvo-bd-card-top"><span class="syvo-bd-status">کسب‌وکار</span><span class="syvo-bd-score">'+esc(item.completeness||0)+'% تکمیل</span></div><h3><a href="'+esc(item.url||'#')+'">'+esc(item.name||'')+'</a></h3><p>'+esc((item.description||'').slice(0,170))+'</p><div class="syvo-bd-tags">'+(item.services||[]).slice(0,4).map(s=>'<span>'+esc(s)+'</span>').join('')+'</div></div><div class="syvo-bd-business-side"><span>'+esc(item.city?.name_fa||'')+'</span><a class="syvo-bd-btn syvo-bd-btn-secondary syvo-bd-icon-btn" href="'+esc(item.url||'#')+'">'+icon('arrow')+'</a></div>';results.appendChild(a)});
  }catch(e){results.innerHTML='<div class="syvo-bd-notice">'+esc(e.message)+'</div>'}
 }
 q('[data-search-submit]',root)?.addEventListener('click',run);term?.addEventListener('keydown',e=>{if(e.key==='Enter')run()});
 q('[data-use-location]',root)?.addEventListener('click',()=>navigator.geolocation?.getCurrentPosition(async pos=>{try{const rows=await api('search?lat='+encodeURIComponent(pos.coords.latitude)+'&lng='+encodeURIComponent(pos.coords.longitude)+'&per_page=30');count.textContent=rows.length+' نتیجه';results.innerHTML='';rows.forEach(x=>{const el=document.createElement('div');el.className='syvo-bd-notice';el.textContent=x.name;results.appendChild(el)})}catch(e){msg(results,e.message)}},()=>msg(results,'اجازه موقعیت مکانی داده نشد.')));
}
function initHome(root){
 const p=q('[data-home-province]',root),c=q('[data-home-county]',root),city=q('[data-home-city]',root),term=q('[data-home-q]',root);
 if(!p)return;
 provinces(p).catch(()=>{});
 p.addEventListener('change',()=>{setOptions(city,[],'همه شهرها',true);counties(c,p.value)});
 c.addEventListener('change',()=>cities(city,c.value));
 q('[data-home-search]',root)?.addEventListener('click',()=>{const u=new URL(C.home+(C.home.endsWith('/')?'':'/')+'directory-search/');if(term.value)u.searchParams.set('q',term.value);if(p.value)u.searchParams.set('province_id',p.value);if(c.value)u.searchParams.set('county_id',c.value);if(city.value)u.searchParams.set('city_id',city.value);location.href=u.toString()});
}
function initMap(el,form){if(!el||!C.tile_url)return;const lat=Number(el.dataset.lat)||35.6892,lng=Number(el.dataset.lng)||51.389;el.__map=new MapCanvas(el,lat,lng,form)}
class MapCanvas{
 constructor(el,lat,lng,form){this.el=el;this.lat=lat;this.lng=lng;this.zoom=12;this.form=form;this.canvas=document.createElement('canvas');this.ctx=this.canvas.getContext('2d');this.tiles={};this.marker=document.createElement('div');this.marker.className='syvo-bd-map-marker';this.el.append(this.canvas,this.marker);this.resize();window.addEventListener('resize',()=>this.resize());this.bind();this.render()}
 resize(){const d=devicePixelRatio||1;this.canvas.width=this.el.clientWidth*d;this.canvas.height=this.el.clientHeight*d;this.canvas.style.width='100%';this.canvas.style.height='100%';this.ctx.setTransform(d,0,0,d,0,0);this.render()}
 project(lat,lng){const n=2**this.zoom,x=(lng+180)/360*n*256,y=(1-Math.asinh(Math.tan(lat*Math.PI/180))/Math.PI)/2*n*256;return{x,y}}
 unproject(x,y){const n=2**this.zoom,lng=x/(n*256)*360-180,yy=2*(1-y/(n*256)),lat=180/Math.PI*(2*Math.atan(Math.exp(yy*Math.PI))-Math.PI/2);return{lat,lng}}
 setCenter(lat,lng){this.lat=lat;this.lng=lng;this.render();if(this.form){this.form.elements.lat.value=lat;this.form.elements.lng.value=lng}}
 render(){const w=this.el.clientWidth,h=this.el.clientHeight;if(!w||!h)return;const c=this.project(this.lat,this.lng),left=c.x-w/2,top=c.y-h/2;this.ctx.clearRect(0,0,w,h);for(let x=Math.floor(left/256);x<=Math.ceil((left+w)/256);x++)for(let y=Math.floor(top/256);y<=Math.ceil((top+h)/256);y++){const wrap=2**this.zoom,tx=((x%wrap)+wrap)%wrap,ty=y;if(ty<0||ty>=wrap)continue;const key=this.zoom+'/'+tx+'/'+ty;if(!this.tiles[key]){const im=new Image();im.onload=()=>{this.tiles[key]=im;this.render()};im.src=C.tile_url.replace('{z}',this.zoom).replace('{x}',tx).replace('{y}',ty);this.tiles[key]=true}if(this.tiles[key] instanceof Image)this.ctx.drawImage(this.tiles[key],x*256-left,y*256-top,256,256)}const p=this.project(this.lat,this.lng);this.marker.style.left=(p.x-left)+'px';this.marker.style.top=(p.y-top)+'px'}
 bind(){let drag=false,last=null;this.el.addEventListener('pointerdown',e=>{drag=true;last={x:e.clientX,y:e.clientY};this.el.setPointerCapture(e.pointerId)});this.el.addEventListener('pointermove',e=>{if(!drag)return;const dx=e.clientX-last.x,dy=e.clientY-last.y;last={x:e.clientX,y:e.clientY};const c=this.project(this.lat,this.lng);const q=this.unproject(c.x-dx,c.y-dy);this.setCenter(q.lat,q.lng)});const end=()=>drag=false;this.el.addEventListener('pointerup',end);this.el.addEventListener('pointercancel',end);this.el.addEventListener('wheel',e=>{e.preventDefault();this.zoom=Math.max(4,Math.min(17,this.zoom+(e.deltaY<0?1:-1)));this.render()},{passive:false})}
}
document.addEventListener('DOMContentLoaded',()=>{
 document.body.classList.add('syvo-bd-body');
 initAccount();
 qa('[data-syvo-bd-wizard]').forEach(initWizard);
 qa('[data-syvo-bd-search]').forEach(initSearchPage);
 qa('[data-syvo-bd-directory-home]').forEach(initHome);
 qa('[data-form-map]').forEach(el=>initMap(el,el.closest('form')));
 qa('[data-map]').forEach(el=>initMap(el,null));
});
})(window,document);