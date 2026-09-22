// AGENZA interactions
const $ = (s) => document.querySelector(s);
const LOC = document.documentElement.getAttribute('lang') === 'en' ? 'en' : 'ar';

// Language: toggle + auto open by device language (loop-safe on hosting)
(function(){
  const path = location.pathname;
  const isEn = /-en\.html$/.test(path);
  let counterpart = null;
  if (isEn) counterpart = path.replace(/-en\.html$/, '.html');
  else if (/\.html$/i.test(path)) counterpart = path.replace(/\.html$/i, '-en.html');
  else counterpart = path.replace(/\/?$/, '/index-en.html'); // hosting root "/" case
  const same = counterpart === path;
  const saved = (()=>{ try{ return localStorage.getItem('agenza-lang'); }catch(e){ return null; } })();
  const navLang = ((navigator.language || navigator.userLanguage || 'ar') + '').toLowerCase();
  const shouldBeEn = saved ? saved === 'en' : !navLang.startsWith('ar');
  // NEVER redirect to the same URL — that was causing infinite reload on hosting
  // + redirect only once per session as extra loop insurance
  let redirected = false;
  try{ redirected = sessionStorage.getItem('agenza-redirected') === '1'; }catch(e){}
  if(!same && !redirected && shouldBeEn !== isEn){
    try{ sessionStorage.setItem('agenza-redirected', '1'); }catch(e){}
    try{ location.replace(counterpart); }catch(e){}
    return;
  }
  const target = same ? (isEn ? 'index.html' : 'index-en.html') : counterpart;
  document.querySelectorAll('.langToggle').forEach(btn=>btn.addEventListener('click',()=>{
    try{ localStorage.setItem('agenza-lang', isEn ? 'ar' : 'en'); }catch(e){}
    location.href = target;
  }));
})();

// Infinite marquee — guarantee it never ends on any screen width
(function(){
  const m = document.getElementById('marquee');
  if(!m) return;
  const original = m.innerHTML;
  let t = null;
  const ensure = () => {
    // rebuild from original (idempotent) until track is at least 3x viewport
    m.innerHTML = original;
    let guard = 0;
    while(m.scrollWidth < innerWidth * 3 && guard < 4){
      m.innerHTML += original;
      guard++;
    }
  };
  ensure();
  addEventListener('resize', () => { clearTimeout(t); t = setTimeout(ensure, 250); });
})();

// Preloader
window.addEventListener('load', () => {
  setTimeout(() => $('#preloader').classList.add('hide'), 900);
});
setTimeout(() => $('#preloader')?.classList.add('hide'), 3500);

// Nav + mobile
const burger = $('#burger'), mobileMenu = $('#mobileMenu');
burger?.addEventListener('click', () => mobileMenu.classList.toggle('open'));
mobileMenu?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => mobileMenu.classList.remove('open')));

// Particles canvas (cyan circuit dots like logo)
const canvas = $('#particles'), ctx = canvas.getContext('2d');
let W, H, pts = [];
function resize() { W = canvas.width = innerWidth; H = canvas.height = innerHeight; }
resize(); addEventListener('resize', resize);
for (let i = 0; i < 70; i++) pts.push({ x: Math.random()*innerWidth, y: Math.random()*innerHeight, r: Math.random()*2+.5, vx:(Math.random()-.5)*.35, vy:(Math.random()-.5)*.35 });
(function loop(){
  ctx.clearRect(0,0,W,H);
  pts.forEach(p=>{
    p.x+=p.vx; p.y+=p.vy;
    if(p.x<0||p.x>W)p.vx*=-1; if(p.y<0||p.y>H)p.vy*=-1;
    ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,7);
    ctx.fillStyle='rgba(56,225,255,.5)'; ctx.fill();
  });
  // connect near lines
  for(let i=0;i<pts.length;i++)for(let j=i+1;j<pts.length;j++){
    const dx=pts[i].x-pts[j].x, dy=pts[i].y-pts[j].y, d=Math.hypot(dx,dy);
    if(d<130){ ctx.beginPath(); ctx.moveTo(pts[i].x,pts[i].y); ctx.lineTo(pts[j].x,pts[j].y); ctx.strokeStyle=`rgba(56,225,255,${(1-d/130)*.14})`; ctx.stroke(); }
  }
  requestAnimationFrame(loop);
})();

