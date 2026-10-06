import React, { useState } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Clock, User, ArrowRight, ArrowLeft, MessageSquare, 
  Send, Share2, Star, ShoppingCart, Tag, ExternalLink, Sparkles, 
  BookOpen, Flame, Zap, ShieldCheck, FileText, CheckCircle2, 
  PackageCheck, Check, ThumbsUp, HelpCircle 
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { BouncyText } from './BouncyText';
import { SubmissionSuccessCard } from './SubmissionSuccessCard';
import { playClickSound, playHoverSound } from '../utils/audioEffects';
import { slugify } from '../seo/routeRegistry';

export const BlogsView = () => {
  const { 
    blogs, 
    selectedBlogId, 
    setSelectedBlogId, 
    navigateToBlog, 
    navigateToProduct, 
    products, 
    searchQuery,
    pageFaqs 
  } = useApp();

  const [commentName, setCommentName] = useState("");
  const [commentText, setCommentText] = useState("");
  const [commentsList, setCommentsList] = useState([]);
  const [commentSubmitted, setCommentSubmitted] = useState(false);
  const [copied, setCopied] = useState(false);

  // Community Review & Hardware Suggestions Form State (for Blogs page bottom)
  const [sugName, setSugName] = useState("");
  const [sugEmail, setSugEmail] = useState("");
  const [sugType, setSugType] = useState("Hardware Review Sample Submission");
  const [sugDetails, setSugDetails] = useState("");
  const [sugSubmitted, setSugSubmitted] = useState(false);
  const [sugRef, setSugRef] = useState("");

  const handleSuggestionSubmit = (e) => {
    e.preventDefault();
    if (!sugName || !sugEmail || !sugDetails) {
      alert("Please fill in all fields!");
      return;
    }
    setSugRef('SUG-' + Date.now().toString().slice(-6));
    setSugSubmitted(true);
  };

  // If a specific blog is selected, render the Individual Blog Reader Page
  if (selectedBlogId) {
    const blog = blogs.find(b => b.id === selectedBlogId || b.slug === selectedBlogId) || blogs[0];
    const relatedProducts = products.filter(p => blog.relatedProductIds?.includes(p.id));

    const handleShare = () => {
      playClickSound();
      navigator.clipboard?.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2500);
    };

    const handleComment = (e) => {
      e.preventDefault();
      if (!commentText.trim()) return;
      playClickSound();
      const newC = {
        id: 'c-' + Date.now(),
        name: commentName.trim() || 'TacticalGamer',
        text: commentText.trim(),
        date: 'Just now'
      };
      setCommentsList([newC, ...commentsList]);
      setCommentSubmitted(true);
    };

    return (
      <article className="max-w-4xl mx-auto py-6 space-y-8 animate-page-in">
        
        {/* Back and Share Navigation */}
        <div className="flex items-center justify-between">
          <button 
            onClick={() => {
              playClickSound();
              setSelectedBlogId(null);
            }}
            className="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to All Blogs</span>
          </button>

          <button 
            onClick={handleShare}
            className="p-2 bg-white border border-slate-300 rounded-xl text-slate-600 hover:text-emerald-600 text-xs font-semibold flex items-center gap-1.5 shadow-sm transition-colors"
          >
            <Share2 className="w-3.5 h-3.5" />
            <span>{copied ? 'Link Copied!' : 'Share Article'}</span>
          </button>
        </div>

        {/* Blog Header */}
        <header className="space-y-4">
          <span className="bg-emerald-600 text-white text-xs font-extrabold uppercase px-3 py-1 rounded-md tracking-wider shadow-sm badge-glow">
            {blog.category}
          </span>
          <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">
            <BouncyText text={blog.title} enableAudio={true} />
          </h1>
          {blog.subtitle && (
            <p className="text-base sm:text-lg text-emerald-700 font-semibold">
              {blog.subtitle}
            </p>
          )}

          <div className="flex flex-wrap items-center justify-between gap-4 py-3 border-y border-slate-200 text-xs text-slate-500 font-medium">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-full bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-bold flex items-center justify-center shadow-md">
                {(blog.author || 'FR').slice(0, 2).toUpperCase()}
              </div>
              <div>
                <span className="font-bold text-slate-900 block">{blog.author}</span>
                <span className="text-[10px] text-slate-400">Senior Hardware Columnist</span>
              </div>
            </div>

            <div className="flex items-center gap-3">
              <span className="flex items-center gap-1">
                <Clock className="w-3.5 h-3.5" /> {blog.date}
              </span>
              <span>•</span>
              <span className="text-emerald-600 font-bold">{blog.readTime}</span>
            </div>
          </div>
        </header>

        {/* Featured Cover Image */}
        <Tilt3DCard className="relative rounded-3xl overflow-hidden shadow-2xl max-h-[480px] bg-slate-900">
          <img src={blog.image} alt={blog.title} className="w-full h-full object-cover max-h-[480px]" />
        </Tilt3DCard>

        {/* Summary Card */}
        <div className="bg-emerald-50/80 border-l-4 border-emerald-600 p-5 rounded-r-2xl shadow-sm">
          <h3 className="font-display font-extrabold text-base text-emerald-950 mb-1 flex items-center gap-2">
            <Sparkles className="w-4 h-4 text-emerald-600" />
            <BouncyText text="Article Overview & Key Takeaways" />
          </h3>
          <p className="text-xs sm:text-sm text-emerald-900 leading-relaxed">
            {blog.summary}
          </p>
        </div>

        {/* Article Body Content */}
        <div className="prose prose-slate max-w-none text-slate-800 space-y-6 text-sm sm:text-base leading-relaxed">
          <div className="whitespace-pre-line text-slate-700 leading-relaxed font-sans">
            {blog.content}
          </div>
        </div>

        {/* RELATED PRODUCTS CALLOUT WITH DO-FOLLOW BUYING LINKS */}
        {relatedProducts.length > 0 && (
          <section className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4 my-8">
            <h3 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
              <Tag className="w-5 h-5 text-emerald-600" />
              <BouncyText text="Featured Gear in this Article" />
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {relatedProducts.map(prod => (
                <div key={prod.id} className="p-4 bg-slate-50 hover:bg-emerald-50/60 border border-slate-200 rounded-2xl flex items-center justify-between gap-4 transition-colors">
                  <div className="flex items-center gap-3">
                    <img src={prod.image} alt={prod.title} className="w-14 h-14 rounded-xl object-cover shadow-sm" />
                    <div>
                      <h4 
                        onClick={() => {
                          playClickSound();
                          navigateToProduct(prod.id);
                        }}
                        className="font-display font-bold text-sm text-slate-900 hover:text-emerald-600 cursor-pointer line-clamp-1"
                      >
                        {prod.title}
                      </h4>
                      <span className="text-xs font-extrabold text-emerald-600">{prod.price}</span>
                    </div>
                  </div>

                  <a 
                    href={prod.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20"}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={() => playClickSound()}
                    className="bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs px-3.5 py-2 rounded-xl flex items-center gap-1 shrink-0 shadow-sm transition-all hover:scale-105"
                  >
                    <ShoppingCart className="w-3.5 h-3.5" />
                    <span>Buy</span>
                    <ExternalLink className="w-3 h-3" />
                  </a>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* Blog-Specific FAQs */}
        <FAQSection 
          faqs={blog.faqs || pageFaqs.blogs}
          title={`FAQs: ${blog.title}`}
          subtitle="Frequently asked questions about this gaming guide and benchmarks."
        />

        {/* Community Comments Section with Party Popper Celebration */}
        <section className="border-t border-slate-200 pt-8 space-y-5">
          <h3 className="font-display font-extrabold text-xl text-slate-900 flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-emerald-600" />
            <BouncyText text={`Comments & Discussion [${commentsList.length}]`} />
          </h3>

          {commentSubmitted ? (
            <SubmissionSuccessCard 
              formType="comment"
              userName={commentsList[0]?.name || "Gamer"}
              onReset={() => {
                setCommentSubmitted(false);
                setCommentText('');
                setCommentName('');
              }}
            />
          ) : (
            <form onSubmit={handleComment} className="bg-white border border-slate-200 rounded-2xl p-5 space-y-3 shadow-sm">
              <input 
                type="text"
                placeholder="Your Name / Gamer Tag"
                value={commentName}
                onChange={(e) => setCommentName(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
              />
              <textarea 
                rows={3}
                placeholder="Leave your thoughts or ask a hardware question... (Submitted to administrator for approval)"
                value={commentText}
                onChange={(e) => setCommentText(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
              ></textarea>
              <div className="flex justify-end">
                <button type="submit" className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-6 rounded-xl shadow-md shadow-emerald-600/20 flex items-center gap-1.5 transition-all hover:scale-105">
                  <Send className="w-3.5 h-3.5" /> Post Comment
                </button>
              </div>
            </form>
          )}

          <div className="space-y-3">
            {commentsList.map(c => (
              <div key={c.id} className="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                <div className="flex justify-between items-center mb-1">
                  <span className="font-bold text-xs text-slate-900">{c.name}</span>
                  <span className="text-[10px] text-slate-400">{c.date}</span>
                </div>
                <p className="text-xs text-slate-600">{c.text}</p>
              </div>
            ))}
          </div>
        </section>

      </article>
    );
  }

  // Main Blogs Listing Grid
  let filtered = blogs.filter(b => {
    if (!searchQuery) return true;
    const q = searchQuery.toLowerCase();
    return b.title.toLowerCase().includes(q) || 
           (b.summary || "").toLowerCase().includes(q) || 
           (b.category || "").toLowerCase().includes(q);
  });

  return (
    <div className="space-y-12 py-6 animate-page-in">
      
      {/* Visually Rich Blogs Catalog Header Banner with BouncyText */}
      <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        
        <CyberMatrixHoloBackground />

        <div className="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          
          <div className="lg:col-span-7 space-y-4">
            <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
              <Sparkles className="w-3.5 h-3.5" /> RUN ON CONSOLE EDITORIAL BLOG
            </span>
            <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
              <BouncyText text="Gaming Articles &" enableAudio={true} /> <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
                <BouncyText text="Buying Guides Hub" enableAudio={true} />
              </span>
            </h1>
            <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">
              In-depth game analysis, setup optimization tutorials, esports firmware reviews, and verified hardware buying guides.
            </p>

            <div className="flex flex-wrap gap-2.5 pt-2">
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <FileText className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
                <span>{blogs.length} Hardware Reviews & Guides</span>
              </span>
            </div>
          </div>

          <div className="lg:col-span-5 grid grid-cols-2 gap-3">
            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/trending_elden.jpg" 
                alt="Elden Ring Guide" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Shadow of Erdtree</div>
              <div className="text-[10px] text-emerald-300 font-medium">Full Boss & Build Guide</div>
            </Tilt3DCard>

            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/hero_cod.jpg" 
                alt="COD Black Ops 6" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Black Ops 6 Latency</div>
              <div className="text-[10px] text-emerald-300 font-medium">8000Hz Input Delay Test</div>
            </Tilt3DCard>
          </div>

        </div>
      </div>

      {/* Blogs Grid with 3D Physics Cards & BouncyText */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {filtered.map((blog) => {
          const blogSlug = blog.slug || slugify(blog.title);
          const blogUrl = `/blogs/${blogSlug}/`;
          return (
            <a 
              key={blog.id}
              href={blogUrl}
              onClick={(e) => {
                if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                  e.preventDefault();
                  playClickSound();
                  navigateToBlog(blog.id);
                }
              }}
              className="block no-underline"
            >
              <Tilt3DCard 
                onMouseEnter={playHoverSound}
                className="game-card flex flex-col justify-between group cursor-pointer h-full"
              >
                {/* Image Box */}
                <div className="relative h-60 bg-slate-900 overflow-hidden">
                  <img 
                    src={blog.image} 
                    alt={blog.title} 
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  <span className="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded-md shadow-sm uppercase badge-glow">
                    {blog.category}
                  </span>
                  <span className="absolute bottom-3 right-3 bg-slate-900/90 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg flex items-center gap-1 backdrop-blur-sm">
                    <Clock className="w-3 h-3" /> {blog.readTime}
                  </span>
                </div>

                {/* Content */}
                <div className="p-6 flex-1 flex flex-col justify-between space-y-4">
                  <div>
                    <h2 className="font-display font-extrabold text-lg sm:text-xl text-slate-900 group-hover:text-emerald-600 transition-colors leading-snug line-clamp-2 mb-2">
                      <BouncyText text={blog.title} />
                    </h2>
                    <p className="text-xs text-slate-500 line-clamp-3 leading-relaxed">
                      {blog.summary}
                    </p>
                  </div>

                  <div className="pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
                    <div className="flex items-center gap-2 text-slate-600 font-medium">
                      <User className="w-3.5 h-3.5 text-emerald-600" />
                      <span>By {blog.author}</span>
                    </div>

                    <span className="text-emerald-600 font-bold flex items-center gap-1 group-hover:translate-x-1 transition-transform">
                      Read Article <ArrowRight className="w-3.5 h-3.5" />
                    </span>
                  </div>
                </div>
              </Tilt3DCard>
            </a>
          );
        })}
      </div>

      {/* DEDICATED REVIEW SAMPLE & EDITORIAL SUGGESTIONS SUBMISSION BOX (Bottom of Blogs Page) */}
      {sugSubmitted ? (
        <SubmissionSuccessCard 
          formType="suggestion"
          userName={sugName}
          referenceId={sugRef}
          onReset={() => {
            setSugSubmitted(false);
            setSugName('');
            setSugEmail('');
            setSugDetails('');
          }}
        />
      ) : (
        <Tilt3DCard className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-10 shadow-sm space-y-6">
          <div className="flex items-center gap-3 border-b border-emerald-100 pb-4">
            <div className="w-11 h-11 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md shadow-emerald-500/20 shrink-0">
              <PackageCheck className="w-6 h-6 animate-pulse" />
            </div>
            <div>
              <div className="inline-flex items-center gap-1.5 bg-emerald-800 text-white text-[10px] font-extrabold px-3 py-0.5 rounded-full uppercase tracking-wider mb-1 border border-emerald-500/40">
                <Sparkles className="w-3 h-3 text-emerald-300" />
                <span>EDITORIAL REVIEW & SUGGESTIONS DESK</span>
              </div>
              <h2 className="font-display font-extrabold text-2xl sm:text-3xl text-emerald-950">
                <BouncyText text="Submit Review Sample & Hardware Suggestions" />
              </h2>
              <p className="text-xs sm:text-sm text-emerald-800 font-semibold">
                Send your hardware testing suggestions, feedback, or review samples directly to the administrator for moderation & lab queue placement.
              </p>
            </div>
          </div>

          <form onSubmit={handleSuggestionSubmit} className="space-y-4 text-xs sm:text-sm">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-emerald-950 font-bold mb-1">YOUR NAME *</label>
                <input 
                  type="text"
                  required
                  placeholder="e.g. Elena Rostova"
                  value={sugName}
                  onChange={(e) => setSugName(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                />
              </div>

              <div>
                <label className="block text-emerald-950 font-bold mb-1">EMAIL ADDRESS *</label>
                <input 
                  type="email"
                  required
                  placeholder="e.g. elena@gaminglab.com"
                  value={sugEmail}
                  onChange={(e) => setSugEmail(e.target.value)}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                />
              </div>
            </div>

            <div>
              <label className="block text-emerald-950 font-bold mb-1">SUBMISSION TYPE</label>
              <select
                value={sugType}
                onChange={(e) => setSugType(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
              >
                <option value="Hardware Review Sample Submission">Hardware Review Sample Submission (Peripherals / Rig)</option>
                <option value="Blog Topic / Game Benchmark Suggestion">Blog Topic / Game Benchmark Suggestion</option>
                <option value="Product Lab Latency Retest Request">Product Lab Latency Retest Request</option>
                <option value="General Editorial Feedback">General Editorial & Content Feedback</option>
              </select>
            </div>

            <div>
              <label className="block text-emerald-950 font-bold mb-1">YOUR REVIEW / SUGGESTION DETAILS *</label>
              <textarea 
                required
                rows={4}
                placeholder="Describe the hardware model, benchmark parameters, or article topic you want our lab to review..."
                value={sugDetails}
                onChange={(e) => setSugDetails(e.target.value)}
                className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-slate-900 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
              ></textarea>
            </div>

            <button 
              type="submit"
              className="w-full bg-emerald-800 hover:bg-emerald-900 text-white font-display font-extrabold text-sm py-4 px-6 rounded-xl shadow-lg border border-emerald-500/40 flex items-center justify-center gap-2 transition-all hover:scale-[1.01]"
            >
              <Send className="w-4 h-4 text-emerald-300" />
              <span>Submit Review Suggestion to Administrator</span>
            </button>
          </form>
        </Tilt3DCard>
      )}

      {/* Blogs Page FAQs */}
      <FAQSection 
        faqs={pageFaqs.blogs}
        title="Editorial & Review Policy FAQs"
        subtitle="How our editorial team reviews games, handles review samples, and conducts lab testing."
      />

    </div>
  );
};
