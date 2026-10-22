/*
 * Run On Console — blog and product navigation bridge (interim, until React reads them from the API).
 *
 * 1. On React pages: any navigation to /blogs/... or /products/... becomes a normal page load, so the
 *    visitor always gets the server-rendered page with the current CMS content instead
 *    of the copy that was baked into the React bundle.
 * 2. Header search box on every page (products, blogs and games, with typo-tolerant matching).
 * 3. On the server-rendered blog pages (<html data-roc-static>): small replacements for
 *    the React-only header controls — mobile menu, share button, AI button.
 */
(function () {
  'use strict';

  function blogPath(href) {
    try {
      var u = new URL(href, location.href);
      // Blogs, news, products and gaming platforms are rendered by the server from the CMS database.
      return u.origin === location.origin && /^\/(blogs|news|products|category|categories|gaming-platforms)(\/|$)/.test(u.pathname) ? u : null;
    } catch (e) { return null; }
  }

  /* ---------- 1. full page loads for /blogs links (capture phase runs before React) */
  window.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download') || a.hasAttribute('data-roc-local')) return;   // data-roc-local: the page handles it (Load more)
    var u = blogPath(a.getAttribute('href'));
    if (!u) return;
    ev.preventDefault();
    ev.stopImmediatePropagation();
    location.assign(u.href);
  }, true);

  // Router navigations that do not come from a link (buttons, cards with onClick).
  ['pushState', 'replaceState'].forEach(function (name) {
    var original = history[name];
    if (typeof original !== 'function') return;
    history[name] = function (state, title, url) {
      if (url != null) {
        var u = blogPath(String(url));
        if (u && u.pathname + u.search !== location.pathname + location.search) {
          location.assign(u.href);
          return;
        }
      }
      return original.apply(this, arguments);
    };
  });

  /* ---------- SEO overrides from the CMS (title + description).
     The server already patched the page HTML; this keeps React from putting its
     built-in values back after it loads or when it navigates between pages. */
  (function () {
    var map = window.__ROC_SEO_MAP__ || {};
    function apply() {
      var o = map[location.pathname] || map[location.pathname.replace(/\/?$/, '/')];
      if (!o) return;
      if (o.title && document.title !== o.title) document.title = o.title;
      if (o.description) {
        var d = document.querySelector('meta[name="description"]');
        if (d && d.getAttribute('content') !== o.description) d.setAttribute('content', o.description);
      }
    }
    function watch() {
      apply();
      if (!window.MutationObserver || !document.head) return;
      new MutationObserver(apply).observe(document.head, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['content'] });
      window.addEventListener('popstate', apply);
    }
    try {
      fetch('/roc-seo-map.json', { cache: 'no-cache' }).then(function (r) { return r.ok ? r.json() : {}; })
        .then(function (m) { for (var k in m) if (!map[k]) map[k] = m[k]; apply(); }).catch(function () {});
    } catch (e) {}
    ['pushState', 'replaceState'].forEach(function (name) {
      var wrapped = history[name];
      history[name] = function () { var r = wrapped.apply(this, arguments); setTimeout(apply, 0); return r; };
    });
    if (document.readyState !== 'loading') watch(); else document.addEventListener('DOMContentLoaded', watch);
  })();

  /* ---------- Buy / affiliate link clicks -> CMS Live activity */
  (function () {
    var AFF = /(^|\.)(amazon\.[a-z.]+|amzn\.to|a\.co|daraz\.pk|bestbuy\.com|newegg\.com|walmart\.com|ebay\.[a-z.]+)$/i;
    function send(a) {
      try {
        var u = new URL(a.href, location.href);
        var sponsored = /sponsored/i.test(a.getAttribute('rel') || '');
        if (u.origin === location.origin || !(sponsored || AFF.test(u.hostname))) return;
        var label = (a.getAttribute('aria-label') || a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 200);
        var card = a.closest('[class*="card"], article, section');
        var heading = card && card.querySelector('h2, h3, h4');
        if (heading && heading.textContent) label = heading.textContent.trim().slice(0, 160) + (label ? ' · ' + label : '');
        var body = JSON.stringify({ type: 'affiliate_click', url: u.href, label: label, page: location.pathname });
        if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/track.php', new Blob([body], { type: 'application/json' }));
        else fetch('/api/v1/track.php', { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'application/json' } });
      } catch (e) {}
    }
    document.addEventListener('click', function (ev) { var a = ev.target && ev.target.closest && ev.target.closest('a[href]'); if (a) send(a); }, true);
    document.addEventListener('auxclick', function (ev) { var a = ev.target && ev.target.closest && ev.target.closest('a[href]'); if (a && ev.button === 1) send(a); }, true);
  })();

  /* ---------- Site settings from the CMS (/roc-site.json):
     - social profile links in the footer (cards marked data-roc-social="facebook" …;
       older pages are matched by their placeholder link, e.g. https://facebook.com)
     - Amazon affiliate tag, added to every Amazon link on the page */
  (function () {
    var cfg = null;
    function setVisible(a, on) {
      if (on) { a.removeAttribute('hidden'); a.classList.remove('hidden'); if (!a.classList.contains('flex') && a.getAttribute('data-roc-social')) a.classList.add('flex'); a.style.display = ''; }
      else { a.setAttribute('hidden', ''); a.style.display = 'none'; }
    }
    function applySocial() {
      if (!cfg || !cfg.social || !cfg.social.some(function (x) { return x.url; })) return;   // nothing saved yet: keep the built footer
      var foot = document.querySelector('footer');
      if (!foot) return;
      cfg.social.forEach(function (x) {
        var nodes = Array.prototype.slice.call(document.querySelectorAll('a[data-roc-social="' + x.platform + '"]'));
        // Boxes that only make sense with a link (e.g. the Discord card on the contact page).
        document.querySelectorAll('[data-roc-social-box="' + x.platform + '"]').forEach(function (b) { b.style.display = x.url ? '' : 'none'; });
        if (!nodes.length && x.match) {
          var re = new RegExp('^https?://(www\\.)?' + x.match.replace(/\./g, '\\.') + '/?$', 'i');
          foot.querySelectorAll('a[href]').forEach(function (a) {
            var orig = a.getAttribute('data-roc-orig') || a.getAttribute('href');
            if (re.test(orig)) { a.setAttribute('data-roc-orig', orig); nodes.push(a); }
          });
        }
        nodes.forEach(function (a) {
          if (x.url) {
            if (a.getAttribute('href') !== x.url) a.setAttribute('href', x.url);
            a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener me');
            setVisible(a, true);
          } else {
            setVisible(a, false);
          }
        });
      });
    }
    function tagUrl(href) {
      try {
        var u = new URL(href, location.href);
        if (!/(^|\.)amazon\.[a-z.]+$/i.test(u.hostname)) return null;
        if (u.searchParams.get('tag') === cfg.amazon_tag) return null;
        u.searchParams.set('tag', cfg.amazon_tag);
        return u.href;
      } catch (e) { return null; }
    }
    function applyTag(root) {
      if (!cfg || !cfg.amazon_tag) return;
      (root || document).querySelectorAll('a[href*="amazon."]').forEach(function (a) {
        var t = tagUrl(a.getAttribute('href')); if (t) a.setAttribute('href', t);
      });
    }
    /* Daraz for Pakistan (CMS > Social profiles & Amazon tag): Amazon does not deliver to most of
       Pakistan, so visitors there (their clock is on Pakistan time) get a "Check on Daraz" button
       before each Amazon buy button: the product's own Daraz page when the CMS has one, else a Daraz
       search for it, through the affiliate link template when one is saved. ?country=pk / ?country=off
       tries it from anywhere (remembered in this browser). */
    function inPakistan() {
      try {
        var q = new URLSearchParams(location.search).get('country');
        if (q) localStorage.setItem('roc_country', q.toLowerCase());
        var f = localStorage.getItem('roc_country');
        if (f === 'pk') return true;
        if (f === 'off') return false;
      } catch (e) {}
      try { return /^Asia\/Karachi$/.test(Intl.DateTimeFormat().resolvedOptions().timeZone || ''); } catch (e) { return false; }
    }
    function productOf(a) {
      // The product page itself, or the product card the button sits in.
      var m = /^\/products\/([a-z0-9-]+)\/$/.exec(location.pathname);
      var el = a, link = null, box = null, name = '';
      // The smallest box around the button that links exactly one product (a card); stop at a box with several.
      for (var i = 0; i < 7; i++) {
        el = el.parentElement;
        if (!el || el === document.body) break;
        var seen = {}, n = 0, first = null;
        el.querySelectorAll('a[href^="/products/"]').forEach(function (x) {
          var s = (/^\/products\/([a-z0-9-]+)\/?$/.exec(x.getAttribute('href')) || [])[1];
          if (s && s !== 'category' && !seen[s]) { seen[s] = 1; n++; if (!first) first = x; }
        });
        if (n > 1) break;
        if (n === 1) { if (!link) link = first; box = el; if (el.querySelector('h2, h3, h4, .rp-title, a.t')) break; }   // up to the card's title
      }
      var slug = link ? (/^\/products\/([a-z0-9-]+)/.exec(link.getAttribute('href')) || [])[1] : (m ? m[1] : null);
      var h = box ? box.querySelector('h2, h3, h4, .rp-title, a.t') : document.querySelector('h1');
      name = h ? (h.textContent || '').trim() : '';
      if (!name && link) { var img = link.querySelector('img'); name = (img && img.getAttribute('alt')) || (link.textContent || '').trim(); }
      try { var k = new URL(a.href).searchParams.get('k'); if (!name && k) name = k; } catch (e) {}
      return { slug: slug, name: name.replace(/\s+/g, ' ').slice(0, 90) };
    }
    function applyDaraz() {
      var z = cfg && cfg.daraz;
      if (!z || !z.on || (z.who !== 'all' && !inPakistan())) return;
      document.querySelectorAll('a[href*="amazon."]:not([data-roc-dz])').forEach(function (a) {
        if (!/\/dp\/|\/gp\/product\/|\/s\?|amzn\.to|a\.co\//.test(a.href)) return;   // buy buttons only, not help pages
        a.setAttribute('data-roc-dz', '1');
        var p = productOf(a);
        if (!p.slug && !p.name) return;
        var url = (p.slug && z.links && z.links[p.slug]) || ('https://www.daraz.pk/catalog/?q=' + encodeURIComponent(p.name));
        if (z.template) url = z.template.replace('{url}', encodeURIComponent(url));
        var b = a.cloneNode(false);
        b.removeAttribute('data-roc-dz');
        b.setAttribute('data-roc-daraz', '1');
        b.setAttribute('href', url);
        b.setAttribute('target', '_blank');
        b.setAttribute('rel', 'sponsored nofollow noopener');
        b.style.background = '#f85606'; b.style.color = '#fff'; b.style.borderColor = '#f85606';
        var t = (a.textContent || '').trim();
        b.textContent = /amazon/i.test(t) ? t.replace(/amazon/i, 'Daraz') : 'Check on Daraz';
        b.setAttribute('aria-label', (p.name ? p.name + ': ' : '') + 'check on Daraz (Pakistan)');
        a.parentNode.insertBefore(b, a);
        if (a.parentNode && getComputedStyle(a.parentNode).display.indexOf('flex') < 0 && getComputedStyle(b).display === 'inline') b.style.marginRight = '8px';
      });
    }
    function apply() { applySocial(); applyTag(); applyDaraz(); }
    // A click can come before the page finished loading the settings: tag it right then.
    document.addEventListener('click', function (ev) {
      var a = ev.target && ev.target.closest ? ev.target.closest('a[href*="amazon."]') : null;
      if (a && cfg && cfg.amazon_tag) { var t = tagUrl(a.getAttribute('href')); if (t) a.setAttribute('href', t); }
    }, true);
    function start() {
      fetch('/roc-site.json', { cache: 'no-cache' }).then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { cfg = j; apply(); setTimeout(apply, 1500); }).catch(function () {});
      if (window.MutationObserver) {
        var t = null;
        new MutationObserver(function () { clearTimeout(t); t = setTimeout(apply, 60); })
          .observe(document.body, { childList: true, subtree: true });
      }
    }
    if (document.readyState !== 'loading') start(); else document.addEventListener('DOMContentLoaded', start);
  })();

  /* ---------- Header search (every page): <form data-roc-search> with a scope select.
     Products, blogs, guides, news and platforms come live from the server, games from the website build.
     Matching forgives small typos ("logitec", "deathader"). Deploy 81: no "All" any more; Products is the
     default, then Blogs, Games, Guides, News and Platforms (the select is rewritten on every page). */
  (function () {
    var data = null, loading = null;
    var PAGES = { products: '/products/', blogs: '/blogs/', games: '/can-i-run/', guides: '/guides/', news: '/news/', platforms: '/gaming-platforms/' };
    var LABELS = { products: 'Products', blogs: 'Blogs', games: 'Games & compatibility', guides: 'Guides', news: 'News', platforms: 'Gaming platforms' };
    var OPTIONS = '<option value="products">Products</option><option value="blogs">Blogs</option><option value="games">Games</option><option value="guides">Guides</option><option value="news">News</option><option value="platforms">Platforms</option>';
    function scopeOf(f) { var v = f['in'] ? f['in'].value : 'products'; return PAGES[v] ? v : 'products'; }
    function pageScope() {
      var p = location.pathname;
      return /^\/blogs\//.test(p) ? 'blogs' : /^\/guides\//.test(p) ? 'guides' : /^\/news\//.test(p) ? 'news'
        : /^\/(compatibility|can-i-run)\//.test(p) ? 'games' : /^\/gaming-platforms\//.test(p) ? 'platforms' : 'products';
    }
    function fixSelects() {
      document.querySelectorAll('form[data-roc-search] select[name="in"]').forEach(function (s) {
        if (s.getAttribute('data-roc-scopes') === '2') return;
        s.innerHTML = OPTIONS;
        s.value = pageScope();
        s.setAttribute('data-roc-scopes', '2');
      });
    }
    fixSelects();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fixSelects);
    window.addEventListener('load', fixSelects);
    if (window.MutationObserver) new MutationObserver(function () { if (document.querySelector('form[data-roc-search] select[name="in"]:not([data-roc-scopes="2"])')) fixSelects(); })
      .observe(document.documentElement, { childList: true, subtree: true });

    function load() {
      if (data) return Promise.resolve(data);
      if (loading) return loading;
      function get(u) { return fetch(u, { cache: 'no-cache' }).then(function (r) { return r.ok ? r.json() : []; }).catch(function () { return []; }); }
      // Games: every "Can I run" page (/can-i-run/?json=1, Deploy 89); the old checker list only when that is not there.
      loading = Promise.all([get('/products/?json=1'), get('/blogs/?json=1'), get('/can-i-run/?json=1').then(function (x) { return Array.isArray(x) && x.length ? x : get('/roc-games.json'); }), get('/gaming-platforms/?json=1')]).then(function (r) {
        // /blogs/?json=1 has every post: blogs, guides and news (their address says which).
        var posts = r[1] || [], kind = function (k) { return posts.filter(function (p) { return String(p.u || '').indexOf('/' + k + '/') === 0; }); };
        data = { products: r[0] || [], blogs: kind('blogs'), guides: kind('guides'), news: kind('news'), games: r[2] || [], platforms: Array.isArray(r[3]) ? r[3] : [] };
        return data;
      });
      return loading;
    }

    // Words people type that the catalog spells differently.
    var SYNONYMS = { mouse: ['mice'], mice: ['mouse'], headset: ['headsets', 'audio'], headphone: ['headset', 'audio'], headphones: ['headset', 'audio'],
      earbuds: ['buds'], gpu: ['graphics', 'rtx', 'radeon'], graphics: ['gpu'], videocard: ['graphics'], monitor: ['monitors', 'display'],
      display: ['monitor'], screen: ['monitor'], speaker: ['speakers', 'soundbar'], mic: ['microphone', 'wave', 'quadcast', 'seiren'] };
    function norm(t) { return String(t || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim(); }
    function lev(a, b, max) {
      if (Math.abs(a.length - b.length) > max) return max + 1;
      var prev = [], cur, i, j;
      for (j = 0; j <= b.length; j++) prev[j] = j;
      for (i = 1; i <= a.length; i++) {
        cur = [i];
        for (j = 1; j <= b.length; j++) cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
        prev = cur;
      }
      return prev[b.length];
    }
    /** 0 = no match; higher = better. Every query word must match (exactly, as a prefix, or one or two letters off). */
    function score(item, words) {
      var title = norm(item.t), hay = title + ' ' + norm((item.b || '') + ' ' + (item.c || '') + ' ' + (item.g || '') + ' ' + (item.e || ''));
      var hayWords = hay.split(' '), total = 0;
      for (var i = 0; i < words.length; i++) {
        var w = words[i], alts = [w].concat(SYNONYMS[w] || []);
        if (alts.some(function (a) { return title.indexOf(a) !== -1; })) { total += 3; continue; }
        if (alts.some(function (a) { return hay.indexOf(a) !== -1; })) { total += 2; continue; }
        if (w.length < 4) return 0;
        var max = w.length >= 7 ? 2 : 1, hit = false;
        for (var k = 0; k < hayWords.length && !hit; k++) {
          // Whole word, or its start ("superlite" ~ "superligh|t").
          if (lev(w, hayWords[k], max) <= max || lev(w, hayWords[k].slice(0, w.length), max) <= max) hit = true;
        }
        if (!hit) return 0;
        total += 1;
      }
      if (title.indexOf(words.join(' ')) === 0) total += 2;
      return total;
    }
    function find(scope, q, limit) {
      var words = norm(q).split(' ').filter(Boolean);
      if (!words.length || !data) return [];
      return (data[scope] || []).map(function (it) { return { it: it, s: score(it, words) }; })
        .filter(function (x) { return x.s > 0; })
        .sort(function (a, b) { return b.s - a.s; })
        .slice(0, limit).map(function (x) { return x.it; });
    }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    function css() {
      if (document.getElementById('roc-search-css')) return;
      var st = document.createElement('style');
      st.id = 'roc-search-css';
      st.textContent = '.roc-sr{position:absolute;right:0;top:calc(100% + 8px);width:min(380px,calc(100vw - 24px));max-height:420px;overflow-y:auto;background:#fff;border:2px solid #34d399;border-radius:18px;padding:8px;box-shadow:0 20px 50px -12px rgba(15,23,42,.35);z-index:100000;font-family:Inter,system-ui,sans-serif}' +
        '.roc-sr h6{margin:6px 8px 4px;font:800 10px Outfit,Inter,sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#047857}' +
        '.roc-sr a{display:block;padding:7px 10px;border-radius:10px;text-decoration:none;color:#0f172a}' +
        '.roc-sr a:hover,.roc-sr a.on{background:#ecfdf5}' +
        '.roc-sr a b{display:block;font-size:12.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
        '.roc-sr a small{display:block;font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
        '.roc-sr .all{margin-top:4px;text-align:center;font-size:12px;font-weight:800;color:#fff;background:#059669}' +
        '.roc-sr .all:hover{background:#047857}' +
        '.roc-sr p{margin:12px;font-size:12px;color:#64748b;text-align:center}';
      document.head.appendChild(st);
    }

    function targetUrl(scope, q) {
      if (!PAGES[scope]) scope = 'products';
      // Guides and platforms have no search page: the list itself. Games: the A to Z list, filtered by q.
      return PAGES[scope] + (q && scope !== 'guides' && scope !== 'platforms' ? '?q=' + encodeURIComponent(q) : '');
    }

    function render(form) {
      var q = form.q.value.trim(), scope = scopeOf(form);
      var box = form.querySelector('.roc-sr');
      if (!q) { if (box) box.remove(); return; }
      css();
      if (!box) { box = document.createElement('div'); box.className = 'roc-sr'; box.setAttribute('role', 'listbox'); form.appendChild(box); }
      if (!data) { box.innerHTML = '<p>Searching…</p>'; return; }
      var scopes = [scope], html = '', any = false;
      scopes.forEach(function (s) {
        var list = find(s, q, 10);
        if (!list.length) return;
        any = true;
        html += '<h6>' + LABELS[s] + '</h6>' + list.map(function (it) {
          var sub = s === 'products' ? [it.b, it.c].filter(Boolean).join(' · ') : s === 'games' ? (it.g ? it.g + ' · check if it runs' : 'Check if it runs') : (it.c || it.e || '');
          return '<a href="' + esc(it.u) + '"><b>' + esc(it.t) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</a>';
        }).join('');
      });
      box.innerHTML = any ? html + '<a class="all" href="' + esc(targetUrl(scope, q)) + '">See all results for “' + esc(q) + '”</a>'
        : '<p>Nothing found for “' + esc(q) + '”.</p>';
    }

    function formOf(el) { return el && el.closest ? el.closest('form[data-roc-search]') : null; }
    // Only the text box: focusing a result link must not rebuild the list, or the click on it is lost.
    document.addEventListener('focusin', function (e) { var f = formOf(e.target); if (f && e.target.name === 'q') load().then(function () { render(f); }); });
    document.addEventListener('input', function (e) { var f = formOf(e.target); if (f) { render(f); load().then(function () { render(f); }); } });
    document.addEventListener('change', function (e) { var f = formOf(e.target); if (f && e.target.name === 'in') { render(f); if (f.q.value.trim()) f.q.focus(); } });
    document.addEventListener('submit', function (e) {
      var f = formOf(e.target);
      if (!f) return;
      e.preventDefault();
      e.stopPropagation();
      var q = f.q.value.trim(), scope = scopeOf(f);
      load().then(function () { location.assign(targetUrl(scope, q)); });
    }, true);
    document.addEventListener('keydown', function (e) {
      var f = formOf(e.target);
      if (!f) return;
      var box = f.querySelector('.roc-sr');
      if (e.key === 'Escape' && box) { box.remove(); return; }
      if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && box) {
        var links = Array.prototype.slice.call(box.querySelectorAll('a')), i = links.indexOf(document.activeElement);
        e.preventDefault();
        var next = links[e.key === 'ArrowDown' ? Math.min(links.length - 1, i + 1) : i - 1];
        if (next) next.focus(); else f.q.focus();
      }
    });
    document.addEventListener('click', function (e) {
      document.querySelectorAll('form[data-roc-search] .roc-sr').forEach(function (box) {
        if (!box.parentNode.contains(e.target)) box.remove();
      });
    });
    // Keep the words in the box on the results page.
    function fill() {
      var p = new URLSearchParams(location.search), q = p.get('q');
      if (!q) return;
      fixSelects();
      var scope = pageScope();
      document.querySelectorAll('form[data-roc-search]').forEach(function (f) { if (!f.q.value) f.q.value = q; if (f['in']) f['in'].value = scope; });
    }
    if (document.readyState !== 'loading') setTimeout(fill, 0); else document.addEventListener('DOMContentLoaded', fill);
  })();

  /* ---------- 2. server-rendered pages only */
  if (!document.documentElement.hasAttribute('data-roc-static')) return;

  function ready(fn) {
    if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    var header = document.querySelector('header');

    // Mobile menu: rebuild from the desktop nav links.
    var toggle = document.querySelector('button[aria-label="Toggle mobile navigation menu"]');
    if (toggle && header) {
      var panel = null;
      toggle.setAttribute('aria-expanded', 'false');
      toggle.addEventListener('click', function () {
        if (!panel) {
          panel = document.createElement('nav');
          panel.setAttribute('aria-label', 'Mobile');
          panel.style.cssText = 'display:none;background:#fff;border-top:1px solid #e2e8f0;padding:10px 16px 16px;box-shadow:0 12px 24px rgba(15,23,42,.08)';
          var seen = {};
          header.querySelectorAll('nav a[href]').forEach(function (a) {
            var label = (a.textContent || '').trim();
            if (!label || seen[label]) return;
            seen[label] = 1;
            var l = document.createElement('a');
            l.href = a.href;
            l.textContent = label;
            l.style.cssText = 'display:block;padding:10px 12px;border-radius:12px;font:800 13px Outfit,Inter,sans-serif;color:#0f172a;text-decoration:none;letter-spacing:.02em';
            panel.appendChild(l);
          });
          var s = document.createElement('form');
          s.action = '/products/';
          s.setAttribute('data-roc-search', '');
          s.setAttribute('role', 'search');
          s.style.cssText = 'margin-top:8px;position:relative;display:flex;gap:6px';
          s.innerHTML = '<select name="in" aria-label="Search in" style="padding:10px 6px;border:1px solid #cbd5e1;border-radius:12px;font-size:13px;background:#f8fafc">' +
            '<option value="products">Products</option><option value="blogs">Blogs</option><option value="games">Games</option><option value="guides">Guides</option><option value="news">News</option><option value="platforms">Platforms</option></select>' +
            '<input name="q" type="search" placeholder="Search products, games, guides..." aria-label="Search" ' +
            'style="flex:1;min-width:0;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:12px;font-size:14px">';
          panel.appendChild(s);
          header.appendChild(panel);
        }
        var open = panel.style.display === 'none';
        panel.style.display = open ? 'block' : 'none';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // Share button.
    document.querySelectorAll('[data-roc-share]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var data = { title: document.title, url: location.href.split('#')[0] };
        if (navigator.share) { navigator.share(data).catch(function () {}); return; }
        var done = function () {
          var label = btn.querySelector('span');
          if (!label) return;
          var old = label.textContent;
          label.textContent = 'Link copied';
          setTimeout(function () { label.textContent = old; }, 1800);
        };
        if (navigator.clipboard) navigator.clipboard.writeText(data.url).then(done, function () {});
      });
    });

  });
})();

