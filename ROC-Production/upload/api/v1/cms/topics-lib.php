<?php
/**
 * Run On Console — topics of blogs, guides and news (Deploy 71).
 *
 * Every post can have: a hardware group (pc-hardware or gaming-hardware, the two main product
 * categories), a product category inside it (gpu, cpu, mice ...) and tags (comma separated, for search).
 * Columns blogs.topic_group, blogs.topic_cat, blogs.tags are added by cli-updates.php. The /updates/
 * page filters on them (blog-render.php).
 */
if (defined('ROC_TOPICS_LIB')) return;
define('ROC_TOPICS_LIB', 1);

/** ['pc-hardware' => ['name' => 'PC Hardware', 'cats' => [slug => name]], 'gaming-hardware' => [...]]. */
function rocTopicGroups(PDO $pdo): array {
    static $g = null;
    if ($g !== null) return $g;
    require_once __DIR__ . '/product-groups-lib.php';
    $pc = ROC_PRODUCT_GROUPS['pc-hardware']['members'];
    $names = ['pc-hardware' => 'PC Hardware', 'gaming-hardware' => 'Gaming Hardware'];
    try {
        $st = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key = 'product_groups' LIMIT 1");
        $st->execute();
        $saved = json_decode((string)$st->fetchColumn(), true);
        if (is_array($saved) && isset($saved['pc-hardware']['members']) && is_array($saved['pc-hardware']['members'])) $pc = $saved['pc-hardware']['members'];
        foreach (['pc-hardware', 'gaming-hardware'] as $k) if (!empty($saved[$k]['name'])) $names[$k] = (string)$saved[$k]['name'];
    } catch (\Throwable $e) {}
    $g = ['pc-hardware' => ['name' => $names['pc-hardware'], 'cats' => []], 'gaming-hardware' => ['name' => $names['gaming-hardware'], 'cats' => []]];
    try {
        foreach ($pdo->query("SELECT slug, name FROM product_categories WHERE status = 'published' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) as $c)
            $g[in_array($c['slug'], $pc, true) ? 'pc-hardware' : 'gaming-hardware']['cats'][(string)$c['slug']] = (string)$c['name'];
    } catch (\Throwable $e) {}
    return $g;
}

/** "gpu" -> "Graphics Cards (GPU)" ('' when unknown). */
function rocTopicCatName(PDO $pdo, string $cat): string {
    foreach (rocTopicGroups($pdo) as $g) if (isset($g['cats'][$cat])) return $g['cats'][$cat];
    return '';
}

/** Tags as a clean list (lowercase words, at most 12). */
function rocTopicTags(string $raw): array {
    $out = [];
    foreach (preg_split('/[,;#\n]+/', mb_strtolower($raw)) as $t) {
        $t = trim((string)preg_replace('/[^\p{L}\p{N} +.\-]+/u', '', $t));
        if ($t !== '' && mb_strlen($t) <= 40 && !in_array($t, $out, true)) $out[] = $t;
    }
    return array_slice($out, 0, 12);
}

/** Do the topic columns exist? */
function rocTopicColumns(PDO $pdo): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try { $pdo->query('SELECT topic_group, topic_cat, tags FROM blogs LIMIT 1'); $ok = true; } catch (\Throwable $e) { $ok = false; }
    return $ok;
}
