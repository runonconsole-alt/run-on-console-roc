import React from 'react';
import { HeroSection } from './HeroSection';
import { LatestBlogsSection } from './LatestBlogsSection';
import { LatestReviewsSection } from './LatestReviewsSection';
import { FAQSection } from './FAQSection';
import { useApp } from '../context/AppContext';

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// The newest post or product change, set by the server (site-layer-lib.php, the same date as the
// home page's dateModified). The server also writes the text into the built page, so it matches here.
const homeUpdated = () => {
  const iso = typeof window !== 'undefined' ? window.__ROC_HOME_DATE : '';
  const d = iso ? new Date(iso) : null;
  if (!d || isNaN(d)) return null;
  return { iso, text: `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]} ${d.getUTCFullYear()}` };
};

export const HomeView = () => {
  const { pageFaqs } = useApp();
  const updated = homeUpdated();
  return (
    <div className="space-y-14 animate-page-in">

      {/* 1. Hero Banner */}
      <HeroSection />

      {/* 2. Latest blog posts from the CMS (hidden until a post is published) */}
      <LatestBlogsSection />

      {/* 3. Latest Tested Hardware & Deals */}
      <LatestReviewsSection />

      {/* 4. Questions about the site (also the FAQPage schema, seoConfig.js) */}
      <FAQSection
        faqs={pageFaqs.home}
        title="Run On Console FAQ"
        subtitle="What the site is, how picks are made and how the checker works."
      />

      <p data-roc-home-updated="" className="text-center text-xs text-slate-500 font-semibold">
        {updated && <>Last updated: <time dateTime={updated.iso}>{updated.text}</time></>}
      </p>

    </div>
  );
};
