import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { Save, Plus, Trash2, Tag, ShoppingCart, DollarSign, FileText } from 'lucide-react';

const CATEGORIES = [
  "Keyboards", 
  "Mice", 
  "Monitors", 
  "Audio", 
  "Chairs & Desks", 
  "Controllers & Gear", 
  "PC Builds & Hardware", 
  "Guides & Deals"
];

export const AdminReviewForm = ({ initialData, onDone }) => {
  const { addReview, updateReview, setAdminTab } = useApp();
  const isEdit = Boolean(initialData);

  const [title, setTitle] = useState(initialData?.title || "");
  const [subtitle, setSubtitle] = useState(initialData?.subtitle || "");
  const [category, setCategory] = useState(initialData?.category || "Keyboards");
  const [author, setAuthor] = useState(initialData?.author || "Hardware Editor");
  const [badge, setBadge] = useState(initialData?.badge || "EDITOR'S CHOICE");
  const [rating, setRating] = useState(initialData?.rating || 4.8);
  const [rocScore, setrocScore] = useState(initialData?.rocScore || 9.5);
  const [image, setImage] = useState(initialData?.image || "/images/cyber_keyboard.jpg");
  const [summary, setSummary] = useState(initialData?.summary || "");
  const [featured, setFeatured] = useState(Boolean(initialData?.featured));

  // Affiliate & Pricing State
  const [price, setPrice] = useState(initialData?.price || "$149.99");
  const [originalPrice, setOriginalPrice] = useState(initialData?.originalPrice || "$179.99");
  const [discount, setDiscount] = useState(initialData?.discount || "15% OFF");
  const [amazonLink, setAmazonLink] = useState(initialData?.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20");
  const [bestbuyLink, setBestbuyLink] = useState(initialData?.affiliateLinks?.bestbuy || "https://bestbuy.com");
  const [officialLink, setOfficialLink] = useState(initialData?.affiliateLinks?.official || "https://store.com");
  const [primeEligible, setPrimeEligible] = useState(Boolean(initialData?.primeEligible !== false));

  // Full Article Body
  const [articleBody, setArticleBody] = useState(initialData?.articleBody || "Over three weeks of rigorous tournament testing across Counter-Strike 2 and Apex Legends, we evaluated latency, mechanical tolerance, and daily durability.");

  const [specs, setSpecs] = useState(
    initialData?.specs ? Object.entries(initialData.specs).map(([key, val]) => ({ key, val })) : [
      { key: "Switches / Sensor", val: "Custom Precision Optical" },
      { key: "Connectivity", val: "2.4GHz Ultra-Low Latency Wireless" },
      { key: "Battery / Weight", val: "Up to 80 Hours continuous" }
    ]
  );

  const [prosText, setProsText] = useState(initialData?.pros ? initialData.pros.join('\n') : "Superb esports ergonomics\nCrisp 0.3mm actuation\nFlawless wireless tracking");
  const [consText, setConsText] = useState(initialData?.cons ? initialData.cons.join('\n') : "Premium price point");

  const handleSubmit = (e) => {
    e.preventDefault();

    if (!title.trim() || !summary.trim()) {
      alert("Please fill in title and executive summary!");
      return;
    }

    const specsObj = {};
    specs.forEach(s => {
      if (s.key.trim()) specsObj[s.key.trim()] = s.val.trim();
    });

    const prosArray = prosText.split('\n').map(p => p.trim()).filter(Boolean);
    const consArray = consText.split('\n').map(c => c.trim()).filter(Boolean);

    const formData = {
      title,
      subtitle,
      category,
      author,
      badge,
      rating: Number(rating),
      rocScore: Number(rocScore),
      image,
      summary,
      featured,
      price,
      originalPrice,
      discount,
      primeEligible,
      affiliateLinks: {
        amazon: amazonLink,
        bestbuy: bestbuyLink,
        official: officialLink
      },
      articleBody,
      specs: specsObj,
      pros: prosArray,
      cons: consArray
    };

    if (isEdit) {
      updateReview(initialData.id, formData);
    } else {
      addReview(formData);
    }

    if (onDone) onDone();
    else setAdminTab('reviews');
  };

  const addSpecRow = () => setSpecs(prev => [...prev, { key: "", val: "" }]);
  const removeSpecRow = (idx) => setSpecs(prev => prev.filter((_, i) => i !== idx));
  const updateSpecRow = (idx, field, val) => {
    setSpecs(prev => prev.map((item, i) => i === idx ? { ...item, [field]: val } : item));
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-6 max-w-4xl bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 shadow-sm">
      
      <div className="flex justify-between items-center pb-4 border-b border-slate-200">
        <div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">
            {isEdit ? `EDIT: ${initialData.title}` : 'PUBLISH NEW BLOG REVIEW & AFFILIATE POST'}
          </h2>
          <p className="text-xs text-slate-500 font-medium">Configure blog article, specs, pricing and affiliate monetization links</p>
        </div>

        <button 
          type="button" 
          onClick={onDone || (() => setAdminTab('reviews'))}
          className="text-xs font-bold text-slate-500 hover:text-slate-900 border border-slate-300 px-3 py-1.5 rounded-lg"
        >
          CANCEL
        </button>
      </div>

      {/* Main Info */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div>
          <label className="block text-slate-700 mb-1 font-bold">ARTICLE TITLE *</label>
          <input 
            type="text"
            required
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Logitech G Pro X TKL – Review"
            className="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-900 focus:border-blue-500 outline-none font-semibold"
          />
        </div>

        <div>
          <label className="block text-slate-700 mb-1 font-bold">SUBTITLE / HIGHLIGHT</label>
          <input 
            type="text"
            value={subtitle}
            onChange={(e) => setSubtitle(e.target.value)}
            placeholder="e.g. LIGHTSPEED Wireless // GX Switches"
            className="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-900 focus:border-blue-500 outline-none"
          />
        </div>

        <div>
          <label className="block text-slate-700 mb-1 font-bold">CATEGORY</label>
          <select 
            value={category}
            onChange={(e) => setCategory(e.target.value)}
            className="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-900 focus:border-blue-500 outline-none font-bold"
          >
            {CATEGORIES.map(c => (
              <option key={c} value={c}>{c}</option>
            ))}
          </select>
        </div>

        <div>
          <label className="block text-slate-700 mb-1 font-bold">EDITOR BADGE TAG</label>
          <input 
            type="text"
            value={badge}
            onChange={(e) => setBadge(e.target.value)}
            placeholder="e.g. EDITOR'S CHOICE, BEST VALUE"
            className="w-full bg-slate-50 border border-slate-300 rounded-lg p-2.5 text-slate-900 focus:border-blue-500 outline-none"
          />
        </div>
      </div>

      {/* AFFILIATE MONETIZATION & PRICING SECTION */}
      <div className="bg-amber-50/60 border border-amber-200 rounded-xl p-5 space-y-4">
        <div className="flex items-center gap-2 text-amber-900 font-display font-extrabold text-sm border-b border-amber-200 pb-2">
          <Tag className="w-4 h-4 text-amber-600" />
          <span>AFFILIATE LINKS & MONETIZATION SETTINGS</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
          <div>
            <label className="block text-slate-700 mb-1 font-bold">RETAIL PRICE ($)</label>
            <input 
              type="text"
              value={price}
              onChange={(e) => setPrice(e.target.value)}
              placeholder="$149.99"
              className="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-900 font-bold"
            />
          </div>

          <div>
            <label className="block text-slate-700 mb-1 font-bold">ORIGINAL / LIST PRICE</label>
            <input 
              type="text"
              value={originalPrice}
              onChange={(e) => setOriginalPrice(e.target.value)}
              placeholder="$179.99"
              className="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-900"
            />
          </div>

          <div>
            <label className="block text-slate-700 mb-1 font-bold">DISCOUNT BADGE</label>
            <input 
              type="text"
              value={discount}
              onChange={(e) => setDiscount(e.target.value)}
              placeholder="15% OFF"
              className="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-900"
            />
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <label className="block text-amber-900 mb-1 font-bold">AMAZON AFFILIATE LINK *</label>
            <input 
              type="text"
              value={amazonLink}
              onChange={(e) => setAmazonLink(e.target.value)}
              placeholder="https://amazon.com/dp/...?tag=yourtag-20"
              className="w-full bg-white border border-amber-300 rounded-lg p-2 text-slate-900 font-mono text-[11px]"
            />
          </div>

          <div>
            <label className="block text-slate-700 mb-1 font-bold">BEST BUY / STORE AFFILIATE LINK</label>
            <input 
              type="text"
              value={bestbuyLink}
              onChange={(e) => setBestbuyLink(e.target.value)}
              placeholder="https://bestbuy.com/..."
              className="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-900 font-mono text-[11px]"
            />
          </div>
        </div>
      </div>

      {/* Ratings & Image */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs bg-slate-50 border border-slate-200 rounded-xl p-4">
        <div>
          <label className="block text-blue-600 mb-1 font-bold">STAR RATING (★ {rating} / 5.0)</label>
          <input 
            type="range"
            min="1.0"
            max="5.0"
            step="0.1"
            value={rating}
            onChange={(e) => setRating(e.target.value)}
            className="w-full accent-blue-600 cursor-pointer"
          />
        </div>

        <div>
          <label className="block text-slate-700 mb-1 font-bold">PRODUCT IMAGE</label>
          <select
            value={image}
            onChange={(e) => setImage(e.target.value)}
            className="w-full bg-white border border-slate-300 rounded-lg p-2 text-slate-900 outline-none"
          >
            <option value="/images/cyber_keyboard.jpg">Logitech Keyboard</option>
            <option value="/images/apex_mouse.jpg">Razer Mouse</option>
            <option value="/images/gaming_monitor.jpg">360Hz Gaming Monitor</option>
            <option value="/images/tactical_headset.jpg">HyperX Headset</option>
            <option value="/images/pro_controller.jpg">Apex Pro Controller</option>
            <option value="/images/review_chair.jpg">Secretlab Chair</option>
            <option value="/images/battlestation_pc.jpg">RTX 4090 Battlestation PC</option>
          </select>
        </div>

        <div className="flex items-center gap-2 pt-4">
          <input 
            type="checkbox"
            id="featured"
            checked={featured}
            onChange={(e) => setFeatured(e.target.checked)}
            className="w-4 h-4 accent-blue-600"
          />
          <label htmlFor="featured" className="text-slate-900 font-bold cursor-pointer">
            FEATURED HERO BANNER
          </label>
        </div>
      </div>

      {/* Executive Summary */}
      <div>
        <label className="block text-xs text-slate-700 mb-1 font-bold">EXECUTIVE VERDICT SUMMARY *</label>
        <textarea 
          required
          rows={2}
          value={summary}
          onChange={(e) => setSummary(e.target.value)}
          placeholder="Brief summary shown on review cards..."
          className="w-full bg-slate-50 border border-slate-300 rounded-lg p-3 text-xs text-slate-900 focus:border-blue-500 outline-none"
        ></textarea>
      </div>

      {/* Full Blog Article Content */}
      <div>
        <label className="block text-xs text-slate-700 mb-1 font-bold flex items-center gap-1">
          <FileText className="w-3.5 h-3.5 text-blue-600" />
          <span>FULL BLOG REVIEW ARTICLE CONTENT (Markdown / Paragraphs)</span>
        </label>
        <textarea 
          rows={5}
          value={articleBody}
          onChange={(e) => setArticleBody(e.target.value)}
          placeholder="Write in-depth testing methodology, gaming impressions, performance benchmarks and buyer advice..."
          className="w-full bg-slate-50 border border-slate-300 rounded-lg p-3 text-xs text-slate-900 focus:border-blue-500 outline-none font-sans"
        ></textarea>
      </div>

      {/* Dynamic Specs */}
      <div className="space-y-3 text-xs">
        <div className="flex justify-between items-center">
          <label className="text-blue-600 font-bold">HARDWARE SPECIFICATIONS TABLE</label>
          <button 
            type="button"
            onClick={addSpecRow}
            className="text-blue-600 hover:underline flex items-center gap-1 font-bold"
          >
            <Plus className="w-3.5 h-3.5" /> Add Spec Line
          </button>
        </div>

        <div className="space-y-2">
          {specs.map((s, idx) => (
            <div key={idx} className="flex gap-2 items-center">
              <input 
                type="text"
                placeholder="Spec Key (e.g. Refresh Rate)"
                value={s.key}
                onChange={(e) => updateSpecRow(idx, 'key', e.target.value)}
                className="w-1/3 bg-slate-50 border border-slate-300 rounded-lg p-2 text-slate-900"
              />
              <input 
                type="text"
                placeholder="Spec Value (e.g. 360Hz QD-OLED)"
                value={s.val}
                onChange={(e) => updateSpecRow(idx, 'val', e.target.value)}
                className="w-2/3 bg-slate-50 border border-slate-300 rounded-lg p-2 text-slate-900"
              />
              <button 
                type="button"
                onClick={() => removeSpecRow(idx)}
                className="text-rose-500 p-1 hover:text-rose-700"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
        </div>
      </div>

      {/* Pros & Cons */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
        <div>
          <label className="block text-emerald-600 mb-1 font-bold">WHAT WE LIKE (One per line)</label>
          <textarea 
            rows={3}
            value={prosText}
            onChange={(e) => setProsText(e.target.value)}
            className="w-full bg-emerald-50/50 border border-emerald-300 rounded-lg p-2.5 text-slate-900 outline-none"
          ></textarea>
        </div>

        <div>
          <label className="block text-rose-600 mb-1 font-bold">WHAT COULD BE BETTER (One per line)</label>
          <textarea 
            rows={3}
            value={consText}
            onChange={(e) => setConsText(e.target.value)}
            className="w-full bg-rose-50/50 border border-rose-300 rounded-lg p-2.5 text-slate-900 outline-none"
          ></textarea>
        </div>
      </div>

      {/* Submit Button */}
      <div className="pt-4 border-t border-slate-200 flex justify-end">
        <button type="submit" className="bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs py-3 px-8 rounded-lg shadow-md shadow-blue-600/30 flex items-center gap-2">
          <Save className="w-4 h-4" />
          <span>{isEdit ? 'SAVE & UPDATE AFFILIATE POST' : 'PUBLISH BLOG REVIEW & AFFILIATE POST'}</span>
        </button>
      </div>

    </form>
  );
};
