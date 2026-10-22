import React, { useEffect, useRef, useState } from 'react';
import { Lock, Check, ArrowRight, AlertCircle, RefreshCw } from 'lucide-react';

/**
 * Email verification with a 6-digit code (no link): lock badge with a countdown ring, code boxes that pop
 * as they fill, a "Verifying" progress bar, a green "You're verified" state and a red shake on a wrong code.
 * Site colours: slate-950 card, emerald/teal accents.
 *
 * verify(code) -> Promise<{ success, message?, error? }>
 * resend()     -> Promise<{ success, message?, error? }>
 */
const LEN = 6;
const RESEND_SECONDS = 90;          // wait before "Resend code"
const MAX_RESENDS = 3;              // after this many new codes, wait an hour
const LOCK_SECONDS = 3600;
const memKey = (email) => 'roc-otp-' + String(email || '').toLowerCase();
const readMem = (email) => { try { return JSON.parse(localStorage.getItem(memKey(email)) || '{}') || {}; } catch (e) { return {}; } };
const writeMem = (email, m) => { try { localStorage.setItem(memKey(email), JSON.stringify(m)); } catch (e) {} };

const mask = (email) => {
  if (!email || !email.includes('@')) return email || 'your inbox';
  const [user, domain] = email.split('@');
  return (user.slice(0, 1) + '•'.repeat(Math.max(3, Math.min(6, user.length - 1)))) + '@' + domain;
};

