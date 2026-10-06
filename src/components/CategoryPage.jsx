import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { CATEGORIES_LIST } from '../data/initialData';
import { Star, ShoppingCart, ExternalLink, SlidersHorizontal, ArrowLeft, Check, ShieldCheck, Tag } from 'lucide-react';

export const CategoryPage = () => {
  const { 
    activeCategory, 
    setActiveCategory, 
    reviews, 
    setSelectedArticle, 
    toggleCompare, 
    compareIds, 
    searchQuery 
  } = useApp();

  const [sortBy, setSortBy] = useState("rating"); // 'rating', 'price_asc', 'price_desc', 'newest'
  const [filterBrand, setFilterBrand] = useState("all");

  const currentCatInfo = CATEGORIES_LIST.find(c => c.slug === activeCategory) || {
    name: "All Gaming Gear & Reviews",
    desc: "Browse our comprehensive, tested and verified gaming hardware reviews and buying guides."
  };

  // Filter items
  let filtered = reviews.filter(r => {
    if (activeCategory === "all") return true;
    const catName = currentCatInfo.name.toLowerCase();
    const rCat = (r.category || "").toLowerCase();
    return rCat.includes(activeCategory) || catName.includes(rCat) || rCat.includes(catName);
  });

  if (searchQuery) {
    const q = searchQuery.toLowerCase();
    filtered = filtered.filter(r => 
      r.title.toLowerCase().includes(q) || 
      (r.summary || "").toLowerCase().includes(q) ||
      (r.subtitle || "").toLowerCase().includes(q)
    );
  }

  // Sort items
  filtered.sort((a, b) => {
    if (sortBy === "rating") return (b.rating || b.rocScore) - (a.rating || a.rocScore);
    if (sortBy === "newest") return new Date(b.date) - new Date(a.date);
    const parsePrice = (p) => parseFloat((p || "$0").replace(/[^0-9.]/g, '')) || 0;
    if (sortBy === "price_asc") return parsePrice(a.price) - parsePrice(b.price);
    if (sortBy === "price_desc") return parsePrice(b.price) - parsePrice(a.price);
    return 0;
  });

  return (
    <div className="space-y-8 py-4">
      
      {/* Category Hero Header */}
      <div className="bg-[#0B132B] text-white rounded-2xl p-6 sm:p-10 border border-[#1E293B] shadow-xl relative overflow-hidden">
        
        {/* Breadcrumbs */}
        <div className="flex items-center gap-2 text-xs text-slate-400 mb-3">
          <button onClick={() => setActiveCategory('all')} className="hover:text-white flex items-center gap-1 font-semibold">
            <ArrowLeft className="w-3.5 h-3.5" /> All Gear
          </button>
          <span>/</span>
          <span className="text-blue-400 font-bold">{currentCatInfo.name}</span>
        </div>

        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
          <div>
            <h1 className="font-display font-extrabold text-2xl sm:text-4xl text-white mb-2">
              {currentCatInfo.name}
            </h1>
            <p className="text-slate-300 text-xs sm:text-sm max-w-2xl leading-relaxed">
              {currentCatInfo.desc}
            </p>
          </div>

          <div className="bg-slate-800/80 border border-slate-700 rounded-xl px-4 py-2 text-center shrink-0">
            <div className="text-blue-400 font-display font-extrabold text-xl">{filtered.length}</div>
            <div className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">TESTED GUIDES</div>
          </div>
        </div>

      </div>

      {/* Filter & Sorting Controls */}
      <div className="bg-white border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
        
        {/* Category Pills Slider */}
        <div className="flex items-center gap-2 overflow-x-auto w-full sm:w-auto no-scrollbar pb-1 sm:pb-0">
          {CATEGORIES_LIST.map((cat) => (
            <button
              key={cat.slug}
              onClick={() => setActiveCategory(cat.slug)}
              className={`px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all ${
                activeCategory === cat.slug
                  ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/30'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-900'
              }`}
            >
              {cat.name}
            </button>
          ))}
        </div>

        {/* Sort Dropdown */}
        <div className="flex items-center gap-2 self-end sm:self-auto shrink-0 text-xs">
          <span className="text-slate-500 font-bold">Sort By:</span>
          <select 
            value={sortBy} 
            onChange={(e) => setSortBy(e.target.value)}
            className="bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-900 focus:outline-none focus:border-blue-500"
          >
            <option value="rating">Top Rated (ROC Score)</option>
            <option value="newest">Latest Published</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
          </select>
        </div>

      </div>

      {/* Product Cards Grid with Affiliate Buy Buttons */}
      {filtered.length === 0 ? (
        <div className="bg-white border border-slate-200 rounded-2xl p-12 text-center my-6">
          <p className="text-slate-500 text-sm font-semibold">No hardware articles found in this category.</p>
          <button 
            onClick={() => setActiveCategory('all')} 
            className="mt-4 bg-blue-600 text-white text-xs font-bold px-4 py-2 rounded-lg"
          >
            Show All Gear
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {filtered.map((item) => {
            const isComparing = compareIds.includes(item.id);
            const amazonLink = item.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20";

            return (
              <div 
                key={item.id}
                className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 flex flex-col justify-between group"
              >
                {/* Image & Badges */}
                <div className="relative h-52 bg-slate-900 overflow-hidden cursor-pointer" onClick={() => setSelectedArticle(item)}>
                  <img 
                    src={item.image} 
                    alt={item.title} 
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  
                  {/* Top Badges */}
                  <div className="absolute top-3 left-3 flex flex-col gap-1.5">
                    {item.badge && (
                      <span className="bg-amber-500 text-slate-950 text-[10px] font-extrabold px-2.5 py-0.5 rounded shadow-sm">
                        {item.badge}
                      </span>
                    )}
                    <span className="bg-blue-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded shadow-sm uppercase">
                      {item.category}
                    </span>
                  </div>

                  {/* Compare Button */}
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      toggleCompare(item.id);
                    }}
                    className={`absolute top-3 right-3 p-1.5 rounded-lg text-xs transition-all ${
                      isComparing 
                        ? 'bg-green-600 text-white shadow-md' 
                        : 'bg-black/60 text-white hover:bg-blue-600 backdrop-blur-sm'
                    }`}
                    title="Compare item"
                  >
                    <SlidersHorizontal className="w-3.5 h-3.5" />
                  </button>

                  {/* Rating Overlay */}
                  <div className="absolute bottom-3 right-3 bg-slate-900/90 text-white px-2.5 py-1 rounded-lg text-xs font-bold flex items-center gap-1">
                    <Star className="w-3.5 h-3.5 text-amber-400 fill-amber-400" />
                    <span>{item.rating || (item.rocScore/2).toFixed(1)}</span>
                  </div>
                </div>

                {/* Body Content */}
                <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                  
                  <div>
                    <h3 
                      onClick={() => setSelectedArticle(item)}
                      className="font-display font-extrabold text-base text-slate-900 group-hover:text-blue-600 cursor-pointer transition-colors leading-snug line-clamp-2 mb-1"
                    >
                      {item.title}
                    </h3>
                    <p className="text-xs text-blue-600 font-semibold mb-2 line-clamp-1">
                      {item.subtitle}
                    </p>
                    <p className="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                      {item.summary}
                    </p>
                  </div>

                  {/* Pricing & Affiliate Action */}
                  <div className="pt-4 border-t border-slate-100 space-y-3">
                    
                    {/* Price & Discount Info */}
                    <div className="flex items-center justify-between">
                      <div>
                        <span className="text-lg font-display font-extrabold text-slate-900">
                          {item.price || "$149.99"}
                        </span>
                        {item.originalPrice && (
                          <span className="text-xs text-slate-400 line-through ml-2">
                            {item.originalPrice}
                          </span>
                        )}
                      </div>

                      {item.discount && (
                        <span className="bg-emerald-50 text-emerald-700 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-200">
                          {item.discount}
                        </span>
                      )}
                    </div>

                    {/* Affiliate Links / Buy Buttons */}
                    <div className="grid grid-cols-2 gap-2">
                      {/* Direct Amazon Affiliate Button */}
                      <a
                        href={amazonLink}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs py-2.5 px-3 rounded-lg flex items-center justify-center gap-1.5 shadow-sm transition-all"
                      >
                        <ShoppingCart className="w-3.5 h-3.5" />
                        <span>Check Amazon</span>
                        <ExternalLink className="w-3 h-3 ml-0.5 opacity-70" />
                      </a>

                      {/* Read In-Depth Blog Review */}
                      <button
                        onClick={() => setSelectedArticle(item)}
                        className="bg-slate-900 hover:bg-blue-600 text-white font-bold text-xs py-2.5 px-3 rounded-lg transition-all text-center"
                      >
                        Read Review →
                      </button>
                    </div>

                  </div>

                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Affiliate Disclosure Notice */}
      <div className="bg-slate-100 border border-slate-200 rounded-xl p-4 text-center text-xs text-slate-500">
        <ShieldCheck className="w-4 h-4 text-slate-400 inline-block mr-1" />
        <span className="font-semibold">Affiliate Disclosure:</span> When you purchase through links on Run On Console, we may earn an affiliate commission from Amazon and partner retailers at zero extra cost to you.
      </div>

    </div>
  );
};
