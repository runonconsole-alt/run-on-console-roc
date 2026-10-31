<?php
/**
 * Run On Console — AI discovery files written from the live site (Deploy 97).
 *
 *   /.well-known/ai.txt   which AI crawlers may read and cite the site, and where the files below are
 *   /ai/summary.json      what the site is, its sections and how big it is
 *   /ai/faq.json          short answers about the site, for AI search
 *   /ai/service.json      what visitors can do on the site (free tools, guides, news)
 *
 * rocAiFilesWrite() rebuilds them from the database and dates the home page (rocLayerApply puts
 * the newest post or product change in its WebPage dateModified). It runs from cli-ai-files.php
 * and after each blog post or news story goes live (cli-blog-scheduler.php), next to llms.txt.
 */
if (defined('ROC_AI_FILES_LIB')) return;
define('ROC_AI_FILES_LIB', 1);

/** Newest real change shown on the home page: a published post or a product edit (Unix time, 0 if none). */
function rocAiHomeModified(PDO $pdo): int {
    $t = 0;
    foreach (["SELECT MAX(published_at) FROM blogs WHERE status = 'published'",
              "SELECT MAX(COALESCE(content_modified_at, updated_at, created_at)) FROM products WHERE status = 'published'",
              "SELECT MAX(COALESCE(updated_at, created_at)) FROM products WHERE status = 'published'"] as $sql) {
        try { $v = (string)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { continue; }   // content_modified_at is newer than some installs
        if ($v !== '' && strpos($v, '0000-00-00') !== 0 && ($u = strtotime($v)) && $u <= time()) $t = max($t, $u);
    }
    return $t;
}

/** [published path => file contents] */
function rocAiFilesBuild(PDO $pdo): array {
    $B = 'https://runonconsole.com';
    $n = function (string $sql) use ($pdo): int { try { return (int)$pdo->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; } };
    $products  = $n("SELECT COUNT(*) FROM products WHERE status = 'published'");
    $platforms = $n("SELECT COUNT(*) FROM gaming_categories WHERE status = 'published'");
    $posts     = $n("SELECT COUNT(*) FROM blogs WHERE status = 'published' AND (category IS NULL OR category <> 'News')");
    $news      = $n("SELECT COUNT(*) FROM blogs WHERE status = 'published' AND category = 'News'");
    $mod = rocAiHomeModified($pdo);
    $updated = gmdate('c', $mod ?: time());
    $json = function (array $a): string { return json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"; };

    $desc = 'Run On Console (ROC) is an independent gaming and PC hardware site: product picks with key specs and Amazon links, '
          . 'a free "Can I run it" checker built on official game requirements, guides to every gaming platform, buying guides '
          . 'and gaming news checked against trusted sources.';
    $contact = ['email' => 'contact@runonconsole.com', 'url' => $B . '/contact/'];

    $out = [];
    $out['.well-known/ai.txt'] =
          "# Run On Console - AI access policy\n"
        . "# " . $B . "/\n\n"
        . "User-Agent: *\nAllow: /\nDisallow: /cms/\nDisallow: /api/\nDisallow: /account/\n\n"
        . "# AI assistants and search engines may read, summarise and cite every public page.\n"
        . "# Please link the specific runonconsole.com page you used.\n"
        . "# Prices and stock are not listed on the site; they change on Amazon.\n\n"
        . "Contact: mailto:contact@runonconsole.com\n"
        . "Sitemap: " . $B . "/sitemap.xml\n"
        . "LLMs: " . $B . "/llms.txt\n"
        . "Feed: " . $B . "/feed.xml\n"
        . "Summary: " . $B . "/ai/summary.json\n"
        . "FAQ: " . $B . "/ai/faq.json\n"
        . "Service: " . $B . "/ai/service.json\n";

    $out['ai/summary.json'] = $json([
        'name' => 'Run On Console', 'alternateName' => 'ROC', 'url' => $B . '/', 'description' => $desc,
        'language' => 'en', 'founded' => '2026', 'location' => 'Lahore, Pakistan', 'contact' => $contact,
        'topics' => ['PC game requirements', 'gaming hardware', 'PC hardware', 'gaming platforms', 'buying guides', 'gaming news'],
        'sections' => [
            ['name' => 'Can I Run It? checker', 'url' => $B . '/compatibility/'],
            ['name' => 'Game requirement pages', 'url' => $B . '/can-i-run/'],
            ['name' => 'Products', 'url' => $B . '/products/', 'count' => $products],
            ['name' => 'Gaming platforms', 'url' => $B . '/gaming-platforms/', 'count' => $platforms],
            ['name' => 'Blogs and guides', 'url' => $B . '/blogs/', 'count' => $posts],
            ['name' => 'News', 'url' => $B . '/news/', 'count' => $news],
        ],
        'llms' => $B . '/llms.txt', 'sitemap' => $B . '/sitemap.xml', 'updated' => $updated,
    ]);

    // The first six are the home page FAQ word for word (src/data/initialData.js PAGE_FAQS.home).
    $faq = [
        ['What is Run On Console?', $desc],
        ['Is the "Can I run it" checker free?', 'Yes. The checker is free and needs no account. It compares your PC with the official minimum and recommended requirements of 2,400+ games, taken from Steam or the game\'s publisher.'],
        ['How are the product picks chosen?', 'The Run On Console team picks gear from manufacturer specs, official data and published reviews, and explains who each product is for. We do not claim lab testing. Our product picks and rankings cannot be bought; a brand can pay for a sponsored article, and every one is labelled Sponsored.'],
        ['Does Run On Console show prices?', 'No. Prices and stock change all the time, so we link straight to Amazon, where you always see the current price and availability.'],
        ['Are the Amazon links affiliate links?', 'Yes. As an Amazon Associate we earn from qualifying purchases, at no extra cost to you. It keeps the site free and independent.'],
        ['How is the gaming news checked?', 'Every news story is checked against at least two trusted sources, and the sources are listed with the story.'],
        ['Which gaming platforms are covered?', 'PC, consoles, handhelds, retro systems, VR and cloud gaming' . ($platforms ? ': ' . $platforms . ' platform guides' : '') . ' at ' . $B . '/gaming-platforms/.'],
        ['How do I contact Run On Console?', 'Email contact@runonconsole.com or use the form at ' . $B . '/contact/.'],
    ];
    $out['ai/faq.json'] = $json([
        'name' => 'Run On Console FAQ', 'url' => $B . '/', 'updated' => $updated,
        'faqs' => array_map(function ($f) { return ['question' => $f[0], 'answer' => $f[1]]; }, $faq),
    ]);

    $out['ai/service.json'] = $json([
        'name' => 'Run On Console', 'url' => $B . '/', 'type' => 'Independent gaming information website', 'price' => 'Free', 'account_required' => false,
        'capabilities' => ['Check if a PC can run a game (official minimum and recommended requirements)', 'Gaming and PC hardware picks with key specs',
                           'Gaming platform guides', 'Buying guides and how-to articles', 'Gaming news checked against trusted sources'],
        'services' => [
            ['name' => 'Can I Run It? checker', 'url' => $B . '/compatibility/', 'description' => 'Compare a PC with the official minimum and recommended requirements of 2,400+ games.'],
            ['name' => 'Game requirement pages', 'url' => $B . '/can-i-run/', 'description' => 'The PC requirements of each game on its own page.'],
            ['name' => 'Gaming gear picks', 'url' => $B . '/products/', 'description' => $products . ' gaming and PC hardware picks with key specs and Amazon links.'],
            ['name' => 'Gaming platform guides', 'url' => $B . '/gaming-platforms/', 'description' => 'Guides to every gaming platform, from PC and consoles to handhelds, VR and cloud gaming.'],
            ['name' => 'Buying guides and how-tos', 'url' => $B . '/blogs/', 'description' => 'Buying guides, comparisons and how-to articles.'],
            ['name' => 'Gaming news', 'url' => $B . '/news/', 'description' => 'Gaming and hardware news, each story checked against trusted sources.'],
        ],
        'contact' => $contact, 'updated' => $updated,
    ]);
    return $out;
}

/** Writes the files into public_html. Returns the number written. */
function rocAiFilesWrite(PDO $pdo, string $root): int {
    $done = 0;
    foreach (rocAiFilesBuild($pdo) as $rel => $body) {
        $file = $root . '/' . $rel;
        if (!is_dir(dirname($file)) && !@mkdir(dirname($file), 0755, true)) continue;
        $tmp = $file . '.tmp' . getmypid();
        if (@file_put_contents($tmp, $body) !== false && @rename($tmp, $file)) $done++;
    }
    return $done;
}

/** Puts the newest change into the home page's WebPage dateModified. Returns true when index.html changed. */
function rocAiDateHome(PDO $pdo, string $root): bool {
    require_once __DIR__ . '/site-layer-lib.php';
    $mod = rocAiHomeModified($pdo);
    if (!$mod) return false;
    $L = rocLayerFromDb($pdo, $root);
    $L['home_modified'] = gmdate('c', $mod);
    return rocLayerApplyFiles($root, $L, ['/']) > 0;
}
