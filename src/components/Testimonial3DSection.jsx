import React from 'react';
import { useApp } from '../context/AppContext';
import { Star, MessageSquare, Award, Sparkles, CheckCircle, ChevronLeft, ChevronRight, Quote } from 'lucide-react';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';

export const Testimonial3DSection = () => {
  const { testimonials = [] } = useApp();

  const allTestimonials = [
    ...testimonials,
    {
      id: "t-4",
      name: "Marcus 'Vortex' Vance",
      role: "Sim Racing Driver & Modder",
      avatar: "MV",
      rating: 5,
      comment: "The latency test on 8000Hz polling rate completely transformed my micro-aim consistency. Best independent hardware lab on the internet!",
      tag: "Verified Hardware Modder",
      gearMentioned: "Direct Drive & Hall-Effect Gear"
    }
  ];

  return (
    <section className="my-14 py-8">
      
      {/* Sleek Green Section Header - EXACT SAME DEEP EMERALD & BRIGHT WHITE TEXT AS VIEW ALL PRODUCTS */}
      <div className="text-center max-w-2xl mx-auto mb-10 space-y-3">
        <div className="inline-flex items-center gap-2 bg-emerald-800 hover:bg-emerald-900 text-white text-xs font-display font-extrabold uppercase px-6 py-2.5 rounded-full border border-emerald-500/40 shadow-md transition-all hover:scale-105">
          <Sparkles className="w-4 h-4 text-emerald-300 animate-spin" />
          <BouncyText text="COMMUNITY & ESPORTS VERDICTS" />
        </div>
        <h2 className="font-display font-extrabold text-2xl sm:text-4xl text-emerald-950">
          <BouncyText text="Trusted by Pro Players & Enthusiasts" />
        </h2>
        <p className="text-xs sm:text-sm text-emerald-800 font-semibold">
          Independent feedback from verified competitive tournament players, Twitch streamers, and custom PC builders.
        </p>
      </div>

      {/* 4-Card 3D Interactive Grid with Circular Avatars */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        {allTestimonials.slice(0, 4).map((item, idx) => {
          return (
            <Tilt3DCard 
              key={item.id || idx}
              className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 flex flex-col justify-between shadow-xl relative group transition-all"
            >
              {/* Top Neon Gradient Bar */}
              <div className="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-emerald-500 via-teal-400 to-cyan-400"></div>

              <div>
                {/* Tag & Rating */}
                <div className="flex items-center justify-between mb-4">
                  <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                    {item.tag}
                  </span>
                  <div className="flex text-amber-400">
                    {[...Array(item.rating || 5)].map((_, i) => (
                      <Star key={i} className="w-3.5 h-3.5 fill-amber-400" />
                    ))}
                  </div>
                </div>

                {/* Quote Icon */}
                <Quote className="w-6 h-6 text-emerald-300 mb-2" />

                {/* Comment Text */}
                <p className="text-xs text-slate-700 leading-relaxed italic mb-6">
                  "{item.comment}"
                </p>
              </div>

              {/* Author Info with Circular Avatar */}
              <div className="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                <div className="flex items-center gap-3">
                  {/* Round Avatar with 3D Pop */}
                  <div className="w-11 h-11 rounded-full bg-gradient-to-tr from-emerald-600 via-teal-500 to-emerald-400 text-white font-display font-extrabold text-xs flex items-center justify-center shadow-md shadow-emerald-600/30 group-hover:scale-110 group-hover:rotate-6 transition-all duration-300 border-2 border-white">
                    {item.avatar || 'FR'}
                  </div>
                  <div>
                    <h4 className="font-display font-bold text-xs text-slate-900 leading-tight">
                      <BouncyText text={item.name} />
                    </h4>
                    <span className="text-[10px] text-slate-500 font-medium block">{item.role}</span>
                  </div>
                </div>

                {item.gearMentioned && (
                  <span className="text-[9px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-md shrink-0">
                    ✓ Verified
                  </span>
                )}
              </div>

            </Tilt3DCard>
          );
        })}
      </div>

    </section>
  );
};
