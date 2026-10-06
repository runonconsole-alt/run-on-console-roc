import React, { useState, useEffect } from 'react';
import { AppProvider, useApp } from './context/AppContext';
import { Header } from './components/Header';
import { HomeView } from './components/HomeView';
import { CategoriesView } from './components/CategoriesView';
import { ProductsView } from './components/ProductsView';
import { BlogsView } from './components/BlogsView';
import { AboutView } from './components/AboutView';
import { AuthorView } from './components/AuthorView';
import { NotFoundView } from './components/NotFoundView';
import { WriteForUsView } from './components/WriteForUsView';
import { PartnershipView } from './components/PartnershipView';
import { PolicyView } from './components/PolicyView';
import { TermsAndConditionsView } from './components/TermsAndConditionsView';
import { PrivacyPolicyView } from './components/PrivacyPolicyView';
import { ContactView } from './components/ContactView';
import { GameCompatibilityView } from './components/GameCompatibilityView';
import { AuthView } from './components/AuthView';
import { ProfileView } from './components/ProfileView';
import { ValuePropsFooter } from './components/ValuePropsFooter';
import { CompareDrawer } from './components/CompareDrawer';
import { AdminLayout } from './components/admin/AdminLayout';
import { FragAIAssistantModal } from './components/FragAIAssistantModal';
import { ROCAgentModal } from './components/ROCAgentModal';

const MainRouter = () => {
  const { currentPage, is404 } = useApp();
  const [navigating, setNavigating] = useState(false);

  useEffect(() => {
    if (is404) {
      setNavigating(false);
      return;
    }
    setNavigating(true);
    const timer = setTimeout(() => setNavigating(false), 200);
    return () => clearTimeout(timer);
  }, [currentPage, is404]);

  // If in Admin URL route (e.g. /admin), render separate ROC Admin Suite
  if (currentPage === 'admin') {
    return <AdminLayout />;
  }

  return (
    <div className="min-h-screen bg-[#F8FAFC] text-[#0F172A] flex flex-col font-body relative" suppressHydrationWarning>
      
      {/* Route Progress Indicator (Disabled completely on 404 views) */}
      <div 
        className={`fixed top-0 left-0 right-0 h-0.5 bg-gradient-to-r from-emerald-500 via-teal-400 to-emerald-300 z-50 transition-opacity duration-200 ${
          navigating && !is404 ? 'opacity-100' : 'opacity-0 pointer-events-none'
        }`} 
      />

      {/* Primary Navigation Header */}
      <Header />

      {/* Main Routed Page Content (Opacity transition disabled completely on 404 views) */}
      <main className={`flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 ${
        is404 ? 'opacity-100' : `transition-opacity duration-200 ${navigating ? 'opacity-90' : 'opacity-100'}`
      }`}>
        {is404 ? (
          <NotFoundView />
        ) : (
          <>
            {currentPage === 'home' && <HomeView />}
            {currentPage === 'categories' && <CategoriesView />}
            {currentPage === 'compatibility' && <GameCompatibilityView />}
            {currentPage === 'products' && <ProductsView />}
            {currentPage === 'blogs' && <BlogsView />}
            {currentPage === 'about' && <AboutView />}
            {currentPage === 'author' && <AuthorView />}
            {currentPage === 'write-for-us' && <WriteForUsView />}
            {currentPage === 'partnerships' && <PartnershipView />}
            {currentPage === 'policy' && <PolicyView />}
            {currentPage === 'terms-and-conditions' && <TermsAndConditionsView />}
            {currentPage === 'privacy-policy' && <PrivacyPolicyView />}
            {currentPage === 'contact' && <ContactView />}
            {currentPage === 'auth' && <AuthView />}
            {currentPage === 'profile' && <ProfileView />}
          </>
        )}
      </main>

      {/* Rich Multi-Column Footer with About Snippet, Social Media Hub & Policy Links */}
      <ValuePropsFooter />

      {/* Side-by-side Hardware Compare Drawer */}
      <CompareDrawer />

      {/* Global ROC Gaming, Product & Hardware Assistant Agent */}
      <ROCAgentModal />

    </div>
  );
};

export default function App({ initialUrl = null }) {
  return (
    <AppProvider initialUrl={initialUrl}>
      <MainRouter />
    </AppProvider>
  );
}