/* Amazon product pictures (SiteStripe links) can be blocked by ad blockers or come back
   blank: show the site's own picture instead. The fallback is in the link after "#fb="
   (or the data-fb attribute); without one, the picture is hidden. Pictures that failed
   before this script ran are found by the scan below. */
(function () {
  var AMZ = /amazon-adsystem\.com|media-amazon\.com/;
  function swap(img) {
    if (!img || img.getAttribute('data-roc-fb-done')) return;
    var src = img.getAttribute('src') || '';
    if (!AMZ.test(src)) return;
    img.setAttribute('data-roc-fb-done', '1');
    var m = src.match(/#fb=([^#]+)$/), fb = img.getAttribute('data-fb') || (m ? decodeURIComponent(m[1]) : '');
    if (fb) { img.removeAttribute('srcset'); img.src = fb; img.style.objectFit = 'cover'; img.style.padding = '0'; }
    else img.style.visibility = 'hidden';
  }
  function bad(img) { return img.complete && img.naturalWidth <= 2; }
  window.addEventListener('error', function (e) { if (e.target && e.target.tagName === 'IMG') swap(e.target); }, true);
  window.addEventListener('load', function (e) { var t = e.target; if (t && t.tagName === 'IMG' && AMZ.test(t.src) && bad(t)) swap(t); }, true);
  function scan() { [].forEach.call(document.querySelectorAll('img'), function (i) { if (AMZ.test(i.getAttribute('src') || '') && bad(i)) swap(i); }); }
  scan();
  document.addEventListener('DOMContentLoaded', scan);
  window.addEventListener('load', function () { scan(); setTimeout(scan, 1500); });
  if (window.MutationObserver) new MutationObserver(function () { setTimeout(scan, 300); }).observe(document.documentElement, { childList: true, subtree: true });
})();

/* ROC, the site assistant: the chat box on every page (roc-chat.js, answers from /api/v1/agent.php). */
(function () {
  if (window.__rocChat || /^\/cms\//.test(location.pathname)) return;
  var s = document.createElement('script');
  s.src = '/roc-chat.js?v=91';
  s.defer = true;
  (document.head || document.documentElement).appendChild(s);
})();

/* The contact form above the footer (data-roc-contact-form): sent to /api/v1/messages.php, the same as the
   Contact page, so it lands in CMS > Messages and is emailed to contact@runonconsole.com. Works on every
   page (the PHP pages have no React). */
document.addEventListener('submit', function (e) {
  var f = e.target;
  if (!f || !f.hasAttribute || !f.hasAttribute('data-roc-contact-form')) return;
  e.preventDefault();
  var note = f.querySelector('[data-roc-contact-note]'), btn = f.querySelector('button[type=submit]');
  var val = function (n) { var el = f.elements[n]; return el ? String(el.value || '').trim() : ''; };
  var say = function (t, ok) { if (note) { note.textContent = t; note.style.color = ok ? '#047857' : '#b91c1c'; } };
  if (!val('name') || !val('message')) return say('Please fill in your name and message.');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email'))) return say('Please enter a valid email address.');
  if (f.elements.agree && !f.elements.agree.checked) return say('Please tick the box to agree to the Terms and Privacy Policy.');
  if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
  fetch('/api/v1/messages.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
    body: JSON.stringify({ kind: 'contact', name: val('name'), email: val('email'), subject: val('subject'), message: val('message'),
                           website: val('website'), meta: { page: location.pathname, form: 'footer' } }) })
    .then(function (r) { return r.json().catch(function () { return {}; }); })
    .then(function (d) {
      if (d && d.success) { f.reset(); say('Thank you! Your message was sent' + (d.reference ? ' (' + d.reference + ')' : '') + '. We usually reply within 2 working days.', true); }
      else say((d && d.error) || 'Your message could not be sent. Please email contact@runonconsole.com.');
    })
    .catch(function () { say('No connection. Please try again or email contact@runonconsole.com.'); })
    .then(function () { if (btn) { btn.disabled = false; btn.textContent = 'Send message'; } });
});

/* ---------- Wishlist (Deploy 70): a "Save" button on product pages (/products/{slug}/) and game pages
   (/can-i-run/{slug}/). Signed-in members save them (api/v1/member.php); the list is in Profile > Wishlist.
   Visitors who are not signed in go to the sign-in page and come back here. */
(function () {
  var state = null, busy = false;
  function item() {
    var m = /^\/products\/([a-z0-9-]+)\/$/.exec(location.pathname);
    if (m && m[1] !== 'category') return { type: 'product', key: m[1] };
    m = /^\/can-i-run\/([a-z0-9-]+)\/$/.exec(location.pathname);
    return m ? { type: 'game', key: m[1] } : null;
  }
  function load(cb) {
    if (state) return cb();
    fetch('/api/v1/member.php', { credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (d) { state = d && d.success ? d : { signed_in: false, keys: [] }; cb(); })
      .catch(function () { state = { signed_in: false, keys: [] }; cb(); });
  }
  function paint(b, it) {
    var on = state.keys.indexOf(it.type + ':' + it.key) > -1;
    b.innerHTML = (on ? '♥ Saved' : '♡ Save') + '<span class="roc-wl-s">' + (on ? ' · in your wishlist' : ' to wishlist') + '</span>';
    b.setAttribute('aria-pressed', on ? 'true' : 'false');
    b.style.background = on ? '#fff1f2' : '#fff'; b.style.borderColor = on ? '#fda4af' : '#cbd5e1'; b.style.color = on ? '#be123c' : '#334155';
  }
  function add() {
    var it = item();
    if (!it || document.querySelector('[data-roc-wl]')) return;
    var h1 = document.querySelector('main h1') || document.querySelector('h1');
    if (!h1) return;
    var b = document.createElement('button');
    b.type = 'button'; b.setAttribute('data-roc-wl', '1');
    b.style.cssText = 'display:inline-flex;align-items:center;gap:4px;margin-top:10px;padding:7px 14px;border:1px solid #cbd5e1;border-radius:999px;font:700 13px Inter,Arial,sans-serif;cursor:pointer;background:#fff;color:#334155';
    h1.insertAdjacentElement('afterend', b);
    b.textContent = '♡ Save';
    load(function () { paint(b, it); });
    b.addEventListener('click', function () {
      if (!state || busy) return;
      if (!state.signed_in) { location.href = '/auth/login/?return=' + encodeURIComponent(location.pathname); return; }
      busy = true;
      fetch('/api/v1/member.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'toggle', type: it.type, key: it.key }) })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.success) {
            var k = it.type + ':' + it.key, i = state.keys.indexOf(k);
            if (d.saved && i < 0) state.keys.push(k); if (!d.saved && i > -1) state.keys.splice(i, 1);
            paint(b, it);
            if (d.saved && !document.querySelector('[data-roc-wl-tip]')) {
              var t = document.createElement('a'); t.setAttribute('data-roc-wl-tip', '1'); t.href = '/profile/?tab=wishlist';
              t.textContent = 'See your wishlist →'; t.style.cssText = 'margin-left:10px;font:700 13px Inter,Arial,sans-serif;color:#047857;text-decoration:underline';
              b.insertAdjacentElement('afterend', t);
            }
          } else if (d && d.login) location.href = '/auth/login/?return=' + encodeURIComponent(location.pathname);
        })
        .catch(function () {})
        .then(function () { busy = false; });
    });
  }
  function start() {
    add();
    if (window.MutationObserver) { var t = null; new MutationObserver(function () { clearTimeout(t); t = setTimeout(add, 200); }).observe(document.body, { childList: true, subtree: true }); }
  }
  if (document.readyState !== 'loading') start(); else document.addEventListener('DOMContentLoaded', start);
})();

