import React from 'react';
import { ShieldCheck, FileText, Lock, AlertCircle } from 'lucide-react';
import { FAQSection } from './FAQSection';

export const PolicyView = () => {
  const policyFaqs = [
    {
      q: "Does clicking an affiliate link cost me more money?",
      a: "No. When you purchase through our verified links on Amazon, Best Buy, or manufacturer stores, the price you pay is identical (or discounted with our verified coupons). Retailers pay us a small referral commission for sending them traffic."
    },
    {
      q: "Can brands pay to get a higher ROC Score?",
      a: "Never. All our ROC Scores and award badges are determined solely by objective laboratory benchmark readings and acoustic/latency data. Sponsored articles are clearly demarcated as sponsored."
    },
    {
      q: "How is my contact information protected?",
      a: "Any contact details or guest submission forms sent to our editorial desk are encrypted and will never be sold to third-party marketing brokers."
    }
  ];

  return (
    <div className="space-y-10 py-6 max-w-4xl mx-auto text-slate-800">
      
      {/* Header */}
      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-xl text-center">
        <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider inline-flex items-center gap-1.5">
          <ShieldCheck className="w-3.5 h-3.5" /> LEGAL & TRANSPARENCY
        </span>
        <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
          Privacy Policy & Disclosures
        </h1>
        <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-xl mx-auto">
          Our commitment to reader transparency, data privacy, and ethical affiliate partnerships.
        </p>
      </div>

      {/* Main Policy Content Blocks */}
      <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8 text-xs sm:text-sm leading-relaxed">
        
        {/* 1. Affiliate Disclosure */}
        <section className="space-y-3">
          <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
            <ShieldCheck className="w-5 h-5 text-emerald-600" />
            1. FTC Affiliate Disclosure & Monetization
          </h2>
          <p className="text-slate-600">
            Run On Console is a reader-supported independent publication. Some of the links on this website are affiliate links, primarily through the <strong>Amazon Services LLC Associates Program</strong>, Best Buy Affiliate Network, and manufacturer partner programs.
          </p>
          <p className="text-slate-600">
            When you click on these links and complete a purchase, we may earn an affiliate commission at <strong>no extra cost to you</strong>. These referral earnings directly fund our independent hardware benchmarking equipment, oscilloscope probes, and editorial staff.
          </p>
        </section>

        {/* 2. Editorial Independence */}
        <section className="space-y-3 pt-6 border-t border-slate-100">
          <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
            <FileText className="w-5 h-5 text-emerald-600" />
            2. Editorial Independence & Testing Standards
          </h2>
          <p className="text-slate-600">
            Our editorial staff maintains absolute separation between commercial affiliate operations and hardware test scoring. Products provided as review samples by manufacturers undergo the exact same rigorous testing methodology as retail units purchased off the shelf. We never accept payment in exchange for favorable reviews or higher performance scores.
          </p>
        </section>

        {/* 3. Privacy Policy */}
        <section className="space-y-3 pt-6 border-t border-slate-100">
          <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
            <Lock className="w-5 h-5 text-emerald-600" />
            3. Privacy Policy & Data Protection
          </h2>
          <p className="text-slate-600">
            When you use our Contact Form or Write For Us guest post portal, we collect your name, email address, phone number, and submission details solely to communicate regarding your request. We do not sell, lease, or distribute your personal data to any third-party advertisers.
          </p>
          <p className="text-slate-600">
            We use privacy-friendly analytics and standard web cookies to track aggregate traffic trends and improve website usability across desktop and mobile devices.
          </p>
        </section>

        {/* 4. Terms of Service */}
        <section className="space-y-3 pt-6 border-t border-slate-100">
          <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
            <AlertCircle className="w-5 h-5 text-emerald-600" />
            4. Terms of Service & Copyright
          </h2>
          <p className="text-slate-600">
            All proprietary review content, laboratory benchmark graphs, and editorial articles on Run On Console are protected by international copyright laws. Reproduction without written consent is strictly prohibited.
          </p>
        </section>

      </div>

      {/* Policy FAQs */}
      <FAQSection 
        faqs={policyFaqs}
        title="Privacy & Affiliate Policy FAQs"
        subtitle="Common questions regarding our monetization, disclosures, and data protection."
      />

    </div>
  );
};
