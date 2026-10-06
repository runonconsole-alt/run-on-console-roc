# Attaching the CMS to production

No staging. This takes runonconsole.com from a fully static site to one where
blogs and products come out of a database you edit through the CMS.

Roughly 30–45 minutes. Read the **order** section before starting — one step out
of sequence takes the site down.

---

## What is true right now

Your live site is 100% static. Every page is a prerendered HTML file from
`npm run build`. There is no PHP running and no database behind it.

The CMS, the PHP router and the API all exist in your source, but they were
built against a staging database and have never run on production. Several
files refuse outright to run anywhere else — they check for a database literally
named `runoncon_rocstage`. That is what this package fixes, along with the CMS
bugs from the previous round.

---

## The order, and why it matters

Your `.htaccess` contains this, at rule 6:

```apache
# 6. CMS-Managed Dynamic Public Content Routes (Handled by PHP SSR BEFORE static file bypass)
RewriteRule ^$ /index.php [QSA,L]
RewriteRule ^(products|blogs|categories|about|author|contact|compatibility|
               write-for-us|partnerships|terms-and-conditions|privacy-policy|policy)(/.*)?$ /index.php [QSA,L]

# 7. Generic Physical Static Asset Bypass
RewriteCond %{REQUEST_FILENAME} -f [OR]
...
```

Rule 6 runs **before** rule 7. So once that `.htaccess` is live, a request for
`/blogs/anything/` goes to `index.php` and never reaches the prerendered file
sitting on disk. `index.php` looks the post up in the database. Empty database
means every blog and product URL returns **404**.

So: **database and content first, `.htaccess` and `index.php` last.** That is
steps 1–6 before step 7, and step 7 is the only one that changes what visitors
see.

---

## Step 1 — database

In cPanel (or whatever your host gives you), create a database and a user, and
grant the user all privileges on it. Note down the four values.

Nothing here requires a specific name. The old code demanded
`runoncon_rocstage`; the version in this package takes the name from your
settings file instead.

---

## Step 2 — settings file

Upload `upload/api/v1/site-settings.example.php` to `public/api/v1/`, rename it
to `site-settings.php`, and fill in the database values.

```bash
cd public/api/v1
mv site-settings.example.php site-settings.php
nano site-settings.php     # fill in DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
chmod 600 site-settings.php
```

`SITE_URL` should be `https://www.runonconsole.com` — with the `www`, because
your `.htaccess` redirects the bare domain to `www` and a mismatch would make
your canonical URLs fight those redirects.

Leave SMTP, Google and CAPTCHA empty for now. They only affect account signup
and password reset, not the CMS or the blog pages. Fill them in later.

---

## Step 3 — config

Back up the existing file first, then replace it:

```bash
cd public/api/v1
cp config.php config.php.bak
# upload the new config.php here
```

What changed: the database name is no longer hard-coded, and SMTP, OAuth,
CAPTCHA and the site URL all come from the settings file. It is still
fail-closed — it connects only to the database your settings name, and verifies
after connecting that it landed there.

One bug fixed along the way: `ROC_SITE_URL` was read by eight files
(`index.php`, `sitemap.xml.php`, `cms/media.php`, the agent endpoints) but was
never defined anywhere, so each fell back to its own hard-coded guess. Those
guesses disagreed — `sitemap.xml.php` used the `www` form, `agent/blogs.php`
used the bare domain, and `cms/media.php` fell back to an **empty string**,
which would have made every uploaded image URL come out relative. It is now
defined once, from `SITE_URL`.

Check it connects:

```bash
php -r 'require "public/api/v1/config.php"; var_dump(getDBConnection() !== null);'
```

`bool(true)` means you are good. `bool(false)` means the credentials or the
database name are wrong — fix that before going further.

---

## Step 4 — tables

Upload the three scripts from `upload/api/v1/cron/` into `public/api/v1/cron/`,
then:

```bash
cd public
php api/v1/cron/cli-migrate-cms.php --apply
```

This creates the CMS tables: blogs, products, categories, authors, cms_users,
cms_revisions, cms_cache_queue, cms_redirects, audit logs and the rest — 15
tables in all.

Then add the SEO columns:

```bash
php api/v1/cron/cli-migrate-seo-fields.php            # dry run first
php api/v1/cron/cli-migrate-seo-fields.php --apply
```

That adds Open Graph, Twitter, canonical, schema and alt-text columns to blogs
and products, alt text and caption to media, and two indexes on `(slug, status)`
that the router hits on every request. Running it twice is harmless.

