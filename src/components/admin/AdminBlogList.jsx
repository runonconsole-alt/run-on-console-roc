import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { Search, Edit3, Trash2, Plus, FileText, Clock } from 'lucide-react';

export const AdminBlogList = ({ onEdit }) => {
  const { blogs, deleteBlog, setAdminTab } = useApp();
  const [search, setSearch] = useState("");

  const filtered = blogs.filter(b => 
    b.title.toLowerCase().includes(search.toLowerCase()) ||
    (b.category || "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-4 max-w-6xl">
      
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">BLOG ARTICLES & EDITORIAL GUIDES</h2>
          <p className="text-xs text-slate-500 font-medium">Publish, edit and manage gaming guides and do-follow internal linking</p>
        </div>

        <div className="flex items-center gap-3 w-full sm:w-auto">
          <div className="relative flex-1 sm:w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input 
              type="text" 
              placeholder="Search blogs..." 
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
            />
          </div>

          <button 
            onClick={() => setAdminTab('new-blog')}
            className="bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-2.5 px-4 rounded-xl flex items-center gap-1.5 shrink-0"
          >
            <Plus className="w-4 h-4" /> Write Blog
          </button>
        </div>
      </div>

      <div className="bg-white border border-slate-200 rounded-2xl overflow-x-auto shadow-sm">
        <table className="w-full text-left border-collapse text-xs">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold uppercase tracking-wider text-[11px]">
              <th className="p-3.5">BLOG TITLE</th>
              <th className="p-3.5">CATEGORY</th>
              <th className="p-3.5">AUTHOR</th>
              <th className="p-3.5">DATE</th>
              <th className="p-3.5 text-right">ACTIONS</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 font-medium">
            {filtered.length === 0 ? (
              <tr>
                <td colSpan={5} className="p-6 text-center text-slate-400">
                  No blog articles found.
                </td>
              </tr>
            ) : (
              filtered.map(item => (
                <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                  <td className="p-3.5">
                    <div className="flex items-center gap-3">
                      <img src={item.image} alt={item.title} className="w-12 h-10 rounded-xl object-cover border border-slate-200" />
                      <div>
                        <div className="font-display font-bold text-slate-900 text-sm line-clamp-1">{item.title}</div>
                        <div className="text-[11px] text-slate-400 line-clamp-1">{item.subtitle}</div>
                      </div>
                    </div>
                  </td>
                  <td className="p-3.5">
                    <span className="bg-blue-50 text-blue-700 font-bold text-[10px] px-2 py-0.5 rounded uppercase">
                      {item.category}
                    </span>
                  </td>
                  <td className="p-3.5 text-slate-700 font-semibold">{item.author}</td>
                  <td className="p-3.5 text-slate-500">{item.date}</td>
                  <td className="p-3.5 text-right space-x-2">
                    <button 
                      onClick={() => onEdit(item)}
                      className="p-2 bg-slate-100 hover:bg-emerald-600 hover:text-white rounded-xl text-slate-600 transition-colors"
                      title="Edit Blog"
                    >
                      <Edit3 className="w-3.5 h-3.5" />
                    </button>
                    <button 
                      onClick={() => {
                        if (confirm(`Delete blog "${item.title}"?`)) {
                          deleteBlog(item.id);
                        }
                      }}
                      className="p-2 bg-rose-50 hover:bg-rose-600 hover:text-white rounded-xl text-rose-600 transition-colors"
                      title="Delete Blog"
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
  );
};
