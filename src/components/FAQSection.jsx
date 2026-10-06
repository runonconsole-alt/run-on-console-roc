import React, { useState, useRef } from 'react';
import { HelpCircle, ChevronDown, Sparkles } from 'lucide-react';
import { BouncyText } from './BouncyText';

// Individual Interactive FAQ Item with Real-Time Mouse-Tracking Green Radial Spotlight
const InteractiveFAQItem = ({ faq, idx, isOpen, onToggle }) => {
  const itemRef = useRef(null);
  const [glareStyle, setGlareStyle] = useState({ opacity: 0 });

  const handleMouseMove = (e) => {
    if (!itemRef.current) return;
    const rect = itemRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const glareX = (x / rect.width) * 100;
    const glareY = (y / rect.height) * 100;

    setGlareStyle({
      background: `radial-gradient(380px circle at ${glareX}% ${glareY}%, rgba(52, 211, 153, 0.28) 0%, rgba(16, 185, 129, 0.12) 45%, transparent 80%)`,
      opacity: 1
    });
  };

  const handleMouseLeave = () => {
    setGlareStyle({ opacity: 0 });
  };

  return (
    <div 
      ref={itemRef}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      className={`relative rounded-2xl transition-all duration-300 overflow-hidden border group ${
        isOpen 
          ? 'border-emerald-500 bg-emerald-50/50 shadow-md shadow-emerald-600/10 ring-1 ring-emerald-400/50' 
          : 'border-slate-200 bg-slate-50/80 hover:border-emerald-400 hover:bg-white shadow-xs'
      }`}
    >
      {/* Real-time Dynamic Mouse Follow Green Radial Spotlight Circle */}
      <div
        className="pointer-events-none absolute inset-0 transition-opacity duration-300 rounded-[inherit] z-0"
        style={glareStyle}
      />

      <button
        type="button"
        onClick={onToggle}
        className="relative z-10 w-full text-left p-4 sm:p-5 flex items-center justify-between gap-4 font-display font-bold text-sm sm:text-base text-slate-900 transition-colors"
      >
        <div className="flex items-center gap-3">
          <span className={`w-7 h-7 rounded-xl text-xs font-extrabold flex items-center justify-center shrink-0 transition-all ${
            isOpen 
              ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30 scale-105' 
              : 'bg-emerald-100/80 text-emerald-900 border border-emerald-300/60 group-hover:bg-emerald-600 group-hover:text-white'
          }`}>
            Q{idx + 1}
          </span>
          <span className={`${isOpen ? 'text-emerald-950 font-extrabold' : 'text-slate-800 group-hover:text-emerald-900'}`}>
            <BouncyText text={faq.q} />
          </span>
        </div>

        <span className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 transition-all duration-300 ${
          isOpen 
            ? 'rotate-180 bg-emerald-600 text-white shadow-md' 
            : 'bg-slate-200 text-slate-600 group-hover:bg-emerald-100 group-hover:text-emerald-800'
        }`}>
          <ChevronDown className="w-4 h-4" />
        </span>
      </button>

      {isOpen && (
        <div className="relative z-10 px-5 pb-5 pt-2 text-xs sm:text-sm text-slate-700 leading-relaxed border-t border-emerald-200/60 bg-white/80 backdrop-blur-xs animate-page-in">
          <div className="flex items-start gap-2.5">
            <Sparkles className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5 animate-pulse" />
            <p>{faq.a}</p>
          </div>
        </div>
      )}
    </div>
  );
};

export const FAQSection = ({ 
  faqs = [], 
  title = "Frequently Asked Questions", 
  subtitle = "Got questions? We've got answers from our hardware lab experts." 
}) => {
  const [openIdx, setOpenIdx] = useState(0);
  const containerRef = useRef(null);
  const [containerGlare, setContainerGlare] = useState({ opacity: 0 });

  if (!faqs || faqs.length === 0) return null;

  const handleContainerMouseMove = (e) => {
    if (!containerRef.current) return;
    const rect = containerRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const glareX = (x / rect.width) * 100;
    const glareY = (y / rect.height) * 100;

    setContainerGlare({
      background: `radial-gradient(500px circle at ${glareX}% ${glareY}%, rgba(52, 211, 153, 0.22) 0%, rgba(16, 185, 129, 0.08) 40%, transparent 80%)`,
      opacity: 1
    });
  };

  const handleContainerMouseLeave = () => {
    setContainerGlare({ opacity: 0 });
  };

  return (
    <section 
      ref={containerRef}
      onMouseMove={handleContainerMouseMove}
      onMouseLeave={handleContainerMouseLeave}
      className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-10 my-10 shadow-sm relative overflow-hidden group"
    >
      {/* Real-time Dynamic Mouse Follow Green Radial Spotlight on Container */}
      <div
        className="pointer-events-none absolute inset-0 transition-opacity duration-300 rounded-[inherit] z-0"
        style={containerGlare}
      />
      
      {/* Decorative ambient corner glow */}
      <div className="absolute -top-10 -right-10 w-48 h-48 bg-emerald-100/60 rounded-full blur-2xl pointer-events-none"></div>

      {/* Sleek Green Header with Glowing Emerald Styling */}
      <div className="relative z-10 flex items-center gap-3 mb-2 pb-4 border-b border-emerald-100">
        <div className="w-11 h-11 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md shadow-emerald-500/20 shrink-0">
          <HelpCircle className="w-6 h-6 animate-pulse" />
        </div>
        <div>
          <h3 className="font-display font-extrabold text-xl sm:text-2xl text-emerald-950 leading-tight">
            <BouncyText text={title} />
          </h3>
          <p className="text-xs sm:text-sm text-emerald-700 font-semibold max-w-2xl mt-0.5">
            {subtitle}
          </p>
        </div>
      </div>

      {/* Animated Accordion Items with Real-time Mouse-Following Green Spotlight Glow */}
      <div className="relative z-10 space-y-3.5 mt-6">
        {faqs.map((faq, idx) => (
          <InteractiveFAQItem
            key={idx}
            faq={faq}
            idx={idx}
            isOpen={openIdx === idx}
            onToggle={() => setOpenIdx(openIdx === idx ? null : idx)}
          />
        ))}
      </div>

    </section>
  );
};
