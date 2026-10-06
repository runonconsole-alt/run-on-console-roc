import React from 'react';
import { useApp } from '../context/AppContext';
import { ChevronRight, SlidersHorizontal, Star, MessageSquare, Zap } from 'lucide-react';

export const ReviewCard = ({ review }) => {
  const { setSelectedReview, toggleCompare, compareIds } = useApp();
  const isComparing = compareIds.includes(review.id);

  return (
    <div className="glass-card group flex flex-col justify-between overflow-hidden relative border border-[#27272a] hover:border-emerald-500/40 hover:-translate-y-1 hover:shadow-lg hover:shadow-emerald-500/10 transition-[transform,border-color,box-shadow] duration-200">
      
      {/* Top Banner Image */}
      <div className="relative w-full h-48 aspect-video bg-[#0e0e0e] overflow-hidden">
        <img 
          src={review.image} 
          alt={review.title}
          width="400"
          height="225"
          loading="lazy"
          className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-[#1c1b1b] via-transparent to-transparent"></div>

        {/* Category Tag */}
        <span className="absolute top-3 left-3 cyber-badge badge-cyan text-[10px]">
          {review.category}
        </span>

        {/* ROC Score Pill */}
        <div className="absolute top-3 right-3 bg-black/90 border border-[#00f0ff] px-2.5 py-1 flex items-center gap-1.5 shadow-[0_0_10px_rgba(0,240,255,0.4)]">
          <span className="font-mono text-[10px] text-[#a1a1aa] font-bold">FRAG</span>
          <span className="font-display font-extrabold text-base text-[#00f0ff]">
            {review.rocScore.toFixed(1)}
          </span>
        </div>
      </div>

      {/* Card Content Body */}
      <div className="p-5 flex-1 flex flex-col justify-between">
        
        <div>
          <h3 
            onClick={() => setSelectedReview(review)}
            className="font-display font-bold text-lg text-white group-hover:text-[#00f0ff] cursor-pointer transition-colors line-clamp-1 mb-1"
          >
            {review.title}
          </h3>
          <p className="font-mono text-xs text-[#00f0ff] mb-3 line-clamp-1">
            {review.subtitle}
          </p>
          <p className="text-xs text-[#a1a1aa] line-clamp-2 mb-4 leading-relaxed">
            {review.summary}
          </p>

          {/* Quick Specs Key/Val */}
          {review.specs && (
            <div className="border-t border-[#27272a] pt-3 mb-4 space-y-1.5 font-mono text-[11px]">
              {Object.entries(review.specs).slice(0, 2).map(([key, val]) => (
                <div key={key} className="flex justify-between items-center text-[#a1a1aa]">
                  <span>{key}:</span>
                  <span className="text-white truncate max-w-[150px] font-semibold">{val}</span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Bottom Actions */}
        <div className="border-t border-[#27272a] pt-3 flex items-center justify-between gap-2">
          
          <div className="flex items-center gap-2 font-mono text-xs text-[#a1a1aa]">
            <MessageSquare className="w-3.5 h-3.5 text-[#a1a1aa]" />
            <span>{review.comments ? review.comments.length : 0}</span>
          </div>

          <div className="flex items-center gap-2">
            <button 
              onClick={() => toggleCompare(review.id)}
              title="Compare specs"
              className={`p-2 border border-[#27272a] hover:border-[#b600f8] hover:text-[#ebb2ff] transition-colors ${
                isComparing ? 'border-[#39ff14] text-[#39ff14] bg-[#39ff14]/10' : 'text-[#a1a1aa]'
              }`}
            >
              <SlidersHorizontal className="w-4 h-4" />
            </button>

            <button 
              onClick={() => setSelectedReview(review)}
              className="btn-cyber-primary py-1.5 px-3 text-xs"
            >
              <span>SPECS</span>
              <ChevronRight className="w-3.5 h-3.5" />
            </button>
          </div>

        </div>

      </div>

    </div>
  );
};
