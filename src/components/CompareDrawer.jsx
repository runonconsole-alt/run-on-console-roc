import React from 'react';
import { useApp } from '../context/AppContext';
import { X, SlidersHorizontal, Trash2 } from 'lucide-react';

export const CompareDrawer = () => {
  const { compareIds, toggleCompare, clearCompare, isCompareOpen, setIsCompareOpen, reviews } = useApp();

  if (!isCompareOpen || compareIds.length === 0) return null;

  const compareItems = reviews.filter(r => compareIds.includes(r.id));

  // Collect unique spec keys across selected items
  const allSpecKeys = Array.from(
    new Set(compareItems.flatMap(item => Object.keys(item.specs || {})))
  );

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      
      <div className="relative w-full max-w-5xl bg-white rounded-3xl shadow-2xl overflow-hidden my-6 border-2 border-emerald-500">
        
        {/* Header */}
        <div className="bg-slate-950 text-white p-4 sm:p-5 flex items-center justify-between border-b border-emerald-500/30">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center shadow-md">
              <SlidersHorizontal className="w-5 h-5 text-white" />
            </div>
            <div>
              <h3 className="font-display font-bold text-base sm:text-lg text-white">
                HARDWARE SPEC COMPARISON MATRIX
              </h3>
              <p className="text-[11px] text-emerald-400 font-medium">Comparing {compareItems.length} items side-by-side</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <button 
              onClick={clearCompare} 
              className="text-xs text-rose-400 hover:text-rose-300 hover:underline flex items-center gap-1 font-bold"
            >
              <Trash2 className="w-3.5 h-3.5" /> Clear All
            </button>
            <button 
              onClick={() => setIsCompareOpen(false)}
              className="w-8 h-8 rounded-full bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Comparison Matrix Table */}
        <div className="p-4 sm:p-6 overflow-x-auto max-h-[70vh]" style={{ scrollbarWidth: 'thin' }}>
          <table className="w-full min-w-[580px] text-left border-collapse text-xs">
            <thead>
              <tr className="border-b border-slate-200">
                <th className="p-3 text-slate-500 font-bold w-1/4 uppercase tracking-wider">SPECIFICATION</th>
                {compareItems.map(item => (
                  <th key={item.id} className="p-3 text-slate-900 w-1/4 align-top">
                    <div className="relative bg-slate-50 border border-slate-200 rounded-2xl p-3 text-center mb-2 shadow-xs">
                      <button 
                        onClick={() => toggleCompare(item.id)}
                        className="absolute top-2 right-2 text-slate-400 hover:text-rose-600 text-xs font-bold"
                      >
                        ✕
                      </button>
                      <img src={item.image} alt={item.title} className="w-full h-24 object-cover rounded-xl mb-2" />
                      <h4 className="font-display font-bold text-xs sm:text-sm text-slate-900 line-clamp-1">{item.title}</h4>
                      <div className="text-emerald-600 font-extrabold text-xs mt-1">★ {item.rocScore || item.rating} / 10</div>
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              
              {/* Category */}
              <tr className="hover:bg-slate-50">
                <td className="p-3 font-bold text-slate-600">Category</td>
                {compareItems.map(item => (
                  <td key={item.id} className="p-3 text-emerald-700 font-bold">{item.category}</td>
                ))}
              </tr>

              {/* ROC Score */}
              <tr className="hover:bg-slate-50">
                <td className="p-3 font-bold text-slate-600">ROC Score</td>
                {compareItems.map(item => (
                  <td key={item.id} className="p-3 text-emerald-600 font-extrabold text-sm">
                    {item.rocScore ? item.rocScore.toFixed(1) : "9.5"} / 10.0
                  </td>
                ))}
              </tr>

              {/* Dynamic Specs Rows */}
              {allSpecKeys.map(specKey => (
                <tr key={specKey} className="hover:bg-slate-50">
                  <td className="p-3 font-semibold text-slate-600">{specKey}</td>
                  {compareItems.map(item => (
                    <td key={item.id} className="p-3 text-slate-800">
                      {item.specs?.[specKey] || <span className="text-slate-400">-</span>}
                    </td>
                  ))}
                </tr>
              ))}

            </tbody>
          </table>
        </div>

        <div className="bg-slate-50 border-t border-slate-200 p-4 text-right">
          <button 
            onClick={() => setIsCompareOpen(false)}
            className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2 px-6 rounded-xl shadow-md transition-all hover:scale-102"
          >
            Close Matrix
          </button>
        </div>

      </div>

    </div>
  );
};
