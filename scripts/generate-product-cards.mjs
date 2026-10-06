/**
 * Run On Console — one card image per product.
 *
 * Writes public/images/products/{slug}.svg for every product in the catalog:
 * product name, brand and category on the site's own colours. These replace the
 * six shared category photos until a real product photo is set in the CMS.
 * Deterministic, so re-running only changes files whose product changed.
 *
 *   node scripts/generate-product-cards.mjs
 */
import fs from 'fs';
import path from 'path';
import { ALL_PRODUCTS, PRODUCT_CATEGORIES } from '../src/data/productCatalog.js';

const OUT = path.resolve('public/images/products');

// One accent per category so cards in a grid are easy to tell apart.
const ACCENT = {
  keyboards: '#34d399', audio: '#22d3ee', mice: '#a3e635',
  speakers: '#fbbf24', monitors: '#60a5fa', gpu: '#f472b6',
};

// Simple line glyph per category (24x24 grid, drawn large and faint).
const GLYPH = {
  keyboards: '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/>',
  audio: '<path d="M3 14v-2a9 9 0 0 1 18 0v2"/><rect x="2" y="14" width="5" height="7" rx="2"/><rect x="17" y="14" width="5" height="7" rx="2"/>',
  mice: '<rect x="6" y="2" width="12" height="20" rx="6"/><path d="M12 6v4"/>',
  speakers: '<rect x="5" y="2" width="14" height="20" rx="2"/><circle cx="12" cy="14" r="4"/><path d="M12 6h.01"/>',
  monitors: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
  gpu: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="9" cy="12" r="3"/><circle cx="16" cy="12" r="2"/><path d="M6 18v3M10 18v3M14 18v3"/>',
};

const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[c]));

/** Greedy word wrap by an approximate character budget per line. */
function wrap(text, maxChars, maxLines) {
  const words = String(text).split(/\s+/).filter(Boolean);
  const lines = [];
  let cur = '';
  for (const w of words) {
    const next = cur ? cur + ' ' + w : w;
    if (next.length > maxChars && cur) { lines.push(cur); cur = w; } else cur = next;
  }
  if (cur) lines.push(cur);
  if (lines.length > maxLines) {
    const kept = lines.slice(0, maxLines);
    kept[maxLines - 1] = kept[maxLines - 1].replace(/\s*\S*$/, '') + '…';
    return kept;
  }
  return lines;
}

function card(p, catName) {
  const accent = ACCENT[p.categorySlug] || '#34d399';
  const glyph = GLYPH[p.categorySlug] || GLYPH.keyboards;
  const lines = wrap(p.title.replace(new RegExp('^' + (p.brand || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s+', 'i'), '') || p.title, 14, 3);
  // 320px of room for the name; bold sans glyphs average about 0.66em wide.
  const longest = Math.max(...lines.map((l) => l.length));
  const size = Math.max(20, Math.min(lines.length > 2 ? 34 : 40, Math.floor(320 / (longest * 0.66))));
  const startY = 150 - ((lines.length - 1) * size * 1.15) / 2;
  const nameTspans = lines.map((l, i) => `<tspan x="40" y="${Math.round(startY + i * size * 1.15)}">${esc(l)}</tspan>`).join('');
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="400" height="300" role="img" aria-label="${esc(p.title)}">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#022c22"/><stop offset="1" stop-color="#0f172a"/></linearGradient></defs>
<rect width="400" height="300" fill="url(#g)"/>
<g transform="translate(250 90) scale(6)" fill="none" stroke="${accent}" stroke-opacity=".16" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">${glyph}</g>
<rect x="40" y="40" width="44" height="4" rx="2" fill="${accent}"/>
<text x="40" y="74" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="15" font-weight="700" fill="${accent}" letter-spacing="1">${esc((p.brand || '').toUpperCase())}</text>
<text font-family="Outfit,Inter,Segoe UI,Arial,sans-serif" font-size="${size}" font-weight="800" fill="#f8fafc">${nameTspans}</text>
<text x="40" y="262" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="13" font-weight="600" fill="#94a3b8">${esc(catName)}</text>
<text x="360" y="262" text-anchor="end" font-family="Inter,Segoe UI,Arial,sans-serif" font-size="12" font-weight="800" fill="#10b981">RUN ON CONSOLE</text>
</svg>
`;
}

fs.mkdirSync(OUT, { recursive: true });
const names = Object.fromEntries(PRODUCT_CATEGORIES.map((c) => [c.slug, c.name]));
let written = 0;
for (const p of ALL_PRODUCTS) {
  const file = path.join(OUT, `${p.slug}.svg`);
  const svg = card(p, names[p.categorySlug] || p.category);
  if (!fs.existsSync(file) || fs.readFileSync(file, 'utf8') !== svg) { fs.writeFileSync(file, svg); written++; }
}
console.log(`Product cards: ${ALL_PRODUCTS.length} products, ${written} file(s) written to public/images/products/`);
