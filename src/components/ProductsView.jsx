import React, { useState, useMemo } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Star, ShoppingCart, SlidersHorizontal, CheckCircle2, 
  Filter, Search, RefreshCw, ExternalLink, ArrowRight, 
  ArrowLeft, Check, ShieldCheck, AlertTriangle, Sparkles, Tag, 
  RotateCcw, PackageCheck, Zap, Laptop, Monitor, Headphones, Mouse, Cable, Cpu,
  Plus, Trash2, Gauge, Scale, Layers, Flame, Activity, Disc, Smartphone, Radio, Volume2
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { playClickSound, playHoverSound, playPowerUpSound } from '../utils/audioEffects';
import confetti from 'canvas-confetti';

export const ProductsView = () => {
  const { 
    products = [], 
    selectedProductId, 
    setSelectedProductId, 
    navigateToProduct, 
    toggleCompare, 
    compareIds = [], 
    searchQuery,
    pageFaqs 
  } = useApp();

  // Filters State
  const [activeCategoryFilter, setActiveCategoryFilter] = useState("all");
  const [selectedBrand, setSelectedBrand] = useState("all");
  const [selectedColor, setSelectedColor] = useState("all");
  const [maxPrice, setMaxPrice] = useState(5000);
  const [primeOnly, setPrimeOnly] = useState(false);
  const [minRating, setMinRating] = useState(0);
  const [sortBy, setSortBy] = useState("rating");

  // --- OPERATOR ACTIVE LOADOUT STATE ---
  const [equippedIds, setEquippedIds] = useState(['prod-102', 'prod-101', 'prod-104', 'prod-gpu-1']);
  const [selectedToEquipId, setSelectedToEquipId] = useState(products[0]?.id || 'prod-102');

  // --- HEAD-TO-HEAD BENCHMARK STATE ---
  const [compareUnitAId, setCompareUnitAId] = useState('prod-102'); // Razer DeathAdder V3 Pro
  const [compareUnitBId, setCompareUnitBId] = useState('prod-101'); // Logitech G Pro X TKL

  const equippedProducts = useMemo(() => {
    return equippedIds
      .map(id => products.find(p => p.id === id))
      .filter(Boolean);
  }, [equippedIds, products]);

  // Loadout Telemetry Metrics Calculation
  const loadoutMetrics = useMemo(() => {
    if (equippedProducts.length === 0) {
      return { totalMass: 0, avgScore: 0, stability: '99.98%', debounce: '0.15ms', gameRating: 0 };
    }

    const totalScore = equippedProducts.reduce((sum, p) => sum + (p.rocScore || p.rating || 9.0), 0);
    const avgScore = Number((totalScore / equippedProducts.length).toFixed(2));
    
    // Estimate weight/mass in grams
    const totalMass = equippedProducts.length * 420 + 79;
    
    // Game Compatibility Rating out of 10
    const hasGpu = equippedProducts.some(p => p.categorySlug === 'gpu' || p.categorySlug === 'pc-gaming');
    const hasCpu = equippedProducts.some(p => p.categorySlug === 'cpu' || p.categorySlug === 'pc-gaming');
    const hasRam = equippedProducts.some(p => p.categorySlug === 'storage-memory');
    
    let gameRating = avgScore;
    if (hasGpu && hasCpu) gameRating = 9.9;
    else if (hasGpu || hasCpu) gameRating = 9.4;

    return {
      totalMass,
      avgScore,
      stability: '99.98%',
      debounce: '0.15ms',
      gameRating: Number(gameRating.toFixed(1))
    };
  }, [equippedProducts]);

  const handleEquipProduct = () => {
    if (!selectedToEquipId) return;
    if (!equippedIds.includes(selectedToEquipId)) {
      playPowerUpSound();
      setEquippedIds([...equippedIds, selectedToEquipId]);
    } else {
      playClickSound();
    }
  };

  const handleRemoveEquipped = (id) => {
    playClickSound();
    setEquippedIds(equippedIds.filter(itemId => itemId !== id));
  };

  const unitA = useMemo(() => products.find(p => p.id === compareUnitAId) || products[0], [compareUnitAId, products]);
  const unitB = useMemo(() => products.find(p => p.id === compareUnitBId) || products[1], [compareUnitBId, products]);

  const brands = ["all", "ASUS", "MSI", "AMD", "Intel", "Samsung", "Corsair", "SanDisk", "Razer", "Logitech", "Creative", "SteelSeries", "HyperX", "Secretlab", "Valve"];
  const colors = ["all", "Black", "White", "Cyber Pink", "Silver", "Gunmetal Gray"];

  const parsePrice = (pStr) => {
    if (!pStr) return 0;
    const num = parseFloat(pStr.replace(/[^0-9.]/g, ''));
    return isNaN(num) ? 0 : num;
  };

  const handleResetFilters = () => {
    playClickSound();
    setActiveCategoryFilter("all");
    setSelectedBrand("all");
    setSelectedColor("all");
    setMaxPrice(5000);
    setPrimeOnly(false);
    setMinRating(0);
    setSortBy("rating");
  };

  const handleBuyClick = () => {
    playClickSound();
    try {
      confetti({
        particleCount: 50,
        spread: 60,
        origin: { y: 0.7 },
        colors: ['#10B981', '#34D399', '#06B6D4', '#F59E0B', '#FFFFFF']
      });
    } catch (err) {}
  };

  // --- PRODUCT DETAIL PAGE (If single product selected) ---
  if (selectedProductId) {
    const product = products.find(p => p.id === selectedProductId || p.slug === selectedProductId) || products[0];
    const isComparing = compareIds.includes(product.id);
    const related = products.filter(p => p.category === product.category && p.id !== product.id).slice(0, 3);

    const amazonLink = product.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20";
    const bestbuyLink = product.affiliateLinks?.bestbuy || "https://bestbuy.com";
    const officialLink = product.affiliateLinks?.official || "https://store.com";

    return (
      <div className="max-w-6xl mx-auto py-6 space-y-8 animate-page-in">
        
        {/* Back and Compare Navigation */}
        <div className="flex items-center justify-between">
          <button 
            onClick={() => {
              playClickSound();
              setSelectedProductId(null);
            }}
            className="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2 rounded-xl flex items-center gap-2 transition-colors shadow-sm"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to All Gear & Products</span>
          </button>

          <button 
            onClick={() => {
              playClickSound();
              toggleCompare(product.id);
            }}
            className={`text-xs font-bold px-4 py-2 rounded-xl border transition-all flex items-center gap-2 shadow-sm ${
              isComparing 
                ? 'bg-emerald-600 text-white border-emerald-600' 
                : 'bg-white border-slate-300 text-slate-700 hover:border-emerald-600'
            }`}
          >
            <SlidersHorizontal className="w-4 h-4" />
            <span>{isComparing ? '✓ Added to Compare' : 'Add to Compare'}</span>
          </button>
        </div>

        {/* Hero Product Overview Card */}
        <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-sm grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          {/* Left: Product Image with 3D Physics */}
          <div className="lg:col-span-5 flex flex-col justify-center">
            <Tilt3DCard className="relative rounded-2xl overflow-hidden bg-slate-950 p-4 border border-slate-800 shadow-xl group">
              <img 
                src={product.image} 
                alt={product.title} 
                className="w-full h-80 object-cover rounded-xl group-hover:scale-105 transition-transform duration-500" 
              />
              <span className="absolute top-4 left-4 bg-emerald-600 text-white font-extrabold text-[10px] px-3 py-1 rounded-full uppercase tracking-wider badge-glow">
                {product.badge || "VERIFIED LAB GEAR"}
              </span>
            </Tilt3DCard>
          </div>

          {/* Right: Specs & Live Buy Pricing */}
          <div className="lg:col-span-7 space-y-5">
            <div>
              <span className="text-xs font-bold text-emerald-600 uppercase tracking-wider">{product.category}</span>
              <h1 className="font-display font-extrabold text-2xl sm:text-3xl text-slate-900 leading-tight mt-1">
                {product.title}
              </h1>
              <p className="text-xs sm:text-sm text-slate-500 font-medium mt-1">
                {product.subtitle}
              </p>
            </div>

            <div className="flex items-center gap-4 border-y border-slate-100 py-3">
              <div>
                <span className="font-display font-extrabold text-2xl text-emerald-600">{product.price}</span>
                {product.originalPrice && (
                  <span className="text-xs text-slate-400 line-through ml-2">{product.originalPrice}</span>
                )}
              </div>
              <span className="bg-emerald-50 border border-emerald-200 text-emerald-800 font-extrabold text-xs px-2.5 py-1 rounded-lg">
                ★ {product.rocScore || product.rating} ROC Score / 10
              </span>
            </div>

            <p className="text-xs sm:text-sm text-slate-600 leading-relaxed">
              {product.fullReview || product.shortDesc}
            </p>

            {/* Direct Affiliate Buttons */}
            <div className="space-y-2 pt-2">
              <a 
                href={amazonLink}
                target="_blank"
                rel="noopener noreferrer"
                onClick={handleBuyClick}
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-sm py-3.5 px-6 rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/30 transition-all hover:scale-102"
              >
                <ShoppingCart className="w-4 h-4" />
                <span>Check Best Price & Stock on Amazon</span>
                <ExternalLink className="w-4 h-4 opacity-70" />
              </a>

              <div className="grid grid-cols-2 gap-2">
                <a 
                  href={bestbuyLink}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs py-2.5 px-4 rounded-xl flex items-center justify-center gap-1.5 transition-colors"
                >
                  <span>Best Buy Store</span>
                  <ExternalLink className="w-3 h-3 text-slate-400" />
                </a>
                <a 
                  href={officialLink}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs py-2.5 px-4 rounded-xl flex items-center justify-center gap-1.5 transition-colors"
                >
                  <span>Official Brand Store</span>
                  <ExternalLink className="w-3 h-3 text-slate-400" />
                </a>
              </div>
            </div>

          </div>
        </div>

        {/* Specs Table */}
        {product.specs && (
          <div className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4">
            <h3 className="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider flex items-center gap-2">
              <Cpu className="w-4 h-4 text-emerald-600" />
              <span>Laboratory Benchmark Specifications</span>
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
              {Object.entries(product.specs).map(([key, val]) => (
                <div key={key} className="flex justify-between p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <span className="font-bold text-slate-500">{key}:</span>
                  <span className="font-semibold text-slate-900 text-right">{val}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Related Products */}
        {related.length > 0 && (
          <div className="space-y-4">
            <h3 className="font-display font-extrabold text-base text-slate-900 uppercase tracking-wider">
              Related Category Gear
            </h3>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              {related.map((rel) => (
                <Tilt3DCard
                  key={rel.id}
                  onClick={() => {
                    playClickSound();
                    navigateToProduct(rel.id);
                  }}
                  className="game-card p-4 cursor-pointer flex flex-col justify-between"
                >
                  <img src={rel.image} alt={rel.title} className="w-full h-36 object-cover rounded-xl mb-3" />
                  <div>
                    <h4 className="font-display font-bold text-sm text-slate-900 line-clamp-1">{rel.title}</h4>
                    <span className="text-xs text-emerald-600 font-extrabold">{rel.price}</span>
                  </div>
                </Tilt3DCard>
              ))}
            </div>
          </div>
        )}

      </div>
    );
  }

  // --- PRODUCTS CATALOG WITH BouncyText ---
  const categories = [
    "all", 
    "Graphic Cards (GPU)",
    "Processors (CPU)",
    "Storage & Memory Cards",
    "Gaming Monitors",
    "Keyboards", 
    "Gaming Mice", 
    "Headsets & Audio", 
    "Speakers & Soundbars",
    "Handsfree & Earbuds",
    "Chargers, Docks & Power",
    "Cables & Mod Gear", 
    "Controllers & Gear", 
    "Chairs & Desks", 
    "PC Gaming Rigs", 
    "Handheld Gaming Consoles"
  ];

  let filtered = products.filter(p => {
    if (activeCategoryFilter !== "all" && p.category !== activeCategoryFilter && p.categorySlug !== activeCategoryFilter) {
      return false;
    }
    if (selectedBrand !== "all") {
      const matchBrand = p.title.toLowerCase().includes(selectedBrand.toLowerCase()) || 
                         (p.subtitle || "").toLowerCase().includes(selectedBrand.toLowerCase());
      if (!matchBrand) return false;
    }
    if (selectedColor !== "all") {
      const colorQuery = selectedColor.toLowerCase();
      const matchColor = (p.title + " " + p.subtitle + " " + (p.shortDesc || "")).toLowerCase().includes(colorQuery);
      if (!matchColor) return false;
    }
    const priceNum = parsePrice(p.price);
    if (priceNum > maxPrice) return false;
    if (primeOnly && !p.primeEligible) return false;
    if (minRating > 0 && (p.rating || 4.8) < minRating) return false;
    if (searchQuery) {
      const q = searchQuery.toLowerCase();
      return p.title.toLowerCase().includes(q) || 
             (p.subtitle || "").toLowerCase().includes(q) || 
             (p.shortDesc || "").toLowerCase().includes(q);
    }
    return true;
  });

  filtered.sort((a, b) => {
    if (sortBy === "rating") return (b.rating || b.rocScore) - (a.rating || a.rocScore);
    if (sortBy === "price_asc") return parsePrice(a.price) - parsePrice(b.price);
    if (sortBy === "price_desc") return parsePrice(b.price) - parsePrice(a.price);
    return 0;
  });

  return (
    <div className="space-y-10 py-6 animate-page-in">
      
      {/* High-Impact Visual Products Hero Banner with BouncyText */}
      <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        
        <CyberMatrixHoloBackground />

        <div className="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          
          <div className="lg:col-span-7 space-y-4">
            <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
              <Sparkles className="w-3.5 h-3.5" /> COMPLETE GAMING HARDWARE, PERIPHERALS & ACCESSORIES
            </span>
            <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
              <BouncyText text="Gaming Gear, Cables &" enableAudio={true} /> <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
                <BouncyText text="Hardware Accessories Catalog" enableAudio={true} />
              </span>
            </h1>
            <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">
              GPUs, CPUs, RAM, NVMe SSDs, Fast Chargers, 360Hz Monitors, Keyboards, Mice, Speakers, Handsfree, Cables & Docks with verified Amazon affiliate pricing.
            </p>

            <div className="flex flex-wrap gap-2.5 pt-2">
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <PackageCheck className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
                <BouncyText text={`${products.length} Benchmark Verified Products`} />
              </span>

              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5" style={{ animationDelay: '0.6s' }}>
                <RefreshCw className="w-3.5 h-3.5 text-teal-400 animate-spin" />
                <BouncyText text="Amazon & Best Buy Price Sync" />
              </span>
            </div>
          </div>

          <div className="lg:col-span-5 grid grid-cols-2 gap-3">
            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/cyber_keyboard.jpg" 
                alt="Cyber Keyboard" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Custom Peripherals</div>
              <div className="text-[10px] text-emerald-300 font-medium">Hot-Swap • Coiled Cables</div>
            </Tilt3DCard>

            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/gaming_monitor.jpg" 
                alt="Gaming Monitors" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Fast OLED Displays</div>
              <div className="text-[10px] text-emerald-300 font-medium">360Hz • 0.03ms QD-OLED</div>
            </Tilt3DCard>
          </div>

        </div>
      </div>

      {/* 2. OPERATOR ACTIVE LOADOUT & HEAD-TO-HEAD BENCHMARK (Exact Match to User's Uploaded Mockup) */}
      <section className="bg-slate-950 text-white rounded-3xl p-6 sm:p-8 shadow-2xl border-2 border-emerald-500/30 space-y-6 relative overflow-hidden">
        
        {/* Top 4 Telemetry Metrics Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
          
          <div className="bg-slate-900/90 border border-slate-800 p-4 rounded-2xl space-y-1 relative overflow-hidden">
            <span className="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest block">
              POLLING STABILITY
            </span>
            <div className="text-xl sm:text-2xl font-mono font-extrabold text-cyan-400">
              {loadoutMetrics.stability}
            </div>
            <span className="text-[9px] font-mono text-cyan-300/70 uppercase block">
              4000HZ PASS
            </span>
            <Activity className="absolute right-3 top-3 w-5 h-5 text-cyan-400/30" />
          </div>

          <div className="bg-slate-900/90 border border-slate-800 p-4 rounded-2xl space-y-1 relative overflow-hidden">
            <span className="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest block">
              CLICK DEBOUNCE
            </span>
            <div className="text-xl sm:text-2xl font-mono font-extrabold text-emerald-400">
              {loadoutMetrics.debounce}
            </div>
            <span className="text-[9px] font-mono text-emerald-300/70 uppercase block">
              OPTICAL ZERO DEBOUNCE
            </span>
            <Zap className="absolute right-3 top-3 w-5 h-5 text-emerald-400/30" />
          </div>

          <div className="bg-slate-900/90 border border-slate-800 p-4 rounded-2xl space-y-1 relative overflow-hidden">
            <span className="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest block">
              LOADOUT WEIGHT
            </span>
            <div className="text-xl sm:text-2xl font-mono font-extrabold text-fuchsia-400">
              {loadoutMetrics.totalMass}g
            </div>
            <span className="text-[9px] font-mono text-fuchsia-300/70 uppercase block">
              {equippedProducts.length} UNITS COMBINED
            </span>
            <Scale className="absolute right-3 top-3 w-5 h-5 text-fuchsia-400/30" />
          </div>

          <div className="bg-slate-900/90 border border-slate-800 p-4 rounded-2xl space-y-1 relative overflow-hidden">
            <span className="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest block">
              LOADOUT INDEX
            </span>
            <div className="text-xl sm:text-2xl font-mono font-extrabold text-cyan-400">
              {loadoutMetrics.avgScore}
            </div>
            <span className="text-[9px] font-mono text-emerald-400 uppercase block font-bold">
              FRAG TIER 1 (COMPATIBLE: {loadoutMetrics.gameRating}/10)
            </span>
            <Gauge className="absolute right-3 top-3 w-5 h-5 text-cyan-400/30" />
          </div>

        </div>

        {/* Two Column Layout: Left (Operator Active Loadout) + Right (Head-to-Head Benchmark) */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          
          {/* LEFT: OPERATOR ACTIVE LOADOUT BOX */}
          <div className="lg:col-span-6 bg-slate-900/90 border-2 border-cyan-500/40 rounded-2xl p-5 space-y-4 shadow-xl">
            
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <div className="flex items-center gap-2 text-cyan-400 font-display font-extrabold text-xs uppercase tracking-wider">
                <Cpu className="w-4 h-4" />
                <span>OPERATOR ACTIVE LOADOUT</span>
              </div>
              <span className="text-[10px] font-mono text-cyan-400 border border-cyan-500/40 px-2 py-0.5 rounded bg-cyan-950/40 font-bold">
                {equippedProducts.length} HARDWARE ITEMS
              </span>
            </div>

            {/* Equip New Hardware Dropdown Bar */}
            <div className="flex items-center gap-2">
              <select
                value={selectedToEquipId}
                onChange={(e) => setSelectedToEquipId(e.target.value)}
                className="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-mono text-slate-300 focus:outline-none focus:border-cyan-500"
              >
                <option value="">-- SELECT HARDWARE TO EQUIP --</option>
                {products.map(p => (
                  <option key={p.id} value={p.id}>
                    [{p.category}] {p.title} ({p.price})
                  </option>
                ))}
              </select>
              <button
                onClick={handleEquipProduct}
                className="bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-display font-extrabold text-xs px-4 py-2 rounded-xl transition-all hover:scale-105 shadow-md shadow-cyan-500/20 shrink-0 flex items-center gap-1"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>EQUIP</span>
              </button>
            </div>

            {/* Equipped Items List */}
            <div className="space-y-2.5 max-h-[360px] overflow-y-auto pr-1" style={{ scrollbarWidth: 'thin' }}>
              {equippedProducts.map((item) => (
                <div 
                  key={item.id}
                  className="bg-slate-950 border border-slate-800 hover:border-cyan-500/40 p-3 rounded-xl flex items-center justify-between gap-3 group transition-all"
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <img 
                      src={item.image} 
                      alt={item.title} 
                      className="w-11 h-11 rounded-lg object-cover bg-slate-900 border border-slate-800 shrink-0" 
                    />
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-[9px] font-mono font-extrabold uppercase px-1.5 py-0.2 rounded bg-slate-800 text-slate-300">
                          {item.category.toUpperCase()}
                        </span>
                        <span className="text-[10px] font-mono text-cyan-400 font-bold">
                          SCORE {item.rocScore || item.rating}
                        </span>
                      </div>
                      <h4 className="font-display font-bold text-xs text-white truncate group-hover:text-cyan-300">
                        {item.title}
                      </h4>
                      <span className="text-[10px] font-mono text-slate-400">
                        {item.price} • {item.badge || "Verified Hardware"}
                      </span>
                    </div>
                  </div>

                  <button
                    onClick={() => handleRemoveEquipped(item.id)}
                    className="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-950/40 transition-colors shrink-0"
                    title="Unequip Item"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              ))}
            </div>

            {/* Loadout Summary Footer */}
            <div className="pt-3 border-t border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs font-mono">
              <div>
                <span className="text-slate-400">TOTAL MASS: </span>
                <span className="text-emerald-400 font-bold">{loadoutMetrics.totalMass}g</span>
              </div>
              <div>
                <span className="text-slate-400">AVERAGE HARDWARE SCORE: </span>
                <span className="text-cyan-400 font-bold">{loadoutMetrics.avgScore} / 10</span>
              </div>
            </div>

            {/* Buy Loadout Bundle Affiliate Link */}
            <a 
              href="https://amazon.com?tag=fragreviews-20"
              target="_blank"
              rel="noopener noreferrer"
              onClick={handleBuyClick}
              className="w-full bg-gradient-to-r from-cyan-500 to-emerald-500 hover:from-cyan-400 hover:to-emerald-400 text-slate-950 font-display font-extrabold text-xs py-3 px-4 rounded-xl flex items-center justify-center gap-2 shadow-lg shadow-cyan-500/20 transition-all hover:scale-102"
            >
              <ShoppingCart className="w-4 h-4" />
              <span>BUY EQUIPPED LOADOUT BUNDLE ON AMAZON</span>
              <ExternalLink className="w-3.5 h-3.5 opacity-80" />
            </a>

          </div>

          {/* RIGHT: HEAD-TO-HEAD BENCHMARK TELEMETRY BOX */}
          <div className="lg:col-span-6 bg-slate-900/90 border-2 border-fuchsia-500/30 rounded-2xl p-5 space-y-4 shadow-xl">
            
            <div className="flex items-center justify-between border-b border-slate-800 pb-3">
              <div className="flex items-center gap-2 text-fuchsia-400 font-display font-extrabold text-xs uppercase tracking-wider">
                <Activity className="w-4 h-4" />
                <span>HEAD-TO-HEAD BENCHMARK</span>
              </div>
              <span className="text-[10px] font-mono text-fuchsia-400 border border-fuchsia-500/40 px-2 py-0.5 rounded bg-fuchsia-950/40 font-bold">
                TELEMETRY COMPARISON
              </span>
            </div>

            {/* Selectors Unit A vs Unit B */}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1">
                <label className="text-[10px] font-mono text-slate-400 uppercase block">UNIT A:</label>
                <select
                  value={compareUnitAId}
                  onChange={(e) => {
                    playClickSound();
                    setCompareUnitAId(e.target.value);
                  }}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs font-mono text-cyan-300 focus:outline-none focus:border-cyan-400"
                >
                  {products.map(p => (
                    <option key={p.id} value={p.id}>
                      {p.title}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1">
                <label className="text-[10px] font-mono text-slate-400 uppercase block">UNIT B:</label>
                <select
                  value={compareUnitBId}
                  onChange={(e) => {
                    playClickSound();
                    setCompareUnitBId(e.target.value);
                  }}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-2.5 py-1.5 text-xs font-mono text-fuchsia-300 focus:outline-none focus:border-fuchsia-400"
                >
                  {products.map(p => (
                    <option key={p.id} value={p.id}>
                      {p.title}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Side-by-side Telemetry Unit Cards */}
            <div className="grid grid-cols-2 gap-3">
              
              {/* Unit A Card */}
              <div className="bg-slate-950 border border-slate-800 p-3 rounded-xl space-y-2 text-xs font-mono">
                <span className="text-[9px] font-bold text-cyan-400 border border-cyan-500/40 px-1.5 py-0.2 rounded inline-block">
                  UNIT A
                </span>
                <img src={unitA.image} alt={unitA.title} className="w-full h-24 object-cover rounded-lg bg-slate-900" />
                <h5 className="font-bold text-white text-xs truncate">{unitA.title}</h5>
                <div className="text-cyan-400 font-extrabold text-sm">SCORE {unitA.rocScore || unitA.rating}</div>

                <div className="space-y-1 pt-1 text-[10px]">
                  <div className="flex justify-between text-slate-400">
                    <span>BUILD:</span> <span className="text-cyan-300 font-bold">9.9</span>
                  </div>
                  <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-cyan-400 h-full w-[99%]"></div>
                  </div>

                  <div className="flex justify-between text-slate-400 pt-0.5">
                    <span>SENSOR:</span> <span className="text-emerald-300 font-bold">10.0</span>
                  </div>
                  <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-emerald-400 h-full w-[100%]"></div>
                  </div>

                  <div className="flex justify-between text-slate-400 pt-0.5">
                    <span>LATENCY:</span> <span className="text-cyan-300 font-bold">9.8</span>
                  </div>
                  <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-cyan-400 h-full w-[98%]"></div>
                  </div>
                </div>

                <a 
                  href={unitA.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20"}
                  target="_blank"
                  rel="noopener noreferrer"
                  onClick={handleBuyClick}
                  className="w-full bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-[10px] py-1.5 px-2 rounded-lg flex items-center justify-center gap-1 mt-2"
                >
                  <span>Buy {unitA.price}</span>
                  <ExternalLink className="w-3 h-3" />
                </a>
              </div>

              {/* Unit B Card */}
              <div className="bg-slate-950 border border-slate-800 p-3 rounded-xl space-y-2 text-xs font-mono">
                <span className="text-[9px] font-bold text-fuchsia-400 border border-fuchsia-500/40 px-1.5 py-0.2 rounded inline-block">
                  UNIT B
                </span>
                <img src={unitB.image} alt={unitB.title} className="w-full h-24 object-cover rounded-lg bg-slate-900" />
                <h5 className="font-bold text-white text-xs truncate">{unitB.title}</h5>
                <div className="text-fuchsia-400 font-extrabold text-sm">SCORE {unitB.rocScore || unitB.rating}</div>

                <div className="space-y-1 pt-1 text-[10px]">
                  <div className="flex justify-between text-slate-400">
                    <span>BUILD:</span> <span className="text-fuchsia-300 font-bold">9.5</span>
                  </div>
                  <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-fuchsia-400 h-full w-[95%]"></div>
                  </div>

                  <div className="flex justify-between text-slate-400 pt-0.5">
                    <span>SENSOR:</span> <span className="text-purple-300 font-bold">9.2</span>
                  </div>
                  <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-purple-400 h-full w-[92%]"></div>
                  </div>

                  <div className="flex justify-between text-slate-400 pt-0.5">
                    <span>LATENCY:</span> <span className="text-fuchsia-300 font-bold">9.6</span>
                  </div>
                  <div className="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-fuchsia-400 h-full w-[96%]"></div>
                  </div>
                </div>

                <a 
                  href={unitB.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20"}
                  target="_blank"
                  rel="noopener noreferrer"
                  onClick={handleBuyClick}
                  className="w-full bg-fuchsia-600 hover:bg-fuchsia-500 text-white font-bold text-[10px] py-1.5 px-2 rounded-lg flex items-center justify-center gap-1 mt-2"
                >
                  <span>Buy {unitB.price}</span>
                  <ExternalLink className="w-3 h-3" />
                </a>
              </div>

            </div>

          </div>

        </div>

      </section>

      {/* 3. Main Catalog Grid: Left Filters Sidebar + Right Products Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        {/* LEFT COMPREHENSIVE FILTER SIDEBAR */}
        <aside className="lg:col-span-3 space-y-6">
          <div className="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-6 sticky top-24">
            
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2 font-display font-extrabold text-sm text-slate-900">
                <Filter className="w-4 h-4 text-emerald-600" />
                <span>FILTER PRODUCTS</span>
              </div>
              <button 
                onClick={handleResetFilters}
                className="text-[11px] font-bold text-slate-400 hover:text-emerald-600 flex items-center gap-1 transition-colors"
                title="Reset all filters"
              >
                <RotateCcw className="w-3 h-3" /> Reset
              </button>
            </div>

            {/* Category Filter */}
            <div className="space-y-2">
              <label className="text-xs font-display font-bold text-slate-700 uppercase tracking-wider">
                Category:
              </label>
              <select
                value={activeCategoryFilter}
                onChange={(e) => setActiveCategoryFilter(e.target.value)}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
              >
                {categories.map((c) => (
                  <option key={c} value={c}>
                    {c === "all" ? "All Hardware & Accessories" : c}
                  </option>
                ))}
              </select>
            </div>

            {/* Brand Filter */}
            <div className="space-y-2">
              <label className="text-xs font-display font-bold text-slate-700 uppercase tracking-wider">
                Manufacturer / Brand:
              </label>
              <select
                value={selectedBrand}
                onChange={(e) => setSelectedBrand(e.target.value)}
                className="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
              >
                {brands.map((b) => (
                  <option key={b} value={b}>
                    {b === "all" ? "All Verified Brands" : b}
                  </option>
                ))}
              </select>
            </div>

            {/* Price Max Slider */}
            <div className="space-y-2">
              <div className="flex justify-between text-xs font-bold text-slate-700">
                <span>Max Budget:</span>
                <span className="text-emerald-600 font-extrabold">${maxPrice}</span>
              </div>
              <input
                type="range"
                min="20"
                max="5000"
                step="20"
                value={maxPrice}
                onChange={(e) => setMaxPrice(Number(e.target.value))}
                className="w-full accent-emerald-600"
              />
            </div>

            {/* Prime Only Checkbox */}
            <label className="flex items-center gap-2.5 cursor-pointer pt-2 border-t border-slate-100">
              <input
                type="checkbox"
                checked={primeOnly}
                onChange={(e) => setPrimeOnly(e.target.checked)}
                className="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500"
              />
              <span className="text-xs font-bold text-slate-700">Prime Fast 1-Day Shipping</span>
            </label>

          </div>
        </aside>

        {/* RIGHT PRODUCTS GRID */}
        <div className="lg:col-span-9 space-y-6">
          
          {/* Filter Bar / Sort Header */}
          <div className="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div className="text-xs font-bold text-slate-600">
              Showing <span className="text-emerald-600 font-extrabold">{filtered.length}</span> Verified Products
            </div>

            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-500 font-bold">Sort By:</span>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:outline-none focus:border-emerald-500"
              >
                <option value="rating">Top ROC Score / Rating</option>
                <option value="price_asc">Price: Low to High</option>
                <option value="price_desc">Price: High to Low</option>
              </select>
            </div>
          </div>

          {/* Products Grid */}
          {filtered.length === 0 ? (
            <div className="bg-white border border-slate-200 rounded-3xl p-12 text-center space-y-3">
              <p className="text-slate-500 text-sm font-semibold">No hardware products match your selected filters.</p>
              <button 
                onClick={handleResetFilters}
                className="bg-emerald-600 text-white text-xs font-bold py-2.5 px-6 rounded-xl shadow-md"
              >
                Reset All Filters
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
              {filtered.map((prod) => (
                <Tilt3DCard
                  key={prod.id}
                  className="game-card flex flex-col justify-between group p-5 space-y-4"
                  onMouseEnter={playHoverSound}
                  onClick={() => {
                    playClickSound();
                    navigateToProduct(prod.id);
                  }}
                >
                  <div className="space-y-3">
                    <div className="relative h-44 rounded-2xl overflow-hidden bg-slate-950 border border-slate-800 shadow-sm">
                      <img 
                        src={prod.image} 
                        alt={prod.title} 
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" 
                      />
                      <span className="absolute top-2.5 left-2.5 bg-emerald-600 text-white text-[9px] font-extrabold px-2.5 py-0.5 rounded-full uppercase shadow-sm">
                        {prod.badge || "VERIFIED GEAR"}
                      </span>
                    </div>

                    <div>
                      <span className="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">
                        {prod.category}
                      </span>
                      <h3 className="font-display font-extrabold text-sm sm:text-base text-slate-900 group-hover:text-emerald-600 transition-colors line-clamp-1">
                        <BouncyText text={prod.title} />
                      </h3>
                      <p className="text-[11px] text-slate-500 line-clamp-2 mt-1">
                        {prod.shortDesc || prod.subtitle}
                      </p>
                    </div>
                  </div>

                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div>
                      <span className="font-display font-extrabold text-lg text-emerald-600">{prod.price}</span>
                      {prod.originalPrice && (
                        <span className="text-[10px] text-slate-400 line-through ml-1.5">{prod.originalPrice}</span>
                      )}
                    </div>

                    <span className="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                      ★ {prod.rocScore || prod.rating}
                    </span>
                  </div>

                  {/* Buy Button with Direct Amazon Link */}
                  <a
                    href={prod.affiliateLinks?.amazon || "https://amazon.com?tag=fragreviews-20"}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleBuyClick();
                    }}
                    className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-display font-extrabold text-xs py-2.5 px-4 rounded-xl flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 transition-all hover:scale-102"
                  >
                    <ShoppingCart className="w-3.5 h-3.5" />
                    <span>Check Best Price on Amazon</span>
                    <ExternalLink className="w-3 h-3 opacity-70" />
                  </a>
                </Tilt3DCard>
              ))}
            </div>
          )}

        </div>

      </div>

      {/* FAQs Section */}
      <FAQSection 
        faqs={pageFaqs.products || pageFaqs.categories}
        title="Gaming Hardware & Accessories FAQs"
        subtitle="Frequently asked questions about warranty, latency, genuine Amazon shipping, and affiliate authenticity."
      />

    </div>
  );
};
