import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { Search, Edit3, Trash2, Award } from 'lucide-react';

export const AdminReviewList = ({ onEdit }) => {
  const { reviews, deleteReview, toggleFeatured } = useApp();
  const [search, setSearch] = useState("");

  const filtered = reviews.filter(r => 
    r.title.toLowerCase().includes(search.toLowerCase()) ||
    r.category.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-4 max-w-6xl">
      
      {/* Search Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">HARDWARE CATALOG DATABASE</h2>
          <p className="text-xs text-slate-500 font-medium">Manage all published reviews, ratings and front-page featured status</p>
        </div>

        <div className="relative w-full sm:w-72">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input 
            type="text" 
            placeholder="Search catalog..." 
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-white border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-blue-500"
          />
        </div>
      </div>

      {/* Table */}
      <div className="bg-white border border-slate-200 rounded-xl overflow-x-auto shadow-sm">
        <table className="w-full text-left border-collapse text-xs">
          <thead>
            <tr className="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider">
              <th className="p-3.5">HARDWARE TITLE</th>
              <th className="p-3.5">CATEGORY</th>
              <th className="p-3.5">RATING</th>
              <th className="p-3.5">FEATURED</th>
              <th className="p-3.5">DATE</th>
              <th className="p-3.5 text-right">ACTIONS</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {filtered.length === 0 ? (
              <tr>
                <td colSpan={6} className="p-6 text-center text-slate-400">
                  No hardware reviews match your search.
                </td>
              </tr>
            ) : (
              filtered.map(item => (
                <tr key={item.id} className="hover:bg-slate-50 transition-colors">
                  <td className="p-3.5">
                    <div className="flex items-center gap-3">
                      <img src={item.image} alt={item.title} className="w-10 h-10 rounded-lg object-cover border border-slate-200" />
                      <div>
                        <div className="font-display font-bold text-slate-900 text-sm line-clamp-1">{item.title}</div>
                        <div className="text-[11px] text-slate-400 line-clamp-1">{item.subtitle}</div>
                      </div>
                    </div>
                  </td>
                  <td className="p-3.5">
                    <span className="bg-blue-50 text-blue-600 font-bold text-[10px] px-2 py-0.5 rounded uppercase">
                      {item.category}
                    </span>
                  </td>
                  <td className="p-3.5">
                    <span className="font-bold text-amber-500 text-xs">★ {item.rating || (item.rocScore/2).toFixed(1)}</span>
                  </td>
                  <td className="p-3.5">
                    <button 
                      onClick={() => toggleFeatured(item.id)}
                      className={`px-2.5 py-1 rounded-md text-xs font-bold flex items-center gap-1 border transition-colors ${
                        item.featured 
                          ? 'border-blue-600 bg-blue-50 text-blue-600' 
                          : 'border-slate-200 text-slate-400 hover:text-slate-700'
                      }`}
                    >
                      <Award className="w-3.5 h-3.5" />
                      <span>{item.featured ? 'FEATURED' : 'OFF'}</span>
                    </button>
                  </td>
                  <td className="p-3.5 text-slate-500">{item.date}</td>
                  <td className="p-3.5 text-right space-x-2">
                    <button 
                      onClick={() => onEdit(item)}
                      className="p-2 bg-slate-100 hover:bg-blue-600 hover:text-white rounded-lg text-slate-600 transition-colors"
                      title="Edit Review"
                    >
                      <Edit3 className="w-3.5 h-3.5" />
                    </button>
                    <button 
                      onClick={() => {
                        if (confirm(`Are you sure you want to delete "${item.title}"?`)) {
                          deleteReview(item.id);
                        }
                      }}
                      className="p-2 bg-rose-50 hover:bg-rose-600 hover:text-white rounded-lg text-rose-600 transition-colors"
                      title="Delete Review"
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
