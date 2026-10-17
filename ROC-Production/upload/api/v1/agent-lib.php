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
         . "- Roman Urdu means URDU, never Hindi: no Hindi words such as kripya, dhanyavaad, prashn, uttar, sahayata, uplabdh, avashyak, vishay, samasya. Use Urdu: meharbani kar ke, shukriya, sawal, jawab, madad, mojood, zaroori, masla.\n"
         . "- Talk like a friendly gamer friend, the way ChatGPT or Claude chat: natural, warm, varied sentences, never the same reply twice, and follow the conversation. Answer the visitor's actual message first.\n"
         . "- Small talk is fine (hi, salam, how are you, kya haal hai, who are you, what can you do, thanks): reply naturally in one or two lines, and you may ask what they play or how you can help with gaming.\n"
         . "- You CANNOT see or detect the visitor's PC. Never say what their PC has (graphics card, processor, RAM) unless they told you in this chat, uploaded a dxdiag report, or visitor_pc.saved_from_earlier has it.\n"
         . "- visitor_pc.saved_from_earlier is the PC this member told you before (kept with their account): use it without asking again, say briefly which PC you used, and tell them they can say 'forget my PC' to remove it.\n"
         . "- The best way to read a PC exactly: the 🖥 button in this chat box reads a dxdiag file (Win+R, type dxdiag, Save All Information). Suggest it when specs are missing or unclear.\n"
         . "- Analyze my PC, step by step: ask for what is missing, one or two things at a time. Show where to find them: Windows: Settings > System > About (processor and RAM), Task Manager > Performance > GPU (graphics card), or Win+R and type dxdiag; Mac: Apple menu > About This Mac; a laptop: the model name on the sticker underneath. Laptop chips: U = low power, H/HX = gaming. When graphics card and processor are known and no game is named, use pc_summary: say which games run well, which only on low settings and which are too heavy, and suggest one sensible upgrade only if they ask.\n"
         . "- Questions about this website (what products or categories it has, how many, a product's page, the newest blogs or news): use this_website and link the pages.\n"
         . "- 'Can I run <game>' or analyze my PC, in this order: (1) If visitor_pc.known lacks the graphics card, processor or RAM, do NOT guess and give no percent: ask them to tell you their graphics card, processor and RAM in one short message. (2) If they say they do not know where to see them (pata nahi, kahan se dekhun, where do I find): tell them exactly where: Windows: press Win + R, type dxdiag, Enter; the System tab shows Processor and Memory (RAM), the Display tab shows the graphics card name; or Task Manager (Ctrl + Shift + Esc) > Performance; or the 🖥 button in this chat to upload the dxdiag file; a laptop: the model name on the sticker underneath. Then ask them to type what they see. (3) Once known, use check: say clearly if the game runs and how (high, medium, low settings or not playable well) with the percent. (4) If it is below the recommended specs, name the exact upgrade from upgrade_options, for example \"Agar aap apna graphics card GeForce RTX 3060 (ya Radeon RX 6600) kar lein to ye game high settings par chalega\"; for RAM say the exact size. Name only the parts that are short.\n"
         . "- You answer EVERYTHING about gaming: games and their history, studios, consoles and PCs, PC hardware, esports teams, players and tournaments, game tips, settings and performance, gaming news. Use your own knowledge; when you are not sure, say so.\n"
         . "- Esports (teams, players, tournaments, who won, results) and questions about Run On Console / ROC itself (what it is, when it was made, where it is, how to contact it) are IN range: answer them, using about_run_on_console for the site. For results you are not sure about, give what you know and say it may have changed since.\n"
         . "- Esports winners, finals and scores: give ONLY what reference.esports_results says (event, year, winner). If the event is not there, say you are not sure of that result instead of guessing. Never invent final scores or opponents.\n"
         . "- Games and consoles in reference (year, developer, publisher, platforms, series) are checked data: use them. Gaming is not only hardware: talk about the games themselves, their stories, modes, history and platforms too.\n"
         . "- Gaming schoolwork or assignments: help with ideas, an outline and facts, and encourage the visitor to write it in their own words.\n"
         . "- Use the refusal below ONLY for topics that are clearly not gaming and not about this website; when in doubt, answer.\n"
         . "- Anything that is not about gaming (politics, religion, medicine, money, homework, general chat, other topics): answer exactly: \"" . $no . "\"\n"
         . "- Never use or answer bad language, insults, sexual or adult content, violence against real people, hacking, cheats that break rules, piracy or anything illegal. For those answer exactly: \"" . $no . "\"\n"
         . "- Never repeat your introduction once the chat has started.\n"
         . "- For games, scores and products of this website use the FACTS below. Never invent prices (prices are on Amazon through the product pages), FPS numbers, specs or products.\n"
         . "- If the game is not in our list, say our checker has 2,000+ games (list: /can-i-run/) and suggest /compatibility/.\n"
         . "- Products in products_shown_as_cards appear as cards under your answer: mention them by name in one line each. Recommend gear or upgrades ONLY when the visitor asks for them; otherwise you may add one short line that they can ask you for picks.\n"
         . "- game_found and platform in the FACTS are this website's data (requirements, devices, guide text): use them first and link the page (the game's requirements_page when there is one, else the checker; or /gaming-platforms/...).\n"
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
    if (preg_match('/(\d{3,6})\s*mb\s*ram/', $s, $m)) return (float)round((int)$m[1] / 1024);   // dxdiag
    return null;
}

