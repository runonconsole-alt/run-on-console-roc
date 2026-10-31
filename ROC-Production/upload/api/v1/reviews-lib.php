<?php
/**
 * Run On Console — reviews of working with Run On Console (Deploy 151).
 *
 *   /reviews/   real reviews from brands and readers, newest first, and the form to write one
 *               (page-render.php sends the slug "reviews" here)
 *
 * Only real reviews: the form posts to /api/v1/messages.php (kind "review", CMS > Messages). Nothing is shown
 * until the owner presses "Publish this review" in the notification email (review-publish.php checks a signed
 * link); the same email has "Hide it again". A published review is a roc_messages row with "published": 1 in
 * meta_json, so no new table. No star schema: Google does not show review stars for a site's reviews of itself.
 */

/** The secret that signs the publish links: made once, kept outside public_html. */
function rocReviewSecret(string $root): string {
    $f = dirname($root) . '/roc-review-secret.txt';
    $s = is_file($f) ? trim((string)file_get_contents($f)) : '';
    if (strlen($s) < 32) {
        $s = bin2hex(random_bytes(32));
        @file_put_contents($f, $s);
        @chmod($f, 0600);
    }
    return $s;
}

function rocReviewToken(string $root, int $id, string $action): string {
    return substr(hash_hmac('sha256', $id . '|' . $action, rocReviewSecret($root)), 0, 32);
}

/** The publish and hide links for the notification email. */
function rocReviewLinks(string $root, int $id): array {
    $u = 'https://runonconsole.com/api/v1/review-publish.php?id=' . $id;
    return ['publish' => $u . '&a=publish&t=' . rocReviewToken($root, $id, 'publish'),
            'hide' => $u . '&a=hide&t=' . rocReviewToken($root, $id, 'hide')];
}

/** Published reviews, newest first. */
function rocReviewsPublished(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT id, name, message, meta_json, created_at FROM roc_messages WHERE kind = 'review' ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) { return []; }
    $out = [];
    foreach ($rows as $r) {
        $meta = json_decode((string)($r['meta_json'] ?? ''), true) ?: [];
        if (empty($meta['published'])) continue;
        $out[] = ['name' => (string)$r['name'], 'text' => (string)$r['message'], 'company' => (string)($meta['company'] ?? ''),
                  'rating' => max(1, min(5, (int)($meta['rating'] ?? 5))), 'date' => strtotime((string)$r['created_at']) ?: time()];
    }
    return $out;
}

