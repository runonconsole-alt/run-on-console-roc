"""
Run On Console — new product categories and products from the owner's photos.

    python scripts/products/build_expand.py <list.txt> [photo map json]

<list.txt> is the list of photo files ("Folder/Product name.jpg"), one per line.
Writes:
  ROC-Production/upload/api/v1/cron/roc-product-expand.json   (server installer data)
  src/data/productCatalog.js                                 (React copy: categories + products added)
  scripts/products/expand-photos.json                        ({slug: "Folder/file"} for the photo maker)

Only facts that are in the product name are used as specs (brand, memory type and speed,
capacity, model year). Nothing is made up; the CMS can add more later.
"""
import json, os, re, sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
CATALOG = os.path.join(ROOT, 'src', 'data', 'productCatalog.js')
OUT = os.path.join(ROOT, 'ROC-Production', 'upload', 'api', 'v1', 'cron', 'roc-product-expand.json')
PHOTOS = os.path.join(ROOT, 'scripts', 'products', 'expand-photos.json')

# folder -> category
CATS = {
    'Gaming CPUs': dict(slug='cpu', name='Processors (CPU)', noun='desktop processor', group='pc-hardware', rank=7,
        desc='AMD Ryzen and Intel Core desktop processors for gaming PCs, from budget 6-core chips to X3D gaming flagships.'),
    'Rams': dict(slug='ram', name='Memory (RAM)', noun='memory kit', group='pc-hardware', rank=8,
        desc='DDR5 and DDR4 desktop memory kits for gaming PCs, from fast 6000 MT/s kits to high-capacity sets.'),
    'Custom PC Cases': dict(slug='cases', name='PC Cases', noun='PC case', group='pc-hardware', rank=9,
        desc='Airflow, showcase and compact PC cases for gaming builds, from budget mid-towers to large full-towers.'),
    'Gaming Chair': dict(slug='chairs', name='Gaming Chairs', noun='gaming chair', group='gaming-hardware', rank=10,
        desc='Gaming and ergonomic office chairs for long sessions, from budget racing seats to premium ergonomic picks.'),
    'Gaming Handhelds': dict(slug='handhelds', name='Gaming Handhelds', noun='handheld gaming PC', group='gaming-hardware', rank=11,
        desc='Handheld gaming PCs such as Steam Deck, ROG Ally, Legion Go and MSI Claw, for playing PC games anywhere.'),
    'Gaming Laptops': dict(slug='laptops', name='Gaming Laptops', noun='gaming laptop', group='gaming-hardware', rank=12,
        desc='Gaming laptops from ASUS ROG, Lenovo Legion, Razer, Alienware, MSI and Acer, from budget picks to 18-inch flagships.'),
}
MULTI_BRANDS = ['be quiet!', 'Lian Li', 'Fractal Design', 'Cooler Master', 'G.Skill', 'Steam Deck']
BRAND_FIX = {'Steam Deck': 'Valve', 'ROG': 'ASUS', 'HYTE': 'HYTE', 'GPD': 'GPD', 'NZXT': 'NZXT', 'AYANEO': 'AYANEO', 'Havn': 'HAVN'}
TITLE_FIX = {'GTPLAYER': 'GTPLAYER Gaming Chair', 'CAPOT': 'CAPOT Gaming Chair', 'HUANUO': 'HUANUO Gaming Chair',
             'Athena Pro': 'Athena Pro Gaming Chair', 'Vita Pro': 'Vita Pro Handheld'}
NO_BRAND = {'Athena Pro', 'Vita Pro'}


def clean(name):
    name = re.sub(r'\s+', ' ', name).strip()
    h = len(name) // 2
    if len(name) % 2 == 0 and name[:h] == name[h:]:
        name = name[:h]
    return name


def slugify(s):
    s = s.lower().replace('+', ' plus').replace('!', '')
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def brand_of(name):
    if name in NO_BRAND:
        return ''
    for b in MULTI_BRANDS:
        if name.lower().startswith(b.lower()):
            return BRAND_FIX.get(b, b)
    first = name.split(' ')[0]
    if first.lower() == 'respawn':
        return 'RESPAWN'
    return BRAND_FIX.get(first, first)


