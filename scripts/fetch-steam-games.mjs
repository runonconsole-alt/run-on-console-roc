/**
 * Run On Console — popular PC games of the last five years, with their official
 * Steam system requirements, for the compatibility checker.
 *
 *   node scripts/fetch-steam-games.mjs            fetch (resumes from the cache)
 *   node scripts/fetch-steam-games.mjs --parse    only rebuild the output from the cache
 *   --target=2000 --from=2013 --pages=12            more games, older ones too, more SteamSpy pages
 *
 * 1. Candidates: SteamSpy's list of Steam games by owners (steamspy.com/api.php?request=all),
 *    most-reviewed first.
 * 2. For each: the Steam store's own data (store.steampowered.com/api/appdetails):
 *    release date and the "System Requirements" text from the game's store page.
 * 3. Kept: games released from 2021 on, with PC requirements, without adult-only content,
 *    until there are 500.
 *
 * Requests are spaced out to respect both services' rate limits (SteamSpy: 1 "all" page a
 * minute; Steam store: a few hundred requests per 5 minutes). Raw answers are cached in
 * scripts/data/steam-cache.json so a stopped run continues where it left off.
 * Output: public/roc-steam-games.json (see scripts/steam-requirements.mjs for the parsing).
 */
import fs from 'fs';
import path from 'path';
import { buildGame } from './steam-requirements.mjs';

const CACHE = path.resolve('scripts/data/steam-cache.json');
const OUT = path.resolve('public/roc-steam-games.json');
const arg = (k, d) => { const a = process.argv.find((x) => x.startsWith('--' + k + '=')); return a ? +a.split('=')[1] : d; };
const TARGET = arg('target', 500);
const FROM_YEAR = arg('from', 2021);
const SPY_PAGES = arg('pages', 5);
// Steam numbers apps in the order they were created: below this number they are
// almost all released before 2021 (Helldivers 2, 553850, is one of the lowest recent ones).
const MIN_APPID = FROM_YEAR >= 2021 ? 500000 : 0;
const ADULT_DESCRIPTORS = [3, 4];   // Steam: adult-only sexual content, frequent nudity or sexual content

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const cache = fs.existsSync(CACHE) ? JSON.parse(fs.readFileSync(CACHE, 'utf8')) : { spy: {}, apps: {} };
const save = () => { fs.mkdirSync(path.dirname(CACHE), { recursive: true }); fs.writeFileSync(CACHE, JSON.stringify(cache)); };

async function getJson(url, tries = 5) {
  for (let i = 0; i < tries; i++) {
    try {
      const r = await fetch(url, { headers: { 'User-Agent': 'RunOnConsole-compatibility-list/1.0 (+https://runonconsole.com)' } });
      if (r.status === 429 || r.status === 403) { console.log(`  rate limited (${r.status}), waiting 90s`); await sleep(90000); continue; }
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return await r.json();
    } catch (e) {
      console.log(`  ${e.message}, retry in 20s`);
      await sleep(20000);
    }
  }
  return null;
}

function yearOf(app) {
  const m = /(\d{4})/.exec(app?.release_date?.date || '');
  return m ? +m[1] : 0;
}

function keep(app) {
  if (!app || app.type !== 'game') return false;
  if (app.release_date?.coming_soon) return false;
  const req = app.pc_requirements;
  if (!req || Array.isArray(req) || !req.minimum) return false;
  if ((app.content_descriptors?.ids || []).some((id) => ADULT_DESCRIPTORS.includes(id))) return false;
  return true;
}

function writeOutput() {
  const kept = Object.values(cache.apps).filter((a) => a && a._keep && yearOf(a) >= FROM_YEAR).sort((a, b) => b._positive - a._positive).slice(0, TARGET);
  const games = kept.map((a) => buildGame(a, yearOf(a)));
  fs.writeFileSync(OUT, JSON.stringify({ source: 'Steam store system requirements (store.steampowered.com), list by SteamSpy popularity', updated: new Date().toISOString().slice(0, 10), games }));
  const rated = games.filter((g) => g.req && g.req.min.gpu && g.req.min.cpu).length;
  console.log(`Wrote ${games.length} games to ${path.relative(process.cwd(), OUT)} (${rated} with CPU and GPU recognised for the automatic check)`);
}

if (process.argv.includes('--parse')) { writeOutput(); process.exit(0); }

// 1. candidates
for (let page = 0; page < SPY_PAGES; page++) {
  if (cache.spy[page]) continue;
  console.log(`SteamSpy page ${page + 1}/${SPY_PAGES}`);
  const data = await getJson(`https://steamspy.com/api.php?request=all&page=${page}`);
  if (!data) break;
  cache.spy[page] = Object.values(data).map((a) => ({ appid: a.appid, name: a.name, positive: a.positive || 0 }));
  save();
  if (page < SPY_PAGES - 1) await sleep(62000);
}
const seen = new Set();
const candidates = Object.values(cache.spy).flat()
  .filter((a) => a && a.appid >= MIN_APPID && !seen.has(a.appid) && seen.add(a.appid))
  .sort((a, b) => b.positive - a.positive);
console.log(`${candidates.length} candidates`);
// --retry=N: ask Steam again about the N most popular candidates that an earlier run skipped
// (a failed or rate-limited request is cached as "not kept": CS2, Cyberpunk 2077, PUBG ... were lost that way).
const RETRY = arg('retry', 0);
if (RETRY) {
  let n = 0;
  for (const c of candidates.slice(0, RETRY)) if (cache.apps[c.appid] === null) { delete cache.apps[c.appid]; n++; }
  console.log(`retrying ${n} skipped games`);
}

// 2. store data, most-reviewed first, until there are enough recent games
const isKept = (a) => a && a._keep && yearOf(a) >= FROM_YEAR;   // the year is checked here, so a later run can use older games
let kept = Object.values(cache.apps).filter(isKept).length;
for (const c of candidates) {
  if (kept >= TARGET) break;
  if (cache.apps[c.appid] !== undefined) continue;
  const d = await getJson(`https://store.steampowered.com/api/appdetails?appids=${c.appid}&cc=us&l=english`);
  const app = d?.[c.appid]?.success ? d[c.appid].data : null;
  if (app && keep(app)) {
    cache.apps[c.appid] = {
      _keep: true, _positive: c.positive, steam_appid: app.steam_appid, name: app.name, release_date: app.release_date,
      genres: app.genres, pc_requirements: app.pc_requirements, platforms: app.platforms,
    };
    if (yearOf(app) >= FROM_YEAR) kept++;
    console.log(`  + ${app.name} (${yearOf(app)})  [${kept}/${TARGET}]`);
  } else {
    cache.apps[c.appid] = null;   // checked, not kept
    console.log(`  - ${c.name}: ${app ? 'not kept (' + (app.type !== 'game' ? app.type : 'no PC requirements or adult') + ')' : 'no answer'}`);
  }
  if (Object.keys(cache.apps).length % 10 === 0) save();
  await sleep(1600);
}
save();
writeOutput();
