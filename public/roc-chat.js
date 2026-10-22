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
    '#roc-chat.inline{position:relative;right:auto;bottom:auto;z-index:1;width:100%;max-width:100%;height:600px;max-height:80vh;box-shadow:0 10px 30px -14px rgba(4,120,87,.45);border:2px solid #a7f3d0}' +
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
    '#roc-chat .vt{align-self:flex-start;display:flex;gap:4px;margin-top:-6px}#roc-chat .vt button{border:1px solid #e2e8f0;background:#fff;border-radius:8px;padding:1px 6px;font-size:12px;cursor:pointer;opacity:.6}' +
    '#roc-chat .vt button:hover,#roc-chat .vt button.on{opacity:1;border-color:#34d399;background:#ecfdf5}' +
    '#roc-chat .ft{font-size:10.5px;color:#94a3b8;text-align:center;padding:0 10px 8px;background:#fff}#roc-chat .ft a{color:#64748b}' +
    '#roc-chat .dots span{display:inline-block;width:6px;height:6px;margin:0 2px;border-radius:50%;background:#94a3b8;animation:rocd 1s infinite}' +
    '#roc-chat .dots span:nth-child(2){animation-delay:.15s}#roc-chat .dots span:nth-child(3){animation-delay:.3s}@keyframes rocd{50%{opacity:.2}}@keyframes rocp{50%{opacity:.6}}' +
    '@media(max-width:480px){#roc-chat{right:0;bottom:0;width:100vw;max-width:100vw;height:100%;max-height:100%;border-radius:0}#roc-chat-btn{right:12px;bottom:12px}}' +
    // Night theme (html.roc-dark, the sun/moon button in the header: roc-nav.js / roc-theme.css)
    'html.roc-dark #roc-chat{background:#0f172a;color:#e2e8f0;border-color:#134e4a}html.roc-dark #roc-chat.inline{border-color:#134e4a}' +
    'html.roc-dark #roc-chat .rb{background:#020617}' +
    'html.roc-dark #roc-chat .m.a,html.roc-dark #roc-chat .gc,html.roc-dark #roc-chat .pc{background:#0f172a;border-color:#1e293b}' +
    'html.roc-dark #roc-chat .gc{border-color:rgba(52,211,153,.35)}html.roc-dark #roc-chat .gc .bar{background:#1e293b}' +
    'html.roc-dark #roc-chat .m a{color:#6ee7b7}html.roc-dark #roc-chat .m.u a{color:#fff}' +
    'html.roc-dark #roc-chat .pc span{color:#94a3b8}html.roc-dark #roc-chat .pc img{background:#fff}' +
    'html.roc-dark #roc-chat .chips button{background:rgba(16,185,129,.12);border-color:rgba(52,211,153,.4);color:#a7f3d0}' +
    'html.roc-dark #roc-chat .att,html.roc-dark #roc-chat form,html.roc-dark #roc-chat .ft{background:#0f172a;border-color:#1e293b}' +
    'html.roc-dark #roc-chat input[type=text]{background:#0b1220;border-color:#334155;color:#e2e8f0}' +
    'html.roc-dark #roc-chat input[type=text]::placeholder{color:#64748b}' +
    'html.roc-dark #roc-chat form .ic{background:#0b1220;border-color:#334155;color:#6ee7b7}' +
    'html.roc-dark #roc-chat form .ic.rec,html.roc-dark #roc-chat .att button{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.45);color:#fca5a5}' +
    'html.roc-dark #roc-chat .vt button{background:#0f172a;border-color:#1e293b}html.roc-dark #roc-chat .vt button:hover,html.roc-dark #roc-chat .vt button.on{background:rgba(16,185,129,.14)}' +
    'html.roc-dark #roc-chat .ft{color:#64748b}html.roc-dark #roc-chat .ft a{color:#94a3b8}' +
    'html.roc-dark .roc-gate{background:#0f172a!important;border-color:#134e4a!important}html.roc-dark .roc-gate-in{color:#cbd5e1!important}' +
    'html.roc-dark .roc-gate-t{color:#6ee7b7!important}html.roc-dark .roc-gate-su{background:rgba(16,185,129,.12)!important;border-color:rgba(52,211,153,.4)!important;color:#a7f3d0!important}';

  var SVG = function (p) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + p + '</svg>'; };
  var ICON = SVG('<rect x="3" y="7" width="18" height="13" rx="4"/><path d="M12 3v4M8 13h.01M16 13h.01M9 17h6"/>');
  var MIC = SVG('<rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0M12 17v5"/>');
  var CAM = SVG('<rect x="3" y="5" width="18" height="15" rx="3"/><circle cx="12" cy="12.5" r="3.5"/><path d="M8 5l1.5-2h5L16 5"/>');

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  /* **bold**, [text](/link) and plain /paths from ROC's answer; everything else stays text. */
  function fmt(s) {
    return esc(s).replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
      .replace(/\[([^\]]+)\]\((\/[^)\s]*|https:\/\/[^)\s]+)\)/g, function (m, t, u) { return '<a href="' + u + '"' + (/^https:/.test(u) && u.indexOf('runonconsole.com') < 0 ? ' target="_blank" rel="noopener nofollow"' : '') + '>' + t + '</a>'; })
      .replace(/(^|\s)(\/(?:compatibility|products|blogs|news|guides|category|contact|gaming-platforms|can-i-run|compare|updates)\/[a-z0-9\/?=&%-]*)/g, '$1<a href="$2">$2</a>');
  }

  var btn, box, body, form, input, sendBtn, micBtn, att, pic = null, busy = false, rec = null, user = { signed_in: false, name: '' }, styled = false;
  var CHECKER = /^\/compatibility\/?$/.test(location.pathname);
  var RETURN = encodeURIComponent('/compatibility/#roc');
  function style() {
    if (styled) return; styled = true;
    var st = document.createElement('style'); st.textContent = css; document.head.appendChild(st);
    // The old assistant button of the built pages: ROC replaces it.
    var old = document.querySelector('button[title="ROC AI Hardware Assistant"]');
    if (old && old.parentElement) old.parentElement.style.display = 'none';
  }
  /* The floating "Ask ROC" button opens ROC on every page. Members chat; visitors who are not
     signed in see "please sign in or sign up" in the same box (owner: stay on the page). */
  function floating() {
    style();
    btn = document.createElement('button');
    btn.id = 'roc-chat-btn'; btn.type = 'button'; btn.setAttribute('aria-label', 'Ask ROC, the Run On Console assistant');
    btn.innerHTML = ICON + '<span>Ask ROC</span>';
    document.body.appendChild(btn);
    btn.addEventListener('click', function () {
      if (!box) {
        box = makeBox(false); document.body.appendChild(box); wire();
        if (user.signed_in) render(); else gate();
      }
      toggle(true);
    });
    if (user.signed_in && state.open) btn.click();
    // "Ask ROC" buttons on the pages (data-roc-ask="question", Deploy 85): open the chat (made on first use)
    // with the question written in the box, ready to finish with the visitor's parts and send.
    document.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('[data-roc-ask]') : null;
      if (!a) return;
      e.preventDefault(); e.stopPropagation();
      btn.click();
      setTimeout(function () {
        if (!form || form.style.display === 'none') return;   // not signed in: the sign-in card shows
        input.value = a.getAttribute('data-roc-ask') || '';
        input.focus(); input.setSelectionRange(input.value.length, input.value.length);
      }, 80);
    });
  }
  function loginCard() {
    var back = encodeURIComponent(location.pathname + location.search);
    return '<div class="roc-gate-in" style="text-align:center;padding:18px 10px;font:14px/1.6 Inter,system-ui,sans-serif;color:#334155">' +
      '<div style="width:52px;height:52px;margin:0 auto 10px;border-radius:16px;background:#047857;color:#fff;display:flex;align-items:center;justify-content:center">' + ICON.replace('<svg', '<svg width="28" height="28"') + '</div>' +
      '<b class="roc-gate-t" style="display:block;font:900 18px Outfit,Inter,sans-serif;color:#064e3b">For access, please sign in or sign up</b>' +
      '<p style="margin:6px auto 14px;max-width:420px">ROC, our gaming assistant, is free for members. Ask anything about games, consoles, PC parts and esports, or if your PC can run a game. ' +
      'Type or speak, in English or Roman Urdu.<br><span style="color:#64748b">Access ke liye login ya sign up karein.</span></p>' +
      '<a href="/auth/login/?return=' + back + '" style="display:inline-block;margin:4px;background:#047857;color:#fff;font-weight:800;padding:11px 20px;border-radius:12px;text-decoration:none">Sign in</a>' +
      '<a href="/auth/signup/?return=' + back + '" class="roc-gate-su" style="display:inline-block;margin:4px;background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0;font-weight:800;padding:11px 20px;border-radius:12px;text-decoration:none">Sign up free</a></div>';
  }
  /* Not signed in: the box shows the sign-in card instead of the chat. */
  function gate() {
    body.innerHTML = loginCard();
    form.style.display = 'none'; att.style.display = 'none';
    var tools = box.querySelector('.tools');
    [].forEach.call(tools.querySelectorAll('.lang,.spk'), function (b) { b.style.display = 'none'; });
  }
  /* On the checker page: ROC inside the page (React puts <div id="roc-chat-inline"> there). */
  function inline(host) {
    if (!host || host.getAttribute('data-roc-on')) return;
    host.setAttribute('data-roc-on', '1');
    style();
    if (!user.signed_in) {
      host.innerHTML = '<div class="roc-gate" style="border:2px solid #a7f3d0;border-radius:20px;background:#fff;padding:10px">' + loginCard() + '</div>';
      return;
    }
    box = makeBox(true);
    host.innerHTML = ''; host.appendChild(box);
    wire(); render();
  }
  function makeBox(isInline) {
    var b = document.createElement('div');
    b.id = 'roc-chat'; b.className = isInline ? 'on inline' : ''; b.setAttribute('role', isInline ? 'region' : 'dialog'); b.setAttribute('aria-label', 'ROC assistant');
    b.innerHTML = '<div class="rh"><div class="av">ROC</div><div><b>ROC</b><small>Your gaming assistant · English / Roman Urdu</small></div>' +
      '<div class="tools"><button type="button" class="new" title="New chat (clears this conversation)" aria-label="New chat">🔄</button>' +
      (Rec ? '<button type="button" class="lang" title="Microphone language: tap to switch between English and Urdu">' + (state.voice === 'ur' ? '🎤 UR' : '🎤 EN') + '</button>' : '') +
      ('speechSynthesis' in window ? '<button type="button" class="spk' + (state.speak ? ' on' : '') + '" title="Read answers aloud" aria-label="Read answers aloud">🔊</button>' : '') +
      (isInline ? '' : '<button type="button" class="rx" aria-label="Close" style="font-size:18px">&times;</button>') + '</div></div>' +
      '<div class="rb" aria-live="polite"></div>' +
      '<div class="att"><img alt=""><span style="font-size:12px;color:#475569;flex:1">Picture added</span><button type="button">Remove</button></div>' +
      '<form><button type="button" class="ic pcb" title="Read my PC: upload a dxdiag file (Win+R, type dxdiag, Save All Information)" aria-label="Upload a dxdiag file">🖥</button>' +
      '<input type="file" class="pcf" accept=".txt,text/plain" hidden>' +
      (Rec ? '<button type="button" class="ic mic" title="Speak" aria-label="Speak your question">' + MIC + '</button>' : '') +
      '<input type="text" maxlength="500" placeholder="Ask anything about gaming..." aria-label="Your question"><button type="submit" class="go">Send</button></form>' +
      '<div class="ft">ROC answers gaming questions only and can make mistakes. Chats are saved with your account, without personal details. <a href="/privacy-policy/">Privacy</a></div>';
    return b;
  }
  function wire() {
    body = box.querySelector('.rb'); form = box.querySelector('form'); input = form.querySelector('input[type=text]'); sendBtn = form.querySelector('.go');
    att = box.querySelector('.att'); micBtn = form.querySelector('.mic');
    var x = box.querySelector('.rx');
    if (x) x.addEventListener('click', function () { toggle(false); });
    form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && state.open && btn) toggle(false); });
    att.querySelector('button').addEventListener('click', function () { pic = null; att.classList.remove('on'); });
    if (micBtn) micBtn.addEventListener('click', listen);
    var pcf = form.querySelector('.pcf');
    form.querySelector('.pcb').addEventListener('click', function () { pcf.click(); });
    pcf.addEventListener('change', function () { if (pcf.files && pcf.files[0]) readDxdiag(pcf.files[0]); pcf.value = ''; });
    body.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-vote]') : null;
      if (b) vote(+b.getAttribute('data-i'), +b.getAttribute('data-vote'));
    });
    var lang = box.querySelector('.lang');
    if (lang) lang.addEventListener('click', function () { state.voice = state.voice === 'ur' ? 'en' : 'ur'; lang.textContent = state.voice === 'ur' ? '🎤 UR' : '🎤 EN'; save(); });
    var fresh = box.querySelector('.new');
    if (fresh) fresh.addEventListener('click', function () {
      if (busy) return;
      try { if ('speechSynthesis' in window) speechSynthesis.cancel(); } catch (e) {}
      state.msgs = []; state.session = Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-6);
      save(); render(); input.focus();
    });
    // A click anywhere else on the page closes the floating box (the chat is kept).
    if (btn) document.addEventListener('mousedown', function (e) {
      if (state.open && box && !box.contains(e.target) && !btn.contains(e.target)) toggle(false);
    });
    var spk = box.querySelector('.spk');
    if (spk) spk.addEventListener('click', function () { state.speak = !state.speak; spk.classList.toggle('on', state.speak); if (!state.speak) speechSynthesis.cancel(); save(); });
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
  /* Speaking: the browser turns speech into text (English or Urdu), then it is sent and ROC answers
     out loud (a voice conversation). */
  var viaVoice = false;
  function listen() {
    if (rec) { rec.stop(); return; }
    try { if ('speechSynthesis' in window) speechSynthesis.cancel(); } catch (e) {}
    rec = new Rec();
    rec.lang = state.voice === 'ur' ? 'ur-PK' : 'en-US';
    rec.interimResults = true;
    micBtn.classList.add('rec');
    input.placeholder = state.voice === 'ur' ? 'Bolein, main sun raha hoon...' : 'Listening... speak now';
    var said = '', failed = '';
    rec.onresult = function (e) { said = ''; for (var i = 0; i < e.results.length; i++) said += e.results[i][0].transcript; input.value = said; };
    rec.onerror = function (e) {
      failed = e.error === 'not-allowed' || e.error === 'service-not-allowed'
        ? 'The microphone is blocked. Click the lock icon next to the address bar, allow the Microphone and try again. / Mic ki ijazat dein.'
        : e.error === 'no-speech' ? 'I did not hear anything. Tap the microphone and speak again.'
        : e.error === 'audio-capture' ? 'No microphone was found on this device.'
        : e.error === 'network' ? 'Voice input needs an internet connection. Please try again.' : '';
    };
    rec.onend = function () {
      micBtn.classList.remove('rec'); rec = null;
      input.placeholder = 'Ask anything about gaming...';
      if (said.trim()) { viaVoice = true; send(said); }
      else if (failed) { state.msgs.push({ role: 'assistant', content: failed }); render(); save(); }
    };
    try { rec.start(); } catch (e) { micBtn.classList.remove('rec'); rec = null; input.placeholder = 'Ask anything about gaming...'; }
  }
  /* Reading answers aloud with the most natural voice the browser has: "Natural", "Online" and Google
     voices sound the most human. Roman Urdu is read by a Hindi or Indian English voice, which says the
     words the way they are meant. */
  var voices = [];
  function loadVoices() { try { voices = speechSynthesis.getVoices() || []; } catch (e) { voices = []; } }
  if ('speechSynthesis' in window) { loadVoices(); try { speechSynthesis.addEventListener('voiceschanged', loadVoices); } catch (e) {} }
  function pickVoice(urdu) {
    var score = function (v) {
      var n = (v.name || '') + ' ' + (v.lang || ''), x = 0;
      if (/natural|neural|online/i.test(n)) x += 6;
      if (/google/i.test(n)) x += 4;
      if (/female|aria|jenny|zira|samantha|swara|neerja|heera/i.test(n)) x += 1;
      if (urdu) { if (/^(hi|ur)/i.test(v.lang)) x += 10; else if (/en-IN/i.test(v.lang)) x += 8; else if (/^en/i.test(v.lang)) x += 2; else x -= 20; }
      else { if (/en-US|en-GB/i.test(v.lang)) x += 8; else if (/^en/i.test(v.lang)) x += 5; else x -= 20; }
      return x;
    };
    var best = null, top = -99;
    voices.forEach(function (v) { var x = score(v); if (x > top) { top = x; best = v; } });
    return best;
  }
  function speak(text, force) {
    if ((!state.speak && !force) || !('speechSynthesis' in window)) return;
    try {
      if (!voices.length) loadVoices();
      speechSynthesis.cancel();
      var clean = String(text).replace(/\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/\*\*|__|`/g, '').replace(/https?:\/\/\S+/g, '').replace(/\/[a-z0-9\-\/?=&%]+\//gi, '').replace(/[•#>]/g, ' ');
      var urdu = /\b(hai|hain|kya|aap|mein|nahi|chalega|kar|karein|ke|ki|ka|bhi|yeh|ye)\b/i.test(clean) || /[\u0600-\u06FF]/.test(clean);
      // Shorter pieces sound smoother and avoid the cut-off some browsers have on long text.
      var parts = clean.match(/[^.!?\n]+[.!?]?/g) || [clean];
      var v = pickVoice(urdu);
      parts.forEach(function (t) {
        t = t.trim(); if (!t) return;
        var u = new SpeechSynthesisUtterance(t);
        if (v) { u.voice = v; u.lang = v.lang; } else u.lang = urdu ? 'hi-IN' : 'en-US';
        u.rate = 1; u.pitch = 1;
        speechSynthesis.speak(u);
      });
    } catch (e) {}
  }
  /* What this browser says about the PC, for "analyze my PC": the graphics card (WebGL), processor
     threads and a memory class. Nothing else is read. */
  var deviceInfo = null;
  function device() {
    if (deviceInfo) return deviceInfo;
    var gpu = '';
    try {
      var c = document.createElement('canvas'), gl = c.getContext('webgl') || c.getContext('experimental-webgl');
      if (gl) { var ext = gl.getExtension('WEBGL_debug_renderer_info'); gpu = String(ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER) || ''); }
    } catch (e) {}
    gpu = gpu.replace(/^ANGLE \((.*)\)$/, '$1').replace(/,? ?(Direct3D|D3D)\S*.*$/i, '').replace(/\s*\(0x[0-9a-f]+\)/ig, '').trim();
    var ua = navigator.userAgent || '';
    var os = /Windows/i.test(ua) ? 'Windows' : /Mac OS/i.test(ua) ? 'macOS' : /Android/i.test(ua) ? 'Android' : /iPhone|iPad/i.test(ua) ? 'iOS' : /Linux/i.test(ua) ? 'Linux' : '';
    return deviceInfo = { gpu: gpu.slice(0, 120), threads: navigator.hardwareConcurrency || 0, ram: navigator.deviceMemory || 0, os: os };
  }
  /* The PC report: Windows dxdiag (Win+R, dxdiag, "Save All Information"). Only the lines about the
     processor, memory, graphics cards, system model and Windows are sent; nothing else of the file. */
  function readDxdiag(f) {
    if (f.size > 3000000) { state.msgs.push({ role: 'assistant', content: 'That file is too big. Please choose the DxDiag.txt file.' }); render(); return; }
    var r = new FileReader();
    r.onload = function () {
      var b = new Uint8Array(r.result), text;
      try { text = new TextDecoder(b[0] === 0xFF && b[1] === 0xFE ? 'utf-16le' : 'utf-8').decode(b); } catch (e) { text = ''; }
      var keep = (text.match(/^\s*(Operating System|System Manufacturer|System Model|Processor|Memory|Card name|Dedicated Memory):.*$/gmi) || [])
        .map(function (l) { return l.trim(); }).filter(function (l, i, a) { return a.indexOf(l) === i; }).slice(0, 12);
      if (!/Processor:|Card name:/i.test(keep.join('\n'))) {
        state.msgs.push({ role: 'assistant', content: 'This does not look like a dxdiag report. On Windows press Win + R, type dxdiag, press Enter, click "Save All Information" and choose that file here.' });
        render(); save(); return;
      }
      send('My PC (from dxdiag):\n' + keep.join('\n') + '\n\nPlease analyze my PC.');
    };
    r.readAsArrayBuffer(f);
  }
  /* 👍 / 👎 on an answer: CMS > ROC assistant shows the 👎 ones, to make ROC better. */
  function vote(i, v) {
    var m = state.msgs[i];
    if (!m || !m.id) return;
    m.vote = m.vote === v ? 0 : v;
    save(); render();
    fetch('/api/v1/agent.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ action: 'vote', id: m.id, vote: m.vote }) }).catch(function () {});
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
      // Nothing written by ROC before the visitor speaks: only its mark, like an empty chat.
      h += '<div style="margin:auto;text-align:center;opacity:.35"><div style="width:48px;height:48px;margin:0 auto;border-radius:16px;background:#047857;color:#fff;display:flex;align-items:center;justify-content:center">' +
        ICON.replace('<svg', '<svg width="26" height="26"') + '</div></div>';
    }
    state.msgs.forEach(function (m, i) {
      var shown = m.role === 'user' && /^My PC \(from dxdiag\)/.test(m.content) ? '🖥 ' + m.content.split('\n').slice(1).filter(function (l) { return /Processor|Card name|Memory/i.test(l); }).join('\n') : m.content;
      h += '<div class="m ' + (m.role === 'user' ? 'u' : 'a') + '">' + (m.pic ? '<img src="' + m.pic + '" alt="">' : m.hadPic ? '📷 ' : '') +
        (m.role === 'user' ? esc(shown) : fmt(m.content)) + '</div>';
      if (m.role === 'assistant' && m.id) h += '<div class="vt"><button type="button" data-vote="1" data-i="' + i + '" class="' + (m.vote === 1 ? 'on' : '') + '" title="Good answer">👍</button>' +
        '<button type="button" data-vote="-1" data-i="' + i + '" class="' + (m.vote === -1 ? 'on' : '') + '" title="Wrong or not helpful">👎</button></div>';
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
    try { if ('speechSynthesis' in window) speechSynthesis.cancel(); } catch (e) {}
    var image = pic; pic = null; att.classList.remove('on');
    var voiceTurn = viaVoice; viaVoice = false;   // asked by voice: the answer is spoken too
    state.msgs.push(image ? { role: 'user', content: text, pic: image, hadPic: true } : { role: 'user', content: text });
    busy = true; sendBtn.disabled = true; render(); save();
    var hist = state.msgs.slice(-8).map(function (m) { return { role: m.role, content: m.content }; });
    fetch('/api/v1/agent.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
      body: JSON.stringify({ session: state.session, page: location.pathname, messages: hist, image: image || undefined, device: device() }) })
      .then(function (r) { return r.json().catch(function () { return { error: 'ROC could not answer. Please try again.' }; }); })
      .then(function (d) {
        if (d && d.reply) { state.msgs.push({ role: 'assistant', content: d.reply, id: d.id || 0, game: d.game || null, products: d.products || [] }); speak(d.reply, voiceTurn); }
        else if (d && d.login) { user.signed_in = false; state.msgs.pop(); }
        else state.msgs.push({ role: 'assistant', content: (d && d.error) || 'ROC could not answer. Please try again.' });
      })
      .catch(function () { state.msgs.push({ role: 'assistant', content: 'No connection. Please try again.' }); })
      .then(function () { busy = false; sendBtn.disabled = false; save(); if (!user.signed_in) { gate(); return; } render(); input.focus(); });
  }

  function start() {
    // Switched off in the CMS: nothing (asked once per visit). Signed in or not: asked on every page.
    if (ss && ss.getItem('roc-chat-off') === '1') return;
    fetch('/api/v1/agent.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
      try { ss && ss.setItem('roc-chat-off', d && d.on === false ? '1' : '0'); } catch (e) {}
      if (!d || d.on === false) return;
      user = { signed_in: !!d.signed_in, name: d.name || '', vision: d.vision !== false };
      if (!CHECKER) { floating(); return; }
      // The checker page: ROC inside the page. React gives its place after it has drawn the page
      // (GameCompatibilityView: window.__rocChatHost / window.rocChatMount), never before, so the
      // page's own markup stays as React expects it.
      window.rocChatMount = function (el) { inline(el); };
      if (window.__rocChatHost) inline(window.__rocChatHost);
    }).catch(function () {});
  }
  if (document.readyState !== 'loading') start(); else document.addEventListener('DOMContentLoaded', start);
})();
