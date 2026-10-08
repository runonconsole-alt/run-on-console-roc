import { 
  STATIC_PUBLIC_ROUTES, 
  PRIVATE_ROUTES, 
  ENHANCED_BLOGS, 
  ENHANCED_CATEGORIES, 
  ENHANCED_PRODUCTS,
  getBlogBySlug,
  getCategoryBySlug,
  getProductBySlug,
  getProductCategoryBySlug,
  slugify,
  BASE_DOMAIN,
  isStagingEnv
} from './routeRegistry.js';

const SITE_NAME = 'Run On Console';
const SITE_DOMAIN = BASE_DOMAIN;
const DEFAULT_IMAGE = `${SITE_DOMAIN}/images/hero_cod.jpg`;
const LOGO_URL = `${SITE_DOMAIN}/images/logo-512.png`;

/**
 * Generate full JSON-LD structured data for any route
 */
export function generateJsonLd(routeData) {
  const schemas = [];

  // 1. Organization Schema (Included on homepage & relevant pages)
  const organizationSchema = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    '@id': `${SITE_DOMAIN}/#organization`,
    'name': SITE_NAME,
    'url': SITE_DOMAIN,
    'logo': { '@type': 'ImageObject', 'url': LOGO_URL, 'width': 512, 'height': 512 },
    'description': 'Independent gaming site: gear picks for PC and console players, gaming platform guides and a free game requirements checker.',
    'email': 'support@runonconsole.com',
    'contactPoint': { '@type': 'ContactPoint', 'contactType': 'customer support', 'email': 'support@runonconsole.com', 'url': `${SITE_DOMAIN}/contact/`, 'availableLanguage': ['English'] },
    'sameAs': [
      'https://www.facebook.com/profile.php?id=61595168580625',
      'https://www.instagram.com/runonconsole/',
      'https://www.pinterest.com/runonconsole/',
      'https://x.com/RunOnConsole',
      'https://www.linkedin.com/company/run-on-console/',
      'https://www.reddit.com/user/runonconsole_roc/'
    ]
  };

  if (routeData.path === '/') {
    schemas.push(organizationSchema);

    // 2. WebSite Schema with SearchAction
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'WebSite',
      '@id': `${SITE_DOMAIN}/#website`,
      'url': SITE_DOMAIN,
      'name': SITE_NAME,
      'publisher': { '@id': `${SITE_DOMAIN}/#organization` },
      'potentialAction': {
        '@type': 'SearchAction',
        'target': `${SITE_DOMAIN}/products/?q={search_term_string}`,
        'query-input': 'required name=search_term_string'
      }
    });
  }

  // 2b. The page itself, typed by what it is, linked to the site and publisher.
  const PAGE_TYPES = { '/about/': 'AboutPage', '/contact/': 'ContactPage', '/compatibility/': 'WebPage',
    '/privacy-policy/': 'WebPage', '/terms-and-conditions/': 'WebPage', '/write-for-us/': 'WebPage' };
  if (PAGE_TYPES[routeData.path]) {
    schemas.push({
      '@context': 'https://schema.org',
      '@type': PAGE_TYPES[routeData.path],
      '@id': `${routeData.canonical}#webpage`,
      'url': routeData.canonical,
      'name': routeData.title,
      'description': routeData.description,
      'isPartOf': { '@type': 'WebSite', '@id': `${SITE_DOMAIN}/#website`, 'name': SITE_NAME, 'url': SITE_DOMAIN },
      'publisher': organizationSchema,
    });
  }
  if (routeData.path === '/compatibility/') {
    // The checker is a free tool that runs in the browser.
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'WebApplication',
      'name': 'Can I Run It? PC Game Compatibility Checker',
      'url': routeData.canonical,
      'applicationCategory': 'UtilitiesApplication',
      'operatingSystem': 'Any (web browser)',
      'offers': { '@type': 'Offer', 'price': '0', 'priceCurrency': 'USD' },
      'publisher': { '@id': `${SITE_DOMAIN}/#organization` },
    });
  }

  // 3. BreadcrumbList Schema (on all non-homepage public routes)
  if (routeData.path !== '/' && routeData.breadcrumbs && routeData.breadcrumbs.length > 0) {
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      'itemListElement': routeData.breadcrumbs.map((b, idx) => ({
        '@type': 'ListItem',
        'position': idx + 1,
        'name': b.name,
        'item': b.url
      }))
    });
  }

  // 4. BlogPosting / Article Schema for blog post detail pages
  if (routeData.type === 'blog' && routeData.blog) {
    const b = routeData.blog;
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'BlogPosting',
      'mainEntityOfPage': {
        '@type': 'WebPage',
        '@id': routeData.canonical
      },
      'headline': b.title,
      'description': b.summary || routeData.description,
      'image': [b.image ? `${SITE_DOMAIN}${b.image}` : DEFAULT_IMAGE],
      'datePublished': b.isoDate || '2024-05-20T08:00:00+00:00',
      'dateModified': b.isoDateModified || '2024-05-20T08:00:00+00:00',
      'author': {
        '@type': 'Person',
        'name': b.author || 'Omar Abobakar',
        'jobTitle': 'Founder & Editor',
        'url': `${SITE_DOMAIN}/author/omar-abobakar/`
      },
      'publisher': {
        '@type': 'Organization',
        'name': SITE_NAME,
        'logo': {
          '@type': 'ImageObject',
          'url': LOGO_URL
        }
      },
      'articleSection': b.category || 'Hardware Reviews',
      'inLanguage': 'en-US'
    });
  }

  // 5. CollectionPage for Blogs Hub & Categories Hub
  if (routeData.path === '/blogs/' || routeData.path === '/categories/' || routeData.path === '/products/') {
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'CollectionPage',
      'name': routeData.h1,
      'url': routeData.canonical,
      'description': routeData.description
    });
  }

  // 6. Product schema for product pages. Price, offer and rating are only included
  //    when the product really has them (no invented prices or reviews).
  if (routeData.type === 'product' && routeData.product) {
    const p = routeData.product;
    const product = {
      '@context': 'https://schema.org',
      '@type': 'Product',
      'name': p.title,
      'image': [p.image ? `${SITE_DOMAIN}${p.image}` : DEFAULT_IMAGE],
      'description': p.shortDesc || routeData.description,
      'category': p.category,
      'brand': { '@type': 'Brand', 'name': p.brandName || p.brand || SITE_NAME }
    };
    const priceNumeric = p.price ? parseFloat(String(p.price).replace(/[^0-9.]/g, '')) : NaN;
    if (!isNaN(priceNumeric) && priceNumeric > 0) {
      product.offers = {
        '@type': 'Offer',
        'url': routeData.canonical,
        'priceCurrency': 'USD',
        'price': priceNumeric,
        'availability': p.inStock !== false ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
      };
    }
    if (p.rating) {
      product.review = {
        '@type': 'Review',
        'reviewRating': { '@type': 'Rating', 'ratingValue': p.rating, 'bestRating': 5, 'worstRating': 1 },
        'author': { '@type': 'Organization', 'name': SITE_NAME }
      };
    }
    if (product.offers || product.review) schemas.push(product);
  }

  // 7. Product category listing: ItemList of the products on the page
  if (routeData.type === 'product-category' && routeData.productCategory) {
    const items = ENHANCED_PRODUCTS.filter(p => p.categorySlug === routeData.productCategory.slug);
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'CollectionPage',
      'name': routeData.h1,
      'url': routeData.canonical,
      'description': routeData.description,
      'mainEntity': {
        '@type': 'ItemList',
        'numberOfItems': items.length,
        'itemListElement': items.map((p, i) => ({ '@type': 'ListItem', 'position': i + 1, 'url': p.url, 'name': p.title }))
      }
    });
  }

  return schemas;
}

