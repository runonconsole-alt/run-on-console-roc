import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { 
  Code, Copy, Check, ExternalLink, Sparkles, CheckCircle2, 
  HelpCircle, ShoppingBag, FileText, Building2, Layers, AlertCircle, Save
} from 'lucide-react';

export const AdminSchemasManager = () => {
  const { showNotification } = useApp();

  const [selectedSchemaType, setSelectedSchemaType] = useState('Organization');
  const [copied, setCopied] = useState(false);
  const [jsonError, setJsonError] = useState(null);

  // Form State for different schemas
  const [orgData, setOrgData] = useState({
    name: 'Run On Console',
    url: 'https://runonconsole.com',
    logo: 'https://runonconsole.com/favicon.svg',
    description: 'Independent gaming hardware intelligence lab, benchmark testing facility, and platform reviews desk.',
    twitter: 'https://twitter.com/runonconsole',
    youtube: 'https://youtube.com/@runonconsole'
  });

  const [articleData, setArticleData] = useState({
    headline: 'Call of Duty: Black Ops 6 – Everything We Know So Far',
    url: 'https://runonconsole.com/blogs/call-of-duty-black-ops-6-everything-we-know-so-far/',
    image: 'https://runonconsole.com/images/hero_cod.jpg',
    datePublished: '2024-05-20T10:00:00+00:00',
    dateModified: '2024-05-20T10:00:00+00:00',
    authorName: 'Omar Abobakar',
    publisherName: 'Run On Console'
  });

  const [productData, setProductData] = useState({
    name: 'ASUS ROG Strix GeForce RTX 4070 Ti Super OC',
    description: 'Elite 1440p and 4K gaming graphics card with triple Axial-tech cooling and 16GB GDDR6X VRAM.',
    image: 'https://runonconsole.com/images/hero_cod.jpg',
    brand: 'ASUS ROG',
    sku: 'ROG-STRIX-RTX4070TIS-O16G',
    price: '849.99',
    currency: 'USD',
    ratingValue: '4.9',
    reviewCount: '142'
  });

  const [faqData, setFaqData] = useState([
    { question: 'How are gaming frame rates benchmarked on Run On Console?', answer: 'We capture hardware frametimes using CapFrameX and FCAT frame capture tools across 10-minute standardized loops at 1080p, 1440p, and 4K.' },
    { question: 'Can I check if my PC can run Black Ops 6?', answer: 'Yes! Use our Game Compatibility Tool to compare your CPU, GPU, and RAM against verified minimum and recommended requirements.' },
    { question: 'Are your hardware product reviews sponsored?', answer: 'No. All tested hardware is either purchased retail or subject to strictly independent lab testing guidelines.' }
  ]);

  // Generate JSON-LD dynamically
  const generateSchemaJson = () => {
    if (selectedSchemaType === 'Organization') {
      return {
        '@context': 'https://schema.org',
        '@type': 'Organization',
        '@id': `${orgData.url}/#organization`,
        'name': orgData.name,
        'url': orgData.url,
        'logo': orgData.logo,
        'description': orgData.description,
        'sameAs': [orgData.twitter, orgData.youtube].filter(Boolean)
      };
    } else if (selectedSchemaType === 'Article') {
      return {
        '@context': 'https://schema.org',
        '@type': 'BlogPosting',
        'headline': articleData.headline,
        'image': articleData.image,
        'datePublished': articleData.datePublished,
        'dateModified': articleData.dateModified,
        'author': {
          '@type': 'Person',
          'name': articleData.authorName
        },
        'publisher': {
          '@type': 'Organization',
          'name': articleData.publisherName,
          'logo': {
            '@type': 'ImageObject',
            'url': 'https://runonconsole.com/favicon.svg'
          }
        },
        'mainEntityOfPage': {
          '@type': 'WebPage',
          '@id': articleData.url
        }
      };
    } else if (selectedSchemaType === 'Product') {
      return {
        '@context': 'https://schema.org',
        '@type': 'Product',
        'name': productData.name,
        'image': productData.image,
        'description': productData.description,
        'brand': {
          '@type': 'Brand',
          'name': productData.brand
        },
        'sku': productData.sku,
        'offers': {
          '@type': 'Offer',
          'price': productData.price,
          'priceCurrency': productData.currency,
          'availability': 'https://schema.org/InStock',
          'url': 'https://runonconsole.com/products/'
        },
        'aggregateRating': {
          '@type': 'AggregateRating',
          'ratingValue': productData.ratingValue,
          'reviewCount': productData.reviewCount,
          'bestRating': '5',
          'worstRating': '1'
        }
      };
    } else if (selectedSchemaType === 'FAQPage') {
      return {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        'mainEntity': faqData.map(f => ({
          '@type': 'Question',
          'name': f.question,
          'acceptedAnswer': {
            '@type': 'Answer',
            'text': f.answer
          }
        }))
      };
    }
    return {};
  };

  const schemaObject = generateSchemaJson();
  const jsonString = JSON.stringify(schemaObject, null, 2);

  const handleCopyCode = () => {
    const fullSnippet = `<script type="application/ld+json">\n${jsonString}\n</script>`;
    navigator.clipboard.writeText(fullSnippet);
    setCopied(true);
    showNotification('JSON-LD script copied to clipboard!');
    setTimeout(() => setCopied(false), 2000);
  };

  const handleOpenGoogleTester = () => {
    window.open('https://search.google.com/test/rich-results', '_blank');
  };

  return (
    <div className="space-y-6 max-w-7xl animate-page-in">
      
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2 mb-1">
            <span className="text-[10px] text-amber-800 bg-amber-100 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider">
              Google Structured Data Studio
            </span>
          </div>
          <h2 className="font-display font-extrabold text-2xl text-slate-900">SCHEMAS (JSON-LD) MANAGER</h2>
          <p className="text-xs text-slate-500 font-medium">
            Generate, validate, and customize Google Rich Snippet Structured Data for high CTR search results
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={handleOpenGoogleTester}
            className="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold py-2.5 px-3.5 rounded-xl flex items-center gap-1.5 transition-colors"
          >
            <ExternalLink className="w-3.5 h-3.5" /> Google Rich Results Test
          </button>
          <button
            onClick={handleCopyCode}
            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-2.5 px-4 rounded-xl flex items-center gap-2 shadow-md shadow-emerald-600/20 transition-all hover:scale-105"
          >
            {copied ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
            <span>{copied ? 'COPIED SCRIPT' : 'COPY <SCRIPT> JSON-LD'}</span>
          </button>
        </div>
      </div>

      {/* Schema Type Selector */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        {[
          { id: 'Organization', label: 'Organization & Publisher', icon: Building2 },
          { id: 'Article', label: 'Article & BlogPosting', icon: FileText },
          { id: 'Product', label: 'Product & Rating', icon: ShoppingBag },
          { id: 'FAQPage', label: 'FAQPage Rich Snippet', icon: HelpCircle },
        ].map(s => {
          const Icon = s.icon;
          const isSelected = selectedSchemaType === s.id;
          return (
            <button
              key={s.id}
              onClick={() => setSelectedSchemaType(s.id)}
              className={`p-4 rounded-2xl border text-left flex flex-col justify-between transition-all ${
                isSelected 
                  ? 'bg-slate-900 border-slate-900 text-white shadow-md' 
                  : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'
              }`}
            >
              <Icon className={`w-5 h-5 mb-2 ${isSelected ? 'text-emerald-400' : 'text-slate-400'}`} />
              <div className="font-extrabold text-xs">{s.label}</div>
            </button>
          );
        })}
      </div>

      {/* Main Grid: Form Inputs (Left) and Live JSON-LD Code (Right) */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        {/* Left: Input Form (6 cols) */}
        <div className="lg:col-span-6 bg-white border border-slate-200 rounded-3xl p-6 shadow-xs space-y-4">
          <div className="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 className="font-display font-black text-sm text-slate-900 flex items-center gap-2">
              <Sparkles className="w-4 h-4 text-emerald-600" /> Configure {selectedSchemaType} Properties
            </h3>
            <span className="text-[10px] text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded">
              schema.org compliant
            </span>
          </div>

          {/* Form for Organization */}
          {selectedSchemaType === 'Organization' && (
            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Organization Name</label>
                <input
                  type="text"
                  value={orgData.name}
                  onChange={(e) => setOrgData({ ...orgData, name: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Website URL</label>
                <input
                  type="url"
                  value={orgData.url}
                  onChange={(e) => setOrgData({ ...orgData, url: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Logo URL</label>
                <input
                  type="url"
                  value={orgData.logo}
                  onChange={(e) => setOrgData({ ...orgData, logo: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Description</label>
                <textarea
                  rows={3}
                  value={orgData.description}
                  onChange={(e) => setOrgData({ ...orgData, description: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Twitter / X URL</label>
                  <input
                    type="url"
                    value={orgData.twitter}
                    onChange={(e) => setOrgData({ ...orgData, twitter: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">YouTube URL</label>
                  <input
                    type="url"
                    value={orgData.youtube}
                    onChange={(e) => setOrgData({ ...orgData, youtube: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Form for Article */}
          {selectedSchemaType === 'Article' && (
            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Article Headline</label>
                <input
                  type="text"
                  value={articleData.headline}
                  onChange={(e) => setArticleData({ ...articleData, headline: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Article Canonical URL</label>
                <input
                  type="url"
                  value={articleData.url}
                  onChange={(e) => setArticleData({ ...articleData, url: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                />
              </div>

              <div>
                <label className="block font-bold text-slate-700 mb-1">Featured Image URL</label>
                <input
                  type="url"
                  value={articleData.image}
                  onChange={(e) => setArticleData({ ...articleData, image: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Author Name</label>
                  <input
                    type="text"
                    value={articleData.authorName}
                    onChange={(e) => setArticleData({ ...articleData, authorName: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Publisher</label>
                  <input
                    type="text"
                    value={articleData.publisherName}
                    onChange={(e) => setArticleData({ ...articleData, publisherName: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Form for Product */}
          {selectedSchemaType === 'Product' && (
            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-bold text-slate-700 mb-1">Product Title</label>
                <input
                  type="text"
                  value={productData.name}
                  onChange={(e) => setProductData({ ...productData, name: e.target.value })}
                  className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Brand</label>
                  <input
                    type="text"
                    value={productData.brand}
                    onChange={(e) => setProductData({ ...productData, brand: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">SKU / Model Number</label>
                  <input
                    type="text"
                    value={productData.sku}
                    onChange={(e) => setProductData({ ...productData, sku: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Offer Price</label>
                  <input
                    type="text"
                    value={productData.price}
                    onChange={(e) => setProductData({ ...productData, price: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Currency</label>
                  <input
                    type="text"
                    value={productData.currency}
                    onChange={(e) => setProductData({ ...productData, currency: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Rating Value (1-5)</label>
                  <input
                    type="text"
                    value={productData.ratingValue}
                    onChange={(e) => setProductData({ ...productData, ratingValue: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-emerald-700"
                  />
                </div>
                <div>
                  <label className="block font-bold text-slate-700 mb-1">Total Reviews Count</label>
                  <input
                    type="text"
                    value={productData.reviewCount}
                    onChange={(e) => setProductData({ ...productData, reviewCount: e.target.value })}
                    className="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Form for FAQPage */}
          {selectedSchemaType === 'FAQPage' && (
            <div className="space-y-3 text-xs">
              <span className="text-[11px] text-slate-500 block mb-2">
                FAQ question-and-answer items for Google Search accordion rich snippets:
              </span>

              {faqData.map((f, i) => (
                <div key={i} className="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                  <div className="font-bold text-slate-800">Q{i + 1}: {f.question}</div>
                  <div className="text-[11px] text-slate-600 line-clamp-2">{f.answer}</div>
                </div>
              ))}
            </div>
          )}

        </div>

        {/* Right: Live JSON-LD Output (6 cols) */}
        <div className="lg:col-span-6 bg-slate-900 rounded-3xl p-6 shadow-xl border border-slate-800 flex flex-col justify-between text-xs space-y-4">
          <div>
            <div className="flex justify-between items-center pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2">
                <Code className="w-4 h-4 text-emerald-400" />
                <span className="font-mono text-xs font-extrabold text-white">JSON-LD Output</span>
              </div>
              <span className="text-[10px] font-mono text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-500/30">
                application/ld+json
              </span>
            </div>

            <pre className="mt-4 bg-slate-950 p-4 rounded-2xl text-emerald-300 font-mono text-[11px] overflow-x-auto max-h-[420px] leading-relaxed border border-slate-800">
              {`<script type="application/ld+json">\n${jsonString}\n</script>`}
            </pre>
          </div>

          <div className="pt-3 border-t border-slate-800 flex justify-between items-center">
            <span className="text-[10px] text-slate-400 flex items-center gap-1">
              <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
              Valid JSON-LD structure ready for indexing
            </span>

            <button
              onClick={handleCopyCode}
              className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2 px-4 rounded-xl flex items-center gap-1.5 transition-all"
            >
              {copied ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
              <span>Copy Script</span>
            </button>
          </div>
        </div>

      </div>

    </div>
  );
};
