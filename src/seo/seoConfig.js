import { 
  STATIC_PUBLIC_ROUTES, 
  PRIVATE_ROUTES, 
  ENHANCED_BLOGS, 
  ENHANCED_CATEGORIES, 
  ENHANCED_PRODUCTS,
  getBlogBySlug,
  getCategoryBySlug,
  getProductBySlug,
  slugify,
  BASE_DOMAIN,
  isStagingEnv
} from './routeRegistry.js';

const SITE_NAME = 'Run On Console';
const SITE_DOMAIN = BASE_DOMAIN;
const DEFAULT_IMAGE = `${SITE_DOMAIN}/images/hero_cod.jpg`;
const LOGO_URL = `${SITE_DOMAIN}/favicon.svg`;

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
    'logo': LOGO_URL,
    'description': 'Independent gaming hardware intelligence lab, benchmark testing facility, and platform reviews desk.',
    'sameAs': [
      'https://twitter.com/runonconsole',
      'https://youtube.com/@runonconsole'
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
        'target': `${SITE_DOMAIN}/products/?search={search_term_string}`,
        'query-input': 'required name=search_term_string'
      }
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
        'jobTitle': 'Senior Hardware Columnist'
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

  // 6. Product / Review Schema for product pages
  if (routeData.type === 'product' && routeData.product) {
    const p = routeData.product;
    const priceNumeric = parseFloat((p.price || '199.99').replace(/[^0-9.]/g, '')) || 199.99;
    schemas.push({
      '@context': 'https://schema.org',
      '@type': 'Product',
      'name': p.title,
      'image': [p.image ? `${SITE_DOMAIN}${p.image}` : DEFAULT_IMAGE],
      'description': p.shortDesc || routeData.description,
      'brand': {
        '@type': 'Brand',
        'name': p.brandName || p.brand || SITE_NAME
      },
      'offers': {
        '@type': 'Offer',
        'url': routeData.canonical,
        'priceCurrency': 'USD',
        'price': priceNumeric,
        'availability': p.inStock !== false ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'
      },
      'review': {
        '@type': 'Review',
        'reviewRating': {
          '@type': 'Rating',
          'ratingValue': p.rating || 4.8,
          'bestRating': 5,
          'worstRating': 1
        },
        'author': {
          '@type': 'Organization',
          'name': 'Run On Console Testing Lab'
        }
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
        { name: 'Categories', url: `${SITE_DOMAIN}/categories/` },
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

  // 5. Check individual Product route `/products/{slug}/`
  if (fullPath.startsWith('/products/')) {
    const slug = fullPath.replace('/products/', '').replace('/', '');
    const product = getProductBySlug(slug);
    if (product) {
      const canonical = product.url;
      const breadcrumbs = [
        { name: 'Home', url: SITE_DOMAIN },
        { name: 'Products', url: `${SITE_DOMAIN}/products/` },
        { name: product.title, url: canonical }
      ];
      const data = {
        path: fullPath,
        canonical,
        title: `${product.title} Benchmark Review & Price | Run On Console`,
        description: product.shortDesc || `${product.title} tested by Run On Console testing lab. Full specs, pros, cons, and retailer prices.`,
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

export function applyClientSideSeo(seo) {
  if (typeof document === 'undefined') return;
  if (seo.title) document.title = seo.title;
  let metaDesc = document.querySelector('meta[name="description"]');
  if (metaDesc && seo.description) metaDesc.setAttribute('content', seo.description);
}