def specs_of(name, cat):
    sp, feats = {}, []
    m = re.search(r'(DDR[45])-(\d{4})', name)
    if m:
        sp['Memory type'] = m.group(1); sp['Speed'] = m.group(2) + ' MT/s'; feats += [m.group(1), m.group(2) + ' MT/s']
    m = re.search(r'\b(\d{2,3}GB|\d TB|\dTB|\d{3}GB)\b', name)
    if m:
        sp['Capacity' if cat['slug'] == 'ram' else 'Storage'] = m.group(1); feats.append(m.group(1))
    m = re.search(r'\bCL(\d{2})\b', name)
    if m:
        sp['CAS latency'] = 'CL' + m.group(1); feats.append('CL' + m.group(1))
    m = re.search(r'\((20\d\d)\)', name)
    if m:
        sp['Model year'] = m.group(1); feats.append(m.group(1) + ' model')
    if 'X3D' in name:
        sp['Cache'] = '3D V-Cache (X3D)'; feats.append('3D V-Cache')
    if 'RGB' in name or 'ARGB' in name:
        feats.append('RGB lighting')
    if 'Airflow' in name or 'Flow' in name.split():
        feats.append('airflow front')
    return sp, feats


def main():
    files = [l.strip() for l in open(sys.argv[1], encoding='utf-8') if l.strip()]
    js = open(CATALOG, encoding='utf-8').read()
    head, sep, body = js.partition('export const ALL_PRODUCTS = ')
    end = body.rindex('];') + 1
    products = json.loads(body[:end])
    have = {p['slug'] for p in products}
    chead, csep, cbody = head.partition('export const PRODUCT_CATEGORIES = ')
    cend = cbody.index('];') + 1
    cats = json.loads(cbody[:cend])

    new_products, new_cats, photos = [], [], {}
    extra = {'Keyboards/nuphy WH80.jpg': ('Gaming Keyboards', 'keyboards', 'gaming keyboard', 'NuPhy WH80')}
    for f in files:
        folder, fn = f.split('/', 1)
        base = os.path.splitext(fn)[0]
        if f in extra:
            catname, catslug, noun, title = extra[f]
            cat = dict(slug=catslug, name=catname, noun=noun)
        elif folder in CATS:
            cat = CATS[folder]
            title = clean(base)
            title = TITLE_FIX.get(title, title)
        else:
            continue
        slug = slugify(title)
        if slug in have:
            continue
        brand = brand_of(clean(base)) if f not in extra else 'NuPhy'
        sp, feats = specs_of(title, cat)
        spec = {'Brand': brand} if brand else {}
        spec.update(sp)
        by = (' from ' + brand) if brand else ''
        short = 'The %s is a %s%s.' % (title, cat['noun'], by) + (' Key details: ' + ', '.join(feats) + '.' if feats else '') + \
                ' See the full specifications and the current price on Amazon.'
        rank = sum(1 for p in new_products if p['categorySlug'] == cat['slug']) + 1
        p = {'id': slug, 'slug': slug, 'title': title, 'subtitle': ' // '.join(feats), 'brand': brand, 'brandName': brand,
             'category': cat['name'], 'categorySlug': cat['slug'], 'badge': cat['name'].upper(), 'bestFor': '', 'rank': rank,
             'image': '/images/products/photos/' + slug + '.webp',
             'affiliateLinks': {'amazon': 'https://www.amazon.com/s?k=' + re.sub(r'\s+', '+', re.sub(r'[()]', '', title))},
             'shortDesc': short, 'specs': spec, 'features': feats, 'lastmod': '2026-10-12T10:00:00+05:00'}
        new_products.append(p); have.add(slug); photos[slug] = f
    for folder, c in CATS.items():
        n = sum(1 for p in new_products if p['categorySlug'] == c['slug'])
        if n and not any(x['slug'] == c['slug'] for x in cats):
            first = next(p for p in new_products if p['categorySlug'] == c['slug'])
            new_cats.append({'slug': c['slug'], 'name': c['name'], 'image': first['image'], 'desc': c['desc'], 'count': n,
                             'group': c['group'], 'rank': c['rank']})
    for c in cats:
        c['count'] = sum(1 for p in products + new_products if p['categorySlug'] == c['slug'])

    json.dump({'generated': 'scripts/products/build_expand.py', 'categories': new_cats, 'products': new_products},
              open(OUT, 'w', encoding='utf-8'), indent=1, ensure_ascii=False)
    json.dump(photos, open(PHOTOS, 'w', encoding='utf-8'), indent=1)
    cats_out = cats + [{k: c[k] for k in ('slug', 'name', 'image', 'desc', 'count')} for c in new_cats]
    head2 = chead + csep + json.dumps(cats_out, indent=1, ensure_ascii=False) + cbody[cend:]
    open(CATALOG, 'w', encoding='utf-8', newline='\n').write(head2 + sep + json.dumps(products + new_products, indent=1, ensure_ascii=False) + body[end:])
    print('new categories:', [c['slug'] for c in new_cats], '| new products:', len(new_products))
    for c in new_cats:
        print(' ', c['slug'], c['count'])


if __name__ == '__main__':
    main()
