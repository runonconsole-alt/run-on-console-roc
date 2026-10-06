import React from 'react';
import { useApp } from '../context/AppContext';
import { ReviewCard } from './ReviewCard';
import { SearchX, Filter } from 'lucide-react';

export const ReviewGrid = () => {
  const { reviews, activeCategory, searchQuery } = useApp();

  const filtered = reviews.filter(item => {
    const matchesCategory = activeCategory === "All" || item.category === activeCategory;
    const q = searchQuery.toLowerCase();
    const matchesSearch = !q || 
      item.title.toLowerCase().includes(q) || 
      item.subtitle.toLowerCase().includes(q) || 
      item.summary.toLowerCase().includes(q) ||
      (item.category && item.category.toLowerCase().includes(q));
    
    return matchesCategory && matchesSearch;
  });

  return (
    <section className="my-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-display font-bold text-xl text-white flex items-center gap-2">
          <span>HARDWARE INTELLIGENCE CATALOG</span>
          <span className="font-mono text-xs text-[#00f0ff] font-normal">[{filtered.length} REVIEWS]</span>
        </h3>
      </div>

      {filtered.length === 0 ? (
        <div className="glass-panel p-12 text-center my-8 border border-[#27272a]">
          <SearchX className="w-12 h-12 text-[#a1a1aa] mx-auto mb-3" />
          <h4 className="font-display text-lg text-white mb-1">No Hardware Matches Found</h4>
          <p className="font-mono text-xs text-[#a1a1aa]">Try adjusting your search criteria or selecting another gear category.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {filtered.map(review => (
            <ReviewCard key={review.id} review={review} />
          ))}
        </div>
      )}
    </section>
  );
};
