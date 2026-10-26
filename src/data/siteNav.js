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
 * lg:col-span-1 lg:col-span-2 lg:col-span-3 lg:col-span-4 lg:col-span-5 lg:col-span-7 contents
 */
import { useEffect, useState } from 'react';
import { NAV_ICONS } from './navIcons.js';

export const DEFAULT_SITE_NAV = {
  logo: { line1: 'RUN ON', line2: 'CONSOLE', tagline: 'THE GAMING & HARDWARE HUB', image: '' },
  header: [
    { label: 'HOME', url: '/' },
    { label: 'CATEGORY', url: '/category/' },
    { label: 'PRODUCTS', url: '/products/' },
    { label: 'SYSTEMS', url: '/compatibility/' },
    // Blogs, guides and news in one menu (owner, 2026-10-16); /updates/ shows them together with filters.
    { label: 'UPDATES', url: '/updates/', children: [
      { title: 'Blogs', desc: 'Reviews, comparisons and buying advice', url: '/blogs/', icon: 'book' },
      { title: 'Guides', desc: 'Step by step: buying, setup, game settings', url: '/guides/', icon: 'file-text' },
      { title: 'News', desc: 'Gaming and hardware news, checked daily', url: '/news/', icon: 'newspaper' },
    ] },
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
        { label: 'Can I Run It? PC Game Checker', url: '/compatibility/', icon: 'gamepad' },
        { label: 'PC Game Requirements A to Z', url: '/can-i-run/', icon: 'monitor' },
        { label: 'Compare', url: '/compare/', icon: 'scale' },
        { label: 'Gaming Platforms', url: '/gaming-platforms/', icon: 'grid' },
        { label: 'About Run On Console', url: '/about/', icon: 'info' },
      ] },
      { title: 'UPDATES & TERMS', icon: 'file-text', links: [
        { label: 'Blogs', url: '/blogs/', icon: 'book' },
        { label: 'News', url: '/news/', icon: 'newspaper' },
        { label: 'Guides', url: '/guides/', icon: 'file-text' },
        { label: 'Terms & Conditions', url: '/terms-and-conditions/', icon: 'scale' },
        { label: 'Privacy Policy & Affiliate Disclosure', url: '/privacy-policy/', icon: 'file-check' },
        { label: 'Contact Us', url: '/contact/', icon: 'mail' },
        { label: 'Suggestions', url: '/suggestions/', icon: 'star' },
        { label: 'Write For Us & Advertising', url: '/write-for-us/', icon: 'pen' },
      ] },
    ],
    copyright: '© {year} ROC (Run On Console). All rights reserved. Built for gamers & console enthusiasts.',
    disclaimer: 'As an Amazon Associate we earn from qualifying purchases. Prices and stock are shown on Amazon.',
  },
};

/**
 * The menu as a React hook: redraws when the CMS site builder sends a new menu
 * (it sets window.__ROC_NAV and fires "roc:nav" for a live preview).
 */
export function useSiteNav() {
  const [, setTick] = useState(0);
  useEffect(() => {
    const redraw = () => setTick((t) => t + 1);
    window.addEventListener('roc:nav', redraw);
    return () => window.removeEventListener('roc:nav', redraw);
  }, []);
  return getSiteNav();
}

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

/** Footer column widths on large screens (12-column grid): brand 5 + menu columns 4 + FOLLOW US 3; two columns get the same width. */
const SPANS = { 1: [4], 2: [2, 2], 3: [2, 1, 1], 4: [1, 1, 1, 1] };
export function footerSpan(count, i) {
  return 'lg:col-span-' + ((SPANS[count] || [])[i] || 2);
}

export function iconSvg(name, cls) {
  const svg = NAV_ICONS[name] || '';
  return svg.replace('__CLS__', cls);
}

export const currentYear = () => String(new Date().getFullYear());