/**
 * Main SEO resolver function for any route path
 */
export function getSeoMetadata(pathname) {
  const cleanPath = pathname.toLowerCase().replace(/^\/+|\/+$/g, '');
  const fullPath = cleanPath ? `/${cleanPath}/` : '/';

  // 1. Check static public routes
  const staticMatch = STATIC_PUBLIC_ROUTES.find(r => r.path === fullPath);
  if (staticMatch) {
    const breadcrumbs = fullPath === '/' ? [] : [
      { name: 'Home', url: SITE_DOMAIN },
      { name: staticMatch.category, url: staticMatch.canonical }
    ];
    const data = {
      ...staticMatch,
      robots: 'index, follow',
      ogType: 'website',
      ogImage: DEFAULT_IMAGE,
      breadcrumbs
    };
    data.jsonLd = generateJsonLd(data);
    return data;
  }

  // 2. Check private routes
  const privateMatch = PRIVATE_ROUTES.find(r => r.path === fullPath);
  if (privateMatch) {
    return {
      ...privateMatch,
      robots: 'noindex, follow',
      ogType: 'website',
      ogImage: DEFAULT_IMAGE,
      breadcrumbs: [],
      jsonLd: []
    };
  }

  // 3. Check individual Blog Article route `/blogs/{slug}/`
  if (fullPath.startsWith('/blogs/')) {
    const slug = fullPath.replace('/blogs/', '').replace('/', '');
    const blog = getBlogBySlug(slug);
    if (blog) {
      const canonical = blog.url;
      const breadcrumbs = [
        { name: 'Home', url: SITE_DOMAIN },
        { name: 'Blogs', url: `${SITE_DOMAIN}/blogs/` },
        { name: blog.title, url: canonical }
      ];
      const data = {
        path: fullPath,
        canonical,
        title: `${blog.title} | Run On Console`,
        description: blog.summary || `${blog.title} - Read full hardware benchmarking & review on Run On Console.`,
        h1: blog.title,
        robots: 'index, follow',
        ogType: 'article',
        ogImage: blog.image ? `${SITE_DOMAIN}${blog.image}` : DEFAULT_IMAGE,
        type: 'blog',
        blog,
        breadcrumbs
      };
      data.jsonLd = generateJsonLd(data);
      return data;
    }
  }

  // 4. Check individual Category route `/categories/{slug}/`
  if (fullPath.startsWith('/categories/')) {
    const slug = fullPath.replace('/categories/', '').replace('/', '');
    const category = getCategoryBySlug(slug);
    if (category) {
      const canonical = category.url;
      const breadcrumbs = [
        { name: 'Home', url: SITE_DOMAIN },
        { name: 'Gaming Platforms', url: `${SITE_DOMAIN}/gaming-platforms/` },
        { name: category.title, url: canonical }
      ];
      const data = {
        path: fullPath,
        canonical,
        title: `${category.title} Hardware & Specs Hub | Run On Console`,
        description: category.desc || `Explore hardware devices, gaming handhelds, and consoles under ${category.title}.`,
        h1: `${category.title} Gaming Platform Hub`,
        robots: 'index, follow',
        ogType: 'website',
        ogImage: category.image ? `${SITE_DOMAIN}${category.image}` : DEFAULT_IMAGE,
        type: 'category',
        category,
        breadcrumbs
      };
      data.jsonLd = generateJsonLd(data);
      return data;
    }
  }

  // 5a. Product category listing `/products/category/{slug}/`
  if (fullPath.startsWith('/products/category/')) {
    const cat = getProductCategoryBySlug(fullPath.replace('/products/category/', '').replace('/', ''));
    if (cat) {
      const data = {
        path: fullPath,
        canonical: cat.url,
        title: `Best ${cat.name}: ${cat.count} Picks | Run On Console`,
        description: `${cat.desc} ${cat.count} picks with key specs and Amazon links.`,
        h1: `Best ${cat.name}`,
        robots: 'index, follow',
        ogType: 'website',
        ogImage: cat.image ? `${SITE_DOMAIN}${cat.image}` : DEFAULT_IMAGE,
        type: 'product-category',
        productCategory: cat,
        breadcrumbs: [
          { name: 'Home', url: SITE_DOMAIN },
          { name: 'Products', url: `${SITE_DOMAIN}/products/` },
          { name: cat.name, url: cat.url }
        ]
      };
      data.jsonLd = generateJsonLd(data);
      return data;
    }
  }

  // 5. Check individual Product route `/products/{slug}/`
  if (fullPath.startsWith('/products/')) {
    const slug = fullPath.replace('/products/', '').replace('/', '');
    const product = getProductBySlug(slug);
    if (product) {
      const canonical = product.url;
      const breadcrumbs = [
        { name: 'Home', url: SITE_DOMAIN },
        { name: 'Products', url: `${SITE_DOMAIN}/products/` },
        ...(getProductCategoryBySlug(product.categorySlug) ? [{ name: product.category, url: getProductCategoryBySlug(product.categorySlug).url }] : []),
        { name: product.title, url: canonical }
      ];
      const data = {
        path: fullPath,
        canonical,
        title: `${product.title}: Specs & Where to Buy | Run On Console`,
        description: product.shortDesc || `${product.title} key specs and where to buy it.`,
        h1: product.title,
        robots: 'index, follow',
        ogType: 'product',
        ogImage: product.image ? `${SITE_DOMAIN}${product.image}` : DEFAULT_IMAGE,
        type: 'product',
        product,
        breadcrumbs
      };
      data.jsonLd = generateJsonLd(data);
      return data;
    }
  }

  // 6. Unknown 404 Route
  return {
    path: fullPath,
    canonical: `${SITE_DOMAIN}/404/`,
    title: 'Page Not Found (404) | Run On Console',
    description: 'The requested gaming hardware review or page was not found.',
    h1: 'Page Not Found (404)',
    robots: 'noindex, nofollow',
    ogType: 'website',
    ogImage: DEFAULT_IMAGE,
    is404: true,
    breadcrumbs: [],
    jsonLd: []
  };
}

// A page whose title/description was changed in the CMS (Metas tab) carries
// <meta name="roc-meta-override">: on that page keep the server's values.
const ROC_OVERRIDE_PATH = typeof document !== 'undefined' && document.querySelector('meta[name="roc-meta-override"]')
  ? window.location.pathname : null;

export function applyClientSideSeo(seo) {
  if (typeof document === 'undefined') return;
  if (ROC_OVERRIDE_PATH && window.location.pathname === ROC_OVERRIDE_PATH) return;
  if (seo.title) document.title = seo.title;
  let metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc && seo.description) metaDesc.setAttribute('content', seo.description);
}
