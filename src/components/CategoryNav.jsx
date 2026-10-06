import React from 'react';
import { useApp } from '../context/AppContext';
import { CATEGORIES } from '../data/initialData';
import { Mouse, Keyboard, Headphones, Monitor, Sliders, Cpu } from 'lucide-react';

const categoryIcons = {
  "All": Cpu,
  "Mice": Mouse,
  "Keyboards": Keyboard,
  "Audio": Headphones,
  "Rig Builds": Monitor
};

export const CategoryNav = () => {
  const { activeCategory, setActiveCategory, reviews } = useApp();

  return (
    <div className="w-full my-4 border-b border-[#27272a] pb-3 flex items-center justify-between gap-4 overflow-x-auto no-scrollbar">
      
      {/* Category Pills */}
      <div className="flex items-center gap-2">
        {CATEGORIES.map(cat => {
          const Icon = categoryIcons[cat] || Sliders;
          const count = cat === "All" ? reviews.length : reviews.filter(r => r.category === cat).length;
          const isActive = activeCategory === cat;

          return (
            <button
              key={cat}
              onClick={() => setActiveCategory(cat)}
              className={`flex items-center gap-2 py-2 px-4 font-mono text-xs font-semibold tracking-wider transition-all border ${
                isActive 
                  ? 'bg-[#00f0ff]/10 text-[#00f0ff] border-[#00f0ff] shadow-[0_0_15px_rgba(0,240,255,0.3)]' 
                  : 'bg-[#141414] text-[#a1a1aa] border-[#27272a] hover:border-[#a1a1aa] hover:text-white'
              }`}
            >
              <Icon className={`w-3.5 h-3.5 ${isActive ? 'text-[#00f0ff]' : 'text-[#a1a1aa]'}`} />
              <span>{cat.toUpperCase()}</span>
              <span className={`text-[10px] px-1.5 py-0.5 rounded-none font-bold ${
                isActive ? 'bg-[#00f0ff] text-black' : 'bg-[#27272a] text-[#a1a1aa]'
              }`}>
                {count}
              </span>
            </button>
          );
        })}
      </div>

    </div>
  );
};