function rocReviewsPage(string $root, PDO $pdo): void {
    $reviews = rocReviewsPublished($pdo);
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-post.html');
    $url = ROC_PUBLIC_URL . '/reviews/';
    $metaT = 'Reviews of Run On Console | Brands and Readers';
    $metaD = 'What brands, partners and readers say about working with Run On Console. Every review is real and written by the person named.';
    $mod = $reviews ? max(array_column($reviews, 'date')) : null;

    $h  = '<title>' . rocH($metaT) . "</title>\n";
    $h .= '    <meta name="description" content="' . rocH($metaD) . "\" />\n";
    // An empty page has nothing for Google yet: it is indexed from the first published review.
    $h .= '    <meta name="robots" content="' . ($reviews ? 'index, follow, max-image-preview:large' : 'noindex, follow') . "\" />\n";
    $h .= '    <link rel="canonical" href="' . $url . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"website\" />\n";
    $h .= '    <meta property="og:url" content="' . $url . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($metaT) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($metaD) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    $h .= rocJsonLd(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Reviews', 'item' => $url]]]);
    $h .= rocArticleStyles();
    $h .= '<style>.rv-grid{display:grid;gap:1rem}@media(min-width:768px){.rv-grid{grid-template-columns:1fr 1fr}}'
        . '.rv-card{border:1px solid #e2e8f0;border-radius:1.25rem;background:#fff;padding:1.2rem;display:flex;flex-direction:column;gap:.6rem}'
        . '.rv-stars{color:#f59e0b;font-size:1.05rem;letter-spacing:.1em}.rv-text{color:#334155;line-height:1.7;white-space:pre-line}'
        . '.rv-who{font:800 .9rem Outfit,Inter,sans-serif;color:#0f172a}.rv-who span{display:block;font:500 .78rem Inter,sans-serif;color:#64748b}'
        . '.rv-empty{border:1px dashed #a7f3d0;background:#f0fdf4;border-radius:1.25rem;padding:1.5rem;text-align:center;color:#065f46;font-weight:600}'
        . '.rv-form{border:1px solid #d1fae5;border-radius:1.25rem;padding:1.25rem;background:#f8fffb}.rv-form h2{font:800 1.3rem Outfit,Inter,sans-serif;color:#064e3b;margin:0 0 .25rem}'
        . '.rv-form p{font-size:.85rem;color:#64748b;margin:0 0 1rem}.rv-g{display:grid;gap:.75rem}@media(min-width:640px){.rv-g{grid-template-columns:1fr 1fr}}'
        . '.rv-form input,.rv-form textarea,.rv-form select{width:100%;border:1px solid #cbd5e1;border-radius:.75rem;padding:.7rem .85rem;font:inherit;font-size:.9rem;background:#fff;color:#0f172a}'
        . '.rv-form textarea{margin-top:.75rem;min-height:120px;resize:vertical}.rv-form select{margin-top:.75rem}'
        . '.rv-form button{margin-top:.75rem;background:#047857;color:#fff;border:0;border-radius:.75rem;padding:.75rem 1.25rem;font:800 .9rem Outfit,Inter,sans-serif;cursor:pointer}.rv-form button:disabled{opacity:.6}'
        . '.rv-msg{margin-top:.75rem;font-size:.9rem;font-weight:600}.rv-msg.ok{color:#047857}.rv-msg.bad{color:#be123c}'
        . 'html.roc-dark .rv-card{background:#0b1220;border-color:#1e293b}html.roc-dark .rv-text{color:#cbd5e1}html.roc-dark .rv-who{color:#e2e8f0}'
        . 'html.roc-dark .rv-empty{background:rgba(16,185,129,.06);border-color:rgba(52,211,153,.3);color:#a7f3d0}'
        . 'html.roc-dark .rv-form{background:rgba(16,185,129,.06);border-color:rgba(52,211,153,.3)}html.roc-dark .rv-form h2{color:#a7f3d0}'
        . 'html.roc-dark .rv-form input,html.roc-dark .rv-form textarea,html.roc-dark .rv-form select{background:#0b1220;border-color:#334155;color:#e2e8f0}</style>';
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-8">'
        . '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-medium"><a href="/" class="hover:text-emerald-600">Home</a> <span aria-hidden="true">›</span> <span class="text-slate-700">Reviews</span></nav>'
        . '<header class="space-y-3"><h1 class="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">Reviews of Run On Console</h1>'
        . '<p class="text-base sm:text-lg text-emerald-700 font-semibold">What brands, partners and readers say about working with us. Every review here is real, written by the person named, and shown after we check it.</p></header>';
    if ($reviews) {
        $m .= '<section class="rv-grid" aria-label="Reviews">';
        foreach ($reviews as $r) {
            $m .= '<div class="rv-card"><div class="rv-stars" aria-label="' . $r['rating'] . ' out of 5">' . str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) . '</div>'
                . '<p class="rv-text">' . rocH($r['text']) . '</p>'
                . '<div class="rv-who">' . rocH($r['name']) . '<span>' . ($r['company'] !== '' ? rocH($r['company']) . ' · ' : '') . date('M Y', $r['date']) . '</span></div></div>';
        }
        $m .= '</section>';
    } else {
        $m .= '<div class="rv-empty">No reviews yet. Worked with us or use the site? Be the first to write one below.</div>';
    }
    $m .= '<section class="rv-form" aria-labelledby="rv-h"><h2 id="rv-h">Write a review</h2>'
        . '<p>Brands, partners and readers are welcome. Your email is never shown; we may write to you to check the review before it goes up.</p>'
        . '<form id="rv-form" novalidate><input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">'
        . '<div class="rv-g"><input name="name" required maxlength="120" placeholder="Your name" aria-label="Your name">'
        . '<input name="email" type="email" required maxlength="150" placeholder="Your email (not shown)" aria-label="Your email"></div>'
        . '<input name="company" maxlength="120" placeholder="Company or website (optional)" aria-label="Company or website" style="margin-top:.75rem">'
        . '<select name="rating" aria-label="Rating"><option value="5">★★★★★ Excellent</option><option value="4">★★★★ Good</option><option value="3">★★★ OK</option><option value="2">★★ Poor</option><option value="1">★ Bad</option></select>'
        . '<textarea name="message" required maxlength="2000" placeholder="Your review: what did you work on with us, and how did it go?" aria-label="Your review"></textarea>'
        . '<button type="submit">Send review</button><div class="rv-msg" role="status"></div></form></section>'
        . '<p class="text-sm text-slate-500">More about us: <a class="text-emerald-700 font-bold" href="/about/">About Run On Console</a> · <a class="text-emerald-700 font-bold" href="/write-for-us/">Write for us</a> · <a class="text-emerald-700 font-bold" href="/contact/">Contact</a></p>'
        . '<script>(function(){var f=document.getElementById("rv-form");if(!f)return;f.addEventListener("submit",function(e){e.preventDefault();'
        . 'var m=f.querySelector(".rv-msg"),b=f.querySelector("button"),v=function(n){return (f.elements[n].value||"").trim()};'
        . 'if(!v("name")||v("message").length<20||!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v("email"))){m.className="rv-msg bad";m.textContent="Please write your name, a valid email and a review of at least 20 characters.";return}'
        . 'b.disabled=true;m.className="rv-msg";m.textContent="Sending…";'
        . 'fetch("/api/v1/messages.php",{method:"POST",headers:{"Content-Type":"application/json"},credentials:"same-origin",body:JSON.stringify({kind:"review",name:v("name"),email:v("email"),'
        . 'subject:"Review ("+v("rating")+"/5)",message:v("message"),meta:{rating:v("rating"),company:v("company"),page:"/reviews/"},website:v("website")})})'
        . '.then(function(r){return r.json().catch(function(){return {}})}).then(function(d){if(d&&d.success){if(window.rocTrack)rocTrack("roc_review",v("rating"),"");f.reset();m.className="rv-msg ok";m.textContent="Thank you! We check every review and publish it soon."}'
        . 'else{m.className="rv-msg bad";m.textContent=(d&&d.error)||"Your review could not be sent. Please email contact@runonconsole.com."}})'
        . '.catch(function(){m.className="rv-msg bad";m.textContent="No connection. Please try again."}).then(function(){b.disabled=false})})})();</script>'
        . '</article>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}
