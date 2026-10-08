/**
 * Header menu, footer and logo text: editable in CMS > Menus & footer.
 *
 * DEFAULT_SITE_NAV is what the site shows until the CMS saves something. When it does,
 * the server (api/v1/cms/site-layer-lib.php) redraws the same parts of every page from
 * the saved menu and puts the menu in the page as window.__ROC_NAV, so React renders
 * exactly the same thing. Keep the markup of the marked parts (data-roc-region,
 * data-roc-text, data-roc-logo) in step with rocNav*() in site-layer-lib.php.
 *
 * Tailwind classes used only through this data (so the CSS build keeps them):
 * lg:col-span-1 lg:col-span-2 lg:col-span-3 lg:col-span-4 lg:col-span-7 contents
 */
import { NAV_ICONS } from './navIcons.js';

export const DEFAULT_SITE_NAV = {
  logo: { line1: 'RUN ON', line2: 'CONSOLE', tagline: 'THE GAMING & HARDWARE HUB', image: '' },
  header: [
    { label: 'HOME', url: '/' },
    {
      label: 'PRODUCTS', url: '/products/',
      children: [
        { title: 'PC Hardware', desc: 'Graphics cards and PC components', url: '/products/pc-hardware/', icon: 'cpu' },
        { title: 'Gaming Hardware', desc: 'Monitors, mice, keyboards, headsets, speakers', url: '/products/gaming-hardware/', icon: 'gamepad' },
      ],
      button: { label: 'View all products →', url: '/products/' },
    },
    { label: 'SYSTEMS', url: '/compatibility/' },
    { label: 'BLOGS', url: '/blogs/' },
    { label: 'ABOUT US', url: '/about/' },
  ],
  footer: {
    about: 'ROC (Run On Console) is an independent gaming site: gear picks for PC and console players, guides for every gaming platform and a free "Can I run it" checker built on official game requirements.',
    points: [
      { icon: 'check', text: 'Independent picks based on specs and published reviews' },
      { icon: 'shield', text: 'Sponsored content is always labelled' },
    ],
    columns: [
      { title: 'EXPLORE HUB', icon: 'compass', links: [
        { label: 'Home', url: '/', icon: 'home' },
        { label: 'Products', url: '/products/', icon: 'cpu' },
        { label: 'Systems', url: '/compatibility/', icon: 'gamepad' },
        { label: 'Blogs', url: '/blogs/', icon: 'book' },
        { label: 'Gaming Platforms', url: '/gaming-platforms/', icon: 'grid' },
        { label: 'About Run On Console', url: '/about/', icon: 'info' },
      ] },
      { title: 'PARTNERS', icon: 'handshake', links: [
        { label: 'Write For Us & Advertising', url: '/write-for-us/', icon: 'pen' },
        { label: 'Contact Us', url: '/contact/', icon: 'mail' },
      ] },
      { title: 'POLICIES', icon: 'file-text', links: [
        { label: 'Terms & Conditions', url: '/terms-and-conditions/', icon: 'scale' },
        { label: 'Privacy Policy & Affiliate Disclosure', url: '/privacy-policy/', icon: 'file-check' },
      ] },
    ],
    copyright: '© {year} ROC (Run On Console). All rights reserved. Built for gamers & console enthusiasts.',
    disclaimer: 'As an Amazon Associate we earn from qualifying purchases. Prices and stock are shown on Amazon.',
  },
};

/** The menu in use: the CMS one when the page carries it, else the default. */
export function getSiteNav() {
  if (typeof window !== 'undefined' && window.__ROC_NAV && typeof window.__ROC_NAV === 'object') {
    const n = window.__ROC_NAV;
    return {
      logo: { ...DEFAULT_SITE_NAV.logo, ...(n.logo || {}) },
      header: Array.isArray(n.header) ? n.header : DEFAULT_SITE_NAV.header,
      footer: { ...DEFAULT_SITE_NAV.footer, ...(n.footer || {}) },
    };
  }
  return DEFAULT_SITE_NAV;
}

/** Same rule as rocNavActive() on the server. */
export function navIsActive(url, path) {
  if (!url || !path) return false;
  return path === url || (url !== '/' && url.endsWith('/') && path.startsWith(url));
}

/** Footer column widths on large screens (12-column grid; the logo column takes 5). */
const SPANS = { 1: [7], 2: [4, 3], 3: [3, 2, 2], 4: [2, 2, 2, 1] };
export function footerSpan(count, i) {
  return 'lg:col-span-' + ((SPANS[count] || [])[i] || 2);
}

export function iconSvg(name, cls) {
  const svg = NAV_ICONS[name] || '';
  return svg.replace('__CLS__', cls);
}

export const currentYear = () => String(new Date().getFullYear());
