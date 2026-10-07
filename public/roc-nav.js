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
      // Blogs, products and gaming platforms are rendered by the server from the CMS database.
      return u.origin === location.origin && /^\/(blogs|products|categories)(\/|$)/.test(u.pathname) ? u : null;
    } catch (e) { return null; }
  }

  /* ---------- 1. full page loads for /blogs links (capture phase runs before React) */
  window.addEventListener('click', function (ev) {
    if (ev.defaultPrevented || ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) return;
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download')) return;
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
    var AFF = /(^|\.)(amazon\.[a-z.]+|amzn\.to|a\.co|bestbuy\.com|newegg\.com|walmart\.com|ebay\.[a-z.]+)$/i;
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
    function apply() { applySocial(); applyTag(); }
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
     Products and blogs come live from the server, games from the website build.
     Matching forgives small typos ("logitec", "deathader"). */
  (function () {
    var data = null, loading = null;
    var PAGES = { products: '/products/', blogs: '/blogs/', games: '/compatibility/' };
    var LABELS = { products: 'Products', blogs: 'Blogs', games: 'Games & compatibility' };

    function load() {
      if (data) return Promise.resolve(data);
      if (loading) return loading;
      function get(u) { return fetch(u, { cache: 'no-cache' }).then(function (r) { return r.ok ? r.json() : []; }).catch(function () { return []; }); }
      loading = Promise.all([get('/products/?json=1'), get('/blogs/?json=1'), get('/roc-games.json')]).then(function (r) {
        data = { products: r[0] || [], blogs: r[1] || [], games: r[2] || [] };
        return data;
      });
      return loading;
    }

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
        var w = words[i];
        if (title.indexOf(w) !== -1) { total += 3; continue; }
        if (hay.indexOf(w) !== -1) { total += 2; continue; }
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
      if (scope === 'all') {
        // "All": open the page of the best kind of result.
        scope = find('products', q, 1).length ? 'products' : find('blogs', q, 1).length ? 'blogs' : find('games', q, 1).length ? 'games' : 'products';
      }
      return PAGES[scope] + (q ? '?q=' + encodeURIComponent(q) : '');
    }

    function render(form) {
      var q = form.q.value.trim(), scope = form['in'] ? form['in'].value : 'all';
      var box = form.querySelector('.roc-sr');
      if (!q) { if (box) box.remove(); return; }
      css();
      if (!box) { box = document.createElement('div'); box.className = 'roc-sr'; box.setAttribute('role', 'listbox'); form.appendChild(box); }
      if (!data) { box.innerHTML = '<p>Searching…</p>'; return; }
      var scopes = scope === 'all' ? ['products', 'blogs', 'games'] : [scope], html = '', any = false;
      scopes.forEach(function (s) {
        var list = find(s, q, scope === 'all' ? 4 : 10);
        if (!list.length) return;
        any = true;
        html += '<h6>' + LABELS[s] + '</h6>' + list.map(function (it) {
          var sub = s === 'products' ? [it.b, it.c].filter(Boolean).join(' · ') : s === 'blogs' ? (it.c || it.e || '') : (it.g ? it.g + ' · check if it runs' : 'Check if it runs');
          return '<a href="' + esc(it.u) + '"><b>' + esc(it.t) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</a>';
        }).join('');
      });
      box.innerHTML = any ? html + '<a class="all" href="' + esc(targetUrl(scope, q)) + '">See all results for “' + esc(q) + '”</a>'
        : '<p>Nothing found for “' + esc(q) + '”.</p>';
    }

    function formOf(el) { return el && el.closest ? el.closest('form[data-roc-search]') : null; }
    document.addEventListener('focusin', function (e) { var f = formOf(e.target); if (f) load().then(function () { render(f); }); });
    document.addEventListener('input', function (e) { var f = formOf(e.target); if (f) { render(f); load().then(function () { render(f); }); } });
    document.addEventListener('change', function (e) { var f = formOf(e.target); if (f && e.target.name === 'in') { render(f); if (f.q.value.trim()) f.q.focus(); } });
    document.addEventListener('submit', function (e) {
      var f = formOf(e.target);
      if (!f) return;
      e.preventDefault();
      e.stopPropagation();
      var q = f.q.value.trim(), scope = f['in'] ? f['in'].value : 'all';
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
      var scope = /^\/products\//.test(location.pathname) ? 'products' : /^\/blogs\//.test(location.pathname) ? 'blogs' : /^\/compatibility\//.test(location.pathname) ? 'games' : 'all';
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
            '<option value="all">All</option><option value="products">Products</option><option value="blogs">Blogs</option><option value="games">Games</option></select>' +
            '<input name="q" type="search" placeholder="Search products, blogs, games..." aria-label="Search" ' +
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

    // Floating AI assistant button: open the compatibility checker.
    var bot = document.querySelector('button[title="ROC AI Hardware Assistant"]');
    if (bot) {
      bot.addEventListener('click', function () { location.assign('/compatibility/'); });
      var bubble = bot.previousElementSibling;
      if (bubble) { bubble.style.cursor = 'pointer'; bubble.addEventListener('click', function () { location.assign('/compatibility/'); }); }
    }
  });
})();
