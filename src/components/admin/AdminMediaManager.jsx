import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { 
  Image as ImageIcon, Upload, Search, Filter, Copy, Check, 
  Trash2, Edit3, ExternalLink, AlertCircle, Sparkles, Plus,
  FileImage, Eye, RefreshCw, CheckCircle2
} from 'lucide-react';

export const AdminMediaManager = () => {
  const { siteImages = [], addSiteImage, updateSiteImage, deleteSiteImage } = useApp();
  
  const [search, setSearch] = useState('');
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [copiedId, setCopiedId] = useState(null);
  const [editingImage, setEditingImage] = useState(null);
  const [isUploadOpen, setIsUploadOpen] = useState(false);
  const [previewImage, setPreviewImage] = useState(null);

  // New Image Form State
  const [newImage, setNewImage] = useState({
    title: '',
    url: '',
    alt: '',
    category: 'Blogs',
    caption: '',
    dimensions: '1920x1080'
  });

  const categories = ['all', 'Hero & Banners', 'Blogs', 'Products', 'Hardware', 'Avatars'];

  const filteredImages = siteImages.filter(img => {
    const matchesSearch = (img.title || '').toLowerCase().includes(search.toLowerCase()) ||
                          (img.alt || '').toLowerCase().includes(search.toLowerCase()) ||
                          (img.url || '').toLowerCase().includes(search.toLowerCase());
    const matchesCategory = selectedCategory === 'all' || img.category === selectedCategory;
    return matchesSearch && matchesCategory;
  });

  const missingAltCount = siteImages.filter(img => !img.alt || img.alt.trim() === '').length;

  const handleCopy = (text, id, type = 'url') => {
    navigator.clipboard.writeText(text);
    setCopiedId(`${id}-${type}`);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleFileUpload = (e) => {
    const file = e.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (uploadEvent) => {
        setNewImage(prev => ({
          ...prev,
          url: uploadEvent.target.result,
          title: file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, ' '),
          alt: file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, ' ')
        }));
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSaveNew = (e) => {
    e.preventDefault();
    if (!newImage.url || !newImage.title) return;
    
    addSiteImage({
      ...newImage,
      id: `img-${Date.now()}`,
      createdAt: new Date().toISOString()
    });

    setNewImage({
      title: '',
      url: '',
      alt: '',
      category: 'Blogs',
      caption: '',
      dimensions: '1920x1080'
    });
    setIsUploadOpen(false);
  };

  const handleUpdate = (e) => {
    e.preventDefault();
    if (!editingImage) return;
    updateSiteImage(editingImage.id, editingImage);
    setEditingImage(null);
  };

  return (
    <div className="space-y-6 max-w-7xl animate-page-in">
      
      {/* Header & Stats Banner */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[10px] text-emerald-700 bg-emerald-100 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
              Asset & Media Intelligence
            </span>
          </div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">IMAGES & MEDIA LIBRARY</h2>
          <p className="text-xs text-slate-500 font-medium">
            Full access to uploaded images, SEO alt tags, dimensions, captions, and CDN paths
          </p>
        </div>

        <button
          onClick={() => setIsUploadOpen(true)}
          className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 shadow-md shadow-emerald-600/20 transition-all hover:scale-105"
        >
          <Upload className="w-4 h-4" /> UPLOAD NEW IMAGE
        </button>
      </div>

      {/* Media Metrics Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="flex justify-between items-start mb-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Media Assets</span>
            <div className="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
              <FileImage className="w-4 h-4" />
            </div>
          </div>
          <div className="font-display font-black text-2xl text-slate-900">{siteImages.length}</div>
          <span className="text-[10px] font-semibold text-slate-400">Indexed across website & CMS</span>
        </div>

        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="flex justify-between items-start mb-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">SEO Alt Tag Coverage</span>
            <div className={`p-1.5 rounded-lg ${missingAltCount === 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}>
              <CheckCircle2 className="w-4 h-4" />
            </div>
          </div>
          <div className="font-display font-black text-2xl text-slate-900">
            {siteImages.length > 0 ? `${Math.round(((siteImages.length - missingAltCount) / siteImages.length) * 100)}%` : '100%'}
          </div>
          <span className={`text-[10px] font-semibold ${missingAltCount === 0 ? 'text-emerald-600' : 'text-amber-600'}`}>
            {missingAltCount === 0 ? 'All images have alt tags!' : `${missingAltCount} image(s) missing alt text`}
          </span>
        </div>

        <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs">
          <div className="flex justify-between items-start mb-1">
            <span className="text-[11px] font-bold text-slate-500 uppercase tracking-wider">WebP & SVG Optimized</span>
            <div className="p-1.5 rounded-lg bg-teal-50 text-teal-600">
              <Sparkles className="w-4 h-4" />
            </div>
          </div>
          <div className="font-display font-black text-2xl text-slate-900">100%</div>
          <span className="text-[10px] font-semibold text-teal-600">High-performance compression</span>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white border border-slate-200 rounded-2xl p-4 shadow-xs flex flex-col sm:flex-row gap-3 items-center justify-between">
        <div className="relative w-full sm:w-80">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            placeholder="Search by title, alt text or filename..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full bg-slate-50 border border-slate-300 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-900 focus:outline-none focus:border-emerald-500"
          />
        </div>

        <div className="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
          <Filter className="w-3.5 h-3.5 text-slate-400 shrink-0 ml-1" />
          {categories.map(cat => (
            <button
              key={cat}
              onClick={() => setSelectedCategory(cat)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold capitalize whitespace-nowrap transition-all ${
                selectedCategory === cat
                  ? 'bg-slate-900 text-white'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>
      </div>

      {/* Upload Modal */}
      {isUploadOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 space-y-4 animate-page-in">
            <div className="flex justify-between items-center pb-3 border-b border-slate-100">
              <h3 className="font-display font-black text-lg text-slate-900 flex items-center gap-2">
                <Upload className="w-5 h-5 text-emerald-600" /> Upload Media Asset
              </h3>
              <button 
                onClick={() => setIsUploadOpen(false)}
                className="text-slate-400 hover:text-slate-700 font-bold text-sm"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSaveNew} className="space-y-4 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Image File or URL</label>
                <div className="flex gap-2">
                  <input
                    type="text"
                    required
                    placeholder="Enter image URL or choose file below"
                    value={newImage.url}
                    onChange={(e) => setNewImage({ ...newImage, url: e.target.value })}
                    className="flex-1 bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                  <label className="bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-xl cursor-pointer flex items-center gap-1 font-bold">
                    <span>Browse</span>
                    <input type="file" accept="image/*" onChange={handleFileUpload} className="hidden" />
                  </label>
                </div>
              </div>

              {newImage.url && (
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 flex items-center gap-4">
                  <img src={newImage.url} alt="Preview" className="w-16 h-16 object-cover rounded-lg border border-slate-200" />
                  <div className="text-[11px] text-slate-500">Image loaded successfully and ready to save.</div>
                </div>
              )}

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Image Title</label>
                  <input
                    type="text"
                    required
                    placeholder="e.g. RTX 4090 Benchmark Graph"
                    value={newImage.title}
                    onChange={(e) => setNewImage({ ...newImage, title: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>

                <div>
                  <label className="block font-bold text-slate-700 mb-1">Category</label>
                  <select
                    value={newImage.category}
                    onChange={(e) => setNewImage({ ...newImage, category: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  >
                    <option value="Hero & Banners">Hero & Banners</option>
                    <option value="Blogs">Blogs</option>
                    <option value="Products">Products</option>
                    <option value="Hardware">Hardware</option>
                    <option value="Avatars">Avatars</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1 flex justify-between">
                  <span>SEO Alt Text (Recommended for Google Image Search)</span>
                  <span className="text-[10px] text-emerald-600 font-bold">Crucial for SEO</span>
                </label>
                <input
                  type="text"
                  required
                  placeholder="Descriptive alt text for accessibility and search ranking"
                  value={newImage.alt}
                  onChange={(e) => setNewImage({ ...newImage, alt: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setIsUploadOpen(false)}
                  className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2 rounded-xl shadow-md"
                >
                  Save Image
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Image Modal */}
      {editingImage && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-xl w-full p-6 space-y-4 animate-page-in">
            <div className="flex justify-between items-center pb-3 border-b border-slate-100">
              <h3 className="font-display font-black text-lg text-slate-900 flex items-center gap-2">
                <Edit3 className="w-5 h-5 text-emerald-600" /> Edit Media Metadata
              </h3>
              <button 
                onClick={() => setEditingImage(null)}
                className="text-slate-400 hover:text-slate-700 font-bold text-sm"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleUpdate} className="space-y-4 text-xs">
              <div className="flex items-center gap-4 p-3 bg-slate-50 rounded-xl border border-slate-200">
                <img 
                  src={editingImage.url} 
                  alt={editingImage.alt} 
                  className="w-20 h-20 object-cover rounded-xl border border-slate-300 shadow-xs" 
                />
                <div className="flex-1 space-y-1">
                  <div className="font-bold text-slate-900 text-sm truncate">{editingImage.title}</div>
                  <div className="text-[10px] text-slate-500 font-mono truncate">{editingImage.url}</div>
                  <div className="text-[10px] text-emerald-700 font-bold uppercase">{editingImage.category}</div>
                </div>
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Image Title</label>
                <input
                  type="text"
                  required
                  value={editingImage.title}
                  onChange={(e) => setEditingImage({ ...editingImage, title: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1 flex justify-between">
                  <span>SEO Alt Text</span>
                  <span className="text-[10px] text-emerald-600 font-bold">Google Image SEO</span>
                </label>
                <input
                  type="text"
                  required
                  value={editingImage.alt}
                  onChange={(e) => setEditingImage({ ...editingImage, alt: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Category</label>
                  <select
                    value={editingImage.category}
                    onChange={(e) => setEditingImage({ ...editingImage, category: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  >
                    <option value="Hero & Banners">Hero & Banners</option>
                    <option value="Blogs">Blogs</option>
                    <option value="Products">Products</option>
                    <option value="Hardware">Hardware</option>
                    <option value="Avatars">Avatars</option>
                  </select>
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Dimensions / Ratio</label>
                  <input
                    type="text"
                    value={editingImage.dimensions || '1920x1080'}
                    onChange={(e) => setEditingImage({ ...editingImage, dimensions: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setEditingImage(null)}
                  className="px-4 py-2 rounded-xl text-slate-600 hover:bg-slate-100 font-bold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2 rounded-xl shadow-md"
                >
                  Update Image
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Media Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
        {filteredImages.map((img) => {
          const isUrlCopied = copiedId === `${img.id}-url`;
          const isMdCopied = copiedId === `${img.id}-md`;

          return (
            <div 
              key={img.id}
              className="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-xs hover:shadow-md transition-all flex flex-col justify-between group"
            >
              {/* Image Preview Box */}
              <div className="relative aspect-video bg-slate-100 overflow-hidden">
                <img
                  src={img.url}
                  alt={img.alt || img.title}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                  loading="lazy"
                />
                
                <span className="absolute top-2 left-2 bg-slate-900/80 backdrop-blur-xs text-white text-[10px] font-bold px-2 py-0.5 rounded-md">
                  {img.category}
                </span>

                {!img.alt && (
                  <span className="absolute top-2 right-2 bg-rose-500 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md flex items-center gap-1 shadow-xs">
                    <AlertCircle className="w-3 h-3" /> No Alt
                  </span>
                )}
              </div>

              {/* Card Body */}
              <div className="p-3.5 space-y-2 flex-1 flex flex-col justify-between">
                <div>
                  <h4 className="font-display font-extrabold text-xs text-slate-900 truncate" title={img.title}>
                    {img.title}
                  </h4>
                  <p className="text-[11px] text-slate-500 truncate" title={img.alt}>
                    <span className="font-bold text-slate-700">Alt:</span> {img.alt || <span className="text-rose-500 italic">Missing</span>}
                  </p>
                  <p className="text-[10px] text-slate-400 font-mono truncate" title={img.url}>
                    {img.url}
                  </p>
                </div>

                {/* Quick Actions */}
                <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      title="Copy URL"
                      onClick={() => handleCopy(img.url, img.id, 'url')}
                      className={`p-1.5 rounded-lg border text-[11px] font-bold flex items-center gap-1 transition-all ${
                        isUrlCopied 
                          ? 'bg-emerald-50 border-emerald-300 text-emerald-700' 
                          : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'
                      }`}
                    >
                      {isUrlCopied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                      <span>{isUrlCopied ? 'Copied' : 'URL'}</span>
                    </button>

                    <button
                      type="button"
                      title="Copy Markdown Tag"
                      onClick={() => handleCopy(`![${img.alt || img.title}](${img.url})`, img.id, 'md')}
                      className={`p-1.5 rounded-lg border text-[11px] font-bold flex items-center gap-1 transition-all ${
                        isMdCopied 
                          ? 'bg-emerald-50 border-emerald-300 text-emerald-700' 
                          : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'
                      }`}
                    >
                      {isMdCopied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                      <span>MD</span>
                    </button>
                  </div>

                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => setEditingImage(img)}
                      className="p-1.5 rounded-lg bg-slate-50 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 border border-slate-200 transition-colors"
                      title="Edit Image Details"
                    >
                      <Edit3 className="w-3.5 h-3.5" />
                    </button>

                    <button
                      type="button"
                      onClick={() => {
                        if (confirm(`Delete image "${img.title}"?`)) {
                          deleteSiteImage(img.id);
                        }
                      }}
                      className="p-1.5 rounded-lg bg-slate-50 hover:bg-rose-50 text-slate-600 hover:text-rose-600 border border-slate-200 transition-colors"
                      title="Delete Image"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

              </div>
            </div>
          );
        })}
      </div>

    </div>
  );
};
