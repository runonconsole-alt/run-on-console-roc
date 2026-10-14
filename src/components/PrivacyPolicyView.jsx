import React from 'react';
import { ShieldCheck, Lock, Cookie, ShoppingCart, FileText, UserCheck, Mail, Megaphone, Bot } from 'lucide-react';
import { CONTACT } from '../data/siteContact';

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
          earn money through affiliate links (Amazon Associates) and advertising (Google AdSense).
        </p>
        <p className="text-emerald-200/80 text-[11px] mt-3">Last updated: October 14, 2026</p>
      </div>

      <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm space-y-8 text-xs sm:text-sm leading-relaxed">

        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900 font-semibold">
          As an Amazon Associate, Run On Console earns from qualifying purchases. Some links on this
          website are affiliate links: if you buy through them we may earn a commission, at no extra cost to you.
        </div>

        <Section icon={FileText} title="1. Who we are">
          <p>
            Run On Console (“ROC”, “we”, “us”) publishes gaming and PC hardware guides, product
            recommendations, news and platform compatibility information at runonconsole.com. This policy
            covers the whole website, including our blog, news, product pages, gaming platform pages and
            the ROC assistant chat.
          </p>
          <p>
            Run On Console, {CONTACT.address}. Email{' '}
            <a href={'mailto:' + CONTACT.email} className="text-emerald-700 font-semibold underline">{CONTACT.email}</a>, phone{' '}
            <a href={CONTACT.tel} className="text-emerald-700 font-semibold underline">{CONTACT.phone}</a>.
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
            We do not show prices on this website. The price and availability shown on Amazon at the time
            of purchase apply to the purchase of the product. Product images on this website come from
            Amazon, from the manufacturer or are our own.
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
            <li><strong>ROC assistant chats</strong>: see section 7.</li>
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
            <li><strong>Google AdSense</strong>: shows advertisements (see section 6).</li>
          </ul>
          <p>
            You can block or delete cookies in your browser settings. The website still works without
            analytics and advertising cookies, although signing in needs the essential cookie.
          </p>
        </Section>

        <Section icon={Megaphone} title="6. Advertising (Google AdSense)">
          <p>
            We use Google AdSense to show advertisements on this website. Third-party vendors, including
            Google, use cookies to serve ads based on your prior visits to this website or other websites.
            Google’s use of advertising cookies enables it and its partners to serve ads to you based on your
            visits to this and/or other sites on the Internet.
          </p>
          <p>
            You can opt out of personalised advertising in{' '}
            <a className="text-emerald-700 font-semibold underline" href="https://adssettings.google.com" target="_blank" rel="noopener noreferrer">Google Ads Settings</a>,
            or opt out of some third-party vendors’ use of cookies for personalised advertising at{' '}
            <a className="text-emerald-700 font-semibold underline" href="https://www.aboutads.info/choices/" target="_blank" rel="noopener noreferrer">www.aboutads.info</a>{' '}
            (<a className="text-emerald-700 font-semibold underline" href="https://www.youronlinechoices.eu/" target="_blank" rel="noopener noreferrer">youronlinechoices.eu</a> in Europe).
            How Google uses this data:{' '}
            <a className="text-emerald-700 font-semibold underline" href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener noreferrer">How Google uses information from sites that use its services</a>.
          </p>
          <p>
            Visitors in the European Economic Area, the United Kingdom and Switzerland are asked for consent
            before personalised ads are shown; without consent, Google shows non-personalised ads.
          </p>
        </Section>

        <Section icon={Bot} title="7. The ROC assistant (chat)">
          <p>
            The “Ask ROC” chat answers questions about games and gaming gear. Your question is sent to our
            server and to Groq, the AI service that writes the wording of the answer; the facts come from this
            website (our game list and products).
          </p>
          <p>
            ROC is for signed-in members. We save the questions and answers with your account to improve the
            assistant and to stop abuse. Before saving, email addresses, phone and ID numbers and names are removed
            from the text. We do not save your IP address, only a code that changes every day. Pictures you send are
            read once and not saved. Chats are deleted after 90 days. Please do not type personal details in the chat.
          </p>
          <p>
            If you tell ROC your PC parts (or upload a dxdiag report, of which only the processor, memory, graphics card,
            system model and Windows lines are sent), ROC keeps those PC specs with your account so you do not have to repeat
            them. Say “forget my PC” in the chat to delete them. Your 👍 / 👎 on answers is saved to improve ROC.
          </p>
        </Section>

        <Section icon={ShieldCheck} title="8. How long we keep data, and your rights">
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

        <Section icon={Mail} title="9. Changes and contact">
          <p>
            We will update this page when the way the website works changes; the date at the top shows the
            latest version. Questions or requests about your data: email{' '}
            <a href={'mailto:' + CONTACT.email} className="text-emerald-700 font-semibold underline">{CONTACT.email}</a>,
            call <a href={CONTACT.tel} className="text-emerald-700 font-semibold underline">{CONTACT.phone}</a> or
            write to Run On Console, {CONTACT.address}.
          </p>
        </Section>

      </div>
    </div>
  );
};
