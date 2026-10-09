"""
Run On Console — Amazon ASINs for the products (scripts/asin/asins.txt).

    python scripts/asin/build_asin.py

1. Writes ROC-Production/upload/api/v1/cron/roc-product-asins.json, which the server
   installer (api/v1/cron/cli-product-asin.php) uses to update the products in the CMS
   database.
2. Updates src/data/productCatalog.js (the React copy of the products) the same way:
   - the Amazon link becomes https://www.amazon.com/dp/ASIN when it is still a search link;
   - the picture becomes Amazon's SiteStripe product image when it is still a shared
     placeholder photo. The site's own name card is kept after "#fb=" so the page can
     fall back to it when a browser blocks Amazon's image server.

asins.txt lines:  slug | ASIN or "search" | note
"""
import json, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
SRC = os.path.join(ROOT, 'scripts', 'asin', 'asins.txt')
OUT = os.path.join(ROOT, 'ROC-Production', 'upload', 'api', 'v1', 'cron', 'roc-product-asins.json')
CATALOG = os.path.join(ROOT, 'src', 'data', 'productCatalog.js')
PLACEHOLDERS = {'/images/cyber_keyboard.jpg', '/images/tactical_headset.jpg', '/images/apex_mouse.jpg',
                '/images/streaming_vr_gear.jpg', '/images/gaming_monitor.jpg', '/images/battlestation_pc.jpg'}


def sitestripe_image(asin, fallback):
    """The image link SiteStripe gives for a product (served by Amazon, never copied)."""
    url = ('https://ws-na.amazon-adsystem.com/widgets/q?_encoding=UTF8&ASIN=' + asin +
           '&Format=_SL500_&ID=AsinImage&MarketPlace=US&ServiceVersion=20070822&WS=1&language=en_US')
    return url + ('#fb=' + fallback if fallback else '')


def main():
    asins = {}
    for line in open(SRC, encoding='utf-8'):
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        slug, asin, note = [x.strip() for x in (line.split('|') + ['', ''])[:3]]
        if asin == 'search':
            continue
        assert re.fullmatch(r'B0[A-Z0-9]{8}', asin), (slug, asin)
        card = '/images/products/' + slug + '.webp'
        fb = card if os.path.isfile(os.path.join(ROOT, 'public', card.lstrip('/'))) else ''
        asins[slug] = {'asin': asin, 'link': 'https://www.amazon.com/dp/' + asin, 'image': sitestripe_image(asin, fb), 'note': note}
    with open(OUT, 'w', encoding='utf-8') as f:
        json.dump({'generated': 'scripts/asin/build_asin.py', 'products': asins}, f, indent=1)
    print('ASINs:', len(asins), '->', os.path.relpath(OUT, ROOT))

    js = open(CATALOG, encoding='utf-8').read()
    head, sep, body = js.partition('export const ALL_PRODUCTS = ')
    end = body.rindex('];') + 1
    products = json.loads(body[:end])
    links = images = 0
    for p in products:
        a = asins.get(p['slug'])
        if not a:
            continue
        cur = (p.get('affiliateLinks') or {}).get('amazon', '')
        if not cur or '/s?' in cur:
            p.setdefault('affiliateLinks', {})['amazon'] = a['link']; links += 1
        if p.get('image', '') in PLACEHOLDERS or not p.get('image'):
            p['image'] = a['image']; images += 1
    new = head + sep + json.dumps(products, indent=1, ensure_ascii=False) + body[end:]
    open(CATALOG, 'w', encoding='utf-8', newline='\n').write(new)
    print('productCatalog.js: links', links, 'images', images)


if __name__ == '__main__':
    main()
