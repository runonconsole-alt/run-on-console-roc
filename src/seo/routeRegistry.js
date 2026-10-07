import { ALL_BLOGS, GAMING_CATEGORIES, ALL_PRODUCTS, PRODUCT_CATEGORIES } from '../data/initialData.js';

export const isStagingEnv = (() => {
  if (typeof window !== 'undefined' && window.location) {
    const host = window.location.hostname || '';
    if (host.includes('staging') || host.includes('loca.lt') || host.includes('localhost')) {
      return true;
    }
  }
  if (typeof import.meta !== 'undefined' && import.meta.env) {
    if (import.meta.env.MODE === 'staging' || import.meta.env.VITE_APP_ENV === 'staging') {
      return true;
    }
  }
  if (typeof process !== 'undefined' && process.argv && Array.isArray(process.argv)) {
    if (process.argv.includes('--staging')) return true;
  }
  if (typeof process !== 'undefined' && process.env) {
    if (process.env.VITE_APP_ENV === 'staging' || process.env.NODE_ENV === 'staging') return true;
  }
  return false;
})();

export const BASE_DOMAIN = isStagingEnv ? 'https://staging.runonconsole.com' : 'https://runonconsole.com';

/**
 * Generate a clean URL slug from any string title
 */
export function slugify(text) {
  if (!text) return '';
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/[\s_]+/g, '-')           // Replace spaces & underscores with -
    .replace(/[^\w\-]+/g, '')          // Remove all non-word chars
    .replace(/\-\-+/g, '-')            // Replace multiple - with single -
    .replace(/^-+/, '')                // Trim - from start
    .replace(/-+$/, '');               // Trim - from end
}

// Enhance ALL_PRODUCTS with slug, verified Brand Name, and ISO 8601 lastmod
export const ENHANCED_PRODUCTS = ALL_PRODUCTS.map(prod => {
  const slug = prod.slug || slugify(prod.title);
  
  const brandName = prod.brandName || prod.brand || 'Run On Console';

  return {
    ...prod,
    slug,
    brandName,
    url: `${BASE_DOMAIN}/products/${slug}/`,
    lastmod: prod.lastmod || '2024-05-18T10:00:00+00:00'
  };
});

// Product categories (keyboards, mice, …) — listing pages at /products/category/{slug}/
export const ENHANCED_PRODUCT_CATEGORIES = PRODUCT_CATEGORIES.map(cat => ({
  ...cat,
  url: `${BASE_DOMAIN}/products/category/${cat.slug}/`,
  path: `/products/category/${cat.slug}/`,
  lastmod: '2026-09-29T10:00:00+05:00'
}));

export function getProductCategoryBySlug(slug) {
  if (!slug) return null;
  const clean = slugify(slug);
  return ENHANCED_PRODUCT_CATEGORIES.find(c => c.slug === clean) || null;
}

// Helper function to resolve (internal-link:id) to canonical URL
export function resolveInternalLinks(content) {
  if (!content) return '';
  return content.replace(/\(internal-link:([a-zA-Z0-9_-]+)\)/g, (match, targetId) => {
    const prod = ENHANCED_PRODUCTS.find(p => p.id === targetId || p.slug === targetId);
    if (prod) return `(${prod.url})`;
    
    const blog = ALL_BLOGS.find(b => b.id === targetId || b.slug === targetId);
    if (blog) {
      const slug = blog.slug || slugify(blog.title);
      return `(${BASE_DOMAIN}/blogs/${slug}/)`;
    }

    console.warn(`⚠️ Warning: Could not resolve internal link: ${targetId}`);
    return `(${BASE_DOMAIN}/products/)`;
  });
}

// Enhance ALL_BLOGS with slug, resolved content, author URL, ISO date, and lastmod
export const ENHANCED_BLOGS = ALL_BLOGS.map(blog => {
  const slug = blog.slug || slugify(blog.title);
  const resolvedContent = resolveInternalLinks(blog.content);

  const isOmar = (blog.author || '').toLowerCase().includes('omar');
  const authorUrl = isOmar ? `${BASE_DOMAIN}/author/omar-abobakar/` : `${BASE_DOMAIN}/about/`;

  let isoDate = '2024-05-20';
  if (blog.date) {
    if (blog.date.includes('May 20, 2024')) isoDate = '2024-05-20';
    else if (blog.date.includes('May 19, 2024')) isoDate = '2024-05-19';
    else if (blog.date.includes('May 18, 2024')) isoDate = '2024-05-18';
    else if (blog.date.includes('May 10, 2024')) isoDate = '2024-05-10';
  }

  const lastmod = blog.updatedAt || `${isoDate}T10:00:00+00:00`;

  return {
    ...blog,
    slug,
    content: resolvedContent,
    authorUrl,
    isoDate,
    lastmod,
    url: `${BASE_DOMAIN}/blogs/${slug}/`
  };
});

