import React from 'react';
import { useApp } from '../context/AppContext';
import { Award, Zap, ChevronRight, SlidersHorizontal, ShieldCheck } from 'lucide-react';

export const HeroFeatured = () => {
  const { reviews, setSelectedReview, toggleCompare, compareIds } = useApp();
  const featured = reviews.find(r => r.featured) || reviews[0];

  if (!featured) return null;

  const isComparing = compareIds.includes(featured.id);

  return (
    <section className="relative w-full overflow-hidden my-6">
      {/* Background Image Container */}
      <div className="relative w-full min-h-[420px] lg:min-h-[480px] bg-[#141414] border border-[#27272a] overflow-hidden group">
        
        {/* Product Image */}
        <img 
          src={featured.image} 
          alt={featured.title}
          className="absolute inset-0 w-full h-full object-cover opacity-65 group-hover:scale-105 transition-transform duration-700 ease-out"
        />

        {/* Cyber Dark Gradients */}
        <div className="absolute inset-0 bg-gradient-to-t from-[#0e0e0e] via-[#0e0e0e]/70 to-transparent"></div>
        <div className="absolute inset-0 bg-gradient-to-r from-[#0e0e0e] via-[#0e0e0e]/80 to-transparent"></div>

        {/* Glowing Ambient Light */}
        <div className="absolute -top-24 -left-24 w-96 h-96 bg-[#00f0ff]/20 rounded-full blur-3xl pointer-events-none"></div>

        {/* Content Overlay */}
        <div className="relative z-10 p-6 sm:p-10 max-w-4xl flex flex-col justify-end h-full min-h-[420px] lg:min-h-[480px]">
          
          {/* Top Badges */}
          <div className="flex flex-wrap items-center gap-3 mb-4">
            <span className="cyber-badge badge-cyan flex items-center gap-1.5">
              <Award className="w-3.5 h-3.5" /> EDITOR'S CHOICE FEATURED
            </span>
            <span className="cyber-badge badge-purple">{featured.category}</span>
            <span className="font-mono text-xs text-[#a1a1aa] flex items-center gap-1">
              <Zap className="w-3.5 h-3.5 text-[#39ff14]" /> Tested by {featured.author}
            </span>
          </div>

          {/* Title & Subtitle */}
          <h2 className="font-display font-bold text-2xl sm:text-4xl text-white tracking-tight leading-tight mb-2 group-hover:text-[#00f0ff] transition-colors">
            {featured.title}
          </h2>
          <p className="font-mono text-xs sm:text-sm text-[#00f0ff] mb-4 tracking-wide">
            // {featured.subtitle}
          </p>

          {/* Summary Text */}
          <p className="text-sm sm:text-base text-[#a1a1aa] max-w-2xl mb-6 line-clamp-2 sm:line-clamp-3">
            {featured.summary}
          </p>

          {/* Quick Specs Chips */}
          {featured.specs && (
            <div className="flex flex-wrap gap-2 mb-6 font-mono text-xs">
              {Object.entries(featured.specs).slice(0, 3).map(([key, val]) => (
                <div key={key} className="bg-[#131313]/90 border border-[#27272a] px-3 py-1 text-white">
                  <span className="text-[#a1a1aa]">{key}:</span> <span className="text-[#00f0ff] font-semibold">{val}</span>
                </div>
              ))}
            </div>
          )}

          {/* Actions & ROC Score */}
          <div className="flex flex-wrap items-center gap-4">
            
            {/* ROC Score Badge */}
            <div className="bg-[#131313] border-2 border-[#00f0ff] p-3 flex items-center gap-3 shadow-[0_0_20px_rgba(0,240,255,0.3)]">
              <div className="font-display font-extrabold text-3xl text-[#00f0ff]">
                {featured.rocScore.toFixed(1)}
              </div>
              <div className="border-l border-[#27272a] pl-3 text-left">
                <div className="font-mono text-[10px] uppercase text-[#a1a1aa] tracking-widest">FRAG SCORE</div>
                <div className="font-mono text-xs font-bold text-[#39ff14]">TACTICAL PRO</div>
              </div>
            </div>

            {/* Read Review Button */}
            <button 
              onClick={() => setSelectedReview(featured)}
              className="btn-cyber-primary text-sm py-3 px-6"
            >
              <span>EXPLORE FULL BENCHMARK</span>
              <ChevronRight className="w-4 h-4" />
            </button>

            {/* Compare Button */}
            <button 
              onClick={() => toggleCompare(featured.id)}
              className={`btn-cyber-secondary text-xs py-3 px-4 ${isComparing ? 'border-[#39ff14] text-[#39ff14]' : ''}`}
            >
              <SlidersHorizontal className="w-4 h-4" />
              <span>{isComparing ? 'ADDED TO COMPARE' : 'COMPARE SPECS'}</span>
            </button>

          </div>

        </div>

      </div>
    </section>
  );
};