/**
 * PC parts from a dxdiag report or pasted system text: processor, graphics cards, RAM, system model
 * and Windows. Null when the text has none of them.
 */
function rocAgentSpecsFromText(string $t): ?array {
    $one = function (string $re) use ($t) { return preg_match($re, $t, $m) ? trim(preg_replace('/\s+/', ' ', $m[1])) : ''; };
    $cpu = $one('/^\s*Processor:\s*(.+)$/mi');
    $cpu = trim(preg_replace('/\(R\)|\(TM\)|CPU|@.*$|\(\d+ CPUs\).*$/i', ' ', $cpu));
    $cpu = trim(preg_replace('/\s+/', ' ', $cpu));
    preg_match_all('/^\s*(?:Card name|Name|GPU):\s*(.+)$/mi', $t, $gm);
    $gpus = array_values(array_unique(array_filter(array_map(function ($g) { return trim(preg_replace('/\(R\)|\(TM\)/i', '', $g)); }, $gm[1] ?? []),
        function ($g) { return preg_match('/nvidia|geforce|radeon|amd|intel|arc|uhd|iris|graphics/i', $g); })));
    $ramMb = (int)$one('/^\s*Memory:\s*(\d+)\s*MB/mi');
    $ram = $ramMb ? (int)round($ramMb / 1024) : null;
    $maker = $one('/^\s*System Manufacturer:\s*(.+)$/mi'); $sys = $one('/^\s*System Model:\s*(.+)$/mi');
    $model = trim(stripos($sys, $maker) === 0 ? $sys : $maker . ' ' . $sys);
    $os = $one('/^\s*Operating System:\s*([^(\r\n]+)/mi');
    if ($cpu === '' && !$gpus && !$ram) return null;
    // Of two cards (laptops: Intel + NVIDIA), the one that games use: the fastest.
    usort($gpus, function ($a, $b) { return (rocAgentGpuScore($b) ?? 0) <=> (rocAgentGpuScore($a) ?? 0); });
    return ['cpu' => mb_substr($cpu, 0, 80), 'gpu' => mb_substr($gpus[0] ?? '', 0, 80), 'gpus' => array_slice($gpus, 0, 3), 'ram' => $ram,
            'model' => mb_substr($model, 0, 80), 'os' => mb_substr(trim($os), 0, 60)];
}

/* -------------------------------------------------------------- saved PC */

