import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { Search, Mail, Phone, User, CheckCircle, Clock, DollarSign, PenTool } from 'lucide-react';

export const AdminSubmissionsList = () => {
  const { guestSubmissions } = useApp();
  const [search, setSearch] = useState('');

  const filtered = (guestSubmissions || []).filter(s => 
    s.name.toLowerCase().includes(search.toLowerCase()) ||
    s.email.toLowerCase().includes(search.toLowerCase()) ||
    (s.title || "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-4 max-w-6xl">
      
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">WRITE FOR US – GUEST POST LEADS & INVOICES</h2>
          <p className="text-xs text-slate-500 font-medium">Manage incoming sponsored article submissions and paid guest post requests (Starting from $50)</p>
        </div>

        <div className="relative w-full sm:w-64">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input 
            type="text" 
            placeholder="Search leads by author, email..." 
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
          />
        </div>
      </div>

      <div className="space-y-4">
        {filtered.length === 0 ? (
          <div className="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-400 text-xs">
            No guest post submissions yet.
          </div>
        ) : (
          filtered.map(sub => (
            <div key={sub.id} className="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm space-y-3">
              
              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-xs">
                    {(sub.name || 'G').slice(0, 2).toUpperCase()}
                  </div>
                  <div>
                    <h3 className="font-display font-bold text-sm text-slate-900">{sub.name}</h3>
                    <div className="flex items-center gap-3 text-[11px] text-slate-500">
                      <span className="flex items-center gap-1"><Mail className="w-3 h-3 text-emerald-600" /> {sub.email}</span>
                      <span className="flex items-center gap-1"><Phone className="w-3 h-3 text-emerald-600" /> {sub.contact}</span>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <span className="bg-emerald-600 text-white font-extrabold text-xs px-3 py-1 rounded-lg">
                    {sub.price || "$50"}
                  </span>
                  <span className="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold px-2 py-0.5 rounded">
                    {sub.status || 'Pending Review'}
                  </span>
                </div>
              </div>

              <div>
                <div className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider mb-0.5">
                  Proposed Article: {sub.niche}
                </div>
                <h4 className="font-display font-bold text-slate-900 text-sm">{sub.title}</h4>
                <p className="text-xs text-slate-600 mt-1 leading-relaxed bg-slate-50 p-3 rounded-xl border border-slate-100">
                  "{sub.pitch}"
                </p>
              </div>

              <div className="flex items-center justify-between text-xs pt-1">
                <span className="text-slate-400 font-medium">Submitted: {sub.date} • Selected Plan: <strong>{sub.plan}</strong></span>
                <button 
                  onClick={() => alert(`Invoice generated for ${sub.name} (${sub.price}). Dispatched to ${sub.email}`)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-1.5 px-3 rounded-lg shadow-sm"
                >
                  Send Invoice & Approve →
                </button>
              </div>

            </div>
          ))
        )}
      </div>

    </div>
  );
};
