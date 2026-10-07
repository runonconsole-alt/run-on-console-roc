import React from 'react';
import { HeroSection } from './HeroSection';
import { LatestBlogsSection } from './LatestBlogsSection';
import { LatestReviewsSection } from './LatestReviewsSection';

export const HomeView = () => {
  return (
    <div className="space-y-14 animate-page-in">
      
      {/* 1. Hero Banner */}
      <HeroSection />

      {/* 2. Latest blog posts from the CMS (hidden until a post is published) */}
      <LatestBlogsSection />

      {/* 3. Latest Tested Hardware & Deals */}
      <LatestReviewsSection />

    </div>
  );
};