Both scripts previously refused to run against anything but
`runoncon_rocstage`. The copies in this package check against your settings file
instead.

---

## Step 5 — content

**This is the step that decides whether the site survives step 7.**

```bash
cd public
php api/v1/cron/seed-roc-agent-content.php              # preview, writes nothing
php api/v1/cron/seed-roc-agent-content.php --confirm    # writes
```

This fills `blogs` and `products` from `initialData.js` — the same data your
prerendered pages were built from. After it runs, every URL that currently works
has a matching database row.

Check the count matches what is live:

```bash
php -r 'require "public/api/v1/config.php";
$p=getDBConnection();
printf("blogs: %d (published: %d)\n",
  $p->query("SELECT COUNT(*) FROM blogs")->fetchColumn(),
  $p->query("SELECT COUNT(*) FROM blogs WHERE status=\"published\"")->fetchColumn());
printf("products: %d\n", $p->query("SELECT COUNT(*) FROM products")->fetchColumn());'
```

The seeder inserts blogs as **drafts** by design. Drafts 404 in the router, so
before step 7 you must publish the ones that are currently live:

```bash
php -r 'require "public/api/v1/config.php";
$p=getDBConnection();
$n=$p->exec("UPDATE blogs SET status=\"published\", published_at=IFNULL(published_at,NOW()) WHERE status=\"draft\"");
echo "published {$n} posts\n";'
```

Then list the slugs and compare them against your live URLs:

```bash
php -r 'require "public/api/v1/config.php";
foreach (getDBConnection()->query("SELECT slug FROM blogs WHERE status=\"published\"") as $r)
  echo "https://www.runonconsole.com/blogs/{$r["slug"]}/\n";'
```

**Every URL that is live today must appear in that list.** Any that does not
will 404 the moment step 7 lands. If some are missing, add them in the CMS
before continuing, or add a redirect row in `cms_redirects`.

---

## Step 6 — CMS and API

```bash
# into public/api/v1/cms/
cms-html.php
blogs.php

# into public/cms/
index.html
```

`cms-html.php` is new; `blogs.php` requires it. Both replace or add to what the
`backend-cms-fixed` ZIP contained — upload the rest of `api/v1/cms/*` from your
source at the same time if it is not already on the server.

Create your CMS login:

```bash
php api/v1/cron/cli-create-cms-admin.php
```

It prompts for the password rather than taking it on the command line, so it
does not end up in your shell history.

Now open `https://www.runonconsole.com/cms/` and sign in. Nothing visitors see
has changed yet — `/cms/` is already routed by `.htaccess` rule 3 and carries
`noindex`.

**Click around properly before moving on.** Open a post, check the content came
through the seeder intact, edit something, save a draft, publish it. If the CMS
does not work here, step 7 will take the site down.

### What the new editor gives you

Headings, bold, italic, lists, quotes, code blocks, links, images and real
tables with add/remove row and column (click any cell). `Ctrl+K` for a link,
`Ctrl+S` to save, Tab between table cells.

Paste from Word, Google Docs or any web page and the formatting is cleaned on
the way in — bold and italic expressed as CSS become real `<strong>` and `<em>`
instead of being lost, and scripts, inline styles and `javascript:` links are
stripped. The server sanitises again on save.

Side panel: cover image and alt text, a live Google preview with character
meters, Open Graph and Twitter fields with a live share-card preview, and
generated JSON-LD with a manual override. Live checks flag thin content, missing
alt text and overlong meta fields.

The old editor could not save content at all — it sent `action:'create'` (which
stored only the title) and `action:'update'` (which only changed status), so a
whole post would report success and persist nothing. That is what was fixed.

---

## Step 7 — go live

This is the only step visitors notice. Do it when you can watch the site for
ten minutes.

```bash
cd public
cp index.php index.php.bak        # if one exists
cp .htaccess .htaccess.bak
```

Upload, in this order:

1. `index.php` (from this package)
2. `.htaccess` (from your source ZIP — `public/.htaccess`)

Then immediately:

```bash
for u in / /blogs/ /about/ /policy/ /products/ /compatibility/; do
  printf "%-20s %s\n" "$u" "$(curl -s -o /dev/null -w '%{http_code}' https://www.runonconsole.com$u)"
done
```

All `200`. Then check a real blog post:

```bash
curl -s https://www.runonconsole.com/blogs/YOUR-SLUG/ | grep -cE 'og:title|rel="canonical"|twitter:card'
```

