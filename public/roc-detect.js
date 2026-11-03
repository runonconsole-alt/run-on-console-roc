/* Run On Console — "Detect my graphics card" (Deploy 153).
   window.rocDetectGpu() -> Promise<{name, score, laptop} | null>
   The browser tells the page its graphics card's name through WebGL; /api/v1/gpu-detect.php scores it with the
   checker's rules (nothing is stored). Processor and RAM cannot be read reliably by a browser. Loaded only when a
   visitor presses the button (game pages, /compatibility/). */
(function () {
  if (window.rocDetectGpu) return;
  function renderer() {
    try {
      var c = document.createElement('canvas');
      var gl = c.getContext('webgl') || c.getContext('experimental-webgl');
      if (!gl) return '';
      var ext = gl.getExtension('WEBGL_debug_renderer_info');
      var r = ext ? gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) : gl.getParameter(gl.RENDERER);
      return String(r || '');
    } catch (e) { return ''; }
  }
  window.rocDetectGpu = function () {
    var r = renderer();
    if (!r || /^(webkit webgl|mozilla|apple gpu)$/i.test(r)) return Promise.resolve(null);
    return fetch('/api/v1/gpu-detect.php?r=' + encodeURIComponent(r.slice(0, 200)))
      .then(function (x) { return x.json(); })
      .then(function (d) { return d && d.success ? { name: d.name, score: d.score, laptop: !!d.laptop } : null; })
      .catch(function () { return null; });
  };
})();
