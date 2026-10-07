/**
 * Run On Console — read a Steam "System Requirements" block and score it on the
 * compatibility checker's scale (the same scale as its CPU / GPU options):
 *
 *   GPU  1.8 integrated · 4.5 GTX 1650 / 1060 / RX 580 · 7 RTX 3060 / 4060 / RX 6600 XT
 *        8.5 RTX 4070 / 3080 / RX 7800 XT · 10 RTX 4080 / 4090 / RX 7900
 *   CPU  2 dual-core / Core 2 · 4.5 older quad-core (i5-4460, i7-4790) · 7 i5 10th-13th gen /
 *        Ryzen 5 5600X · 8.5 i7 12th gen+ / Ryzen 7 5800X · 10 i9 / Ryzen 9 / 7800X3D
 *   RAM  in GB
 *
 * A part that cannot be recognised stays null: the checker then shows the official text
 * and does not guess. When a line lists several parts ("i5-4460 / Ryzen 3 1200") the
 * strongest one counts, so the check never says a game runs on less than it may need.
 */

const decode = (s) => String(s || '')
  .replace(/<br\s*\/?>/gi, '\n').replace(/<\/li>/gi, '\n').replace(/<[^>]+>/g, '')
  .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#39;|&apos;/g, "'")
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/®|™|\(R\)|\(TM\)/gi, '');

/** {os, cpu, ram, gpu, storage} from the HTML of one block (minimum or recommended). */
export function readBlock(html) {
  const text = decode(html);
  const field = (label) => {
    const m = new RegExp(`(?:^|\\n)\\s*(?:${label})\\s*:\\s*([^\\n]+)`, 'i').exec(text);
    return m ? m[1].trim().replace(/\s+/g, ' ').slice(0, 220) : '';
  };
  return {
    os: field('OS'),
    cpu: field('Processor'),
    ram: field('Memory'),
    gpu: field('Graphics|Video Card|Video'),
    storage: field('Storage|Hard Drive|Hard Disk Space'),
  };
}

const max = (xs) => (xs.length ? Math.max(...xs) : null);

export function gpuScore(s) {
  // "Nvidia 2060 Super", "GeForce 1080 Ti": the model number without RTX / GTX in front.
  s = String(s || '').toLowerCase()
    .replace(/(?:nvidia|geforce)\s+(?=(?:20|30|40|50)\d0\b)/g, ' rtx ')
    .replace(/(?:nvidia|geforce)\s+(?=(?:9[5-8]0|10[5-8]0|16[3-6]0)\b)/g, ' gtx ')
    .replace(/geforce|nvidia|amd|radeon|intel|graphics card|®|™/g, ' ');
  const found = [];
  const add = (v) => found.push(v);
  let m;
  const rtx = /rtx\s*(\d{4})\s*(ti|super)?/g;
  const RTX = { 2050: 4.5, 2060: 6, 2070: 7, 2080: 8, 3050: 4.5, 3060: 7, 3070: 8, 3080: 8.5, 3090: 10,
                4050: 6, 4060: 7, 4070: 8.5, 4080: 10, 4090: 10, 5050: 6.5, 5060: 8, 5070: 9, 5080: 10, 5090: 10 };
  const RTX_TI = { 2080: 8.5, 3060: 8, 3070: 8.5, 3080: 9, 4060: 8, 4070: 9, 4080: 10, 5060: 8.5, 5070: 9.5, 2060: 6.5, 2070: 7 };
  while ((m = rtx.exec(s))) { const n = +m[1]; if (RTX[n] !== undefined) add(m[2] ? (RTX_TI[n] ?? RTX[n]) : RTX[n]); }
  const gtx = /gtx\s*(\d{3,4})\s*(ti|super)?/g;
  const GTX = { 650: 2, 660: 2.5, 670: 3, 680: 3, 750: 2.5, 760: 3, 770: 3, 780: 3.5, 950: 3, 960: 3.5, 970: 4, 980: 4.5,
                1050: 3, 1060: 4.5, 1070: 5, 1080: 6, 1630: 3, 1650: 4.5, 1660: 4.5 };
  const GTX_TI = { 750: 2.5, 780: 4, 980: 5, 1050: 3.5, 1070: 5.5, 1080: 7, 1650: 4.5, 1660: 5 };
  while ((m = gtx.exec(s))) { const n = +m[1]; if (GTX[n] !== undefined) add(m[2] ? (GTX_TI[n] ?? GTX[n]) : GTX[n]); }
  if (/\bgt\s*(1030|730|710)\b/.test(s)) add(1.8);
  if (/titan\s*x/.test(s)) add(5);
  const rx = /\brx\s*(\d{3,4})\s*(xtx|xt|gre)?/g;
  const RX = { 460: 2.5, 470: 4, 480: 4, 550: 2, 560: 2.5, 570: 4, 580: 4.5, 590: 4.5, 5300: 3.5, 5500: 4.5, 5600: 6, 5700: 6,
               6400: 3, 6500: 3.5, 6600: 6.5, 6650: 7, 6700: 7.5, 6750: 8, 6800: 8.5, 6900: 9.5, 6950: 9.5,
               7600: 7, 7700: 8.5, 7800: 8.5, 7900: 10, 9060: 8, 9070: 9.5 };
  const RX_XT = { 5500: 4.5, 5600: 6, 5700: 6.5, 6500: 3.5, 6600: 7, 6650: 7, 6700: 8, 6750: 8, 6800: 9, 6900: 9.5, 7600: 7, 7700: 8.5, 7800: 8.5, 7900: 10, 9060: 8, 9070: 10 };
  while ((m = rx.exec(s))) { const n = +m[1]; if (RX[n] !== undefined) add(m[2] === 'gre' ? (n === 7900 ? 9 : RX[n]) : m[2] ? (RX_XT[n] ?? RX[n]) : RX[n]); }
  if (/vega\s*56/.test(s)) add(5);
  if (/vega\s*64/.test(s)) add(5.5);
  if (/r9\s*(290|390|fury)/.test(s)) add(4);
  if (/r9\s*(270|280|285|380)/.test(s)) add(3);
  if (/\bhd\s*7\d{3}\b|r7\s*\d{3}/.test(s)) add(2);
  const arc = /arc\s*([ab])\s*(\d{3})/g;
  while ((m = arc.exec(s))) {
    const k = m[1] + m[2];
    const ARC = { a310: 2.5, a380: 3.5, a580: 6, a750: 6.5, a770: 7, b570: 7, b580: 7.5 };
    if (ARC[k] !== undefined) add(ARC[k]);
  }
  if (!found.length && /\b(uhd|iris|hd graphics|integrated|vega\s*(3|6|7|8|10|11)\b)/.test(s)) add(1.8);
  return max(found);
}

