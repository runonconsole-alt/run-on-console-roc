import React from 'react';
import { CheckCircle2, ArrowRight } from 'lucide-react';

/** /author/roc-team/ — the team behind Run On Console (author of the blog posts). */
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
          <li className="text-slate-900 font-bold">ROC</li>
        </ol>
      </nav>

      <div className="bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 text-white rounded-3xl p-6 sm:p-10 shadow-2xl border border-emerald-500/30 flex flex-col md:flex-row items-center gap-8">
        <img src="/images/logo-512.png" alt="Run On Console logo" width="144" height="144" className="w-28 h-28 sm:w-36 sm:h-36 rounded-3xl shrink-0 shadow-xl" />
        <div className="space-y-3 text-center md:text-left flex-1">
          <span className="inline-block bg-emerald-500 text-slate-950 text-xs font-extrabold px-3 py-1 rounded-full uppercase tracking-wider">
            Run On Console
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-white">ROC (Run On Console)</h1>
          <p className="text-xs sm:text-sm text-slate-300 leading-relaxed max-w-2xl">
            ROC (Run On Console) is run by a small, independent group of gamers. We write the guides on this site,
            keep the gaming gear picks and the game requirements checker up to date, and answer every message we get.
          </p>
        </div>
      </div>

      <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
        <h2 className="font-display font-extrabold text-xl text-slate-900">What we write about</h2>
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
        <h2 className="font-display font-extrabold text-xl text-slate-900">How we work</h2>
        <p className="text-sm text-slate-600 leading-relaxed">
          We pick products from their specifications, the maker&apos;s own data and well-known published reviews, and we say who each one is for.
          Game requirements come from the publishers (Steam for PC games). Brands cannot pay for a place on our lists, and sponsored content is always labelled.
          Found a mistake? Tell us on the <a href="/contact/" className="font-bold text-emerald-700">contact page</a>.
        </p>
        <div className="flex flex-wrap gap-3 pt-1">
          <a href="/blogs/" className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-5 rounded-xl inline-flex items-center gap-1.5 no-underline">
            Read our articles <ArrowRight className="w-3.5 h-3.5" />
          </a>
          <a href="/about/" className="bg-slate-100 hover:bg-slate-200 text-slate-900 font-bold text-xs py-2.5 px-5 rounded-xl no-underline">
            About Run On Console
          </a>
        </div>
      </section>

    </div>
  );
};
