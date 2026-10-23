import React, { useState } from 'react';
import { Lightbulb, Send, Sparkles, Palette, Bot, BookOpen, ShoppingBag, Bug } from 'lucide-react';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { SubmissionSuccessCard } from './SubmissionSuccessCard';
import { sendMessage } from '../utils/sendMessage';

/* /suggestions/: ideas and queries about the site (design, ROC, content, products), sent to suggestions@. */
const TOPICS = [
  { id: 'Website design', icon: Palette, text: 'Layout, colours, night theme, mobile' },
  { id: 'ROC assistant', icon: Bot, text: 'The chat agents and their answers' },
  { id: 'Guides & news', icon: BookOpen, text: 'Topics you want us to cover' },
  { id: 'Products & picks', icon: ShoppingBag, text: 'Gear we should add or compare' },
  { id: 'Something else', icon: Bug, text: 'Bugs, ideas, anything at all' },
];

export const SuggestionsView = () => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [topic, setTopic] = useState(TOPICS[0].id);
  const [message, setMessage] = useState('');
  const [website, setWebsite] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const [refId, setRefId] = useState('');

  const submit = async (e) => {
    e.preventDefault();
    if (!name || !email || !message || sending) return;
    setSending(true); setError('');
    try {
      const r = await sendMessage({ kind: 'suggestion', name: name.trim(), email: email.trim(), subject: topic, message: message.trim(), meta: { topic }, website });
      setRefId(r.reference);
    } catch (err) { setError(err.message); }
    finally { setSending(false); }
  };

  const field = 'w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all';

  return (
    <div className="space-y-10 py-6 max-w-5xl mx-auto animate-page-in">
      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-2xl relative overflow-hidden text-center max-w-3xl mx-auto border border-emerald-500/30">
        <CyberMatrixHoloBackground />
        <div className="relative z-10 space-y-3">
          <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5">
            <Lightbulb className="w-3.5 h-3.5" /> IDEAS &amp; SUGGESTIONS
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
            <BouncyText text="Help Us Make ROC Better" />
          </h1>
          <p className="text-emerald-100 text-xs sm:text-base leading-relaxed max-w-xl mx-auto">
            A question, an idea or something that bugs you: about the design, ROC, our guides or the gear. We read every message and take care of it.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <div className="lg:col-span-7">
          {refId ? (
            <SubmissionSuccessCard formType="suggestion" userName={name} referenceId={refId}
              onReset={() => { setRefId(''); setMessage(''); }} />
          ) : (
            <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
              <div className="flex items-center gap-3 border-b border-emerald-100 pb-3">
                <div className="w-10 h-10 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shrink-0">
                  <Sparkles className="w-5 h-5" />
                </div>
                <div>
                  <h2 className="font-display font-extrabold text-xl sm:text-2xl text-emerald-950">Send a suggestion</h2>
                  <p className="text-xs text-emerald-700 font-semibold">It goes to suggestions@runonconsole.com and you get a copy.</p>
                </div>
              </div>
              <form onSubmit={submit} className="space-y-4 text-xs sm:text-sm relative">
                <input type="text" name="website" value={website} onChange={(e) => setWebsite(e.target.value)} tabIndex={-1} autoComplete="off" aria-hidden="true" style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }} />
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <label className="block text-emerald-950 font-bold">YOUR NAME *
                    <input type="text" required value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Ali" className={field + ' mt-1 font-normal'} />
                  </label>
                  <label className="block text-emerald-950 font-bold">EMAIL ADDRESS *
                    <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} placeholder="you@example.com" className={field + ' mt-1 font-normal'} />
                  </label>
                </div>
                <fieldset>
                  <legend className="block text-emerald-950 font-bold mb-2">ABOUT *</legend>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {TOPICS.map((t) => {
                      const Icon = t.icon, on = topic === t.id;
                      return (
                        <button type="button" key={t.id} onClick={() => setTopic(t.id)} aria-pressed={on}
                          className={'text-left p-3 rounded-xl border-2 flex items-start gap-3 transition-all ' + (on ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white hover:border-emerald-300')}>
                          <Icon className={'w-5 h-5 shrink-0 ' + (on ? 'text-emerald-700' : 'text-slate-500')} />
                          <span><span className="block font-extrabold text-slate-900">{t.id}</span><span className="block text-[11px] text-slate-500">{t.text}</span></span>
                        </button>
                      );
                    })}
                  </div>
                </fieldset>
                <label className="block text-emerald-950 font-bold">YOUR SUGGESTION OR QUESTION *
                  <textarea required rows={5} value={message} onChange={(e) => setMessage(e.target.value)} placeholder="Tell us what to add, change or fix…" className={field + ' mt-1 font-normal'} />
                </label>
                {error && <p role="alert" className="text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3 font-semibold">{error}</p>}
                <button type="submit" disabled={sending} className="w-full bg-emerald-800 hover:bg-emerald-900 disabled:opacity-60 text-white font-display font-extrabold text-sm py-4 px-6 rounded-xl shadow-lg flex items-center justify-center gap-2">
                  <Send className="w-4 h-4 text-emerald-300" /> {sending ? 'Sending…' : 'Send Suggestion'}
                </button>
              </form>
            </Tilt3DCard>
          )}
        </div>
        <div className="lg:col-span-5 space-y-6">
          <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-7 shadow-sm space-y-4">
            <h2 className="font-display font-extrabold text-lg text-emerald-950">What happens next</h2>
            <ol className="space-y-3 text-sm text-slate-600 list-decimal pl-5">
              <li>Your message lands with the team at suggestions@runonconsole.com.</li>
              <li>You get a copy by email with a reference number.</li>
              <li>We read it, and when it needs an answer we reply, usually within 2 working days.</li>
            </ol>
            <p className="text-xs text-slate-500">Need help with your account or a problem on the site? Write to <a className="text-emerald-700 font-bold underline" href="mailto:support@runonconsole.com">support@runonconsole.com</a> or use the <a className="text-emerald-700 font-bold underline" href="/contact/">contact page</a>.</p>
          </Tilt3DCard>
        </div>
      </div>
    </div>
  );
};
