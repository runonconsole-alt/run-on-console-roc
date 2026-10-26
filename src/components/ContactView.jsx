import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { Mail, MapPin, Phone, Send, MessageSquare, ShieldCheck, Check, Sparkles, SendHorizontal } from 'lucide-react';
import { CONTACT } from '../data/siteContact';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { SubmissionSuccessCard } from './SubmissionSuccessCard';
import { sendMessage } from '../utils/sendMessage';
import { FAQSection } from './FAQSection';

const CONTACT_FAQS = [
  { q: 'How quickly does Run On Console reply?', a: 'We read every message and usually reply within 1-2 working days by email. You get a copy of your message with a reference number as soon as you send it, so you can quote it if you write again.' },
  { q: 'Which email should I use?', a: 'Use contact@runonconsole.com for questions, corrections, review samples and partnerships, and support@runonconsole.com for help with your account, signing in or a problem on the site. Ideas for the site go to suggestions@runonconsole.com (or the suggestions page).' },
  { q: 'Can you tell me if my PC can run a game?', a: 'Yes, and faster than by email: the free Can I run it checker at /compatibility/ compares your graphics card, processor and RAM with the official requirements of 2,000+ games, and ROC, our AI assistant (the chat button), can check it with you in English or Roman Urdu.' },
  { q: 'Do you sell products or handle orders?', a: 'No. Run On Console recommends gear and links to Amazon, where you see the current price and stock and place the order. For an order, a delivery or a return, contact the store you bought from.' },
  { q: 'I found a mistake on a page. What should I do?', a: 'Send it with the form above and choose Website Feedback / Bug Report, or write to support@runonconsole.com with the page address. We check every report and correct the page.' },
];


