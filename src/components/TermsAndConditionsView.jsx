import React from 'react';
import { ShieldCheck, FileText, Lock, CheckCircle2 } from 'lucide-react';

export const TermsAndConditionsView = () => {
  return (
    <div className="max-w-4xl mx-auto py-10 px-4 space-y-8 animate-page-in text-slate-200">
      <div className="bg-slate-900/90 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl backdrop-blur-md space-y-6">
        
        <div className="border-b border-slate-800 pb-6 space-y-3">
          <div className="inline-flex items-center gap-2 bg-emerald-500/10 text-emerald-400 text-xs font-bold px-3 py-1 rounded-full border border-emerald-500/30 uppercase tracking-wider">
            <FileText className="w-4 h-4" /> Legal Policy & Governance
          </div>
          <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-white">
            Terms of Service & Conditions
          </h1>
          <p className="text-xs text-slate-400">
            Last Updated: August 30, 2026 • Official Platform Rules for Run On Console (ROC)
          </p>
        </div>

        <div className="space-y-6 text-sm text-slate-300 leading-relaxed">
          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">1. Acceptance of Terms</h2>
            <p>
              By accessing, browsing, or registering an account on <strong>Run On Console</strong>, you agree to be bound by these Terms of Service, all applicable laws, and regulations. If you do not agree with any of these terms, you are prohibited from using or accessing this site.
            </p>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">2. User Accounts & Verification</h2>
            <p>
              To access community features—such as submitting product reviews, rating gaming hardware, saving custom PC specs, and commenting on blog guides—you must register a valid gamer account with a unique username and verified email address. Accounts registered under temporary or disposable emails are subject to immediate suspension.
            </p>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">3. Hardware Reviews & Affiliate Transparency</h2>
            <p>
              Run On Console provides independent, data-driven hardware reviews and compatibility testing. We participate in affiliate marketing programs. When you click merchant links (e.g. Amazon, Best Buy, Newegg) through our site, we may earn an affiliate commission at no additional cost to you. All affiliate links carry strict <code className="text-emerald-400 bg-slate-950 px-2 py-0.5 rounded">rel="nofollow sponsored noopener noreferrer"</code> attributes.
            </p>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">4. User Content & Prohibited Conduct</h2>
            <p>
              Users are solely responsible for content posted in comments and submissions. You agree not to upload malicious scripts, attempt unauthorized database access, engage in rate-limit abuse, or post defamatory content. We reserve the right to remove any content or terminate accounts violating these standards.
            </p>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">5. Limitation of Liability</h2>
            <p>
              The materials on Run On Console are provided on an 'as is' basis. We make no warranties, expressed or implied, regarding 100% accuracy of third-party hardware pricing or merchant stock levels.
            </p>
          </section>

          <section className="space-y-2 border-t border-slate-800 pt-4">
            <h2 className="font-display font-bold text-lg text-white">Contact & Support</h2>
            <p>
              If you have questions regarding these Terms, please contact our legal governance team at <a href="mailto:support@runonconsole.com" className="text-emerald-400 hover:underline">support@runonconsole.com</a>.
            </p>
          </section>
        </div>

      </div>
    </div>
  );
};
