import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { 
  GAMING_CATEGORIES, 
  ALL_GAMING_DEVICES, 
  GAME_COMPATIBILITY_DATA, 
  COMMUNITY_TESTIMONIALS, 
  PAGE_FAQS 
} from '../data/initialData.js';
import { applyClientSideSeo, getSeoMetadata } from '../seo/seoConfig.js';
import { 
  ENHANCED_PRODUCTS, 
  ENHANCED_BLOGS, 
  getProductBySlug as getStaticProductBySlug,
  getBlogBySlug, 
  getCategoryBySlug, 
  slugify 
} from '../seo/routeRegistry.js';
import {
  INITIAL_SITE_IMAGES,
  INITIAL_SITE_CONTENT,
  INITIAL_SITE_METAS,
  INITIAL_INTERNAL_LINKS
} from '../data/adminInitialData.js';

// â”€â”€â”€ Public API endpoint (no auth needed) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const PRODUCTS_API = '/api/v1/products-public.php';

/**
 * Fetch all published products from the live database.
 * Falls back to static bundled data if the API is unavailable.
 */
async function fetchLiveProducts() {
  try {
    const res = await fetch(PRODUCTS_API + '?_=' + Date.now(), {
      cache: 'no-store',
      headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) throw new Error('API ' + res.status);
    const data = await res.json();
    if (data.success && Array.isArray(data.products) && data.products.length > 0) {
      return { products: data.products, categories: data.categories || [], fromApi: true };
    }
    throw new Error('Empty or invalid API response');
  } catch (e) {
    console.warn('[ROC] Products API unavailable, using bundled data:', e.message);
    return { products: ENHANCED_PRODUCTS, categories: [], fromApi: false };
  }
}

const AppContext = createContext();

export const AppProvider = ({ children, initialUrl = null }) => {
  // Navigation & Routing State
  const resolveInitialState = (urlPath) => {
    let path = urlPath;
    if (!path && typeof window !== 'undefined') {
      path = window.location.pathname;
    }
    if (!path) path = '/';

    path = path.toLowerCase().trim();

    if (path === '/' || path === '') return { page: 'home', param: null, is404: false };
    if (path.includes('/compatibility')) return { page: 'compatibility', param: null, is404: false };
    if (path.includes('/about')) return { page: 'about', param: null, is404: false };
    if (path.includes('/write-for-us')) return { page: 'write-for-us', param: null, is404: false };
    if (path.includes('/partnerships')) return { page: 'partnerships', param: null, is404: false };
    if (path.includes('/terms-and-conditions')) return { page: 'terms-and-conditions', param: null, is404: false };
    if (path.includes('/privacy-policy')) return { page: 'privacy-policy', param: null, is404: false };
    if (path.includes('/policy')) return { page: 'policy', param: null, is404: false };
    if (path.includes('/contact')) return { page: 'contact', param: null, is404: false };
    if (path.includes('/admin')) return { page: 'admin', param: null, is404: false };
    
    if (path.includes('/auth/login')) return { page: 'auth', param: 'login', is404: false };
    if (path.includes('/auth/signup')) return { page: 'auth', param: 'signup', is404: false };
    if (path.includes('/auth/verify-email')) return { page: 'auth', param: 'verify-email', is404: false };
    if (path.includes('/auth/forgot-password')) return { page: 'auth', param: 'forgot-password', is404: false };
    if (path.includes('/auth/reset-password')) return { page: 'auth', param: 'reset-password', is404: false };
    if (path.includes('/auth')) return { page: 'auth', param: 'login', is404: false };
    
    if (path.includes('/profile')) return { page: 'profile', param: null, is404: false };

    if (path.includes('/products/')) {
      // Category pages: /products/category/keyboards/
      if (path.includes('/products/category/')) {
        const catSlug = path.split('/products/category/')[1]?.replace(/\/+$/, '') || null;
        return { page: 'products', param: null, productCategory: catSlug, is404: false };
      }
      const parts = path.split('/products/').filter(Boolean);
      const slug = parts[0] ? parts[0].replace(/\/+$/, '') : null;
      if (slug) {
        // Try static catalog first (fast); dynamic products checked after API load
        const prod = getStaticProductBySlug(slug);
        if (prod) return { page: 'products', param: prod.slug, is404: false };
        // Not in static catalog: treat as dynamic product (resolves after API load)
        return { page: 'products', param: slug, is404: false };
      }
      return { page: 'products', param: null, is404: false };
    }

    if (path.includes('/blogs/')) {
      const parts = path.split('/blogs/').filter(Boolean);
      const slug = parts[0] ? parts[0].replace(/\/+$/, '') : null;
      if (slug) {
        const b = getBlogBySlug(slug);
        if (b) return { page: 'blogs', param: b.slug, is404: false };
        return { page: '404', param: null, is404: true };
      }
      return { page: 'blogs', param: null, is404: false };
    }

    if (path.includes('/categories/')) {
      const parts = path.split('/categories/').filter(Boolean);
      const slug = parts[0] ? parts[0].replace(/\/+$/, '') : null;
      if (slug) {
        const c = getCategoryBySlug(slug);
        if (c) return { page: 'categories', param: c.slug, is404: false };
        return { page: '404', param: null, is404: true };
      }
      return { page: 'categories', param: null, is404: false };
    }

    if (path.includes('/author/')) {
      if (typeof window !== 'undefined' && window.__INITIAL_CONTENT__ && window.__INITIAL_CONTENT__.pageType === 'author' && window.__INITIAL_CONTENT__.author) {
        return { page: 'author', param: window.__INITIAL_CONTENT__.author.slug, is404: false };
      }
      return { page: '404', param: null, is404: true };
    }

    return { page: '404', param: null, is404: true };
  };

  const initialResolution = resolveInitialState(initialUrl);

  const [currentPage, setCurrentPage] = useState(initialResolution.page);
  const [is404, setIs404] = useState(initialResolution.is404);
  const [selectedProductId, setSelectedProductId] = useState(initialResolution.page === 'products' ? initialResolution.param : null);
  const [selectedBlogId, setSelectedBlogId] = useState(initialResolution.page === 'blogs' ? initialResolution.param : null);
  const [selectedCategorySlug, setSelectedCategorySlug] = useState(initialResolution.page === 'categories' ? initialResolution.param || 'all' : 'all');
  const [searchQuery, setSearchQuery] = useState('');
  const [compareIds, setCompareIds] = useState([]);
  const [isCompareOpen, setIsCompareOpen] = useState(false);

  // Dynamic Data Lists — products start from static bundle, replaced by live API on mount
  const [products, setProducts] = useState(ENHANCED_PRODUCTS);
  const [productsLoading, setProductsLoading] = useState(true);
  const [productsFromApi, setProductsFromApi] = useState(false);
  const [blogs, setBlogs] = useState(ENHANCED_BLOGS);
  const [guestSubmissions, setGuestSubmissions] = useState([]);
  const [siteImages, setSiteImages] = useState(INITIAL_SITE_IMAGES);
  const [siteContent, setSiteContent] = useState(INITIAL_SITE_CONTENT);
  const [siteMetas, setSiteMetas] = useState(INITIAL_SITE_METAS);
  const [internalLinks, setInternalLinks] = useState(INITIAL_INTERNAL_LINKS);
  const [savedProductIds, setSavedProductIds] = useState([]);

  // Working Shopping Cart State
  const [cart, setCart] = useState([]);

  const addToCart = (product) => {
    if (!product) return;
    setCart(prev => {
      const existing = prev.find(item => item.id === product.id || item.slug === product.slug);
      if (existing) {
        return prev.map(item => (item.id === product.id || item.slug === product.slug) ? { ...item, quantity: (item.quantity || 1) + 1 } : item);
      }
      return [...prev, { ...product, quantity: 1 }];
    });
    showNotification(`Added "${product.name}" to cart!`);
  };

  const removeFromCart = (productId) => {
    setCart(prev => prev.filter(item => item.id !== productId && item.slug !== productId));
    showNotification(`Removed item from cart.`, "warning");
  };

  const clearCart = () => setCart([]);

  // Server Authentication & CSRF State
  const [currentUser, setCurrentUser] = useState(null);
  const [isSessionLoading, setIsSessionLoading] = useState(true);
  const [csrfToken, setCsrfToken] = useState('');
  const [authMode, setAuthMode] = useState(initialResolution.param || 'login');
  const [pendingActivationEmail, setPendingActivationEmail] = useState('');

  // Admin tab & notification
  const [adminTab, setAdminTab] = useState('overview');
  const [adminNotification, setAdminNotification] = useState(null);

  const [authConfig, setAuthConfig] = useState({
    smtp_configured: false,
    google_oauth_configured: false,
    captcha_configured: false,
    google_client_id: '',
    captcha_site_key: '',
    captcha_provider: 'turnstile'
  });

  // Fetch Server Session & CSRF Token on Page Mount
  const checkServerSession = async () => {
    if (typeof window === 'undefined') {
      setIsSessionLoading(false);
      return;
    }

    setIsSessionLoading(true);
    try {
      const resp = await fetch('/api/v1/auth.php?action=session');
      const data = await resp.json();
      if (data && data.success) {
        if (data.csrf_token) setCsrfToken(data.csrf_token);
        if (data.config) setAuthConfig(data.config);
        if (data.authenticated && data.user) {
          setCurrentUser(data.user);
        } else {
          setCurrentUser(null);
        }
      } else {
        setCurrentUser(null);
      }
    } catch (e) {
      setCurrentUser(null);
    } finally {
      setIsSessionLoading(false);
    }
  };

  useEffect(() => {
    checkServerSession();
  }, []);

  // ── Load live products from API on mount ─────────────────────────────────────
  useEffect(() => {
    if (typeof window === 'undefined') return;
    setProductsLoading(true);
    fetchLiveProducts().then(({ products: liveProducts, fromApi }) => {
      if (liveProducts && liveProducts.length > 0) {
        setProducts(liveProducts);
        setProductsFromApi(fromApi);
      }
    }).finally(() => setProductsLoading(false));
  }, []);

  // ── Refresh products from API (call this after CMS save) ─────────────────────
  const refreshProducts = useCallback(async () => {
    const { products: liveProducts, fromApi } = await fetchLiveProducts();
    if (liveProducts && liveProducts.length > 0) {
      setProducts(liveProducts);
      setProductsFromApi(fromApi);
    }
  }, []);


  useEffect(() => {
    if (typeof window === 'undefined') return;

    const syncUrlWithState = () => {
      const state = resolveInitialState(window.location.pathname);
      setCurrentPage(state.page);
      setIs404(state.is404);
      if (state.page === 'products') setSelectedProductId(state.param);
      if (state.page === 'blogs') setSelectedBlogId(state.param);
      if (state.page === 'categories') setSelectedCategorySlug(state.param || 'all');
      if (state.page === 'auth') setAuthMode(state.param || 'login');

      const seo = getSeoMetadata(window.location.pathname);
      applyClientSideSeo(seo);
    };

    window.addEventListener('popstate', syncUrlWithState);
    return () => window.removeEventListener('popstate', syncUrlWithState);
  }, []);

  const showNotification = (msg, type = "success") => {
    setAdminNotification({ msg, type });
    setTimeout(() => setAdminNotification(null), 3500);
  };

  const navigateTo = (page, param = null) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });

    let cleanPath = '/';

    if (page === 'home') cleanPath = '/';
    else if (page === 'compatibility') cleanPath = '/compatibility/';
    else if (page === 'products') {
      if (param) {
        const p = getProductBySlug(param);
        cleanPath = p ? p.url.replace(/^https?:\/\/[^\/]+/, '') : `/products/${slugify(param)}/`;
      } else {
        cleanPath = '/products/';
      }
    } else if (page === 'blogs') {
      if (param) {
        const b = getBlogBySlug(param);
        cleanPath = b ? b.url.replace(/^https?:\/\/[^\/]+/, '') : `/blogs/${slugify(param)}/`;
      } else {
        cleanPath = '/blogs/';
      }
    } else if (page === 'categories') {
      if (param && param !== 'all') {
        const c = getCategoryBySlug(param);
        cleanPath = c ? c.url.replace(/^https?:\/\/[^\/]+/, '') : `/categories/${slugify(param)}/`;
      } else {
        cleanPath = '/categories/';
      }
    } else if (page === 'about') cleanPath = '/about/';
    else if (page === 'write-for-us') cleanPath = '/write-for-us/';
    else if (page === 'partnerships') cleanPath = '/partnerships/';
    else if (page === 'terms-and-conditions') cleanPath = '/terms-and-conditions/';
    else if (page === 'privacy-policy') cleanPath = '/privacy-policy/';
    else if (page === 'policy') cleanPath = '/policy/';
    else if (page === 'contact') cleanPath = '/contact/';
    else if (page === 'admin') cleanPath = '/admin/';
    else if (page === 'auth') {
      if (param) cleanPath = `/auth/${param}/`;
      else cleanPath = '/auth/login/';
    } else if (page === 'profile') cleanPath = '/profile/';

    const seo = getSeoMetadata(cleanPath);
    applyClientSideSeo(seo);

    if (typeof window !== 'undefined' && window.history) {
      window.history.pushState({ page, param }, seo.title, cleanPath);
      // Trigger SPA Analytics Pageview for client-side route changes
      if (window.gtag && typeof window.gtag === 'function') {
        window.gtag('event', 'page_view', {
          page_title: seo.title,
          page_location: window.location.origin + cleanPath,
          page_path: cleanPath
        });
      }
    }

    if (page === 'auth') {
      setAuthMode(param || 'login');
    } else if (page === 'products') {
      setSelectedProductId(param);
      setSelectedBlogId(null);
    } else if (page === 'blogs') {
      setSelectedBlogId(param);
      setSelectedProductId(null);
    } else if (page === 'categories') {
      setSelectedCategorySlug(param || 'all');
    } else {
      setSelectedProductId(null);
      setSelectedBlogId(null);
    }
  };

  const navigateToProduct = (id) => navigateTo('products', id);
  const navigateToCategory = (slug) => navigateTo('categories', slug);
  const navigateToBlog = (id) => navigateTo('blogs', id);

  const REQUIRE_LOGIN_FOR_AFFILIATE_CLICK = false;

  const registerUser = async ({ name, username, email, password, confirmPassword, agreeTerms, captchaToken }) => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=signup', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ name, username, email, password, confirmPassword, agreeTerms, captchaToken })
      });
      const data = await resp.json();
      if (data && data.success) {
        setPendingActivationEmail(email);
        showNotification(data.message);
        return { success: true, message: data.message };
      } else {
        return { 
          success: false, 
          error: data.error || data.message || 'Registration failed', 
          message: data.message || data.error,
          request_id: data.request_id 
        };
      }
    } catch (e) {
      return { success: false, error: 'Network error during registration.' };
    }
  };

  const loginUser = async ({ email, password }) => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=login', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ email, password })
      });
      const data = await resp.json();
      if (data && data.success) {
        setCurrentUser(data.user);
        if (data.csrf_token) setCsrfToken(data.csrf_token);
        showNotification(`Welcome back, ${data.user.name}!`);
        return { success: true, user: data.user };
      } else {
        return { success: false, error: data.error, message: data.message, email: data.email };
      }
    } catch (e) {
      return { success: false, error: 'Network error during login.' };
    }
  };

  const logoutUser = async () => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=logout', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        }
      });
      const data = await resp.json();
      if (data && data.success) {
        setCurrentUser(null);
        showNotification('Logged out successfully.');
        await checkServerSession();
        navigateTo("auth", "login");
      }
    } catch (e) {
      setCurrentUser(null);
      navigateTo("auth", "login");
    }
  };

  const verifyUserEmail = async (token) => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=verify-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ token })
      });
      const data = await resp.json();
      if (data && data.success) {
        showNotification('Email verified. Opening your profile.');
        return { success: true, message: data.message };
      } else {
        return { success: false, error: data.error || 'Verification failed.' };
      }
    } catch (e) {
      return { success: false, error: 'Network error.' };
    }
  };

  const resendVerification = async (email) => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=resend-verification', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ email })
      });
      return await resp.json();
    } catch (e) {
      return { success: false, error: 'Network error.' };
    }
  };

  const requestPasswordReset = async (email) => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=forgot-password', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ email })
      });
      return await resp.json();
    } catch (e) {
      return { success: false, error: 'Network error.' };
    }
  };

  const resetPasswordWithToken = async ({ token, password, confirmPassword }) => {
    try {
      const resp = await fetch('/api/v1/auth.php?action=reset-password', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ token, password, confirmPassword })
      });
      return await resp.json();
    } catch (e) {
      return { success: false, error: 'Network error.' };
    }
  };

  const toggleSaveProduct = async (productId) => {
    if (!currentUser) {
      setSavedProductIds(prev => {
        const exists = prev.includes(productId);
        const updated = exists ? prev.filter(id => id !== productId) : [...prev, productId];
        showNotification(exists ? "Removed from saved gear" : "Saved to your favorites!");
        return updated;
      });
      return { success: true };
    }
    try {
      const resp = await fetch('/api/v1/profile.php?action=toggle-save-product', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ productId })
      });
      const data = await resp.json();
      if (data && data.success) {
        showNotification(data.message);
        return data;
      }
    } catch (e) {}
  };

  const createPriceAlert = async (productId, targetPrice) => {
    if (!currentUser) {
      showNotification(`Price alert tracking set at $${targetPrice}!`);
      return { success: true };
    }
    try {
      const resp = await fetch('/api/v1/profile.php?action=create-price-alert', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ productId, targetPrice })
      });
      const data = await resp.json();
      if (data && data.success) {
        showNotification(data.message);
        return data;
      }
    } catch (e) {}
  };

  const deletePriceAlert = async (alertId) => {
    try {
      const resp = await fetch('/api/v1/profile.php?action=delete-price-alert', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ alertId })
      });
      return await resp.json();
    } catch (e) {}
  };

  const addComment = async ({ articleId, productId, commentText }) => {
    if (!currentUser) {
      navigateTo("auth", "login");
      return;
    }
    try {
      const resp = await fetch('/api/v1/profile.php?action=add-comment', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ articleId, productId, commentText })
      });
      const data = await resp.json();
      if (data && data.success) {
        showNotification(data.message);
        return data;
      }
    } catch (e) {}
  };

  const updateUserProfile = async (profileFields) => {
    try {
      const resp = await fetch('/api/v1/profile.php?action=update-profile', {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(profileFields)
      });
      const data = await resp.json();
      if (data && data.success) {
        setCurrentUser(prev => ({ ...prev, ...profileFields }));
        showNotification('Profile updated successfully!');
        return { success: true };
      } else {
        return { success: false, error: data.error };
      }
    } catch (e) {
      return { success: false, error: 'Network error.' };
    }
  };

  const addGuestSubmission = (subData) => {
    const newSub = { ...subData, id: 'sub-' + Date.now(), date: new Date().toLocaleDateString(), status: 'Pending Review' };
    setGuestSubmissions(prev => [newSub, ...prev]);
    showNotification(`Submitted proposal for ${subData.name}!`);
    return newSub;
  };

  const addProduct = (prodData) => {
    const newProd = { ...prodData, id: 'prod-' + Date.now(), rating: 4.9, reviewCount: 1 };
    setProducts(prev => [newProd, ...prev]);
    showNotification(`Added hardware: "${newProd.name}"`);
    return newProd;
  };

  const updateProduct = (id, fields) => {
    setProducts(prev => prev.map(p => p.id === id ? { ...p, ...fields } : p));
    showNotification(`Updated hardware: "${fields.name || id}"`);
  };

  const deleteProduct = (id) => {
    setProducts(prev => prev.filter(p => p.id !== id));
    if (selectedProductId === id) setSelectedProductId(null);
    showNotification(`Hardware removed from catalog`, "warning");
  };

  const addBlog = (blogData) => {
    const newBlog = { ...blogData, id: 'blog-' + Date.now(), readTime: '6 min read', comments: [] };
    setBlogs(prev => [newBlog, ...prev]);
    showNotification(`Published article: "${newBlog.title}"`);
    return newBlog;
  };

  const updateBlog = (id, fields) => {
    setBlogs(prev => prev.map(b => b.id === id ? { ...b, ...fields } : b));
    showNotification(`Updated article: "${fields.title || id}"`);
  };

  const deleteBlog = (id) => {
    setBlogs(prev => prev.filter(b => b.id !== id));
    if (selectedBlogId === id) setSelectedBlogId(null);
    showNotification(`Article removed`, "warning");
  };

  // Media Manager Helpers
  const addSiteImage = (img) => {
    setSiteImages(prev => [img, ...prev]);
    showNotification(`Saved image asset: "${img.title}"`);
  };

  const updateSiteImage = (id, fields) => {
    setSiteImages(prev => prev.map(img => img.id === id ? { ...img, ...fields } : img));
    showNotification(`Updated image metadata.`);
  };

  const deleteSiteImage = (id) => {
    setSiteImages(prev => prev.filter(img => img.id !== id));
    showNotification(`Image asset removed`, "warning");
  };

  // Site Content Helpers
  const updateSiteContent = (newContent) => {
    setSiteContent(newContent);
    showNotification(`Site content updated.`);
  };

  const resetSiteContentToDefaults = () => {
    setSiteContent(INITIAL_SITE_CONTENT);
  };

  // SEO Metas Helpers
  const updateSiteMetas = (routePath, metaObj) => {
    setSiteMetas(prev => ({
      ...prev,
      [routePath]: metaObj
    }));
  };

  // Internal Links Helpers
  const addInternalLink = (link) => {
    setInternalLinks(prev => [link, ...prev]);
  };

  const deleteInternalLink = (id) => {
    setInternalLinks(prev => prev.filter(l => l.id !== id));
    showNotification(`Internal link removed`, "warning");
  };

  const toggleCompare = (id) => {
    setCompareIds(prev => {
      if (prev.includes(id)) return prev.filter(item => item !== id);
      if (prev.length >= 3) {
        alert("Maximum 3 hardware items can be compared side-by-side!");
        return prev;
      }
      return [...prev, id];
    });
  };

  const clearCompare = () => setCompareIds([]);

  return (
    <AppContext.Provider value={{
      currentPage,
      setCurrentPage,
      is404,
      setIs404,
      selectedProductId,
      setSelectedProductId,
      selectedBlogId,
      setSelectedBlogId,
      selectedCategorySlug,
      setSelectedCategorySlug,
      searchQuery,
      setSearchQuery,
      compareIds,
      toggleCompare,
      clearCompare,
      isCompareOpen,
      setIsCompareOpen,
      products,
      productsLoading,
      productsFromApi,
      refreshProducts,
      blogs,
      guestSubmissions,
      siteImages,
      addSiteImage,
      updateSiteImage,
      deleteSiteImage,
      siteContent,
      updateSiteContent,
      resetSiteContentToDefaults,
      siteMetas,
      updateSiteMetas,
      internalLinks,
      addInternalLink,
      deleteInternalLink,
      savedProductIds,
      categories: GAMING_CATEGORIES,
      allDevices: ALL_GAMING_DEVICES,
      gameCompatibility: GAME_COMPATIBILITY_DATA,
      testimonials: COMMUNITY_TESTIMONIALS,
      pageFaqs: PAGE_FAQS,
      adminTab,
      setAdminTab,
      adminNotification,
      currentUser,
      isSessionLoading,
      csrfToken,
      authConfig,
      authMode,
      setAuthMode,
      pendingActivationEmail,
      setPendingActivationEmail,
      registerUser,
      verifyUserEmail,
      resendVerification,
      requestPasswordReset,
      resetPasswordWithToken,
      REQUIRE_LOGIN_FOR_AFFILIATE_CLICK,
      loginUser,
      logoutUser,
      updateUserProfile,
      toggleSaveProduct,
      createPriceAlert,
      deletePriceAlert,
      addComment,
      navigateTo,
      navigateToProduct,
      navigateToCategory,
      navigateToBlog,
      addGuestSubmission,
      addProduct,
      updateProduct,
      deleteProduct,
      addBlog,
      updateBlog,
      deleteBlog,
      cart,
      addToCart,
      removeFromCart,
      clearCart
    }}>
      {children}
    </AppContext.Provider>
  );
};

export const useApp = () => useContext(AppContext);