/* ---------- Green light under the pointer on every white card or box (Deploy 71; owner: the whole site).
   The same effect as the React cards (.roc-spot in the site CSS: a soft green circle follows the pointer
   behind the content). Here it is added to any white, rounded box the pointer is over, on every page
   (also the server-drawn ones: game pages, blogs, products). Menus, forms and the chat are left alone. */
(function () {
  if (!window.matchMedia || window.matchMedia('(hover: none)').matches || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var SKIP = 'header, nav, form, input, textarea, select, button, .roc-dd-panel, #roc-chat, .veil, [data-roc-region="header-nav"]';
  var cache = typeof WeakMap === 'function' ? new WeakMap() : null;
  function ok(el) {
    if (cache && cache.has(el)) return cache.get(el);
    var cs = getComputedStyle(el), r = el.getBoundingClientRect(), yes = false;
    var white = /^rgba?\((25[0-5]|24\d), (25[0-5]|24\d), (25[0-5]|24\d)(, (1|0\.[89]\d*))?\)$/.test(cs.backgroundColor);
    if (white && parseFloat(cs.borderTopLeftRadius) >= 6 && (cs.position === 'static' || cs.position === 'relative')
        && r.width >= 120 && r.height >= 60 && r.width < window.innerWidth * 0.98 && !el.closest(SKIP)) yes = true;
    if (cache) cache.set(el, yes);
    return yes;
  }
  // A brighter light (owner, 2026-10-16): the same rule as src/index.css, also for pages built with an older stylesheet.
  var st = document.createElement('style');
  st.textContent = '.roc-spot::before{background:radial-gradient(340px circle at var(--mx,50%) var(--my,50%),rgba(16,185,129,.40),rgba(52,211,153,.12) 45%,transparent 70%)!important}';
  (document.head || document.documentElement).appendChild(st);
  var raf = 0, ev = null;
  document.addEventListener('pointermove', function (e) {
    ev = e;
    if (raf) return;
    raf = requestAnimationFrame(function () {
      raf = 0;
      var el = ev.target && ev.target.nodeType === 1 ? ev.target : null;
      for (var i = 0; el && i < 8 && el !== document.body; i++, el = el.parentElement) {
        if (el.classList.contains('roc-spot') || ok(el)) {
          if (!el.classList.contains('roc-spot')) el.classList.add('roc-spot');
          var r = el.getBoundingClientRect();
          el.style.setProperty('--mx', (ev.clientX - r.left) + 'px');
          el.style.setProperty('--my', (ev.clientY - r.top) + 'px');
          return;
        }
      }
    });
  }, { passive: true });
})();

/* ---------- Visits from social media (Deploy 74): once per visit, the platform (from utm_source on a
   utm_medium=social link, or from the site the visitor came from) and the landing page are counted by
   api/v1/social-visit.php. Nothing about the person is sent. CMS > Social posts > Social traffic. */
(function () {
  try { if (sessionStorage.getItem('roc_soc')) return; } catch (e) { return; }
  var q = new URLSearchParams(location.search), src = '';
  var map = { facebook: 'facebook', fb: 'facebook', instagram: 'instagram', ig: 'instagram', x: 'x', twitter: 'x', linkedin: 'linkedin', pinterest: 'pinterest',
              tiktok: 'tiktok', youtube: 'youtube', reddit: 'reddit', quora: 'quora', whatsapp: 'whatsapp', feed: 'feed' };
  if ((q.get('utm_medium') || '').toLowerCase() === 'social') src = map[(q.get('utm_source') || '').toLowerCase()] || 'other';
  if (!src && document.referrer) {
    var h = '';
    try { h = new URL(document.referrer).hostname.replace(/^www\.|^m\.|^l\.|^lm\./, ''); } catch (e) {}
    var hosts = [[/(^|\.)facebook\.com$|(^|\.)fb\.com$/, 'facebook'], [/(^|\.)instagram\.com$/, 'instagram'], [/^t\.co$|(^|\.)x\.com$|(^|\.)twitter\.com$/, 'x'],
                 [/(^|\.)linkedin\.com$|^lnkd\.in$/, 'linkedin'], [/(^|\.)pinterest\./, 'pinterest'], [/(^|\.)tiktok\.com$/, 'tiktok'],
                 [/(^|\.)youtube\.com$|^youtu\.be$/, 'youtube'], [/(^|\.)reddit\.com$/, 'reddit'], [/(^|\.)quora\.com$/, 'quora'], [/whatsapp\.com$/, 'whatsapp']];
    for (var i = 0; i < hosts.length; i++) if (hosts[i][0].test(h)) { src = hosts[i][1]; break; }
  }
  if (!src) return;
  try { sessionStorage.setItem('roc_soc', src); } catch (e) {}
  var body = JSON.stringify({ src: src, path: location.pathname.slice(0, 190) });
  if (navigator.sendBeacon) navigator.sendBeacon('/api/v1/social-visit.php', new Blob([body], { type: 'application/json' }));
  else fetch('/api/v1/social-visit.php', { method: 'POST', body: body, keepalive: true, headers: { 'Content-Type': 'application/json' } });
})();

/* Day / night theme: the sun/moon button in the header ([data-roc-theme], Header.jsx). The choice is saved in
   localStorage "roc-theme" and put on <html> as .roc-dark before the page paints (inline script in index.html);
   the colours are in /roc-theme.css (scripts/gen-roc-theme.py). Works on React and server-rendered pages. */
(function () {
  var root = document.documentElement;
  function isDark() { return root.classList.contains('roc-dark'); }
  function label() {
    document.querySelectorAll('[data-roc-theme]').forEach(function (b) {
      b.setAttribute('aria-label', isDark() ? 'Switch to day mode' : 'Switch to night mode');
      b.setAttribute('aria-pressed', isDark() ? 'true' : 'false');
    });
    var m = document.querySelector('meta[name="theme-color"]');
    if (m) m.setAttribute('content', isDark() ? '#020617' : '#064E3B');
  }
  function set(dark, animate) {
    if (animate) { root.classList.add('roc-theme-anim'); setTimeout(function () { root.classList.remove('roc-theme-anim'); }, 450); }
    root.classList.toggle('roc-dark', dark);
    try { localStorage.setItem('roc-theme', dark ? 'dark' : 'light'); } catch (e) {}
    label();
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target && ev.target.closest && ev.target.closest('[data-roc-theme]');
    if (!b) return;
    ev.preventDefault();
    set(!isDark(), true);
  });
  // Another tab changed it: follow.
  window.addEventListener('storage', function (ev) { if (ev.key === 'roc-theme') { root.classList.toggle('roc-dark', ev.newValue === 'dark'); label(); } });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', label); else label();
  if (window.MutationObserver) new MutationObserver(function () { var b = document.querySelector('[data-roc-theme]:not([aria-pressed])'); if (b) label(); })
    .observe(document.documentElement, { childList: true, subtree: true });
})();
