import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { X, CheckCircle2, AlertTriangle, MessageSquare, Send, Star, Zap, SlidersHorizontal, Clock, User } from 'lucide-react';
import confetti from 'canvas-confetti';

export const ReviewDetailModal = () => {
  const { selectedReview, setSelectedReview, toggleCompare, compareIds, addComment } = useApp();
  const [commentName, setCommentName] = useState("");
  const [commentText, setCommentText] = useState("");
  const [userRating, setUserRating] = useState(5);

  if (!selectedReview) return null;

  const isComparing = compareIds.includes(selectedReview.id);
  const ratingVal = selectedReview.rating || (selectedReview.rocScore / 2).toFixed(1);

  const handleCommentSubmit = (e) => {
    e.preventDefault();
    if (!commentText.trim()) return;

    const newComment = {
      id: 'c-' + Date.now(),
      user: commentName.trim() || 'TacticalGamer',
      avatar: (commentName.trim() || 'TG').slice(0, 2).toUpperCase(),
      rating: Number(userRating),
      text: commentText.trim(),
      date: 'Just now'
    };

    addComment(selectedReview.id, newComment);
    setCommentText("");
    setCommentName("");

    try {
      confetti({ particleCount: 40, spread: 60, origin: { y: 0.8 } });
    } catch (err) {}
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
      
      {/* Modal Card */}
      <div className="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden my-8 border border-slate-200">
        
        {/* Close Button */}
        <button 
          onClick={() => setSelectedReview(null)}
          className="absolute top-4 right-4 z-20 w-10 h-10 bg-slate-900/80 hover:bg-slate-900 text-white rounded-full flex items-center justify-center transition-colors shadow-lg"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header Hero Banner */}
        <div className="relative w-full h-64 sm:h-80 bg-slate-900 overflow-hidden">
          <img 
            src={selectedReview.image} 
            alt={selectedReview.title}
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>

          <div className="absolute bottom-6 left-6 right-6 flex flex-col sm:flex-row items-start sm:items-end justify-between gap-4">
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <span className="bg-blue-600 text-white text-[11px] font-extrabold uppercase px-2.5 py-1 rounded tracking-wider">
                  {selectedReview.category}
                </span>
                <span className="text-xs text-slate-300 flex items-center gap-1">
                  <Clock className="w-3.5 h-3.5" /> {selectedReview.date}
                </span>
              </div>
              <h2 className="font-display font-extrabold text-2xl sm:text-3xl text-white">
                {selectedReview.title}
              </h2>
              {selectedReview.subtitle && (
                <p className="text-xs sm:text-sm text-blue-400 font-semibold">
                  {selectedReview.subtitle}
                </p>
              )}
            </div>

            {/* Score Box */}
            <div className="bg-[#0B132B] border border-blue-500/40 p-4 rounded-xl text-center shrink-0 shadow-lg">
              <div className="text-[10px] text-slate-400 uppercase tracking-widest font-bold">FRAG SCORE</div>
              <div className="font-display font-extrabold text-3xl text-blue-400">
                {selectedReview.rocScore ? selectedReview.rocScore.toFixed(1) : ratingVal}
              </div>
              <div className="text-[10px] text-emerald-400 font-semibold">/ 10 VERIFIED</div>
            </div>
          </div>
        </div>

        {/* Modal Main Body */}
        <div className="p-6 sm:p-8 space-y-6 max-h-[60vh] overflow-y-auto text-slate-800">
          
          {/* Executive Summary */}
          <div>
            <h3 className="font-display font-extrabold text-lg text-slate-900 mb-2 flex items-center gap-2 border-b border-slate-100 pb-2">
              <Zap className="w-5 h-5 text-blue-600" /> EXECUTIVE VERDICT
            </h3>
            <p className="text-sm text-slate-600 leading-relaxed">
              {selectedReview.summary}
            </p>
          </div>

          {/* Sub-scores Progress Breakdown */}
          {selectedReview.subScores && (
            <div>
              <h3 className="font-display font-extrabold text-lg text-slate-900 mb-3 border-b border-slate-100 pb-2">
                PERFORMANCE METRICS
              </h3>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {Object.entries(selectedReview.subScores).map(([metric, score]) => (
                  <div key={metric} className="bg-slate-50 p-3.5 rounded-lg border border-slate-200">
                    <div className="flex justify-between items-center text-xs font-semibold mb-1.5">
                      <span className="text-slate-600 uppercase tracking-wider">{metric.replace(/([A-Z])/g, ' $1')}</span>
                      <span className="text-blue-600 font-bold">{score.toFixed(1)} / 10</span>
                    </div>
                    <div className="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                      <div 
                        className="h-full bg-blue-600 rounded-full"
                        style={{ width: `${(score / 10) * 100}%` }}
                      ></div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Technical Specifications Table */}
          {selectedReview.specs && (
            <div>
              <h3 className="font-display font-extrabold text-lg text-slate-900 mb-3 border-b border-slate-100 pb-2">
                TECHNICAL SPECIFICATIONS
              </h3>
              <div className="bg-slate-50 rounded-xl border border-slate-200 divide-y divide-slate-200 text-xs">
                {Object.entries(selectedReview.specs).map(([specKey, specVal]) => (
                  <div key={specKey} className="flex flex-col sm:flex-row p-3 hover:bg-slate-100/60 transition-colors">
                    <span className="w-full sm:w-1/3 text-slate-500 font-semibold">{specKey}</span>
                    <span className="w-full sm:w-2/3 text-slate-900 font-bold">{specVal}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Pros & Cons */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* Pros */}
            <div className="bg-emerald-50/60 border border-emerald-200 rounded-xl p-4">
              <h4 className="font-display font-bold text-sm text-emerald-800 mb-3 flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-600" /> WHAT WE LIKE
              </h4>
              <ul className="space-y-1.5 text-xs text-emerald-950">
                {selectedReview.pros?.map((pro, idx) => (
                  <li key={idx} className="flex items-start gap-2">
                    <span className="text-emerald-600 font-bold">+</span>
                    <span>{pro}</span>
                  </li>
                ))}
              </ul>
            </div>

            {/* Cons */}
            <div className="bg-rose-50/60 border border-rose-200 rounded-xl p-4">
              <h4 className="font-display font-bold text-sm text-rose-800 mb-3 flex items-center gap-2">
                <AlertTriangle className="w-4 h-4 text-rose-600" /> WHAT COULD BE BETTER
              </h4>
              <ul className="space-y-1.5 text-xs text-rose-950">
                {selectedReview.cons?.map((con, idx) => (
                  <li key={idx} className="flex items-start gap-2">
                    <span className="text-rose-600 font-bold">-</span>
                    <span>{con}</span>
                  </li>
                ))}
              </ul>
            </div>
          </div>

          {/* User Reviews & Comments */}
          <div className="border-t border-slate-100 pt-6">
            <h3 className="font-display font-extrabold text-lg text-slate-900 mb-4 flex items-center gap-2">
              <MessageSquare className="w-5 h-5 text-blue-600" /> 
              COMMUNITY REVIEWS [{selectedReview.comments?.length || 0}]
            </h3>

            {/* Form */}
            <form onSubmit={handleCommentSubmit} className="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4 space-y-3">
              <div className="flex flex-col sm:flex-row gap-3">
                <input 
                  type="text"
                  placeholder="Your Name / Gamer Tag"
                  value={commentName}
                  onChange={(e) => setCommentName(e.target.value)}
                  className="flex-1 bg-white border border-slate-300 rounded-lg px-3 py-2 text-xs focus:outline-none focus:border-blue-500"
                />
                
                <div className="flex items-center gap-1 bg-white border border-slate-300 rounded-lg px-3 py-1">
                  <span className="text-xs text-slate-500 mr-1 font-semibold">Rating:</span>
                  {[1, 2, 3, 4, 5].map(star => (
                    <button
                      key={star}
                      type="button"
                      onClick={() => setUserRating(star)}
                      className={`text-sm ${star <= userRating ? 'text-amber-500' : 'text-slate-300'}`}
                    >
                      ★
                    </button>
                  ))}
                </div>
              </div>

              <textarea 
                placeholder="Write your feedback..."
                value={commentText}
                onChange={(e) => setCommentText(e.target.value)}
                rows={2}
                className="w-full bg-white border border-slate-300 rounded-lg p-3 text-xs focus:outline-none focus:border-blue-500"
              ></textarea>

              <div className="flex justify-end">
                <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2 px-4 rounded-lg flex items-center gap-2">
                  <Send className="w-3.5 h-3.5" /> POST REVIEW
                </button>
              </div>
            </form>

            {/* List */}
            <div className="space-y-3">
              {selectedReview.comments?.map(c => (
                <div key={c.id} className="bg-white border border-slate-200 rounded-lg p-3.5 flex gap-3 items-start shadow-sm">
                  <div className="w-8 h-8 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                    {c.avatar}
                  </div>
                  <div className="flex-1">
                    <div className="flex justify-between items-center mb-1">
                      <span className="text-xs font-bold text-slate-900">{c.user}</span>
                      <div className="flex items-center gap-2">
                        <span className="text-amber-500 text-xs">{"★".repeat(c.rating)}</span>
                        <span className="text-[10px] text-slate-400">{c.date}</span>
                      </div>
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">{c.text}</p>
                  </div>
                </div>
              ))}
            </div>

          </div>

        </div>

        {/* Footer */}
        <div className="bg-slate-50 border-t border-slate-200 p-4 flex items-center justify-between">
          <button 
            onClick={() => toggleCompare(selectedReview.id)}
            className={`text-xs font-bold px-4 py-2 rounded-lg border transition-colors flex items-center gap-2 ${
              isComparing ? 'bg-green-600 text-white border-green-600' : 'bg-white border-slate-300 text-slate-700 hover:border-blue-600'
            }`}
          >
            <SlidersHorizontal className="w-4 h-4" />
            <span>{isComparing ? 'ADDED TO COMPARE' : 'COMPARE SPECS'}</span>
          </button>

          <button 
            onClick={() => setSelectedReview(null)}
            className="bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold px-5 py-2 rounded-lg"
          >
            CLOSE
          </button>
        </div>

      </div>

    </div>
  );
};
