<?php
/**
 * Run On Console — ROC, the site assistant (Deploy 55).
 *
 * Answers from the site's own data only:
 *   - "Can I run it": the 500 Steam games of the checker (roc-steam-games.json, official requirements
 *     scored on the same scale as /compatibility/) against the graphics card, processor and memory the
 *     visitor names (same scoring as scripts/steam-requirements.mjs and GameCompatibilityView.jsx);
 *   - gear finder: published products of the catalog (links to our product pages, never prices);
 *   - the wording comes from Groq (the free key of the blog writer, on a different model so the writers
 *     keep their own limit), told to use only these facts. Without Groq, or over the daily limit, ROC
 *     answers from the facts with fixed sentences.
 * Chats are saved without personal details (emails, phone numbers, ID numbers and names are removed).
 */
if (defined('ROC_AGENT_LIB')) return;
define('ROC_AGENT_LIB', 1);

// Preferred models, best first; only the ones the Groq key can use now are tried (rocAgentModels).
const ROC_AGENT_MODELS = ['llama-3.3-70b-versatile', 'openai/gpt-oss-120b', 'moonshotai/kimi-k2-instruct', 'qwen/qwen3-32b', 'openai/gpt-oss-20b', 'llama-3.1-8b-instant'];
const ROC_AGENT_WEB = 'groq/compound-mini';                                  // Groq model with web search
const ROC_AGENT_VISION = 'meta-llama/llama-4-scout-17b-16e-instruct';      // reads pictures

/**
 * The chat, web search and picture models this key can use, from Groq's model list (kept 12 hours in
 * agent_models). Groq retires models now and then; a fixed name would make ROC fall back to its fixed
 * sentences.
 */
function rocAgentModels(PDO $pdo, string $key, bool $fresh = false): array {
    $c = rocBlogGet($pdo, 'agent_models', []);
    if (!$fresh && is_array($c) && !empty($c['chat']) && time() - (int)($c['t'] ?? 0) < 43200) return $c;
    $r = rocGroqCall($key, 'GET', '/models', null, 20);
    $have = $r['ok'] ? array_values(array_filter(array_map('strval', array_column((array)($r['data']['data'] ?? []), 'id')))) : [];
    if (!$have) return ['chat' => ROC_AGENT_MODELS, 'web' => [ROC_AGENT_WEB], 'vision' => [ROC_AGENT_VISION], 't' => 0, 'error' => $r['error'] ?? 'no models'];
    $chat = array_values(array_intersect(ROC_AGENT_MODELS, $have));
    foreach ($have as $m) if (!in_array($m, $chat, true) && preg_match('/llama|gpt-oss|qwen|kimi|gemma|mistral|deepseek/i', $m) && !preg_match('/guard|whisper|tts|vision|scout|maverick|compound|prompt/i', $m)) $chat[] = $m;
    $web = array_values(array_filter($have, function ($m) { return stripos($m, 'compound') !== false; }));
    usort($web, function ($a, $b) { return (stripos($b, 'mini') !== false) <=> (stripos($a, 'mini') !== false); });
    $vision = array_values(array_filter($have, function ($m) { return preg_match('/scout|maverick|vision/i', $m); }));
    $out = ['chat' => array_slice($chat, 0, 4), 'web' => array_slice($web, 0, 1), 'vision' => array_slice($vision, 0, 1), 't' => time()];
    rocBlogSet($pdo, 'agent_models', $out);
    return $out;
}

