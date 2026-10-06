import React from 'react';
import { ShieldCheck, Lock, Eye, CheckCircle2 } from 'lucide-react';

export const PrivacyPolicyView = () => {
  return (
    <div className="max-w-4xl mx-auto py-10 px-4 space-y-8 animate-page-in text-slate-200">
      <div className="bg-slate-900/90 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl backdrop-blur-md space-y-6">
        
        <div className="border-b border-slate-800 pb-6 space-y-3">
          <div className="inline-flex items-center gap-2 bg-emerald-500/10 text-emerald-400 text-xs font-bold px-3 py-1 rounded-full border border-emerald-500/30 uppercase tracking-wider">
            <Lock className="w-4 h-4" /> Data Privacy & Protection
          </div>
          <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-white">
            Privacy Policy
          </h1>
          <p className="text-xs text-slate-400">
            Effective Date: August 30, 2026 • Data Protection Governance for Run On Console (ROC)
          </p>
        </div>

        <div className="space-y-6 text-sm text-slate-300 leading-relaxed">
          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">1. Information We Collect</h2>
            <p>
              We collect minimal data required to provide a secure gaming hardware experience:
            </p>
            <ul className="list-disc pl-5 space-y-1 text-xs text-slate-300">
              <li><strong>Account Credentials</strong>: Display name, unique username, email address, and cryptographically hashed passwords (Argon2id/bcrypt).</li>
              <li><strong>OAuth Profiles</strong>: When signing in with Google, we store your verified Google email and provider ID (<code className="text-emerald-400">sub</code>). We never request or store your Google password.</li>
              <li><strong>Security Logs</strong>: IP addresses and user agents are logged in security tables to prevent brute-force attacks and rate-limit abuse.</li>
            </ul>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">2. How We Use Your Information</h2>
            <p>
              Your data is strictly used to authenticate your session, verify account ownership, deliver transactional email challenges (verification & password resets), and maintain custom PC spec wishlists. We <strong>never sell or rent</strong> personal data to third-party advertisers.
            </p>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">3. Cookies & Session Security</h2>
            <p>
              We use secure, server-side HTTP-only cookies with <code className="text-emerald-400 bg-slate-950 px-2 py-0.5 rounded">SameSite=Lax</code> and <code className="text-emerald-400 bg-slate-950 px-2 py-0.5 rounded">Secure</code> flags. Authentication tokens are never stored in browser <code className="text-slate-400">localStorage</code> or exposed to client-side scripts.
            </p>
          </section>

          <section className="space-y-2">
            <h2 className="font-display font-bold text-lg text-white">4. CAPTCHA Security Verification</h2>
            <p>
              We utilize Cloudflare Turnstile to protect signup forms against automated abuse. Turnstile evaluates security risks without relying on intrusive tracking cookies or personal data profiling.
            </p>
          </section>

          <section className="space-y-2 border-t border-slate-800 pt-4">
            <h2 className="font-display font-bold text-lg text-white">Data Rights & Contact</h2>
            <p>
              You have the right to request access to or deletion of your personal account data at any time. Please email our Data Protection Officer at <a href="mailto:support@runonconsole.com" className="text-emerald-400 hover:underline">support@runonconsole.com</a>.
            </p>
          </section>
        </div>

      </div>
    </div>
  );
};
