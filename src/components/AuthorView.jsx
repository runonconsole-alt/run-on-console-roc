import React from 'react';
import { useApp } from '../context/AppContext';
import { User, ShieldCheck, Award, Clock, ArrowRight, BookOpen, Cpu, CheckCircle2 } from 'lucide-react';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { ENHANCED_BLOGS } from '../seo/routeRegistry';

export const AuthorView = () => {
  const { navigateToBlog } = useApp();

  // Filter articles authored by Omar Abobakar
  const omarArticles = ENHANCED_BLOGS.filter(b => 
    (b.author || '').toLowerCase().includes('omar')
  );

  return (
    <div className="max-w-4xl mx-auto py-8 space-y-10 animate-fade-in">
      
      {/* Breadcrumbs */}
      <nav aria-label="Breadcrumb" className="text-xs text-slate-500 font-medium">
        <ol className="flex items-center gap-2 list-none p-0 m-0">
          <li>
            <a href="/" className="text-emerald-600 hover:underline">Home</a>
            <span className="ml-2 text-slate-300">/</span>
          </li>
          <li>
            <a href="/about/" className="text-emerald-600 hover:underline">Editorial Team</a>
            <span className="ml-2 text-slate-300">/</span>
          </li>
          <li className="text-slate-900 font-bold">Omar Abobakar</li>
        </ol>
      </nav>

      {/* Author Bio Header Card */}
      <div className="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 text-white rounded-3xl p-6 sm:p-10 shadow-2xl border border-emerald-500/30 flex flex-col md:flex-row items-center gap-8">
        
        {/* Avatar */}
        <div className="w-28 h-28 sm:w-36 sm:h-36 rounded-full bg-gradient-to-br from-emerald-500 to-teal-600 p-1 shrink-0 shadow-xl">
          <div className="w-full h-full rounded-full bg-slate-900 flex items-center justify-center font-display font-extrabold text-3xl sm:text-4xl text-white">
            OA
          </div>
        </div>

        {/* Bio Text */}
        <div className="space-y-3 text-center md:text-left flex-1">
          <div className="flex flex-wrap items-center justify-center md:justify-start gap-2">
            <span className="bg-emerald-500 text-slate-950 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">
              VERIFIED SENIOR AUTHOR
            </span>
            <span className="bg-slate-800 border border-emerald-400/40 text-emerald-300 text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1">
              <ShieldCheck className="w-3.5 h-3.5 text-emerald-400" /> Latency & Hardware Testing
            </span>
          </div>

          <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-white">
            Omar Abobakar
          </h1>
          
          <p className="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl">
            Senior Hardware Columnist and Latency Benchmarker at Run On Console Testing Lab. Specializes in input delay analysis, 8000Hz polling rate measurements, custom PC spec optimization, and esports peripheral testing.
          </p>

          <div className="flex flex-wrap items-center justify-center md:justify-start gap-4 pt-2 text-xs text-slate-400 font-medium">
            <span className="flex items-center gap-1.5 text-emerald-300 font-bold">
              <Award className="w-4 h-4 text-emerald-400" /> 4+ Years Hardware Testing
            </span>
            <span>•</span>
            <span className="flex items-center gap-1.5 text-teal-300 font-bold">
              <BookOpen className="w-4 h-4 text-teal-400" /> {omarArticles.length} Published Reviews
            </span>
          </div>
        </div>
      </div>

      {/* Expertise Topics */}
      <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
          <Cpu className="w-5 h-5 text-emerald-600" />
          Areas of Technical Expertise & Testing Focus
        </h2>
        
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
          {[
            "Input Latency & Oscilloscope Testing",
            "8000Hz Polling Rate Mice",
            "Hall-Effect Magnetic Switches",
            "360Hz QD-OLED Motion Clarity",
            "x86 Handheld Gaming Benchmarks",
            "Custom PC Frame Pacing Analysis"
          ].map((topic, i) => (
            <div key={i} className="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-2 text-xs font-bold text-slate-800">
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>{topic}</span>
            </div>
          ))}
        </div>
      </section>

      {/* Articles Published by Omar Abobakar */}
      <section className="space-y-6">
        <div className="flex items-center justify-between border-b border-slate-200 pb-3">
          <h2 className="font-display font-extrabold text-2xl text-slate-900">
            Published Articles & Reviews ({omarArticles.length})
          </h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {omarArticles.map(blog => (
            <a
              key={blog.id}
              href={blog.url}
              onClick={(e) => {
                if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                  e.preventDefault();
                  navigateToBlog(blog.id);
                }
              }}
              className="block no-underline"
            >
              <Tilt3DCard className="game-card flex flex-col justify-between group cursor-pointer h-full">
                <div className="relative h-48 bg-slate-900 overflow-hidden rounded-t-2xl">
                  <img 
                    src={blog.image} 
                    alt={blog.title} 
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                  />
                  <span className="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded shadow-sm uppercase">
                    {blog.category}
                  </span>
                </div>

                <div className="p-5 flex-1 flex flex-col justify-between space-y-3">
                  <div>
                    <h3 className="font-display font-extrabold text-base text-slate-900 group-hover:text-emerald-600 transition-colors line-clamp-2 mb-1.5">
                      {blog.title}
                    </h3>
                    <p className="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                      {blog.summary}
                    </p>
                  </div>

                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span className="flex items-center gap-1">
                      <Clock className="w-3.5 h-3.5" />
                      <time datetime="2024-05-20">{blog.date}</time>
                    </span>
                    <span className="text-emerald-600 font-bold flex items-center gap-1">
                      Read Article <ArrowRight className="w-3.5 h-3.5" />
                    </span>
                  </div>
                </div>
              </Tilt3DCard>
            </a>
          ))}
        </div>
      </section>

    </div>
  );
};
