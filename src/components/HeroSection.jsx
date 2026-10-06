import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { HERO_SLIDES } from '../data/initialData';
import { 
  ArrowRight, ChevronLeft, ChevronRight, Gamepad2, FileText, 
  Users, Clock, User, Sparkles, Activity, ShoppingBag 
} from 'lucide-react';
import { LiveGamingCanvas } from './LiveGamingCanvas';
import { Tilt3DCard } from './Tilt3DCard';

export const HeroSection = () => {
  const { navigateToBlog, navigateTo } = useApp();
  const [currentSlide, setCurrentSlide] = useState(0);

  const slide = HERO_SLIDES[currentSlide];

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev === HERO_SLIDES.length - 1 ? 0 : prev + 1));
    }, 6000);
    return () => clearInterval(timer);
  }, []);

  const handlePrev = () => {
    setCurrentSlide((prev) => (prev === 0 ? HERO_SLIDES.length - 1 : prev - 1));
  };

  const handleNext = () => {
    setCurrentSlide((prev) => (prev === HERO_SLIDES.length - 1 ? 0 : prev + 1));
  };

  return (
    <section className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
      
      {/* Live Warzone Battlefield Combat Canvas */}
      <LiveGamingCanvas />

      {/* Ambient Soft Glow Orbs */}
      <div className="absolute -top-24 -left-24 w-96 h-96 bg-emerald-400/15 rounded-full blur-3xl pointer-events-none motion-reduce:hidden"></div>
      <div className="absolute -bottom-24 -right-24 w-96 h-96 bg-teal-400/15 rounded-full blur-3xl pointer-events-none motion-reduce:hidden"></div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center relative z-10">
        
        {/* Left Column */}
        <div className="lg:col-span-6 space-y-6">
          
          <div className="flex flex-wrap items-center gap-3">
            <div className="inline-flex items-center gap-2 bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-4 py-1.5 rounded-full tracking-wider shadow-lg">
              <Sparkles className="w-3.5 h-3.5" />
              <span>LIVE BENCHMARK INTEL & REVIEWS</span>
            </div>

            <div className="flex items-center gap-1.5 bg-slate-900/80 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-emerald-400/40 text-[11px] text-emerald-300 font-mono shadow-sm">
              <Activity className="w-3.5 h-3.5 text-emerald-400" />
              <span className="font-bold">0.95ms LATENCY</span>
            </div>
          </div>

          {/* Main Headline (Single H1 on Page) */}
          <h1 className="font-display font-extrabold text-3xl sm:text-5xl lg:text-5xl tracking-tight text-white leading-tight">
            Discover the Best <br />
            <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
              Games, Hardware
            </span> <br />
            and Accessories
          </h1>

          {/* Subtitle */}
          <p className="text-emerald-100 text-xs sm:text-sm sm:text-base leading-relaxed max-w-lg">
            Independent laboratory testing, oscilloscope latency benchmarks, verified affiliate deals, and game compatibility matrices.
          </p>

          {/* Floating Hardware Badges */}
          <div className="flex flex-wrap gap-2.5 pt-1">
            <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
              ⚡ 8000Hz Polling
            </span>
            <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
              🔥 0.03ms QD-OLED
            </span>
            <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
              🛡️ Zero Stick Drift
            </span>
          </div>

          {/* DUAL RESPONSIVE CRAWLABLE BUTTONS */}
          <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4 pt-2 w-full">
            
            <a 
              href="/products/"
              onClick={(e) => {
                if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                  e.preventDefault();
                  navigateTo('products');
                }
              }}
              className="group bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-display font-extrabold text-xs sm:text-sm px-6 py-3.5 rounded-2xl flex items-center justify-center gap-2 shadow-xl w-full sm:w-auto no-underline transition-[transform,shadow] duration-200 hover:-translate-y-0.5"
            >
              <ShoppingBag className="w-4 h-4 text-emerald-200 shrink-0" />
              <span>EXPLORE ALL GEAR</span>
              <ArrowRight className="w-4 h-4 text-emerald-200 shrink-0 transition-transform duration-200 group-hover:translate-x-1" />
            </a>

            <a 
              href="/compatibility/"
              onClick={(e) => {
                if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                  e.preventDefault();
                  navigateTo('compatibility');
                }
              }}
              className="bg-slate-900/90 hover:bg-slate-800 text-white font-display font-extrabold text-xs sm:text-sm px-6 py-3.5 rounded-2xl flex items-center justify-center gap-2 shadow-xl w-full sm:w-auto no-underline border border-emerald-500/30 transition-[transform,border-color] duration-200 hover:-translate-y-0.5 hover:border-emerald-400"
            >
              <Gamepad2 className="w-4 h-4 text-emerald-300 shrink-0" />
              <span>GAME COMPATIBILITY MATRIX</span>
            </a>

          </div>

          {/* Quick Metrics Bar */}
          <div className="grid grid-cols-3 gap-4 pt-6 border-t border-emerald-700/60">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-xl bg-emerald-500/20 text-emerald-300">
                <Gamepad2 className="w-5 h-5" />
              </div>
              <div>
                <div className="font-display font-extrabold text-lg sm:text-xl text-white">50+</div>
                <div className="text-[11px] text-emerald-200 font-medium">Tested Gear</div>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <div className="p-2 rounded-xl bg-emerald-500/20 text-emerald-300">
                <FileText className="w-5 h-5" />
              </div>
              <div>
                <div className="font-display font-extrabold text-lg sm:text-xl text-white">100+</div>
                <div className="text-[11px] text-emerald-200 font-medium">Guides</div>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <div className="p-2 rounded-xl bg-emerald-500/20 text-emerald-300">
                <Users className="w-5 h-5" />
              </div>
              <div>
                <div className="font-display font-extrabold text-lg sm:text-xl text-white">5K+</div>
                <div className="text-[11px] text-emerald-200 font-medium">Gamers</div>
              </div>
            </div>
          </div>

        </div>

        {/* Right Column: Hero Card with LCP Image */}
        <div className="lg:col-span-6">
          <Tilt3DCard 
            className="rounded-3xl shadow-2xl border-2 border-emerald-400/40 bg-slate-950 group"
            onClick={() => navigateToBlog(slide.id)}
            enableBurst={true}
          >
            <div className="relative h-72 sm:h-96 w-full overflow-hidden cursor-pointer">
              <img 
                src={slide.image} 
                alt={slide.title}
                width="800"
                height="500"
                loading="eager"
                fetchpriority="high"
                className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
              />
              
              <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>

              <div className="absolute top-4 left-4 flex items-center gap-2">
                <span className="bg-emerald-600 text-white text-[11px] font-extrabold uppercase px-3 py-1 rounded-full tracking-wider shadow-md">
                  {slide.badge}
                </span>
                <span className="bg-slate-900/80 backdrop-blur-sm text-emerald-300 text-[10px] font-bold px-2.5 py-1 rounded-full border border-emerald-500/30">
                  {slide.category}
                </span>
              </div>

              <div className="absolute bottom-0 left-0 right-0 p-6 sm:p-8 space-y-3">
                <h3 className="font-display font-bold text-xl sm:text-2xl text-white leading-snug group-hover:text-emerald-300 transition-colors duration-200">
                  {slide.title}
                </h3>

                <div className="flex items-center gap-4 text-xs text-slate-300 font-medium pt-1">
                  <span className="flex items-center gap-1.5">
                    <Clock className="w-3.5 h-3.5 text-emerald-400" />
                    {slide.date}
                  </span>
                  <span>•</span>
                  <span className="flex items-center gap-1.5">
                    <User className="w-3.5 h-3.5 text-emerald-400" />
                    By {slide.author}
                  </span>
                </div>
              </div>
            </div>

            <div 
              className="absolute bottom-4 right-4 flex items-center gap-2 z-20"
              onClick={(e) => e.stopPropagation()}
            >
              <button 
                type="button"
                aria-label="Previous hero slide"
                onClick={handlePrev}
                className="w-8 h-8 rounded-full bg-slate-900/90 hover:bg-emerald-600 text-white flex items-center justify-center transition-colors border border-slate-700 shadow-md"
              >
                <ChevronLeft className="w-4 h-4" />
              </button>

              <div className="flex items-center gap-1.5 px-2">
                {HERO_SLIDES.map((_, idx) => (
                  <button
                    key={idx}
                    type="button"
                    aria-label={`Go to slide ${idx + 1}`}
                    onClick={() => setCurrentSlide(idx)}
                    className={`h-2 rounded-full transition-all ${
                      currentSlide === idx ? 'w-4 bg-emerald-400' : 'w-2 bg-slate-600'
                    }`}
                  />
                ))}
              </div>

              <button 
                type="button"
                aria-label="Next hero slide"
                onClick={handleNext}
                className="w-8 h-8 rounded-full bg-slate-900/90 hover:bg-emerald-600 text-white flex items-center justify-center transition-colors border border-slate-700 shadow-md"
              >
                <ChevronRight className="w-4 h-4" />
              </button>
            </div>
          </Tilt3DCard>
        </div>

      </div>
    </section>
  );
};
