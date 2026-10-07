import React, { useState, useMemo } from 'react';
import { useApp } from '../context/AppContext';
import {
  ShoppingCart, SlidersHorizontal, Search, ExternalLink, ArrowRight,
  ArrowLeft, Sparkles, PackageCheck, RotateCcw, Cpu, Tag, CheckCircle2, XCircle
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { playClickSound, playHoverSound } from '../utils/audioEffects';
import { ENHANCED_PRODUCT_CATEGORIES, getProductCategoryBySlug } from '../seo/routeRegistry';

const BUY_REL = 'sponsored nofollow noopener noreferrer';

/** Left-click navigates inside the app; ctrl/cmd/middle click keeps the normal browser behaviour. */
const spaClick = (fn) => (e) => {
  if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
  e.preventDefault();
  playClickSound();
  fn();
};

const ProductCard = ({ prod, onOpen, onToggleCompare, isComparing }) => {
  const url = `/products/${prod.slug}/`;
  return (
    <Tilt3DCard className="game-card flex flex-col justify-between group p-5 space-y-4">
      <div className="space-y-3">
        <a
          href={url}
          onClick={spaClick(() => onOpen(prod.id))}
          onMouseEnter={playHoverSound}
          className="relative block h-44 rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 shadow-sm"
        >
          <img
            src={prod.image}
            alt={prod.title}
            width="400"
            height="300"
            loading="lazy"
            decoding="async"
            className="w-full h-full object-cover opacity-90 group-hover:scale-105 transition-transform duration-500"
          />
          {prod.badge && (
            <span className="absolute top-2.5 left-2.5 max-w-[80%] truncate bg-emerald-600 text-white text-[9px] font-extrabold px-2.5 py-0.5 rounded-full uppercase shadow-sm">
              {prod.badge}
            </span>
          )}
          <span className="absolute bottom-2.5 left-2.5 bg-slate-900/90 text-white text-[10px] font-extrabold px-2 py-0.5 rounded backdrop-blur-xs">
            {prod.brand}
          </span>
        </a>

        <div>
          <span className="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">
            {prod.category}
          </span>
          <h3 className="font-display font-extrabold text-sm sm:text-base text-slate-900 leading-snug">
            <a
              href={url}
              onClick={spaClick(() => onOpen(prod.id))}
              className="text-slate-900 group-hover:text-emerald-600 transition-colors no-underline"
            >
              {prod.title}
            </a>
          </h3>
          <p className="text-[11px] text-slate-500 line-clamp-2 mt-1">
            {prod.subtitle}
          </p>
        </div>
      </div>

      <div className="pt-3 border-t border-slate-100 space-y-2">
        {prod.price && (
          <div className="font-display font-extrabold text-lg text-emerald-600">{prod.price}</div>
        )}
        <div className="grid grid-cols-[1fr_auto] gap-2">
          <a
            href={prod.affiliateLinks?.amazon}
            target="_blank"
            rel={BUY_REL}
            aria-label={`Check price of ${prod.title} on Amazon`}
            onClick={(e) => { e.stopPropagation(); playClickSound(); }}
            className="bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-xs py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all hover:scale-102 no-underline"
          >
            <ShoppingCart className="w-3.5 h-3.5" />
            <span>Check price on Amazon</span>
            <ExternalLink className="w-3 h-3 opacity-70" />
          </a>
          <button
            type="button"
            onClick={(e) => { e.stopPropagation(); playClickSound(); onToggleCompare(prod.id); }}
            aria-label={`Compare ${prod.title}`}
            title={isComparing ? 'Remove from compare' : 'Add to compare'}
            className={`px-3 rounded-xl border text-xs font-bold transition-colors ${
              isComparing ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white border-slate-300 text-slate-600 hover:border-emerald-600'
            }`}
          >
            <SlidersHorizontal className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </Tilt3DCard>
  );
};

export const ProductsView = () => {
  const {
    products = [],
    selectedProductId,
    productCategorySlug = 'all',
    navigateTo,
    navigateToProduct,
    toggleCompare,
    compareIds = [],
    searchQuery,
    pageFaqs
  } = useApp();

  const [localSearch, setLocalSearch] = useState('');
  const [selectedBrand, setSelectedBrand] = useState('all');
  const [sortBy, setSortBy] = useState('rank');

  const activeCategory = productCategorySlug !== 'all' ? getProductCategoryBySlug(productCategorySlug) : null;

  const categoryHref = (slug) => (slug === 'all' ? '/products/' : `/products/${slug}/`);
  const openCategory = (slug) => {
    setSelectedBrand('all');
    navigateTo('products', slug === 'all' ? null : slug);
  };

  // --- PRODUCT DETAIL PAGE ---
  if (selectedProductId) {
    const product = products.find(p => p.id === selectedProductId || p.slug === selectedProductId) || products[0];
    const isComparing = compareIds.includes(product.id);
    const category = getProductCategoryBySlug(product.categorySlug);
    const related = products.filter(p => p.categorySlug === product.categorySlug && p.id !== product.id).slice(0, 6);
    const amazonLink = product.affiliateLinks?.amazon;
    const bestbuyLink = product.affiliateLinks?.bestbuy;
    const officialLink = product.affiliateLinks?.official;

    return (
      <div className="max-w-6xl mx-auto py-6 space-y-8 animate-page-in">

        <nav aria-label="Breadcrumb" className="text-xs font-semibold text-slate-500 flex flex-wrap items-center gap-1.5">
          <a href="/products/" onClick={spaClick(() => openCategory('all'))} className="hover:text-emerald-600 no-underline text-slate-500">Products</a>
          {category && (
            <>
              <span>/</span>
              <a href={category.path} onClick={spaClick(() => openCategory(category.slug))} className="hover:text-emerald-600 no-underline text-slate-500">{category.name}</a>
            </>
          )}
          <span>/</span>
          <span className="text-slate-800">{product.title}</span>
        </nav>

        <div className="flex items-center justify-between gap-3">
          <a
            href={category ? category.path : '/products/'}
            onClick={spaClick(() => openCategory(category ? category.slug : 'all'))}
            className="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm no-underline"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>All {category ? category.name : 'products'}</span>
          </a>

          <button
            onClick={() => { playClickSound(); toggleCompare(product.id); }}
            className={`text-xs font-bold px-4 py-2 rounded-xl border transition-all flex items-center gap-2 shadow-sm ${
              isComparing ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white border-slate-300 text-slate-700 hover:border-emerald-600'
            }`}
          >
            <SlidersHorizontal className="w-4 h-4" />
            <span>{isComparing ? '✓ Added to Compare' : 'Add to Compare'}</span>
          </button>
        </div>

        <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8">
          <div className="lg:col-span-5 flex flex-col justify-center">
            <Tilt3DCard className="relative rounded-2xl overflow-hidden bg-slate-950 p-4 border border-slate-800 shadow-xl group">
              <img
                src={product.image}
                alt={product.title}
                className="w-full h-80 object-cover rounded-xl group-hover:scale-105 transition-transform duration-500"
              />
              {product.badge && (
                <span className="absolute top-4 left-4 max-w-[85%] bg-emerald-600 text-white font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider badge-glow">
                  {product.badge}
                </span>
              )}
            </Tilt3DCard>
          </div>

          <div className="lg:col-span-7 space-y-5">
            <div>
              <span className="text-xs font-bold text-emerald-600 uppercase tracking-wider">{product.category}</span>
              <h1 className="font-display font-extrabold text-2xl sm:text-3xl text-slate-900 leading-tight mt-1">
                {product.title}
              </h1>
              <p className="text-xs sm:text-sm text-slate-500 font-medium mt-1">
                by <strong className="text-slate-700">{product.brand}</strong>
              </p>
            </div>

            {(product.price || product.rocScore || product.bestFor) && (
              <div className="flex flex-wrap items-center gap-3 border-y border-slate-100 py-3">
                {product.price && (
                  <span className="font-display font-extrabold text-2xl text-emerald-600">{product.price}</span>
                )}
                {product.rocScore && (
                  <span className="bg-emerald-50 border border-emerald-200 text-emerald-800 font-extrabold text-xs px-2.5 py-1 rounded-lg">
                    ★ {product.rocScore} ROC Score / 10
                  </span>
                )}
                {product.bestFor && (
                  <span className="bg-emerald-50 border border-emerald-200 text-emerald-800 font-extrabold text-xs px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                    <Tag className="w-3.5 h-3.5" /> {product.bestFor}
                  </span>
                )}
              </div>
            )}

            <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
              {product.fullReview || product.shortDesc}
            </p>

            {Array.isArray(product.features) && product.features.length > 0 && (
              <ul className="flex flex-wrap gap-2">
                {product.features.map((f) => (
                  <li key={f} className="text-[11px] font-bold text-slate-700 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-lg">
                    {f}
                  </li>
                ))}
              </ul>
            )}

            <div className="space-y-2 pt-2">
              {amazonLink && (
                <a
                  href={amazonLink}
                  target="_blank"
                  rel={BUY_REL}
                  aria-label={`Check price of ${product.title} on Amazon`}
                  onClick={() => playClickSound()}
                  className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-sm py-3.5 px-6 rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition-all hover:scale-102 no-underline"
                >
                  <ShoppingCart className="w-4 h-4" />
                  <span>Check price & stock on Amazon</span>
                  <ExternalLink className="w-4 h-4 opacity-70" />
                </a>
              )}
              {(bestbuyLink || officialLink) && (
                <div className="grid grid-cols-2 gap-2">
                  {bestbuyLink && (
                    <a href={bestbuyLink} target="_blank" rel={BUY_REL}
                      className="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs py-2.5 px-4 rounded-xl flex items-center justify-center gap-1.5 transition-colors no-underline">
                      <span>Best Buy</span><ExternalLink className="w-3 h-3 text-slate-400" />
                    </a>
                  )}
                  {officialLink && (
                    <a href={officialLink} target="_blank" rel={BUY_REL}
                      className="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs py-2.5 px-4 rounded-xl flex items-center justify-center gap-1.5 transition-colors no-underline">
                      <span>Official store</span><ExternalLink className="w-3 h-3 text-slate-400" />
                    </a>
                  )}
                </div>
              )}
              <p className="text-[11px] text-slate-400">
                Prices and stock change often; Amazon shows the current price. We may earn a commission from qualifying purchases.
              </p>
            </div>
          </div>
        </div>

        {product.specs && (
          <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
            <h2 className="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <Cpu className="w-4 h-4 text-emerald-600" />
              <span>Key specifications</span>
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              {Object.entries(product.specs).map(([key, val]) => (
                <div key={key} className="flex justify-between gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="font-bold text-slate-500 shrink-0">{key}:</span>
                  <span className="font-semibold text-slate-900 text-right">{val}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {((product.pros && product.pros.length > 0) || (product.cons && product.cons.length > 0)) && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {product.pros && product.pros.length > 0 && (
              <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-2">
                <h2 className="font-display font-extrabold text-sm text-slate-900 uppercase">Pros</h2>
                {product.pros.map(p => <p key={p} className="text-xs text-slate-600 flex gap-2"><CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />{p}</p>)}
              </div>
            )}
            {product.cons && product.cons.length > 0 && (
              <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-2">
                <h2 className="font-display font-extrabold text-sm text-slate-900 uppercase">Cons</h2>
                {product.cons.map(c => <p key={c} className="text-xs text-slate-600 flex gap-2"><XCircle className="w-4 h-4 text-rose-500 shrink-0" />{c}</p>)}
              </div>
            )}
          </div>
        )}

        {related.length > 0 && (
          <div className="space-y-4">
            <div className="flex items-center justify-between gap-3">
              <h2 className="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider">
                More {category ? category.name : 'products'}
              </h2>
              {category && (
                <a href={category.path} onClick={spaClick(() => openCategory(category.slug))}
                  className="text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1 no-underline">
                  See all {category.count} <ArrowRight className="w-3.5 h-3.5" />
                </a>
              )}
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {related.map((rel) => (
                <ProductCard key={rel.id} prod={rel} onOpen={navigateToProduct} onToggleCompare={toggleCompare} isComparing={compareIds.includes(rel.id)} />
              ))}
            </div>
          </div>
        )}
      </div>
    );
  }

  // --- CATALOG (all products, or one category) ---
  const inScope = activeCategory ? products.filter(p => p.categorySlug === activeCategory.slug) : products;
  const brands = ['all', ...Array.from(new Set(inScope.map(p => p.brand).filter(Boolean))).sort((a, b) => a.localeCompare(b))];
  const query = (localSearch || searchQuery || '').toLowerCase().trim();

  const filtered = inScope.filter(p => {
    if (selectedBrand !== 'all' && p.brand !== selectedBrand) return false;
    if (query) {
      const hay = `${p.title} ${p.brand} ${p.subtitle} ${p.bestFor} ${p.category}`.toLowerCase();
      if (!hay.includes(query)) return false;
    }
    return true;
  });
  const catOrder = ENHANCED_PRODUCT_CATEGORIES.map(c => c.slug);
  filtered.sort((a, b) => {
    if (sortBy === 'name') return a.title.localeCompare(b.title);
    if (sortBy === 'brand') return a.brand.localeCompare(b.brand) || a.title.localeCompare(b.title);
    return (catOrder.indexOf(a.categorySlug) - catOrder.indexOf(b.categorySlug)) || ((a.rank || 0) - (b.rank || 0));
  });

  const grouped = !activeCategory && !query && selectedBrand === 'all' && sortBy === 'rank';
  const resetFilters = () => { playClickSound(); setLocalSearch(''); setSelectedBrand('all'); setSortBy('rank'); };

  return (
    <div className="space-y-10 py-6 animate-page-in">

      <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        <CyberMatrixHoloBackground />
        <div className="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          <div className="lg:col-span-7 space-y-4">
            <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
              <Sparkles className="w-3.5 h-3.5" /> {activeCategory ? `${activeCategory.count} PICKS` : 'GAMING GEAR PICKS BY CATEGORY'}
            </span>
            <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
              {activeCategory ? (
                <BouncyText text={`Best ${activeCategory.name}`} enableAudio={true} />
              ) : (
                <>
                  <BouncyText text="Gaming Gear" enableAudio={true} /> <br />
                  <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
                    <BouncyText text="Picks by Category" enableAudio={true} />
                  </span>
                </>
              )}
            </h1>
            <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">
              {activeCategory
                ? activeCategory.desc
                : 'Keyboards, mice, headsets, speakers, monitors and graphics cards, each with what it is best for, key specs and a direct Amazon link.'}
            </p>
            <div className="flex flex-wrap gap-2.5 pt-2">
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <PackageCheck className="w-3.5 h-3.5 text-emerald-400" />
                <span>{inScope.length} products</span>
              </span>
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <ShoppingCart className="w-3.5 h-3.5 text-teal-400" />
                <span>Direct Amazon links</span>
              </span>
            </div>
          </div>

          <div className="lg:col-span-5 grid grid-cols-2 gap-3">
            {(activeCategory ? [activeCategory] : ENHANCED_PRODUCT_CATEGORIES.slice(0, 4)).map(c => (
              <a
                key={c.slug}
                href={c.path}
                onClick={spaClick(() => openCategory(c.slug))}
                className={`bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group no-underline ${activeCategory ? 'col-span-2' : ''}`}
              >
                <img src={c.image} alt={c.name} className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" />
                <div className="text-xs font-display font-bold text-white">{c.name}</div>
                <div className="text-[10px] text-emerald-300 font-medium">{c.count} picks</div>
              </a>
            ))}
          </div>
        </div>
      </div>

      {/* Category navigation */}
      <nav aria-label="Product categories" className="flex flex-wrap gap-2">
        {[{ slug: 'all', name: 'All products', count: products.length }, ...ENHANCED_PRODUCT_CATEGORIES].map(c => {
          const active = (activeCategory ? activeCategory.slug : 'all') === c.slug;
          return (
            <a
              key={c.slug}
              href={categoryHref(c.slug)}
              onClick={spaClick(() => openCategory(c.slug))}
              aria-current={active ? 'page' : undefined}
              className={`text-xs font-bold px-4 py-2 rounded-full border transition-colors no-underline ${
                active ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-300 hover:border-emerald-600'
              }`}
            >
              {c.name} <span className={active ? 'text-emerald-100' : 'text-slate-400'}>({c.count})</span>
            </a>
          );
        })}
      </nav>

      {/* Filter bar */}
      <div className="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-3 shadow-xs">
        <div className="relative flex-1 max-w-md">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="search"
            value={localSearch}
            onChange={(e) => setLocalSearch(e.target.value)}
            placeholder={activeCategory ? `Search ${activeCategory.name.toLowerCase()}…` : 'Search products or brands…'}
            aria-label="Search products"
            className="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:border-emerald-500"
          />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <select
            value={selectedBrand}
            onChange={(e) => setSelectedBrand(e.target.value)}
            aria-label="Brand"
            className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
          >
            {brands.map(b => <option key={b} value={b}>{b === 'all' ? 'All brands' : b}</option>)}
          </select>
          <select
            value={sortBy}
            onChange={(e) => setSortBy(e.target.value)}
            aria-label="Sort"
            className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
          >
            <option value="rank">Our order</option>
            <option value="name">Name A–Z</option>
            <option value="brand">Brand A–Z</option>
          </select>
          <button onClick={resetFilters} className="text-[11px] font-bold text-slate-400 hover:text-emerald-600 flex items-center gap-1 px-2">
            <RotateCcw className="w-3 h-3" /> Reset
          </button>
        </div>
      </div>

      {filtered.length === 0 ? (
        <div className="bg-white border border-slate-200 rounded-3xl p-12 text-center space-y-3">
          <p className="text-slate-500 text-sm font-semibold">No products match your search.</p>
          <button onClick={resetFilters} className="bg-emerald-600 text-white text-xs font-bold py-2.5 px-6 rounded-xl shadow-md">
            Reset filters
          </button>
        </div>
      ) : grouped ? (
        ENHANCED_PRODUCT_CATEGORIES.map(cat => {
          const list = filtered.filter(p => p.categorySlug === cat.slug);
          if (!list.length) return null;
          return (
            <section key={cat.slug} className="space-y-4" aria-labelledby={`cat-${cat.slug}`}>
              <div className="flex items-end justify-between gap-3 border-b border-emerald-100 pb-2">
                <div>
                  <h2 id={`cat-${cat.slug}`} className="font-display font-extrabold text-lg sm:text-xl text-slate-900">
                    <a href={cat.path} onClick={spaClick(() => openCategory(cat.slug))} className="text-slate-900 hover:text-emerald-700 no-underline">
                      {cat.name}
                    </a>
                  </h2>
                  <p className="text-xs text-slate-500">{cat.desc}</p>
                </div>
                <a href={cat.path} onClick={spaClick(() => openCategory(cat.slug))}
                  className="text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1 shrink-0 no-underline">
                  See all {list.length} <ArrowRight className="w-3.5 h-3.5" />
                </a>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                {list.slice(0, 4).map(prod => (
                  <ProductCard key={prod.id} prod={prod} onOpen={navigateToProduct} onToggleCompare={toggleCompare} isComparing={compareIds.includes(prod.id)} />
                ))}
              </div>
            </section>
          );
        })
      ) : (
        <div className="space-y-4">
          <div className="text-xs font-bold text-slate-600">
            Showing <span className="text-emerald-600 font-extrabold">{filtered.length}</span> of {inScope.length} products
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
            {filtered.map(prod => (
              <ProductCard key={prod.id} prod={prod} onOpen={navigateToProduct} onToggleCompare={toggleCompare} isComparing={compareIds.includes(prod.id)} />
            ))}
          </div>
        </div>
      )}

      <FAQSection
        faqs={pageFaqs.products || pageFaqs.categories}
        title="Gaming Gear FAQs"
        subtitle="How products are listed, where the Amazon buttons go, and how affiliate links work."
      />
    </div>
  );
};
