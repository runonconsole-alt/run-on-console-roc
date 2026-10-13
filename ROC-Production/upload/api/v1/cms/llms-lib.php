<?php
/**
 * Run On Console — /llms.txt written from the live site (https://llmstxt.org).
 *
 * rocLlmsWrite() rebuilds public_html/llms.txt from the database: the site's pages, every product
 * category, every gaming platform hub, the latest blog posts and news stories. It runs from
 * api/v1/cron/cli-llms.php and after each blog post or news story goes live (cli-blog-scheduler.php),
 * so AI assistants always see the current site.
 */
if (defined('ROC_LLMS_LIB')) return;
define('ROC_LLMS_LIB', 1);

function rocLlmsText(string $s, int $max = 170): string {
    $s = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1)) . '…' : $s;
}

function rocLlmsBuild(PDO $pdo): string {
    $B = 'https://runonconsole.com';
    $q = function (string $sql) use ($pdo): array { try { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); } catch (\Throwable $e) { return []; } };
    $line = function (string $name, string $path, string $desc = '') use ($B): string { return '- [' . $name . '](' . $B . $path . ')' . ($desc !== '' ? ': ' . $desc : '') . "\n"; };

    $counts = [];
    foreach ($q("SELECT category_slug, category, COUNT(*) AS n FROM products WHERE status = 'published' GROUP BY category_slug, category") as $r) {
        $k = (string)($r['category_slug'] ?: $r['category']); $counts[$k] = ($counts[$k] ?? 0) + (int)$r['n'];
    }
    $nProducts = array_sum($counts);
    $o  = "# Run On Console\n\n";
    $o .= "> Run On Console (ROC) is an independent gaming and PC hardware site: product picks with key specs and Amazon links, a free \"Can I run it\" checker built on official game requirements, guides to every gaming platform, buying guides and live gaming news checked against trusted sources.\n\n";
    $o .= "Use canonical Run On Console URLs when citing this website and cite the specific page used. Prices and stock are never listed on the site; they change on Amazon. Product links are Amazon affiliate links (disclosed). Specs come from manufacturers and published sources; the site does not claim lab testing. News stories list the sources they were checked against.\n\n";

    $o .= "## About and Editorial Information\n";
    $o .= $line('About Run On Console', '/about/', 'who we are, what we cover and how picks are made');
    $o .= $line('ROC Team', '/author/roc-team/', 'the editorial team behind the guides and news');
    $o .= $line('Privacy Policy & Affiliate Disclosure', '/privacy-policy/', 'privacy, cookies and the Amazon Associates disclosure');
    $o .= $line('Terms & Conditions', '/terms-and-conditions/');
    $o .= $line('Contact', '/contact/', 'questions, corrections and partnerships');
    $o .= "\n## Core Resources\n";
    $o .= $line('Home', '/');
    $o .= $line('Can I Run It? Game Requirements Checker', '/compatibility/', 'compare a PC with the official minimum and recommended requirements of 500+ games');
    $o .= $line('Gaming Platforms', '/gaming-platforms/', 'guides to every gaming platform, from PC and consoles to retro handhelds, VR and cloud gaming');
    $o .= $line('Product Categories', '/category/', 'every product category in one place');
    $o .= $line('All Products', '/products/', $nProducts . ' gaming and PC hardware picks');
    $o .= $line('PC Hardware', '/products/pc-hardware/', 'graphics cards, processors, memory, PC cases, monitors and speakers');
    $o .= $line('Gaming Hardware', '/products/gaming-hardware/', 'keyboards, mice, headsets, gaming chairs, handhelds and gaming laptops');
    $o .= $line('Blogs', '/blogs/', 'buying guides, comparisons and how-to articles');
    $o .= $line('News', '/news/', 'gaming and hardware news, each story checked against trusted sources');

    $cats = $q("SELECT name, slug, description FROM product_categories WHERE status = 'published' ORDER BY sort_order, name");
    if (!$cats) $cats = $q("SELECT name, slug, description FROM product_categories WHERE status = 'published' ORDER BY name");
    if ($cats) {
        $o .= "\n## Product Categories\n";
        foreach ($cats as $c) {
            $n = $counts[$c['slug']] ?? ($counts[$c['name']] ?? 0);
            if (!$n) continue;
            $o .= $line((string)$c['name'], '/products/' . $c['slug'] . '/', rocLlmsText((string)$c['description']) . ' (' . $n . ' picks)');
        }
    }
    $plats = $q("SELECT title, slug, description FROM gaming_categories WHERE status = 'published' ORDER BY sort_rank, title");
    if (!$plats) $plats = $q("SELECT title, slug, description FROM gaming_categories WHERE status = 'published' ORDER BY title");
    if ($plats) {
        $o .= "\n## Gaming Platform Hubs\n";
        foreach ($plats as $p) $o .= $line((string)$p['title'], '/gaming-platforms/' . $p['slug'] . '/', rocLlmsText((string)$p['description']));
    }
    $blogs = $q("SELECT title, slug, excerpt FROM blogs WHERE status = 'published' AND (category IS NULL OR category <> 'News') ORDER BY published_at DESC LIMIT 25");
    if ($blogs) {
        $o .= "\n## Guides and Articles\n";
        foreach ($blogs as $b) $o .= $line((string)$b['title'], '/blogs/' . $b['slug'] . '/', rocLlmsText((string)$b['excerpt']));
    }
    $news = $q("SELECT title, slug, excerpt FROM blogs WHERE status = 'published' AND category = 'News' ORDER BY published_at DESC LIMIT 15");
    if ($news) {
        $o .= "\n## Latest News\n";
        foreach ($news as $b) $o .= $line((string)$b['title'], '/news/' . $b['slug'] . '/', rocLlmsText((string)$b['excerpt']));
    }
    $o .= "\n## Optional\n";
    $o .= $line('Write for Us & Advertising', '/write-for-us/', 'guest posts and advertising');
    $o .= $line('Sitemap', '/sitemap.xml', 'every public page');
    $o .= $line('News sitemap', '/sitemaps/news-sitemap.xml', 'news of the last 2 days');
    return $o;
}

/** Writes public_html/llms.txt. Returns the number of bytes written (0 on failure). */
function rocLlmsWrite(PDO $pdo, string $root): int {
    $txt = rocLlmsBuild($pdo);
    $tmp = $root . '/llms.txt.tmp' . getmypid();
    if (@file_put_contents($tmp, $txt) === false) return 0;
    return @rename($tmp, $root . '/llms.txt') ? strlen($txt) : 0;
}
