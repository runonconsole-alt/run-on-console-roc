<?php
/**
 * Run On Console — one quality gate for titles and descriptions (Deploy 127).
 *
 * Owner, 2026-10-27: posts kept coming out with weak titles and descriptions ("Audeze Maxwell 2 Review RTINGS",
 * "Discover the audio-technica ath-m50x review that ...", 166-character descriptions). Every writer now runs its
 * post through rocSeoCheck() before it is saved, and a post that fails goes back to the AI with the reasons
 * (blog writer, news writer and guide writer: 2 tries). CMS > Blogs uses the same check when the owner publishes,
 * with the style rules left out ($strict = false): another review site's name and the lengths only.
 */
if (defined('ROC_SEO_CHECK_LIB')) return;
define('ROC_SEO_CHECK_LIB', 1);

const ROC_SEO_SITES = '/\b(rtings|ign|pcmag|pc ?gamer|tom\'?s ?hardware|techradar|cnet|gamespot|the verge|wirecutter|digital foundry|gamesradar|eurogamer|kotaku|notebookcheck|hardware ?unboxed|gamers ?nexus|linus tech tips|mkbhd)\b/i';
// Names that are written in capitals (or with a capital) by their makers; in lowercase they read like stuffed keywords.
const ROC_SEO_NAMES = '/\b(rtx|gtx|amd|nvidia|asus|msi|rog|hyperx|logitech|razer|corsair|steelseries|intel|ryzen|radeon|geforce|xbox|playstation|ps[45]|ddr[45]|ssd|nvme|oled|dlss|fsr|cpu|gpu|fps|audeze|sennheiser|audio-technica|blacklyte|secretlab|lg|aoc|benq|alienware|lenovo|acer)\b/';

/** Field => problem. $strict adds the style rules for the AI writers. */
function rocSeoCheck(array $p, bool $strict = true): array {
    $get = function ($k) use ($p) { return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)($p[$k] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); };
    $title = $get('title'); $mt = $get('meta_title'); $md = $get('meta_description');
    $err = [];
    foreach (['title' => 'Title', 'meta_title' => 'Meta title', 'meta_description' => 'Meta description', 'excerpt' => 'Short description', 'focus_keyword' => 'Focus keyword', 'slug' => 'Address'] as $k => $label) {
        $v = $k === 'slug' ? str_replace('-', ' ', $get($k)) : $get($k);
        if ($v !== '' && preg_match(ROC_SEO_SITES, $v, $m)) $err[$k] = $label . ' names another review site ("' . $m[1] . '"). Write ROC\'s own words, never another site\'s name.';
    }
    if ($mt !== '' && mb_strlen($mt) > 60 && !isset($err['meta_title'])) $err['meta_title'] = 'Meta title is ' . mb_strlen($mt) . ' characters; at most 60.';
    if ($md !== '' && !isset($err['meta_description'])) {
        $min = $strict ? 110 : 70;
        if (mb_strlen($md) < $min || mb_strlen($md) > 160) $err['meta_description'] = 'Meta description is ' . mb_strlen($md) . ' characters; it must be ' . $min . '-160.';
    }
    if (!$strict) return $err;

    if ($title !== '' && mb_strlen($title) > 90 && !isset($err['title'])) $err['title'] = 'Title is ' . mb_strlen($title) . ' characters; at most 90.';
    if ($mt === '' && !isset($err['meta_title'])) $err['meta_title'] = 'Meta title is missing.';
    if ($md === '' && !isset($err['meta_description'])) $err['meta_description'] = 'Meta description is missing.';
    foreach (['title' => $title, 'meta_title' => $mt, 'meta_description' => $md] as $k => $v) {
        if ($v === '' || isset($err[$k])) continue;
        // Product and brand names in lowercase: "ath-m50x", "rtx 4060", "audeze maxwell 2".
        if (preg_match_all('/\b([a-z][a-z]*-?[a-z]*\d[a-z0-9-]*)\b/', $v, $mm)) {
            $bad = array_values(array_filter($mm[1], function ($w) { return !preg_match('/^(\d|x\d|v\d|i[3579](-|$))/', $w); }));
            if ($bad) { $err[$k] = 'Write product names the way the maker does (capitals), not "' . $bad[0] . '".'; continue; }
        }
        if (preg_match(ROC_SEO_NAMES, $v, $m)) { $err[$k] = 'Write "' . $m[1] . '" the way the maker does (for example GPU, RTX, NVIDIA, Xbox), not in lowercase.'; continue; }
        if (!preg_match('/^[\p{Lu}\d"“(]/u', $v)) { $err[$k] = 'Start with a capital letter.'; continue; }
    }
    if ($md !== '' && !isset($err['meta_description']) && preg_match('/^(discover|read our|check out|learn|find out|explore)\b/i', $md))
        $err['meta_description'] = 'Do not start the meta description with "' . strtok($md, ' ') . '": start with what the reader gets.';
    $fk = mb_strtolower($get('focus_keyword'));
    if ($fk !== '' && $title !== '' && !isset($err['title']) && substr_count(mb_strtolower($title), $fk) > 1) $err['title'] = 'The focus keyword is in the title twice.';
    return $err;
}

/** At most $max characters, cut at a word (no half words). */
function rocSeoCut(string $s, int $max): string {
    $s = trim($s);
    if (mb_strlen($s) <= $max) return $s;
    $cut = mb_substr($s, 0, $max - 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim($sp ? mb_substr($cut, 0, $sp) : $cut, ' ,;:-–') . '…';
}
