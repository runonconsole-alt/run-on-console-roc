import React, { useState } from 'react';
import { Gamepad2, Shield, Rocket, Swords } from 'lucide-react';

export const AVATARS = { gamepad: Gamepad2, shield: Shield, rocket: Rocket, swords: Swords };
export function GamingAvatar({ profile, className = 'w-16 h-16' }) {
  const Icon = AVATARS[profile?.avatarIcon] || Gamepad2;
  return profile?.gaming?.avatarData
    ? <img src={profile.gaming.avatarData} alt="Your gaming avatar" className={`${className} rounded-2xl object-cover`} />
    : <Icon aria-hidden="true" className={className} />;
}

export function GamingProfileEditor({ profile, csrfToken, onSaved }) {
  const [draft, setDraft] = useState({ name: profile.name || '', bio: profile.bio || '', country: profile.country || '',
    avatarIcon: profile.avatarIcon || 'gamepad', city: '', dateOfBirth: '', avatarData: '', favoriteGames: [], gamingIds: {}, socialLinks: {}, ...profile.gaming });
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const set = (key, value) => setDraft(d => ({ ...d, [key]: value }));
  const inputClass = 'w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500';
  const fileChange = async event => {
    const file = event.target.files?.[0];
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      setMessage('Choose a JPEG, PNG or WebP picture under 5 MB.'); return;
    }
    setBusy(true);
    try {
      const url = URL.createObjectURL(file);
      try {
        const img = new Image();
        await new Promise((resolve, reject) => { img.onload = resolve; img.onerror = reject; img.src = url; });
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = 256;
        const ctx = canvas.getContext('2d'); const side = Math.min(img.width, img.height);
        ctx.drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, 256, 256);
        set('avatarData', canvas.toDataURL('image/jpeg', 0.85)); setMessage('Photo ready. Save your profile to apply it.');
      } finally { URL.revokeObjectURL(url); }
    } catch { setMessage('This image could not be opened. Try another picture.'); }
    finally { setBusy(false); }
  };
  const save = async event => {
    event.preventDefault(); setBusy(true); setMessage('');
    try {
      const response = await fetch('/api/v1/profile.php?action=update-gaming-profile', { method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify(draft) });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.error || 'Could not save profile.');
      setMessage('Your gaming profile has been saved.'); await onSaved();
    } catch (error) { setMessage(error.message || 'Network error. Try again.'); }
    finally { setBusy(false); }
  };
  const linkGoogle = async () => {
    setBusy(true); setMessage('');
    try {
      const response = await fetch('/api/v1/auth.php?action=google-link', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
      const data = await response.json();
      if (!data.success) throw new Error(data.error);
      window.location.assign('/api/v1/oauth/google-start.php');
    } catch (error) { setMessage(error.message || 'Could not link Google.'); setBusy(false); }
  };
  return <form onSubmit={save} className="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 space-y-7">
    <div><h2 className="text-2xl font-bold">Build your gamer identity</h2><p className="text-slate-400 mt-2">Your profile, your games. Date of birth stays private. All fields except your display name are optional.</p></div>
    <div className="flex flex-wrap items-center gap-5">
      <div className="p-4 bg-emerald-500/10 rounded-2xl text-emerald-400"><GamingAvatar profile={{ ...draft, gaming: draft }} /></div>
      <div className="space-y-3"><label className="block">Upload profile picture<input className="block mt-2 max-w-full text-sm" type="file" accept="image/jpeg,image/png,image/webp" onChange={fileChange} disabled={busy} /></label>
        <p className="text-xs text-slate-400">Photos are cropped square. Or choose a built-in avatar.</p>
        <div className="flex gap-2">{Object.entries(AVATARS).map(([name, Icon]) => <button key={name} type="button" aria-label={`${name} avatar`} aria-pressed={!draft.avatarData && draft.avatarIcon === name}
          onClick={() => setDraft(d => ({ ...d, avatarData: '', avatarIcon: name }))} className={`p-3 rounded-xl border ${!draft.avatarData && draft.avatarIcon === name ? 'border-emerald-400 bg-emerald-500/20' : 'border-slate-700'}`}><Icon size={22} /></button>)}</div>
      </div>
    </div>
    <div className="grid sm:grid-cols-2 gap-5">
      <label className="space-y-2"><span>Display name</span><input className={inputClass} required maxLength={120} value={draft.name} onChange={e => set('name', e.target.value)} /></label>
      <label className="space-y-2"><span>Username</span><input className={`${inputClass} opacity-60`} value={profile.username || ''} readOnly /></label>
      <label className="space-y-2"><span>City</span><input className={inputClass} maxLength={100} autoComplete="address-level2" value={draft.city} onChange={e => set('city', e.target.value)} /></label>
      <label className="space-y-2"><span>Country</span><input className={inputClass} maxLength={50} autoComplete="country-name" value={draft.country} onChange={e => set('country', e.target.value)} /></label>
      <label className="space-y-2"><span>Date of birth, private</span><input className={inputClass} type="date" min="1900-01-01" max={new Date().toISOString().slice(0,10)} value={draft.dateOfBirth} onChange={e => set('dateOfBirth', e.target.value)} /></label>
    </div>
    <label className="block space-y-2"><span>Bio</span><textarea className={inputClass} rows={4} maxLength={2000} value={draft.bio} onChange={e => set('bio', e.target.value)} placeholder="Tell us what you play and what kind of gamer you are." /></label>
    <label className="block space-y-2"><span>Favourite games, separated by commas</span><input className={inputClass} value={draft.favoriteGames.join(',')} onChange={e => set('favoriteGames', e.target.value.split(',').slice(0,20))} placeholder="Valorant, Fortnite, Minecraft" /></label>
    <fieldset><legend className="text-lg font-bold mb-4">Gaming IDs</legend><div className="grid sm:grid-cols-2 gap-4">
      {['Steam', 'PlayStation', 'Xbox', 'Riot', 'Epic', 'Discord'].map(platform => <label className="space-y-2" key={platform}><span>{platform}</span><input className={inputClass} maxLength={100} value={draft.gamingIds[platform] || ''} onChange={e => set('gamingIds', { ...draft.gamingIds, [platform]: e.target.value })} /></label>)}
    </div></fieldset>
    <fieldset><legend className="text-lg font-bold mb-4">Social profiles</legend><div className="grid sm:grid-cols-2 gap-4">
      {['YouTube', 'Twitch', 'Instagram', 'TikTok', 'X', 'Facebook'].map(platform => <label className="space-y-2" key={platform}><span>{platform}</span><input className={inputClass} type="url" maxLength={300} placeholder="https://" value={draft.socialLinks[platform] || ''} onChange={e => set('socialLinks', { ...draft.socialLinks, [platform]: e.target.value })} /></label>)}
    </div></fieldset>
    {message && <p role="status" aria-live="polite" className="rounded-xl border border-slate-700 p-4">{message}</p>}
    <div className="flex flex-wrap gap-3"><button type="submit" disabled={busy} className="bg-emerald-500 text-slate-950 font-bold px-6 py-3 rounded-xl disabled:opacity-50">{busy ? 'Please wait…' : 'Save gaming profile'}</button>
      <button type="button" disabled={busy} onClick={linkGoogle} className="border border-slate-600 px-6 py-3 rounded-xl">Link Google</button></div>
    <p className="text-xs text-slate-400">Linking requires a sign-in within the last 10 minutes and a Google account with your profile email.</p>
  </form>;
}
