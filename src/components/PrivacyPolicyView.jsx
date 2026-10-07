import React from 'react';
import { ShieldCheck, Lock, Cookie, ShoppingCart, FileText, UserCheck, Mail } from 'lucide-react';

/* One page for everything legal about how the site works: privacy, cookies and analytics,
   and the Amazon Associates / affiliate disclosure. /policy/ redirects here. */
const Section = ({ icon: Icon, title, children }) => (
  <section className="space-y-3 pt-6 border-t border-slate-100 first:pt-0 first:border-t-0">
    <h2 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
      <Icon className="w-5 h-5 text-emerald-600 shrink-0" />
      {title}
    </h2>
    <div className="space-y-3 text-slate-600">{children}</div>
  </section>
);

export const PrivacyPolicyView = () => {
  return (
    <div className="space-y-10 py-6 max-w-4xl mx-auto text-slate-800">

      <div className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-12 shadow-xl text-center">
        <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider inline-flex items-center gap-1.5">
          <ShieldCheck className="w-3.5 h-3.5" /> Privacy &amp; Disclosures
        </span>
        <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white my-3">
          Privacy Policy
        </h1>
        <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-xl mx-auto">
          How Run On Console handles your data, which cookies and tools the website uses, and how we
          earn money through affiliate links, including the Amazon Associates Program.
        </p>
        <p className="text-emerald-200/80 text-[11px] mt-3">Last updated: October 7, 2026</p>
      </div>

      <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8 text-xs sm:text-sm leading-relaxed">

        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 font-semibold">
          As an Amazon Associate, Run On Console earns from qualifying purchases. Some links on this
          website are affiliate links: if you buy through them we may earn a commission, at no extra cost to you.
        </div>

        <Section icon={FileText} title="1. Who we are">
          <p>
            Run On Console (“ROC”, “we”, “us”) publishes gaming and PC hardware guides, product
            recommendations and platform compatibility information at runonconsole.com. This policy
            covers the whole website, including our blog, product pages and gaming platform pages.
          </p>
        </Section>

        <Section icon={ShoppingCart} title="2. Affiliate disclosure and the Amazon Associates Program">
          <p>
            Run On Console is a participant in the <strong>Amazon Services LLC Associates Program</strong>,
            an affiliate advertising program designed to provide a means for sites to earn advertising fees
            by advertising and linking to Amazon.com. Amazon and the Amazon logo are trademarks of
            Amazon.com, Inc. or its affiliates.
          </p>
          <p>
            “Buy on Amazon” buttons and many product links on this website are affiliate links. When you
            click one, Amazon may place a cookie in your browser so that a purchase can be credited to us.
            You pay the same price as you would without the link. Purchases themselves happen on Amazon’s
            website and are covered by <a className="text-emerald-700 font-semibold underline" href="https://www.amazon.com/gp/help/customer/display.html?nodeId=468496" target="_blank" rel="noopener noreferrer">Amazon’s privacy notice</a>;
            we never see your payment or delivery details.
          </p>
          <p>
            Product prices and availability are accurate as of the date and time indicated and are subject
            to change. Any price and availability information shown on Amazon at the time of purchase will
            apply to the purchase of the product. Product images shown on this website come from Amazon
            or from the manufacturer.
          </p>
          <p>
            We may also join other affiliate programs in the future; the same rules apply to them.
          </p>
        </Section>

        <Section icon={UserCheck} title="3. Editorial independence">
          <p>
            Affiliate commissions never decide what we recommend. Brands cannot pay for a product to be
            listed, ranked higher or described more favourably. If an article is ever sponsored, it will be
            clearly labelled as sponsored.
          </p>
        </Section>

        <Section icon={Lock} title="4. Information we collect">
          <ul className="list-disc pl-5 space-y-1.5">
            <li><strong>Messages you send us</strong>: when you use the contact or “Write for us” forms we receive your name, email address and the message, and use them only to reply.</li>
            <li><strong>Accounts</strong>: if you create an account we store your display name, username, email address and a securely hashed password. If you sign in with Google we receive your verified email address and Google account ID, never your Google password.</li>
            <li><strong>Affiliate link clicks</strong>: when you click a shop link we record which link and which page it was on, so we can see which recommendations are useful. This record does not contain your name or email address.</li>
            <li><strong>Security logs</strong>: our server keeps IP addresses and browser details for a limited time to stop spam, brute-force login attempts and other abuse.</li>
          </ul>
          <p>We do not sell or rent your personal data to anyone.</p>
        </Section>

        <Section icon={Cookie} title="5. Cookies, analytics and third-party services">
          <ul className="list-disc pl-5 space-y-1.5">
            <li><strong>Essential cookies</strong>: keep you signed in and protect forms. They are HTTP-only and secure.</li>
            <li><strong>Google Analytics and Google Tag Manager</strong>: count visits and show which pages are popular, using cookies and anonymous usage data.</li>
            <li><strong>Microsoft Clarity</strong>: shows how visitors use pages (clicks, scrolling) so we can improve the layout.</li>
            <li><strong>Cloudflare Turnstile</strong>: checks that sign-up and contact forms are used by people, not bots.</li>
            <li><strong>Amazon</strong>: sets its own cookies when you follow an affiliate link (see section 2).</li>
          </ul>
          <p>
            You can block or delete cookies in your browser settings. The website still works without
            analytics cookies, although signing in needs the essential cookie.
          </p>
        </Section>

        <Section icon={ShieldCheck} title="6. How long we keep data, and your rights">
          <p>
            We keep messages and account data only as long as needed for the purpose they were given for,
            and security logs for a limited period. You can ask us to show, correct or delete the personal
            data we hold about you, or to close your account, at any time.
          </p>
          <p>
            This website is not directed at children under 13 and we do not knowingly collect their data.
            Links to other websites are covered by those websites’ own policies.
          </p>
        </Section>

        <Section icon={Mail} title="7. Changes and contact">
          <p>
            We will update this page when the way the website works changes; the date at the top shows the
            latest version. Questions or requests about your data: email{' '}
            <a href="mailto:support@runonconsole.com" className="text-emerald-700 font-semibold underline">support@runonconsole.com</a>.
          </p>
        </Section>

      </div>
    </div>
  );
};