Expect `3` — one of each. If you get 6, the template's own tags are not being
stripped; roll back and tell me.

### What changed in `index.php`

Three bugs from the previous round, plus one found while preparing this:

**Duplicate meta tags.** The old code replaced only `<title>` in the template.
The template is the prerendered *home page*, so it arrives carrying the home
page's `og:title`, `og:image`, `canonical`, `twitter:*` and JSON-LD. Those were
left in place and the route's tags appended — two canonicals, and social
scrapers taking the home hero as every post's share image. Now stripped first.

**Missing tags.** No `og:type`, `og:site_name`, no `twitter:*`, no `robots`.
`prerender.mjs` writes all of them, so any page served by PHP was losing its
Twitter card relative to the static build. All present now.

**`is_noindex` ignored** for blogs, products and categories — only authors were
checked. A post marked noindex was dropped from the sitemap but still rendered
with no robots tag, so Google could reach it through links. Honoured now.

**Static pages served the home page.** `.htaccess` rule 6 routes `/about/`,
`/contact/`, `/policy/`, `/compatibility/`, `/write-for-us/`, `/partnerships/`,
`/terms-and-conditions/` and `/privacy-policy/` to `index.php` — but `index.php`
has database branches only for products, blogs, categories and authors. Those
eight would have fallen through and rendered `index.html`, the home page, under
their own URLs, losing their prerendered content and meta entirely. `index.php`
now uses each route's own prerendered file and serves it untouched when the CMS
has nothing for that route.

---

## Step 8 — the queue worker

Publishing inserts a `purge_and_prerender` task into `cms_cache_queue`.
`cli-process-queue.php` processes them, but nothing runs it on a schedule. Add a
cron job:

```
*/5 * * * * cd /path/to/public && php api/v1/cron/cli-process-queue.php >/dev/null 2>&1
```

Without it those rows just accumulate and any cache in front of the site keeps
serving stale pages.

If you use `cli-publish-scheduled.php`, give it a cron too.

---

## Rollback

If anything goes wrong at step 7, one command puts it back:

```bash
cd public && mv .htaccess.bak .htaccess
```

Removing `.htaccess` rule 6 sends every request back to the prerendered files,
which are all still on disk. The site returns to exactly what it is today.
Nothing in steps 1–6 is visible to visitors, so those can stay.

---

## After it is live

**Rebuild carefully.** `npm run build` runs `prerender.mjs`, which still
generates `/blogs/*` routes from `ENHANCED_BLOGS` in
`src/seo/routeRegistry.js` — a static file, not the database. Those files are
now dead weight, because `.htaccess` sends those paths to `index.php` first. The
risk is drift: `initialData.js` and the database slowly disagree, and if
`.htaccess` is ever removed the site silently reverts to old content. Worth
deleting the blog and product route generation from `prerender.mjs` once you
trust the CMS.

**Client-side navigation.** If the React app navigates between pages without a
full reload, it may be rendering blog content from `initialData.js` rather than
from `window.__INITIAL_CONTENT__`. That would mean clicking through from a
listing shows old content while a hard refresh shows the CMS version. Send me
`src/context/AppContext.jsx` and the blog page component and I will check.

**Sitemap.** `sitemap.xml.php` reads published rows from the database, and
`.htaccess` already routes `/sitemap.xml` to it. After step 7 your sitemap is
live and automatic. Resubmit it in Search Console.

---

## File list

```
upload/
├── index.php                              -> public/
├── cms/index.html                         -> public/cms/
└── api/v1/
    ├── config.php                         -> public/api/v1/   (replaces)
    ├── site-settings.example.php          -> public/api/v1/   (rename, fill in)
    ├── cms/cms-html.php                   -> public/api/v1/cms/   (new)
    ├── cms/blogs.php                      -> public/api/v1/cms/   (replaces)
    └── cron/
        ├── cli-migrate-cms.php            -> public/api/v1/cron/  (replaces)
        ├── cli-create-cms-admin.php       -> public/api/v1/cron/  (replaces)
        ├── seed-roc-agent-content.php     -> public/api/v1/cron/  (replaces)
        └── cli-migrate-seo-fields.php     -> public/api/v1/cron/  (new)
```

Everything else — the rest of `api/v1/`, `sitemap.xml.php`, `.htaccess`,
`assets/`, the prerendered HTML — comes from your `runonconsole-source-fixed`
ZIP unchanged.
