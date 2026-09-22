/* AGENZA on-site agent "Amer" — chat widget.
   AI brain lives on your Render server (URL below). The API key stays
   server-side only. If the server is unreachable, a polite local
   fallback answers so the widget never breaks. */
const AGENZA_AGENT = {
  // Production AI server (Render) — key stays server-side in env vars.
  ENDPOINT: "https://amer-api.onrender.com/amer/backend/api.php",
  WHATSAPP: "https://api.whatsapp.com/send?text=" + encodeURIComponent("أهلاً AGENZA 👋 عايز أستفسر عن وكلاء الذكاء الاصطناعي"),
  WHATSAPP_EN: "https://api.whatsapp.com/send?text=" + encodeURIComponent("Hi AGENZA 👋 I want to ask about your AI agents"),
};

(function(){
  const LOC = document.documentElement.getAttribute('lang') === 'en' ? 'en' : 'ar';

  const T = {
    ar: {
      name: 'عامر', sub: 'فريق مبيعات AGENZA • متصل الآن', ph: 'اكتب سؤالك…',
      hello: 'أهلاً بحضرتك في AGENZA، معاك عامر من فريق المبيعات 👋 اتفضل اسأل عن الوكلاء أو الأسعار أو نظام الشغل، وأنا تحت أمرك.',
      chips: ['💰 الأسعار', '🤖 الوكلاء', '🛠️ ابني وكيلك', '💬 واتساب'],
      typing: 'عامر بيكتب…', badge: 'تحب أساعدك؟ كلمني 🤝',
      fallback: null,
      fallbacks: [
        'تمام، عشان أفيد حضرتك بدقة في النقطة دي يفضّل نكمل كلامنا واتساب أو تحجز ديمو مجاني، وده هياخد دقيقة واحدة. وفي نفس الوقت ممكن تسألني عن: الأسعار، الوكلاء، تأكيد الأوردرات، أو الشيتات.',
        'النقطة دي محتاجة متابعة مخصصة عشان أجاوب حضرتك صح — تحب نكملها واتساب؟ أو احجز ديمو مجاني وهنشرحلك كل حاجة على منتجاتك.',
        'خليني أكون صريح مع حضرتك: الإجابة الدقيقة هنا عند فريقنا على الواتساب وهيردوا في ساعات العمل. تحب أساعدك في حاجة تانية عن الوكلاء أو الأسعار؟'
      ],
      waBtn: '💬 كمّل على واتساب', demoBtn: '🎯 احجز ديمو مجاني',
      err: 'النت قطع لحظة.. جرّب تبعت رسالتك تاني 🙏',
    },
    en: {
      name: 'Amer', sub: 'AGENZA sales team • online now', ph: 'Type your question…',
      hello: "Welcome to AGENZA, this is Amer from the sales team 👋 Feel free to ask about our agents, pricing, or how we work — happy to help.",
      chips: ['💰 Pricing', '🤖 Agents', '🛠️ Build my agent', '💬 WhatsApp'],
      typing: 'Amer is typing…', badge: 'Need a hand? Talk to me 🤝',
      fallback: null,
      fallbacks: [
        'Noted — to advise you precisely on this, best we continue on WhatsApp or you book a free demo, it takes a minute. Meanwhile you can ask about: pricing, agents, order confirmation, or sheets.',
        'This one needs dedicated follow-up to answer correctly — shall we continue on WhatsApp? Or book a free demo and we will walk you through everything.',
        'To be frank with you: the exact answer is with our team on WhatsApp, they reply during working hours. Anything else about agents or pricing I can help with?'
      ],
      waBtn: '💬 Continue on WhatsApp', demoBtn: '🎯 Book a free demo',
      err: 'Connection hiccup.. please resend your message 🙏',
    }
  }[LOC];

  // ---- local fallback brain (offline safety net only) ----
  const BRAIN = LOC === 'en' ? [
    { k:['price','pricing','cost','plan','package','much'], a:"We have 3 plans: <b>Launch</b> (1 agent + 2 channels), <b>Growth ⭐</b> (3 agents + all channels — best seller), and <b>Business</b> (custom). Every plan includes human-feel replies + training on your products. Want an exact quote? Tell me your business type 👇", c:['🛍️ Online store','🏥 Clinic','🏠 Real estate'] },
    { k:['agent','agents','bot','reply','replies'], a:"We build 3 agents: <b>💬 Reply</b> (messages + comments like a human), <b>📦 Orders</b> (WhatsApp/email confirmation), <b>📊 Sheets</b> (auto logging to Google Sheets & Excel). Most clients take all three together." },
    { k:['order','confirm','return','cart'], a:"The Orders agent messages every customer on WhatsApp/email right after ordering, verifies name/address/phone/payment, and follows up no-answers — that's how returns drop and confirmations jump <b>+40%</b>. 📦" },
    { k:['sheet','excel','data','log'], a:"Every chat becomes an organized row in your Google Sheets or Excel — name, phone, address, product — instantly, with numbers auto-cleaned. No copy-paste ever again. 📊" },
    { k:['comment','tiktok','instagram','facebook','social'], a:"Yes! The agent watches TikTok, Instagram & Facebook comments in real time, replies publicly like a human, and moves buyers to DM to close the order. 💬" },
    { k:['human','real person','robot'], a:"That's our whole point 😉 The agent talks in your dialect and style — most customers think it's a sharp new employee." },
    { k:['pay','deposit','upfront','50'], a:"Simple: <b>50% upfront</b> before work starts + 50% on delivery. Then a <b>monthly fee at month start</b> covering AI API + maintenance with support all month. Full details on the Policies page. 💳" },
    { k:['demo','trial','try','test'], a:"You get a <b>free 30-min live demo</b> on your own products + a one-week trial on your channels, no credit card. Hit the button 👇", btn:'demo' },
    { k:['whatsapp','contact','talk','call','human support'], a:"Easiest way — continue with us on WhatsApp and we'll reply during working hours 👇", btn:'wa' },
    { k:['who','about','company','agenza'], a:"<b>AGENZA</b> is an Egyptian software company specialized in AI agents that reply, confirm and log — like humans, but they never sleep. ⚡" },
    { k:['hi','hello','hey','salam','morning','evening'], a:"Hello and welcome 😊 Would you like to know about pricing, our agents, or how we work?" },
    { k:['thank','thanks','great','perfect','nice'], a:"You're welcome 🙏 Shall I book you a free demo?" },
    { k:['bye'], a:"Looking forward to hearing from you anytime 👋 We're here around the clock." },
  ] : [
    { k:['سعر','أسعار','باقة','باقات','تكلفة','بكام','فلوس'], a:'عندنا 3 باقات: <b>الانطلاقة</b> (وكيل + قناتين)، <b>النمو ⭐</b> (3 وكلاء + كل القنوات — الأكثر مبيعاً)، و<b>الشركات</b> (مخصص). وكلهم شاملين الرد البشري والتدريب على منتجاتك. عايز عرض دقيق؟ قولي نوع البيزنس 👇', c:['🛍️ متجر أونلاين','💄 عيادة','🏠 عقارات'] },
    { k:['وكيل','وكلاء','بوت','رد','روبوت ذكي'], a:'بنبني 3 وكلاء: <b>💬 الرد</b> (رسائل + كومنتات كبشري)، <b>📦 الأوردرات</b> (تأكيد واتساب/إيميل)، <b>📊 الشيتات</b> (تسجيل تلقائي في Sheets وإكسل). وأغلب العملاء بياخدوا التلاتة مع بعض.' },
    { k:['أوردر','اوردر','طلب','تأكيد','مرتجع','سلة'], a:'وكيل الأوردرات بيكلم كل عميل واتساب/إيميل أول ما يطلب، يتأكد من الاسم والعنوان والتليفون والدفع، ويتابع اللي مردّش — وعشان كده المرتجعات بتقل والتأكيد بيزيد <b>+40%</b>. 📦' },
    { k:['شيت','إكسل','اكسل','داتا','بيانات','تسجيل'], a:'كل محادثة بتتحوّل لصف منظم في Google Sheets أو إكسل — اسم وتليفون وعنوان ومنتج — لحظياً والأرقام بتتنضف لوحدها. من غير كوبي بيست خالص. 📊' },
    { k:['كومنت','تعليق','تيك توك','انستجرام','فيسبوك','سوشيال'], a:'أيوه! الوكيل بيراقب كومنتات تيك توك وإنستجرام وفيسبوك لحظة بلحظة، يرد علناً كبشري، ويحوّل اللي ناوي يشتري لخاص ويقفّل معاه الأوردر. 💬' },
    { k:['بشري','إنسان','حقيقي','موظف','بيعرف'], a:'وهو ده سر شغلنا 😉 الوكيل بيتكلم بلهجتك وأسلوبك — وأغلب العملاء فاكرينه موظف جديد شاطر.' },
    { k:['دفع','مقدم','50','نظام','فلوس التعامل'], a:'ببساطة: <b>50% مقدم</b> قبل البدء + 50% عند التسليم. وبعدين <b>مصروف شهري أول كل شهر</b> يشمل API الذكاء الاصطناعي + الصيانة والدعم طول الشهر. التفاصيل في صفحة السياسات. 💳' },
    { k:['ديمو','تجربة','تجربه','أجرب','معاينة'], a:'ليك <b>ديمو مجاني 30 دقيقة</b> على منتجاتك انت + أسبوع تجربة على قنواتك، من غير كارت بنكي. دوس 👇', btn:'demo' },
    { k:['واتساب','واتس','اتواصل','اكلم','أكلم','رقم','تواصل'], a:'أسهل حاجة — كمّل معانا واتساب وهنرد عليك في ساعات العمل 👇', btn:'wa' },
    { k:['مين','شركة','agenza','عنكم','بتعملوا'], a:'<b>AGENZA</b> شركة برمجيات مصرية متخصصة في وكلاء الذكاء الاصطناعي اللي بيردّوا ويأكّدوا وبيسجّلوا — كأنهم بشر، بس مابيناموش. ⚡' },
    { k:['سلام','صباح','مساء','اهلا','أهلا','ازيك','هاي'], a:'أهلاً بحضرتك 😊 اتفضل، تحب تستفسر عن الأسعار ولا الوكلاء ولا طريقة الشغل؟' },
    { k:['شكرا','متشكر','تمام','جميل','ممتاز','برافو'], a:'العفو، ده واجبي 🙏 تحب أحجز لحضرتك ديمو مجاني؟' },
    { k:['باي','سلام','مع السلامة'], a:'في انتظار حضرتك في أي وقت 👋 موجودين على مدار الساعة.' },
  ];

  function brain(msg){
    const m = ' ' + msg.toLowerCase() + ' ';
    let best = null, score = 0;
    for(const r of BRAIN){
      let s = 0;
      for(const k of r.k){ if(m.includes(k.toLowerCase())) s += k.length; }
      if(s > score){ score = s; best = r; }
    }
    return score > 0 ? best : null;
  }

  // ---- build widget DOM ----
  const wrap = document.createElement('div');
  wrap.id = 'agWidget';
  wrap.innerHTML =
    '<div class="ag-tip" id="agTip">' + T.badge + '</div>' +
    '<button class="ag-fab" id="agFab" aria-label="chat"><span class="ag-fab-dot"></span>💬</button>' +
    '<div class="ag-panel" id="agPanel">' +
      '<div class="ag-head"><img src="logo/agenza-logo.png" alt=""/>' +
        '<div><strong>' + T.name + '</strong><small><i></i>' + T.sub + '</small></div>' +
        '<button id="agClose">✕</button></div>' +
      '<div class="ag-body" id="agBody"></div>' +
      '<div class="ag-chips" id="agChips"></div>' +
      '<div class="ag-input"><input id="agText" placeholder="' + T.ph + '" maxlength="500"/>' +
      '<button id="agSend">➤</button></div>' +
    '</div>';
  document.body.appendChild(wrap);

  const fab = wrap.querySelector('#agFab'), panel = wrap.querySelector('#agPanel'),
        body = wrap.querySelector('#agBody'), chips = wrap.querySelector('#agChips'),
        text = wrap.querySelector('#agText'), tip = wrap.querySelector('#agTip');
  let opened = false, greeted = false, fbI = 0;
  const fb = () => T.fallbacks[(fbI++) % T.fallbacks.length];
  const history = [];

  function scrollDown(){ body.scrollTop = body.scrollHeight; }
  function bubble(html, who){
    const d = document.createElement('div');
    d.className = 'msg ' + who; d.innerHTML = html;
    body.appendChild(d); scrollDown(); return d;
  }
  function showChips(list){
    chips.innerHTML = '';
    (list || T.chips).forEach(c=>{
      const b = document.createElement('button');
      b.textContent = c;
      b.onclick = ()=>{ userSay(c.replace(/^[^\s]+\s/, '')); };
      chips.appendChild(b);
    });
  }
  function withButtons(html, rule, action){
    let h = html;
    const a = action || (rule && rule.btn);
    if(a === 'wa') h += '<br><a class="ag-btn" target="_blank" href="' + (LOC==='en'?AGENZA_AGENT.WHATSAPP_EN:AGENZA_AGENT.WHATSAPP) + '">' + T.waBtn + '</a>';
    if(a === 'demo') h += '<br><a class="ag-btn" href="' + (LOC==='en'?'index-en.html#contact':'index.html#contact') + '">' + T.demoBtn + '</a>';
    return h;
  }
  function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>'); }
  async function agentReply(msg){
    history.push({role:'user', content:msg});
    const tp = document.createElement('div');
    tp.className = 'msg ai typing'; tp.innerHTML = '<i></i><i></i><i></i>';
    body.appendChild(tp); scrollDown();
    const wait = new Promise(r=>setTimeout(r, 800 + Math.random()*600));
    const rule = brain(msg); // chips + offline fallback + backup buttons
    let action = null; // server-driven button (wa/demo) takes priority
    try{
      let html = null;
      const endpointReady = AGENZA_AGENT.ENDPOINT && AGENZA_AGENT.ENDPOINT.indexOf('RENDER-APP-URL') === -1;
      if(endpointReady){
        try{
          const ctl = new AbortController();
          const to = setTimeout(()=>ctl.abort(), 45000); // Render free cold-start can be slow
          const res = await fetch(AGENZA_AGENT.ENDPOINT, {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({message:msg, lang:LOC, history:history.slice(-10), page:(location.pathname.split('/').pop()||'index.html')}),
            signal: ctl.signal
          });
          clearTimeout(to);
          const data = await res.json().catch(()=>null);
          if(data && data.reply) {
            html = esc(data.reply);
            action = data.action || null;
          }
          else console.warn('[Amer] API error payload:', data);
        }catch(e){ console.warn('[Amer] API unreachable, brain fallback:', e && e.message); html = null; }
      }
      await wait; tp.remove();
      if(html){
        bubble(withButtons(html, rule, action), 'ai');
        showChips(rule && rule.c ? rule.c : null);
      } else {
        bubble(withButtons(rule ? rule.a : fb(), rule, null), 'ai');
        showChips(rule && rule.c ? rule.c : null);
      }
      history.push({role:'assistant', content: body.lastChild.textContent.slice(0,300)});
    }catch(e){ try{tp.remove();}catch(_){} bubble(T.err, 'ai'); }
  }
  function userSay(msg){
    msg = (msg||'').trim(); if(!msg) return;
    bubble(msg.replace(/</g,'&lt;'), 'user');
    text.value = ''; showChips(null);
    agentReply(msg);
  }

  function open(){
    opened = true; panel.classList.add('open'); tip.style.display = 'none';
    fab.innerHTML = '✕';
    if(!greeted){ greeted = true; setTimeout(()=>{ bubble(T.hello,'ai'); showChips(null); }, 350); }
    setTimeout(()=>text.focus(), 450);
  }
  function close(){ opened = false; panel.classList.remove('open'); fab.innerHTML = '<span class="ag-fab-dot"></span>💬'; }
  fab.onclick = ()=> opened ? close() : open();
  wrap.querySelector('#agClose').onclick = close;
  wrap.querySelector('#agSend').onclick = ()=>userSay(text.value);
  text.addEventListener('keydown', e=>{ if(e.key === 'Enter') userSay(text.value); });

  // gentle nudge
  setTimeout(()=>{ if(!opened) tip.classList.add('show'); }, 6000);
  setTimeout(()=>{ tip.classList.remove('show'); }, 16000);
})();
