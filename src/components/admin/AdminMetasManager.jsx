import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { 
  Globe, Search, CheckCircle2, AlertTriangle, Save, 
  Smartphone, Monitor, Eye, Sparkles, Copy, ExternalLink, RefreshCw
} from 'lucide-react';

export const AdminMetasManager = () => {
  const { siteMetas, updateSiteMetas, showNotification } = useApp();

  const [selectedRoute, setSelectedRoute] = useState('/');
  const [serpDevice, setSerpDevice] = useState('desktop'); // desktop or mobile
  const [activeMetaTab, setActiveMetaTab] = useState('basic'); // basic or social

  // Current route's metadata
  const currentMeta = (siteMetas && siteMetas[selectedRoute]) || {
    title: 'Run On Console | Gaming Hardware, Compatibility & Peripherals Hub',
    description: 'Independent gaming hardware intelligence lab. Test PC and console game compatibility, frametime analysis, and unbiased gaming gear benchmarks.',
    keywords: 'gaming hardware, pc game compatibility, console benchmarks, gaming laptops, fps testing',
    canonical: 'https://runonconsole.com/',
    robots: 'index, follow',
    ogTitle: 'Run On Console | Gaming Hardware & Compatibility Lab',
    ogDescription: 'Real-world benchmarks, frame pacing tests, and hardware intelligence for gamers.',
    ogImage: 'https://runonconsole.com/images/hero_cod.jpg',
    ogType: 'website',
    twitterCard: 'summary_large_image',
    twitterCreator: '@runonconsole'
  };

  const [formData, setFormData] = useState(currentMeta);

  // When selected route changes, update formData
  React.useEffect(() => {
    if (siteMetas && siteMetas[selectedRoute]) {
      setFormData(siteMetas[selectedRoute]);
    }
  }, [selectedRoute, siteMetas]);

  const handleChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSave = (e) => {
    e.preventDefault();
    updateSiteMetas(selectedRoute, formData);
    showNotification(`SEO Metas saved for route: ${selectedRoute}`);
  };

  const routesList = [
    { path: '/', label: 'Homepage (/)' },
    { path: '/compatibility/', label: 'Game Compatibility Tool (/compatibility/)' },
    { path: '/products/', label: 'Hardware Products & Reviews (/products/)' },
    { path: '/blogs/', label: 'Blogs & Guides Editorial (/blogs/)' },
    { path: '/categories/', label: 'Platform Hubs & Categories (/categories/)' },
    { path: '/about/', label: 'About Us (/about/)' },
    { path: '/author/', label: 'Lead Editor Profile (/author/)' },
    { path: '/write-for-us/', label: 'Guest Writing Program (/write-for-us/)' },
    { path: '/partnerships/', label: 'Brand Partnerships (/partnerships/)' },
    { path: '/contact/', label: 'Contact Us (/contact/)' },
  ];

  // Character lengths
  const titleLen = formData.title ? formData.title.length : 0;
  const descLen = formData.description ? formData.description.length : 0;

  const isTitleOptimal = titleLen >= 45 && titleLen <= 65;
  const isDescOptimal = descLen >= 130 && descLen <= 165;

  return (
    <div className="space-y-6 max-w-7xl animate-page-in">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[10px] text-blue-700 bg-blue-100 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
              Search Engine Optimization Hub
            </span>
          </div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">METAS & SEO TITLE/DESCRIPTION STUDIO</h2>
          <p className="text-xs text-slate-500 font-medium">
            Full control over Google SERP snippets, OpenGraph tags, canonicals, robots indexation, and keywords
          </p>
        </div>

        <button
          onClick={handleSave}
          className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 shadow-md shadow-emerald-600/20 transition-all hover:scale-105"
        >
          <Save className="w-4 h-4" /> SAVE ROUTE METAS
        </button>
      </div>

      {/* Route Selector Bar */}
      <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div className="flex items-center gap-2 w-full md:w-auto">
          <Globe className="w-4 h-4 text-emerald-600 shrink-0" />
          <span className="text-xs font-bold text-slate-700">Target Route:</span>
          <select
            value={selectedRoute}
            onChange={(e) => setSelectedRoute(e.target.value)}
            className="flex-1 md:w-80 bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-900"
          >
            {routesList.map(r => (
              <option key={r.path} value={r.path}>{r.label}</option>
            ))}
          </select>
        </div>

        <div className="flex items-center gap-2">
          <span className="text-[11px] font-bold text-slate-500">Indexing Directive:</span>
          <span className="text-[11px] font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-lg">
            {formData.robots || 'index, follow'}
          </span>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        {/* Left Column: Meta Editor Form (7 cols) */}
        <div className="lg:col-span-7 space-y-5">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-5">
            
            {/* Sub-Tabs: Basic Meta vs Social OpenGraph */}
            <div className="flex gap-2 pb-3 border-b border-slate-100">
              <button
                type="button"
                onClick={() => setActiveMetaTab('basic')}
                className={`px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-colors ${
                  activeMetaTab === 'basic' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'
                }`}
              >
                1. Google Search Metas
              </button>
              <button
                type="button"
                onClick={() => setActiveMetaTab('social')}
                className={`px-3.5 py-1.5 rounded-xl text-xs font-extrabold transition-colors ${
                  activeMetaTab === 'social' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'
                }`}
              >
                2. Social / OpenGraph & Twitter
              </button>
            </div>

            {activeMetaTab === 'basic' && (
              <form onSubmit={handleSave} className="space-y-4 text-xs">
                
                {/* Meta Title */}
                <div>
                  <div className="flex justify-between items-center mb-1">
                    <label className="font-bold text-slate-800">
                      Page Title Tag (&lt;title&gt;)
                    </label>
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-md ${
                      isTitleOptimal ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                    }`}>
                      {titleLen}/60 chars {isTitleOptimal ? '✓ Optimal' : '(Aim for 50-60)'}
                    </span>
                  </div>
                  <input
                    type="text"
                    required
                    value={formData.title || ''}
                    onChange={(e) => handleChange('title', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs font-bold text-slate-900 focus:outline-none focus:border-emerald-500"
                    placeholder="Page Title displayed in Google Search results"
                  />
                </div>

                {/* Meta Description */}
                <div>
                  <div className="flex justify-between items-center mb-1">
                    <label className="font-bold text-slate-800">
                      Meta Description (name="description")
                    </label>
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-md ${
                      isDescOptimal ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                    }`}>
                      {descLen}/160 chars {isDescOptimal ? '✓ Optimal' : '(Aim for 140-160)'}
                    </span>
                  </div>
                  <textarea
                    rows={3}
                    required
                    value={formData.description || ''}
                    onChange={(e) => handleChange('description', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs leading-relaxed focus:outline-none focus:border-emerald-500"
                    placeholder="Compelling snippet text to drive click-through rate (CTR)"
                  />
                </div>

                {/* Canonical URL */}
                <div>
                  <label className="block font-bold text-slate-800 mb-1">Canonical URL (rel="canonical")</label>
                  <input
                    type="url"
                    value={formData.canonical || ''}
                    onChange={(e) => handleChange('canonical', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                    placeholder="https://runonconsole.com/..."
                  />
                </div>

                {/* Focus Keywords */}
                <div>
                  <label className="block font-bold text-slate-800 mb-1">
                    Focus Keywords (Comma-separated)
                  </label>
                  <input
                    type="text"
                    value={formData.keywords || ''}
                    onChange={(e) => handleChange('keywords', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                    placeholder="e.g. gaming benchmarks, rtx 4070, steam deck compatibility"
                  />
                </div>

                {/* Robots Indexation */}
                <div>
                  <label className="block font-bold text-slate-800 mb-1">Robots Meta Tag</label>
                  <select
                    value={formData.robots || 'index, follow'}
                    onChange={(e) => handleChange('robots', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold"
                  >
                    <option value="index, follow">index, follow (Standard Public Indexing)</option>
                    <option value="noindex, follow">noindex, follow (Private / Staging Page)</option>
                    <option value="noindex, nofollow">noindex, nofollow (Strict Block)</option>
                  </select>
                </div>

              </form>
            )}

            {activeMetaTab === 'social' && (
              <form onSubmit={handleSave} className="space-y-4 text-xs">
                
                <div>
                  <label className="block font-bold text-slate-800 mb-1">OpenGraph Title (og:title)</label>
                  <input
                    type="text"
                    value={formData.ogTitle || ''}
                    onChange={(e) => handleChange('ogTitle', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold"
                    placeholder="Title shown on Discord, Facebook, Slack previews"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-800 mb-1">OpenGraph Description (og:description)</label>
                  <textarea
                    rows={2}
                    value={formData.ogDescription || ''}
                    onChange={(e) => handleChange('ogDescription', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                    placeholder="Short summary for social card shares"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-800 mb-1">Social Share Image URL (og:image)</label>
                  <input
                    type="text"
                    value={formData.ogImage || ''}
                    onChange={(e) => handleChange('ogImage', e.target.value)}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                    placeholder="https://runonconsole.com/images/..."
                  />
                  {formData.ogImage && (
                    <div className="mt-2 p-2 bg-slate-50 rounded-xl border border-slate-200 flex items-center gap-3">
                      <img src={formData.ogImage} alt="OG Preview" className="w-16 h-10 object-cover rounded-md border" />
                      <span className="text-[10px] text-slate-500">Social banner preview (1200x630 recommended)</span>
                    </div>
                  )}
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block font-bold text-slate-800 mb-1">Twitter Card Type</label>
                    <select
                      value={formData.twitterCard || 'summary_large_image'}
                      onChange={(e) => handleChange('twitterCard', e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                    >
                      <option value="summary_large_image">summary_large_image (Big Card)</option>
                      <option value="summary">summary (Small Thumb)</option>
                    </select>
                  </div>

                  <div>
                    <label className="block font-bold text-slate-800 mb-1">Twitter Creator Handle</label>
                    <input
                      type="text"
                      value={formData.twitterCreator || '@runonconsole'}
                      onChange={(e) => handleChange('twitterCreator', e.target.value)}
                      className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                      placeholder="@runonconsole"
                    />
                  </div>
                </div>

              </form>
            )}

            <div className="pt-2 flex justify-end">
              <button
                type="button"
                onClick={handleSave}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-xl shadow-md flex items-center gap-2"
              >
                <Save className="w-4 h-4" /> Save Metadata for {selectedRoute}
              </button>
            </div>

          </div>
        </div>

        {/* Right Column: Live Google SERP Simulator (5 cols) */}
        <div className="lg:col-span-5 space-y-4">
          <div className="bg-white border border-slate-200 rounded-3xl p-5 shadow-xs space-y-4">
            
            <div className="flex justify-between items-center pb-2 border-b border-slate-100">
              <h3 className="font-display font-extrabold text-sm text-slate-900 flex items-center gap-2">
                <Search className="w-4 h-4 text-emerald-600" /> Google Search Result Simulator
              </h3>

              <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                <button
                  type="button"
                  onClick={() => setSerpDevice('desktop')}
                  className={`p-1.5 rounded-lg text-xs transition-colors ${
                    serpDevice === 'desktop' ? 'bg-white shadow-xs text-slate-900' : 'text-slate-500'
                  }`}
                  title="Desktop Preview"
                >
                  <Monitor className="w-3.5 h-3.5" />
                </button>
                <button
                  type="button"
                  onClick={() => setSerpDevice('mobile')}
                  className={`p-1.5 rounded-lg text-xs transition-colors ${
                    serpDevice === 'mobile' ? 'bg-white shadow-xs text-slate-900' : 'text-slate-500'
                  }`}
                  title="Mobile Preview"
                >
                  <Smartphone className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>

            {/* Google SERP Simulated Card */}
            <div className={`p-4 bg-white border border-slate-200 rounded-2xl shadow-xs transition-all ${
              serpDevice === 'mobile' ? 'max-w-[340px] mx-auto' : 'w-full'
            }`}>
              
              {/* Site URL & Favicon */}
              <div className="flex items-center gap-2 mb-1.5">
                <div className="w-6 h-6 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center p-1">
                  <img src="/favicon.svg" alt="ROC" className="w-4 h-4" />
                </div>
                <div className="text-[11px] leading-tight text-slate-700">
                  <div className="font-semibold text-slate-900">Run On Console</div>
                  <div className="text-[10px] text-slate-500 font-mono truncate">
                    {formData.canonical || `https://runonconsole.com${selectedRoute}`}
                  </div>
                </div>
              </div>

              {/* Clickable Blue Title */}
              <h4 className="text-base text-[#1a0dab] hover:underline cursor-pointer font-medium leading-snug line-clamp-2">
                {formData.title || 'Untitled Page — Run On Console'}
              </h4>

              {/* Grey Snippet Description */}
              <p className="text-xs text-[#4d5156] mt-1 leading-normal line-clamp-2">
                {formData.description || 'No meta description provided. Add a description above to control your search snippet.'}
              </p>

              {/* Rich snippet badges */}
              <div className="mt-2.5 pt-2 border-t border-slate-100 flex items-center gap-2 text-[10px] text-emerald-700 font-bold">
                <span className="flex items-center gap-1 bg-emerald-50 px-2 py-0.5 rounded">
                  ★ 4.9 (1,280 reviews)
                </span>
                <span className="text-slate-400">•</span>
                <span className="bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                  Fast Loading
                </span>
              </div>
            </div>

            {/* Social Share Card Preview */}
            <div className="mt-4 pt-4 border-t border-slate-100">
              <span className="text-[11px] font-extrabold text-slate-700 block mb-2">
                Social Card Share Preview (Discord / X / Facebook)
              </span>

              <div className="border border-slate-200 rounded-2xl overflow-hidden bg-slate-50 shadow-xs">
                {formData.ogImage ? (
                  <img src={formData.ogImage} alt="Social Card" className="w-full h-36 object-cover" />
                ) : (
                  <div className="w-full h-36 bg-slate-200 flex items-center justify-center text-slate-400 text-xs">
                    No image provided
                  </div>
                )}
                <div className="p-3 bg-white space-y-1">
                  <div className="text-[10px] uppercase font-bold text-slate-400">runonconsole.com</div>
                  <div className="text-xs font-bold text-slate-900 truncate">
                    {formData.ogTitle || formData.title}
                  </div>
                  <div className="text-[11px] text-slate-500 line-clamp-2">
                    {formData.ogDescription || formData.description}
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

    </div>
  );
};
