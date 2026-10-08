/**
 * Run On Console — one card image per product.
 *
 * Writes public/images/products/{slug}.webp for every product in the catalog
 * (and share/{slug}.jpg, 1200 x 630, for link previews):
 * product name and brand on the site's own colours. These replace the
 * six shared category photos until a real product photo is set in the CMS.
 * Deterministic, so re-running only changes files whose product changed.
 *
 * The drawing is done by scripts/render-product-cards.php (PHP with GD), so the
 * cards are plain WebP images like every other image on the site.
 *
 *   node scripts/generate-product-cards.mjs          (PHP_BIN=path/to/php if php is not on PATH)
 */
import fs from 'fs';
import os from 'os';
import path from 'path';
import { execFileSync } from 'child_process';
import { ALL_PRODUCTS } from '../src/data/productCatalog.js';

const OUT = path.resolve('public/images/products');
const SHARE = path.resolve('public/images/products/share');
const list = ALL_PRODUCTS.map((p) => ({ slug: p.slug, title: p.title, brand: p.brand || '', categorySlug: p.categorySlug }));
const tmp = path.join(os.tmpdir(), 'roc-product-cards.json');
fs.writeFileSync(tmp, JSON.stringify(list));
try {
  process.stdout.write(execFileSync(process.env.PHP_BIN || 'php',
    [path.resolve('scripts/render-product-cards.php'), tmp, OUT, SHARE], { encoding: 'utf8' }));
} finally {
  fs.rmSync(tmp, { force: true });
}
