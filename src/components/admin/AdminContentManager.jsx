import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { 
  FileText, Save, RotateCcw, Sparkles, HelpCircle, 
  Plus, Trash2, Edit2, Layers, CheckCircle2, Globe, Shield, MessageSquare
} from 'lucide-react';

export const AdminContentManager = () => {
  const { 
    siteContent, 
    updateSiteContent, 
    resetSiteContentToDefaults, 
    showNotification 
  } = useApp();

  const [activeSection, setActiveSection] = useState('hero');
  const [formData, setFormData] = useState(siteContent || {});
  const [editingFaqIndex, setEditingFaqIndex] = useState(null);
  const [newFaq, setNewFaq] = useState({ question: '', answer: '', category: 'General' });
  const [isAddingFaq, setIsAddingFaq] = useState(false);

  // Sync if context updates
  React.useEffect(() => {
    if (siteContent) {
      setFormData(siteContent);
    }
  }, [siteContent]);

  const handleFieldChange = (section, field, value) => {
    setFormData(prev => ({
      ...prev,
      [section]: {
        ...prev[section],
        [field]: value
      }
    }));
  };

  const handleSaveAll = () => {
    updateSiteContent(formData);
    showNotification('Site content and copywriting updated successfully!');
  };

  const handleReset = () => {
    if (confirm('Are you sure you want to reset all site content back to original defaults?')) {
      resetSiteContentToDefaults();
      showNotification('Site content reset to original defaults.', 'warning');
    }
  };

  const handleAddFaq = (e) => {
    e.preventDefault();
    if (!newFaq.question || !newFaq.answer) return;

    const currentFaqs = formData.faqs || [];
    const updated = [...currentFaqs, newFaq];
    setFormData(prev => ({ ...prev, faqs: updated }));
    setNewFaq({ question: '', answer: '', category: 'General' });
    setIsAddingFaq(false);
  };

  const handleDeleteFaq = (index) => {
    const currentFaqs = formData.faqs || [];
    const updated = currentFaqs.filter((_, i) => i !== index);
    setFormData(prev => ({ ...prev, faqs: updated }));
  };

  return (
    <div className="space-y-6 max-w-7xl animate-page-in">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[10px] text-teal-700 bg-teal-100 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
              Visual Copywriting CMS
            </span>
          </div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">SITE CONTENT & COPYWRITING</h2>
          <p className="text-xs text-slate-500 font-medium">
            Live editable access to hero headers, about text, platform descriptions, FAQs, and footer copy
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={handleReset}
            className="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold py-2.5 px-3.5 rounded-xl flex items-center gap-1.5 transition-colors"
          >
            <RotateCcw className="w-3.5 h-3.5" /> Defaults
          </button>
          <button
            onClick={handleSaveAll}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 shadow-md shadow-emerald-600/20 transition-all hover:scale-105"
          >
            <Save className="w-4 h-4" /> SAVE ALL CONTENT
          </button>
        </div>
      </div>

      {/* Navigation Sub-Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto">
        {[
          { id: 'hero', label: '1. Homepage Hero', icon: Sparkles },
          { id: 'about', label: '2. About & Mission', icon: FileText },
          { id: 'faqs', label: '3. Site FAQs', icon: HelpCircle },
          { id: 'footer', label: '4. Footer & Legal', icon: Globe },
        ].map(tab => {
          const Icon = tab.icon;
          const isActive = activeSection === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveSection(tab.id)}
              className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all whitespace-nowrap ${
                isActive 
                  ? 'bg-slate-900 text-white shadow-sm' 
                  : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <Icon className="w-3.5 h-3.5" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* SECTION 1: HOMEPAGE HERO */}
      {activeSection === 'hero' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-6">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h3 className="font-display font-black text-lg text-slate-900">Homepage Hero Banner & Callouts</h3>
              <p className="text-xs text-slate-500">Frontline visitor greeting, value proposition, and main action triggers.</p>
            </div>
            <span className="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-2.5 py-1 rounded-lg">
              Live on /
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
            <div className="space-y-4">
              <div>
                <label className="block font-bold text-slate-800 mb-1">Hero Main Badge / Pill Text</label>
                <input
                  type="text"
                  value={formData.hero?.badge || ''}
                  onChange={(e) => handleFieldChange('hero', 'badge', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-semibold"
                  placeholder="e.g. GAMING BENCHMARKS & HARDWARE INTELLIGENCE"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Primary Hero Headline (H1)</label>
                <input
                  type="text"
                  value={formData.hero?.headline || ''}
                  onChange={(e) => handleFieldChange('hero', 'headline', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900"
                  placeholder="e.g. Master Your Frame Rates. Discover Verified Gear."
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Hero Supporting Subtitle</label>
                <textarea
                  rows={3}
                  value={formData.hero?.subtitle || ''}
                  onChange={(e) => handleFieldChange('hero', 'subtitle', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                  placeholder="e.g. Real-world benchmark lab testing, hardware specs comparisons, and unbiased hardware editorial guides."
                />
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block font-bold text-slate-800 mb-1">Primary Button Label</label>
                <input
                  type="text"
                  value={formData.hero?.primaryCta || ''}
                  onChange={(e) => handleFieldChange('hero', 'primaryCta', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs"
                  placeholder="e.g. EXPLORE 15 PLATFORMS"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Secondary Button Label</label>
                <input
                  type="text"
                  value={formData.hero?.secondaryCta || ''}
                  onChange={(e) => handleFieldChange('hero', 'secondaryCta', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs"
                  placeholder="e.g. CHECK COMPATIBILITY"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Live Gaming Ticker / Announcement Text</label>
                <input
                  type="text"
                  value={formData.hero?.tickerText || ''}
                  onChange={(e) => handleFieldChange('hero', 'tickerText', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs"
                  placeholder="e.g. ⚡ NEW BENCHMARK: RTX 4080 Super tested against RTX 4070 Ti in Black Ops 6"
                />
              </div>
            </div>
          </div>

          {/* Live Mini Preview Box */}
          <div className="bg-slate-900 text-white rounded-2xl p-5 border border-slate-800 space-y-2">
            <span className="text-[10px] text-emerald-400 font-extrabold uppercase tracking-widest">
              Live Preview of Hero
            </span>
            <div className="inline-block bg-emerald-950/80 text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/40">
              {formData.hero?.badge || 'BADGE'}
            </div>
            <h4 className="font-display font-black text-lg text-white">
              {formData.hero?.headline || 'Headline'}
            </h4>
            <p className="text-xs text-slate-400 max-w-2xl">
              {formData.hero?.subtitle || 'Subtitle'}
            </p>
          </div>
        </div>
      )}

      {/* SECTION 2: ABOUT & MISSION */}
      {activeSection === 'about' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-6">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h3 className="font-display font-black text-lg text-slate-900">About Page & Editorial Mission</h3>
              <p className="text-xs text-slate-500">Core organizational information, ethics guidelines, and testing criteria.</p>
            </div>
            <span className="text-[10px] text-teal-700 font-bold bg-teal-50 px-2.5 py-1 rounded-lg">
              Live on /about/
            </span>
          </div>

          <div className="space-y-4 text-xs">
            <div>
              <label className="block font-bold text-slate-800 mb-1">About Page Headline</label>
              <input
                type="text"
                value={formData.about?.headline || ''}
                onChange={(e) => handleFieldChange('about', 'headline', e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900"
                placeholder="e.g. Independent Hardware Intelligence for Enthusiasts and Esports Pros"
              />
            </div>

            <div>
              <label className="block font-bold text-slate-800 mb-1">Mission Statement</label>
              <textarea
                rows={3}
                value={formData.about?.mission || ''}
                onChange={(e) => handleFieldChange('about', 'mission', e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                placeholder="Core mission and why Run On Console exists..."
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block font-bold text-slate-800 mb-1">Editorial Independence Pledge</label>
                <textarea
                  rows={4}
                  value={formData.about?.editorialPledge || ''}
                  onChange={(e) => handleFieldChange('about', 'editorialPledge', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                  placeholder="How products are tested independently without brand sponsorship bias..."
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Hardware Testing Lab Methodology</label>
                <textarea
                  rows={4}
                  value={formData.about?.methodology || ''}
                  onChange={(e) => handleFieldChange('about', 'methodology', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                  placeholder="Frametime analysis, 1% low metrics, thermal logging, and battery benchmarks..."
                />
              </div>
            </div>
          </div>
        </div>
      )}

      {/* SECTION 3: FAQS MANAGER */}
      {activeSection === 'faqs' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-6">
          <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pb-3 border-b border-slate-100">
            <div>
              <h3 className="font-display font-black text-lg text-slate-900">Frequently Asked Questions (FAQs)</h3>
              <p className="text-xs text-slate-500">Live Q&A items rendered on the site and automatically fed into Google FAQPage Schema.</p>
            </div>
            
            <button
              onClick={() => setIsAddingFaq(true)}
              className="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-2 px-3 rounded-xl flex items-center gap-1.5"
            >
              <Plus className="w-3.5 h-3.5" /> Add Question
            </button>
          </div>

          {/* Add FAQ Form */}
          {isAddingFaq && (
            <form onSubmit={handleAddFaq} className="p-4 bg-emerald-50/60 border border-emerald-200 rounded-2xl space-y-3 text-xs animate-page-in">
              <div className="font-bold text-emerald-950 text-sm flex items-center gap-1.5">
                <HelpCircle className="w-4 h-4 text-emerald-700" /> New FAQ Item
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Question</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. How does Run On Console verify hardware performance?"
                  value={newFaq.question}
                  onChange={(e) => setNewFaq({ ...newFaq, question: e.target.value })}
                  className="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Answer</label>
                <textarea
                  rows={3}
                  required
                  placeholder="Detailed, helpful answer with recommendations..."
                  value={newFaq.answer}
                  onChange={(e) => setNewFaq({ ...newFaq, answer: e.target.value })}
                  className="w-full bg-white border border-slate-300 rounded-xl p-3 text-xs"
                />
              </div>

              <div className="flex justify-end gap-2">
                <button
                  type="button"
                  onClick={() => setIsAddingFaq(false)}
                  className="px-3 py-1.5 rounded-lg text-slate-600 hover:bg-slate-200 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-1.5 rounded-lg shadow-xs"
                >
                  Save FAQ
                </button>
              </div>
            </form>
          )}

          {/* FAQ List */}
          <div className="space-y-3">
            {(formData.faqs || []).map((faq, index) => (
              <div key={index} className="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex items-start justify-between gap-4">
                <div className="space-y-1">
                  <div className="font-bold text-slate-900 text-xs flex items-center gap-2">
                    <span className="w-5 h-5 rounded-md bg-emerald-100 text-emerald-800 font-mono flex items-center justify-center text-[10px] shrink-0 font-extrabold">
                      Q{index + 1}
                    </span>
                    <span>{faq.question}</span>
                  </div>
                  <p className="text-[11px] text-slate-600 pl-7 leading-relaxed">
                    {faq.answer}
                  </p>
                </div>

                <button
                  onClick={() => handleDeleteFaq(index)}
                  className="text-slate-400 hover:text-rose-600 p-1.5 rounded-lg hover:bg-rose-50 transition-colors shrink-0"
                  title="Delete FAQ"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* SECTION 4: FOOTER & LEGAL */}
      {activeSection === 'footer' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-6">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <div>
              <h3 className="font-display font-black text-lg text-slate-900">Footer Details, Socials & Disclaimers</h3>
              <p className="text-xs text-slate-500">Site-wide bottom navigation, social channel links, and legal disclosures.</p>
            </div>
            <span className="text-[10px] text-slate-700 font-bold bg-slate-100 px-2.5 py-1 rounded-lg">
              All Public Pages
            </span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-5 text-xs">
            <div className="space-y-4">
              <div>
                <label className="block font-bold text-slate-800 mb-1">Brand Tagline</label>
                <input
                  type="text"
                  value={formData.footer?.tagline || ''}
                  onChange={(e) => handleFieldChange('footer', 'tagline', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-semibold"
                  placeholder="e.g. Your definitive independent source for gaming hardware benchmarks, reviews and compatibility."
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Support & Editorial Email</label>
                <input
                  type="email"
                  value={formData.footer?.contactEmail || ''}
                  onChange={(e) => handleFieldChange('footer', 'contactEmail', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs"
                  placeholder="e.g. contact@runonconsole.com"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Copyright Notice</label>
                <input
                  type="text"
                  value={formData.footer?.copyright || ''}
                  onChange={(e) => handleFieldChange('footer', 'copyright', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs"
                  placeholder="e.g. © 2026 Run On Console. All rights reserved."
                />
              </div>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block font-bold text-slate-800 mb-1">Twitter / X URL</label>
                <input
                  type="url"
                  value={formData.footer?.socialTwitter || ''}
                  onChange={(e) => handleFieldChange('footer', 'socialTwitter', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-mono"
                  placeholder="https://twitter.com/runonconsole"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">YouTube Channel URL</label>
                <input
                  type="url"
                  value={formData.footer?.socialYoutube || ''}
                  onChange={(e) => handleFieldChange('footer', 'socialYoutube', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-mono"
                  placeholder="https://youtube.com/@runonconsole"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-800 mb-1">Affiliate Disclosure Statement</label>
                <textarea
                  rows={3}
                  value={formData.footer?.affiliateNotice || ''}
                  onChange={(e) => handleFieldChange('footer', 'affiliateNotice', e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                  placeholder="e.g. Run On Console earns affiliate commissions from qualifying purchases through our tested product links."
                />
              </div>
            </div>
          </div>
        </div>
      )}

    </div>
  );
};
