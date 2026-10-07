/**
 * Run On Console — games list for the header search box.
 *
 * Writes dist/roc-games.json ([{t: title, g: genre, u: url}]). Products and blogs
 * come live from the server (/products/?json=1, /blogs/?json=1); games are part of
 * the website build, so their list is written here.
 */
import fs from 'fs';
import path from 'path';
import { GAME_COMPATIBILITY_DATA } from '../src/data/gameCompatibilityData.js';

const games = GAME_COMPATIBILITY_DATA.map((g) => ({
  t: g.gameTitle,
  g: g.genre || '',
  u: `/compatibility/?q=${encodeURIComponent(g.gameTitle)}`,
}));
// Popular PC games with official Steam requirements (scripts/fetch-steam-games.mjs).
const steamFile = path.resolve('public/roc-steam-games.json');
if (fs.existsSync(steamFile)) {
  const known = new Set(games.map((g) => g.t.toLowerCase()));
  for (const g of JSON.parse(fs.readFileSync(steamFile, 'utf8')).games || []) {
    if (known.has(g.t.toLowerCase())) continue;
    games.push({ t: g.t, g: [g.g, g.y].filter(Boolean).join(' · '), u: `/compatibility/?steam=${g.appid}` });
  }
}
fs.writeFileSync(path.resolve('dist/roc-games.json'), JSON.stringify(games));
console.log(`Search index: ${games.length} games written to dist/roc-games.json`);
