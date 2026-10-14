/**
 * Run On Console — ROC's gaming knowledge base: public/roc-kb.json.
 *
 *   node scripts/build-roc-kb.mjs
 *
 * Sources (free to reuse; ROC names them in its answers when it uses them):
 *   - Wikidata (CC0): notable video games (year, developers, publishers, genres, platforms, series),
 *     video game consoles (maker, year) and esports tournaments with their winners.
 *   - Wikipedia (CC BY-SA): the champion of the big esports events from each article's infobox
 *     (Valorant Champions, League of Legends Worlds and MSI, The International, Esports World Cup,
 *     MLBB M-series, PUBG Mobile, Fortnite and Overwatch world events).
 * ROC (api/v1/agent-lib.php) picks the entries a question names and gives them to the model as facts.
 * Requests are few and spaced out (Wikimedia asks for a User-Agent with a contact).
 */
import fs from 'fs';

const UA = 'RunOnConsole-KB/1.0 (https://runonconsole.com; contact@runonconsole.com)';
const OUT = 'public/roc-kb.json';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const YEAR = new Date().getFullYear();

async function sparql(q) {
  for (let i = 0; i < 4; i++) {
    const r = await fetch('https://query.wikidata.org/sparql?query=' + encodeURIComponent(q), { headers: { 'User-Agent': UA, Accept: 'application/sparql-results+json' } });
    if (r.ok) return (await r.json()).results.bindings;
    console.log(`  Wikidata ${r.status}, waiting`); await sleep(15000 * (i + 1));
  }
  throw new Error('Wikidata did not answer');
}
const v = (b, k) => (b[k] ? b[k].value : '');
const list = (s, n) => [...new Set(String(s || '').split('|').map((x) => x.trim()).filter((x) => x && !/^Q\d+$/.test(x)))].slice(0, n);

/* ------------------------------------------------------------------ games */
async function games() {
  console.log('Games: notable titles');
  const ids = (await sparql('SELECT ?g ?s WHERE { ?g wdt:P31 wd:Q7889; wikibase:sitelinks ?s. FILTER(?s >= 14) }'))
    .map((b) => [v(b, 'g').split('/').pop(), +v(b, 's')]).sort((a, b) => b[1] - a[1]).slice(0, 3500).map((x) => x[0]);
  console.log(`  ${ids.length} games`);
  const out = [];
  for (let i = 0; i < ids.length; i += 120) {
    const vals = ids.slice(i, i + 120).map((q) => 'wd:' + q).join(' ');
    const rows = await sparql(`SELECT ?g ?name (MIN(YEAR(?d)) AS ?y)
      (GROUP_CONCAT(DISTINCT ?devL; separator="|") AS ?dev) (GROUP_CONCAT(DISTINCT ?pubL; separator="|") AS ?pub)
      (GROUP_CONCAT(DISTINCT ?genL; separator="|") AS ?gen) (GROUP_CONCAT(DISTINCT ?plL; separator="|") AS ?plat)
      (GROUP_CONCAT(DISTINCT ?serL; separator="|") AS ?ser) WHERE {
      VALUES ?g { ${vals} }
      ?g rdfs:label ?name. FILTER(LANG(?name) = "en")
      OPTIONAL { ?g wdt:P577 ?d }
      OPTIONAL { ?g wdt:P178 ?dv. ?dv rdfs:label ?devL. FILTER(LANG(?devL) = "en") }
      OPTIONAL { ?g wdt:P123 ?pb. ?pb rdfs:label ?pubL. FILTER(LANG(?pubL) = "en") }
      OPTIONAL { ?g wdt:P136 ?gn. ?gn rdfs:label ?genL. FILTER(LANG(?genL) = "en") }
      OPTIONAL { ?g wdt:P400 ?pl. ?pl rdfs:label ?plL. FILTER(LANG(?plL) = "en") }
      OPTIONAL { ?g wdt:P179 ?sr. ?sr rdfs:label ?serL. FILTER(LANG(?serL) = "en") }
    } GROUP BY ?g ?name`);
    for (const b of rows) out.push({ n: v(b, 'name'), y: +v(b, 'y') || null, dev: list(v(b, 'dev'), 4), pub: list(v(b, 'pub'), 3),
      genre: list(v(b, 'gen'), 3), plat: list(v(b, 'plat'), 8), series: list(v(b, 'ser'), 1)[0] || null });
    process.stdout.write(`  ${Math.min(i + 120, ids.length)}/${ids.length}\r`);
    await sleep(1500);
  }
  console.log();
  return out.filter((g) => g.n && !/^Q\d+$/.test(g.n));
}

/* --------------------------------------------------------------- consoles */
async function consoles() {
  console.log('Consoles');
  const rows = await sparql(`SELECT ?c ?name (MIN(YEAR(?d)) AS ?y) (GROUP_CONCAT(DISTINCT ?mkL; separator="|") AS ?mk) (MAX(?s) AS ?sl) WHERE {
    VALUES ?kind { wd:Q8076 wd:Q17589470 wd:Q941818 wd:Q27496624 wd:Q115187350 wd:Q67387549 }
    ?c wdt:P31 ?kind; wikibase:sitelinks ?s. FILTER(?s >= 8)
    ?c rdfs:label ?name. FILTER(LANG(?name) = "en")
    OPTIONAL { ?c wdt:P571|wdt:P577 ?d }
    OPTIONAL { ?c wdt:P176 ?m. ?m rdfs:label ?mkL. FILTER(LANG(?mkL) = "en") }
  } GROUP BY ?c ?name`);
  return rows.map((b) => ({ n: v(b, 'name'), y: +v(b, 'y') || null, maker: list(v(b, 'mk'), 2) })).filter((c) => c.n);
}