export function cpuScore(s) {
  s = String(s || '').toLowerCase().replace(/®|™/g, '');
  const found = [];
  let m;
  const core = /\bi([3579])[\s-]*(\d{4,5})[a-z]*/g;
  while ((m = core.exec(s))) {
    const tier = +m[1], num = m[2];
    const gen = num.length === 5 ? +num.slice(0, 2) : +num[0];
    let v;
    if (tier === 3) v = gen >= 12 ? 6 : gen >= 10 ? 5 : gen >= 8 ? 4.5 : 3;
    else if (tier === 5) v = gen >= 10 ? 7 : gen >= 8 ? 6 : gen >= 6 ? 5 : gen >= 4 ? 4.5 : 3.5;
    else if (tier === 7) v = gen >= 12 ? 8.5 : gen >= 10 ? 8 : gen >= 8 ? 7 : gen >= 6 ? 6 : gen >= 4 ? 4.5 : 4;
    else v = gen >= 12 ? 10 : 8.5;
    found.push(v);
  }
  const ultra = /core\s*ultra\s*([579])/g;
  while ((m = ultra.exec(s))) found.push({ 5: 7, 7: 8.5, 9: 10 }[m[1]]);
  const ryzen = /(?:ryzen\s*|\br)([3579])\s*[\s-]*(\d{4})\s*(x3d)?/g;   // also "AMD r5 3600"
  while ((m = ryzen.exec(s))) {
    const tier = +m[1], series = +m[2][0], x3d = !!m[3];
    let v;
    if (tier === 3) v = series >= 7 ? 6 : series >= 4 ? 5 : series >= 3 ? 4.5 : 3.5;
    else if (tier === 5) v = series >= 7 ? 8 : series >= 4 ? 7 : series >= 3 ? 6 : series >= 2 ? 5 : 4.5;
    else if (tier === 7) v = x3d && series >= 5 ? 10 : series >= 7 ? 9 : series >= 4 ? 8.5 : series >= 3 ? 7 : series >= 2 ? 5.5 : 5;
    else v = series >= 7 ? 10 : series >= 5 ? 9.5 : 8.5;
    found.push(v);
  }
  if (/\bfx[\s-]*\d{4}/.test(s)) found.push(3.5);
  if (/phenom|athlon/.test(s)) found.push(2.5);
  if (/core\s*2|pentium|celeron|atom/.test(s)) found.push(2);
  if (!found.length && /quad[\s-]*core|4[\s-]*core/.test(s)) found.push(3);
  if (!found.length && /dual[\s-]*core|2[\s-]*core/.test(s)) found.push(2);
  return max(found);
}

export function ramGb(s) {
  const m = /(\d+(?:\.\d+)?)\s*(gb|mb)/i.exec(String(s || ''));
  if (!m) return null;
  const v = m[2].toLowerCase() === 'mb' ? +m[1] / 1024 : +m[1];
  return Math.round(v * 10) / 10;
}

const score = (b) => (b ? { cpu: cpuScore(b.cpu), gpu: gpuScore(b.gpu), ram: ramGb(b.ram) } : null);

/** Entry for public/roc-steam-games.json. */
export function buildGame(app, year) {
  const min = readBlock(app.pc_requirements.minimum);
  const recHtml = app.pc_requirements.recommended;
  const rec = recHtml ? readBlock(recHtml) : null;
  const sMin = score(min);
  const sRec = score(rec) || sMin;
  // A recommended part that was not recognised falls back to the minimum.
  for (const k of ['cpu', 'gpu', 'ram']) if (sRec[k] == null) sRec[k] = sMin[k];
  return {
    id: `steam-${app.steam_appid}`,
    appid: app.steam_appid,
    t: app.name,
    y: year,
    g: (app.genres || []).map((x) => x.description).slice(0, 3).join(', '),
    u: `https://store.steampowered.com/app/${app.steam_appid}/`,
    min,
    rec,
    req: { min: sMin, rec: sRec },
  };
}
