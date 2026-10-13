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
      title: "Honest Gaming Gear Picks",
      desc: "We compare keyboards, mice, headsets, speakers, monitors and graphics cards on their specs, official data and published reviews, and say who each one is for. Brands cannot buy a place on our lists."
    },
    {
      icon: ShoppingCart,
      title: "Straight Links to Amazon",
      desc: "Every product links to Amazon, where you see the current price, stock and seller. We are an Amazon Associate and may earn a small commission, at no extra cost to you."
    },
    {
      icon: Gamepad2,
      title: "Game & Platform Compatibility Engine",
      desc: "Confused about whether GTA 6, Black Ops 6, or Elden Ring will run smoothly on your PC, PS5, Xbox, or Handheld? Our free checker compares your hardware with the official minimum and recommended requirements of 500+ games."
    }
  ];

  const aboutFaqs = [
    {
      q: "What makes Run On Console different from other gaming review websites?",
      a: "We keep things simple and honest: clear picks for each type of gear, the specs that matter, who each product is for, and a free checker built on official game requirements. We do not invent test results or prices."
    },
    {
      q: "Can I purchase gaming accessories directly through Run On Console?",
      a: "We do not sell anything ourselves. Every product has a link to Amazon, where you can see the current price and buy it. As an Amazon Associate we may earn a commission on qualifying purchases."
    },
    {
      q: "How does the Game Compatibility Engine work?",
      a: "Pick a game, then choose your graphics card, processor and memory. The checker compares them with the publisher's official minimum and recommended requirements (from Steam for PC games) and shows where your PC stands."
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
                <BouncyText text="Clear Picks. Real Specs." enableAudio={true} />
              </span>
            </h1>
            <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">
              Run On Console is an independent gaming site built by gamers, for gamers. We help you choose the right console and PC gear, check whether your PC can run a game, and find your way around every gaming platform.
            </p>

            <div className="flex flex-wrap gap-2.5 pt-2">
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <Microscope className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
                <BouncyText text="Independent Picks" />
              </span>

              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5" style={{ animationDelay: '0.6s' }}>
                <Cpu className="w-3.5 h-3.5 text-teal-400 animate-pulse" />
                <BouncyText text="Sponsored Posts Labelled" />
              </span>
            </div>
          </div>

          <div className="lg:col-span-5 grid grid-cols-2 gap-3">
            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/battlestation_pc.jpg"
                width="400"
                height="224"
                loading="lazy" 
                alt="Gaming PC setup" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">PC & Console Gear</div>
              <div className="text-[10px] text-emerald-300 font-medium">Keyboards, mice, monitors, GPUs</div>
            </Tilt3DCard>

            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/tactical_headset.jpg"
                width="400"
                height="224"
                loading="lazy" 
                alt="Gaming headset" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Gaming Audio</div>
              <div className="text-[10px] text-emerald-300 font-medium">Headsets and speakers</div>
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
            Three things we do, and how we keep them honest.
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

      {/* How we choose products */}
      <section className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-6">
        <h2 className="font-display font-extrabold text-2xl text-slate-900 border-b border-slate-100 pb-3">
          <BouncyText text="How We Choose Products" />
        </h2>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs sm:text-sm text-slate-700 leading-relaxed">
          <div className="space-y-3">
            <h3 className="font-display font-bold text-base text-slate-900 flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-emerald-600" /> Specs and published data
            </h3>
            <p>
              We compare the specifications that matter for each type of gear (sensor and weight for mice, panel and refresh rate for monitors, VRAM and power for graphics cards) together with the maker&apos;s own data and well-known published reviews.
            </p>
          </div>

          <div className="space-y-3">
            <h3 className="font-display font-bold text-base text-slate-900 flex items-center gap-2">
              <CheckCircle className="w-4 h-4 text-emerald-600" /> Who it is for
            </h3>
            <p>
              Every pick says what it is best for, so you can find the right product for your games and budget. When we test a product ourselves, the article says so. Brands cannot pay for a place on our lists.
            </p>
          </div>
        </div>

        <div className="rounded-2xl bg-slate-50 border border-slate-200 p-5 text-xs sm:text-sm text-slate-700 leading-relaxed">
          <strong className="text-slate-900">Who runs Run On Console?</strong>{' '}
          The site is run by <a href="/author/omar-abobakar/" rel="author" className="font-bold text-emerald-700 hover:text-emerald-900">Omar Abobakar</a>, its founder and editor.
          Questions or corrections are welcome on our <a href="/contact/" className="font-bold text-emerald-700 hover:text-emerald-900">contact page</a>.
        </div>

        <div className="pt-4 border-t border-slate-100 flex flex-wrap gap-4 items-center justify-between">
          <div className="text-xs text-slate-500 font-semibold">
            Ready to find the best gear for your favorite game?
          </div>
          <div className="flex flex-wrap gap-3">
            <a
              href="/products/"
              onClick={() => playClickSound()}
              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-5 rounded-xl flex items-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all hover:scale-105 no-underline"
            >
              Browse All Products <ArrowRight className="w-3.5 h-3.5" />
            </a>
            <a
              href="/compatibility/"
              onClick={() => playClickSound()}
              className="bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold text-xs py-2.5 px-5 rounded-xl transition-colors no-underline"
            >
              Can my PC run it?
            </a>
            <a
              href="/gaming-platforms/"
              onClick={() => playClickSound()}
              className="bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold text-xs py-2.5 px-5 rounded-xl transition-colors no-underline"
            >
              Gaming platforms
            </a>
          </div>
        </div>
      </section>

      {/* About FAQs */}
      <FAQSection 
        faqs={aboutFaqs}
        title="About Run On Console FAQs"
        subtitle="Our mission, how we choose products and how we stay independent."
      />

    </div>
  );
};
