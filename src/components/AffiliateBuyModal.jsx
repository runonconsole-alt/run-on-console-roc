import React from 'react';
import { useApp } from '../context/AppContext';
import { ShoppingBag, ArrowRight, UserPlus, LogIn, ExternalLink, X, ShieldCheck } from 'lucide-react';

export const AffiliateBuyModal = ({ product, onClose }) => {
  const { navigateTo } = useApp();

  if (!product) return null;

  const safeReturnPath = `/products/${product.slug}/`;

  const handleGuestContinue = () => {
    if (product.affiliateUrl) {
      window.open(product.affiliateUrl, '_blank', 'noopener,noreferrer');
    }
    onClose();
  };

  const handleSignIn = () => {
    onClose();
    navigateTo('auth', 'login');
  };

  const handleSignUp = () => {
    onClose();
    navigateTo('auth', 'signup');
  };

  return (
    <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-[99999] flex items-center justify-center p-4 animate-fade-in">
      <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 max-w-md w-full space-y-6 shadow-2xl relative">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-2 rounded-xl transition"
          aria-label="Close modal"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="text-center space-y-2">
          <div className="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center mx-auto">
            <ShoppingBag className="w-6 h-6" />
          </div>
          <h3 className="font-display font-extrabold text-xl text-white">
            {product.name}
          </h3>
          <p className="text-xs text-slate-400">
            {product.brand} • <strong className="text-emerald-400 font-bold">{product.price}</strong>
          </p>
        </div>

        <div className="space-y-3">
          <button
            onClick={handleGuestContinue}
            className="w-full py-3 px-4 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-display font-extrabold text-sm rounded-xl transition shadow-lg flex items-center justify-center gap-2"
          >
            <span>Continue as Guest</span>
            <ExternalLink className="w-4 h-4" />
          </button>
          
          <p className="text-[11px] text-slate-500 text-center">
            Guest redirect opens merchant storefront immediately in a new tab without creating an account.
          </p>

          <div className="relative my-4">
            <div className="absolute inset-0 flex items-center">
              <div className="w-full border-t border-slate-800"></div>
            </div>
            <div className="relative flex justify-center text-xs uppercase">
              <span className="bg-slate-900 px-2 text-slate-500 font-bold">Or Save Your Wishlist</span>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <button
              onClick={handleSignIn}
              className="py-2.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition flex items-center justify-center gap-1.5"
            >
              <LogIn className="w-3.5 h-3.5 text-emerald-400" />
              <span>Sign In</span>
            </button>
            <button
              onClick={handleSignUp}
              className="py-2.5 px-3 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition flex items-center justify-center gap-1.5"
            >
              <UserPlus className="w-3.5 h-3.5 text-cyan-400" />
              <span>Join Free</span>
            </button>
          </div>
        </div>

        <div className="text-[10px] text-slate-500 text-center border-t border-slate-800/80 pt-3 flex items-center justify-center gap-1">
          <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
          <span>Direct verified storefront links with rel="nofollow sponsored noopener noreferrer"</span>
        </div>
      </div>
    </div>
  );
};
