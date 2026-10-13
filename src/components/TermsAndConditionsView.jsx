import React from 'react';
import { ShieldCheck, FileText, UserCheck, ShoppingCart, MessageSquare, Gauge, Scale, Mail } from 'lucide-react';

/* Terms of use, in the site's own light green style (same layout as the Privacy Policy). */
const Section = ({ icon: Icon, title, children }) => (
  <section className="space-y-3 pt-6 border-t border-slate-100 first:pt-0 first:border-t-0">
    <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
      <Icon className="w-5 h-5 text-emerald-600 shrink-0" />
      {title}
    </h2>
    <div className="space-y-3 text-slate-600">{children}</div>
  </section>
);

export const TermsAndConditionsView = () => {
  return (
    <div className="space-y-10 py-6 max-w-4xl mx-auto text-slate-800">

      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-xl text-center">
        <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider inline-flex items-center gap-1.5">
          <FileText className="w-3.5 h-3.5" /> Terms of use
        </span>
        <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
          Terms &amp; Conditions
        </h1>
        <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-xl mx-auto">
          The rules for using runonconsole.com, its accounts, the compatibility checker and our affiliate links.
        </p>
        <p className="text-emerald-200/80 text-[11px] mt-3">Last updated: October 7, 2026</p>
      </div>

      <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8 text-xs sm:text-sm leading-relaxed">

        <Section icon={ShieldCheck} title="1. Accepting these terms">
          <p>
            By using Run On Console (“ROC”, “we”, “us”) at runonconsole.com you agree to these terms and to our{' '}
            <a href="/privacy-policy/" className="text-emerald-700 font-semibold underline">Privacy Policy &amp; Affiliate Disclosure</a>.
            If you do not agree, please do not use the website.
          </p>
        </Section>

        <Section icon={UserCheck} title="2. Accounts">
          <p>
            Some features, such as your gaming profile and comments, need a free account with a unique username
            and a verified email address. Keep your password private; you are responsible for what happens under
            your account. We may suspend accounts that break these terms or use disposable email addresses.
          </p>
        </Section>

        <Section icon={ShoppingCart} title="3. Affiliate links and prices">
          <p>
            Product pages link to stores such as Amazon. As an Amazon Associate we earn from qualifying purchases,
            at no extra cost to you. These links are marked <code className="text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">rel="sponsored"</code>.
          </p>
          <p>
            Prices, stock and product details are set by the store and can change at any time. The price shown on
            the store when you buy is the one that applies. Please check the details on the store before buying.
          </p>
        </Section>

        <Section icon={Gauge} title="4. The compatibility checker">
          <p>
            The “Can I run it?” checker compares the parts you choose with each game’s published system
            requirements (for many games, the requirements on its Steam store page). The result is a guide, not a
            guarantee: real performance also depends on drivers, settings, updates and your exact hardware.
          </p>
        </Section>

        <Section icon={MessageSquare} title="5. Your content and conduct">
          <p>
            You are responsible for what you post (comments, profile text, messages and article pitches). Do not post
            anything illegal, abusive, misleading or that you do not have the right to share, and do not try to
            break, overload or gain unauthorised access to the website. We may remove content or accounts that
            break these rules.
          </p>
        </Section>

        <Section icon={FileText} title="6. Our content">
          <p>
            Text, images and design on this website belong to Run On Console or to their owners (for example product
            photos from stores and brands). You may link to our pages and quote short parts with a link back; please
            ask us before copying more.
          </p>
        </Section>

        <Section icon={Scale} title="7. Limitation of liability">
          <p>
            The website is provided “as is”. We work to keep information accurate and up to date, but we do not
            guarantee that every detail is complete or correct, and we are not responsible for third-party stores
            or websites we link to. To the extent the law allows, we are not liable for losses that come from using
            the website.
          </p>
          <p>We may update these terms; the date at the top shows the latest version.</p>
        </Section>

        <Section icon={Mail} title="8. Contact">
          <p>
            Questions about these terms: email{' '}
            <a href="mailto:support@runonconsole.com" className="text-emerald-700 font-semibold underline">support@runonconsole.com</a>{' '}
            or use the <a href="/contact/" className="text-emerald-700 font-semibold underline">contact page</a>.
          </p>
        </Section>

      </div>
    </div>
  );
};
