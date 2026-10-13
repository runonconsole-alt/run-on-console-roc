// One-off: give the games of public/roc-steam-games.json whose requirements name no card or
// processor an estimated score from their own words (see estimate() in steam-requirements.mjs).
import fs from 'node:fs';
import { estimate } from './steam-requirements.mjs';
const F = 'public/roc-steam-games.json';
const d = JSON.parse(fs.readFileSync(F, 'utf8'));
let n = 0;
for (const g of d.games) {
  if (!g.req) continue;
  const before = JSON.stringify(g.req);
  g.req.min = estimate(g.min, g.req.min);
  g.req.rec = estimate(g.rec || g.min, g.req.rec || { ...g.req.min });
  for (const k of ['cpu', 'gpu', 'ram']) if (g.req.rec[k] == null) g.req.rec[k] = g.req.min[k];
  if (JSON.stringify(g.req) !== before) n++;
}
fs.writeFileSync(F, JSON.stringify(d));
const rated = d.games.filter((g) => g.req && g.req.min.gpu && g.req.min.cpu).length;
console.log(`estimated parts for ${n} game(s); ${rated} of ${d.games.length} can be checked automatically`);
