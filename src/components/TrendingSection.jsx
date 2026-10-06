import React from 'react';
import { INITIAL_TRENDING } from '../data/initialData';
import { Flame, Clock } from 'lucide-react';
import { useApp } from '../context/AppContext';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { playClickSound, playHoverSound } from '../utils/audioEffects';

export const TrendingSection = () => {
  const { navigateToBlog } = useApp();

  const blogSlugs = [
    'call-of-duty-black-ops-6-everything-we-know-so-far',
    'elden-ring-shadow-of-the-erdtree-full-hardware-boss-guide',
    'spider-man-2-on-ps5-performance-ray-tracing-masterclass',
    'best-gaming-laptops-under-1000-in-2024-buyers-guide'
  ];

  return (
    <section id="trending-section" className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
      
      {/* Sleek Green Section Header */}
      <div className="flex items-center justify-between pb-4 border-b border-emerald-100">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md">
            <Flame className="w-5 h-5 text-emerald-400 animate-pulse" />
          </div>
          <div>
            <h2 className="font-display font-extrabold text-lg sm:text-2xl text-emerald-950 uppercase tracking-wide">
              <BouncyText text="TRENDING ARTICLES & RELEASES" />
            </h2>
            <p className="text-xs text-emerald-700 font-semibold">
              Deep dives, benchmark breakdowns & early access guides
            </p>
          </div>
        </div>
      </div>

      {/* 4 Cards Grid with Crawlable Anchors */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {INITIAL_TRENDING.map((item, idx) => {
          const blogSlug = blogSlugs[idx % blogSlugs.length];
          const blogUrl = `/blogs/${blogSlug}/`;

          return (
            <a
              key={item.id}
              href={blogUrl}
              onClick={(e) => {
                if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                  e.preventDefault();
                  playClickSound();
                  navigateToBlog(item.id || `blog-${idx + 1}`);
                }
              }}
              onMouseEnter={playHoverSound}
              className="no-underline block h-full"
            >
              <Tilt3DCard className="game-card group cursor-pointer flex flex-col justify-between h-full">
                {/* Image Box */}
                <div className="relative h-44 w-full overflow-hidden bg-slate-900">
                  <img 
                    src={item.image} 
                    alt={item.title} 
                    width="400"
                    height="250"
                    loading="lazy"
                    decoding="async"
                    className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                  />
                  <span className="absolute bottom-2.5 left-2.5 bg-emerald-600 text-white text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-lg tracking-wider shadow-sm badge-glow">
                    {item.category}
                  </span>
                </div>

                {/* Content Body */}
                <div className="p-4 flex-1 flex flex-col justify-between space-y-3">
                  <h3 className="font-display font-bold text-sm text-slate-900 group-hover:text-emerald-700 transition-colors leading-snug line-clamp-2">
                    <BouncyText text={item.title} />
                  </h3>

                  <div className="flex items-center justify-between text-xs text-slate-500 font-medium pt-2 border-t border-slate-100">
                    <span className="flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5 text-emerald-600" />
                      {item.date}
                    </span>
                    <span className="text-emerald-700 font-bold">Read →</span>
                  </div>
                </div>
              </Tilt3DCard>
            </a>
          );
        })}
      </div>

    </section>
  );
};