export const OtpVerify = ({ email, emailEditable, onEmailChange, verify, resend, onChangeEmail, onDone }) => {
  const [digits, setDigits] = useState(Array(LEN).fill(''));
  const [state, setState] = useState('idle');          // idle | verifying | success | error
  const [message, setMessage] = useState('');
  const [note, setNote] = useState('');
  const [cooldown, setCooldown] = useState(RESEND_SECONDS);
  const [locked, setLocked] = useState(false);         // too many codes: an hour's wait, then "Try again"
  const [lockDone, setLockDone] = useState(false);
  const [shakeKey, setShakeKey] = useState(0);
  const refs = useRef([]);

  useEffect(() => { refs.current[0]?.focus(); }, []);
  // A reload must not skip the hour's wait (kept in this browser per email).
  useEffect(() => {
    const m = readMem(email), left = Math.ceil(((m.until || 0) - Date.now()) / 1000);
    if (left > 0) { setLocked(true); setCooldown(left); }
  }, [email]);
  useEffect(() => { if (locked && cooldown <= 0) setLockDone(true); }, [locked, cooldown]);
  useEffect(() => {
    if (cooldown <= 0) return undefined;
    const t = setInterval(() => setCooldown((c) => (c > 0 ? c - 1 : 0)), 1000);
    return () => clearInterval(t);
  }, [cooldown]);

  const submit = async (code) => {
    if (code.length !== LEN || state === 'verifying' || state === 'success') return;
    if (!email || !email.includes('@')) { setState('error'); setMessage('Enter the email you signed up with first.'); return; }
    setState('verifying'); setMessage(''); setNote('');
    const started = Date.now();
    let res;
    try { res = await verify(code); } catch (e) { res = { success: false, error: 'Network error. Please try again.' }; }
    await new Promise((r) => setTimeout(r, Math.max(0, 900 - (Date.now() - started))));   // let the progress bar finish
    if (res && res.success) {
      setState('success');
      setTimeout(() => onDone && onDone(), 2200);
    } else {
      setState('error');
      setMessage((res && (res.message || res.error)) || "That code didn't match. Try again.");
      setShakeKey((k) => k + 1);
      setTimeout(() => { setDigits(Array(LEN).fill('')); setState('idle'); refs.current[0]?.focus(); }, 1300);
    }
  };

  const setAt = (i, v) => {
    if (state === 'verifying' || state === 'success') return;
    const d = v.replace(/\D/g, '');
    if (d.length > 1) { fill(d); return; }                   // autofill of the whole code into one box
    const next = [...digits]; next[i] = d; setDigits(next);
    if (state === 'error') { setState('idle'); setMessage(''); }
    if (d && i < LEN - 1) refs.current[i + 1]?.focus();
    if (next.every(Boolean)) submit(next.join(''));
  };

  const fill = (raw) => {
    const d = raw.replace(/\D/g, '').slice(0, LEN);
    if (!d) return;
    const next = Array(LEN).fill('').map((_, i) => d[i] || '');
    setDigits(next);
    refs.current[Math.min(d.length, LEN - 1)]?.focus();
    if (d.length === LEN) submit(d);
  };

  const onKey = (i, e) => {
    if (e.key === 'Backspace' && !digits[i] && i > 0) refs.current[i - 1]?.focus();
    if (e.key === 'ArrowLeft' && i > 0) refs.current[i - 1]?.focus();
    if (e.key === 'ArrowRight' && i < LEN - 1) refs.current[i + 1]?.focus();
    if (e.key === 'Enter') submit(digits.join(''));
  };

  const lockHour = () => {
    writeMem(email, { n: MAX_RESENDS, until: Date.now() + LOCK_SECONDS * 1000 });
    setLocked(true); setLockDone(false); setCooldown(LOCK_SECONDS);
  };

  const doResend = async () => {
    if (cooldown > 0) return;
    setNote(''); setMessage('');
    const m = readMem(email);
    if ((m.n || 0) >= MAX_RESENDS) { lockHour(); return; }
    let res;
    try { res = await resend(); } catch (e) { res = { success: false, error: 'Could not send a new code. Try again.' }; }
    if (res && res.success) {
      const n = (m.n || 0) + 1;
      writeMem(email, { n, until: 0 });
      setNote('A new code is on its way. Only the newest code works.');
      setDigits(Array(LEN).fill('')); refs.current[0]?.focus();
      if (n >= MAX_RESENDS) lockHour(); else setCooldown(RESEND_SECONDS);
    } else if (/too many|try again later|60 seconds/i.test((res && (res.error || res.message)) || '')) {
      lockHour();                                       // the server says wait: show the hour's countdown
    } else { setMessage((res && (res.message || res.error)) || 'Could not send a new code.'); }
  };

  const tryAgain = () => {                             // the hour is over: start fresh
    writeMem(email, { n: 0, until: 0 });
    setLocked(false); setLockDone(false); setCooldown(0); setNote(''); setMessage('');
    doResendFresh();
  };
  const doResendFresh = async () => {
    let res;
    try { res = await resend(); } catch (e) { res = { success: false }; }
    if (res && res.success) { writeMem(email, { n: 1, until: 0 }); setCooldown(RESEND_SECONDS); setNote('A new code is on its way. Only the newest code works.'); refs.current[0]?.focus(); }
    else setMessage((res && (res.message || res.error)) || 'Could not send a new code. Try again later.');
  };

  const R = 44, C = 2 * Math.PI * R;
  const ring = state === 'success' ? 1 : cooldown / (locked ? LOCK_SECONDS : RESEND_SECONDS);
  const mm = Math.floor(cooldown / 60), ss = String(cooldown % 60).padStart(2, '0');
  const ringMax = locked ? LOCK_SECONDS : RESEND_SECONDS;
  const ok = state === 'success', bad = state === 'error', busy = state === 'verifying';

  return (
    <div className={`otp-card ${ok ? 'is-ok' : ''} ${bad ? 'is-bad' : ''}`}>
      <div className={`otp-badge ${ok ? 'ok' : ''} ${bad ? 'bad' : ''}`} aria-hidden="true">
        <svg viewBox="0 0 100 100" className={`otp-ring ${busy ? 'spin' : ''}`}>
          <circle cx="50" cy="50" r={R} className="track" />
          <circle cx="50" cy="50" r={R} className="bar" strokeDasharray={C} strokeDashoffset={C * (1 - (busy ? 0.3 : ring))} />
        </svg>
        <span className="otp-icon">{ok ? <Check className="w-7 h-7" strokeWidth={3} /> : <Lock className="w-6 h-6" />}</span>
      </div>

      <h2 className="otp-title" key={ok ? 't-ok' : 't'}>{ok ? 'You’re verified' : 'Verify it’s you'}</h2>
      <p className="otp-sub">
        {ok ? 'Your account is ready. Opening your profile…' : <>Enter the 6-digit code we just sent to<br /><b>{mask(email)}</b></>}
      </p>

      {emailEditable && !ok && (
        <label className="otp-email">
          <span>Your sign-up email</span>
          <input type="email" autoComplete="email" value={email || ''} onChange={(e) => onEmailChange(e.target.value)} placeholder="you@example.com" />
        </label>
      )}

      <div className="otp-boxes" key={shakeKey} data-shake={bad ? '1' : '0'} onPaste={(e) => { e.preventDefault(); fill(e.clipboardData.getData('text')); }}>
        {digits.map((d, i) => (
          <input
            key={i}
            ref={(el) => (refs.current[i] = el)}
            className={`otp-box ${d ? 'filled' : ''}`}
            style={{ '--i': i }}
            type="text"
            inputMode="numeric"
            pattern="[0-9]*"
            autoComplete={i === 0 ? 'one-time-code' : 'off'}
            maxLength={i === 0 ? LEN : 1}
            aria-label={`Digit ${i + 1} of ${LEN}`}
            value={d}
            disabled={busy || ok}
            onChange={(e) => setAt(i, e.target.value)}
            onKeyDown={(e) => onKey(i, e)}
            onFocus={(e) => e.target.select()}
          />
        ))}
      </div>

      <p className={`otp-msg ${message ? 'show' : ''}`} role="alert">
        {message && <><AlertCircle className="w-4 h-4" /> {message}</>}
      </p>

      {ok ? (
        <button type="button" className="otp-btn ok" onClick={() => onDone && onDone()}>
          Continue <ArrowRight className="w-4 h-4" />
        </button>
      ) : (
        <button type="button" className={`otp-btn ${busy ? 'busy' : ''}`} disabled={busy || digits.join('').length !== LEN} onClick={() => submit(digits.join(''))}>
          <span className="fill" />
          <span className="lbl">{busy ? <><RefreshCw className="w-4 h-4 animate-spin" /> Verifying</> : 'Verify code'}</span>
        </button>
      )}

      {!ok && (
        <div className="otp-foot">
          <span>Didn’t get a code?</span>
          {cooldown > 0 ? (
            <span className="otp-wait">
              <svg viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" className="track" /><circle cx="10" cy="10" r="8" className="bar" strokeDasharray={50.3} strokeDashoffset={50.3 * (1 - cooldown / ringMax)} /></svg>
              {locked ? <>Too many codes. New code in {mm}:{ss}</> : <>Resend in {mm}:{ss}</>}
            </span>
          ) : locked && lockDone ? (
            <button type="button" onClick={tryAgain} className="otp-link">Try again</button>
          ) : (
            <button type="button" onClick={doResend} className="otp-link">Resend code</button>
          )}
        </div>
      )}
      {note && !ok && <p className="otp-note">{note}</p>}
      {!ok && <p className="otp-hint">Check your spam folder too. The code works for 10 minutes. <button type="button" className="otp-link" onClick={onChangeEmail}>Use a different email</button></p>}
    </div>
  );
};
