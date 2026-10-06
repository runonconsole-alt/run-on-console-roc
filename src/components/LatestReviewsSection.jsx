import React from 'react';
import { useApp } from '../context/AppContext';
import { Star, ArrowRight, ShoppingCart, SlidersHorizontal, PackageCheck } from 'lucide-react';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';

export const LatestReviewsSection = () => {
  const { products, navigateToProduct, navigateTo, toggleCompare, compareIds, searchQuery } = useApp();

  const filtered = products.filter(r => {
    if (!searchQuery) return true;
    const q = searchQuery.toLowerCase();
    return r.title.toLowerCase().includes(q) || 
           (r.category || "").toLowerCase().includes(q) || 
           (r.shortDesc || "").toLowerCase().includes(q);
  });

  return (
    <section id="latest-reviews-section" className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
      
      {/* Sleek Green Section Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-emerald-100 pb-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md">
            <PackageCheck className="w-5 h-5 animate-pulse" />
          </div>
          <div>
            <h2 className="font-display font-extrabold text-xl sm:text-2xl text-emerald-950 uppercase tracking-wide">
              <BouncyText text="LATEST TESTED HARDWARE & DEALS" />
            </h2>
            <p className="text-xs text-emerald-700 font-semibold">
              Complete lab testing verdicts, oscilloscope latency scores, and lowest verified prices.
            </p>
          </div>
        </div>

        <a 
          href="/products/"
          onClick={(e) => {
            if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
              e.preventDefault();
              navigateTo('products');
            }
          }}
          className="bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-display font-extrabold px-6 py-2.5 rounded-full flex items-center gap-2 uppercase tracking-wider shadow-md border border-emerald-500/40 transition-all hover:scale-105 self-start sm:self-auto no-underline"
        >
          <BouncyText text={`VIEW ALL ${products.length} PRODUCTS`} />
          <ArrowRight className="w-4 h-4 text-emerald-300" />
        </a>
      </div>

      {/* FULL-WIDTH 4-COLUMN RESPONSIVE GRID */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        {filtered.slice(0, 8).map((review) => {
          const isComparing = compareIds.includes(review.id);
          const ratingVal = review.rating || (review.rocScore / 2).toFixed(1);
          const amazonLink = review.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20";
          const productUrl = `/products/${review.slug || review.id}/`;

          return (
            <Tilt3DCard 
              key={review.id}
              className="game-card group flex flex-col justify-between"
            >
              {/* Product Image Anchor */}
              <a 
                href={productUrl}
                onClick={(e) => {
                  if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                    e.preventDefault();
                    navigateToProduct(review.id);
                  }
                }}
                className="relative h-48 w-full overflow-hidden bg-slate-900 cursor-pointer block"
              >
                <img 
                  src={review.image} 
                  alt={review.title} 
                  width="400"
                  height="300"
                  loading="lazy"
                  decoding="async"
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
                
                {/* Badges */}
                <div className="absolute top-2.5 left-2.5 flex flex-col gap-1">
                  {review.badge && (
                    <span className="bg-emerald-600 text-white text-[9px] font-extrabold uppercase px-2 py-0.5 rounded shadow-sm badge-glow">
                      {review.badge}
                    </span>
                  )}
                  <span className="bg-slate-900/90 text-white text-[9px] font-extrabold uppercase px-2 py-0.5 rounded shadow-sm backdrop-blur-xs">
                    {review.category}
                  </span>
                </div>

                {/* Compare Quick Toggle */}
                <button
                  type="button"
                  aria-label={`Compare ${review.title}`}
                  onClick={(e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    toggleCompare(review.id);
                  }}
                  className={`absolute top-2.5 right-2.5 p-1.5 rounded-lg text-xs transition-colors ${
                    isComparing 
                      ? 'bg-emerald-600 text-white shadow-md' 
                      : 'bg-black/60 hover:bg-emerald-600 text-white backdrop-blur-xs'
                  }`}
                  title="Compare specs"
                >
                  <SlidersHorizontal className="w-3.5 h-3.5" />
                </button>

                {/* Score Pill */}
                <div className="absolute bottom-2.5 right-2.5 bg-slate-900/90 text-white px-2 py-0.5 rounded-md text-[10px] font-extrabold flex items-center gap-1 backdrop-blur-xs">
                  <Star className="w-3 h-3 text-amber-400 fill-amber-400" />
                  <span>{ratingVal}</span>
                </div>
              </a>

              {/* Card Body */}
              <div className="p-4 flex-1 flex flex-col justify-between space-y-3">
                <div>
                  <h3 className="font-display font-bold text-sm text-slate-900 group-hover:text-emerald-700 leading-snug line-clamp-1 mb-1">
                    <a
                      href={productUrl}
                      onClick={(e) => {
                        if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                          e.preventDefault();
                          navigateToProduct(review.id);
                        }
                      }}
                      className="text-slate-900 group-hover:text-emerald-700 no-underline"
                    >
                      <BouncyText text={review.title} />
                    </a>
                  </h3>
                  <p className="text-[11px] text-emerald-700 font-semibold mb-2 line-clamp-1">
                    {review.subtitle}
                  </p>
                  <p className="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                    {review.shortDesc || review.summary}
                  </p>
                </div>

                {/* Price & Actions */}
                <div className="pt-3 border-t border-slate-100 space-y-2.5">
                  <div className="flex items-center justify-between">
                    <span className="font-display font-extrabold text-base text-slate-900">
                      {review.price || "$149.99"}
                    </span>
                    {review.discount && (
                      <span className="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                        {review.discount}
                      </span>
                    )}
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <a 
                      href={amazonLink}
                      target="_blank"
                      rel="sponsored noopener noreferrer"
                      className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-[10px] py-2 px-2 rounded-xl flex items-center justify-center gap-1 shadow-xs transition-all hover:scale-105 text-center no-underline"
                    >
                      <ShoppingCart className="w-3 h-3" />
                      <span>Buy Amazon</span>
                    </a>

                    <a 
                      href={productUrl}
                      onClick={(e) => {
                        if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                          e.preventDefault();
                          navigateToProduct(review.id);
                        }
                      }}
                      className="bg-slate-900 hover:bg-emerald-600 text-white font-bold text-[10px] py-2 px-2 rounded-xl transition-colors text-center no-underline block"
                    >
                      Full Specs →
                    </a>
                  </div>
                </div>

              </div>
            </Tilt3DCard>
          );
        })}
      </div>

    </section>
  );
};