// Enhance GAMING_CATEGORIES with url property and ISO 8601 lastmod
export const ENHANCED_CATEGORIES = GAMING_CATEGORIES.map(cat => ({
  ...cat,
  slug: cat.slug || slugify(cat.title),
  url: `${BASE_DOMAIN}/categories/${cat.slug || slugify(cat.title)}/`,
  lastmod: cat.lastmod || '2024-05-15T10:00:00+00:00'
}));

// Helper functions for lookup by slug
export function getBlogBySlug(slug) {
  const clean = slugify(slug);
  return ENHANCED_BLOGS.find(b => b.slug === clean || b.id === slug);
}

export function getCategoryBySlug(slug) {
  const clean = slugify(slug);
  return ENHANCED_CATEGORIES.find(c => c.slug === clean || c.id === slug);
}

export function getProductBySlug(slug) {
  const clean = slugify(slug);
  return ENHANCED_PRODUCTS.find(p => p.slug === clean || p.id === slug);
}

// Static Public Routes Definition with stable lastmod
export const STATIC_PUBLIC_ROUTES = [
  {
    path: '/',
    canonical: `${BASE_DOMAIN}/`,
    title: 'Run On Console | Gaming Hardware, Compatibility & Peripherals Hub',
    description: 'Run On Console is an independent gaming hardware, platform, and gear intelligence lab. Benchmark reviews, PC specs compatibility matrix, and esports guides.',
    h1: 'Discover the Best',
    category: 'Home',
    lastmod: '2024-05-20T10:00:00+00:00'
  },
  {
    path: '/blogs/',
    canonical: `${BASE_DOMAIN}/blogs/`,
    title: 'Hardware Reviews & PC Build Guides | Run On Console',
    description: 'Explore in-depth benchmark reviews, PC build guides, game performance analyses, and hardware buying advice from the Run On Console testing lab.',
    h1: 'Hardware Reviews & PC Build Guides',
    category: 'Blogs',
    lastmod: '2024-05-20T10:00:00+00:00'
  },
  {
    path: '/categories/',
    canonical: `${BASE_DOMAIN}/categories/`,
    title: '15 Gaming Platform Hubs & Specs Directory | Run On Console',
    description: 'Explore 15 comprehensive gaming categories and over 100+ hardware devices from x86 handhelds to PS5 Pro, Xbox, retro consoles, and emulation engines.',
    h1: '15 Gaming Platform Hubs & Specs Directory',
    category: 'Categories',
    lastmod: '2024-05-15T10:00:00+00:00'
  },
  {
    path: '/products/',
    canonical: `${BASE_DOMAIN}/products/`,
    title: 'Gaming Hardware & Esports Accessories Catalog | Run On Console',
    description: `Browse ${ALL_PRODUCTS.length} gaming keyboards, mice, headsets, speakers, monitors and graphics cards picked by Run On Console, with key specs and Amazon links.`,
    h1: 'Gaming Hardware & Esports Accessories Catalog',
    category: 'Products',
    lastmod: '2024-05-18T10:00:00+00:00'
  },
  {
    path: '/compatibility/',
    canonical: `${BASE_DOMAIN}/compatibility/`,
    title: 'Can I Run It? Custom PC Hardware Compatibility Matrix | Run On Console',
    description: 'Test your custom PC hardware specs against Call of Duty Black Ops 6, Cyberpunk 2077, and Elden Ring with verified official hardware requirements.',
    h1: 'Can I Run It',
    category: 'Compatibility',
    lastmod: '2024-05-12T10:00:00+00:00'
  },
  {
    path: '/about/',
    canonical: `${BASE_DOMAIN}/about/`,
    title: 'About Us - Independent Hardware Testing Lab | Run On Console',
    description: 'Learn about Run On Console\'s independent hardware testing lab, testing methodologies, oscilloscope latency measurements, and editorial integrity.',
    h1: 'Honest Reviews',
    category: 'About',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/author/omar-abobakar/',
    canonical: `${BASE_DOMAIN}/author/omar-abobakar/`,
    title: 'Omar Abobakar - Senior Hardware Columnist | Run On Console',
    description: 'Biography, testing focus, and hardware reviews published by Omar Abobakar, Senior Hardware Columnist at Run On Console Testing Lab.',
    h1: 'Omar Abobakar',
    category: 'Author',
    lastmod: '2024-05-20T10:00:00+00:00'
  },
  {
    path: '/contact/',
    canonical: `${BASE_DOMAIN}/contact/`,
    title: 'Contact Editorial Testing Lab | Run On Console',
    description: 'Get in touch with the Run On Console hardware lab for review sample submissions, reader feedback, and general editorial inquiries.',
    h1: 'Contact Editorial Testing Lab',
    category: 'Contact',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/write-for-us/',
    canonical: `${BASE_DOMAIN}/write-for-us/`,
    title: 'Write For Us & Submit Review Sample | Run On Console',
    description: 'Submit commercial guest posts, hardware review proposals, and tech articles to the Run On Console editorial team.',
    h1: 'Write For Run On Console',
    category: 'Write For Us',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/partnerships/',
    canonical: `${BASE_DOMAIN}/partnerships/`,
    title: 'Brand Advertising & Partnerships | Run On Console',
    description: 'Partner with Run On Console for hardware review sample testing, brand sponsorships, and commercial advertising opportunities.',
    h1: 'Brand Advertising & Partnerships',
    category: 'Partnerships',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/terms-and-conditions/',
    canonical: `${BASE_DOMAIN}/terms-and-conditions/`,
    title: 'Terms of Service & Conditions | Run On Console',
    description: 'Read Run On Console\'s terms of service, user account rules, content guidelines, and affiliate disclosure terms.',
    h1: 'Terms of Service & Conditions',
    category: 'Terms',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/privacy-policy/',
    canonical: `${BASE_DOMAIN}/privacy-policy/`,
    title: 'Privacy Policy & Affiliate Disclosure | Run On Console',
    description: 'How Run On Console handles your data, cookies and analytics, and our Amazon Associates affiliate disclosure: we earn from qualifying purchases at no extra cost to you.',
    h1: 'Privacy Policy',
    category: 'Privacy',
    lastmod: '2026-10-07T00:00:00+00:00'
  }
];

