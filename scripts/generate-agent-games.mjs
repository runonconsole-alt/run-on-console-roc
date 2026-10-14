/**
 * The games of the compatibility page's own list (src/data/gameCompatibilityData.js) for ROC, the
 * site assistant (api/v1/agent-lib.php), in the same shape as roc-steam-games.json:
 *   public/roc-agent-games.json  {games: [{t, keys, min, rec, req, u}]}
 * keys: the names people type ("gta 5", "valorant", "counter strike 2"). Run by npm run build.
 */
import fs from 'fs';
import { GAME_COMPATIBILITY_DATA } from '../src/data/gameCompatibilityData.js';

const ERA = {
  classic: { min: { cpu: 2, gpu: 1.8, ram: 4 }, rec: { cpu: 4.5, gpu: 1.8, ram: 8 } },
  modern: { min: { cpu: 4.5, gpu: 4.5, ram: 8 }, rec: { cpu: 7, gpu: 7, ram: 16 } },
  upcoming: { min: { cpu: 7, gpu: 7, ram: 16 }, rec: { cpu: 8.5, gpu: 8.5, ram: 32 } },
};
const STOP = new Set(['gta online', 'online', 'original', 'definitive', 'modded', 'special edition', 'samp', 'java', 'bedrock', 'uefn', 'directors cut']);
const norm = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
const games = GAME_COMPATIBILITY_DATA.filter((g) => g.gameTitle).map((g) => {
  const keys = new Set([norm(g.gameTitle)]);
  for (const part of g.gameTitle.split(/\s*(?:&|\(|\)|:|,)\s*/)) { const k = norm(part); if (k.length >= 4 && !STOP.has(k)) keys.add(k); }
  return {
    t: g.gameTitle, keys: [...keys], req: g.req || ERA[g.era] || ERA.modern,
    pc: (g.platforms || []).some((x) => /PC|Windows|Steam/i.test(x)),
    min: { gpu: g.minSpecs || '' }, rec: { gpu: g.recommendedSpecs || '' },
    u: '/compatibility/?q=' + encodeURIComponent(g.gameTitle),
  };
});
fs.writeFileSync('public/roc-agent-games.json', JSON.stringify({ source: 'src/data/gameCompatibilityData.js', games }));
console.log('Agent games: ' + games.length + ' written to public/roc-agent-games.json');
