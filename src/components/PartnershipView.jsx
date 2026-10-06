import React, { useState } from 'react';
import { 
  Megaphone, Handshake, DollarSign, Target, TrendingUp, 
  Users, CheckCircle2, Send, Sparkles, ShieldCheck, Mail, Phone, Briefcase 
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { SubmissionSuccessCard } from './SubmissionSuccessCard';

export const PartnershipView = () => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [entityType, setEntityType] = useState('Agency / PR Representative');
  const [queryType, setQueryType] = useState('Sponsorship & Display Advertising');
  const [budget, setBudget] = useState('$1,000 – $5,000');
  const [proposal, setProposal] = useState('');
  const [submitted, setSubmitted] = useState(false);
  const [submittedRef, setSubmittedRef] = useState('');

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!name || !email || !phone || !proposal) {
      alert("Please fill in all required fields!");
      return;
    }

    const ref = 'PARTNER-' + Date.now().toString().slice(-6);
    setSubmittedRef(ref);
    setSubmitted(true);
  };

  const adPillars = [
    {
      icon: Target,
      title: "HIGH-INTENT GAMING AUDIENCE",
      desc: "Our readers aren't just casual viewers—they are actively researching specs, comparing benchmarks, and buying hardware."
    },
    {
      icon: TrendingUp,
      title: "OMNICHANNEL SPONSORSHIP",
      desc: "Multi-touchpoint exposure across homepage buy-boxes, dedicated buyer guides, category takeovers, and email newsletters."
    },
    {
      icon: ShieldCheck,
      title: "LAB TESTED CREDIBILITY",
      desc: "Partner with an independent review publication trusted for transparent testing, oscilloscope latency graphs, and zero fluff."
    }
  ];

  const partnershipFaqs = [
    {
      q: "What advertising formats does Run On Console offer?",
      a: "We offer IAB standard display banners (Leaderboards, Medium Rectangles), native sponsored buying guide inclusions, dedicated benchmark test features, and newsletter sponsorships."
    },
    {
      q: "Can hardware brands sponsor a lab review?",
      a: "Yes. Brands can sponsor dedicated testing of their peripherals or components. However, all latency, sensor, and performance test results remain 100% objective and transparent according to our editorial standards."
    },
    {
      q: "What is your monthly reader reach?",
      a: "Run On Console reaches over 500,000+ unique monthly visitors across North America, Europe, and Asia-Pacific, with average reader sessions over 4 minutes."
    },
    {
      q: "How quickly can a custom campaign launch?",
      a: "Standard display banners and newsletter placements can go live within 24–48 hours. Custom benchmark reviews and dedicated guide takeovers typically require 5–7 business days for lab testing and proofing."
    }
  ];

  return (
    <div className="space-y-12 py-6 max-w-5xl mx-auto animate-page-in">
      
      {/* High-Impact Hero Banner with Cyber Matrix Background */}
      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-2xl text-center relative overflow-hidden border border-emerald-500/30">
        <CyberMatrixHoloBackground />

        <div className="relative z-10 space-y-3">
          <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
            <Megaphone className="w-3.5 h-3.5" /> COMMERCIAL ADVERTISING & BRAND ALLIANCES
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
            <BouncyText text="Advertising & Brand Partnerships" />
          </h1>
          <p className="text-emerald-100 text-xs sm:text-base leading-relaxed max-w-2xl mx-auto">
            Connect your hardware, game studio, or esports peripheral brand with over <strong className="text-white">500,000+ active gamers</strong> seeking verified benchmarks and buying recommendations.
          </p>
        </div>
      </div>

      {/* 3 Strategic Pillars with 3D Tilt Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        {adPillars.map((p, idx) => {
          const Icon = p.icon;
          return (
            <Tilt3DCard
              key={idx}
              className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-7 shadow-sm space-y-3 hover:border-emerald-400 transition-all cursor-pointer"
            >
              <div className="w-12 h-12 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md shadow-emerald-500/20">
                <Icon className="w-6 h-6 animate-pulse" />
              </div>
              <h3 className="font-display font-bold text-base text-emerald-950">
                <BouncyText text={p.title} />
              </h3>
              <p className="text-xs text-slate-600 leading-relaxed">
                {p.desc}
              </p>
            </Tilt3DCard>
          );
        })}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* Left Proposal Form / Submission Success Card */}
        <div className="lg:col-span-7">
          {submitted ? (
            <SubmissionSuccessCard 
              formType="partnership"
              userName={name}
              referenceId={submittedRef}
              onReset={() => {
                setSubmitted(false);
                setName('');
                setEmail('');
                setPhone('');
                setProposal('');
              }}
            />
          ) : (
            <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
              
              <div className="border-b border-emerald-100 pb-3">
                <div className="flex items-center gap-2 text-emerald-700 mb-1">
                  <Handshake className="w-5 h-5 text-emerald-600" />
                  <span className="font-display font-extrabold text-xs uppercase tracking-wider">COMMERCIAL PROPOSAL DESK</span>
                </div>
                <h2 className="font-display font-extrabold text-xl sm:text-2xl text-emerald-950">
                  <BouncyText text="Submit Partnership Proposal" />
                </h2>
                <p className="text-xs text-slate-600 font-medium">
                  Direct commercial line for agencies, publishers, and hardware manufacturers.
                </p>
              </div>

              <form onSubmit={handleSubmit} className="space-y-4 text-xs sm:text-sm">
                
                {/* Name & Email */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-emerald-950 font-bold mb-1">FULL NAME *</label>
                    <input 
                      type="text"
                      required
                      placeholder="e.g. Marcus Sterling"
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                    />
                  </div>

                  <div>
                    <label className="block text-emerald-950 font-bold mb-1">CORPORATE / BUSINESS EMAIL *</label>
                    <input 
                      type="email"
                      required
                      placeholder="e.g. marcus@brandagency.com"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                    />
                  </div>
                </div>

                {/* Phone & Entity Type */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-emerald-950 font-bold mb-1">CONTACT / PHONE NUMBER *</label>
                    <input 
                      type="text"
                      required
                      placeholder="e.g. +1 (555) 890-1234"
                      value={phone}
                      onChange={(e) => setPhone(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                    />
                  </div>

                  <div>
                    <label className="block text-emerald-950 font-bold mb-1">ENTITY TYPE *</label>
                    <select
                      value={entityType}
                      onChange={(e) => setEntityType(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
                    >
                      <option value="Agency / PR Representative">Agency / PR Representative</option>
                      <option value="Hardware Manufacturer">Hardware / Peripheral Manufacturer</option>
                      <option value="Game Studio / Publisher">Game Studio / Publisher</option>
                      <option value="Individual Creator / Influencer">Individual Creator / Influencer</option>
                      <option value="E-Commerce / Affiliate Network">E-Commerce / Affiliate Network</option>
                    </select>
                  </div>
                </div>

                {/* Partnership Query Type & Budget */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-emerald-950 font-bold mb-1">PARTNERSHIP QUERY TYPE *</label>
                    <select
                      value={queryType}
                      onChange={(e) => setQueryType(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
                    >
                      <option value="Sponsorship & Display Advertising">Sponsorship & Display Advertising</option>
                      <option value="Hardware Benchmark Review Sponsorship">Hardware Benchmark Review Sponsorship</option>
                      <option value="Affiliate Network Collaboration">Affiliate Network Collaboration</option>
                      <option value="Dedicated Buyer Guide Feature">Dedicated Buyer Guide Feature</option>
                      <option value="Community Giveaway & Tournament">Community Giveaway & Tournament</option>
                      <option value="Long-Term Brand Ambassadorship">Long-Term Brand Ambassadorship</option>
                    </select>
                  </div>

                  <div>
                    <label className="block text-emerald-950 font-bold mb-1">ESTIMATED CAMPAIGN BUDGET</label>
                    <select
                      value={budget}
                      onChange={(e) => setBudget(e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
                    >
                      <option value="Under $1,000">Under $1,000</option>
                      <option value="$1,000 – $5,000">$1,000 – $5,000</option>
                      <option value="$5,000 – $15,000">$5,000 – $15,000</option>
                      <option value="$15,000+">$15,000+ (Enterprise Custom)</option>
                    </select>
                  </div>
                </div>

                {/* Proposal Details */}
                <div>
                  <label className="block text-emerald-950 font-bold mb-1">CAMPAIGN PROPOSAL & DELIVERABLES *</label>
                  <textarea 
                    required
                    rows={4}
                    placeholder="Outline your target goals, timeline, product details, and desired advertising placements..."
                    value={proposal}
                    onChange={(e) => setProposal(e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                  ></textarea>
                </div>

                <button 
                  type="submit"
                  className="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-display font-extrabold text-sm py-4 px-6 rounded-xl shadow-lg border border-emerald-500/40 flex items-center justify-center gap-2 transition-all hover:scale-[1.01]"
                >
                  <Send className="w-4 h-4 text-emerald-300" />
                  <span>Submit Campaign Proposal to Partnerships Desk</span>
                </button>

              </form>
            </Tilt3DCard>
          )}
        </div>

        {/* Right Info Cards: SPACIOUS & DE-CONGESTED */}
        <div className="lg:col-span-5 space-y-6">
          
          <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-7 sm:p-8 shadow-sm space-y-5">
            <h3 className="font-display font-extrabold text-lg sm:text-xl text-emerald-950 border-b border-emerald-100 pb-3">
              <BouncyText text="Commercial Desk Contact" />
            </h3>

            <div className="space-y-4 pt-1">
              <div className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <Mail className="w-5 h-5 animate-pulse" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Partnerships Desk</div>
                  <a href="mailto:partnerships@runonconsole.com" className="text-xs sm:text-sm text-emerald-700 font-extrabold hover:underline block mt-0.5 truncate">
                    partnerships@runonconsole.com
                  </a>
                  <span className="text-[10px] text-slate-500 block mt-0.5">Priority Business Desk</span>
                </div>
              </div>

              <div className="p-4 rounded-2xl bg-emerald-50/80 border border-emerald-200/60 flex items-start gap-4 transition-all hover:bg-emerald-100/60 shadow-xs">
                <div className="p-3 rounded-xl bg-emerald-800 text-emerald-300 shrink-0 shadow-sm">
                  <Briefcase className="w-5 h-5 animate-pulse" />
                </div>
                <div className="min-w-0">
                  <div className="font-bold text-xs text-emerald-950 uppercase tracking-wider">Media Kit & Ad Specs</div>
                  <p className="text-xs text-slate-600 font-medium leading-relaxed mt-0.5">
                    Available on request. We provide IAB standard banners, native sponsor cards, and custom takeovers.
                  </p>
                  <span className="text-[10px] text-emerald-700 font-bold block mt-0.5">Rate Card Q3/Q4 Updated</span>
                </div>
              </div>
            </div>
          </Tilt3DCard>

          <Tilt3DCard className="bg-gradient-to-tr from-[#064E3B] to-[#047857] text-white rounded-3xl p-7 sm:p-8 shadow-md space-y-3 border border-emerald-500/40">
            <div className="flex items-center gap-2 text-emerald-300 text-xs font-bold uppercase tracking-wider">
              <Sparkles className="w-4 h-4 text-emerald-400" />
              <span>Run On Console Reach</span>
            </div>
            <h4 className="font-display font-bold text-xl text-white">
              <BouncyText text="500,000+ Monthly Gamers" />
            </h4>
            <p className="text-xs text-emerald-100 leading-relaxed">
              Reach engaged PC builders, esports pros, and console gamers seeking verified reviews and hardware recommendations.
            </p>
            <div className="pt-1 flex items-center gap-3 text-xs text-emerald-200 font-semibold">
              <CheckCircle2 className="w-4 h-4 text-emerald-400 shrink-0" />
              <span>Direct Lead Generation & Verified Conversions</span>
            </div>
          </Tilt3DCard>

        </div>

      </div>

      {/* Partnership FAQs with Mouse-Tracking Spotlight */}
      <FAQSection 
        faqs={partnershipFaqs}
        title="Advertising & Brand Partnership FAQs"
        subtitle="Frequently asked questions regarding sponsorship formats, metrics, turnaround times, and pricing."
      />

    </div>
  );
};
