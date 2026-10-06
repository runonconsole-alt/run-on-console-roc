import React from 'react';
import { useApp } from '../context/AppContext';
import { ShieldCheck, ShoppingCart, Gamepad2, Award, Zap, Users, CheckCircle, ArrowRight, Sparkles, Activity, Microscope, Cpu } from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { BouncyText } from './BouncyText';
import { playClickSound, playHoverSound } from '../utils/audioEffects';

export const AboutView = () => {
  const { navigateTo, pageFaqs } = useApp();

  const pillars = [
    {
      icon: ShieldCheck,
      title: "100% Honest Gaming & Hardware Reviews",
      desc: "We test every keyboard, mouse, headset, and monitor in our dedicated hardware lab. Our reviews feature real oscilloscope latency readings, acoustic analysis, and zero sponsored bias."
    },
    {
      icon: ShoppingCart,
      title: "Direct Buying & Verified Affiliate Deals",
      desc: "Discover tested gaming accessories with real-time verified pricing from authorized retailers like Amazon, Best Buy, and official brand stores with manufacturer warranties."
    },
    {
      icon: Gamepad2,
      title: "Game & Platform Compatibility Engine",
      desc: "Confused about whether GTA 6, Black Ops 6, or Elden Ring will run smoothly on your PC, PS5, Xbox, or Handheld? Our compatibility matrix provides exact FPS targets and hardware requirements."
    }
  ];

  const aboutFaqs = [
    {
      q: "What makes Run On Console different from other gaming review websites?",
      a: "Unlike typical review outlets, Run On Console operates an independent testing lab equipped with optical sensor motion analyzers, audio acoustics testing, and click latency oscilloscopes to provide 100% objective, data-backed reviews."
    },
    {
      q: "Can I purchase gaming accessories directly through Run On Console?",
      a: "Yes! Every reviewed product features verified direct purchase links to Amazon and authorized retailers with daily price tracking and verified discount badges."
    },
    {
      q: "How does the Game Compatibility Engine work?",
      a: "Our hardware team benchmarks major gaming releases across various GPU tiers (RTX 4090 down to RTX 4050 and console hardware) so you know exactly what performance to expect before buying."
    }
  ];

  return (
    <div className="space-y-12 py-6 max-w-5xl mx-auto animate-page-in">
      
      {/* Visually Rich About Hero Banner with BouncyText */}
      <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        
        <CyberMatrixHoloBackground />

        <div className="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          
          <div className="lg:col-span-7 space-y-4">
            <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
              <Sparkles className="w-3.5 h-3.5" /> THE RUN ON CONSOLE MISSION
            </span>
            <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
              <BouncyText text="Honest Reviews." enableAudio={true} /> <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
                <BouncyText text="Verified Deals. True FPS." enableAudio={true} />
              </span>
            </h1>
            <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">
              Run On Console is an independent gaming authority and hardware benchmarking lab built by gamers, for gamers. We help you choose the right console and PC gear, buy at the best price, and optimize your setup.
            </p>

            <div className="flex flex-wrap gap-2.5 pt-2">
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <Microscope className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
                <BouncyText text="Independent Hardware Lab" />
              </span>

              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5" style={{ animationDelay: '0.6s' }}>
                <Cpu className="w-3.5 h-3.5 text-teal-400 animate-pulse" />
                <BouncyText text="0% Sponsored Bias" />
              </span>
            </div>
          </div>

          <div className="lg:col-span-5 grid grid-cols-2 gap-3">
            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/battlestation_pc.jpg" 
                alt="Hardware Lab" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Oscilloscope Lab</div>
              <div className="text-[10px] text-emerald-300 font-medium">Sub-1ms Latency Testing</div>
            </Tilt3DCard>

            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/tactical_headset.jpg" 
                alt="Acoustic Testing" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Audio Acoustics</div>
              <div className="text-[10px] text-emerald-300 font-medium">Spatial Audio Benchmarking</div>
            </Tilt3DCard>
          </div>

        </div>
      </div>

      {/* 3 Core Pillars with 3D Physics Cards & BouncyText */}
      <section className="space-y-6">
        <div className="text-center max-w-xl mx-auto">
          <h2 className="font-display font-extrabold text-2xl sm:text-3xl text-slate-900">
            <BouncyText text="What We Do at Run On Console" />
          </h2>
          <p className="text-xs sm:text-sm text-slate-500 mt-1">
            Three pillars that guide our independent editorial and benchmarking standards.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {pillars.map((p, idx) => {
            const Icon = p.icon;
            return (
              <Tilt3DCard 
                key={idx} 
                onMouseEnter={playHoverSound}
                className="bg-white border border-slate-200 rounded-3xl p-7 flex flex-col justify-between shadow-lg"
              >
                <div className="space-y-3">
                  <div className="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-inner">
                    <Icon className="w-6 h-6" />
                  </div>
                  <h3 className="font-display font-extrabold text-lg text-slate-900">
                    <BouncyText text={p.title} />
                  </h3>
                  <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
                    {p.desc}
                  </p>
                </div>
              </Tilt3DCard>
            );
          })}
        </div>
      </section>

      {/* The Testing Methodology */}
      <section className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-6">
        <h2 className="font-display font-extrabold text-2xl text-slate-900 border-b border-slate-100 pb-3">
          <BouncyText text="Our Hardware Testing Standards" />
        </h2>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs sm:text-sm text-slate-700 leading-relaxed">
          <div className="space-y-3">
            <h4 className="font-display font-bold text-base text-slate-900 flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-emerald-600" /> Latency & Sensor Precision
            </h4>
            <p>
              We measure click-to-photon latency, debouncing delay, and optical sensor tracking accuracy up to 8000Hz polling rate to ensure competitive players get genuine esports advantages.
            </p>
          </div>

          <div className="space-y-3">
            <h4 className="font-display font-bold text-base text-slate-900 flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-emerald-600" /> Long-Term Durability
            </h4>
            <p>
              Hardware is tested over hundreds of hours of intense gameplay across FPS, MOBA, Sim Racing, and RPG titles to evaluate switch bounce, thermal throttling, and ergonomic fatigue.
            </p>
          </div>
        </div>

        <div className="pt-4 border-t border-slate-100 flex flex-wrap gap-4 items-center justify-between">
          <div className="text-xs text-slate-500 font-semibold">
            Ready to find the best gear for your favorite game?
          </div>
          <div className="flex gap-3">
            <button 
              onClick={() => {
                playClickSound();
                navigateTo('products');
              }}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-5 rounded-xl flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all hover:scale-105"
            >
              Browse All Products <ArrowRight className="w-3.5 h-3.5" />
            </button>
            <button 
              onClick={() => {
                playClickSound();
                navigateTo('compatibility');
              }}
              className="bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold text-xs py-2.5 px-5 rounded-xl transition-colors"
            >
              View Game Compatibility Matrix
            </button>
          </div>
        </div>
      </section>

      {/* About FAQs */}
      <FAQSection 
        faqs={aboutFaqs}
        title="About Run On Console FAQs"
        subtitle="Learn more about our mission, testing ethics, and editorial independence."
      />

    </div>
  );
};
