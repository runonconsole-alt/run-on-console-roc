import React, { useState } from 'react';
import { X, Sparkles, Check } from 'lucide-react';
import { ActionFeedbackCard } from './ActionFeedbackCard';
import { AVATAR_LIST, getAvatarById, RenderAvatar, DEFAULT_AVATAR_ID } from '../data/avatarRegistry';

export function GamingAvatar({ profile, className = 'w-16 h-16', size = 48 }) {
  const avatarId = profile?.avatarIcon || profile?.gaming?.avatarIcon || DEFAULT_AVATAR_ID;
  return <RenderAvatar avatarId={avatarId} className={className} size={size} />;
}

export function GamingProfileEditor({ profile, csrfToken, onSaved }) {
  const [draft, setDraft] = useState({
    name: profile.name || '',
    bio: profile.bio || '',
    country: profile.country || '',
    avatarIcon: profile.avatarIcon || DEFAULT_AVATAR_ID,
    city: '',
    dateOfBirth: '',
    favoriteGames: [],
    gamingIds: {},
    socialLinks: {},
    ...profile.gaming
  });

  const [busy, setBusy] = useState(false);
  const [showGallery, setShowGallery] = useState(false);
  const [tempAvatarIcon, setTempAvatarIcon] = useState(draft.avatarIcon || DEFAULT_AVATAR_ID);
  const [feedback, setFeedback] = useState({ status: 'idle', title: '', message: '' });

  const set = (key, value) => setDraft(d => ({ ...d, [key]: value }));
  const inputClass = 'w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors';

  const selectedAvatar = getAvatarById(draft.avatarIcon);

  const openGalleryModal = () => {
    setTempAvatarIcon(draft.avatarIcon || DEFAULT_AVATAR_ID);
    setShowGallery(true);
  };

  const confirmGallerySelection = () => {
    setDraft(d => ({ ...d, avatarIcon: tempAvatarIcon }));
    setShowGallery(false);
  };

  const save = async (e) => {
    e.preventDefault();
    setBusy(true);
    setFeedback({ status: 'submitting', title: 'Saving Profile', message: 'Updating gamer identity and avatar selection...' });

    try {
      const resp = await fetch('/api/v1/profile.php?action=update-profile', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          name: draft.name,
          country: draft.country,
          bio: draft.bio,
          avatarIcon: draft.avatarIcon,
          city: draft.city,
          dateOfBirth: draft.dateOfBirth,
          favoriteGames: Array.isArray(draft.favoriteGames) ? draft.favoriteGames : (draft.favoriteGames || '').split(',').map(s => s.trim()).filter(Boolean),
          gamingIds: draft.gamingIds || {},
          socialLinks: draft.socialLinks || {}
        })
      });

      const data = await resp.json();
      if (!resp.ok || !data.success) {
        throw new Error(data.error || 'Failed to save gaming profile.');
      }

      setFeedback({
        status: 'completed',
        title: 'Profile Saved',
        message: 'Your gamer identity and avatar selection have been saved to your account.'
      });

      if (onSaved) onSaved(data);
    } catch (error) {
      setFeedback({
        status: 'failed',
        title: 'Save Failed',
        message: error.message || 'Error saving gaming profile.'
      });
    } finally {
      setBusy(false);
    }
  };

  const linkGoogle = () => {
    setFeedback({ status: 'submitting', title: 'Linking Google Account', message: 'Redirecting to Google secure authentication...' });
    if (typeof window !== 'undefined') {
      window.location.href = '/api/v1/oauth/google-start.php';
    }
  };

  return (
    <form onSubmit={save} className="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-7 shadow-2xl">
      <div>
        <h2 className="text-2xl font-extrabold text-white">Build Your Gamer Identity</h2>
        <p className="text-slate-400 mt-2 text-sm">
          Customize your public gamer card, esports avatar portrait, favorite games, and social links.
        </p>
      </div>

      {/* Esports Avatar Badge & Picker Launcher */}
      <div className="flex flex-wrap items-center gap-6 p-6 bg-slate-950/60 rounded-2xl border border-slate-800">
        <GamingAvatar profile={draft} className="w-20 h-20 rounded-2xl" size={64} />

        <div className="space-y-2.5 flex-1 min-w-[240px]">
          <div className="flex flex-wrap items-center gap-3">
            <button
              type="button"
              onClick={openGalleryModal}
              className="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs px-5 py-3 rounded-xl flex items-center gap-2 transition-all shadow-md hover:scale-105 cursor-pointer"
            >
              <Sparkles className="w-4 h-4" />
              <span>Change Gaming Avatar</span>
            </button>
          </div>

          <p className="text-xs text-slate-300">
            Current Avatar: <strong className="text-emerald-400">{selectedAvatar.name}</strong> ({selectedAvatar.category})
          </p>
        </div>
      </div>

      {/* Avatar Selection Modal Gallery */}
      {showGallery && (
        <div 
          className="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-4"
          role="dialog"
          aria-modal="true"
          aria-label="Choose Esports Gaming Avatar"
          onKeyDown={(e) => { if (e.key === 'Escape') setShowGallery(false); }}
        >
          <div className="bg-white border-2 border-slate-200 rounded-3xl p-6 max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl relative text-slate-900">
            <div className="flex items-center justify-between pb-4 border-b border-slate-200">
              <div>
                <h3 className="text-xl font-extrabold text-slate-900 flex items-center gap-2">
                  <Sparkles className="w-5 h-5 text-emerald-600" />
                  <span>Choose Your Esports Avatar</span>
                </h3>
                <p className="text-xs text-slate-600 mt-1">Click an avatar to preview, then press Confirm Avatar Selection below</p>
              </div>
              <button
                type="button"
                onClick={() => setShowGallery(false)}
                className="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors"
                aria-label="Close avatar modal"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Avatar Grid Gallery */}
            <div className="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-5 gap-3.5 py-5 overflow-y-auto max-h-[60vh] pr-1">
              {AVATAR_LIST.map((avatar) => {
                const isSelected = tempAvatarIcon === avatar.id;
                return (
                  <button
                    key={avatar.id}
                    type="button"
                    onClick={() => setTempAvatarIcon(avatar.id)}
                    className={`relative p-3 rounded-2xl border-2 transition-all text-left group cursor-pointer flex flex-col items-center gap-2 ${
                      isSelected
                        ? 'border-emerald-500 bg-emerald-50 shadow-md shadow-emerald-500/20 scale-102'
                        : 'border-slate-200 bg-slate-50/80 hover:border-emerald-300 hover:bg-slate-100'
                    }`}
                  >
                    {isSelected && (
                      <span className="absolute top-2 right-2 p-1 bg-emerald-600 text-white rounded-full shadow-md">
                        <Check className="w-3 h-3 stroke-[3]" />
                      </span>
                    )}

                    <RenderAvatar avatarId={avatar.id} className="w-14 h-14 rounded-xl" size={48} />
                    <span className={`text-[11px] font-bold line-clamp-1 text-center ${isSelected ? 'text-emerald-800' : 'text-slate-900 group-hover:text-emerald-700'}`}>
                      {avatar.name}
                    </span>
                    <span className="text-[9px] font-mono font-bold uppercase tracking-wider text-slate-500">
                      {avatar.category}
                    </span>
                  </button>
                );
              })}
            </div>

            <div className="pt-4 border-t border-slate-200 flex items-center justify-between gap-4">
              <div className="text-xs text-slate-600 font-medium hidden sm:block">
                Selected: <strong className="text-emerald-700 font-extrabold">{getAvatarById(tempAvatarIcon).name}</strong>
              </div>
              <div className="flex items-center gap-3 w-full sm:w-auto justify-end">
                <button
                  type="button"
                  onClick={() => setShowGallery(false)}
                  className="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-100 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={confirmGallerySelection}
                  className="bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs px-6 py-2.5 rounded-xl transition-all shadow-md hover:scale-105 cursor-pointer"
                >
                  Confirm Avatar Selection
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Form Text Inputs */}
      <div className="grid sm:grid-cols-2 gap-5">
        <label className="space-y-2">
          <span className="text-xs font-bold uppercase tracking-wider text-slate-300">Display Name</span>
          <input className={inputClass} required maxLength={120} value={draft.name} onChange={e => set('name', e.target.value)} />
        </label>

        <label className="space-y-2">
          <span className="text-xs font-bold uppercase tracking-wider text-slate-300">Username</span>
          <input className={`${inputClass} opacity-60 cursor-not-allowed`} value={profile.username || ''} readOnly />
        </label>

        <label className="space-y-2">
          <span className="text-xs font-bold uppercase tracking-wider text-slate-300">City</span>
          <input className={inputClass} maxLength={100} autoComplete="address-level2" value={draft.city} onChange={e => set('city', e.target.value)} />
        </label>

        <label className="space-y-2">
          <span className="text-xs font-bold uppercase tracking-wider text-slate-300">Country</span>
          <input className={inputClass} maxLength={50} autoComplete="country-name" value={draft.country} onChange={e => set('country', e.target.value)} />
        </label>

        <label className="space-y-2 sm:col-span-2">
          <span className="text-xs font-bold uppercase tracking-wider text-slate-300">Date of Birth (Private)</span>
          <input className={inputClass} type="date" min="1900-01-01" max={new Date().toISOString().slice(0, 10)} value={draft.dateOfBirth} onChange={e => set('dateOfBirth', e.target.value)} />
        </label>
      </div>

      <label className="block space-y-2">
        <span className="text-xs font-bold uppercase tracking-wider text-slate-300">Gamer Bio</span>
        <textarea className={inputClass} rows={3} maxLength={2000} value={draft.bio} onChange={e => set('bio', e.target.value)} placeholder="Tell us what you play and your favorite hardware setup." />
      </label>

      <label className="block space-y-2">
        <span className="text-xs font-bold uppercase tracking-wider text-slate-300">Favorite Games (Comma Separated)</span>
        <input className={inputClass} value={Array.isArray(draft.favoriteGames) ? draft.favoriteGames.join(', ') : draft.favoriteGames} onChange={e => set('favoriteGames', e.target.value.split(',').map(s => s.trim()).slice(0, 20))} placeholder="Valorant, Fortnite, Counter-Strike 2, Cyberpunk 2077" />
      </label>

      {/* Gaming IDs */}
      <fieldset className="space-y-3">
        <legend className="text-sm font-extrabold uppercase tracking-wider text-emerald-400">Gaming IDs</legend>
        <div className="grid sm:grid-cols-2 gap-4">
          {['Steam', 'PlayStation', 'Xbox', 'Riot', 'Epic', 'Discord'].map(platform => (
            <label className="space-y-1.5" key={platform}>
              <span className="text-xs font-semibold text-slate-300">{platform}</span>
              <input className={inputClass} maxLength={100} value={draft.gamingIds?.[platform] || ''} onChange={e => set('gamingIds', { ...draft.gamingIds, [platform]: e.target.value })} />
            </label>
          ))}
        </div>
      </fieldset>

      {/* Social Profiles */}
      <fieldset className="space-y-3">
        <legend className="text-sm font-extrabold uppercase tracking-wider text-emerald-400">Social Profiles (HTTPS URLs)</legend>
        <div className="grid sm:grid-cols-2 gap-4">
          {['YouTube', 'Twitch', 'Instagram', 'TikTok', 'X', 'Facebook'].map(platform => (
            <label className="space-y-1.5" key={platform}>
              <span className="text-xs font-semibold text-slate-300">{platform}</span>
              <input className={inputClass} type="url" maxLength={300} placeholder="https://" value={draft.socialLinks?.[platform] || ''} onChange={e => set('socialLinks', { ...draft.socialLinks, [platform]: e.target.value })} />
            </label>
          ))}
        </div>
      </fieldset>

      {/* Accessible Feedback Card */}
      <ActionFeedbackCard
        status={feedback.status}
        variant="profile"
        title={feedback.title}
        message={feedback.message}
      />

      <div className="flex flex-wrap gap-4 pt-2">
        <button
          type="submit"
          disabled={busy}
          className="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold px-7 py-3.5 rounded-xl shadow-lg disabled:opacity-50 transition-colors cursor-pointer"
        >
          {busy ? 'Saving...' : 'Save Gaming Profile'}
        </button>

        <button
          type="button"
          disabled={busy}
          onClick={linkGoogle}
          className="border border-slate-700 bg-slate-950 hover:bg-slate-800 text-white font-bold px-6 py-3.5 rounded-xl transition-colors cursor-pointer"
        >
          Link Google Account
        </button>
      </div>
    </form>
  );
}
