import React, { useState, useEffect } from 'react';
import { Gamepad2, ChevronUp, Sparkles, Zap, Power, ShieldCheck, ArrowDown } from 'lucide-react';
import { BouncyText } from './BouncyText';

/**
 * Console3DIntroOverlay - Full-screen cinematic 3D next-gen gaming console intro.
 * When opening the site, a large 3D cyber gaming console spans the screen.
 * Swiping up, scrolling down, or clicking "Enter" scales and transforms it 
 * smoothly into the platform interface!
 */
export const Console3DIntroOverlay = () => {
  const [scrollY, setScrollY] = useState(0);
  const [isDismissed, setIsDismissed] = useState(false);
  const [isPoweredOn, setIsPoweredOn] = useState(true);

  useEffect(() => {
    const handleScroll = () => {
      const y = window.scrollY;
      setScrollY(y);
      if (y > 350) {
        setIsDismissed(true);
      }
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  const handleEnterSite = () => {
    setIsDismissed(true);
    window.scrollTo({ top: 400, behavior: 'smooth' });
  };

  // If user has scrolled past 400px, overlay is completely docked
  if (isDismissed && scrollY > 400) return null;

  // Calculate dynamic scale, opacity and transform based on scroll
  const progress = Math.min(scrollY / 350, 1);
  const scale = 1 - progress * 0.45; // Scales down from 1 to 0.55
  const opacity = 1 - progress * 1.1; // Fades out
  const translateY = progress * -180; // Moves upward

  if (opacity <= 0.02) return null;

  return (
    <div 
      className="fixed inset-0 z-40 flex flex-col items-center justify-center pointer-events-none transition-all duration-300 overflow-hidden"
      style={{
        opacity: Math.max(0, opacity),
        background: `radial-gradient(circle at 50% 45%, rgba(6, 78, 59, ${0.92 - progress * 0.9}) 0%, rgba(2, 44, 34, ${0.98 - progress * 0.9}) 70%, rgba(1, 23, 18, ${0.99 - progress * 0.9}) 100%)`
      }}
    >
      {/* Background Animated Laser Grid */}
      <div className="absolute inset-0 bg-[radial-gradient(#10B981_1px,transparent_1px)] [background-size:24px_24px] opacity-20 pointer-events-none"></div>

      {/* Main 3D Interactive Console Container */}
      <div 
        className="pointer-events-auto relative flex flex-col items-center justify-center p-4 transition-transform duration-100 ease-out"
        style={{
          transform: `scale(${scale}) translateY(${translateY}px) perspective(1200px) rotateX(${progress * 15}deg)`,
        }}
      >
        {/* Top Floating Cyber HUD Header */}
        <div className="mb-4 text-center space-y-2">
          <div className="inline-flex items-center gap-2 bg-emerald-500/20 border border-emerald-400/50 text-emerald-300 px-4 py-1.5 rounded-full text-xs font-extrabold uppercase tracking-widest badge-glow">
            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            <Sparkles className="w-3.5 h-3.5 text-emerald-400" />
            <BouncyText text="NEXT-GEN GAMING CONSOLE // INITIALIZING" />
          </div>

          <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white tracking-tight drop-shadow-2xl">
            <BouncyText text="RUN ON CONSOLE" />
          </h1>
        </div>

        {/* --- 3D CYBER HANDHELD / BATTLESTATION CONSOLE BODY --- */}
        <div className="relative w-[340px] sm:w-[580px] md:w-[720px] h-[220px] sm:h-[340px] md:h-[400px] bg-gradient-to-b from-slate-900 via-slate-950 to-black rounded-[40px] sm:rounded-[60px] border-4 border-emerald-500/40 shadow-[0_0_80px_rgba(16,185,129,0.35)] flex items-center justify-between p-4 sm:p-8 overflow-hidden group">
          
          {/* Outer Console Shell Texture & Grips */}
          <div className="absolute inset-0 bg-gradient-to-r from-emerald-950/30 via-transparent to-emerald-950/30 pointer-events-none"></div>
          <div className="absolute -top-20 -left-20 w-48 h-48 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
          <div className="absolute -bottom-20 -right-20 w-48 h-48 bg-teal-500/20 rounded-full blur-3xl pointer-events-none"></div>

          {/* LEFT CONTROLLER GRIP */}
          <div className="flex flex-col items-center justify-between h-full py-3 sm:py-6 z-10 space-y-4">
            
            {/* Left RGB Analog Thumbstick */}
            <div className="relative w-12 sm:w-18 h-12 sm:h-18 rounded-full bg-gradient-to-b from-slate-800 to-slate-950 border-2 border-emerald-400 p-1.5 shadow-[0_0_20px_rgba(16,185,129,0.5)] flex items-center justify-center animate-pulse">
              <div className="w-8 sm:w-12 h-8 sm:h-12 rounded-full bg-slate-900 border border-slate-700 flex items-center justify-center">
                <div className="w-3 h-3 rounded-full bg-emerald-400/80"></div>
              </div>
            </div>

            {/* D-Pad Buttons */}
            <div className="relative w-12 sm:w-16 h-12 sm:h-16 flex items-center justify-center">
              <div className="absolute w-4 sm:w-5 h-12 sm:h-16 bg-slate-800 rounded-md border border-emerald-500/40"></div>
              <div className="absolute h-4 sm:h-5 w-12 sm:w-16 bg-slate-800 rounded-md border border-emerald-500/40"></div>
              <div className="z-10 w-3 h-3 rounded-full bg-slate-950"></div>
            </div>

            {/* Select Button */}
            <div className="w-4 sm:w-6 h-2 bg-slate-800 rounded-full border border-slate-600"></div>
          </div>

          {/* CENTER SCREEN: LIVE RUN ON CONSOLE DISPLAY HUD */}
          <div className="flex-1 h-[90%] mx-3 sm:mx-6 bg-slate-950 rounded-2xl sm:rounded-3xl border-2 border-emerald-400/60 p-3 sm:p-5 flex flex-col justify-between relative overflow-hidden shadow-inner group">
            
            {/* Screen Bezel Glow & Scanline Reflection */}
            <div className="absolute inset-0 bg-gradient-to-b from-emerald-500/10 via-transparent to-teal-500/10 pointer-events-none"></div>
            <div className="absolute -top-10 left-0 right-0 h-2 bg-emerald-300/40 blur-sm animate-pulse"></div>

            {/* Screen Top Status Bar */}
            <div className="flex items-center justify-between text-[9px] sm:text-xs text-emerald-400 font-mono z-10 border-b border-emerald-900/60 pb-1.5">
              <div className="flex items-center gap-1.5 font-bold">
                <span className="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span>SYSTEM ONLINE // 8000Hz</span>
              </div>
              <div className="flex items-center gap-2">
                <span>0.03ms QD-OLED</span>
                <span>⚡ 100% BATTERY</span>
              </div>
            </div>

            {/* Screen Center Gameplay & Benchmark Graphics */}
            <div className="flex flex-col items-center justify-center text-center my-auto space-y-2 z-10">
              <div className="w-10 sm:w-14 h-10 sm:h-14 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-lg shadow-emerald-500/30">
                <Gamepad2 className="w-6 sm:w-8 h-6 sm:h-8 animate-bounce" />
              </div>
              
              <h2 className="font-display font-extrabold text-sm sm:text-xl text-white">
                <BouncyText text="Honest Reviews & Benchmarks" />
              </h2>

              <p className="text-[10px] sm:text-xs text-emerald-200/90 max-w-xs leading-relaxed hidden sm:block">
                PC • PS5 • Xbox • Handheld Consoles • Esports Gear
              </p>
            </div>

            {/* Screen Bottom CTA Button */}
            <button
              onClick={handleEnterSite}
              className="w-full bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-600 hover:from-emerald-500 hover:to-teal-400 text-white font-display font-extrabold text-xs sm:text-sm py-2 sm:py-3 rounded-xl shadow-lg shadow-emerald-600/40 flex items-center justify-center gap-2 transition-all hover:scale-102 z-10 active:scale-95"
            >
              <Power className="w-4 h-4 text-white animate-pulse" />
              <span>ENTER RUN ON CONSOLE HUB</span>
              <ChevronUp className="w-4 h-4 text-emerald-200 animate-bounce" />
            </button>
          </div>

          {/* RIGHT CONTROLLER GRIP */}
          <div className="flex flex-col items-center justify-between h-full py-3 sm:py-6 z-10 space-y-4">
            
            {/* ABXY Action Diamond Buttons */}
            <div className="grid grid-cols-3 gap-1 w-12 sm:w-16 h-12 sm:h-16 items-center justify-items-center">
              <div></div>
              <div className="w-4 sm:w-6 h-4 sm:h-6 rounded-full bg-slate-800 border border-emerald-400 text-[8px] sm:text-[10px] text-emerald-300 font-bold flex items-center justify-center shadow-xs">Y</div>
              <div></div>
              <div className="w-4 sm:w-6 h-4 sm:h-6 rounded-full bg-slate-800 border border-emerald-400 text-[8px] sm:text-[10px] text-emerald-300 font-bold flex items-center justify-center shadow-xs">X</div>
              <div></div>
              <div className="w-4 sm:w-6 h-4 sm:h-6 rounded-full bg-slate-800 border border-emerald-400 text-[8px] sm:text-[10px] text-emerald-300 font-bold flex items-center justify-center shadow-xs">B</div>
              <div></div>
              <div className="w-4 sm:w-6 h-4 sm:h-6 rounded-full bg-slate-800 border border-emerald-400 text-[8px] sm:text-[10px] text-emerald-300 font-bold flex items-center justify-center shadow-xs">A</div>
              <div></div>
            </div>

            {/* Right RGB Analog Thumbstick */}
            <div className="relative w-12 sm:w-18 h-12 sm:h-18 rounded-full bg-gradient-to-b from-slate-800 to-slate-950 border-2 border-teal-400 p-1.5 shadow-[0_0_20px_rgba(20,184,166,0.5)] flex items-center justify-center animate-pulse">
              <div className="w-8 sm:w-12 h-8 sm:h-12 rounded-full bg-slate-900 border border-slate-700 flex items-center justify-center">
                <div className="w-3 h-3 rounded-full bg-teal-400/80"></div>
              </div>
            </div>

            {/* Start Button */}
            <div className="w-4 sm:w-6 h-2 bg-slate-800 rounded-full border border-slate-600"></div>
          </div>

        </div>

        {/* Bottom Swipe Up / Scroll Cue with Bouncing Indicator */}
        <div 
          onClick={handleEnterSite}
          className="mt-6 flex flex-col items-center gap-1.5 cursor-pointer group"
        >
          <div className="w-9 h-9 rounded-full bg-emerald-600/30 border border-emerald-400 text-emerald-300 flex items-center justify-center shadow-lg group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300 animate-bounce">
            <ChevronUp className="w-5 h-5" />
          </div>
          <span className="text-xs font-display font-extrabold uppercase tracking-widest text-emerald-300 drop-shadow-md">
            SWIPE UP OR SCROLL TO REVEAL PLATFORM
          </span>
        </div>

      </div>
    </div>
  );
};
