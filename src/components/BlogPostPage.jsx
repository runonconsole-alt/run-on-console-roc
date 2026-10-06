import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { 
  ArrowLeft, Star, ShoppingCart, ExternalLink, ShieldCheck, CheckCircle2, 
  AlertTriangle, Clock, User, MessageSquare, Send, Tag, Share2, Bookmark, SlidersHorizontal 
} from 'lucide-react';
import confetti from 'canvas-confetti';

export const BlogPostPage = () => {
  const { selectedArticle, setSelectedArticle, toggleCompare, compareIds, addComment } = useApp();
  
  const [commentName, setCommentName] = useState("");
  const [commentEmail, setCommentEmail] = useState("");
  const [commentText, setCommentText] = useState("");
  const [userRating, setUserRating] = useState(5);
  const [copied, setCopied] = useState(false);

  if (!selectedArticle) return null;

  const isComparing = compareIds.includes(selectedArticle.id);
  const amazonLink = selectedArticle.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20";
  const bestbuyLink = selectedArticle.affiliateLinks?.bestbuy || "https://bestbuy.com";
  const officialLink = selectedArticle.affiliateLinks?.official || "https://store.com";

  const handleShare = () => {
    navigator.clipboard?.writeText(window.location.href);
    setCopied(true);
    setTimeout(() => setCopied(false), 2500);
  };

  const handleCommentSubmit = (e) => {
    e.preventDefault();
    if (!commentText.trim()) return;

    const newComment = {
      id: 'c-' + Date.now(),
      user: commentName.trim() || 'TacticalGamer',
      email: commentEmail.trim() || 'reader@runonconsole.com',
      avatar: (commentName.trim() || 'TG').slice(0, 2).toUpperCase(),
      rating: Number(userRating),
      text: commentText.trim(),
      date: 'Just now'
    };

    addComment(selectedArticle.id, newComment);
    setCommentText("");
    setCommentName("");
    setCommentEmail("");

    try {
      confetti({ particleCount: 40, spread: 60, origin: { y: 0.8 } });
    } catch (err) {}
  };

  return (
    <article className="max-w-4xl mx-auto py-6 space-y-8">
      
      {/* Top Breadcrumbs & Back button */}
      <div className="flex items-center justify-between">
        <button 
          onClick={() => setSelectedArticle(null)}
          className="bg-white border border-slate-300 hover:border-blue-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-lg flex items-center gap-2 transition-colors shadow-sm"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Back to Articles</span>
        </button>

        <div className="flex items-center gap-2">
          <button 
            onClick={handleShare}
            className="p-2 bg-white border border-slate-300 rounded-lg text-slate-600 hover:text-blue-600 text-xs font-semibold flex items-center gap-1.5 shadow-sm"
          >
            <Share2 className="w-3.5 h-3.5" />
            <span>{copied ? 'Link Copied!' : 'Share'}</span>
          </button>

          <button 
            onClick={() => toggleCompare(selectedArticle.id)}
            className={`p-2 rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-sm border ${
              isComparing ? 'bg-green-600 text-white border-green-600' : 'bg-white border-slate-300 text-slate-600 hover:text-blue-600'
            }`}
          >
            <SlidersHorizontal className="w-3.5 h-3.5" />
            <span>{isComparing ? 'In Compare' : 'Compare'}</span>
          </button>
        </div>
      </div>

      {/* Article Header */}
      <header className="space-y-4">
        <div className="flex flex-wrap items-center gap-2">
          <span className="bg-blue-600 text-white text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider">
            {selectedArticle.category}
          </span>
          {selectedArticle.badge && (
            <span className="bg-amber-500 text-slate-950 text-xs font-extrabold px-3 py-1 rounded-md">
              {selectedArticle.badge}
            </span>
          )}
        </div>

        <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">
          {selectedArticle.title}
        </h1>

        {selectedArticle.subtitle && (
          <p className="text-base sm:text-lg text-blue-600 font-semibold">
            {selectedArticle.subtitle}
          </p>
        )}

        {/* Author & Timestamp Bar */}
        <div className="flex flex-wrap items-center justify-between gap-4 py-3 border-y border-slate-200 text-xs text-slate-500 font-medium">
          <div className="flex items-center gap-3">
            <a 
              href={selectedArticle.authorUrl || "/author/omar-abobakar/"}
              className="w-8 h-8 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center shrink-0 hover:opacity-80 transition-opacity"
            >
              {(selectedArticle.author || 'FR').slice(0, 2).toUpperCase()}
            </a>
            <div>
              <a 
                href={selectedArticle.authorUrl || "/author/omar-abobakar/"}
                className="font-bold text-slate-900 hover:text-blue-600 transition-colors block"
              >
                By {selectedArticle.author || 'Omar Abobakar'}
              </a>
              <div className="text-[11px] text-slate-400">Senior Hardware Columnist</div>
            </div>
          </div>

          <div className="flex items-center gap-4">
            <span className="flex items-center gap-1">
              <Clock className="w-3.5 h-3.5 text-slate-400" />
              <time datetime={selectedArticle.isoDate || "2024-05-20"}>{selectedArticle.date}</time>
            </span>
            <span>•</span>
            <span className="flex items-center gap-1 text-amber-500 font-bold">
              <Star className="w-3.5 h-3.5 fill-amber-500" />
              {selectedArticle.rating || (selectedArticle.rocScore/2).toFixed(1)} / 5.0 Rating
            </span>
          </div>
        </div>
      </header>

      {/* Featured Cover Image */}
      <div className="relative rounded-2xl overflow-hidden shadow-xl border border-slate-200 bg-slate-900 max-h-[480px]">
        <img 
          src={selectedArticle.image} 
          alt={selectedArticle.title}
          className="w-full h-full object-cover max-h-[480px]"
        />
      </div>

      {/* AFFILIATE BUY CALLOUT BOX (High Converting Card) */}
      <div className="bg-gradient-to-r from-[#0B132B] to-[#1E293B] text-white rounded-2xl p-6 sm:p-8 shadow-xl border border-slate-700">
        <div className="flex flex-col md:flex-row items-center justify-between gap-6">
          
          <div className="space-y-2 text-center md:text-left">
            <div className="flex items-center justify-center md:justify-start gap-2">
              <span className="bg-emerald-500 text-slate-950 font-extrabold text-[11px] px-2.5 py-0.5 rounded-full uppercase tracking-wider">
                IN STOCK & VERIFIED
              </span>
              {selectedArticle.discount && (
                <span className="bg-blue-500 text-white font-extrabold text-[11px] px-2.5 py-0.5 rounded-full">
                  {selectedArticle.discount}
                </span>
              )}
            </div>

            <h3 className="font-display font-extrabold text-xl sm:text-2xl text-white">
              Get the {selectedArticle.title}
            </h3>
            
            <p className="text-xs text-slate-300 max-w-lg">
              Tested by our hardware lab. Ships with full manufacturer warranty and return policy.
            </p>
          </div>

          <div className="flex flex-col items-center md:items-end gap-3 shrink-0">
            <div className="text-center md:text-right">
              <span className="font-display font-extrabold text-3xl text-white">
                {selectedArticle.price || "$149.99"}
              </span>
              {selectedArticle.originalPrice && (
                <span className="text-sm text-slate-400 line-through ml-2">
                  {selectedArticle.originalPrice}
                </span>
              )}
            </div>

            {/* Affiliate Buttons */}
            <div className="flex flex-col sm:flex-row items-center gap-2.5 w-full sm:w-auto">
              <a
                href={amazonLink}
                target="_blank"
                rel="sponsored nofollow noopener noreferrer"
                className="w-full sm:w-auto bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs py-3 px-6 rounded-lg flex items-center justify-center gap-2 shadow-lg shadow-amber-500/20 transition-all hover:scale-105"
              >
                <ShoppingCart className="w-4 h-4" />
                <span>Check Price on Amazon</span>
                <ExternalLink className="w-3.5 h-3.5" />
              </a>

              <a
                href={bestbuyLink}
                target="_blank"
                rel="sponsored nofollow noopener noreferrer"
                className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3 px-5 rounded-lg flex items-center justify-center gap-1.5 transition-colors"
              >
                <span>Best Buy</span>
                <ExternalLink className="w-3.5 h-3.5" />
              </a>
            </div>

            <div className="text-[11px] text-slate-400">
              * Prices and availability subject to change
            </div>
          </div>

        </div>
      </div>

      {/* Main Review Body Content */}
      <div className="prose prose-slate max-w-none text-slate-800 space-y-6 text-sm sm:text-base leading-relaxed">
        
        {/* Executive Summary */}
        <div className="bg-slate-50 border-l-4 border-blue-600 p-5 rounded-r-xl">
          <h3 className="font-display font-extrabold text-base text-slate-900 mb-1.5">
            Quick Verdict & Summary
          </h3>
          <p className="text-slate-700 text-sm leading-relaxed">
            {selectedArticle.summary}
          </p>
        </div>

        {/* Detailed Article Body Paragraphs */}
        <div className="space-y-4">
          <h2 className="font-display font-extrabold text-2xl text-slate-900 border-b border-slate-200 pb-2">
            In-Depth Testing & Real-World Impressions
          </h2>
          
          <p className="text-slate-700 leading-relaxed">
            Over three weeks of relentless competitive gameplay across <em>Counter-Strike 2</em>, <em>VALORANT</em>, and <em>Apex Legends</em>, we subjected the {selectedArticle.title} to our rigorous testing methodology. We evaluated sensor responsiveness, switch debounce behavior, build durability, and thermal profiles under continuous load.
          </p>

          <p className="text-slate-700 leading-relaxed">
            What immediately sets this hardware apart from generic alternatives is the attention to mechanical tolerance. Keystroke and switch actuations provide instantaneous tactile feedback with zero pre-travel mushiness, making micro-adjustments in tactical scenarios feel second nature.
          </p>
        </div>

        {/* Pros & Cons Section */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 my-6">
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-5">
            <h4 className="font-display font-extrabold text-sm text-emerald-900 mb-3 flex items-center gap-2">
              <CheckCircle2 className="w-5 h-5 text-emerald-600" /> WHAT WE LIKE
            </h4>
            <ul className="space-y-2 text-xs sm:text-sm text-emerald-950 font-medium">
              {selectedArticle.pros?.map((pro, idx) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-emerald-600 font-bold">+</span>
                  <span>{pro}</span>
                </li>
              ))}
            </ul>
          </div>

          <div className="bg-rose-50 border border-rose-200 rounded-xl p-5">
            <h4 className="font-display font-extrabold text-sm text-rose-900 mb-3 flex items-center gap-2">
              <AlertTriangle className="w-5 h-5 text-rose-600" /> WHAT COULD BE BETTER
            </h4>
            <ul className="space-y-2 text-xs sm:text-sm text-rose-950 font-medium">
              {selectedArticle.cons?.map((con, idx) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-rose-600 font-bold">-</span>
                  <span>{con}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* Technical Hardware Specs Table */}
        {selectedArticle.specs && (
          <div className="my-8">
            <h3 className="font-display font-extrabold text-xl text-slate-900 mb-3">
              Full Technical Specifications
            </h3>
            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden divide-y divide-slate-200 text-xs sm:text-sm shadow-sm">
              {Object.entries(selectedArticle.specs).map(([k, v]) => (
                <div key={k} className="flex flex-col sm:flex-row p-3.5 hover:bg-slate-50 transition-colors">
                  <span className="w-full sm:w-1/3 text-slate-500 font-bold">{k}</span>
                  <span className="w-full sm:w-2/3 text-slate-900 font-semibold">{v}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* WHERE TO BUY COMPARISON TABLE (Affiliate links) */}
        <div className="bg-slate-50 border border-slate-200 rounded-2xl p-6 my-8">
          <h3 className="font-display font-extrabold text-lg text-slate-900 mb-4 flex items-center gap-2">
            <Tag className="w-5 h-5 text-blue-600" /> WHERE TO BUY AT BEST PRICE
          </h3>

          <div className="space-y-3">
            {/* Amazon Row */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between gap-4 shadow-sm">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 font-extrabold flex items-center justify-center text-xs">
                  AMZ
                </div>
                <div>
                  <div className="font-bold text-sm text-slate-900">Amazon.com</div>
                  <div className="text-xs text-emerald-600 font-semibold">Free Prime 1-Day Delivery</div>
                </div>
              </div>

              <div className="flex items-center gap-4">
                <span className="font-display font-extrabold text-lg text-slate-900">{selectedArticle.price || "$149.99"}</span>
                <a
                  href={amazonLink}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs px-4 py-2 rounded-lg flex items-center gap-1.5 transition-colors"
                >
                  <span>View Deal</span>
                  <ExternalLink className="w-3 h-3" />
                </a>
              </div>
            </div>

            {/* Best Buy Row */}
            <div className="bg-white border border-slate-200 rounded-xl p-4 flex items-center justify-between gap-4 shadow-sm">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 font-extrabold flex items-center justify-center text-xs">
                  BB
                </div>
                <div>
                  <div className="font-bold text-sm text-slate-900">Best Buy</div>
                  <div className="text-xs text-slate-500 font-semibold">Official Authorized Retailer</div>
                </div>
              </div>

              <div className="flex items-center gap-4">
                <span className="font-display font-extrabold text-lg text-slate-900">{selectedArticle.price || "$149.99"}</span>
                <a
                  href={bestbuyLink}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs px-4 py-2 rounded-lg flex items-center gap-1.5 transition-colors"
                >
                  <span>View Deal</span>
                  <ExternalLink className="w-3 h-3" />
                </a>
              </div>
            </div>
          </div>
        </div>

      </div>

      {/* Community Comments */}
      <section className="border-t border-slate-200 pt-8 space-y-6">
        <h3 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
          <MessageSquare className="w-5 h-5 text-blue-600" />
          COMMUNITY FEEDBACK & REVIEWS [{selectedArticle.comments?.length || 0}]
        </h3>

        <form onSubmit={handleCommentSubmit} className="bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-3">
          <div className="flex flex-col sm:flex-row gap-3">
            <input 
              type="text"
              placeholder="Your Gamer Tag / Name *"
              required
              value={commentName}
              onChange={(e) => setCommentName(e.target.value)}
              className="flex-1 bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-500"
            />

            <input 
              type="email"
              placeholder="Your Email Address *"
              required
              value={commentEmail}
              onChange={(e) => setCommentEmail(e.target.value)}
              className="flex-1 bg-white border border-slate-300 rounded-lg p-2.5 text-xs text-slate-900 focus:outline-none focus:border-blue-500"
            />
            
            <div className="flex items-center gap-1 bg-white border border-slate-300 rounded-lg px-3 py-2">
              <span className="text-xs text-slate-500 mr-1 font-semibold">Your Rating:</span>
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
            rows={3}
            placeholder="Share your practical experience with this hardware..."
            value={commentText}
            onChange={(e) => setCommentText(e.target.value)}
            className="w-full bg-white border border-slate-300 rounded-lg p-3 text-xs text-slate-900 focus:outline-none focus:border-blue-500"
          ></textarea>

          <div className="flex justify-end">
            <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-2 px-5 rounded-lg flex items-center gap-1.5 shadow-md shadow-blue-600/20">
              <Send className="w-3.5 h-3.5" /> Submit Review
            </button>
          </div>
        </form>

        <div className="space-y-3">
          {selectedArticle.comments?.map(c => (
            <div key={c.id} className="bg-white border border-slate-200 rounded-xl p-4 flex gap-3.5 shadow-sm">
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
      </section>

      {/* Affiliate Disclaimer Footer */}
      <footer className="bg-slate-100 border border-slate-200 rounded-xl p-4 text-center text-xs text-slate-500">
        <ShieldCheck className="w-4 h-4 text-slate-400 inline-block mr-1" />
        <span className="font-semibold">Affiliate Disclosure:</span> Run On Console participates in the Amazon Services LLC Associates Program and other retailer affiliate programs. When you click our links and make a purchase, we may earn an affiliate commission at zero additional cost to you.
      </footer>

    </article>
  );
};
