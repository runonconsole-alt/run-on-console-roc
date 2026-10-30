import React from 'react';
import { FAQSection } from './FAQSection';

const LINKEDIN = 'https://www.linkedin.com/in/omar-abo-bakar-21ot02/';

const AUTHOR_FAQS = [
  { q: 'Who writes Run On Console?', a: 'Omar Abobakar, the founder and owner of Run On Console, is the editor: he signs the blogs, guides and news, and checks the facts, the links and the game requirements before anything goes live.' },
  { q: 'How are products chosen?', a: 'From their specifications, the maker\'s own data and well-known published reviews. Each pick says who it suits and who it does not. Our product picks and rankings cannot be bought, and we never invent prices: product pages link to Amazon for the current price.' },
  { q: 'Where do the game requirements come from?', a: 'From the publishers: the official minimum and recommended specs on Steam for PC games. The Can I run checker compares them with your graphics card, processor and RAM.' },
  { q: 'Is any content sponsored?', a: 'A brand can pay for a sponsored article. Every one is labelled Sponsored at the top, its links are rel=sponsored, as Google asks, and payment never buys a place in our picks or rankings. Product links to Amazon are affiliate links: we may earn a small commission at no extra cost to you.' },
  { q: 'How do I report a mistake?', a: 'Use the contact page or write to support@runonconsole.com with the page address. We check every report and correct the page.' },
];

import { CheckCircle2, ArrowRight } from 'lucide-react';

/** /author/omar-abobakar/ — Omar Abobakar, founder and editor (Deploy 149; was the ROC Team page). */
export const AuthorView = () => {
  return (
    <div className="max-w-4xl mx-auto py-8 space-y-10 animate-fade-in">

      <nav aria-label="Breadcrumb" className="text-xs text-slate-500 font-medium">
        <ol className="flex items-center gap-2 list-none p-0 m-0">
          <li>
            <a href="/" className="text-emerald-600 hover:underline">Home</a>
            <span className="ml-2 text-slate-300">/</span>
          </li>
          <li>
            <a href="/about/" className="text-emerald-600 hover:underline">About</a>
            <span className="ml-2 text-slate-300">/</span>
          </li>
          <li className="text-slate-900 font-bold">Omar Abobakar</li>
        </ol>
      </nav>

      <div className="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 text-white rounded-3xl p-6 sm:p-10 shadow-2xl border border-emerald-500/30 flex flex-col md:flex-row items-center gap-8">
        <img src="/images/omar-abobakar.webp" alt="Omar Abobakar" width="160" height="160" className="w-32 h-32 sm:w-40 sm:h-40 rounded-3xl object-cover shrink-0 shadow-xl border-2 border-emerald-500/40" />
        <div className="space-y-3 text-center md:text-left flex-1">
          <span className="inline-block bg-emerald-500 text-slate-950 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">
            Founder &amp; editor
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-white">Omar Abobakar</h1>
          <p className="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl">
            Omar Abobakar founded Run On Console in 2026 and owns and edits it. He writes and checks the blogs, guides and news,
            keeps the gaming gear picks and the game requirements checker up to date, and answers the messages readers send.
          </p>
          <a href={LINKEDIN} target="_blank" rel="noopener me" className="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20 text-white font-bold text-xs py-2 px-4 rounded-xl no-underline">
            Omar on LinkedIn <ArrowRight className="w-3.5 h-3.5" />
          </a>
        </div>
      </div>

      <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h2 className="font-display font-extrabold text-xl text-slate-900">What Omar writes about</h2>
        <ul className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 list-none p-0 m-0">
          {[
            'Gaming mice, keyboards and headsets',
            'Gaming monitors and graphics cards',
            'PC game requirements',
            'Handheld gaming PCs',
            'Consoles and gaming platforms',
            'Buying guides for every budget',
          ].map((topic) => (
            <li key={topic} className="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-2 text-xs font-bold text-slate-800">
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>{topic}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-3">
        <h2 className="font-display font-extrabold text-xl text-slate-900">How he works</h2>
        <p className="text-sm text-slate-600 leading-relaxed">
          Products are picked from their specifications, the maker&apos;s own data and well-known published reviews, with who each one is for.
          Game requirements come from the publishers (Steam for PC games). Our product picks and rankings cannot be bought; a brand can pay for a
          sponsored article, and every one is labelled Sponsored. Found a mistake? Tell us on the <a href="/contact/" className="font-bold text-emerald-700">contact page</a>.
        </p>
        <div className="flex flex-wrap gap-3 pt-1">
          <a href="/blogs/" className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-5 rounded-xl inline-flex items-center gap-1.5 no-underline">
            Read his articles <ArrowRight className="w-3.5 h-3.5" />
          </a>
          <a href="/about/" className="bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold text-xs py-2.5 px-5 rounded-xl no-underline">
            About Run On Console
          </a>
        </div>
      </section>

      <FAQSection faqs={AUTHOR_FAQS} title="About Omar and Run On Console" subtitle="Who writes Run On Console and how." />

    </div>
  );
};
