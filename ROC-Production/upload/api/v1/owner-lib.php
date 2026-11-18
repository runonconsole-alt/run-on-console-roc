<?php
/**
 * Run On Console — "Meet the owner" (Deploy 184): /meet-the-owner/
 *
 * Owner, 2026-11-17: a page about the person behind the site, from his own website omarabobakar.com (career,
 * education, skills, projects), linked from the About page. page-render.php sends the slug "meet-the-owner" here.
 * Facts only from omarabobakar.com and the site itself; how the articles are made is told as it is (AI-assisted
 * drafts from cited sources that pass our checks), never as "Omar writes every article".
 */

const ROC_OWNER_PATH = '/meet-the-owner/';
const ROC_OWNER_SAMEAS = ['https://omarabobakar.com/', 'https://www.linkedin.com/in/omar-abo-bakar-21ot02/', 'https://github.com/omarabobakar',
                          'https://medium.com/@omarabobakar21', 'https://www.quora.com/profile/Omar-Abo-Bakar', 'https://www.facebook.com/omarabobakar02/',
                          'https://www.instagram.com/omarabobakar_/'];
const ROC_OWNER_CHANGED = '2026-11-17';

function rocOwnerPage(string $root): void {
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-post.html');
    $url = ROC_PUBLIC_URL . ROC_OWNER_PATH;
    $metaT = 'Meet the Owner: Omar Abobakar, Founder of Run On Console';
    $metaD = 'Omar Abobakar founded Run On Console in Lahore. An SEO specialist with a Computer Science degree, he built the site so gamers get plain, free answers.';
    $mod = (int)strtotime(ROC_OWNER_CHANGED . ' 12:00 UTC');
    $img = ROC_PUBLIC_URL . '/images/omar-abobakar.webp';
    $exp = [
        ['Sep 2026 – today', 'Search Engine Optimization Specialist', 'insuremart.pk, Lahore'],
        ['Nov 2025 – Sep 2026', 'SEO Executive', 'Tkxel, Lahore: Python automation and AI agents for indexing checks, sitemaps and template-based SEO content'],
        ['Jun 2025 – Nov 2025', 'SEO Specialist', 'Quick Share Marketing, Lahore: SEO for 10+ US-based brands'],
        ['Jan 2025 – Jun 2025', 'Data Annotator', 'Akademos, Lahore: data labelling for AI and machine learning models'],
        ['Apr 2023 – Dec 2024', 'SEO Specialist (contract)', 'TechX Marketer, remote: SEO for 8+ service-based websites'],
        ['Jun 2022 – Aug 2022', 'SEO Intern', 'WADIC, remote'],
    ];
    $edu = [
        ['2020 – 2024', 'BS Computer Science', 'University of Management and Technology (UMT), Lahore'],
        ['2025', 'Google Data Analytics Professional Certificate', 'Coursera'],
        ['Dec 2024', 'Microsoft Certified: Azure AI Fundamentals', 'Microsoft'],
        ['2022', 'Digital Marketing Certification', 'NCC Lahore'],
    ];
    $faqs = [
        ['Who owns Run On Console?', 'Omar Abobakar, an SEO specialist from Lahore, Pakistan. He founded Run On Console in 2026 and runs it independently: no brand or publisher owns it.'],
        ['Who writes the articles on Run On Console?', 'Blog posts, guides and news are drafted with AI from cited sources and must pass the site\'s own checks (sources, facts, links and titles) before they go live. Omar sets those checks and corrects anything readers report.'],
        ['Are the game requirements and picks paid for?', 'No. Game requirements come from each game\'s official Steam page, picks cannot be bought, and paid articles are labelled Sponsored. The site earns from Amazon links and ads.'],
        ['How can I contact Omar?', 'Through the Run On Console contact page, on LinkedIn, or on his own website omarabobakar.com.'],
    ];
    $ld = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'About', 'item' => ROC_PUBLIC_URL . '/about/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => 'Meet the owner', 'item' => $url]]],
        ['@context' => 'https://schema.org', '@type' => 'ProfilePage', 'url' => $url, 'name' => $metaT, 'dateModified' => gmdate('c', $mod),
         'mainEntity' => ['@type' => 'Person', '@id' => $url . '#omar', 'name' => 'Omar Abobakar', 'url' => 'https://omarabobakar.com/', 'image' => $img,
            'jobTitle' => 'Founder of Run On Console', 'description' => 'AI-driven SEO specialist and founder of Run On Console.',
            'worksFor' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
            'alumniOf' => ['@type' => 'CollegeOrUniversity', 'name' => 'University of Management and Technology'],
            'homeLocation' => ['@type' => 'Place', 'name' => 'Lahore, Pakistan'],
            'knowsAbout' => ['Search engine optimization', 'Answer engine optimization', 'Generative engine optimization', 'Programmatic SEO', 'Python', 'PC gaming hardware'],
            'knowsLanguage' => ['English', 'Urdu', 'Punjabi'], 'sameAs' => ROC_OWNER_SAMEAS]],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
            return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $faqs)],
    ];
    $h  = '<title>' . rocH($metaT) . "</title>\n";
    $h .= '    <meta name="description" content="' . rocH($metaD) . "\" />\n";
    $h .= "    <meta name=\"robots\" content=\"index, follow, max-image-preview:large\" />\n";
    $h .= '    <link rel="canonical" href="' . $url . "\" />\n";
    $h .= "    <meta property=\"og:type\" content=\"profile\" />\n";
    $h .= '    <meta property="og:url" content="' . $url . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($metaT) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($metaD) . "\" />\n";
    $h .= '    <meta property="og:image" content="' . $img . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    foreach ($ld as $j) $h .= rocJsonLd($j);
    $h .= rocArticleStyles();
    $h .= '<style>.ow-hero{display:flex;flex-direction:column;gap:1.2rem;align-items:center;text-align:center}@media(min-width:640px){.ow-hero{flex-direction:row;text-align:left;align-items:flex-start}}'
        . '.ow-hero img{width:150px;height:150px;border-radius:1.5rem;object-fit:cover;box-shadow:0 12px 30px -12px rgba(4,120,87,.5)}'
        . '.ow-facts{display:grid;grid-template-columns:repeat(2,1fr);gap:.7rem}@media(min-width:640px){.ow-facts{grid-template-columns:repeat(4,1fr)}}'
        . '.ow-facts div{border:1px solid #d1fae5;background:#f0fdf4;border-radius:1rem;padding:.8rem;text-align:center}.ow-facts b{display:block;font:800 1.4rem Outfit,Inter,sans-serif;color:#047857}.ow-facts span{font-size:.8rem;color:#475569}'
        . '.ow-card{border:1px solid #e2e8f0;border-radius:1.25rem;background:#fff;padding:1.2rem}.ow-card h2{font:800 1.3rem Outfit,Inter,sans-serif;color:#0f172a;margin:0 0 .6rem}'
        . '.ow-tl{display:grid;gap:.7rem}.ow-tl div{display:grid;gap:.15rem;border-left:3px solid #10b981;padding-left:.8rem}.ow-tl small{color:#64748b;font-size:.78rem}.ow-tl b{color:#0f172a}'
        . '.ow-tags{display:flex;flex-wrap:wrap;gap:.4rem}.ow-tags span{border:1px solid #a7f3d0;border-radius:99px;padding:.25rem .7rem;font-size:.8rem;color:#065f46;background:#ecfdf5}'
        . '.ow-links{display:flex;flex-wrap:wrap;gap:.6rem}.ow-links a{border-radius:.8rem;padding:.6rem 1rem;font-weight:800;font-size:.88rem;text-decoration:none;border:1px solid #6ee7b7;color:#065f46}.ow-links a.p{background:#047857;color:#fff;border-color:#047857}'
        . 'html.roc-dark .ow-card{background:#0b1220;border-color:#1e293b}html.roc-dark .ow-card h2,html.roc-dark .ow-tl b{color:#e2e8f0}html.roc-dark .ow-facts div{background:rgba(16,185,129,.08);border-color:#065f46}'
        . 'html.roc-dark .ow-tags span{background:transparent;color:#a7f3d0;border-color:#065f46}html.roc-dark .ow-links a{color:#a7f3d0;border-color:#065f46}html.roc-dark .ow-links a.p{color:#fff}</style>';
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $tl = function (array $rows) { return '<div class="ow-tl">' . implode('', array_map(function ($r) { return '<div><small>' . rocH($r[0]) . '</small><b>' . rocH($r[1]) . '</b><span>' . rocH($r[2]) . '</span></div>'; }, $rows)) . '</div>'; };
    $m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-6">'
        . '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-medium"><a href="/" class="hover:text-emerald-600">Home</a> <span aria-hidden="true">›</span> <a href="/about/" class="hover:text-emerald-600">About</a> <span aria-hidden="true">›</span> <span class="text-slate-700">Meet the owner</span></nav>'
        . '<header class="ow-hero"><img src="/images/omar-abobakar.webp" alt="Omar Abobakar, founder of Run On Console" width="150" height="150" fetchpriority="high">'
        . '<div class="space-y-3"><h1 class="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">Meet the owner: Omar Abobakar</h1>'
        . '<p class="text-base sm:text-lg text-emerald-700 font-semibold">Founder of Run On Console, and an AI-driven SEO specialist from Lahore, Pakistan.</p>'
        . '<p class="text-slate-700 leading-relaxed">Hi, I&rsquo;m Omar. I started Run On Console in 2026 because the simplest gaming question, &ldquo;will this game run on my PC?&rdquo;, usually needs a download, an account or a guess. '
        . 'Here the answer comes from each game&rsquo;s official Steam requirements, it is free, and there is nothing to install.</p></div></header>';
    $m .= '<section class="ow-facts" aria-label="Omar in numbers"><div><b>4+</b><span>years in SEO</span></div><div><b>50+</b><span>websites managed</span></div><div><b>10+</b><span>US brands grown</span></div><div><b>BS CS</b><span>UMT Lahore</span></div></section>';
    $m .= '<section class="ow-card"><h2>Who I am</h2><div class="space-y-3 text-slate-700 leading-relaxed">'
        . '<p>I started in 2022 as an SEO intern, doing backlink research and keyword planning. Since then I have managed more than 50 websites and helped more than 10 US-based brands grow their search traffic, '
        . 'across e-commerce, service and local businesses. Today I work as a Search Engine Optimization Specialist at insuremart.pk.</p>'
        . '<p>With a Computer Science degree, I write my own Python scripts and AI agents instead of doing repetitive work by hand. Much of what keeps Run On Console running, '
        . 'from the game requirement pages to the sitemaps and indexing checks, comes from that habit.</p></div></section>';
    $m .= '<section class="ow-card"><h2>Why Run On Console</h2><div class="space-y-3 text-slate-700 leading-relaxed">'
        . '<p>Run On Console helps gamers choose hardware and find out whether their PC, console or handheld can run a game: free &ldquo;Can I run it?&rdquo; pages for thousands of PC games, gear picks with their key specs, platform guides and daily gaming news.</p>'
        . '<p><b>How the content is made.</b> Game requirements come from each game&rsquo;s official Steam page and are checked with the same rules everywhere on the site (<a class="text-emerald-700 font-bold" href="/how-we-check/">how we check games</a>). '
        . 'Blog posts, guides and news are drafted with AI from cited sources and have to pass our checks for sources, facts, links and titles before they go live. I set those checks, and I fix anything readers report.</p>'
        . '<p><b>Independent.</b> No brand or publisher owns the site. Picks cannot be bought; paid articles are labelled Sponsored, and the site earns from Amazon links and ads.</p></div></section>';
    $m .= '<section class="ow-card"><h2>Career</h2>' . $tl($exp) . '</section>';
    $m .= '<section class="ow-card"><h2>Education and certifications</h2>' . $tl($edu) . '</section>';
    $m .= '<section class="ow-card"><h2>What I work with</h2><div class="ow-tags">'
        . implode('', array_map(function ($t) { return '<span>' . rocH($t) . '</span>'; }, ['Technical SEO', 'On-page and off-page SEO', 'Local SEO', 'Programmatic SEO', 'AEO', 'GEO', 'Schema markup', 'Python', 'AI agents', 'Google Search Console', 'GA4', 'Ahrefs', 'SEMrush', 'Screaming Frog', 'WordPress']))
        . '</div><p class="text-sm text-slate-500" style="margin-top:.7rem">Languages: English, Urdu and Punjabi.</p></section>';
    $m .= '<section class="ow-card"><h2>My other projects</h2><div class="space-y-2 text-slate-700 leading-relaxed">'
        . '<p><b>ROC SEO</b>: a free Chrome extension for one-click SEO audits, a site crawler and a check of whether ChatGPT, Perplexity and Google AI cite a site. <a class="text-emerald-700 font-bold" href="https://getrocseo.com/" target="_blank" rel="noopener">getrocseo.com</a></p>'
        . '<p><b>ROC Toolz</b>: 105 free online tools for images, PDFs, video, audio and calculations, most of them running in the browser. <a class="text-emerald-700 font-bold" href="https://roctoolz.com/" target="_blank" rel="noopener">roctoolz.com</a></p></div></section>';
    $m .= '<section class="ow-card"><h2>Questions</h2>';
    foreach ($faqs as $f) $m .= '<h3 class="font-bold text-slate-900" style="margin-top:.8rem">' . rocH($f[0]) . '</h3><p class="text-slate-700">' . rocH($f[1]) . '</p>';
    $m .= '</section>';
    $m .= '<section class="ow-card"><h2>Get in touch</h2><p class="text-slate-700" style="margin-bottom:.8rem">Questions about the site, corrections or partnerships are welcome.</p><div class="ow-links">'
        . '<a class="p" href="/contact/">Contact Run On Console</a>'
        . '<a href="https://omarabobakar.com/" target="_blank" rel="noopener me">omarabobakar.com</a>'
        . '<a href="https://www.linkedin.com/in/omar-abo-bakar-21ot02/" target="_blank" rel="noopener me">LinkedIn</a>'
        . '<a href="/author/omar-abobakar/">Author page</a><a href="/about/">About Run On Console</a></div></section>';
    $m .= '</article>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}
