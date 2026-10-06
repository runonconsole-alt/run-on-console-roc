import React from 'react';
import { ShoppingBag, UserCheck, UserPlus, ArrowRight, X } from 'lucide-react';
import { useApp } from '../context/AppContext';

export const AffiliateModal = ({ isOpen, onClose, targetUrl, productName }) => {
  const { navigateTo, setAuthMode } = useApp();

  if (!isOpen) return null;

  const handleGuestContinue = () => {
    onClose();
    if (targetUrl) {
      window.open(targetUrl, '_blank', 'noopener,noreferrer');
    }
  };

  const handleSignIn = () => {
    onClose();
    setAuthMode('login');
    navigateTo('/auth/login/');
  };

  const handleSignUp = () => {
    onClose();
    setAuthMode('signup');
    navigateTo('/auth/signup/');
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm animate-fade-in">
      <div className="bg-slate-900 border border-emerald-500/30 text-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl relative space-y-6">
        
        <button 
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-2 rounded-full hover:bg-slate-800 transition-colors"
          aria-label="Close Modal"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto border border-emerald-500/30">
            <ShoppingBag className="w-6 h-6" />
          </div>
          <h3 className="font-display font-extrabold text-xl text-white">
            Verified Partner Retailer Deal
          </h3>
          <p className="text-xs text-slate-300">
            You are proceeding to Amazon for <strong className="text-emerald-400">{productName || 'Verified Hardware'}</strong>.
          </p>
        </div>

        <div className="space-y-3 pt-2">
          <button
            onClick={handleGuestContinue}
            className="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-display font-extrabold text-xs uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2"
          >
            <span>Continue to Deal as Guest</span>
            <ArrowRight className="w-4 h-4" />
          </button>

          <button
            onClick={handleSignIn}
            className="w-full py-3 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-2"
          >
            <UserCheck className="w-4 h-4 text-emerald-400" />
            <span>Sign In to Save to Wishlist & Earn Rewards</span>
          </button>

          <button
            onClick={handleSignUp}
            className="w-full py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs rounded-xl border border-slate-700 transition-colors flex items-center justify-center gap-2"
          >
            <UserPlus className="w-4 h-4 text-teal-400" />
            <span>Create Free Gamer Account</span>
          </button>
        </div>

        <p className="text-[11px] text-slate-400 text-center leading-relaxed">
          Run On Console earns an affiliate commission on qualifying purchases at no extra cost to you.
        </p>

      </div>
    </div>
  );
};