// Counters
const counters = document.querySelectorAll('[data-count]');
const cObs = new IntersectionObserver(es=>es.forEach(e=>{
  if(!e.isIntersecting) return;
  const el=e.target, end=+el.dataset.count; let cur=0;
  const t=setInterval(()=>{ cur+=Math.max(1,Math.round(end/40)); if(cur>=end){cur=end;clearInterval(t);} el.textContent=cur; },50);
  cObs.unobserve(el);
}),{threshold:.5});
counters.forEach(c=>cObs.observe(c));

// Reveal on scroll
const rObs = new IntersectionObserver(es=>es.forEach(e=>{ if(e.isIntersecting){ e.target.classList.add('visible'); rObs.unobserve(e.target);} }),{threshold:.12});
document.querySelectorAll('.reveal').forEach(el=>rObs.observe(el));

// Hero chat loop
const heroChat = $('#heroChat');
const heroScript = LOC === 'en' ? [
  { from:'user', text:'Hi! Do you have the white sneakers in size 42? 👟' },
  { from:'ai', text:'Hello and welcome 😊 Yes, size 42 is available — last 2 pieces! Want me to reserve one for you?' },
  { from:'user', text:'Great.. how much with shipping to Mansoura?' },
  { from:'ai', text:'It\'s 1450 + 55 shipping to Mansoura, delivered in 2 days 🚚 Under which name should I confirm the order?' },
  { from:'user', text:'Mahmoud — 01001234567 — El Gomhoureya St.' },
  { from:'sys', text:'✔ Order #4821 confirmed + logged to Google Sheets' },
] : [
  { from:'user', text:'مساء الخير.. عندكم الكوتشي الأبيض مقاس 42؟ 👟' },
  { from:'ai', text:'مساء النور يا فندم 😊 أيوه متوفر مقاس 42 — آخر قطعتين! تحب أحجزهولك؟' },
  { from:'user', text:'تمام.. بكام بالشحن للمنصورة؟' },
  { from:'ai', text:'سعره 1450 + شحن 55 للمنصورة، وبيوصلك خلال يومين 🚚 أأكد الأوردر باسم مين؟' },
  { from:'user', text:'محمود — 01001234567 — شارع الجمهورية' },
  { from:'sys', text:'✔ تم تأكيد الأوردر #4821 + تسجيله في Google Sheets' },
];
let hi = 0;
function pushHero(){
  if(!heroChat) return;
  heroChat.innerHTML='';
  hi=0;
  const tick=()=>{
    if(hi>=heroScript.length){ setTimeout(pushHero, 5000); return; }
    const m=heroScript[hi];
    if(m.from==='ai'){
      const tp=document.createElement('div'); tp.className='msg ai typing'; tp.innerHTML='<i></i><i></i><i></i>';
      heroChat.appendChild(tp);
      setTimeout(()=>{ tp.remove(); addMsg(m); hi++; setTimeout(tick,900); }, 900);
    } else { addMsg(m); hi++; setTimeout(tick,1100); }
  };
  const addMsg=(m)=>{
    const d=document.createElement('div'); d.className='msg '+m.from; d.textContent=m.text;
    heroChat.appendChild(d);
  };
  tick();
}
pushHero();