export const ContactView = () => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [subject, setSubject] = useState('General Inquiry');
  const [message, setMessage] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [refId, setRefId] = useState('');
  const [website, setWebsite] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!name || !email || !message || sending) return;
    setSending(true);
    setError('');
    try {
      // Help and problems go to support@, everything else to contact@ (api/v1/messages.php).
      const kind = /support|bug/i.test(subject) ? 'support' : 'contact';
      const r = await sendMessage({ kind, name: name.trim(), email: email.trim(), subject, message: message.trim(), website });
      setRefId(r.reference);
      setSubmitted(true);
    } catch (err) {
      setError(err.message);
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="space-y-12 py-6 max-w-5xl mx-auto animate-page-in">
      
      {/* Contact Hero Banner with BouncyText & Cyber Matrix Background */}
      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-2xl relative overflow-hidden text-center max-w-3xl mx-auto border border-emerald-500/30">
        <CyberMatrixHoloBackground />

        <div className="relative z-10 space-y-3">
          <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
            <Sparkles className="w-3.5 h-3.5" /> GET IN TOUCH
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
            <BouncyText text="Contact Run On Console" />
          </h1>
          <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-xl mx-auto">
            Have a hardware question, review sample submission, or brand partnership proposal? We read every message.
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* Left Form Box WITH PARTY POPPER SUBMISSION SUCCESS */}
        <div className="lg:col-span-7">
          {submitted ? (
            <SubmissionSuccessCard 
              formType={/support|bug/i.test(subject) ? 'support' : 'contact'}
              userName={name}
              referenceId={refId}
              onReset={() => {
                setSubmitted(false);
                setName('');
                setEmail('');
                setMessage('');
              }}
            />
          ) : (
            <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
              <div className="flex items-center gap-3 border-b border-emerald-100 pb-3">
                <div className="w-10 h-10 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md shadow-emerald-500/20 shrink-0">
                  <SendHorizontal className="w-5 h-5 animate-pulse" />
                </div>
                <div>
                  <h2 className="font-display font-extrabold text-xl sm:text-2xl text-emerald-950">
                    <BouncyText text="Send an Editorial Message" />
                  </h2>
                  <p className="text-xs text-emerald-700 font-semibold">
                    We read every message and reply by email, usually within 1-2 working days.
                  </p>
                </div>
              </div>

              <form onSubmit={handleSubmit} className="space-y-4 text-xs sm:text-sm relative">
                {/* Left empty by people; bots fill it in. */}
                <input type="text" name="website" value={website} onChange={(e) => setWebsite(e.target.value)} tabIndex={-1} autoComplete="off" aria-hidden="true" style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }} />
                <div>
                  <label className="block text-emerald-950 font-bold mb-1">YOUR NAME *</label>
                  <input 
                    type="text"
                    required
                    placeholder="e.g. Alex Vance"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-emerald-950 font-bold mb-1">EMAIL ADDRESS *</label>
                  <input 
                    type="email"
                    required
                    placeholder="e.g. alex@gamingrigs.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all"
                  />
                </div>

                <div>
                  <label className="block text-emerald-950 font-bold mb-1">INQUIRY TYPE</label>
                  <select
                    value={subject}
                    onChange={(e) => setSubject(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
                  >
                    <option value="General Inquiry">General Reader Question</option>
                    <option value="Help / Support">Help &amp; Support (account, sign-in, site problem)</option>
                    <option value="Hardware Review Submission">Hardware Review Sample Submission</option>
                    <option value="Brand / Affiliate Partnership">Affiliate / Advertising Partnership</option>
                    <option value="Bug Report / Feedback">Website Feedback / Bug Report</option>
                  </select>
                </div>

                <div>
                  <label className="block text-emerald-950 font-bold mb-1">MESSAGE *</label>
                  <textarea 
                    required
                    rows={4}
                    placeholder="Provide details regarding your hardware inquiry..."
                    value={message}
                    onChange={(e) => setMessage(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all"
                  ></textarea>
                </div>

                {error && <p role="alert" className="text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3 font-semibold">{error}</p>}
                <button 
                  type="submit"
                  disabled={sending}
                  className="w-full bg-emerald-800 hover:bg-emerald-900 disabled:opacity-60 text-white font-display font-extrabold text-sm py-4 px-6 rounded-xl shadow-lg border border-emerald-500/40 flex items-center justify-center gap-2 transition-all"
                >
                  <Send className="w-4 h-4 text-emerald-300" />
                  <span>{sending ? 'Sending…' : 'Send Message'}</span>
                </button>
              </form>
            </Tilt3DCard>
          )}
        </div>

        {/* Right Info Cards: SPACIOUS & DE-CONGESTED WITH GENEROUS GAPS */}
        <div className="lg:col-span-5 space-y-6">
          
          <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-7 sm:p-8 shadow-sm space-y-5">
            <h2 className="font-display font-extrabold text-lg sm:text-xl text-emerald-950 border-b border-emerald-100 pb-3">
              <BouncyText text="Contact Details" />
            </h2>

            <div className="space-y-4 pt-1">
              <div className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <Mail className="w-5 h-5 animate-pulse" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Direct Email</div>
                  <a href={'mailto:' + CONTACT.email} className="text-xs sm:text-sm text-emerald-700 font-extrabold hover:underline block mt-0.5 truncate">
                    {CONTACT.email}
                  </a>
                  <span className="text-[10px] text-slate-500 block mt-0.5">Questions, reviews and partnerships · reply within 2 working days</span>
                </div>
              </div>
              <div className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <ShieldCheck className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Help &amp; Support</div>
                  <a href="mailto:support@runonconsole.com" className="text-xs sm:text-sm text-emerald-700 font-extrabold hover:underline block mt-0.5 truncate">
                    support@runonconsole.com
                  </a>
                  <span className="text-[10px] text-slate-500 block mt-0.5">Account, sign-in or a problem on the site</span>
                </div>
              </div>
              <a href="/suggestions/" className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs no-underline">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <Sparkles className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Ideas &amp; suggestions</div>
                  <span className="text-xs sm:text-sm text-emerald-700 font-extrabold hover:underline block mt-0.5">suggestions@runonconsole.com</span>
                  <span className="text-[10px] text-slate-500 block mt-0.5">Design, ROC, content: tell us on the suggestions page</span>
                </div>
              </a>
              <a href={CONTACT.tel} className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs no-underline">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <Phone className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Phone</div>
                  <span className="text-xs sm:text-sm text-emerald-700 font-extrabold hover:underline block mt-0.5">{CONTACT.phone}</span>
                  <span className="text-[10px] text-slate-500 block mt-0.5">Tap to call</span>
                </div>
              </a>
              <a href={CONTACT.map} target="_blank" rel="noopener" className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs no-underline">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <MapPin className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Address</div>
                  <span className="text-xs sm:text-sm text-emerald-700 font-extrabold hover:underline block mt-0.5">{CONTACT.address}</span>
                  <span className="text-[10px] text-slate-500 block mt-0.5">Open in Google Maps</span>
                </div>
              </a>

            </div>
          </Tilt3DCard>

          {/* Shown by roc-nav.js once a Discord link is saved in CMS -> Social & Amazon tag. */}
          <div data-roc-social-box="discord" style={{ display: 'none' }}>
          <Tilt3DCard className="bg-gradient-to-tr from-[#064E3B] to-[#047857] text-white rounded-3xl p-7 sm:p-8 shadow-md space-y-3 border border-emerald-500/40">
            <p className="m-0 tracking-tight font-display font-bold text-lg text-white">
              <BouncyText text="Join Our Discord Community" />
            </p>
            <p className="text-xs text-emerald-100 leading-relaxed">
              Connect with other PC builders and gamers, share your battlestation, and get advice on gear.
            </p>
            <div className="pt-2">
              <a
                data-roc-social="discord"
                href="/contact/"
                target="_blank"
                rel="noopener"
                className="inline-block bg-white text-emerald-950 font-bold text-xs px-5 py-2.5 rounded-xl shadow-md no-underline hover:bg-emerald-50"
              >
                Join Our Discord Server →
              </a>
            </div>
          </Tilt3DCard>
          </div>

        </div>

      </div>

      <FAQSection faqs={CONTACT_FAQS} title="Contact FAQs" subtitle="Quick answers before you write to us." />

    </div>
  );
};
