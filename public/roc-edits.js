/*
 * Run On Console — changes made in CMS > Site builder.
 *
 * The server puts window.__ROC_EDITS (every change, with its scope) in the page and the
 * CSS part (colours, sizes, hidden elements) for the first paint. This file puts in the
 * rest (text, links, pictures, added blocks) once React has taken over the page, and
 * again whenever React redraws it or the visitor moves to another page.
 *
 * Change: {id, scope: "*" | "/products/*" | "/about/", sel, text?, href?, src?, alt?,
 *          hide?, style?: {css-property: value}, html?, pos?: "after" | "before"}
 * The CMS builder calls window.__rocEditsSet(list) for a live preview.
 */
(function () {
  if (window.__rocEditsSet) return;
  var edits = window.__ROC_EDITS || [];
  var STYLE = ['color', 'background-color', 'font-size', 'font-weight', 'text-align', 'padding', 'margin',
               'border-radius', 'line-height', 'letter-spacing', 'text-transform'];
  var started = false, timer = 0, busy = false;

  function inScope(scope, path) {
    if (scope === '*') return true;
    if (scope.slice(-1) === '*') { var pre = scope.slice(0, -1); return path.indexOf(pre) === 0 && path !== pre; }
    return scope === path;
  }
  function here() {
    var p = location.pathname.replace(/\/index\.html$/, '/');
    return p;
  }
  function cssFor(list) {
    var out = '';
    list.forEach(function (e) {
      if (!e.sel || /[<{};]/.test(e.sel)) return;
      var r = [];
      if (e.hide) r.push('display:none!important');
      var st = e.style || {};
      Object.keys(st).forEach(function (k) {
        if (STYLE.indexOf(k) !== -1 && /^[#a-zA-Z0-9 .,%()\-]{1,60}$/.test(String(st[k]))) r.push(k + ':' + st[k] + '!important');
      });
      if (r.length) out += e.sel + '{' + r.join(';') + '}';
    });
    return out;
  }
  function all(sel) { try { return document.querySelectorAll(sel); } catch (x) { return []; } }

  function apply() {
    busy = true;
    var path = here();
    var list = edits.filter(function (e) { return inScope(String(e.scope || ''), path); });
    // Looks: one live <style>; the server one only fits the first page.
    var live = document.getElementById('roc-edits-live');
    if (!live) { live = document.createElement('style'); live.id = 'roc-edits-live'; document.head.appendChild(live); }
    var css = cssFor(list);
    if (live.textContent !== css) live.textContent = css;
    var first = document.getElementById('roc-edits-css');
    if (first && first.textContent) first.textContent = '';
    var keep = {};
    list.forEach(function (e) {
      [].forEach.call(all(e.sel), function (el) {
        if (el.closest && el.closest('roc-block')) return;
        if (e.text != null && (!el.children.length || e.mixed) && el.textContent !== e.text) el.textContent = e.text;
        if (e.href != null && el.getAttribute('href') !== e.href) el.setAttribute('href', e.href);
        if (e.src != null && el.tagName === 'IMG' && el.getAttribute('src') !== e.src) { el.removeAttribute('srcset'); el.setAttribute('src', e.src); }
        if (e.alt != null && el.tagName === 'IMG' && el.getAttribute('alt') !== e.alt) el.setAttribute('alt', e.alt);
        if (e.html) {
          keep[e.id] = 1;
          var sib = e.pos === 'before' ? el.previousElementSibling : el.nextElementSibling;
          if (sib && sib.tagName === 'ROC-BLOCK' && sib.getAttribute('data-id') === e.id) {
            if (sib.getAttribute('data-v') !== String(e.html.length)) { sib.innerHTML = e.html; sib.setAttribute('data-v', String(e.html.length)); }
            return;
          }
          var b = document.createElement('roc-block');
          b.setAttribute('data-id', e.id); b.setAttribute('data-v', String(e.html.length));
          b.style.display = 'block';
          b.innerHTML = e.html;
          el.parentNode.insertBefore(b, e.pos === 'before' ? el : el.nextSibling);
        }
      });
    });
    // Blocks that no longer belong here (other page, removed change).
    [].forEach.call(document.querySelectorAll('roc-block[data-id]'), function (b) { if (!keep[b.getAttribute('data-id')]) b.remove(); });
    setTimeout(function () { busy = false; }, 0);
  }
  function soon() { if (busy) return; clearTimeout(timer); timer = setTimeout(apply, 40); }

  function start() {
    if (started) return;
    started = true;
    apply();
    new MutationObserver(soon).observe(document.body, { childList: true, subtree: true, characterData: true });
    ['pushState', 'replaceState'].forEach(function (m) {
      var orig = history[m];
      history[m] = function () { var r = orig.apply(this, arguments); soon(); return r; };
    });
    window.addEventListener('popstate', soon);
  }

  window.__rocEditsSet = function (list) { edits = list || []; if (started) apply(); else start(); };
  // Pages with React wait until it has taken over the page; pages without it start at once.
  var react = !!document.querySelector('script[type="module"][src*="/assets/"]');
  if (window.__ROC_HYDRATED) start();
  else if (!react) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start(); }
  else {
    window.addEventListener('roc:hydrated', start);
    setTimeout(start, 5000);       // in case React never finishes
  }
})();
