import React, { useState } from 'react';
import { POPULAR_POSTS } from '../data/initialData';
import { useApp } from '../context/AppContext';
import { Search, Flame, Mail, Clock, Check, Sparkles } from 'lucide-react';
import confetti from 'canvas-confetti';

export const SidebarWidgets = () => {
  const { searchQuery, setSearchQuery, navigateToBlog } = useApp();
  const [email, setEmail] = useState("");
  const [subscribed, setSubscribed] = useState(false);

  const handleSubscribe = (e) => {
    e.preventDefault();
    if (!email.trim()) return;
    setSubscribed(true);
    try {
      confetti({ particleCount: 50, spread: 60, origin: { y: 0.7 } });
    } catch (err) {}
  };

  return (
    <aside className="space-y-6">
      
      {/* 1. SEARCH WIDGET */}
      <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
        <h3 className="font-display font-extrabold text-xs text-slate-900 uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <Search className="w-4 h-4 text-emerald-600" />
          <span>SEARCH THE DATABASE</span>
        </h3>
        <form onSubmit={(e) => e.preventDefault()} className="flex items-center gap-2">
          <input 
            type="text"
            placeholder="Search gear, reviews, specs..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="flex-1 bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 transition-colors"
          />
          <button 
            type="submit"
            className="bg-emerald-600 hover:bg-emerald-700 text-white p-2.5 rounded-xl transition-colors flex items-center justify-center shrink-0 shadow-md shadow-emerald-600/20"
          >
            <Search className="w-4 h-4" />
          </button>
        </form>
      </div>

      {/* 2. POPULAR POSTS WIDGET */}
      <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm">
        <div className="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
          <Flame className="w-4 h-4 text-emerald-600" />
          <h3 className="font-display font-extrabold text-xs text-slate-900 uppercase tracking-wider">
            POPULAR ARTICLES
          </h3>
        </div>

        <div className="space-y-4">
          {POPULAR_POSTS.map((post) => (
            <div 
              key={post.id}
              onClick={() => navigateToBlog('blog-1')}
              className="flex items-center gap-3 group cursor-pointer"
            >
              {/* Number Rank Badge */}
              <div className="w-6 h-6 rounded-lg bg-emerald-600 text-white font-display font-extrabold text-xs flex items-center justify-center shrink-0 shadow-sm">
                {post.rank}
              </div>

              {/* Thumbnail Image */}
              <div className="w-14 h-12 rounded-xl overflow-hidden bg-slate-800 shrink-0">
                <img 
                  src={post.image} 
                  alt={post.title} 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                />
              </div>

              {/* Title & Date */}
              <div className="flex-1 min-w-0">
                <h4 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-600 transition-colors leading-snug line-clamp-2">
                  {post.title}
                </h4>
                <div className="flex items-center gap-1 text-[11px] text-slate-400 mt-1">
                  <Clock className="w-3 h-3 text-slate-400" />
                  <span>{post.date}</span>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* 3. STAY IN THE GAME NEWSLETTER */}
      <div className="gradient-hero-bg text-white rounded-3xl p-6 shadow-lg space-y-3">
        <div className="flex items-center gap-2">
          <Mail className="w-4 h-4 text-emerald-300" />
          <h3 className="font-display font-extrabold text-xs uppercase tracking-wider text-white">
            STAY IN THE GAME
          </h3>
        </div>

        <p className="text-xs text-emerald-100 leading-relaxed">
          Get weekly hardware benchmark updates, verified discount codes, and new game launch guides.
        </p>

        {subscribed ? (
          <div className="bg-emerald-800/80 border border-emerald-400 text-emerald-100 p-3 rounded-xl text-xs font-semibold flex items-center gap-2">
            <Check className="w-4 h-4 text-emerald-300" />
            <span>You're subscribed to Run On Console!</span>
          </div>
        ) : (
          <form onSubmit={handleSubscribe} className="space-y-2.5 pt-1">
            <input 
              type="email"
              required
              placeholder="Enter your email address"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full bg-white text-slate-900 placeholder:text-slate-400 rounded-xl px-3.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-400"
            />
            <button 
              type="submit"
              className="w-full bg-emerald-400 hover:bg-emerald-300 text-slate-950 font-display font-extrabold text-xs uppercase tracking-wider py-2.5 rounded-xl shadow-md transition-all"
            >
              SUBSCRIBE FOR DEALS
            </button>
          </form>
        )}
      </div>

    </aside>
  );
};
