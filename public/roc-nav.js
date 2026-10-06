/*
 * Run On Console — blog and product navigation bridge (interim, until React reads them from the API).
 *
 * 1. On React pages: any navigation to /blogs/... or /products/... becomes a normal page load, so the
 *    visitor always gets the server-rendered page with the current CMS content instead
 *    of the copy that was baked into the React bundle.
 * 2. On the server-rendered blog pages (<html data-roc-static>): small replacements for
 *    the React-only header controls — mobile menu, search box, share button, AI button.
 */
(function () {
  'use strict';

  function blogPath(href) {
    try {
      var u = new URL(href, location.href);
      // Blogs and products are rendered by the server from the CMS database.
      return u.origin === location.origin && /^\/(blogs|products)(\/|$)/.test(u.pathname) ? u : null;
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
        var nodes = Array.prototype.slice.call(foot.querySelectorAll('a[data-roc-social="' + x.platform + '"]'));
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
          s.action = '/blogs/';
          s.style.cssText = 'margin-top:8px';
          s.innerHTML = '<input name="q" type="search" placeholder="Search articles..." aria-label="Search articles" ' +
            'style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #cbd5e1;border-radius:12px;font-size:14px">';
          panel.appendChild(s);
          header.appendChild(panel);
        }
        var open = panel.style.display === 'none';
        panel.style.display = open ? 'block' : 'none';
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // Header search: search the blog.
    document.querySelectorAll('input[aria-label="Search gaming hardware and games"]').forEach(function (input) {
      input.placeholder = 'Search articles...';
      input.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var q = input.value.trim();
        location.assign('/blogs/' + (q ? '?q=' + encodeURIComponent(q) : ''));
      });
      var here = new URLSearchParams(location.search).get('q');
      if (here) input.value = here;
    });

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
