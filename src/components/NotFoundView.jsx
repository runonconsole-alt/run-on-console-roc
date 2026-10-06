import React from 'react';
import { Home, ArrowLeft } from 'lucide-react';

export const NotFoundView = () => {
  return (
    <div className="max-w-xl mx-auto py-20 px-4 text-center space-y-6">
      <div className="inline-flex items-center justify-center w-20 h-20 rounded-full bg-rose-100 text-rose-600 font-display font-extrabold text-3xl shadow-inner">
        404
      </div>

      <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-slate-900 leading-tight">
        Page Not Found
      </h1>

      <p className="text-sm sm:text-base text-slate-600 leading-relaxed max-w-md mx-auto">
        The requested hardware review, platform guide, or URL does not exist or has been permanently moved.
      </p>

      <div className="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
        <a
          href="/"
          className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs py-3.5 px-6 rounded-xl flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/25 transition-all hover:scale-105"
        >
          <Home className="w-4 h-4" />
          <span>Return to Homepage</span>
        </a>

        <a
          href="/blogs/"
          className="w-full sm:w-auto bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs py-3.5 px-5 rounded-xl flex items-center justify-center gap-2 shadow-sm transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Browse Reviews & Guides</span>
        </a>
      </div>
    </div>
  );
};
