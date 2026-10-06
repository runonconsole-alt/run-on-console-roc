import React from 'react';

export const BrandLogo = ({ 
  size = 'normal', 
  theme = 'light', // 'light' (for white header) | 'dark' (for dark footer/hero)
  showSubtitle = true, 
  onClick 
}) => {
  const isLarge = size === 'large';
  const isSmall = size === 'small';
  const isDark = theme === 'dark';

  return (
    <div 
      onClick={onClick}
      className="flex items-center gap-2.5 sm:gap-3 cursor-pointer group shrink-0 select-none transition-transform duration-200 hover:scale-102"
    >
      {/* Official Emerald Squircle Game Remote Icon (Matching User Brand Spec) */}
      <div className={`relative shrink-0 flex items-center justify-center rounded-2xl bg-gradient-to-tr from-emerald-600 via-teal-500 to-emerald-400 shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-all duration-300 ${
        isLarge ? 'w-12 h-12 sm:w-14 sm:h-14 rounded-3xl' : isSmall ? 'w-8 h-8 rounded-xl' : 'w-9 h-9 sm:w-10 sm:h-10 rounded-2xl'
      }`}>
        <svg 
          viewBox="0 0 100 100" 
          className="w-3/5 h-3/5 drop-shadow-sm text-white"
          fill="none" 
          xmlns="http://www.w3.org/2000/svg"
        >
          {/* Game Controller Outline */}
          <path 
            d="M12 28 C8 28 4 36 2 54 C0 72 8 88 22 88 C30 88 36 78 44 68 L56 68 C64 78 70 88 78 88 C92 88 100 72 98 54 C96 36 92 28 88 28 C76 28 68 36 50 36 C32 36 24 28 12 28 Z" 
            fill="none" 
            stroke="#FFFFFF" 
            strokeWidth="7" 
            strokeLinecap="round" 
            strokeLinejoin="round" 
          />
          {/* D-Pad */}
          <path d="M26 44 V64 M16 54 H36" stroke="#FFFFFF" strokeWidth="7" strokeLinecap="round" />
          {/* ABXY Action Dots */}
          <circle cx="74" cy="48" r="4.5" fill="#FFFFFF" />
          <circle cx="84" cy="58" r="4.5" fill="#FFFFFF" />
          <circle cx="64" cy="58" r="4.5" fill="#FFFFFF" />
          <circle cx="74" cy="68" r="4.5" fill="#FFFFFF" />
        </svg>
      </div>

      {/* Brand Text: RUN ON CONSOLE */}
      <div className="flex flex-col">
        <div className="flex items-center gap-1.5 leading-none">
          <span className={`font-display font-black tracking-tight ${
            isLarge ? 'text-2xl sm:text-3xl' : isSmall ? 'text-base' : 'text-lg sm:text-xl'
          } ${isDark ? 'text-white' : 'text-slate-900'} uppercase`}>
            RUN ON
          </span>
          <span className={`font-display font-black tracking-tight ${
            isLarge ? 'text-2xl sm:text-3xl' : isSmall ? 'text-base' : 'text-lg sm:text-xl'
          } text-emerald-600 uppercase group-hover:text-emerald-500 transition-colors drop-shadow-xs`}>
            CONSOLE
          </span>
        </div>

        {showSubtitle && (
          <span className={`text-[8px] sm:text-[9px] font-mono font-bold tracking-widest uppercase whitespace-nowrap mt-0.5 ${
            isDark ? 'text-emerald-300/90' : 'text-emerald-700'
          }`}>
            THE GAMING & HARDWARE HUB
          </span>
        )}
      </div>
    </div>
  );
};