// Demo tabs
const demos = LOC === 'en' ? {
  reply: {
    title:'Smart Reply Agent',
    desc:'Replies to comments and messages like a real human admin.',
    log:['<div><b>→</b> Watching a new TikTok comment</div>','<div><b>→</b> Public reply + moving customer to DM</div>','<div><b>→</b> Sentiment: excited 🟢 — Tone: friendly</div>'],
    script:[
      {from:'user',text:'💬 TikTok comment: “How much?? Anybody replies here or what 😅”'},
      {from:'ai',text:'Hey hey 😄 Yes we reply faster than delivery! It\'s 1450 with free shipping today.. DM us and I\'ll sort your size 😉'},
      {from:'user',text:'Sent you a DM 📩 do you have black 43?'},
      {from:'ai',text:'Got your message champ 👌 Black 43 is in stock.. want me to place the order? Cash on delivery to your doorstep.'},
      {from:'sys',text:'✔ Comment → chat → purchase intent (3:10 min)'},
    ]
  },
  order: {
    title:'Order Confirmation Agent',
    desc:'Confirms every order via WhatsApp / email and cuts returns.',
    log:['<div><b>→</b> New store order #4822</div>','<div><b>→</b> Auto WhatsApp confirmation sent</div>','<div><b>→</b> Status: <b>confirmed ✔</b> — took 1:24</div>'],
    script:[
      {from:'ai',text:'Hello Sara 🌸 This is the confirmation team.. you ordered the beige set size M, correct?'},
      {from:'user',text:'Yes correct, but the address changed.. Nasr City instead of Maadi'},
      {from:'ai',text:'Perfect, saved Nasr City ✅ Cash on delivery 890.. shall we lock the order?'},
      {from:'user',text:'Lock it 👍'},
      {from:'sys',text:'✔ #4822 confirmed — sent to warehouse + new sheet row'},
    ]
  },
  sheet: {
    title:'Data Entry Agent',
    desc:'Every chat word becomes an organized row in your sheet.',
    log:['<div><b>→</b> Extracting: name / phone / address / product</div>','<div><b>→</b> Phone cleaned: 0100 ✓ — address complete ✓</div>','<div><b>→</b> Writing to Google Sheets now…</div>'],
    script:[
      {from:'user',text:'I\'m Karim 01112223334 — I want 2 grey hoodies — Faisal, El Maleka St. bldg 7'},
      {from:'ai',text:'Got it Karim 👌 Saved: 2 × grey hoodie — Faisal.. number 01112223334 correct?'},
      {from:'user',text:'Correct ✅'},
      {from:'sys',text:'✔ Google Sheets: row #1289 — Karim | 0111… | 2 hoodies | Faisal ✓'},
    ]
  }
} : {
  reply: {
    title:'وكيل الرد الذكي',
    desc:'بيرد على الكومنتات والرسائل كأنه أدمن حقيقي.',
    log:['<div><b>→</b> راقب كومنت جديد على TikTok</div>','<div><b>→</b> رد علني + نقل العميل ل DM</div>','<div><b>→</b> المشاعر: متحمس 🟢 — اللهجة: مصري</div>'],
    script:[
      {from:'user',text:'💬 كومنت على TikTok: “السعر كام؟! حد بيرد هنا ولا إيه 😅”'},
      {from:'ai',text:'أهلاً أهلاً 😄 أيوه بنرد أسرع من الدليفري! سعره 1450 والشحن مجاني النهاردة.. ابعتلنا رسالة وأظبطلك مقاسك 😉'},
      {from:'user',text:'بعتلكم 📩 فيه مقاس 43 اسود؟'},
      {from:'ai',text:'وصلتني رسالتك يا بطل 👌 الأسود 43 متوفر.. تحب أعملك أوردر بيه؟ هيوصلك لباب البيت والدفع عند الاستلام.'},
      {from:'sys',text:'✔ كومنت → محادثة → نية شراء (3:10 دقيقة)'},
    ]
  },
  order: {
    title:'وكيل تأكيد الأوردرات',
    desc:'بيأكد كل أوردر واتساب / إيميل ويقلل المرتجعات.',
    log:['<div><b>→</b> أوردر جديد من المتجر #4822</div>','<div><b>→</b> إرسال واتساب تأكيد تلقائي</div>','<div><b>→</b> الحالة: <b>مؤكد ✔</b> — المدة 1:24</div>'],
    script:[
      {from:'ai',text:'السلام عليكم أستاذة سارة 🌸 معاكي فريق التأكيد.. حضرتك طلبتي الطقم البيج مقاس M، مظبوط؟'},
      {from:'user',text:'أيوه مظبوط بس العنوان اتغير.. مدينة نصر بدل المعادي'},
      {from:'ai',text:'تمام جداً سجّلت مدينة نصر ✅ والدفع عند الاستلام 890 جنيه.. نثبّت الأوردر؟'},
      {from:'user',text:'ثبّته 👍'},
      {from:'sys',text:'✔ مؤكد #4822 — اتبعت للمخزن + صف جديد في الشيت'},
    ]
  },
  sheet: {
    title:'وكيل إدخال البيانات',
    desc:'كل كلمة في الشات بتتحوّل لصف منظم في شيتك.',
    log:['<div><b>→</b> استخراج: اسم / فون / عنوان / منتج</div>','<div><b>→</b> تنظيف الرقم: 0100 ✓ — العنوان مكتمل ✓</div>','<div><b>→</b> كتابة في Google Sheets الآن…</div>'],
    script:[
      {from:'user',text:'أنا كريم 01112223334 — عايز 2 هودي رمادي — فيصل شارع الملكة عمارة 7'},
      {from:'ai',text:'تمام يا كريم 👌 سجّلت: 2 × هودي رمادي — فيصل.. الرقم 01112223334 مظبوط؟'},
      {from:'user',text:'مظبوط ✅'},
      {from:'sys',text:'✔ Google Sheets: صف #1289 — كريم | 0111… | 2 هودي | فيصل ✓'},
    ]
  }
};
const demoChat=$('#demoChat'), demoLog=$('#demoLog'), demoTitle=$('#demoTitle'), demoDesc=$('#demoDesc');
let demoTimer=null;
function playDemo(key){
  const d=demos[key]; if(!d) return;
  demoTitle.textContent=d.title; demoDesc.textContent=d.desc;
  demoChat.innerHTML=''; demoLog.innerHTML=d.log.join('');
  clearTimeout(demoTimer);
  let i=0;
  const step=()=>{
    if(i>=d.script.length) return;
    const m=d.script[i];
    if(m.from==='ai'){
      const tp=document.createElement('div'); tp.className='msg ai typing'; tp.innerHTML='<i></i><i></i><i></i>';
      demoChat.appendChild(tp); demoChat.scrollTop=9999;
      demoTimer=setTimeout(()=>{ tp.remove(); addM(m); i++; demoTimer=setTimeout(step,900); },1000);
    } else { addM(m); i++; demoTimer=setTimeout(step,1100); }
  };
  const addM=(m)=>{ const el=document.createElement('div'); el.className='msg '+m.from; el.textContent=m.text; demoChat.appendChild(el); demoChat.scrollTop=9999; };
  step();
}
document.querySelectorAll('.demo-tabs button').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('.demo-tabs button').forEach(x=>x.classList.remove('active'));
  b.classList.add('active'); playDemo(b.dataset.tab);
}));
$('#replayDemo')?.addEventListener('click',()=>playDemo(document.querySelector('.demo-tabs button.active')?.dataset.tab||'reply'));
playDemo('reply');

// Tilt on phone
const tilt=document.querySelector('.tilt');
if(tilt && matchMedia('(pointer:fine)').matches){
  tilt.addEventListener('mousemove',e=>{
    const r=tilt.getBoundingClientRect();
    const x=(e.clientX-r.left)/r.width-.5, y=(e.clientY-r.top)/r.height-.5;
    tilt.style.transform=`perspective(900px) rotateY(${x*10}deg) rotateX(${-y*10}deg)`;
  });
  tilt.addEventListener('mouseleave',()=>tilt.style.transform='');
}

// Lead form
$('#leadForm')?.addEventListener('submit',e=>{
  e.preventDefault();
  const note=$('#formNote');
  note.textContent = LOC === 'en'
    ? '✔ Got your details! Our agent will contact you on WhatsApp during working hours.. get your products ready 😉'
    : '✔ وصلتنا بياناتك! الوكيل بتاعنا هيكلمك على واتساب خلال ساعات العمل.. جهّز منتجاتك 😉';
  note.style.color='#22ff88';
  e.target.querySelector('button').textContent = LOC === 'en' ? 'Received ✔' : 'تم الاستلام ✔';
});