// Private / Non-Indexed Routes (Strictly Server & Client Noindex, Nofollow)
export const PRIVATE_ROUTES = [
  {
    path: '/auth/',
    canonical: `${BASE_DOMAIN}/auth/`,
    title: 'Authentication & Account Portal | Run On Console',
    description: 'Gamer authentication portal for Run On Console.',
    h1: 'Run On Console Portal',
    robots: 'noindex, nofollow'
  },
  {
    path: '/auth/login/',
    canonical: `${BASE_DOMAIN}/auth/login/`,
    title: 'Sign In to Your Account | Run On Console',
    description: 'Sign in to access your Run On Console wishlist, hardware alerts, and comments.',
    h1: 'Gamer Portal Sign In',
    robots: 'noindex, nofollow'
  },
  {
    path: '/auth/signup/',
    canonical: `${BASE_DOMAIN}/auth/signup/`,
    title: 'Create a Free Account | Run On Console',
    description: 'Register a free gamer account on Run On Console.',
    h1: 'Create Your Gamer Account',
    robots: 'noindex, nofollow'
  },
  {
    path: '/auth/verify-email/',
    canonical: `${BASE_DOMAIN}/auth/verify-email/`,
    title: 'Verify Email Address | Run On Console',
    description: 'Verify your email address to activate your Run On Console account.',
    h1: 'Email Verification',
    robots: 'noindex, nofollow'
  },
  {
    path: '/auth/forgot-password/',
    canonical: `${BASE_DOMAIN}/auth/forgot-password/`,
    title: 'Forgot Password | Run On Console',
    description: 'Request a password reset link for your Run On Console account.',
    h1: 'Forgot Password',
    robots: 'noindex, nofollow'
  },
  {
    path: '/auth/reset-password/',
    canonical: `${BASE_DOMAIN}/auth/reset-password/`,
    title: 'Reset Password | Run On Console',
    description: 'Choose a new password for your Run On Console account.',
    h1: 'Reset Password',
    robots: 'noindex, nofollow'
  },
  {
    path: '/profile/',
    canonical: `${BASE_DOMAIN}/profile/`,
    title: 'Gamer Profile & Dashboard | Run On Console',
    description: 'Manage your Run On Console profile, saved products, price alerts, and preferences.',
    h1: 'Gamer Profile Dashboard',
    robots: 'noindex, nofollow'
  }
];
