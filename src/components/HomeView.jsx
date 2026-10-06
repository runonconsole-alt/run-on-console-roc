import React from 'react';
import { useApp } from '../context/AppContext';
import { HeroSection } from './HeroSection';
import { TrendingSection } from './TrendingSection';
import { LatestReviewsSection } from './LatestReviewsSection';
import { Tilt3DCard } from './Tilt3DCard';
import { ArrowRight, Flame, Gamepad2 } from 'lucide-react';
import { POPULAR_POSTS } from '../data/initialData';
import { BouncyText } from './BouncyText';

export const HomeView = () => {
  const { categories, navigateToCategory, navigateToBlog } = useApp();

  const blogSlugs = [
    'call-of-duty-black-ops-6-everything-we-know-so-far',
    'elden-ring-shadow-of-the-erdtree-full-hardware-boss-guide',
    'spider-man-2-on-ps5-performance-ray-tracing-masterclass',
    'best-gaming-laptops-under-1000-in-2024-buyers-guide'
  ];

  return (
    <div className="space-y-14 animate-page-in">
      
      {/* 1. Hero Banner */}
      <HeroSection />

      {/* 2. Explore By Gaming Platforms */}
      <section className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-emerald-100">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-600/20">
              <Gamepad2 className="w-5 h-5 animate-pulse" />
            </div>
            <div>
              <h2 className="font-display font-extrabold text-lg sm:text-2xl text-slate-900 uppercase tracking-wide">
                <BouncyText text="EXPLORE 15 GAMING PLATFORM HUBS" />
              </h2>
              <p className="text-xs text-emerald-700 font-semibold">
                Browse verified platform hubs: PC Battlestations, Handhelds, Retro Legends, Consoles & VR
              </p>
            </div>
          </div>

          <a 
            href="/categories/"
            onClick={(e) => {
              if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                e.preventDefault();
                navigateToCategory('all');
              }
            }}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-display font-bold px-5 py-2.5 rounded-full flex items-center gap-2 uppercase tracking-wider shadow-sm border border-emerald-400/40 transition-all hover:scale-105 self-start sm:self-auto no-underline"
          >
            <BouncyText text={`ALL 15 PLATFORMS (${categories.length})`} />
            <ArrowRight className="w-4 h-4 text-emerald-200" />
          </a>
        </div>

        {/* 6 Platform Animated 3D Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
          {categories.slice(0, 6).map((cat) => {
            const catUrl = `/categories/${cat.slug}/`;

            return (
              <a
                key={cat.id}
                href={catUrl}
                onClick={(e) => {
                  if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                    e.preventDefault();
                    navigateToCategory(cat.slug);
                  }
                }}
                className="no-underline block"
              >
                <Tilt3DCard className="p-4 bg-slate-50 hover:bg-emerald-50/80 border border-slate-200 hover:border-emerald-400 rounded-2xl text-center group transition-all shadow-xs cursor-pointer h-full">
                  <div className="relative w-14 h-14 mx-auto mb-2.5 overflow-hidden rounded-2xl bg-slate-900 shadow-md">
                    <img 
                      src={cat.image} 
                      alt={cat.title} 
                      width="56"
                      height="56"
                      loading="lazy"
                      decoding="async"
                      className="w-full h-full object-cover group-hover:scale-115 transition-transform duration-500" 
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-emerald-950/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                  </div>

                  <h3 className="font-display font-bold text-xs text-emerald-950 group-hover:text-emerald-700 line-clamp-1 transition-colors">
                    <BouncyText text={cat.title} />
                  </h3>
                  <span className="text-[10px] text-emerald-600 font-bold block mt-0.5">
                    {cat.devices?.length || 8} Models & Specs
                  </span>
                </Tilt3DCard>
              </a>
            );
          })}
        </div>
      </section>

      {/* 3. Trending Articles & Popular Ranked Gaming Intel */}
      <section className="space-y-6">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          <div className="lg:col-span-8">
            <TrendingSection />
          </div>

          <div className="lg:col-span-4">
            <div className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 shadow-sm space-y-4">
              
              <div className="flex items-center justify-between pb-3 border-b border-emerald-100">
                <div className="flex items-center gap-2.5">
                  <div className="w-8 h-8 rounded-xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-xs">
                    <Flame className="w-4 h-4 text-emerald-400 animate-pulse" />
                  </div>
                  <h3 className="font-display font-extrabold text-sm sm:text-base text-emerald-950 uppercase tracking-wider">
                    <BouncyText text="POPULAR ARTICLES" />
                  </h3>
                </div>
                <span className="text-[10px] font-mono font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                  Ranked
                </span>
              </div>

              <div className="space-y-3">
                {POPULAR_POSTS.map((post, index) => {
                  const bSlug = blogSlugs[index % blogSlugs.length];
                  const bUrl = `/blogs/${bSlug}/`;

                  return (
                    <a
                      key={post.id}
                      href={bUrl}
                      onClick={(e) => {
                        if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                          e.preventDefault();
                          navigateToBlog(post.id || `blog-${index + 1}`);
                        }
                      }}
                      className="no-underline block"
                    >
                      <Tilt3DCard className="p-3 bg-slate-50 hover:bg-emerald-50/90 border border-slate-200 hover:border-emerald-400 rounded-2xl cursor-pointer flex items-center gap-3 transition-all group">
                        <div className="w-8 h-8 rounded-xl bg-emerald-700 text-white font-display font-extrabold text-xs flex items-center justify-center shrink-0 shadow-xs">
                          0{index + 1}
                        </div>

                        <div className="min-w-0 flex-1">
                          <h4 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 line-clamp-1 leading-snug">
                            <BouncyText text={post.title} />
                          </h4>
                          <span className="text-[10px] text-slate-500 font-medium block mt-0.5">
                            {post.views} Readers • {post.category}
                          </span>
                        </div>

                        <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                      </Tilt3DCard>
                    </a>
                  );
                })}
              </div>

            </div>
          </div>

        </div>
      </section>

      {/* 4. Latest Tested Hardware & Deals */}
      <LatestReviewsSection />

    </div>
  );
};
