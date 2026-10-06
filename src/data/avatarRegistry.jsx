import React from 'react';

/**
 * Run On Console (ROC) - Official 20 Illustrated Esports Gaming Avatar Registry
 * High-performance vector illustrated gaming character portraits & mascot badges.
 */

export const AVATAR_LIST = [
  { id: 'avatar_01', name: 'Cyber Ninja', category: 'Ninja', bg: 'from-emerald-900 to-slate-950', border: 'border-emerald-400', accent: '#10B981' },
  { id: 'avatar_02', name: 'Shadow Samurai', category: 'Samurai', bg: 'from-rose-950 to-slate-950', border: 'border-rose-500', accent: '#F43F5E' },
  { id: 'avatar_03', name: 'Cyber Soldier', category: 'Soldier', bg: 'from-cyan-950 to-slate-950', border: 'border-cyan-400', accent: '#06B6D4' },
  { id: 'avatar_04', name: 'Frost Wizard', category: 'Wizard', bg: 'from-blue-950 to-slate-950', border: 'border-blue-400', accent: '#3B82F6' },
  { id: 'avatar_05', name: 'Ember Dragon', category: 'Dragon', bg: 'from-amber-950 to-slate-950', border: 'border-amber-500', accent: '#F59E0B' },
  { id: 'avatar_06', name: 'Alpha Wolf', category: 'Beast', bg: 'from-purple-950 to-slate-950', border: 'border-purple-400', accent: '#A855F7' },
  { id: 'avatar_07', name: 'Shadow Panther', category: 'Beast', bg: 'from-yellow-950 to-slate-950', border: 'border-yellow-500', accent: '#EAB308' },
  { id: 'avatar_08', name: 'Golden Eagle', category: 'Beast', bg: 'from-amber-900 to-slate-950', border: 'border-amber-400', accent: '#F59E0B' },
  { id: 'avatar_09', name: 'Mecha Robot', category: 'Robot', bg: 'from-teal-950 to-slate-950', border: 'border-teal-400', accent: '#14B8A6' },
  { id: 'avatar_10', name: 'Skull Gamer', category: 'Skull', bg: 'from-fuchsia-950 to-slate-950', border: 'border-fuchsia-400', accent: '#E0E7FF' },
  { id: 'avatar_11', name: 'Thunder Bulldog', category: 'Mascot', bg: 'from-sky-950 to-slate-950', border: 'border-sky-400', accent: '#38BDF8' },
  { id: 'avatar_12', name: 'Crown Lion', category: 'Mascot', bg: 'from-yellow-900 to-slate-950', border: 'border-amber-300', accent: '#FBBF24' },
  { id: 'avatar_13', name: 'Cyber Skull', category: 'Skull', bg: 'from-red-950 to-slate-950', border: 'border-red-500', accent: '#EF4444' },
  { id: 'avatar_14', name: 'Storm Trooper', category: 'Soldier', bg: 'from-indigo-950 to-slate-950', border: 'border-indigo-400', accent: '#6366F1' },
  { id: 'avatar_15', name: 'Phoenix Knight', category: 'Knight', bg: 'from-orange-950 to-slate-950', border: 'border-orange-400', accent: '#FB923C' },
  { id: 'avatar_16', name: 'Phantom Assassin', category: 'Ninja', bg: 'from-violet-950 to-slate-950', border: 'border-violet-400', accent: '#C084FC' },
  { id: 'avatar_17', name: 'Venom Cobra', category: 'Beast', bg: 'from-emerald-950 to-slate-950', border: 'border-emerald-500', accent: '#34D399' },
  { id: 'avatar_18', name: 'Cosmic Sorcerer', category: 'Wizard', bg: 'from-purple-900 to-slate-950', border: 'border-fuchsia-400', accent: '#D8B4FE' },
  { id: 'avatar_19', name: 'Rogue Goblin', category: 'Gamer', bg: 'from-lime-950 to-slate-950', border: 'border-lime-400', accent: '#A3E635' },
  { id: 'avatar_20', name: 'Titan Warrior', category: 'Knight', bg: 'from-stone-900 to-slate-950', border: 'border-stone-400', accent: '#E7E5E4' }
];

export const DEFAULT_AVATAR_ID = 'avatar_01';

export function getAvatarById(id) {
  const found = AVATAR_LIST.find(a => a.id === id);
  return found || AVATAR_LIST[0];
}

/**
 * Render Avatar Badge / Component
 */
export function RenderAvatar({ avatarId, className = "w-10 h-10", size = 40 }) {
  const avatar = getAvatarById(avatarId);

  return (
    <div className={`relative shrink-0 rounded-2xl bg-gradient-to-br ${avatar.bg} border ${avatar.border} shadow-sm overflow-hidden flex items-center justify-center p-1.5 select-none ${className}`}>
      <img
        src={`/images/avatars/${avatar.id}.svg`}
        alt={`${avatar.name} Avatar`}
        width={size}
        height={size}
        loading="lazy"
        decoding="async"
        className="w-full h-full object-contain drop-shadow-md"
        onError={(e) => {
          // Fallback if image fails
          e.target.onerror = null;
          e.target.src = `/images/avatars/avatar_01.svg`;
        }}
      />
    </div>
  );
}