/** The PC a member told ROC about (kept until they say "forget my PC"). */
function rocAgentPcGet(PDO $pdo, int $uid): ?array {
    if ($uid <= 0) return null;
    try {
        $st = $pdo->prepare('SELECT gpu_score, cpu_score, ram_gb, label FROM roc_agent_pcs WHERE user_id = ?');
        $st->execute([$uid]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return null; }
    if (!$r) return null;
    return ['gpu' => $r['gpu_score'] !== null ? (float)$r['gpu_score'] : null, 'cpu' => $r['cpu_score'] !== null ? (float)$r['cpu_score'] : null,
            'ram' => $r['ram_gb'] !== null ? (float)$r['ram_gb'] : null, 'label' => (string)$r['label']];
}

function rocAgentPcSave(PDO $pdo, int $uid, ?float $gpu, ?float $cpu, ?float $ram, string $label): void {
    if ($uid <= 0) return;
    $old = rocAgentPcGet($pdo, $uid) ?? ['gpu' => null, 'cpu' => null, 'ram' => null, 'label' => ''];
    $row = [$gpu ?? $old['gpu'], $cpu ?? $old['cpu'], $ram ?? $old['ram'], mb_substr($label !== '' ? $label : $old['label'], 0, 300), gmdate('Y-m-d H:i:s')];
    try {
        $pdo->prepare('DELETE FROM roc_agent_pcs WHERE user_id = ?')->execute([$uid]);
        $pdo->prepare('INSERT INTO roc_agent_pcs (user_id, gpu_score, cpu_score, ram_gb, label, updated_at) VALUES (?, ?, ?, ?, ?, ?)')->execute(array_merge([$uid], $row));
    } catch (\Throwable $e) {}
}

/* ------------------------------------------------------------------- games */

function rocAgentNorm(string $s): string {
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', strtolower($s))));
}

/**
 * Games ROC knows: the compatibility page's own list first (roc-agent-games.json: GTA 5, Valorant,
 * Cyberpunk 2077 ... with the names people type), then the 2,000+ Steam games of the checker (each with its page at /can-i-run/{game}/).
 */
function rocAgentGames(string $root): array {
    static $g = null;
    if ($g !== null) return $g;
    $g = [];
    require_once __DIR__ . '/game-pages-lib.php';
    $pages = []; $byName = [];
    foreach (rocGpAll($root) as $p) { $pages[(int)$p['appid']] = rocGpPath($p); $byName[rocAgentNorm((string)$p['name'])] = rocGpPath($p); }
    foreach ((array)(json_decode((string)@file_get_contents($root . '/roc-agent-games.json'), true)['games'] ?? []) as $x) {
        if (empty($x['req'])) continue;
        $x += ['keys' => [rocAgentNorm((string)$x['t'])]];
        foreach ((array)$x['keys'] as $k) if (isset($byName[$k])) { $x['page'] = $byName[$k]; break; }   // its requirements page, when there is one
        $g[] = $x;
    }
    foreach ((array)(json_decode((string)@file_get_contents($root . '/roc-steam-games.json'), true)['games'] ?? []) as $x) {
        if (empty($x['req'])) continue;
        $n = rocAgentNorm((string)$x['t']);
        $short = rocAgentNorm((string)preg_replace('/[:\-–].*$/u', '', (string)$x['t']));   // "Black Myth: Wukong" -> "black myth"
        $x['keys'] = array_values(array_unique(array_filter([$n, strlen($short) >= 5 ? $short : ''])));
        $x['u'] = '/compatibility/?steam=' . (int)($x['appid'] ?? 0);
        if (isset($pages[(int)($x['appid'] ?? 0)])) $x['page'] = $pages[(int)$x['appid']];   // the game's own page (game-render.php)   // the game's own page (game-render.php)
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
            'url' => (string)($game['u'] ?? '/compatibility/'), 'requirements_page' => $game['page'] ?? null, 'unknown_parts' => array_values(array_filter([
                $gpu === null ? 'graphics card' : '', $cpu === null ? 'processor' : '', $ram === null ? 'RAM' : '']))];
}

/**
 * Run On Console's own story for ROC (public facts only: nothing about servers, keys, accounts,
 * earnings or the people behind it beyond what the About and Contact pages say).
 */
function rocAgentAbout(): array {
    return [
        'name' => 'Run On Console (ROC), runonconsole.com',
        'what' => 'An independent gaming and PC hardware website for PC and console players: a free "Can I run it?" checker for 2,000+ PC games with a requirements page for each (/can-i-run/{game}/), gear picks (200+ products: graphics cards, processors, RAM, monitors, mice, keyboards, headsets, speakers, laptops, handhelds, chairs, cases), guides for 15 gaming platforms (PlayStation, Xbox, Nintendo, PC and handhelds, mobile, cloud, VR, retro, arcade), blogs and live gaming news checked against at least two trusted sources.',
        'history' => [
            'Founded in 2026 (only the year is public: never give a month or a day).',
            'It grew in small weekly updates: the game checker with official Steam requirements, product pages with real photos, gaming platform guides, the blog writer, live news (each story checked against 2+ sources), the Geo style news page, and ROC the assistant (you).',
        ],
        'based_in' => 'Lahore, Pakistan (470/16B Fort Villas, Lahore)',
        'contact' => ['email' => 'contact@runonconsole.com', 'phone' => '0317 4123457', 'page' => '/contact/'],
        'roc_assistant' => 'ROC is the site\'s gaming assistant for signed-in members: English and Roman Urdu, voice input, answers about games, PCs, consoles, esports and gear.',
        'how_it_earns' => 'Amazon Associates affiliate links (no extra cost to readers) and ads (Google AdSense). Do not call the site ad-free. Brands cannot pay for rankings; sponsored content is always labelled.',
        'checker_how_it_works' => 'The Can I run it checker (/compatibility/) compares the PC with each game\'s official requirements (Steam store and the game makers). Graphics card and processor get a tier score (1.8 integrated graphics ... 10 the fastest cards and CPUs), RAM is in GB. The result: graphics card 50%, processor 30%, RAM 20% of the recommended specs; under the minimum on any part = at most 40%. 90%+ = recommended specs (high settings), 70-89% = medium to high, 41-69% = low settings, 40% or less = below the minimum. A hard disk instead of an SSD lowers it. There are ready presets for consoles, handhelds, phones and cloud gaming. ROC uses the same rules.',
        'built_with' => 'PHP and MySQL on the server, React (Vite) and Tailwind for the pages; ROC answers with Groq AI models and the site\'s own data. Do not name any other technology.',
        // Every main page, so ROC links the right one (Deploy 81). Product categories, products, guides,
        // blogs and news with their own pages come from the database (this_website).
        'pages' => ['home' => '/', 'about' => '/about/', 'contact' => '/contact/', 'checker' => '/compatibility/', 'game_requirements' => '/can-i-run/ (one page per game: /can-i-run/{game}/)',
                    'products' => '/products/', 'pc_hardware' => '/category/pc-hardware/', 'gaming_hardware' => '/category/gaming-hardware/',
                    'platforms' => '/gaming-platforms/', 'updates' => '/updates/ (blogs, guides and news together)', 'blogs' => '/blogs/', 'guides' => '/guides/', 'news' => '/news/',
                    'write_for_us' => '/write-for-us/', 'privacy_policy_and_affiliate_disclosure' => '/privacy-policy/', 'terms' => '/terms-and-conditions/',
                    'author' => '/author/roc-team/', 'sign_up' => '/auth/signup/', 'sign_in' => '/auth/login/', 'profile_and_wishlist' => '/profile/',
                    'comparisons' => '/compare/ ("X vs Y" pages: two of our top picks side by side, specs, differences, who should pick which: /compare/{a}-vs-{b}/)'],
        'link_rule' => 'Only link pages listed here or in this_website (never make up an address). Questions about privacy, cookies, affiliate links or data: /privacy-policy/. Rules of use: /terms-and-conditions/. Help, feedback, partnerships: /contact/ or /write-for-us/.',
        'not_shared' => 'Do not invent or share anything not listed here (owner names, revenue, traffic, servers, passwords, keys, internal tools). Say it is not public.',
    ];
}

/**
 * Named parts for what a check found short: the weakest common graphics cards / processors that reach
 * the game's recommended level (game-pages-lib.php reference lists), and the RAM size.
 */
function rocAgentUpgrades(array $check): array {
    require_once __DIR__ . '/game-pages-lib.php';
    $out = [];
    foreach ($check['missing'] as $mi) {
        if ($mi['part'] === 'ram') { $out['ram'] = rtrim(rtrim(number_format((float)$mi['need'], 1), '0'), '.') . ' GB'; continue; }
        $out[$mi['part'] === 'gpu' ? 'graphics_card' : 'processor'] = rocGpPartsFor($mi['part'], (float)$mi['need'], 3, true);
    }
    return $out;
}

/** What the site knows about a game: its requirements and the checker link. */
function rocAgentGameInfo(array $g): array {
    $spec = function ($b) { return is_array($b) ? implode(' / ', array_filter([$b['gpu'] ?? '', $b['cpu'] ?? '', $b['ram'] ?? '', $b['storage'] ?? ''])) : ''; };
    return ['title' => (string)$g['t'], 'year' => $g['y'] ?? null, 'genre' => $g['g'] ?? null,
            'minimum' => $spec($g['min'] ?? null), 'recommended' => $spec($g['rec'] ?? null), 'checker' => (string)($g['u'] ?? '/compatibility/'),
            'requirements_page' => $g['page'] ?? null];
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
    // 👍 / 👎 on each answer (Deploy 64): the column is added once to the table made by Deploy 55.
    try { $pdo->query('SELECT vote FROM roc_agent_chats LIMIT 1'); }
    catch (\Throwable $e) { try { $pdo->exec('ALTER TABLE roc_agent_chats ADD COLUMN vote ' . ($sqlite ? 'INTEGER DEFAULT 0' : 'TINYINT NOT NULL DEFAULT 0')); } catch (\Throwable $e2) {} }
    $pdo->exec($sqlite
        ? 'CREATE TABLE IF NOT EXISTS roc_agent_pcs (user_id INTEGER PRIMARY KEY, gpu_score REAL, cpu_score REAL, ram_gb REAL, label TEXT, updated_at TEXT)'
        : 'CREATE TABLE IF NOT EXISTS roc_agent_pcs (user_id INT UNSIGNED NOT NULL PRIMARY KEY, gpu_score DECIMAL(4,1) NULL, cpu_score DECIMAL(4,1) NULL, ram_gb DECIMAL(5,1) NULL,
           label VARCHAR(300) NOT NULL DEFAULT \'\', updated_at DATETIME NOT NULL) DEFAULT CHARSET=utf8mb4');
    $done = true;
}

/** Visitor key for the limits: a daily salted hash, the IP itself is never stored. */
function rocAgentIpHash(): string {
    require_once __DIR__ . '/client-ip.php';
    return substr(hash('sha256', gmdate('Y-m-d') . '|' . rocClientIp() . '|roc-agent'), 0, 16);
}

/* ------------------------------------------------------ the website's own data */

/**
 * What Run On Console has, for questions about the site: the product categories with their
 * number of products, a product the question names (its page, what it is for), and the newest
 * blog posts and news when asked.
 */
function rocAgentSite(PDO $pdo, string $q): array {
    $out = [];
    $t = ' ' . rocAgentNorm($q) . ' ';
    try {
        $cats = $pdo->query("SELECT c.slug, c.name, (SELECT COUNT(*) FROM products p WHERE p.category_slug = c.slug AND p.status = 'published') AS n
                               FROM product_categories c WHERE c.status = 'published' ORDER BY c.sort_rank, c.name")->fetchAll(PDO::FETCH_ASSOC);
        $out['product_categories'] = array_map(function ($c) { return ['name' => $c['name'], 'products' => (int)$c['n'], 'page' => '/category/' . $c['slug'] . '/']; }, $cats);
        $out['products_total'] = array_sum(array_column($out['product_categories'], 'products'));
    } catch (\Throwable $e) {}
    // A product named in the question (its own page on the site; never a price).
    try {
        $rows = $pdo->query("SELECT slug, title, brand, category, short_desc, best_for, summary FROM products WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC);
        $hit = [];
        foreach ($rows as $r) {
            $n = rocAgentNorm((string)$r['title']);
            $short = rocAgentNorm(preg_replace('/\(.*?\)/', '', (string)$r['title']));
            foreach (array_unique([$n, $short]) as $k) if (strlen($k) >= 6 && strpos($t, ' ' . $k . ' ') !== false) { $hit[$r['slug']] = [strlen($k), $r]; }
        }
        uasort($hit, function ($a, $b) { return $b[0] <=> $a[0]; });
        // Our "X vs Y" pages with a product the question names, or any when it asks to compare (Deploy 90).
        $wantsCompare = (bool)preg_match('/vs|versus|compare|comparison|better|difference|ya\s+phir|konsa|kaunsa|kon sa|kaun sa|behtar/i', $q);
        if (($hit || $wantsCompare) && is_file(__DIR__ . '/cms/compare-lib.php')) {
            require_once __DIR__ . '/cms/compare-lib.php';
            $ct = rocCompareTexts($pdo); $named = array_keys($hit); $cmp = [];
            foreach (rocComparePairs($pdo) as $cs => [$a, $b]) {
                if (empty($ct[$cs]['summary']) || !empty($ct[$cs]['hidden'])) continue;
                $score = (in_array($a['slug'], $named, true) ? 1 : 0) + (in_array($b['slug'], $named, true) ? 1 : 0);
                if ($score > 0 || ($wantsCompare && !$named)) $cmp[] = [$score, ['page' => '/compare/' . $cs . '/', 'name' => $a['title'] . ' vs ' . $b['title'], 'short_answer' => mb_substr((string)$ct[$cs]['summary'], 0, 300)]];
            }
            usort($cmp, function ($x, $y) { return $y[0] <=> $x[0]; });
            if ($cmp) $out['comparisons_on_this_site'] = array_map(function ($x) { return $x[1]; }, array_slice($cmp, 0, $named ? 3 : 6));
            elseif ($wantsCompare) $out['comparisons_on_this_site'] = [['page' => '/compare/', 'name' => 'All comparisons']];
        }
        foreach (array_slice($hit, 0, 2) as $h) {
            $r = $h[1];
            $out['products_named'][] = array_filter(['name' => $r['title'], 'brand' => $r['brand'], 'category' => $r['category'], 'page' => '/products/' . $r['slug'] . '/',
                'best_for' => mb_substr((string)$r['best_for'], 0, 120), 'about' => mb_substr(trim(strip_tags((string)($r['summary'] ?: $r['short_desc']))), 0, 400)]);
        }
    } catch (\Throwable $e) {}
    // Newest posts when the visitor asks about blogs, guides, news or articles (each with its real address).
    if (preg_match('/\b(blog|blogs|news|khabar|article|articles|post|posts|guide|guides|tutorial|how to|kaise|latest|naya|nayi|update|updates)\b/i', $q)) {
        try {
            // The same sections as blog-render.php (rocSectionOf): category "News" = news, "Guides" / "Guides: ..." = guides.
            $st = $pdo->query("SELECT title, slug, category FROM blogs WHERE status = 'published' ORDER BY published_at DESC LIMIT 80");
            $seen = ['blogs' => 0, 'guides' => 0, 'news' => 0];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $c = trim((string)($b['category'] ?? ''));
                $k = strcasecmp($c, 'News') === 0 ? 'news' : (preg_match('/^guides?\s*(:.*)?$/i', $c) ? 'guides' : 'blogs');
                if ($seen[$k]++ < 5) $out['latest_' . $k][] = ['title' => $b['title'], 'page' => '/' . $k . '/' . $b['slug'] . '/'];
            }
        } catch (\Throwable $e) {}
    }
    return $out;
}

/**
 * "Analyze my PC" without a game: the checker run on the best known games, so ROC can say what
 * runs well, what runs on low settings and what is too heavy.
 */
function rocAgentPcSummary(string $root, ?float $gpu, ?float $cpu, ?float $ram): ?array {
    if ($gpu === null || $cpu === null) return null;
    $games = array_slice(rocAgentGames($root), 0, 90);   // the checker's own list first, then the most popular Steam games
    $res = ['runs_well' => [], 'medium' => [], 'low_settings' => [], 'too_heavy' => []];
    $seen = [];
    foreach ($games as $g) {
        if (array_key_exists('pc', $g) && !$g['pc']) continue;   // console-only games (Zelda ...)
        $key = implode(' ', array_slice(explode(' ', rocAgentNorm((string)$g['t'])), 0, 2));
        if (isset($seen[$key])) continue;                          // the same game twice (Helldivers 2)
        $seen[$key] = 1;
        $c = rocAgentCheck($g, $gpu, $cpu, $ram);
        $k = $c['percent'] >= 90 ? 'runs_well' : ($c['percent'] >= 70 ? 'medium' : ($c['percent'] > 40 ? 'low_settings' : 'too_heavy'));
        if (count($res[$k]) < 6) $res[$k][] = preg_replace('/\s*\(.*$/', '', (string)$g['t']) . ' (' . $c['percent'] . '%)';
    }
    return $res;
}

/* -------------------------------------------------- knowledge base (roc-kb.json) */

/** ROC's gaming knowledge base (scripts/build-roc-kb.mjs: Wikidata and Wikipedia). */
function rocAgentKbData(string $root): array {
    static $kb = null;
    if ($kb !== null) return $kb;
    $kb = json_decode((string)@file_get_contents($root . '/roc-kb.json'), true);
    return $kb = is_array($kb) ? $kb : ['games' => [], 'consoles' => [], 'esports' => []];
}

/**
 * Reference facts for a question: esports results, games and consoles it names. Only these results
 * may be given as winners, so ROC does not invent them.
 */
function rocAgentKb(string $root, string $q): ?array {
    $kb = rocAgentKbData($root);
    $t = ' ' . rocAgentNorm($q) . ' ';
    // Short names people type for the full titles in the data.
    foreach (['gta v' => 'grand theft auto v', 'gta 5' => 'grand theft auto v', 'gta5' => 'grand theft auto v', 'gta vi' => 'grand theft auto vi', 'gta 6' => 'grand theft auto vi',
              'gta sa' => 'grand theft auto san andreas', 'gta san andreas' => 'grand theft auto san andreas', 'gta 4' => 'grand theft auto iv', 'gta iv' => 'grand theft auto iv',
              'rdr2' => 'red dead redemption 2', 'rdr 2' => 'red dead redemption 2', 'cs2' => 'counter strike 2', 'csgo' => 'counter strike global offensive',
              'cod' => 'call of duty', 'botw' => 'the legend of zelda breath of the wild', 'totk' => 'the legend of zelda tears of the kingdom',
              'pubg' => 'pubg battlegrounds', 'lol' => 'league of legends', 'ps5' => 'playstation 5', 'ps4' => 'playstation 4', 'ps3' => 'playstation 3', 'ps2' => 'playstation 2',
              'psp' => 'playstation portable', 'switch 2' => 'nintendo switch 2'] as $a => $full)
        if (strpos($t, ' ' . $a . ' ') !== false) $t .= ' ' . $full . ' ';
    $out = [];
    // Esports: a question about a winner, a tournament or an esports game.
    $series = ['valorant' => '/valorant/i', 'league of legends|\blol\b|worlds|\bmsi\b|lck|lpl' => '/league of legends|mid-season|\blck\b|\blpl\b|\blcp\b/i',
               'dota|the international|\bti\b' => '/the international|dota/i', 'pubg' => '/pubg/i', 'free fire' => '/free fire/i',
               'mobile legends|mlbb|\bm[0-9]\b' => '/^m\d|mobile legends|\bmsc\b/i', 'counter strike|\bcs ?2?\b|csgo|major' => '/major|counter-strike|pgl|blast|iem/i',
               'fortnite' => '/fortnite/i', 'overwatch' => '/overwatch/i', 'rocket league' => '/rocket league/i', 'esports world cup|ewc' => '/esports world cup/i'];
    $esQ = (bool)preg_match('/\b(won|win|winner|winners|champion|champions|jeet|jeeta|jeeti|jeete|tournament|final|finals|esports|world cup|masters|major)\b/i', $q);
    $pick = [];
    foreach ($series as $k => $re) if (preg_match('/' . $k . '/i', $t)) foreach ((array)$kb['esports'] as $e) if (preg_match($re, $e['t'] . ' ' . ($e['game'] ?? ''))) $pick[] = $e;
    if (!$pick && $esQ) foreach ((array)$kb['esports'] as $e) if ($e['src'] === 'Wikipedia') $pick[] = $e;
    if ($pick) {
        if (preg_match('/\b(20[0-3]\d)\b/', $q, $ym)) { $f = array_values(array_filter($pick, function ($e) use ($ym) { return (int)$e['y'] === (int)$ym[1]; })); if ($f) $pick = $f; }
        usort($pick, function ($a, $b) { return [(int)$b['y'], $a['src'] === 'Wikipedia' ? 0 : 1] <=> [(int)$a['y'], $b['src'] === 'Wikipedia' ? 0 : 1]; });
        $out['esports_results'] = array_map(function ($e) { return array_filter(['event' => $e['t'], 'year' => $e['y'], 'winner' => $e['winner'], 'runner_up' => $e['runner_up'] ?? null, 'source' => $e['src']]); },
                                            array_slice($pick, 0, 10));
    }
    // Games and consoles named in the question (the longest names first).
    foreach (['games' => 2, 'consoles' => 2] as $key => $n) {
        $found = [];
        foreach ((array)$kb[$key] as $g) {
            $name = rocAgentNorm((string)$g['n']);
            if (strlen($name) < 4 || in_array($name, ['game', 'games', 'play', 'online', 'world', 'racing', 'tetris 99'], true)) continue;
            if (strpos($t, ' ' . $name . ' ') !== false) $found[] = [$name, $g];
        }
        usort($found, function ($a, $b) { return strlen($b[0]) <=> strlen($a[0]); });
        if ($found) $out[$key] = array_map(function ($x) { return array_filter($x[1]); }, array_slice($found, 0, $n));
    }
    if (!$out) return null;
    $out['source'] = 'Wikidata and Wikipedia (' . ($kb['updated'] ?? '') . ')';
    return $out;
}

/* ------------------------------------------------------------------ answer */

/**
 * One answer. $history: [['role' => 'user'|'assistant', 'content' => '...'], ...], the last one the question.
 * Returns ['reply' => text, 'game' => check|null, 'products' => [...], 'engine' => model|'basic', 'lang' => ...].
 */
function rocAgentAnswer(PDO $pdo, string $root, array $history, bool $useAi = true, ?string $image = null, array $device = [], int $uid = 0): array {
    $q = (string)end($history)['content'];
    if ($uid > 0 && preg_match('/forget my pc|delete my pc|mera pc (bhool|bhul|delete|hata)|pc (bhool|bhul) ja/i', $q)) {
        try { $pdo->prepare('DELETE FROM roc_agent_pcs WHERE user_id = ?')->execute([$uid]); } catch (\Throwable $e) {}
        $ur = rocAgentLang($q) === 'roman-urdu';
        return ['reply' => $ur ? 'Theek hai, aap ka saved PC delete kar diya. Jab chahein dobara specs bata dein.' : 'Done, I forgot your saved PC. Tell me the specs again any time.',
                'game' => null, 'products' => [], 'engine' => 'basic', 'lang' => $ur ? 'roman-urdu' : 'english'];
    }
    // A dxdiag report (the chat box reads the file): its parts become a clear line for the rest.
    $specs = rocAgentSpecsFromText($q);
    if ($specs && preg_match('/dxdiag|Processor:|Card name:/i', $q)) {
        $q = 'My PC: graphics card ' . ($specs['gpu'] ?: 'unknown') . ', processor ' . ($specs['cpu'] ?: 'unknown') . ', ' . ($specs['ram'] ? $specs['ram'] . ' GB RAM' : 'RAM unknown')
           . ($specs['model'] ? ', ' . $specs['model'] : '') . ($specs['os'] ? ', ' . $specs['os'] : '') . '. ' . trim(preg_replace('/^.*?\n\n/s', '', (string)preg_replace('/(^|\n)\s*(Processor|Memory|Card name|Operating System|System Model|System Manufacturer|Name|GPU):.*$/mi', '', $q)));
        $history[count($history) - 1]['content'] = $q;
    }
    $lang0 = rocAgentLang($q);
    if (rocAgentBlocked($q)) return ['reply' => $lang0 === 'roman-urdu' ? 'Maaf kijiye, main is qisam ki baat ka jawab nahi deta. Aap gaming se related kuch bhi pooch sakte hain!'
                                                                          : 'Sorry, I do not answer that kind of message. Ask me anything about gaming!',
                                     'game' => null, 'products' => [], 'engine' => 'blocked', 'lang' => $lang0];
    $userText = implode("\n", array_map(function ($m) { return $m['content']; }, array_filter($history, function ($m) { return $m['role'] === 'user'; })));
    $lang = rocAgentLang($q);
    // Hardware and game: the newest mention wins, so earlier messages still count ("RTX 3060 hai" then "GTA 5?").
    $gpu = rocAgentGpuScore($q) ?? rocAgentGpuScore($userText);
    // What the browser reports (WebGL) is not used for the PC: it is often wrong (laptops with two cards,
    // phones), so ROC asks for the parts instead (owner, 2026-10-15).
    $cpu = rocAgentCpuScore($q) ?? rocAgentCpuScore($userText);
    $ram = rocAgentRam($q) ?? rocAgentRam($userText);
    // Remember the PC for this member; use the saved one when nothing new is said.
    $pcNowMsg = rocAgentGpuScore($q) !== null || rocAgentCpuScore($q) !== null || rocAgentRam($q) !== null;
    if ($uid > 0 && $pcNowMsg) rocAgentPcSave($pdo, $uid, rocAgentGpuScore($q), rocAgentCpuScore($q), rocAgentRam($q), mb_substr(trim(preg_replace('/\s+/', ' ', $q)), 0, 200));
    $saved = $uid > 0 ? rocAgentPcGet($pdo, $uid) : null;
    if ($saved) { $gpu = $gpu ?? $saved['gpu']; $cpu = $cpu ?? $saved['cpu']; $ram = $ram ?? $saved['ram']; }
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

    $facts = ['site' => 'Run On Console (runonconsole.com): gaming and PC hardware picks, a Can I run it checker for 2,000+ PC games (a page for each at /can-i-run/), gaming platform guides, blogs and news.',
              'about_run_on_console' => rocAgentAbout(),
              'this_website' => rocAgentSite($pdo, $q),
              'pc_summary' => !$game ? rocAgentPcSummary($root, $gpu, $cpu, $ram) : null,
              'reference' => rocAgentKb($root, $q . ' ' . (count($history) > 1 ? (string)($history[count($history) - 3]['content'] ?? '') : '')),
              'visitor_pc' => ['graphics_card_score' => $gpu, 'processor_score' => $cpu, 'ram_gb' => $ram,
                               'saved_from_earlier' => $saved ? $saved['label'] : null,
                               'known' => array_values(array_filter([$gpu !== null ? 'graphics card' : '', $cpu !== null ? 'processor' : '', $ram !== null ? 'RAM' : ''])),
                               'scale' => '1.8 = integrated graphics, 4.5 = GTX 1650 / i5 8th gen, 7 = RTX 3060 / i5 12th gen, 8.5 = RTX 4070 / i7 12th gen, 10 = RTX 4080+ / i9'],
              'game_found' => $game ? rocAgentGameInfo($game) : null, 'check' => $check, 'upgrade_options' => $check ? rocAgentUpgrades($check) : null,
              'products_shown_as_cards' => $products,
              'platform' => rocAgentPlatform($pdo, $q),
              'recommendations' => $products ? 'shown' : 'only when the visitor asks for gear or upgrades: then tell them to ask, for example "suggest a graphics card"',
              'catalog_categories' => 'mice, keyboards, headsets and audio, speakers, monitors, graphics cards, processors, RAM, PC cases, gaming chairs, handhelds, laptops',
              'links' => ['checker' => '/compatibility/', 'game_requirements' => '/can-i-run/', 'products' => '/products/', 'platforms' => '/gaming-platforms/', 'blogs' => '/blogs/', 'guides' => '/guides/',
                          'news' => '/news/', 'contact' => '/contact/', 'privacy_policy' => '/privacy-policy/', 'terms' => '/terms-and-conditions/', 'comparisons' => '/compare/']];

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
        // The named upgrade: "graphics card GeForce RTX 2060 (ya Radeon RX 5600 XT)", "RAM 16 GB".
        $up = rocAgentUpgrades($check); $named = [];
        foreach (['graphics_card' => 'graphics card', 'processor' => 'processor', 'ram' => 'RAM'] as $k => $label) {
            if (!isset($up[$k])) continue;
            $v = $up[$k];
            $named[] = $label . ' ' . (is_array($v) ? $v[0] . (isset($v[1]) ? ($ur ? ' (ya ' : ' (or ') . $v[1] . ')' : '') : $v);
        }
        if ($named) $s .= $ur ? ' Agar aap ' . implode(', ', $named) . ' kar lein to ye game recommended (high) settings par chalega.'
                              : ' With ' . implode(', ', $named) . ' it reaches the recommended (high) settings.';
        if ($check['unknown_parts']) $s .= $ur ? ' (' . implode(', ', $check['unknown_parts']) . ' nahi bataya, is liye wo hisaab mein nahi.)' : ' (Not counted: ' . implode(', ', $check['unknown_parts']) . '.)';
        return $s;
    }
    if ($game) return $ur ? "{$game['t']} check karne ke liye apna graphics card, processor aur RAM batayein. Pata nahi? Win + R dabayein, dxdiag likh kar Enter: System tab mein Processor aur Memory (RAM), Display tab mein graphics card ka naam hai. Ya chat ka 🖥 button dabakar dxdiag file upload kar dein."
                          : "To check {$game['t']}, tell me your graphics card, processor and RAM. Not sure? Press Win + R, type dxdiag, Enter: the System tab shows Processor and Memory (RAM), the Display tab the graphics card. Or upload the dxdiag file with the 🖥 button in this chat.";
    if ($products) return $ur ? 'Ye hamari site ke kuch achhe options hain:' : 'Here are some good picks from our site:';
    if ($gpu !== null || $cpu !== null) return $ur ? 'Shukriya! Ab game ka naam batayein, main check kar deta hoon. Agar game hamari list mein na ho to /compatibility/ par official requirements dekh sakte hain.'
                                                    : 'Thanks! Now tell me the game and I will check it. If it is not in our list, compare the official requirements on /compatibility/.';
    return $ur ? 'Main ROC hoon. Game ka naam aur apna PC (graphics card, processor, RAM) batayein, ya poochein ke kaunsa mouse, keyboard ya headset lena chahiye.'
               : 'I am ROC. Tell me a game and your PC (graphics card, processor, RAM), or ask which mouse, keyboard or headset to get.';
}
