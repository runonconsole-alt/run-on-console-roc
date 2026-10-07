import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { 
  PenTool, CheckCircle, DollarSign, Mail, Phone, User, 
  Send, Sparkles, ShieldCheck, FileText, Check, ArrowRight, Zap, Award 
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { SubmissionSuccessCard } from './SubmissionSuccessCard';
import { sendMessage } from '../utils/sendMessage';

export const WriteForUsView = () => {
  const { addGuestSubmission, sendNotificationEmail } = useApp();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [contact, setContact] = useState('');
  const [niche, setNiche] = useState('Esports Hardware & Accessories');
  const [title, setTitle] = useState('');
  const [pitch, setPitch] = useState('');
  const [selectedPlan, setSelectedPlan] = useState('Standard Guest Article ($50)');
  const [submitted, setSubmitted] = useState(false);
  const [submittedData, setSubmittedData] = useState(null);
  const [website, setWebsite] = useState('');
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');

  const publishingPackages = [
    {
      id: "std",
      name: "Standard Guest Article",
      price: "$50",
      badge: "MOST POPULAR",
      desc: "Perfect for gaming journalists, tech writers, and indie developers looking for quality editorial exposure.",
      features: [
        "1000+ Words in-depth editorial article",
        '1 permanent backlink (rel="sponsored")',
        "Fast 24-hour editorial review",
        "Permanent Google Indexing guarantee"
      ]
    },
    {
      id: "feat",
      name: "Featured Hardware Review",
      price: "$120",
      badge: "HIGH AUTHORITY",
      desc: "Ideal for peripheral manufacturers, gaming brands, and premium tech products.",
      features: [
        "1500+ Words lab benchmark testing",
        '2 permanent backlinks (rel="sponsored")',
        "Featured on Run On Console Homepage",
        "Dedicated Product Gallery & Buy Box"
      ]
    },
    {
      id: "guide",
      name: "Comprehensive Buyer Guide",
      price: "$200",
      badge: "MAXIMUM TRAFFIC",
      desc: "Ultimate authority piece targeting high-intent gaming hardware keywords.",
      features: [
        "2500+ Words cornerstone buyer guide",
        '3 permanent backlinks (rel="sponsored")',
        "Pinned Category Hub placement",
        "Included in 50K+ Subscriber Newsletter"
      ]
    }
  ];

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!name || !email || !contact || !title || !pitch) {
      setError('Please fill in all required fields.');
      return;
    }
    if (sending) return;
    setSending(true);
    setError('');
    try {
      const r = await sendMessage({
        kind: 'write_for_us',
        name: name.trim(),
        email: email.trim(),
        subject: title.trim(),
        message: pitch.trim(),
        meta: { plan: selectedPlan, niche, contact: contact.trim() },
        website,
      });
      setSubmittedData({ name, id: r.reference });
      setSubmitted(true);
    } catch (err) {
      setError(err.message);
    } finally {
      setSending(false);
    }
  };

  const guestFaqs = [
    {
      q: "Why is guest posting on Run On Console a paid service?",
      a: "To maintain the highest editorial standards, every submission is thoroughly fact-checked, proofread, and formatted by our senior tech editors. Paid slots get priority review. As Google requires for paid placements, links in sponsored articles are marked rel=\"sponsored\"."
    },
    {
      q: "What payment methods do you accept?",
      a: "Once your article pitch is approved by our editorial desk, we send a secure invoice via PayPal, Stripe (Credit/Debit Card), or Bank Wire."
    },
    {
      q: "What niches and topics are accepted?",
      a: "We accept articles covering PC Gaming, Consoles (PS5, Xbox, PSP/Handhelds), Esports Hardware (Keyboards, Mice, Monitors, Audio), Game Optimization, and Tech Buying Guides."
    },
    {
      q: "How soon will my article go live after submission?",
      a: "Approved drafts are typically published and indexed within 24 to 48 hours of payment confirmation."
    }
  ];

  return (
    <div className="space-y-12 py-6 max-w-5xl mx-auto animate-page-in">
      
      {/* Hero Header with Cyber Matrix Background */}
      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-2xl text-center relative overflow-hidden border border-emerald-500/30">
        <CyberMatrixHoloBackground />

        <div className="relative z-10 space-y-3">
          <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
            <PenTool className="w-3.5 h-3.5" /> GUEST POSTING & SPONSORED ARTICLES
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
            <BouncyText text="Write For Run On Console" />
          </h1>
          <p className="text-emerald-100 text-xs sm:text-base leading-relaxed max-w-2xl mx-auto">
            Share your gaming expertise, promote your hardware, and get a permanent backlink to your site (marked rel="sponsored", as Google requires for paid posts). Paid editorial publishing starting from <strong className="text-white underline">$50</strong>.
          </p>
        </div>
      </div>

      {/* 3 Paid Publishing Packages with 3D Tilt & Mouse-Follow Spotlight */}
      <section className="space-y-6">
        <div className="text-center max-w-xl mx-auto space-y-2">
          <div className="inline-flex items-center gap-2 bg-emerald-800 text-white text-xs font-display font-extrabold uppercase px-5 py-1.5 rounded-full border border-emerald-500/40 shadow-sm">
            <Award className="w-3.5 h-3.5 text-emerald-300" />
            <BouncyText text="EDITORIAL PRICING TIERS" />
          </div>
          <h2 className="font-display font-extrabold text-2xl sm:text-3xl text-emerald-950">
            <BouncyText text="Publishing Packages & Pricing" />
          </h2>
          <p className="text-xs sm:text-sm text-emerald-800 font-semibold">
            Choose the editorial placement package that fits your brand goals.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {publishingPackages.map((pkg) => {
            const isSelected = selectedPlan.includes(pkg.price);

            return (
              <Tilt3DCard 
                key={pkg.id}
                onClick={() => setSelectedPlan(`${pkg.name} (${pkg.price})`)}
                className={`bg-white border-2 rounded-3xl p-6 sm:p-7 flex flex-col justify-between cursor-pointer transition-all shadow-md ${
                  isSelected 
                    ? 'border-emerald-500 ring-2 ring-emerald-500/40 shadow-xl' 
                    : 'border-emerald-500/20 hover:border-emerald-400'
                }`}
              >
                <div>
                  <div className="flex justify-between items-start mb-2">
                    <span className="bg-emerald-800 text-white text-[10px] font-extrabold px-3 py-1 rounded-full uppercase tracking-wider shadow-xs">
                      {pkg.badge}
                    </span>
                    <span className="font-display font-extrabold text-3xl text-emerald-950">
                      {pkg.price}
                    </span>
                  </div>

                  <h3 className="font-display font-bold text-lg text-emerald-950 mt-2 mb-1">
                    <BouncyText text={pkg.name} />
                  </h3>
                  <p className="text-xs text-slate-500 mb-4 leading-relaxed">
                    {pkg.desc}
                  </p>

                  <ul className="space-y-2.5 border-t border-emerald-100 pt-4 text-xs text-slate-700">
                    {pkg.features.map((f, i) => (
                      <li key={i} className="flex items-start gap-2 font-medium">
                        <Check className="w-3.5 h-3.5 text-emerald-600 shrink-0 mt-0.5" />
                        <span>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>

                <div className="pt-6">
                  <button
                    type="button"
                    className={`w-full py-3 px-4 rounded-xl text-xs font-display font-extrabold uppercase tracking-wider transition-all shadow-sm ${
                      isSelected
                        ? 'bg-emerald-800 text-white shadow-md border border-emerald-400'
                        : 'bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-emerald-900 border border-slate-200'
                    }`}
                  >
                    {isSelected ? '✓ Selected Package' : 'Select Plan'}
                  </button>
                </div>

              </Tilt3DCard>
            );
          })}
        </div>
      </section>

      {/* Submission Form / Party Popper Celebration Card */}
      {submitted ? (
        <SubmissionSuccessCard 
          formType="write-for-us"
          userName={submittedData?.name}
          referenceId={submittedData?.id}
          onReset={() => {
            setSubmitted(false);
            setName('');
            setEmail('');
            setContact('');
            setTitle('');
            setPitch('');
          }}
        />
      ) : (
        <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-10 shadow-sm space-y-6">
          
          <div className="border-b border-emerald-100 pb-4">
            <div className="flex items-center gap-2 text-emerald-700 mb-1">
              <Mail className="w-5 h-5 text-emerald-600" />
              <span className="font-display font-extrabold text-xs uppercase tracking-wider">EDITORIAL SUBMISSION FORM</span>
            </div>
            <h2 className="font-display font-extrabold text-2xl sm:text-3xl text-emerald-950">
              <BouncyText text="Submit Your Article Pitch" />
            </h2>
            <p className="text-xs sm:text-sm text-slate-600 font-medium">
              Fill in your contact details and article outline. Our editorial team receives submissions directly at <strong className="text-emerald-700">editorial@runonconsole.com</strong>.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5 text-xs sm:text-sm relative">
            
            {/* Left empty by people; bots fill it in. */}
            <input type="text" name="website" value={website} onChange={(e) => setWebsite(e.target.value)} tabIndex={-1} autoComplete="off" aria-hidden="true" style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }} />

            {/* Selected Package Banner */}
            <div className="bg-emerald-50/70 border border-emerald-200 rounded-2xl p-4 flex items-center justify-between">
              <div>
                <span className="text-[10px] font-bold text-emerald-800 uppercase block">Selected Publishing Plan:</span>
                <span className="font-display font-extrabold text-emerald-950 text-sm">{selectedPlan}</span>
              </div>
              <span className="text-xs text-emerald-800 font-bold bg-white px-2.5 py-1 rounded-lg border border-emerald-200">Starting from $50</span>
            </div>

            {/* Author Details */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label className="block text-emerald-950 font-bold mb-1">AUTHOR / BRAND NAME *</label>
                <input 
                  type="text"
                  required
                  placeholder="e.g. Jordan Belfort"
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                />
              </div>

              <div>
                <label className="block text-emerald-950 font-bold mb-1">EMAIL ADDRESS *</label>
                <input 
                  type="email"
                  required
                  placeholder="e.g. jordan@techbrand.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                />
              </div>

              <div>
                <label className="block text-emerald-950 font-bold mb-1">CONTACT / PHONE NUMBER *</label>
                <input 
                  type="text"
                  required
                  placeholder="e.g. +1 (555) 234-5678"
                  value={contact}
                  onChange={(e) => setContact(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                />
              </div>
            </div>

            {/* Niche & Title */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-emerald-950 font-bold mb-1">NICHE / CATEGORY *</label>
                <select
                  value={niche}
                  onChange={(e) => setNiche(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
                >
                  <option value="Esports Hardware & Accessories">Esports Hardware (Mice, Keyboards, Audio)</option>
                  <option value="PC Builds & GPU Benchmarks">PC Builds, GPUs & Hardware Optimization</option>
                  <option value="Console Gaming & Handhelds">Console Gaming (PS5, Xbox, PSP/Handhelds)</option>
                  <option value="Game Guides & Walkthroughs">Game Launch Guides & Walkthroughs</option>
                  <option value="Gaming Chairs & Ergonomics">Gaming Chairs & Ergonomics</option>
                  <option value="Streaming & Studio Gear">Streaming, Microphones & Capture Cards</option>
                </select>
              </div>

              <div>
                <label className="block text-emerald-950 font-bold mb-1">PROPOSED ARTICLE TITLE *</label>
                <input 
                  type="text"
                  required
                  placeholder="e.g. Top 5 Wireless Keyboards for FPS Pros"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
                />
              </div>
            </div>

            {/* Pitch / Outline */}
            <div>
              <label className="block text-emerald-950 font-bold mb-1">ARTICLE PITCH & OUTLINE (With Backlink URLs) *</label>
              <textarea 
                required
                rows={4}
                placeholder="Describe your article structure, target keywords, word count, and the backlink URL you want inserted..."
                value={pitch}
                onChange={(e) => setPitch(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
              ></textarea>
            </div>

            {error && <p role="alert" className="text-rose-700 bg-rose-50 border border-rose-200 rounded-xl p-3 font-semibold">{error}</p>}
            <button 
              type="submit"
              disabled={sending}
              className="w-full bg-emerald-800 hover:bg-emerald-900 disabled:opacity-60 text-white font-display font-extrabold text-sm py-4 px-6 rounded-xl shadow-lg border border-emerald-500/40 flex items-center justify-center gap-2 transition-all"
            >
              <Send className="w-4 h-4 text-emerald-300" />
              <span>{sending ? 'Sending…' : 'Submit Article Pitch'}</span>
            </button>

          </form>
        </Tilt3DCard>
      )}

      {/* Advertising & partnerships (this page replaced /partnerships/) */}
      <section className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
        <div className="space-y-2">
          <h2 className="font-display font-extrabold text-xl sm:text-2xl text-emerald-950">Advertising &amp; brand partnerships</h2>
          <p className="text-sm text-slate-600 leading-relaxed">
            Want to send a product for review, sponsor an article or run a campaign on Run On Console? Email us with
            your brand, the product or campaign and your timing. Sponsored content is always labelled as sponsored.
          </p>
        </div>
        <div className="space-y-3">
          <a href="mailto:comments@runonconsole.com" className="flex items-center gap-3 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 no-underline hover:bg-emerald-100">
            <Mail className="w-5 h-5 text-emerald-700 shrink-0" />
            <span>
              <span className="block text-xs font-bold text-emerald-950 uppercase tracking-wider">Partnerships &amp; articles</span>
              <span className="block text-sm font-extrabold text-emerald-700">comments@runonconsole.com</span>
            </span>
          </a>
          <a href="/contact/" className="block text-xs font-bold text-emerald-700 underline">Other questions? Use the contact page →</a>
        </div>
      </section>

      {/* Guest Posting FAQs with Mouse-tracking Spotlight and Green Header */}
      <FAQSection 
        faqs={guestFaqs}
        title="Guest Posting & Sponsored Guidelines FAQs"
        subtitle="Frequently asked questions regarding editorial acceptance, turnaround times, and payment."
      />

    </div>
  );
};
