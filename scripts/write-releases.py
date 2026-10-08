"""
Run On Console — deploy notes shown in the CMS ("Live version").

Writes ROC-Production/roc-releases/deploy-NN.json. Every deploy zip carries the
folder roc-releases/ (unzipped next to public_html, outside the web root) and
api/v1/cms/releases.php shows the newest one as the live version.

Add a new entry to RELEASES for every deploy, then run:
    python scripts/write-releases.py [build-js-name-for-the-newest]
"""
import json, os, sys

OUT = os.path.join(os.path.dirname(__file__), '..', 'ROC-Production', 'roc-releases')
REPO = 'https://github.com/runonconsole-alt/run-on-console-roc/pull/'

RELEASES = [
    dict(deploy=4, date='2026-10-07', title='Editor paste, header menus, media Replace, privacy page',
         changes=['Paste from Google Docs and ChatGPT keeps headings, lists and tables',
                  'Header dropdown menus work everywhere; Ctrl+click opens a new tab',
                  'Media library: Replace button, alt text explained, JPG/PNG/WebP only',
                  'Privacy Policy and Affiliate Disclosure merged into one page'],
         checks=[dict(label='Privacy page installer', type='file', path='privacy-policy/index.html')]),
    dict(deploy=5, date='2026-10-07', title='New header, site search, contact and profile cleanup',
         changes=['Header: Products (PC / Gaming Hardware), Systems, Blogs, About Us',
                  'Search with All / Products / Blogs / Games and spelling tolerance',
                  'Contact page without fake address and phone; Discord link from the CMS',
                  'Green spotlight hover instead of blur'], pr=REPO + '17'),
    dict(deploy=6, date='2026-10-07', title='Messages inbox',
         changes=['Contact and Write for us forms arrive in CMS → Messages and by email',
                  'Reply to a message from the CMS'],
         checks=[dict(label='Messages installer', type='file', path='api/v1/messages.php')]),
    dict(deploy=7, date='2026-10-07', title='QA fixes',
         changes=['Chatbot in English with working buttons', 'Compatibility checker uses each game\'s own requirements',
                  'Recent games added; heavy animations removed; schema and robots fixes'],
         checks=[dict(label='SEO headers installer', type='htaccess', marker='# ROC headers BEGIN (cli-seo-headers.php)')]),
    dict(deploy=8, date='2026-10-07', title='Product URLs, sitemaps, home blog section',
         changes=['Product categories at /products/{category}/ (old /category/ links redirect)',
                  'Products page: All / PC Hardware / Gaming Hardware', 'Sitemaps with page names',
                  'Home shows the newest blog posts automatically']),
    dict(deploy=9, date='2026-10-07', title='CMS redesign and 500 Steam games',
         changes=['New CMS dashboard with charts and accent colour',
                  '500 popular games with official Steam requirements in the checker', 'CMS Games list'],
         checks=[dict(label='Steam games file', type='file', path='roc-steam-games.json')]),
    dict(deploy=10, date='2026-10-07', title='/gaming-platforms/, Write for us + advertising, light Terms page',
         changes=['Platform hubs at /gaming-platforms/ (old /categories/ links redirect)',
                  'Advertising merged into Write for us; review samples into Contact', 'Terms page in site colours'],
         checks=[dict(label='Gaming platforms installer', type='htaccess', marker='# ROC gaming-platforms BEGIN')], pr=REPO + '19'),
    dict(deploy=11, date='2026-10-08', title='Profiles: own photo, required bio/city/country, CMS Site users',
         changes=['Members can upload their own profile photo', 'Bio, city and country are required after sign-up',
                  'CMS → Site users: members, filters, suspend / restore'],
         checks=[dict(label='Profile photos installer', type='file', path='uploads/avatars/.htaccess')], pr=REPO + '20'),
    dict(deploy=12, date='2026-10-08', title='SEO audit fixes', build='index-DRgZuU3J.js',
         changes=['Claims the site cannot back up replaced with what is true',
                  'Titles under 60 characters; product pages use valid schema',
                  '1200×630 share images, PNG logo, twitter:site',
                  'Platform pages link to each other; year-long cache and security headers'],
         checks=[dict(label='Speed and security headers installer', type='htaccess', marker='# ROC speed BEGIN'),
                 dict(label='Logo PNG', type='file', path='images/logo-512.png')], pr=REPO + '21'),
    dict(deploy=13, date='2026-10-08', title='Metas tab, announcement bar, template & code, live version',
         changes=['CMS → Metas: title and description of every page, changed live from the CMS',
                  'CMS → Announcement bar: a message above the header on every page',
                  'CMS → Template & code: custom CSS and HTML on every page',
                  'CMS → Live version: which deploy is on the server',
                  'Login cookie renamed to ROCSESSID (everyone signed out once)',
                  'Blog posts without an author show "Run On Console"'],
         checks=[dict(label='Login cookie installer (cli-session-name.php)', type='contains', path='api/v1/config.php', text="session_name('ROCSESSID')"),
                ],
         pr=REPO + '22'),
    dict(deploy=14, date='2026-10-08', title='30-day history and undo for Metas, announcement and template',
         changes=['Every Metas, announcement and Template & code change is kept for 30 days and can be restored',
                  'Metas: History button on each page and an Archive of all changes',
                  'Template & code: one-click Undo for 30 minutes after saving',
                  'Template & code: after 1 minute without changes, a 5-second countdown returns to the previous page',
                  'About page: "run by the ROC (Run On Console) team"'],
         checks=[], pr=REPO + '23'),
    dict(deploy=15, date='2026-10-08', title='Running announcement bar, page-by-page template code, easier Metas',
         changes=['Announcement bar stays at the top while scrolling and can run from right to left',
                  'Built pages are checked for changes on every visit: no more Ctrl+Shift+R',
                  'Template & code: choose the page (or all product / blog / platform pages) the code is for',
                  'Metas: plain page names in the website order (Home, About, Products …), simpler search',
                  'CMS sidebar keeps its scroll position; darker icons',
                  'The website header now stays at the top while scrolling'],
         checks=[dict(label='Fresh pages installer (cli-html-cache.php)', type='htaccess', marker='# ROC html-cache BEGIN')],
         pr=REPO + '24'),
    dict(deploy=16, date='2026-10-08', title='Metas edit with pencil, honest home hero, heading fixes',
         changes=['Metas: shows the live title and description; ✎ to edit, character and word counter, ✓ to save',
                  'Home hero: no more "live benchmark" and "latency" badges; the carousel shows Products, the game checker and platforms instead of old sample articles',
                  'The first heading on every page is now the H1 (header menu labels are no longer headings)',
                  'Contact page: plain reply-time note instead of "senior testing columnists"',
                  'Heading order on every page: one H1, H2 sections, H3 inside them; footer, value boxes, launchers and social cards are plain text'],
         checks=[], pr=REPO + '25'),
    dict(deploy=17, date='2026-10-08', title='ROC Team author page and the blog agent',
         changes=['Author page is now /author/roc-team/ (the old address redirects); posts are signed "ROC Team"',
                  'CMS > Blog agent: a token for the writer on your PC, and the list of its posts',
                  'Posts from the agent are scheduled for 12:00 and 18:00 Pakistan time with an automatic cover image'],
         checks=[dict(label='ROC Team installer (cli-roc-team.php)', type='htaccess', marker='# ROC team BEGIN'),
                 dict(label='Blog agent token made in CMS', type='setting', key='blog_agent_token_hash')],
         pr=REPO + '26'),
    dict(deploy=18, date='2026-10-08', title='CMS Settings and Sitemap; blog posts by "ROC"',
         changes=['CMS > Settings: Google Tag Manager, GA4, Clarity, Search Console, Bing, Pinterest, Facebook, Yandex and Google Business Profile codes',
                  'CMS > Settings: redirects (301 / 302 / 410), noindex rules, robots.txt and llms.txt editors, all with 30-day history',
                  'CMS > Sitemap: every address in the sitemaps, leave pages out or add pages',
                  'Tracking codes moved out of the page code into CMS (same IDs as before)',
                  'Blog posts are signed "ROC"'],
         checks=[dict(label='Tracking codes on the home page (cli-refresh-templates.php)', type='contains', path='index.html', text='googletagmanager.com/gtm.js')],
         pr=REPO + '27'),
    dict(deploy=19, date='2026-10-08', title='Fix: the ROC author page showed the sign-in page',
         changes=['/author/roc-team/ shows the ROC page again (its address contains "/auth", which the router took for sign-in)'],
         checks=[dict(label='ROC page has the right heading', type='contains', path='author/roc-team/index.html', text='ROC (Run On Console)</h1>')],
         pr=REPO + '27'),
    dict(deploy=20, date='2026-10-08', title='Settings: see the redirects and noindex pages the site already has',
         changes=['Redirects tab lists every redirect already on the site (www, https, old addresses, renamed products) and what set it up',
                  'Noindex tab lists every page that is already noindex, and what robots.txt blocks',
                  'Includes the fix for the ROC author page (deploy 19)'],
         checks=[dict(label='ROC page has the right heading', type='contains', path='author/roc-team/index.html', text='ROC (Run On Console)</h1>')],
         pr=REPO + '27'),
]

STEPS = 'Upload the zip to /home2/runoncon/, unzip it, then run the installers listed in the deploy message.'

os.makedirs(OUT, exist_ok=True)
newest = max(r['deploy'] for r in RELEASES)
for r in RELEASES:
    r = dict(r)
    if r['deploy'] == newest and len(sys.argv) > 1: r['build'] = sys.argv[1]
    if r.get('build'):   # cli-refresh-templates.php copies the new build into the PHP page templates
        r['checks'] = list(r.get('checks', [])) + [dict(label='Page templates refreshed (cli-refresh-templates.php)',
                                                       type='contains', path='cms-templates/product-page.html', text=r['build'])]
    r.setdefault('steps', [STEPS])
    with open(os.path.join(OUT, 'deploy-%02d.json' % r['deploy']), 'w', encoding='utf-8') as f:
        json.dump(r, f, ensure_ascii=False, indent=1)
print('wrote', len(RELEASES), 'release notes; newest deploy', newest)