/* ---------------------------------------------------------------- esports */
async function esportsWikidata() {
  console.log('Esports: Wikidata');
  const rows = await sparql(`SELECT ?t ?name ?gameL ?winL ?y WHERE {
    ?t wdt:P31/wdt:P279* wd:Q48004378; wdt:P1346 ?win.
    ?t rdfs:label ?name. FILTER(LANG(?name) = "en")
    ?win rdfs:label ?winL. FILTER(LANG(?winL) = "en")
    OPTIONAL { ?t wdt:P585 ?d } OPTIONAL { ?t wdt:P580 ?d2 } BIND(YEAR(COALESCE(?d, ?d2)) AS ?y)
    OPTIONAL { ?t wdt:P641 ?game. ?game rdfs:label ?gameL. FILTER(LANG(?gameL) = "en") }
  }`);
  return rows.map((b) => ({ t: v(b, 'name'), y: +v(b, 'y') || null, game: /esports|^$/i.test(v(b, 'gameL')) ? null : v(b, 'gameL'), winner: v(b, 'winL'), src: 'Wikidata' }));
}

/** Big events: the champion from the infobox of each year's Wikipedia article. */
async function esportsWikipedia() {
  console.log('Esports: Wikipedia infoboxes');
  const titles = [];
  for (let y = 2011; y <= YEAR; y++) {
    titles.push(`${y} League of Legends World Championship`, `${y} Mid-Season Invitational`, `The International ${y}`, `Valorant Champions ${y}`, `${y} Valorant Champions`,
      `Esports World Cup ${y}`, `PUBG Mobile Global Championship ${y}`, `${y} PUBG Mobile Global Championship`, `Free Fire World Series ${y}`,
      `${y} Overwatch World Cup`, `Overwatch World Cup ${y}`, `${y} Fortnite World Cup`, `Fortnite World Cup ${y}`, `${y} Rocket League Championship Series`,
      `Valorant Masters Toronto ${y}`, `Valorant Masters Shanghai ${y}`, `Valorant Masters Madrid ${y}`, `Valorant Masters Bangkok ${y}`, `Valorant Masters Tokyo ${y}`);
  }
  for (let n = 1; n <= 9; n++) titles.push(`M${n} World Championship`);
  const out = [];
  for (let i = 0; i < titles.length; i += 45) {
    const url = 'https://en.wikipedia.org/w/api.php?action=query&format=json&formatversion=2&redirects=1&prop=revisions&rvprop=content&rvslots=main&titles=' +
      encodeURIComponent(titles.slice(i, i + 45).join('|'));
    const r = await fetch(url, { headers: { 'User-Agent': UA } });
    const j = r.ok ? await r.json() : {};
    for (const p of (j.query?.pages || [])) {
      const text = p.revisions?.[0]?.slots?.main?.content;
      if (!text) continue;
      // The value up to the next field ("| champion = X | runner-up1 = Y" on one line too).
      const field = (k) => { const m = new RegExp(`\\|\\s*(?:${k})\\s*=\\s*([^\\n]*?)(?=\\n|\\|\\s*[a-z_ -]+\\d*\\s*=)`, 'i').exec(text); return m ? m[1] : ''; };
      const clean = (s) => s.replace(/<ref[^>]*\/>|<ref[\s\S]*?<\/ref>/g, '').replace(/\{\{(?:flagicon|flag|flagcountry|esportsflag)[^}]*\}\}\s*/gi, '')
        .replace(/\[\[(?:[^|\]]*\|)?([^\]]+)\]\]/g, '$1').replace(/\{\{[^}]*\}\}|'''?|<[^>]+>/g, '').replace(/\s+/g, ' ').trim();
      const winner = clean(field('champion|champions|winner|winners|champ'));
      if (!winner || /^[|=]/.test(winner)) continue;   // not played yet
      const runner = clean(field('runner-up|runnerup|runner_up|second'));
      const ym = /(\d{4})/.exec(p.title);
      out.push({ t: p.title, y: ym ? +ym[1] : null, game: null, winner: winner.slice(0, 80), runner_up: runner.slice(0, 80) || null,
                 location: clean(field('location|host|city')).slice(0, 80) || null, src: 'Wikipedia' });
    }
    await sleep(1200);
  }
  return out;
}

const kb = { sources: 'Wikidata (CC0) and Wikipedia (CC BY-SA)', updated: new Date().toISOString().slice(0, 10) };
kb.esports = await esportsWikipedia();
const wd = await esportsWikidata();
const have = new Set(kb.esports.map((e) => e.t.toLowerCase()));
for (const e of wd) if (!have.has(e.t.toLowerCase())) kb.esports.push(e);
kb.consoles = await consoles();
kb.games = await games();
fs.writeFileSync(OUT, JSON.stringify(kb));
console.log(`Wrote ${OUT}: ${kb.games.length} games, ${kb.consoles.length} consoles, ${kb.esports.length} esports results (${(fs.statSync(OUT).size / 1024).toFixed(0)} KB)`);
