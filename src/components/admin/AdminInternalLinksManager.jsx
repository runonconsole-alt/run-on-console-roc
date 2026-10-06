import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { 
  Link2, Plus, Trash2, Edit3, CheckCircle2, AlertTriangle, 
  ExternalLink, Search, Sparkles, ArrowRight, ShieldCheck, RefreshCw, Zap
} from 'lucide-react';

export const AdminInternalLinksManager = () => {
  const { 
    internalLinks = [], 
    addInternalLink, 
    deleteInternalLink, 
    blogs = [], 
    products = [],
    showNotification 
  } = useApp();

  const [search, setSearch] = useState('');
  const [isAddLinkOpen, setIsAddLinkOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('links'); // 'links' | 'rules' | 'audit'

  // New Link Form
  const [newLink, setNewLink] = useState({
    sourcePage: '/blogs/call-of-duty-black-ops-6-everything-we-know-so-far/',
    targetPage: '/products/asus-rog-strix-geforce-rtx-4070-ti-super/',
    anchorText: 'RTX 4070 Ti Super benchmark results',
    type: 'In-Content Editorial',
    rel: 'follow'
  });

  // Keyword Auto-Link Rules
  const [keywordRules, setKeywordRules] = useState([
    { id: 1, keyword: 'RTX 4070', target: '/products/asus-rog-strix-geforce-rtx-4070-ti-super/', active: true },
    { id: 2, keyword: 'Steam Deck', target: '/products/valve-steam-deck-oled/', active: true },
    { id: 3, keyword: 'Black Ops 6', target: '/blogs/call-of-duty-black-ops-6-everything-we-know-so-far/', active: true },
    { id: 4, keyword: 'System Requirements', target: '/compatibility/', active: true },
    { id: 5, keyword: 'ROG Ally X', target: '/products/asus-rog-ally-x/', active: true },
    { id: 6, keyword: 'Gaming Headset', target: '/products/hyperx-cloud-iii-wireless/', active: true }
  ]);

  const [newKeywordRule, setNewKeywordRule] = useState({ keyword: '', target: '' });

  const handleAddLink = (e) => {
    e.preventDefault();
    if (!newLink.sourcePage || !newLink.targetPage || !newLink.anchorText) return;

    addInternalLink({
      ...newLink,
      id: `link-${Date.now()}`,
      createdAt: new Date().toISOString()
    });

    setIsAddLinkOpen(false);
    showNotification('Internal link created and verified!');
  };

  const handleAddKeywordRule = (e) => {
    e.preventDefault();
    if (!newKeywordRule.keyword || !newKeywordRule.target) return;
    setKeywordRules(prev => [...prev, { id: Date.now(), ...newKeywordRule, active: true }]);
    setNewKeywordRule({ keyword: '', target: '' });
    showNotification('Auto-link keyword rule added!');
  };

  const toggleRule = (id) => {
    setKeywordRules(prev => prev.map(r => r.id === id ? { ...r, active: !r.active } : r));
  };

  const deleteRule = (id) => {
    setKeywordRules(prev => prev.filter(r => r.id !== id));
  };

  const filteredLinks = internalLinks.filter(l => 
    (l.sourcePage || '').toLowerCase().includes(search.toLowerCase()) ||
    (l.targetPage || '').toLowerCase().includes(search.toLowerCase()) ||
    (l.anchorText || '').toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6 max-w-7xl animate-page-in">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[10px] text-purple-700 bg-purple-100 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
              Search Authority & PageRank Architecture
            </span>
          </div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">INTERNAL LINKING & TOPIC CLUSTER HUB</h2>
          <p className="text-xs text-slate-500 font-medium">
            Manage do-follow contextual links between articles, reviews, hubs, and auto-link keyword rules
          </p>
        </div>

        <button
          onClick={() => setIsAddLinkOpen(true)}
          className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 shadow-md shadow-emerald-600/20 transition-all hover:scale-105"
        >
          <Plus className="w-4 h-4" /> ADD INTERNAL LINK
        </button>
      </div>

      {/* Internal Link Health Metrics */}
      <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Active Internal Links</div>
          <div className="font-display font-black text-2xl text-slate-900">{internalLinks.length}</div>
          <span className="text-[10px] font-semibold text-emerald-600">Connecting pages & reviews</span>
        </div>

        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Auto-Link Keyword Rules</div>
          <div className="font-display font-black text-2xl text-purple-700">{keywordRules.length}</div>
          <span className="text-[10px] font-semibold text-purple-600">Automated in-text links</span>
        </div>

        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Orphan Pages Detected</div>
          <div className="font-display font-black text-2xl text-emerald-600">0</div>
          <span className="text-[10px] font-semibold text-emerald-600">All pages have inbound links!</span>
        </div>

        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">Link Equity Score</div>
          <div className="font-display font-black text-2xl text-slate-900">96/100</div>
          <span className="text-[10px] font-semibold text-emerald-600">Strong topical clustering</span>
        </div>
      </div>

      {/* Sub Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button
          onClick={() => setActiveTab('links')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            activeTab === 'links' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border'
          }`}
        >
          <Link2 className="w-3.5 h-3.5" />
          <span>Internal Links Registry ({internalLinks.length})</span>
        </button>

        <button
          onClick={() => setActiveTab('rules')}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all ${
            activeTab === 'rules' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:bg-slate-100 border'
          }`}
        >
          <Zap className="w-3.5 h-3.5" />
          <span>Auto-Link Keyword Rules ({keywordRules.length})</span>
        </button>
      </div>

      {/* ADD LINK MODAL */}
      {isAddLinkOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 space-y-4 animate-page-in">
            <div className="flex justify-between items-center pb-3 border-b border-slate-100">
              <h3 className="font-display font-black text-lg text-slate-900 flex items-center gap-2">
                <Link2 className="w-5 h-5 text-emerald-600" /> Create Internal Link Mapping
              </h3>
              <button 
                onClick={() => setIsAddLinkOpen(false)}
                className="text-slate-400 hover:text-slate-700 font-bold text-sm"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleAddLink} className="space-y-4 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Source Page URL</label>
                <input
                  type="text"
                  required
                  placeholder="/blogs/your-source-blog/"
                  value={newLink.sourcePage}
                  onChange={(e) => setNewLink({ ...newLink, sourcePage: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Target Page URL</label>
                <input
                  type="text"
                  required
                  placeholder="/products/your-target-review/"
                  value={newLink.targetPage}
                  onChange={(e) => setNewLink({ ...newLink, targetPage: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Target Anchor Text</label>
                <input
                  type="text"
                  required
                  placeholder="e.g. read our RTX 4070 benchmark guide"
                  value={newLink.anchorText}
                  onChange={(e) => setNewLink({ ...newLink, anchorText: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Placement Type</label>
                  <select
                    value={newLink.type}
                    onChange={(e) => setNewLink({ ...newLink, type: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  >
                    <option value="In-Content Editorial">In-Content Editorial</option>
                    <option value="Related Guide Card">Related Guide Card</option>
                    <option value="Product Mention">Product Mention</option>
                    <option value="Category Breadcrumb">Category Breadcrumb</option>
                  </select>
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Rel Attribute</label>
                  <select
                    value={newLink.rel}
                    onChange={(e) => setNewLink({ ...newLink, rel: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  >
                    <option value="follow">follow (Pass PageRank Authority)</option>
                    <option value="nofollow">nofollow</option>
                  </select>
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsAddLinkOpen(false)}
                  className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2 rounded-xl shadow-md"
                >
                  Save Internal Link
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* TAB 1: LINKS REGISTRY */}
      {activeTab === 'links' && (
        <div className="space-y-4">
          <div className="relative w-full sm:w-80">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input
              type="text"
              placeholder="Search by anchor, source, or target..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs focus:outline-none focus:border-emerald-500"
            />
          </div>

          <div className="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-xs">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold uppercase tracking-wider text-[11px]">
                  <th className="p-3.5">SOURCE PAGE</th>
                  <th className="p-3.5">ANCHOR TEXT</th>
                  <th className="p-3.5">TARGET DESTINATION</th>
                  <th className="p-3.5">TYPE</th>
                  <th className="p-3.5 text-right">ACTION</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium">
                {filteredLinks.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="p-6 text-center text-slate-400">
                      No internal links match your search.
                    </td>
                  </tr>
                ) : (
                  filteredLinks.map((link) => (
                    <tr key={link.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="p-3.5 font-mono text-[11px] text-slate-600 max-w-xs truncate" title={link.sourcePage}>
                        {link.sourcePage}
                      </td>
                      <td className="p-3.5 font-bold text-slate-900">
                        <span className="bg-emerald-50 text-emerald-800 px-2 py-0.5 rounded border border-emerald-200">
                          "{link.anchorText}"
                        </span>
                      </td>
                      <td className="p-3.5 font-mono text-[11px] text-emerald-700 max-w-xs truncate flex items-center gap-1.5" title={link.targetPage}>
                        <ArrowRight className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>{link.targetPage}</span>
                      </td>
                      <td className="p-3.5">
                        <span className="text-[10px] font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded">
                          {link.type || 'Editorial'}
                        </span>
                      </td>
                      <td className="p-3.5 text-right">
                        <button
                          onClick={() => deleteInternalLink(link.id)}
                          className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                          title="Delete Link"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* TAB 2: KEYWORD RULES */}
      {activeTab === 'rules' && (
        <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-6">
          <div className="pb-3 border-b border-slate-100">
            <h3 className="font-display font-black text-lg text-slate-900">Auto-Link Keyword Dictionary</h3>
            <p className="text-xs text-slate-500">
              When these exact hardware terms appear across blog posts, they automatically transform into clean internal hyperlinks.
            </p>
          </div>

          {/* Add Rule Form */}
          <form onSubmit={handleAddKeywordRule} className="p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col sm:flex-row gap-3 items-end text-xs">
            <div className="flex-1 w-full">
              <label className="block font-bold text-slate-700 mb-1">Target Keyword</label>
              <input
                type="text"
                required
                placeholder="e.g. GeForce RTX 4080"
                value={newKeywordRule.keyword}
                onChange={(e) => setNewKeywordRule({ ...newKeywordRule, keyword: e.target.value })}
                className="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold"
              />
            </div>

            <div className="flex-1 w-full">
              <label className="block font-bold text-slate-700 mb-1">Destination Target URL</label>
              <input
                type="text"
                required
                placeholder="/products/rtx-4080-review/"
                value={newKeywordRule.target}
                onChange={(e) => setNewKeywordRule({ ...newKeywordRule, target: e.target.value })}
                className="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
              />
            </div>

            <button
              type="submit"
              className="bg-purple-700 hover:bg-purple-800 text-white font-bold py-2 px-4 rounded-xl flex items-center gap-1.5 shadow-sm shrink-0"
            >
              <Plus className="w-4 h-4" /> Add Rule
            </button>
          </form>

          {/* Rules List */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {keywordRules.map(rule => (
              <div key={rule.id} className="p-3.5 border border-slate-200 rounded-xl bg-white flex items-center justify-between gap-3 shadow-xs">
                <div className="space-y-0.5 truncate">
                  <div className="flex items-center gap-2">
                    <span className="font-extrabold text-xs text-slate-900">"{rule.keyword}"</span>
                    <span className={`text-[9px] font-bold px-1.5 py-0.2 rounded ${rule.active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'}`}>
                      {rule.active ? 'Active' : 'Disabled'}
                    </span>
                  </div>
                  <div className="text-[11px] font-mono text-purple-700 truncate">{rule.target}</div>
                </div>

                <div className="flex items-center gap-1">
                  <button
                    onClick={() => toggleRule(rule.id)}
                    className="p-1 rounded text-slate-400 hover:text-slate-700 text-[11px] font-bold underline"
                  >
                    {rule.active ? 'Disable' : 'Enable'}
                  </button>
                  <button
                    onClick={() => deleteRule(rule.id)}
                    className="p-1 rounded text-slate-400 hover:text-rose-600"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

    </div>
  );
};
