import React from 'react';

/**
 * Run On Console (ROC) - profile badges.
 *
 * A profile badge shows the first two letters of the person's name (like WhatsApp
 * and Gmail) on a colour they choose. The ids avatar_01 … avatar_20 are kept so
 * profiles saved earlier still work; each id is now a colour, not a picture.
 */

export const AVATAR_LIST = [
  { id: 'avatar_01', name: 'Emerald', bg: 'from-emerald-500 to-emerald-700', border: 'border-emerald-400' },
  { id: 'avatar_02', name: 'Rose', bg: 'from-rose-500 to-rose-700', border: 'border-rose-400' },
  { id: 'avatar_03', name: 'Cyan', bg: 'from-cyan-500 to-cyan-700', border: 'border-cyan-400' },
  { id: 'avatar_04', name: 'Blue', bg: 'from-blue-500 to-blue-700', border: 'border-blue-400' },
  { id: 'avatar_05', name: 'Amber', bg: 'from-amber-500 to-amber-700', border: 'border-amber-400' },
  { id: 'avatar_06', name: 'Purple', bg: 'from-purple-500 to-purple-700', border: 'border-purple-400' },
  { id: 'avatar_07', name: 'Yellow', bg: 'from-yellow-500 to-yellow-700', border: 'border-yellow-400' },
  { id: 'avatar_08', name: 'Orange', bg: 'from-orange-500 to-orange-700', border: 'border-orange-400' },
  { id: 'avatar_09', name: 'Teal', bg: 'from-teal-500 to-teal-700', border: 'border-teal-400' },
  { id: 'avatar_10', name: 'Fuchsia', bg: 'from-fuchsia-500 to-fuchsia-700', border: 'border-fuchsia-400' },
  { id: 'avatar_11', name: 'Sky', bg: 'from-sky-500 to-sky-700', border: 'border-sky-400' },
  { id: 'avatar_12', name: 'Gold', bg: 'from-amber-400 to-yellow-600', border: 'border-amber-300' },
  { id: 'avatar_13', name: 'Red', bg: 'from-red-500 to-red-700', border: 'border-red-400' },
  { id: 'avatar_14', name: 'Indigo', bg: 'from-indigo-500 to-indigo-700', border: 'border-indigo-400' },
  { id: 'avatar_15', name: 'Coral', bg: 'from-orange-400 to-rose-600', border: 'border-orange-300' },
  { id: 'avatar_16', name: 'Violet', bg: 'from-violet-500 to-violet-700', border: 'border-violet-400' },
  { id: 'avatar_17', name: 'Mint', bg: 'from-emerald-400 to-teal-600', border: 'border-emerald-300' },
  { id: 'avatar_18', name: 'Lavender', bg: 'from-purple-400 to-fuchsia-600', border: 'border-purple-300' },
  { id: 'avatar_19', name: 'Lime', bg: 'from-lime-500 to-lime-700', border: 'border-lime-400' },
  { id: 'avatar_20', name: 'Slate', bg: 'from-slate-500 to-slate-700', border: 'border-slate-400' }
];

export const DEFAULT_AVATAR_ID = 'avatar_01';

export function getAvatarById(id) {
  const found = AVATAR_LIST.find(a => a.id === id);
  return found || AVATAR_LIST[0];
}

/** First letters of the first two words ("Omar Abobakar" -> "OA"), or the first two letters of one word. */
export function getInitials(name) {
  const clean = String(name || '').replace(/@.*$/, '').replace(/[^\p{L}\p{N}\s._-]/gu, ' ').trim();
  if (!clean) return '?';
  const words = clean.split(/[\s._-]+/).filter(Boolean);
  const letters = words.length >= 2 ? words[0][0] + words[1][0] : words[0].slice(0, 2);
  return letters.toUpperCase();
}

/**
 * Profile badge: initials on the chosen colour.
 */
export function RenderAvatar({ avatarId, name = '', className = "w-10 h-10", size = 40 }) {
  const avatar = getAvatarById(avatarId);
  const initials = getInitials(name);
  return (
    <div
      role="img"
      aria-label={name ? `${name}` : 'Profile'}
      className={`relative shrink-0 rounded-2xl bg-gradient-to-br ${avatar.bg} border ${avatar.border} shadow-sm overflow-hidden flex items-center justify-center select-none text-white font-display font-extrabold leading-none ${className}`}
      style={{ fontSize: `${Math.max(8, Math.round(size * 0.42))}px` }}
    >
      {initials}
    </div>
  );
}
