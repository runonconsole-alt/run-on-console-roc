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

// Product categories (keyboards, mice, …): pages at /products/{slug}/ (rendered by the server). `path` is where
// the build writes the page that the server uses as its layout template.
export const ENHANCED_PRODUCT_CATEGORIES = PRODUCT_CATEGORIES.map(cat => ({
  ...cat,
  url: `${BASE_DOMAIN}/products/${cat.slug}/`,
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

  const authorUrl = `${BASE_DOMAIN}/author/roc-team/`;

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
  url: `${BASE_DOMAIN}/gaming-platforms/${cat.slug || slugify(cat.title)}/`,
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
    title: 'Gaming Gear Picks & Can I Run It Checker | Run On Console',
    description: 'Independent gaming gear picks for PC and console, guides to 15 gaming platforms and a free checker for 500+ games. Find out if your PC can run it.',
    h1: 'Discover the Best',
    category: 'Home',
    lastmod: '2024-05-20T10:00:00+00:00'
  },
  {
    path: '/blogs/',
    canonical: `${BASE_DOMAIN}/blogs/`,
    title: 'Hardware Reviews & PC Build Guides | Run On Console',
    description: 'Gaming hardware guides, PC build advice, game requirements and buying tips from Run On Console.',
    h1: 'Hardware Reviews & PC Build Guides',
    category: 'Blogs',
    lastmod: '2024-05-20T10:00:00+00:00'
  },
  {
    path: '/categories/',
    canonical: `${BASE_DOMAIN}/gaming-platforms/`,
    title: '15 Gaming Platform Hubs & Specs Directory | Run On Console',
    description: 'Explore 15 comprehensive gaming categories and over 100+ hardware devices from x86 handhelds to PS5 Pro, Xbox, retro consoles, and emulation engines.',
    h1: '15 Gaming Platform Hubs & Specs Directory',
    category: 'Categories',
    lastmod: '2024-05-15T10:00:00+00:00'
  },
  {
    path: '/products/',
    canonical: `${BASE_DOMAIN}/products/`,
    title: 'Gaming Hardware & Accessories Catalog | Run On Console',
    description: `Browse ${ALL_PRODUCTS.length} gaming keyboards, mice, headsets, speakers, monitors and graphics cards picked by Run On Console, with key specs and Amazon links.`,
    h1: 'Gaming Hardware & Esports Accessories Catalog',
    category: 'Products',
    lastmod: '2024-05-18T10:00:00+00:00'
  },
  {
    path: '/compatibility/',
    canonical: `${BASE_DOMAIN}/compatibility/`,
    title: 'Can I Run It? PC Game Requirements Checker | Run On Console',
    description: 'Check if your PC can run 500+ games. Pick a game and your graphics card, processor and RAM to compare them with the official minimum and recommended specs.',
    h1: 'Can I Run It',
    category: 'Compatibility',
    lastmod: '2024-05-12T10:00:00+00:00'
  },
  {
    path: '/about/',
    canonical: `${BASE_DOMAIN}/about/`,
    title: 'About Run On Console | Independent Gaming Gear Site',
    description: 'Who runs Run On Console, how we pick gaming gear, how the free game checker works and how we earn money as an Amazon Associate.',
    h1: 'Honest Reviews',
    category: 'About',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/author/roc-team/',
    canonical: `${BASE_DOMAIN}/author/roc-team/`,
    title: 'ROC: who writes Run On Console',
    description: 'ROC (Run On Console) writes the guides, keeps the gaming gear picks and the game requirements checker up to date.',
    h1: 'ROC (Run On Console)',
    category: 'Author',
    lastmod: '2024-05-20T10:00:00+00:00'
  },
  {
    path: '/contact/',
    canonical: `${BASE_DOMAIN}/contact/`,
    title: 'Contact Us | Run On Console',
    description: 'Contact Run On Console: questions, corrections, review samples and partnerships. Email support@runonconsole.com or use the form.',
    h1: 'Contact Run On Console',
    category: 'Contact',
    lastmod: '2024-05-10T10:00:00+00:00'
  },
  {
    path: '/write-for-us/',
    canonical: `${BASE_DOMAIN}/write-for-us/`,
    title: 'Write For Us & Advertise | Run On Console',
    description: 'Pitch a guest article, a sponsored post or an advertising campaign to Run On Console. Email comments@runonconsole.com or use the form.',
    h1: 'Write For Run On Console',
    category: 'Write For Us',
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
    description: 'How Run On Console handles your data, cookies and analytics, plus our Amazon Associates disclosure: we earn from qualifying purchases.',
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
