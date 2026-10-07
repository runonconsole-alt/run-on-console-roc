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
fs.writeFileSync(path.resolve('dist/roc-games.json'), JSON.stringify(games));
console.log(`Search index: ${games.length} games written to dist/roc-games.json`);
