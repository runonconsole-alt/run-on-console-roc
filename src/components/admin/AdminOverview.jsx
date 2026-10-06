import React from 'react';
import { useApp } from '../../context/AppContext';
import { Database, Star, FileText, PlusCircle, Award, TrendingUp, Users, ShoppingCart } from 'lucide-react';

export const AdminOverview = () => {
  const { products, blogs, setAdminTab } = useApp();

  const totalProducts = products.length;
  const totalBlogs = blogs.length;
  const avgRating = totalProducts > 0 
    ? (products.reduce((acc, p) => acc + (p.rating || (p.rocScore/2)), 0) / totalProducts).toFixed(1)
    : "4.8";

  return (
    <div className="space-y-6 max-w-6xl">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">SYSTEM ANALYTICS & DATABASE OVERVIEW</h2>
          <p className="text-xs text-slate-500 font-medium">Run On Console (ROC) Hardware, Compatibility & CMS Suite</p>
        </div>

        <div className="flex items-center gap-2">
          <button 
            onClick={() => setAdminTab('new-product')}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 shadow-md shadow-emerald-600/20 transition-all"
          >
            <PlusCircle className="w-4 h-4" /> ADD PRODUCT / DEAL
          </button>
          <button 
            onClick={() => setAdminTab('new-blog')}
            className="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 transition-all"
          >
            <FileText className="w-4 h-4" /> WRITE BLOG
          </button>
        </div>
      </div>

      {/* Metrics Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        {/* Total Products */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <div className="flex justify-between items-start mb-2">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">TESTED PRODUCTS</span>
            <div className="p-2 rounded-xl bg-emerald-50 text-emerald-600">
              <Database className="w-5 h-5" />
            </div>
          </div>
          <div className="font-display font-extrabold text-3xl text-slate-900 mb-1">{totalProducts}</div>
          <span className="text-[11px] font-semibold text-emerald-600">Active with Affiliate Links</span>
        </div>

        {/* Total Blogs */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <div className="flex justify-between items-start mb-2">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">BLOG POSTS</span>
            <div className="p-2 rounded-xl bg-blue-50 text-blue-600">
              <FileText className="w-5 h-5" />
            </div>
          </div>
          <div className="font-display font-extrabold text-3xl text-slate-900 mb-1">{totalBlogs}</div>
          <span className="text-[11px] font-semibold text-blue-600">Published Guides</span>
        </div>

        {/* Average Rating */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <div className="flex justify-between items-start mb-2">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">AVG RATING</span>
            <div className="p-2 rounded-xl bg-amber-50 text-amber-500">
              <Star className="w-5 h-5 fill-amber-500" />
            </div>
          </div>
          <div className="font-display font-extrabold text-3xl text-slate-900 mb-1">{avgRating} ★</div>
          <span className="text-[11px] font-semibold text-slate-500">Out of 5.0 scale</span>
        </div>

        {/* Active Readers */}
        <div className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
          <div className="flex justify-between items-start mb-2">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">MONTHLY READERS</span>
            <div className="p-2 rounded-xl bg-purple-50 text-purple-600">
              <Users className="w-5 h-5" />
            </div>
          </div>
          <div className="font-display font-extrabold text-3xl text-slate-900 mb-1">54.2K</div>
          <span className="text-[11px] font-semibold text-purple-600">+18% vs last month</span>
        </div>

      </div>

      {/* Quick Catalog List Preview */}
      <div className="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-4">
        <div className="flex justify-between items-center border-b border-slate-100 pb-3">
          <h3 className="font-display font-extrabold text-base text-slate-900">
            RECENT HARDWARE & AFFILIATE PRODUCTS
          </h3>
          <button onClick={() => setAdminTab('products')} className="text-xs font-bold text-emerald-600 hover:underline">
            Manage All Products →
          </button>
        </div>

        <div className="space-y-3">
          {products.slice(0, 5).map(p => (
            <div key={p.id} className="p-3.5 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <img src={p.image} alt={p.title} className="w-12 h-12 rounded-xl object-cover" />
                <div>
                  <h4 className="font-display font-bold text-sm text-slate-900">{p.title}</h4>
                  <span className="text-xs text-emerald-600 font-semibold">{p.category} • {p.price}</span>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <span className="text-xs font-bold text-slate-700">★ {p.rating || 4.8}</span>
                <button 
                  onClick={() => setAdminTab('products')}
                  className="text-xs font-bold text-blue-600 hover:underline"
                >
                  Edit →
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>

    </div>
  );
};
