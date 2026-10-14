/* Run On Console — ROC, the site assistant: the chat box on every page (loaded by roc-nav.js).
   Talks to /api/v1/agent.php. Typing, speaking (microphone, English or Urdu) and pictures (a screenshot
   of the PC specs, a game, a part); answers can be read aloud. The chat of this visit is kept in
   sessionStorage, so it stays while the visitor moves between pages; pictures are never kept. */
(function () {
  if (window.__rocChat || /^\/cms\//.test(location.pathname)) return;
  window.__rocChat = 1;
  var KEY = 'roc-chat-v1', ss = null;
  try { ss = window.sessionStorage; } catch (e) {}
  var state = { open: false, msgs: [], session: '', voice: 'en', speak: false };
  try { var saved = ss && JSON.parse(ss.getItem(KEY) || 'null'); if (saved) for (var k in saved) state[k] = saved[k]; } catch (e) {}
  if (!state.session) state.session = Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-6);
  function save() {
    try { ss && ss.setItem(KEY, JSON.stringify({ open: state.open, session: state.session, voice: state.voice, speak: state.speak,
      msgs: state.msgs.slice(-20).map(function (m) { var c = {}; for (var k in m) if (k !== 'pic') c[k] = m[k]; return c; }) })); } catch (e) {}
  }
  var Rec = window.SpeechRecognition || window.webkitSpeechRecognition;

  var css = '' +
    '#roc-chat-btn{position:fixed;right:18px;bottom:18px;z-index:2147483000;display:flex;align-items:center;gap:8px;border:0;cursor:pointer;' +
    'background:linear-gradient(135deg,#047857,#059669);color:#fff;font:800 14px Outfit,Inter,system-ui,sans-serif;padding:12px 18px 12px 14px;border-radius:999px;' +
    'box-shadow:0 12px 28px -8px rgba(4,120,87,.7)}#roc-chat-btn:hover{filter:brightness(1.08)}#roc-chat-btn svg{width:22px;height:22px}' +
    '#roc-chat{position:fixed;right:18px;bottom:18px;z-index:2147483001;width:380px;max-width:calc(100vw - 24px);height:580px;max-height:calc(100vh - 36px);' +
    'display:none;flex-direction:column;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 24px 60px -12px rgba(15,23,42,.45);' +
    'font:14px/1.5 Inter,system-ui,sans-serif;color:#0f172a;border:1px solid #d1fae5}' +
    '#roc-chat.on{display:flex}#roc-chat .rh{display:flex;align-items:center;gap:10px;padding:12px 14px;background:linear-gradient(135deg,#064e3b,#047857);color:#fff}' +
    '#roc-chat .rh b{font:900 16px Outfit,Inter,sans-serif;display:block}#roc-chat .rh small{color:#a7f3d0;font-size:11px}' +
    '#roc-chat .rh .av{width:34px;height:34px;border-radius:12px;background:#34d399;color:#052e2b;display:flex;align-items:center;justify-content:center;font:900 13px Outfit,sans-serif;flex:none}' +
    '#roc-chat .rh .tools{margin-left:auto;display:flex;gap:6px}' +
    '#roc-chat .rh .tools button{background:rgba(255,255,255,.15);border:0;color:#fff;min-width:30px;height:30px;border-radius:10px;cursor:pointer;font:800 12px Inter,sans-serif;padding:0 7px}' +
    '#roc-chat .rh .tools button.on{background:#34d399;color:#052e2b}' +
    '#roc-chat .rb{flex:1;overflow-y:auto;padding:14px;background:#f8fafc;display:flex;flex-direction:column;gap:10px}' +
    '#roc-chat .m{max-width:88%;padding:9px 12px;border-radius:14px;white-space:pre-wrap;word-wrap:break-word}' +
    '#roc-chat .m.u{align-self:flex-end;background:#047857;color:#fff;border-bottom-right-radius:4px}' +
    '#roc-chat .m.a{align-self:flex-start;background:#fff;border:1px solid #e2e8f0;border-bottom-left-radius:4px}' +
    '#roc-chat .m img{display:block;max-width:100%;max-height:160px;border-radius:10px;margin-bottom:6px}' +
    '#roc-chat .m a{color:#047857;font-weight:700}#roc-chat .m.u a{color:#fff}' +
    '#roc-chat .gc{align-self:stretch;background:#fff;border:1px solid #a7f3d0;border-radius:14px;padding:10px 12px}' +
    '#roc-chat .gc .bar{height:8px;background:#e2e8f0;border-radius:99px;overflow:hidden;margin:6px 0}#roc-chat .gc .bar i{display:block;height:100%;border-radius:99px}' +
    '#roc-chat .gc a{font-weight:800;color:#047857;text-decoration:none;font-size:12px}' +
    '#roc-chat .pc{align-self:stretch;display:flex;gap:10px;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:8px;text-decoration:none;color:inherit}' +
    '#roc-chat .pc:hover{border-color:#34d399}#roc-chat .pc img{width:58px;height:58px;object-fit:contain;border-radius:10px;background:#fff;flex:none}' +
    '#roc-chat .pc b{display:block;font-size:13px;line-height:1.3}#roc-chat .pc span{display:block;font-size:11.5px;color:#64748b;line-height:1.35}' +
    '#roc-chat .chips{display:flex;flex-wrap:wrap;gap:6px}#roc-chat .chips button{border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46;font:700 12px Inter,sans-serif;padding:6px 10px;border-radius:99px;cursor:pointer}' +
    '#roc-chat .att{display:none;align-items:center;gap:8px;padding:8px 10px 0;background:#fff}#roc-chat .att.on{display:flex}' +
    '#roc-chat .att img{width:44px;height:44px;object-fit:cover;border-radius:8px}#roc-chat .att button{border:0;background:#fee2e2;color:#b91c1c;border-radius:8px;padding:4px 8px;cursor:pointer;font-weight:700}' +
    '#roc-chat form{display:flex;gap:6px;padding:10px;border-top:1px solid #e2e8f0;background:#fff;align-items:center}' +
    '#roc-chat input[type=text]{flex:1;min-width:0;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;font:14px Inter,sans-serif;outline:none}#roc-chat input[type=text]:focus{border-color:#059669}' +
    '#roc-chat form .ic{flex:none;width:38px;height:40px;border:1px solid #cbd5e1;background:#f8fafc;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#047857}' +
    '#roc-chat form .ic svg{width:18px;height:18px}#roc-chat form .ic.rec{background:#fee2e2;border-color:#fca5a5;color:#b91c1c;animation:rocp 1s infinite}' +
    '#roc-chat form .go{flex:none;border:0;background:#047857;color:#fff;border-radius:12px;padding:0 14px;height:40px;font-weight:800;cursor:pointer}#roc-chat form .go:disabled{opacity:.5}' +
    '#roc-chat .ft{font-size:10.5px;color:#94a3b8;text-align:center;padding:0 10px 8px;background:#fff}#roc-chat .ft a{color:#64748b}' +
    '#roc-chat .dots span{display:inline-block;width:6px;height:6px;margin:0 2px;border-radius:50%;background:#94a3b8;animation:rocd 1s infinite}' +
    '#roc-chat .dots span:nth-child(2){animation-delay:.15s}#roc-chat .dots span:nth-child(3){animation-delay:.3s}@keyframes rocd{50%{opacity:.2}}@keyframes rocp{50%{opacity:.6}}' +
    '@media(max-width:480px){#roc-chat{right:0;bottom:0;width:100vw;max-width:100vw;height:100%;max-height:100%;border-radius:0}#roc-chat-btn{right:12px;bottom:12px}}';

  var SVG = function (p) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; };
  var ICON = SVG('<rect x="3" y="7" width="18" height="13" rx="4"/><path d="M12 3v4M8 13h.01M16 13h.01M9 17h6"/>');
  var MIC = SVG('<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5"/>');
  var CAM = SVG('<rect x="3" y="5" width="18" height="15" rx="3"/><circle cx="12" cy="12.5" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/>');
  var CHIPS = ['Hi ROC!', 'Can my PC run GTA 5?', 'Mera PC Valorant chala lega?', 'Suggest a light gaming mouse', 'Who won the last Valorant Champions?', 'History of PlayStation'];

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  /* **bold**, [text](/link) and plain /paths from ROC's answer; everything else stays text. */
  function fmt(s) {
    return esc(s).replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
      .replace(/\[([^\]]+)\]\((\/[^)\s]*|https:\/\/[^)\s]+)\)/g, function (m, t, u) { return '<a href="' + u + '"' + (/^https:/.test(u) && u.indexOf('runonconsole.com') < 0 ? ' target="_blank" rel="noopener nofollow"' : '') + '>' + t + '</a>'; })
      .replace(/(^|\s)(\/(?:compatibility|products|blogs|news|category|contact|gaming-platforms)\/[a-z0-9\/?=&%-]*)/g, '$1<a href="$2">$2</a>');
  }

  var btn, box, body, form, input, sendBtn, micBtn, att, pic = null, busy = false, rec = null;
  function build() {
    var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
    // The old assistant button of the built pages: ROC replaces it.
    var old = document.querySelector('button[title="ROC AI Hardware Assistant"]');
    if (old && old.parentElement) old.parentElement.style.display = 'none';
    btn = document.createElement('button');
    btn.id = 'roc-chat-btn'; btn.type = 'button'; btn.setAttribute('aria-label', 'Ask ROC, the Run On Console assistant');
    btn.innerHTML = ICON + '<span>Ask ROC</span>';
    box = document.createElement('div');
    box.id = 'roc-chat'; box.setAttribute('role', 'dialog'); box.setAttribute('aria-label', 'ROC assistant');
    box.innerHTML = '<div class="rh"><div class="av">ROC</div><div><b>ROC</b><small>Your gaming assistant · English / Roman Urdu</small></div>' +
      '<div class="tools">' + (Rec ? '<button type="button" class="lang" title="Microphone language">' + (state.voice === 'ur' ? 'اردو' : 'EN') + '</button>' : '') +
      ('speechSynthesis' in window ? '<button type="button" class="spk' + (state.speak ? ' on' : '') + '" title="Read answers aloud" aria-label="Read answers aloud">🔊</button>' : '') +
      '<button type="button" class="rx" aria-label="Close" style="font-size:18px">&times;</button></div></div>' +
      '<div class="rb" aria-live="polite"></div>' +
      '<div class="att"><img alt=""><span style="font-size:12px;color:#475569;flex:1">Picture added</span><button type="button">Remove</button></div>' +
      '<form><button type="button" class="ic cam" title="Send a picture" aria-label="Send a picture">' + CAM + '</button>' +
      '<input type="file" accept="image/*" hidden>' +
      (Rec ? '<button type="button" class="ic mic" title="Speak" aria-label="Speak your question">' + MIC + '</button>' : '') +
      '<input type="text" maxlength="500" placeholder="Ask anything about gaming..." aria-label="Your question"><button type="submit" class="go">Send</button></form>' +
      '<div class="ft">ROC answers gaming questions only and can make mistakes. Chats are saved without personal details. <a href="/privacy-policy/">Privacy</a></div>';
    document.body.appendChild(btn); document.body.appendChild(box);
    body = box.querySelector('.rb'); form = box.querySelector('form'); input = form.querySelector('input[type=text]'); sendBtn = form.querySelector('.go');
    att = box.querySelector('.att'); micBtn = form.querySelector('.mic');
    btn.addEventListener('click', function () { toggle(true); });
    box.querySelector('.rx').addEventListener('click', function () { toggle(false); });
    form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open) toggle(false); });
    var file = form.querySelector('input[type=file]');
    form.querySelector('.cam').addEventListener('click', function () { file.click(); });
    file.addEventListener('change', function () { if (file.files && file.files[0]) addPicture(file.files[0]); file.value = ''; });
    att.querySelector('button').addEventListener('click', function () { pic = null; att.classList.remove('on'); });
    if (micBtn) micBtn.addEventListener('click', listen);
    var lang = box.querySelector('.lang');
    if (lang) lang.addEventListener('click', function () { state.voice = state.voice === 'ur' ? 'en' : 'ur'; lang.textContent = state.voice === 'ur' ? 'اردو' : 'EN'; save(); });
    var spk = box.querySelector('.spk');
    if (spk) spk.addEventListener('click', function () { state.speak = !state.speak; spk.classList.toggle('on', state.speak); if (!state.speak) speechSynthesis.cancel(); save(); });
    render();
    if (state.open) toggle(true);
  }
  function toggle(on) {
    state.open = on; save();
    box.classList.toggle('on', on); btn.style.display = on ? 'none' : 'flex';
    if (on) { body.scrollTop = body.scrollHeight; setTimeout(function () { input.focus(); }, 50); }
  }
  /* A picture: made smaller in the browser (max 1024 px, JPG) before it is sent. */
  function addPicture(f) {
    if (!/^image\//.test(f.type)) return;
    var img = new Image(), url = URL.createObjectURL(f);
    img.onload = function () {
      var s = Math.min(1, 1024 / Math.max(img.width, img.height)), c = document.createElement('canvas');
      c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      pic = c.toDataURL('image/jpeg', 0.82);
      att.querySelector('img').src = pic; att.classList.add('on'); input.focus();
    };
    img.src = url;
  }
  /* Speaking: the browser turns speech into text (English or Urdu), then it is sent. */
  function listen() {
    if (rec) { rec.stop(); return; }
    rec = new Rec();
    rec.lang = state.voice === 'ur' ? 'ur-PK' : 'en-US';
    rec.interimResults = true;
    micBtn.classList.add('rec');
    var said = '';
    rec.onresult = function (e) { said = ''; for (var i = 0; i < e.results.length; i++) said += e.results[i][0].transcript; input.value = said; };
    rec.onerror = function () {};
    rec.onend = function () { micBtn.classList.remove('rec'); rec = null; if (said.trim()) send(said); };
    try { rec.start(); } catch (e) { micBtn.classList.remove('rec'); rec = null; }
  }
  function speak(text) {
    if (!state.speak || !('speechSynthesis' in window)) return;
    try {
      speechSynthesis.cancel();
      var u = new SpeechSynthesisUtterance(String(text).replace(/\*\*|\[|\]\([^)]*\)/g, ''));
      u.lang = 'en-US'; u.rate = 1;
      speechSynthesis.speak(u);
    } catch (e) {}
  }
  function gameCard(g) {
    var p = +g.percent || 0, col = p >= 90 ? '#059669' : p >= 70 ? '#0d9488' : p > 40 ? '#d97706' : '#e11d48';
    return '<div class="gc"><b>' + esc(g.title) + '</b> · <span style="color:' + col + ';font-weight:800">' + p + '%</span>' +
      '<div class="bar"><i style="width:' + p + '%;background:' + col + '"></i></div>' +
      '<div style="font-size:12px;color:#475569">' + esc(g.verdict) + '</div><a href="' + esc(g.url) + '">Full check in the game checker &rarr;</a></div>';
  }
  function prodCard(p) {
    return '<a class="pc" href="' + esc(p.url) + '">' + (p.image ? '<img src="' + esc(p.image) + '" alt="" loading="lazy">' : '') +
      '<div><b>' + esc(p.title) + '</b>' + (p.about ? '<span>' + esc(p.about) + '</span>' : '') + '<span style="color:#047857;font-weight:800">See it &rarr;</span></div></a>';
  }
  function render() {
    var h = '';
    if (!state.msgs.length) {
      h += '<div class="m a">Salam! I am <b>ROC</b>, your gaming assistant. Ask me anything about games, gaming history, esports, consoles, PC parts, or if your PC can run a game. ' +
        'You can type, speak 🎤 or send a picture 📷. Aap Roman Urdu mein bhi pooch sakte hain.</div>' +
        '<div class="chips">' + CHIPS.map(function (c) { return '<button type="button">' + esc(c) + '</button>'; }).join('') + '</div>';
    }
    state.msgs.forEach(function (m) {
      h += '<div class="m ' + (m.role === 'user' ? 'u' : 'a') + '">' + (m.pic ? '<img src="' + m.pic + '" alt="">' : m.hadPic ? '📷 ' : '') +
        (m.role === 'user' ? esc(m.content) : fmt(m.content)) + '</div>';
      if (m.game) h += gameCard(m.game);
      (m.products || []).forEach(function (p) { h += prodCard(p); });
    });
    if (busy) h += '<div class="m a dots"><span></span><span></span><span></span></div>';
    body.innerHTML = h;
    [].forEach.call(body.querySelectorAll('.chips button'), function (b) { b.addEventListener('click', function () { send(b.textContent); }); });
    body.scrollTop = body.scrollHeight;
  }
  function send(text) {
    text = String(text || '').trim();
    if ((!text && !pic) || busy) return;
    input.value = '';
    var image = pic; pic = null; att.classList.remove('on');
    state.msgs.push(image ? { role: 'user', content: text, pic: image, hadPic: true } : { role: 'user', content: text });
    busy = true; sendBtn.disabled = true; render(); save();
    var hist = state.msgs.slice(-8).map(function (m) { return { role: m.role, content: m.content }; });
    fetch('/api/v1/agent.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit',
      body: JSON.stringify({ session: state.session, page: location.pathname, messages: hist, image: image || undefined }) })
      .then(function (r) { return r.json().catch(function () { return { error: 'ROC could not answer. Please try again.' }; }); })
      .then(function (d) {
        if (d && d.reply) { state.msgs.push({ role: 'assistant', content: d.reply, game: d.game || null, products: d.products || [] }); speak(d.reply); }
        else state.msgs.push({ role: 'assistant', content: (d && d.error) || 'ROC could not answer. Please try again.' });
      })
      .catch(function () { state.msgs.push({ role: 'assistant', content: 'No connection. Please try again.' }); })
      .then(function () { busy = false; sendBtn.disabled = false; render(); save(); input.focus(); });
  }

  function start() {
    // Switched off in the CMS: no button (asked once per visit).
    var off = ss && ss.getItem('roc-chat-off');
    if (off === '1') return;
    if (off === '0') { build(); return; }
    fetch('/api/v1/agent.php', { credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (d) {
      try { ss && ss.setItem('roc-chat-off', d && d.on === false ? '1' : '0'); } catch (e) {}
      if (!d || d.on !== false) build();
    }).catch(function () {});
  }
  if (document.readyState !== 'loading') start(); else document.addEventListener('DOMContentLoaded', start);
})();
