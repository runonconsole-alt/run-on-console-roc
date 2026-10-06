import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { Save, Plus, Trash2, FileText } from 'lucide-react';
import confetti from 'canvas-confetti';

export const AdminBlogForm = ({ initialData, onDone }) => {
  const { addBlog, updateBlog, setAdminTab } = useApp();
  const isEdit = Boolean(initialData);

  const [title, setTitle] = useState(initialData?.title || "");
  const [subtitle, setSubtitle] = useState(initialData?.subtitle || "");
  const [category, setCategory] = useState(initialData?.category || "Guides & Deals");
  const [author, setAuthor] = useState(initialData?.author || "Hardware Columnist");
  const [readTime, setReadTime] = useState(initialData?.readTime || "6 min read");
  const [image, setImage] = useState(initialData?.image || "/images/hero_cod.jpg");
  const [summary, setSummary] = useState(initialData?.summary || "");
  const [content, setContent] = useState(initialData?.content || "Write full blog review article content here with testing results...");

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!title.trim() || !summary.trim()) {
      alert("Please fill in title and summary!");
      return;
    }

    const formData = {
      title,
      subtitle,
      category,
      author,
      readTime,
      image,
      summary,
      content,
      relatedProductIds: initialData?.relatedProductIds || ["prod-101", "prod-102"]
    };

    if (isEdit) {
      updateBlog(initialData.id, formData);
    } else {
      addBlog(formData);
      try {
        confetti({
          particleCount: 70,
          spread: 80,
          origin: { x: 0.5, y: 0.5 },
          colors: ['#10B981', '#34D399', '#06B6D4', '#FFFFFF']
        });
      } catch (err) {}
    }

    if (onDone) onDone();
    else setAdminTab('blogs');
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6 max-w-4xl bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm">
      
      <div className="flex justify-between items-center pb-4 border-b border-slate-200">
        <div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">
            {isEdit ? `EDIT BLOG: ${initialData.title}` : 'WRITE NEW BLOG ARTICLE / GUIDE'}
          </h2>
          <p className="text-xs text-slate-500 font-medium">Publish gaming news, hardware breakdowns, and do-follow internal linking</p>
        </div>

        <button 
          type="button" 
          onClick={onDone || (() => setAdminTab('blogs'))}
          className="text-xs font-bold text-slate-500 hover:text-slate-900 border border-slate-300 px-3 py-1.5 rounded-lg"
        >
          CANCEL
        </button>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div>
          <label className="block text-slate-700 font-bold mb-1">BLOG TITLE *</label>
          <input 
            type="text"
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Call of Duty: Black Ops 6 Guide"
            className="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
          />
        </div>

        <div>
          <label className="block text-slate-700 font-bold mb-1">SUBTITLE / TAGLINE</label>
          <input 
            type="text"
            value={subtitle}
            onChange={(e) => setSubtitle(e.target.value)}
            placeholder="e.g. Movement & Hardware Breakdown"
            className="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-slate-900 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <div>
          <label className="block text-slate-700 font-bold mb-1">CATEGORY</label>
          <input 
            type="text"
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-slate-900 focus:outline-none focus:border-emerald-500 font-semibold"
          />
        </div>

        <div>
          <label className="block text-slate-700 font-bold mb-1">AUTHOR & READING TIME</label>
          <div className="grid grid-cols-2 gap-2">
            <input 
              type="text"
              value={author}
              onChange={(e) => setAuthor(e.target.value)}
              className="bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-slate-900"
            />
            <input 
              type="text"
              value={readTime}
              onChange={(e) => setReadTime(e.target.value)}
              className="bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-slate-900"
            />
          </div>
        </div>
      </div>

      <div>
        <label className="block text-slate-700 font-bold mb-1 text-xs">FEATURED IMAGE</label>
        <select
          value={image}
          onChange={(e) => setImage(e.target.value)}
          className="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-900"
        >
          <option value="/images/hero_cod.jpg">Call of Duty Soldier</option>
          <option value="/images/trending_elden.jpg">Elden Ring Knight</option>
          <option value="/images/trending_spiderman.jpg">Spider-Man City Scene</option>
          <option value="/images/trending_forza.jpg">Forza Motorsport Car</option>
          <option value="/images/gaming_laptop.jpg">Gaming Laptop</option>
          <option value="/images/handheld_console.jpg">Handheld Console</option>
        </select>
      </div>

      <div>
        <label className="block text-slate-700 font-bold mb-1 text-xs">EXECUTIVE SUMMARY *</label>
        <textarea
          required
          rows={2}
          value={summary}
          onChange={(e) => setSummary(e.target.value)}
          className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
        ></textarea>
      </div>

      <div>
        <label className="block text-slate-700 font-bold mb-1 text-xs">FULL BLOG ARTICLE TEXT (Paragraphs & Internal Links)</label>
        <textarea
          rows={6}
          value={content}
          onChange={(e) => setContent(e.target.value)}
          className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs text-slate-900 focus:outline-none focus:border-emerald-500 font-sans"
        ></textarea>
      </div>

      <div className="pt-4 border-t border-slate-200 flex justify-end">
        <button type="submit" className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-3 px-8 rounded-xl shadow-md shadow-emerald-600/20 flex items-center gap-2">
          <Save className="w-4 h-4" />
          <span>{isEdit ? 'SAVE CHANGES' : 'PUBLISH BLOG ARTICLE'}</span>
        </button>
      </div>

    </form>
  );
};