/** What ROC may talk about and how (the owner's rules: gaming only, nothing rude or adult). */
function rocAgentSystem(string $lang, array $facts): string {
    $no = $lang === 'roman-urdu' ? 'Maaf kijiye, ye sawal meri range mein nahi hai. Aap gaming se related kuch bhi pooch sakte hain!'
                                 : 'Sorry, that is outside my range. Ask me anything about gaming!';
    return "You are ROC, the friendly gaming assistant of the website Run On Console (runonconsole.com). Rules:\n"
         . "- Reply in the visitor's language: " . ($lang === 'roman-urdu' ? 'Roman Urdu (Urdu written in English letters), simple words' : 'simple English')
         . ". If the visitor writes in Urdu script, reply in Roman Urdu. Keep it short: at most 120 words, no headings.\n"
         . "- Talk like a friendly gamer friend, the way ChatGPT or Claude chat: natural, warm, varied sentences, never the same reply twice, and follow the conversation. Answer the visitor's actual message first.\n"
         . "- Small talk is fine (hi, salam, how are you, kya haal hai, who are you, what can you do, thanks): reply naturally in one or two lines, and you may ask what they play or how you can help with gaming.\n"
         . "- If the visitor is on a phone (detected_in_browser.system is Android or iOS, or they say mobile / phone / mob): tell them clearly that you cannot guess their PC from their mobile (in their language, e.g. \"Main aap ke mobile se aap ke PC ka andaza nahi laga sakta\"), then ask them to type the PC's graphics card, processor and RAM, or to open runonconsole.com on that PC and ask again. Never treat the phone's chip as their PC. You can also help with mobile games.\n"
         . "- If the visitor asks to analyze or check their PC: use visitor_pc (detected_in_browser is what their browser reports, usually the graphics card; it can be wrong for laptops with two GPUs). Say what you see, ask for the missing parts (processor model, RAM) and which game they want to play.\n"
         . "- You answer EVERYTHING about gaming: games and their history, studios, consoles and PCs, PC hardware, esports teams, players and tournaments, game tips, settings and performance, gaming news. Use your own knowledge; when you are not sure, say so.\n"
         . "- Esports (teams, players, tournaments, who won, results) and questions about Run On Console / ROC itself (what it is, when it was made, where it is, how to contact it) are IN range: answer them, using about_run_on_console for the site. For results you are not sure about, give what you know and say it may have changed since.\n"
         . "- Use the refusal below ONLY for topics that are clearly not gaming and not about this website; when in doubt, answer.\n"
         . "- Anything that is not about gaming (politics, religion, medicine, money, homework, general chat, other topics): answer exactly: \"" . $no . "\"\n"
         . "- Never use or answer bad language, insults, sexual or adult content, violence against real people, hacking, cheats that break rules, piracy or anything illegal. For those answer exactly: \"" . $no . "\"\n"
         . "- Never repeat your introduction once the chat has started.\n"
         . "- For games, scores and products of this website use the FACTS below. Never invent prices (prices are on Amazon through the product pages), FPS numbers, specs or products.\n"
         . "- 'Can I run it': if a check exists, give the percent and the verdict and what to upgrade. If the graphics card or processor is unknown, ask for the graphics card, processor and RAM (Windows: Task Manager > Performance, or dxdiag). If the game is not in our list, say our checker has 500+ games and suggest /compatibility/.\n"
         . "- Products in products_shown_as_cards appear as cards under your answer: mention them by name in one line each. Recommend gear or upgrades ONLY when the visitor asks for them; otherwise you may add one short line that they can ask you for picks.\n"
         . "- game_found and platform in the FACTS are this website's data (requirements, devices, guide text): use them first and link the page (checker or /gaming-platforms/...).\n"
         . "- A picture from the visitor: describe what matters for gaming (for example read the PC specs from a screenshot, name the game or the hardware). If the picture is not about gaming or is not decent, answer exactly: \"" . $no . "\"\n"
         . "- Never ask for or repeat personal details (name, phone, email, address).\n"
         . "FACTS: " . json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Questions about new or changing things, answered with web search. */
function rocAgentNeedsWeb(string $q): bool {
    return (bool)preg_match('/latest|newest|\bnew\b|news|release|launch|announce|coming|kab aa|kab aye|kab ayega|when (is|will|does)|today|this (week|month|year)|20(2[5-9])|update|patch|season|tournament|\bwon\b|winner|jeeta|jeeti|result|trending/i', $q);
}

/** Bad language and adult words (English and Roman Urdu): ROC does not answer these at all. */
function rocAgentBlocked(string $q): bool {
    $t = ' ' . rocAgentNorm($q) . ' ';
    foreach (['fuck', 'fck', 'shit', 'bitch', 'bastard', 'asshole', 'porn', 'xxx', 'nude', 'nudes', 'naked', 'sex', 'sexy', 'boobs', 'dick', 'pussy', 'cock',
              'hentai', 'nsfw', 'onlyfans', 'chutiya', 'chutia', 'bhenchod', 'behenchod', 'madarchod', 'gandu', 'harami', 'haramzada', 'randi',
              'lund', 'phudi', 'phuddi', 'gaand', 'kanjar', 'kutti', 'bkl', 'tatti'] as $w)
        if (strpos($t, ' ' . $w . ' ') !== false || (strlen($w) >= 6 && strpos($t, $w) !== false)) return true;
    return false;
}

/* ------------------------------------------------------------------ settings */

function rocAgentSettings(PDO $pdo): array {
    $s = rocBlogGet($pdo, 'agent_settings', []);
    $s = is_array($s) ? $s : [];
    return ['on' => !array_key_exists('on', $s) || !empty($s['on']), 'daily' => max(0, (int)($s['daily'] ?? 400)),
            'hourly_ip' => max(1, (int)($s['hourly_ip'] ?? 30)), 'keep_days' => max(7, (int)($s['keep_days'] ?? 90))];
}

/* ------------------------------------------------------- hardware scores */

/** Graphics card score (1.8 integrated ... 10 top), null when no card is named. */
function rocAgentGpuScore(string $s): ?float {
    $s = strtolower($s);
    $s = preg_replace('/(?:nvidia|geforce)\s+(?=(?:20|30|40|50)\d0\b)/', ' rtx ', $s);
    $s = preg_replace('/(?:nvidia|geforce)\s+(?=(?:9[5-8]0|10[5-8]0|16[3-6]0)\b)/', ' gtx ', $s);
    $s = preg_replace('/geforce|nvidia|amd|radeon|intel|graphics card|®|™/', ' ', $s);
    $f = [];
    $RTX = [2050 => 4.5, 2060 => 6, 2070 => 7, 2080 => 8, 3050 => 4.5, 3060 => 7, 3070 => 8, 3080 => 8.5, 3090 => 10,
            4050 => 6, 4060 => 7, 4070 => 8.5, 4080 => 10, 4090 => 10, 5050 => 6.5, 5060 => 8, 5070 => 9, 5080 => 10, 5090 => 10];
    $RTX_TI = [2080 => 8.5, 3060 => 8, 3070 => 8.5, 3080 => 9, 4060 => 8, 4070 => 9, 4080 => 10, 5060 => 8.5, 5070 => 9.5, 2060 => 6.5, 2070 => 7];
    if (preg_match_all('/rtx\s*(\d{4})\s*(ti|super)?/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) { $n = (int)$x[1]; if (isset($RTX[$n])) $f[] = !empty($x[2]) ? ($RTX_TI[$n] ?? $RTX[$n]) : $RTX[$n]; }
    $GTX = [650 => 2, 660 => 2.5, 670 => 3, 680 => 3, 750 => 2.5, 760 => 3, 770 => 3, 780 => 3.5, 950 => 3, 960 => 3.5, 970 => 4, 980 => 4.5,
            1050 => 3, 1060 => 4.5, 1070 => 5, 1080 => 6, 1630 => 3, 1650 => 4.5, 1660 => 4.5];
    $GTX_TI = [750 => 2.5, 780 => 4, 980 => 5, 1050 => 3.5, 1070 => 5.5, 1080 => 7, 1650 => 4.5, 1660 => 5];
    if (preg_match_all('/gtx\s*(\d{3,4})\s*(ti|super)?/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) { $n = (int)$x[1]; if (isset($GTX[$n])) $f[] = !empty($x[2]) ? ($GTX_TI[$n] ?? $GTX[$n]) : $GTX[$n]; }
    if (preg_match('/\bgt\s*(1030|730|710)\b/', $s)) $f[] = 1.8;
    if (preg_match('/\bmx\s*\d{3}\b/', $s)) $f[] = 2.5;                  // laptop MX150 ... MX550
    $RX = [460 => 2.5, 470 => 4, 480 => 4, 550 => 2, 560 => 2.5, 570 => 4, 580 => 4.5, 590 => 4.5, 5300 => 3.5, 5500 => 4.5, 5600 => 6, 5700 => 6,
           6400 => 3, 6500 => 3.5, 6600 => 6.5, 6650 => 7, 6700 => 7.5, 6750 => 8, 6800 => 8.5, 6900 => 9.5, 6950 => 9.5,
           7600 => 7, 7700 => 8.5, 7800 => 8.5, 7900 => 10, 9060 => 8, 9070 => 9.5];
    $RX_XT = [5500 => 4.5, 5600 => 6, 5700 => 6.5, 6500 => 3.5, 6600 => 7, 6650 => 7, 6700 => 8, 6750 => 8, 6800 => 9, 6900 => 9.5, 7600 => 7, 7700 => 8.5, 7800 => 8.5, 7900 => 10, 9060 => 8, 9070 => 10];
    if (preg_match_all('/\brx\s*(\d{3,4})\s*(xtx|xt|gre)?/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) { $n = (int)$x[1]; if (!isset($RX[$n])) continue; $k = $x[2] ?? '';
            $f[] = $k === 'gre' ? ($n === 7900 ? 9 : $RX[$n]) : ($k !== '' ? ($RX_XT[$n] ?? $RX[$n]) : $RX[$n]); }
    $ARC = ['a310' => 2.5, 'a380' => 3.5, 'a580' => 6, 'a750' => 6.5, 'a770' => 7, 'b570' => 7, 'b580' => 7.5];
    if (preg_match_all('/arc\s*([ab])\s*(\d{3})/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) if (isset($ARC[$x[1] . $x[2]])) $f[] = $ARC[$x[1] . $x[2]];
    if (!$f && preg_match('/\b(uhd|iris|hd graphics|integrated|intel hd|vega\s*(3|6|7|8|10|11)\b|graphic(s)? card nahi|no graphics card|no gpu|without (a )?gpu)/', $s)) $f[] = 1.8;
    return $f ? (float)max($f) : null;
}

/** Processor score (2 old dual-core ... 10 top), null when no processor is named. */
function rocAgentCpuScore(string $s): ?float {
    $s = strtolower(str_replace(['®', '™'], '', $s));
    $f = [];
    if (preg_match_all('/\bi([3579])[\s-]*(\d{4,5})[a-z]*/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) {
            $t = (int)$x[1]; $num = $x[2]; $g = strlen($num) === 5 ? (int)substr($num, 0, 2) : (int)$num[0];
            if ($t === 3) $v = $g >= 12 ? 6 : ($g >= 10 ? 5 : ($g >= 8 ? 4.5 : 3));
            elseif ($t === 5) $v = $g >= 10 ? 7 : ($g >= 8 ? 6 : ($g >= 6 ? 5 : ($g >= 4 ? 4.5 : 3.5)));
            elseif ($t === 7) $v = $g >= 12 ? 8.5 : ($g >= 10 ? 8 : ($g >= 8 ? 7 : ($g >= 6 ? 6 : ($g >= 4 ? 4.5 : 4))));
            else $v = $g >= 12 ? 10 : 8.5;
            $f[] = $v;
        }
    // "i5 8th gen", "core i7 10th generation"
    if (!$f && preg_match_all('/\bi([3579])\b[^0-9]{0,12}(\d{1,2})\s*(?:th|st|nd|rd)?\s*gen/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) { $t = (int)$x[1]; $g = (int)$x[2];
            $f[] = $t === 3 ? ($g >= 12 ? 6 : ($g >= 10 ? 5 : ($g >= 8 ? 4.5 : 3))) : ($t === 5 ? ($g >= 10 ? 7 : ($g >= 8 ? 6 : ($g >= 6 ? 5 : 4.5)))
                  : ($t === 7 ? ($g >= 12 ? 8.5 : ($g >= 10 ? 8 : ($g >= 8 ? 7 : 6))) : ($g >= 12 ? 10 : 8.5))); }
    if (preg_match_all('/core\s*ultra\s*([579])/', $s, $m)) foreach ($m[1] as $x) $f[] = [5 => 7, 7 => 8.5, 9 => 10][(int)$x];
    if (preg_match_all('/(?:ryzen\s*|\br)([3579])\s*[\s-]*(\d{4})\s*(x3d)?/', $s, $m, PREG_SET_ORDER))
        foreach ($m as $x) {
            $t = (int)$x[1]; $se = (int)$x[2][0]; $x3d = !empty($x[3]);
            if ($t === 3) $v = $se >= 7 ? 6 : ($se >= 4 ? 5 : ($se >= 3 ? 4.5 : 3.5));
            elseif ($t === 5) $v = $se >= 7 ? 8 : ($se >= 4 ? 7 : ($se >= 3 ? 6 : ($se >= 2 ? 5 : 4.5)));
            elseif ($t === 7) $v = $x3d && $se >= 5 ? 10 : ($se >= 7 ? 9 : ($se >= 4 ? 8.5 : ($se >= 3 ? 7 : ($se >= 2 ? 5.5 : 5))));
            else $v = $se >= 7 ? 10 : ($se >= 5 ? 9.5 : 8.5);
            $f[] = $v;
        }
    if (preg_match('/\bfx[\s-]*\d{4}/', $s)) $f[] = 3.5;
    if (preg_match('/phenom|athlon/', $s)) $f[] = 2.5;
    if (preg_match('/core\s*2|pentium|celeron|atom/', $s)) $f[] = 2;
    if (!$f) foreach ([['/\bi9\b/', 6], ['/\bi7\b/', 4.5], ['/\bi5\b/', 4.5], ['/\bi3\b/', 3], ['/ryzen\s*9/', 6], ['/ryzen\s*7/', 5], ['/ryzen\s*5/', 4.5], ['/ryzen\s*3/', 3.5]] as [$re, $v])
        if (preg_match($re, $s)) $f[] = $v;
    return $f ? (float)max($f) : null;
}

/** Memory in GB ("16gb ram", "ram 8 gb", "8 gb memory"), null when not said. */
function rocAgentRam(string $s): ?float {
    $s = strtolower($s);
    if (preg_match('/(\d{1,3})\s*gb\s*(?:of\s*)?(?:ddr\d\s*)?(?:ram|memory)/', $s, $m)) return (float)$m[1];
    if (preg_match('/(?:ram|memory)\s*(?:is|hai|:)?\s*(\d{1,3})\s*gb/', $s, $m)) return (float)$m[1];
    return null;
}

/* ------------------------------------------------------------------- games */

function rocAgentNorm(string $s): string {
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower($s))));
}

/**
 * Games ROC knows: the compatibility page's own list first (roc-agent-games.json: GTA 5, Valorant,
 * Cyberpunk 2077 ... with the names people type), then the 500 Steam games of the checker.
 */
function rocAgentGames(string $root): array {
    static $g = null;
    if ($g !== null) return $g;
    $g = [];
    foreach ((array)(json_decode((string)@file_get_contents($root . '/roc-agent-games.json'), true)['games'] ?? []) as $x)
        if (!empty($x['req'])) $g[] = $x + ['keys' => [rocAgentNorm((string)$x['t'])]];
    foreach ((array)(json_decode((string)@file_get_contents($root . '/roc-steam-games.json'), true)['games'] ?? []) as $x) {
        if (empty($x['req'])) continue;
        $n = rocAgentNorm((string)$x['t']);
        $short = rocAgentNorm((string)preg_replace('/[:\-–].*$/u', '', (string)$x['t']));   // "Black Myth: Wukong" -> "black myth"
        $x['keys'] = array_values(array_unique(array_filter([$n, strlen($short) >= 5 ? $short : ''])));
        $x['u'] = '/compatibility/?steam=' . (int)($x['appid'] ?? 0);
        $g[] = $x;
    }
    return $g;
}

/** The game named in the text (the longest name found in it), or null. */
function rocAgentFindGame(string $root, string $text): ?array {
    $t = ' ' . rocAgentNorm($text) . ' ';
    $alias = ['gta v' => 'gta 5', 'gtav' => 'gta 5', 'gta5' => 'gta 5', 'gta vi' => 'gta 6', 'gta6' => 'gta 6', 'cs2' => 'counter strike 2',
              'cs 2' => 'counter strike 2', 'csgo' => 'counter strike 2', 'cs go' => 'counter strike 2', 'rdr2' => 'red dead redemption 2',
              'rdr 2' => 'red dead redemption 2', 'cod' => 'call of duty', 'valo' => 'valorant', 'cyberpunk' => 'cyberpunk 2077',
              'fifa' => 'ea sports fc 25', 'fc 25' => 'ea sports fc 25', 'wukong' => 'black myth wukong', 'zelda' => 'the legend of zelda'];
    foreach ($alias as $a => $full) if (strpos($t, ' ' . $a . ' ') !== false) $t .= ' ' . $full . ' ';
    $best = null; $bestLen = 0;
    foreach (rocAgentGames($root) as $g)
        foreach ((array)$g['keys'] as $k)
            if (strlen($k) >= 3 && strlen($k) > $bestLen && strpos($t, ' ' . $k . ' ') !== false) { $best = $g; $bestLen = strlen($k); }
    return $best;
}

/** The checker's verdict for a game and a PC (the same rules as /compatibility/). */
function rocAgentCheck(array $game, ?float $gpu, ?float $cpu, ?float $ram): array {
    $req = (array)($game['req'] ?? []);
    $min = (array)($req['min'] ?? []); $rec = (array)($req['rec'] ?? $min);
    $known = function ($v) { return is_numeric($v) && $v > 0; };
    $parts = [];
    foreach ([[0.5, $gpu, $rec['gpu'] ?? null], [0.3, $cpu, $rec['cpu'] ?? null], [0.2, $ram, $rec['ram'] ?? null]] as [$w, $have, $need])
        if ($known($need) && $have !== null) $parts[] = [$w, $have, $need];
    $weight = array_sum(array_column($parts, 0)) ?: 1;
    $pct = (int)round(100 * array_sum(array_map(function ($p) { return $p[0] * min(1, $p[1] / $p[2]); }, $parts)) / $weight);
    $below = function ($have, $m) use ($known) { return $have !== null && $known($m) && $have < $m; };
    $belowMin = $below($gpu, $min['gpu'] ?? null) || $below($cpu, $min['cpu'] ?? null) || $below($ram, $min['ram'] ?? null);
    if ($belowMin) $pct = min($pct, 40);
    $pct = max(5, $pct);
    $missing = [];
    if ($below($gpu, $rec['gpu'] ?? null)) $missing[] = ['part' => 'gpu', 'need' => (float)$rec['gpu'], 'below_min' => $below($gpu, $min['gpu'] ?? null)];
    if ($below($cpu, $rec['cpu'] ?? null)) $missing[] = ['part' => 'cpu', 'need' => (float)$rec['cpu'], 'below_min' => $below($cpu, $min['cpu'] ?? null)];
    if ($below($ram, $rec['ram'] ?? null)) $missing[] = ['part' => 'ram', 'need' => (float)$rec['ram'], 'below_min' => $below($ram, $min['ram'] ?? null)];
    $verdict = $pct >= 90 ? 'meets the recommended specs (high settings)' : ($pct >= 70 ? 'playable on medium to high settings'
             : ($pct > 40 ? 'playable on low settings only' : 'below the minimum specs (not playable well)'));
    $spec = function ($b) { return $b ? implode(' / ', array_filter([$b['gpu'] ?? '', $b['cpu'] ?? '', $b['ram'] ?? ''])) : ''; };
    return ['title' => (string)$game['t'], 'percent' => $pct, 'verdict' => $verdict, 'missing' => $missing,
            'minimum' => $spec($game['min'] ?? null), 'recommended' => $spec($game['rec'] ?? null),
            'url' => (string)($game['u'] ?? '/compatibility/'), 'unknown_parts' => array_values(array_filter([
                $gpu === null ? 'graphics card' : '', $cpu === null ? 'processor' : '', $ram === null ? 'RAM' : '']))];
}

/**
 * Run On Console's own story for ROC (public facts only: nothing about servers, keys, accounts,
 * earnings or the people behind it beyond what the About and Contact pages say).
 */
function rocAgentAbout(): array {
    return [
        'name' => 'Run On Console (ROC), runonconsole.com',
        'what' => 'An independent gaming and PC hardware website for PC and console players: a free "Can I run it?" checker for 500+ PC games, gear picks (200+ products: graphics cards, processors, RAM, monitors, mice, keyboards, headsets, speakers, laptops, handhelds, chairs, cases), guides for 15 gaming platforms (PlayStation, Xbox, Nintendo, PC and handhelds, mobile, cloud, VR, retro, arcade), blogs and live gaming news checked against at least two trusted sources.',
        'history' => [
            'October 2026: the website was built; the first code was written on 6 October 2026 and it went live soon after.',
            'It grew in small weekly updates: the game checker with official Steam requirements, product pages with real photos, gaming platform guides, the blog writer, live news (each story checked against 2+ sources), the Geo style news page, and ROC the assistant (you) in October 2026.',
        ],
        'based_in' => 'Lahore, Pakistan (470/16B Fort Villas, Lahore)',
        'contact' => ['email' => 'contact@runonconsole.com', 'phone' => '0317 4123457', 'page' => '/contact/'],
        'roc_assistant' => 'ROC is the site\'s gaming assistant for signed-in members: English and Roman Urdu, voice input, answers about games, PCs, consoles, esports and gear.',
        'how_it_earns' => 'Amazon Associates affiliate links (no extra cost to readers) and, later, ads. Brands cannot pay for rankings; sponsored content is always labelled.',
        'pages' => ['about' => '/about/', 'checker' => '/compatibility/', 'products' => '/products/', 'platforms' => '/gaming-platforms/', 'blogs' => '/blogs/', 'news' => '/news/',
                    'write_for_us' => '/write-for-us/', 'privacy' => '/privacy-policy/', 'terms' => '/terms-and-conditions/'],
        'not_shared' => 'Do not invent or share anything not listed here (owner names, revenue, traffic, servers, passwords, keys, internal tools). Say it is not public.',
    ];
}

/** What the site knows about a game: its requirements and the checker link. */
function rocAgentGameInfo(array $g): array {
    $spec = function ($b) { return is_array($b) ? implode(' / ', array_filter([$b['gpu'] ?? '', $b['cpu'] ?? '', $b['ram'] ?? '', $b['storage'] ?? ''])) : ''; };
    return ['title' => (string)$g['t'], 'year' => $g['y'] ?? null, 'genre' => $g['g'] ?? null,
            'minimum' => $spec($g['min'] ?? null), 'recommended' => $spec($g['rec'] ?? null), 'checker' => (string)($g['u'] ?? '/compatibility/')];
}

/**
 * A gaming platform or device named in the text, from CMS > Gaming platforms (gaming_categories,
 * gaming_devices): its guide text, the device's year, type, specs and iconic games, and the page link.
 */
function rocAgentPlatform(PDO $pdo, string $q): ?array {
    $t = ' ' . rocAgentNorm($q) . ' ';
    try {
        $plats = $pdo->query("SELECT * FROM gaming_categories WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC);
        $devs = $pdo->query('SELECT category_slug, name, year, type, specs, iconic_games_json FROM gaming_devices')->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return null; }
    $bySlug = [];
    foreach ($plats as $p) $bySlug[$p['slug']] = $p;
    $device = null; $len = 0;
    foreach ($devs as $d) {
        $n = rocAgentNorm(preg_replace('/\(.*?\)/', '', (string)$d['name']));
        if (strlen($n) >= 4 && strlen($n) > $len && strpos($t, ' ' . $n . ' ') !== false && isset($bySlug[$d['category_slug']])) { $device = $d; $len = strlen($n); }
    }
    $slug = $device['category_slug'] ?? null;
    if (!$slug) {
        $keys = ['sony-playstation' => 'playstation|ps5|ps4|ps3|ps2|ps1|psx', 'microsoft-xbox' => 'xbox', 'pc-handheld-pc' => 'steam deck|rog ally|legion go|handheld pc|gaming pc',
                 'nintendo-home' => 'nintendo switch|switch 2|wii|gamecube|n64|snes|\bnes\b', 'sony-handhelds' => 'psp|ps vita|vita', 'nintendo-handhelds' => 'game boy|gameboy|3ds|nintendo ds',
                 'sega-consoles' => 'sega|genesis|dreamcast|saturn|mega drive', 'mobile' => 'mobile gaming|android|iphone|ios gaming', 'cloud-gaming' => 'cloud gaming|geforce now|xcloud|boosteroid',
                 'vr-ar' => '\bvr\b|meta quest|quest 3|vision pro|virtual reality', 'retro-handheld-brands' => 'anbernic|miyoo|retroid|ayn odin', 'arcade' => 'arcade|pinball',
                 'smart-tv-streaming' => 'smart tv|fire tv|apple tv|android tv|chromecast', 'retro-other' => 'atari|neo geo|3do|turbografx'];
        foreach ($keys as $sl => $re) if (isset($bySlug[$sl]) && preg_match('/' . $re . '/', $t)) { $slug = $sl; break; }
    }
    if (!$slug) return null;
    $p = $bySlug[$slug];
    $about = trim(strip_tags((string)($p['about_text'] ?? '') ?: (string)($p['description'] ?? '')));
    $out = ['platform' => (string)$p['title'], 'page' => '/gaming-platforms/' . $slug . '/', 'about' => mb_substr(preg_replace('/\s+/', ' ', $about), 0, 700)];
    if ($device) $out['device'] = ['name' => $device['name'], 'year' => $device['year'], 'type' => $device['type'], 'specs' => $device['specs'],
                                   'iconic_games' => json_decode((string)$device['iconic_games_json'], true) ?: []];
    else $out['devices'] = array_values(array_map(function ($d) { return $d['name'] . ($d['year'] ? ' (' . $d['year'] . ')' : ''); },
                                    array_slice(array_filter($devs, function ($d) use ($slug) { return $d['category_slug'] === $slug; }), 0, 12)));
    return $out;
}

/* ---------------------------------------------------------------- products */

/** Category of the catalog a text asks about (slug), or ''. */
function rocAgentCategory(string $text): string {
    $t = strtolower($text);
    $map = ['mice' => '/\bmouse|\bmice\b/', 'keyboards' => '/keyboard|keybord/', 'audio' => '/headset|headphone|earbud|\bbuds\b|\bmic\b|microphone/',
            'speakers' => '/speaker|soundbar/', 'monitors' => '/monitor|display|screen|lcd|led\b/', 'gpu' => '/graphics card|graphic card|\bgpu\b|\brtx\b|\bgtx\b|\brx \d|video card/',
            'cpu' => '/processor|\bcpu\b|ryzen|core i\d|\bi[3579]\b/', 'ram' => '/\bram\b|memory|ddr[45]/', 'cases' => '/\bcase\b|cabinet|casing/',
            'chairs' => '/chair|kursi/', 'handhelds' => '/handheld|steam deck|rog ally|legion go|\bclaw\b/', 'laptops' => '/laptop|notebook/'];
    foreach ($map as $slug => $re) if (preg_match($re, $t)) return $slug;
    return '';
}

/**
 * Up to $n published products for a category, best match to the words first. For graphics cards and
 * processors with $minScore, only parts that reach it (the lowest that reaches it first).
 */
function rocAgentProducts(PDO $pdo, string $cat, string $text, int $n = 3, ?float $minScore = null): array {
    if ($cat === '') return [];
    try {
        $st = $pdo->prepare("SELECT slug, title, image, short_desc, best_for FROM products WHERE status = 'published' AND category_slug = ?");
        $st->execute([$cat]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
    $words = array_filter(explode(' ', rocAgentNorm($text)), function ($w) { return strlen($w) >= 3 && !in_array($w, ['the', 'and', 'for', 'best', 'mujhe', 'chahiye', 'kaun', 'konsa', 'which', 'what', 'gaming', 'good', 'acha', 'achi', 'suggest'], true); });
    foreach ($rows as &$r) {
        $hay = ' ' . rocAgentNorm($r['title'] . ' ' . $r['short_desc'] . ' ' . $r['best_for']) . ' ';
        $r['_s'] = 0;
        foreach ($words as $w) if (strpos($hay, ' ' . $w) !== false) $r['_s']++;
        $r['_hw'] = $cat === 'gpu' ? rocAgentGpuScore($r['title']) : ($cat === 'cpu' ? rocAgentCpuScore($r['title']) : null);
    }
    unset($r);
    if ($minScore !== null && in_array($cat, ['gpu', 'cpu'], true)) {
        $ok = array_values(array_filter($rows, function ($r) use ($minScore) { return $r['_hw'] !== null && $r['_hw'] >= $minScore; }));
        usort($ok, function ($a, $b) { return [$a['_hw'], -$a['_s']] <=> [$b['_hw'], -$b['_s']]; });
        $rows = $ok;
    } else {
        // Best match to the words first; graphics cards and processors then from the fastest down.
        usort($rows, function ($a, $b) { return [$b['_s'], $b['_hw'] ?? 0, $a['title']] <=> [$a['_s'], $a['_hw'] ?? 0, $b['title']]; });
    }
    return array_map(function ($r) {
        return ['title' => (string)$r['title'], 'url' => '/products/' . $r['slug'] . '/', 'image' => (string)$r['image'],
                'about' => mb_substr(trim((string)($r['best_for'] ?: $r['short_desc'])), 0, 140)];
    }, array_slice($rows, 0, $n));
}

/* ------------------------------------------------------------------ privacy */

/** Removes emails, phone and ID numbers, links with personal data and names from a chat line. */
function rocAgentScrub(string $s): string {
    $s = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $s);
    $s = preg_replace('/\b\d{5}-\d{7}-\d\b/', '[id number]', $s);                         // CNIC
    $s = preg_replace('/(?<![\w.])\+?\d[\d\s().-]{8,}\d(?![\w.])/', '[number]', $s);       // phones, cards
    $s = preg_replace('/\b(my name is|i am called|mera naam|mera name|i\'m)\s+[A-Za-z]+(\s+[A-Za-z]+)?/i', '$1 [name]', $s);
    return $s;
}

/** Roman Urdu or English (for the fixed answers and to tell the model). */
function rocAgentLang(string $s): string {
    if (preg_match('/\p{Arabic}/u', $s)) return 'roman-urdu';                       // Urdu script (voice input in Urdu)
    return preg_match('/\b(salam|assalam|aoa|hai|hain|kya|kia|mera|meri|mere|chalega|chalegi|chal|nahi|nai|ni|mujhe|chahiye|konsa|kaun|kitna|acha|achi|kar|kr|ho|bhai|yar|yaar|lena|batao|bta|btao)\b/i', $s) ? 'roman-urdu' : 'english';
}

/* --------------------------------------------------------------------- log */

function rocAgentTable(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $sqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $pdo->exec($sqlite
        ? 'CREATE TABLE IF NOT EXISTS roc_agent_chats (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT, user_id INTEGER DEFAULT 0, session TEXT, ip_hash TEXT, page TEXT, question TEXT, answer TEXT, game TEXT, products TEXT, engine TEXT, lang TEXT)'
        : 'CREATE TABLE IF NOT EXISTS roc_agent_chats (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, created_at DATETIME NOT NULL, user_id INT UNSIGNED NOT NULL DEFAULT 0, session CHAR(16) NOT NULL, ip_hash CHAR(16) NOT NULL,
           page VARCHAR(200) NOT NULL DEFAULT \'\', question TEXT, answer TEXT, game VARCHAR(200) NOT NULL DEFAULT \'\', products VARCHAR(500) NOT NULL DEFAULT \'\',
           engine VARCHAR(60) NOT NULL DEFAULT \'\', lang VARCHAR(20) NOT NULL DEFAULT \'\', KEY created_at (created_at), KEY ip_hash (ip_hash), KEY user_id (user_id)) DEFAULT CHARSET=utf8mb4');
    $done = true;
}

/** Visitor key for the limits: a daily salted hash, the IP itself is never stored. */
function rocAgentIpHash(): string {
    return substr(hash('sha256', gmdate('Y-m-d') . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|roc-agent'), 0, 16);
}

/* ------------------------------------------------------------------ answer */

/**
 * One answer. $history: [['role' => 'user'|'assistant', 'content' => '...'], ...], the last one the question.
 * Returns ['reply' => text, 'game' => check|null, 'products' => [...], 'engine' => model|'basic', 'lang' => ...].
 */
function rocAgentAnswer(PDO $pdo, string $root, array $history, bool $useAi = true, ?string $image = null, array $device = []): array {
    $q = (string)end($history)['content'];
    $lang0 = rocAgentLang($q);
    if (rocAgentBlocked($q)) return ['reply' => $lang0 === 'roman-urdu' ? 'Maaf kijiye, main is qisam ki baat ka jawab nahi deta. Aap gaming se related kuch bhi pooch sakte hain!'
                                                                          : 'Sorry, I do not answer that kind of message. Ask me anything about gaming!',
                                     'game' => null, 'products' => [], 'engine' => 'blocked', 'lang' => $lang0];
    $userText = implode("\n", array_map(function ($m) { return $m['content']; }, array_filter($history, function ($m) { return $m['role'] === 'user'; })));
    $lang = rocAgentLang($q);
    // Hardware and game: the newest mention wins, so earlier messages still count ("RTX 3060 hai" then "GTA 5?").
    $gpu = rocAgentGpuScore($q) ?? rocAgentGpuScore($userText);
    // "Analyze my PC": the graphics card the browser reports, when the visitor has not named one.
    $detGpu = trim(mb_substr((string)($device['gpu'] ?? ''), 0, 120));
    $askPc = (bool)preg_match('/analy[sz]|check (my|mera)|my pc|mera pc|meri pc|my laptop|mera laptop|system|specs?/i', $q);
    if (preg_match('/android|ios/i', (string)($device['os'] ?? ''))) $detGpu = '';   // a phone says nothing about the PC
    if ($gpu === null && $detGpu !== '' && ($askPc || preg_match('/\brun\b|\bchal|\bplay\b|\bkhel|\bfps\b/i', $q))) $gpu = rocAgentGpuScore($detGpu);
    $cpu = rocAgentCpuScore($q) ?? rocAgentCpuScore($userText);
    $ram = rocAgentRam($q) ?? rocAgentRam($userText);
    $game = rocAgentFindGame($root, $q);
    $askedRun = (bool)preg_match('/\brun\b|\bchal|\bplay\b|\bplayable|\bkhel|\bfps\b|requirement|\bspecs?\b/i', $q);   // not "PlayStation"
    // The game from earlier messages only when this message is about running it, the PC or an upgrade
    // (not for a new topic such as a console).
    $pcNow = rocAgentGpuScore($q) !== null || rocAgentCpuScore($q) !== null || rocAgentRam($q) !== null;
    if (!$game && ($askedRun || $pcNow || preg_match('/upgrade/i', $q))) {
        // A follow-up with only the PC: the game from the last user messages.
        foreach (array_reverse($history) as $m) if ($m['role'] === 'user' && ($g = rocAgentFindGame($root, $m['content']))) { $game = $g; break; }
    }
    $check = $game && ($gpu !== null || $cpu !== null) ? rocAgentCheck($game, $gpu, $cpu, $ram) : null;

    $products = [];
    $cat = rocAgentCategory($q);
    // Product picks only when the visitor asks for them (owner: recommendations on request).
    $wants = (bool)preg_match('/suggest|recommend|best|konsa|kaunsa|kon sa|kaun sa|lena|lun\b|buy|kharid|upgrade|chahiye|which|option|pick/i', $q);
    if ($check && $check['missing'] && $wants) {
        // Upgrades that reach the game's recommended specs.
        foreach ($check['missing'] as $mi) {
            if ($mi['part'] === 'gpu') $products = array_merge($products, rocAgentProducts($pdo, 'gpu', $q, 2, $mi['need']));
            if ($mi['part'] === 'cpu') $products = array_merge($products, rocAgentProducts($pdo, 'cpu', $q, 1, $mi['need']));
            if ($mi['part'] === 'ram') $products = array_merge($products, rocAgentProducts($pdo, 'ram', $q, 1));
        }
        $products = array_slice($products, 0, 3);
    } elseif ($cat !== '' && !$game && $wants) {
        $products = rocAgentProducts($pdo, $cat, $q, 3);
    }

    $facts = ['site' => 'Run On Console (runonconsole.com): gaming and PC hardware picks, a Can I run it checker for 500 PC games, gaming platform guides, blogs and news.',
              'about_run_on_console' => rocAgentAbout(),
              'visitor_pc' => ['graphics_card_score' => $gpu, 'processor_score' => $cpu, 'ram_gb' => $ram,
                               'on_phone' => (bool)preg_match('/android|ios/i', (string)($device['os'] ?? '')),
                               'detected_in_browser' => $detGpu !== '' ? ['graphics' => $detGpu, 'cpu_threads' => (int)($device['threads'] ?? 0) ?: null,
                                                                         'memory_gb_at_least' => (float)($device['ram'] ?? 0) ?: null, 'system' => mb_substr((string)($device['os'] ?? ''), 0, 40)] : null,
                               'scale' => '1.8 = integrated graphics, 4.5 = GTX 1650 / i5 8th gen, 7 = RTX 3060 / i5 12th gen, 8.5 = RTX 4070 / i7 12th gen, 10 = RTX 4080+ / i9'],
              'game_found' => $game ? rocAgentGameInfo($game) : null, 'check' => $check, 'products_shown_as_cards' => $products,
              'platform' => rocAgentPlatform($pdo, $q),
              'recommendations' => $products ? 'shown' : 'only when the visitor asks for gear or upgrades: then tell them to ask, for example "suggest a graphics card"',
              'catalog_categories' => 'mice, keyboards, headsets and audio, speakers, monitors, graphics cards, processors, RAM, PC cases, gaming chairs, handhelds, laptops',
              'links' => ['checker' => '/compatibility/', 'products' => '/products/', 'blogs' => '/blogs/', 'news' => '/news/', 'contact' => '/contact/']];

    $reply = null; $engine = 'basic';
    if ($useAi && ($key = trim((string)rocBlogGet($pdo, 'blog_groq_key', ''))) !== '') {
        $msgs = [['role' => 'system', 'content' => rocAgentSystem($lang, $facts)]];
        foreach (array_slice($history, -6) as $m) $msgs[] = ['role' => $m['role'] === 'assistant' ? 'assistant' : 'user', 'content' => mb_substr((string)$m['content'], 0, 600)];
        // Models: a picture goes to the vision model; questions about new things (releases, news, results)
        // first to Groq's model with web search; the rest to the chat models.
        $av = rocAgentModels($pdo, $key);
        $models = $av['chat'];
        if ($image !== null) {
            $last = array_pop($msgs);
            $msgs[] = ['role' => 'user', 'content' => [['type' => 'text', 'text' => $last['content'] !== '' ? $last['content'] : 'What is in this picture? (gaming related)'],
                                                        ['type' => 'image_url', 'image_url' => ['url' => $image]]]];
            $models = $av['vision'] ?: [ROC_AGENT_VISION];
        }
        // New things (releases, news, results): Groq's web search model, or else the browser search tool
        // of the gpt-oss models (Groq runs the search); without either, the model's own knowledge.
        $web = $image === null && rocAgentNeedsWeb($q) && !$check && !$products;
        if ($web && $av['web']) $models = array_merge($av['web'], $models);
        $errs = [];
        foreach ($models as $model) {
            $body = ['model' => $model, 'temperature' => 0.6, 'max_tokens' => 700, 'messages' => $msgs];
            if (preg_match('/gpt-oss|qwen3/i', $model)) $body['reasoning_effort'] = preg_match('/gpt-oss/i', $model) ? 'low' : 'none';
            $search = $web && !$av['web'] && stripos($model, 'gpt-oss') !== false;
            if ($search) $body['tools'] = [['type' => 'browser_search']];
            $r = rocGroqCall($key, 'POST', '/chat/completions', $body, stripos($model, 'compound') !== false || $search ? 40 : 25);
            if (!$r['ok'] && $search) {   // the search tool is not on this key: answer without it
                unset($body['tools']);
                $r = rocGroqCall($key, 'POST', '/chat/completions', $body, 25);
            }
            if ($r['ok']) {
                $reply = trim((string)preg_replace('#<think>.*?</think>#s', '', (string)($r['data']['choices'][0]['message']['content'] ?? '')));
                if ($reply !== '') { $engine = $model; break; }
                $errs[] = $model . ': empty answer';
            } else {
                $errs[] = $model . ': ' . $r['error'];
                // A retired model: ask Groq for the list again next time.
                if (in_array($r['code'] ?? 0, [400, 404], true) && preg_match('/does not exist|decommission|not found|no longer|deprecated/i', $r['error'])) rocBlogSet($pdo, 'agent_models', []);
            }
        }
        if ($errs && $engine === 'basic') rocBlogSet($pdo, 'agent_last_error', ['t' => gmdate('Y-m-d H:i') . ' UTC', 'errors' => array_slice($errs, 0, 4)]);
    }
    if (($reply === null || $reply === '') && $image !== null)
        $reply = $lang === 'roman-urdu' ? 'Maaf kijiye, abhi picture nahi parh saka. Thori der baad dobara bhejein, ya likh kar poochein.' : 'Sorry, I could not read the picture right now. Please try again later or type your question.';
    if (($reply === null || $reply === '') && !$check && !$products && ($pl = $facts['platform'])) {
        $d = $pl['device'] ?? null;
        $reply = ($d ? $d['name'] . ' (' . $d['year'] . ', ' . $d['type'] . '): ' . $d['specs'] . ".\n" : '') . $pl['about'] . "\n" . $pl['page'];
    }
    if ($reply === null || $reply === '') $reply = rocAgentBasic($lang, $check, $game, $products, $gpu, $cpu, $cat);
    return ['reply' => $reply, 'game' => $check, 'products' => $products, 'engine' => $engine, 'lang' => $lang];
}

/** The answer without the AI: fixed sentences around the facts. */
function rocAgentBasic(string $lang, ?array $check, ?array $game, array $products, ?float $gpu, ?float $cpu, string $cat): string {
    $ur = $lang === 'roman-urdu';
    if (!$check && !$game && !$products && $gpu === null && $cpu === null)
        return $ur ? 'Maaf kijiye, main abhi thora busy hoon aur poora jawab nahi de pa raha. Thori der baad dobara poochein, ya game ka naam aur apna PC (graphics card, processor, RAM) likh dein, wo main abhi bhi check kar sakta hoon.'
                   : 'Sorry, I am a bit busy right now and cannot give a full answer. Please try again in a moment, or tell me a game and your PC (graphics card, processor, RAM): I can still check that.';
    if ($check) {
        $s = $ur ? "{$check['title']}: aap ka PC {$check['percent']}% score karta hai, yani " . ['meets the recommended specs (high settings)' => 'high settings par chalega',
                   'playable on medium to high settings' => 'medium se high settings par chalega', 'playable on low settings only' => 'sirf low settings par chalega',
                   'below the minimum specs (not playable well)' => 'minimum se kam hai, theek nahi chalega'][$check['verdict']] . '.'
                 : "{$check['title']}: your PC scores {$check['percent']}%: " . $check['verdict'] . '.';
        $parts = implode(', ', array_map(function ($m) { return ['gpu' => 'graphics card', 'cpu' => 'processor', 'ram' => 'RAM'][$m['part']]; }, $check['missing']));
        if ($check['missing']) $s .= $products ? ($ur ? ' Behtar chalane ke liye neeche wale parts dekhein.' : ' The parts below would bring it to the recommended specs.')
                                               : ($ur ? " Upgrade ki zaroorat: {$parts}. Picks chahiye to poochein \"upgrade suggest karo\"." : " To reach the recommended specs, upgrade the {$parts}. Ask me \"suggest an upgrade\" for picks.");
        if ($check['unknown_parts']) $s .= $ur ? ' (' . implode(', ', $check['unknown_parts']) . ' nahi bataya, is liye wo hisaab mein nahi.)' : ' (Not counted: ' . implode(', ', $check['unknown_parts']) . '.)';
        return $s;
    }
    if ($game) return $ur ? "{$game['t']} check karne ke liye apna graphics card, processor aur RAM batayein (Windows: Task Manager > Performance)."
                          : "To check {$game['t']}, tell me your graphics card, processor and RAM (Windows: Task Manager > Performance).";
    if ($products) return $ur ? 'Ye hamari site ke kuch achhe options hain:' : 'Here are some good picks from our site:';
    if ($gpu !== null || $cpu !== null) return $ur ? 'Shukriya! Ab game ka naam batayein, main check kar deta hoon. Agar game hamari list mein na ho to /compatibility/ par official requirements dekh sakte hain.'
                                                    : 'Thanks! Now tell me the game and I will check it. If it is not in our list, compare the official requirements on /compatibility/.';
    return $ur ? 'Main ROC hoon. Game ka naam aur apna PC (graphics card, processor, RAM) batayein, ya poochein ke kaunsa mouse, keyboard ya headset lena chahiye.'
               : 'I am ROC. Tell me a game and your PC (graphics card, processor, RAM), or ask which mouse, keyboard or headset to get.';
}
