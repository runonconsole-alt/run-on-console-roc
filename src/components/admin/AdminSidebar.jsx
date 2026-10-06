import React from 'react';
import { useApp } from '../../context/AppContext';
import { 
  LayoutDashboard, Database, FileText, PlusCircle, ArrowLeft, 
  Gamepad2, PenTool, Image, Globe, Link2, Code, Sparkles, CheckCircle2
} from 'lucide-react';

export const AdminSidebar = () => {
  const { adminTab, setAdminTab, navigateTo, products, blogs, guestSubmissions, siteImages, internalLinks } = useApp();

  const navItems = [
    { id: 'overview', label: 'OVERVIEW STATS', icon: LayoutDashboard },
    { id: 'images', label: 'IMAGES & MEDIA', icon: Image, badge: siteImages?.length || 16 },
    { id: 'content', label: 'SITE CONTENT & COPY', icon: FileText },
    { id: 'metas', label: 'METAS & SEO STUDIO', icon: Globe },
    { id: 'internal-links', label: 'INTERNAL LINKS', icon: Link2, badge: internalLinks?.length || 12 },
    { id: 'schemas', label: 'SCHEMAS (JSON-LD)', icon: Code },
    { id: 'blogs', label: 'BLOGS & GUIDES', icon: FileText, badge: blogs.length },
    { id: 'products', label: 'PRODUCTS CATALOG', icon: Database, badge: products.length },
    { id: 'submissions', label: 'WRITE FOR US LEADS', icon: PenTool, badge: (guestSubmissions || []).length },
  ];

  const actionItems = [
    { id: 'new-blog', label: 'WRITE BLOG POST', icon: PlusCircle },
    { id: 'new-product', label: 'ADD PRODUCT / DEAL', icon: PlusCircle }
  ];

  return (
    <aside className="w-full lg:w-64 bg-[#0B132B] border-r border-slate-800 p-5 flex flex-col justify-between shrink-0 text-white min-h-screen">
      <div>
        {/* ROC Master Admin Suite Logo & Unlocked Status */}
        <div className="pb-5 mb-5 border-b border-slate-800">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-500 via-teal-500 to-emerald-400 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
              <Gamepad2 className="w-5 h-5" />
            </div>
            <div>
              <h2 className="font-display font-extrabold text-base tracking-tight text-white">ROC MASTER SUITE</h2>
              <div className="text-[10px] text-emerald-400 font-bold flex items-center gap-1.5">
                <span className="w-2 h-2 bg-emerald-400 rounded-full animate-ping"></span>
                <span>RUN ON CONSOLE</span>
              </div>
            </div>
          </div>

          <div className="bg-emerald-950/70 border border-emerald-500/40 rounded-xl px-2.5 py-1.5 flex items-center justify-between text-[10px]">
            <span className="text-emerald-300 font-bold flex items-center gap-1">
              <CheckCircle2 className="w-3 h-3 text-emerald-400" /> UNLOCKED ACCESS
            </span>
            <span className="text-emerald-400 font-mono font-extrabold">MASTER</span>
          </div>
        </div>

        {/* Navigation Tabs */}
        <nav className="space-y-1">
          <div className="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider px-3 mb-1">
            CORE CONTROL MODULES
          </div>

          {navItems.map(item => {
            const Icon = item.icon;
            const isActive = adminTab === item.id;

            return (
              <button
                key={item.id}
                onClick={() => setAdminTab(item.id)}
                className={`w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-xs font-bold tracking-wide transition-all ${
                  isActive 
                    ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' 
                    : 'text-slate-400 hover:text-white hover:bg-slate-800/60'
                }`}
              >
                <div className="flex items-center gap-2.5 truncate">
                  <Icon className="w-4 h-4 shrink-0" />
                  <span className="truncate">{item.label}</span>
                </div>
                {item.badge !== undefined && (
                  <span className={`text-[10px] px-1.5 py-0.2 rounded-md font-bold shrink-0 ${
                    isActive ? 'bg-white text-emerald-700' : 'bg-slate-800 text-slate-400'
                  }`}>
                    {item.badge}
                  </span>
                )}
              </button>
            );
          })}

          <div className="pt-3 pb-1">
            <div className="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider px-3 mb-1">
              QUICK CREATION
            </div>
            {actionItems.map(item => {
              const Icon = item.icon;
              const isActive = adminTab === item.id;

              return (
                <button
                  key={item.id}
                  onClick={() => setAdminTab(item.id)}
                  className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-bold tracking-wide transition-all ${
                    isActive 
                      ? 'bg-teal-600 text-white shadow-md' 
                      : 'text-slate-400 hover:text-white hover:bg-slate-800/60'
                  }`}
                >
                  <Icon className="w-3.5 h-3.5 text-teal-400" />
                  <span>{item.label}</span>
                </button>
              );
            })}
          </div>
        </nav>
      </div>

      {/* Return to Public Website */}
      <div className="pt-4 border-t border-slate-800">
        <button
          onClick={() => navigateTo('home')}
          className="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white py-2.5 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition-colors shadow-xs"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>VIEW PUBLIC SITE</span>
        </button>
      </div>
    </aside>
  );
};
