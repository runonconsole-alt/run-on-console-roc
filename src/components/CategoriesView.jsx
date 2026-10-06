import React, { useState, useMemo } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Gamepad2, Monitor, Laptop, ArrowRight, ShieldCheck, 
  Cpu, Layers, Sparkles, CheckCircle, Flame, Zap, Tv, Cable, 
  ArrowLeft, Star, ShoppingCart, SlidersHorizontal, CheckCircle2, ExternalLink,
  Glasses, Terminal, Search, Filter, History, X, Info, ChevronRight,
  Disc, Award, Box, Radio, Smartphone, Cloud, Eye
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { Tilt3DCard } from './Tilt3DCard';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { BouncyText } from './BouncyText';
import { playClickSound, playHoverSound } from '../utils/audioEffects';

export const CategoriesView = () => {
  const { 
    categories = [], 
    allDevices = [],
    selectedCategorySlug, 
    setSelectedCategorySlug, 
    navigateToCategory, 
    navigateToProduct, 
    products = [], 
    compareIds, 
    pageFaqs 
  } = useApp();

  // Search & Filter state for devices
  const [deviceSearchQuery, setDeviceSearchQuery] = useState('');
  const [selectedEraFilter, setSelectedEraFilter] = useState('all');
  const [selectedDeviceModal, setSelectedDeviceModal] = useState(null);

  // Era filter groups
  const eraFilters = [
    { id: 'all', label: 'All Platforms (15)' },
    { id: 'modern', label: 'Modern & 9th Gen' },
    { id: 'handhelds', label: 'Handhelds & Emulation' },
    { id: 'retro', label: 'Retro Legends (Sega, Atari, SNES)' },
    { id: 'vr_cloud', label: 'VR, AR & Cloud' },
    { id: 'arcade_misc', label: 'Arcade, Smart TV & Indie' }
  ];

  // Find the selected category if not 'all'
  const isCategorySelected = selectedCategorySlug && selectedCategorySlug !== 'all';
  const currentCategory = isCategorySelected 
    ? categories.find(c => c.slug === selectedCategorySlug || c.title.toLowerCase().includes(selectedCategorySlug.toLowerCase())) || categories[0]
    : null;

  // Filtered devices across catalog
  const filteredDevices = useMemo(() => {
    let list = allDevices;
    if (isCategorySelected && currentCategory) {
      list = list.filter(d => d.categorySlug === currentCategory.slug);
    }
    if (selectedEraFilter !== 'all') {
      if (selectedEraFilter === 'modern') {
        list = list.filter(d => ['pc-handheld-pc', 'sony-playstation', 'microsoft-xbox', 'nintendo-home'].includes(d.categorySlug) && (d.era.includes('Modern') || d.era.includes('9th') || d.era.includes('8th')));
      } else if (selectedEraFilter === 'handhelds') {
        list = list.filter(d => ['pc-handheld-pc', 'sony-handhelds', 'nintendo-handhelds', 'retro-handheld-brands'].includes(d.categorySlug));
      } else if (selectedEraFilter === 'retro') {
        list = list.filter(d => ['sega-consoles', 'retro-other', 'nintendo-home', 'sony-playstation'].includes(d.categorySlug) && (d.era.includes('Retro') || d.era.includes('16-Bit') || d.era.includes('8-Bit') || d.era.includes('32-Bit') || d.era.includes('6th') || d.era.includes('5th') || d.era.includes('4th') || d.era.includes('3rd') || d.era.includes('2nd')));
      } else if (selectedEraFilter === 'vr_cloud') {
        list = list.filter(d => ['vr-ar', 'cloud-gaming', 'mobile'].includes(d.categorySlug));
      } else if (selectedEraFilter === 'arcade_misc') {
        list = list.filter(d => ['arcade', 'smart-tv-streaming', 'misc-other'].includes(d.categorySlug));
      }
    }
    if (deviceSearchQuery.trim()) {
      const q = deviceSearchQuery.toLowerCase();
      list = list.filter(d => 
        d.name.toLowerCase().includes(q) ||
        d.categoryTitle.toLowerCase().includes(q) ||
        d.type.toLowerCase().includes(q) ||
        (d.specs && d.specs.toLowerCase().includes(q)) ||
        (d.iconicGames && d.iconicGames.some(g => g.toLowerCase().includes(q)))
      );
    }
    return list;
  }, [allDevices, isCategorySelected, currentCategory, selectedEraFilter, deviceSearchQuery]);

  // If a specific category is selected, render the DEDICATED PLATFORM HUB PAGE
  if (isCategorySelected && currentCategory) {
    // Filter products belonging to this category / platform
    const categoryProducts = products.filter(p => 
      p.categorySlug === currentCategory.slug || 
      p.category.toLowerCase() === currentCategory.title.toLowerCase() ||
      (p.category || '').toLowerCase().includes(currentCategory.slug.replace('-', ' '))
    );

    // Devices belonging to this category
    const categoryDevices = allDevices.filter(d => d.categorySlug === currentCategory.slug);

    return (
      <div className="space-y-10 py-6 animate-page-in">
        
        {/* Breadcrumb Navigation & Back to Directory Button */}
        <div className="flex items-center justify-between">
          <button 
            onClick={() => {
              playClickSound();
              setSelectedCategorySlug('all');
            }}
            className="bg-white border border-slate-300 hover:border-emerald-600 text-slate-700 font-bold text-xs px-4 py-2.5 rounded-xl flex items-center gap-2 transition-colors shadow-sm"
          >
            <ArrowLeft className="w-4 h-4" />
            <span>Back to All 15 Platforms</span>
          </button>

          <div className="text-xs text-slate-500 font-semibold hidden sm:flex items-center gap-1.5">
            <span>Platform Directory</span>
            <span>/</span>
            <span className="text-emerald-700 font-extrabold">{currentCategory.title}</span>
          </div>
        </div>

        {/* Dedicated Category Hub Hero Banner with BouncyText */}
        <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
          <CyberMatrixHoloBackground />

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
            <div className="lg:col-span-7 space-y-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
                  <Sparkles className="w-3.5 h-3.5" /> DEDICATED PLATFORM HUB
                </span>
                <span className="bg-emerald-950/80 text-emerald-300 border border-emerald-500/40 text-xs font-bold px-3 py-1 rounded-full">
                  Era: {currentCategory.era || "Modern & Retro"}
                </span>
              </div>

              <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
                <BouncyText text={currentCategory.title} enableAudio={true} />
              </h1>

              <p className="text-emerald-100 text-xs sm:text-sm sm:text-base leading-relaxed max-w-lg">
                {currentCategory.desc}
              </p>

              {/* Subcategories Filter Chips */}
              {currentCategory.subcategories && (
                <div className="flex flex-wrap gap-2 pt-2">
                  <span className="text-xs font-bold text-emerald-200 self-center mr-1">Focus Areas:</span>
                  {currentCategory.subcategories.map(sub => (
                    <span 
                      key={sub}
                      className="badge-holo-glow text-emerald-300 text-xs font-bold px-3 py-1 rounded-xl"
                    >
                      <BouncyText text={sub} />
                    </span>
                  ))}
                </div>
              )}
            </div>

            {/* Right 3D Featured Image */}
            <div className="lg:col-span-5">
              <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-3xl p-4 shadow-2xl">
                <img 
                  src={currentCategory.image} 
                  alt={currentCategory.title} 
                  className="w-full h-56 sm:h-64 object-cover rounded-2xl" 
                />
                <div className="pt-3 flex items-center justify-between text-xs">
                  <span className="text-emerald-300 font-bold">✓ {currentCategory.badge || "Verified Platform"}</span>
                  <span className="text-white font-extrabold bg-emerald-600 px-2.5 py-0.5 rounded-md">
                    {currentCategory.devices?.length || categoryDevices.length} Hardware Models Listed
                  </span>
                </div>
              </Tilt3DCard>
            </div>
          </div>
        </div>

        {/* 1. Complete Device Ecosystem & Hardware Models Grid for this Category */}
        <section className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-emerald-100 pb-4">
            <div>
              <div className="flex items-center gap-2 text-emerald-700 mb-1">
                <Layers className="w-5 h-5 animate-pulse" />
                <span className="font-display font-extrabold text-xs uppercase tracking-wider">HARDWARE ECOSYSTEM & GENERATIONS</span>
              </div>
              <h2 className="font-display font-extrabold text-2xl text-slate-900">
                <BouncyText text={`All ${currentCategory.title} Models & Devices`} />
              </h2>
            </div>
            <span className="text-xs font-bold text-emerald-800 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200">
              Click any device to inspect full technical specs
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {(categoryDevices.length > 0 ? categoryDevices : currentCategory.devices?.map((dName, idx) => ({
              id: `gen-dev-${idx}`,
              name: dName,
              categorySlug: currentCategory.slug,
              categoryTitle: currentCategory.title,
              type: "Gaming Hardware",
              specs: "Standard platform hardware specifications and video architecture.",
              status: "Supported Hardware",
              iconicGames: currentCategory.popularGames || ["Featured AAA Titles"],
              image: currentCategory.image
            }))).map((device) => (
              <Tilt3DCard
                key={device.id}
                onClick={() => {
                  playClickSound();
                  setSelectedDeviceModal(device);
                }}
                onMouseEnter={playHoverSound}
                className="bg-slate-50 hover:bg-emerald-50/70 border border-slate-200 hover:border-emerald-400 p-4 rounded-2xl cursor-pointer transition-all flex flex-col justify-between group shadow-2xs"
              >
                <div>
                  <div className="flex items-start justify-between gap-2 mb-2">
                    <h3 className="font-display font-extrabold text-sm text-slate-900 group-hover:text-emerald-700 transition-colors line-clamp-1">
                      <BouncyText text={device.name} />
                    </h3>
                    <span className="text-[9px] font-extrabold bg-emerald-600 text-white px-2 py-0.5 rounded shadow-xs shrink-0">
                      {device.year || device.era || "Active"}
                    </span>
                  </div>

                  <p className="text-xs text-slate-500 font-medium mb-3 line-clamp-2 leading-relaxed">
                    {device.specs || device.type}
                  </p>

                  {device.iconicGames && (
                    <div className="space-y-1 pt-2 border-t border-slate-200/80">
                      <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Iconic Games:</span>
                      <div className="flex flex-wrap gap-1">
                        {device.iconicGames.slice(0, 3).map((g, i) => (
                          <span key={i} className="text-[10px] bg-white border border-slate-200 text-slate-700 font-semibold px-2 py-0.5 rounded truncate max-w-[140px]">
                            {g}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}
                </div>

                <div className="pt-3 mt-3 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-emerald-600 group-hover:text-emerald-700">
                  <span>View Specs & Teardown</span>
                  <ArrowRight className="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform" />
                </div>
              </Tilt3DCard>
            ))}
          </div>
        </section>

        {/* 2. Category FAQs */}
        <FAQSection 
          faqs={pageFaqs.categories}
          title={`FAQs About ${currentCategory.title}`}
          subtitle={`Frequently asked hardware, testing, emulation, and purchasing questions for ${currentCategory.title}.`}
        />

        {/* Device Detail Modal */}
        {selectedDeviceModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fade-in">
            <div className="bg-white border-2 border-emerald-500 rounded-3xl p-6 sm:p-8 max-w-2xl w-full shadow-2xl relative space-y-6 max-h-[90vh] overflow-y-auto">
              
              <button 
                onClick={() => setSelectedDeviceModal(null)}
                className="absolute top-5 right-5 p-2 bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 rounded-full transition-colors"
              >
                <X className="w-5 h-5" />
              </button>

              <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div className="w-12 h-12 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md shrink-0">
                  <Gamepad2 className="w-6 h-6" />
                </div>
                <div>
                  <span className="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider bg-emerald-50 px-2.5 py-0.5 rounded border border-emerald-200">
                    {selectedDeviceModal.categoryTitle}
                  </span>
                  <h3 className="font-display font-extrabold text-xl sm:text-2xl text-slate-900 mt-0.5">
                    {selectedDeviceModal.name}
                  </h3>
                </div>
              </div>

              <div className="space-y-4 text-xs sm:text-sm">
                <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                  <div className="font-bold text-slate-700 uppercase text-xs tracking-wider flex items-center gap-1.5">
                    <Cpu className="w-4 h-4 text-emerald-600" />
                    <span>Technical Architecture & Specs</span>
                  </div>
                  <p className="text-slate-800 font-mono text-xs leading-relaxed">
                    {selectedDeviceModal.specs || "Custom architecture and hardware profile verified by Run On Console Laboratory."}
                  </p>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div className="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                    <span className="text-[10px] font-bold text-emerald-800 uppercase block">Generation / Release</span>
                    <span className="font-display font-extrabold text-slate-900 text-sm">
                      {selectedDeviceModal.year || selectedDeviceModal.era || "Active Generation"}
                    </span>
                  </div>
                  <div className="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                    <span className="text-[10px] font-bold text-emerald-800 uppercase block">Status</span>
                    <span className="font-display font-extrabold text-emerald-700 text-sm">
                      {selectedDeviceModal.status || "Lab Tested ✓"}
                    </span>
                  </div>
                </div>

                {selectedDeviceModal.iconicGames && (
                  <div>
                    <span className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 block flex items-center gap-1.5">
                      <Award className="w-4 h-4 text-amber-500" />
                      <span>Benchmark & Defining Game Titles</span>
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                      {selectedDeviceModal.iconicGames.map((g, idx) => (
                        <span key={idx} className="bg-slate-100 text-slate-800 text-xs font-bold px-3 py-1.5 rounded-xl border border-slate-200">
                          {g}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              <div className="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <button 
                  onClick={() => setSelectedDeviceModal(null)}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-6 rounded-xl shadow-md transition-all hover:scale-105"
                >
                  Close Spec Sheet
                </button>
              </div>

            </div>
          </div>
        )}

      </div>
    );
  }

  // --- GENERAL DIRECTORY OF ALL 15 PLATFORMS WITH ADVANCED SEARCH ENGINE ---
  return (
    <div className="space-y-12 py-6 animate-page-in">
      
      {/* Visual Rich Categories Hero Banner with Live Cyber Matrix Wave Background & BouncyText */}
      <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        
        {/* Live Cyber Wave & Laser Matrix Background Effect */}
        <CyberMatrixHoloBackground />

        {/* Ambient background glows */}
        <div className="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          
          {/* Left Content */}
          <div className="lg:col-span-7 space-y-4">
            <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
              <Sparkles className="w-3.5 h-3.5" /> 15 GAMING PLATFORM CATEGORIES • 100+ DEVICES
            </span>
            
            <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
              <BouncyText text="Gaming Platforms &" enableAudio={true} /> <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
                <BouncyText text="Hardware Ecosystems" enableAudio={true} />
              </span>
            </h1>

            <p className="text-emerald-100 text-xs sm:text-sm leading-relaxed max-w-lg">
              Explore all 15 gaming platform categories across every generation: PC, Steam Deck, PS5 Pro, Xbox Series, Switch 2, PSP, GBA, Sega Dreamcast, Atari, Anbernic, Miyoo, VR/AR, and Cloud.
            </p>

            {/* Classy Animated Holographic Spec Badges with Word Hover Bounce */}
            <div className="flex flex-wrap gap-2.5 pt-2">
              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5">
                <Gamepad2 className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
                <BouncyText text="15 Dedicated Platform Hubs" />
              </span>

              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5" style={{ animationDelay: '0.6s' }}>
                <Zap className="w-3.5 h-3.5 text-amber-400 animate-bounce" />
                <BouncyText text="100+ Hardware Devices" />
              </span>

              <span className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5" style={{ animationDelay: '1.2s' }}>
                <ShieldCheck className="w-3.5 h-3.5 text-teal-400 animate-pulse" />
                <BouncyText text="Lab Benchmark Certified" />
              </span>
            </div>
          </div>

          {/* Right Visual Graphic Showcase */}
          <div className="lg:col-span-5 grid grid-cols-2 gap-3">
            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/battlestation_pc.jpg" 
                alt="PC & Handhelds" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">PC & Handhelds</div>
              <div className="text-[10px] text-emerald-300 font-medium">Steam Deck • ROG • RTX 4090</div>
            </Tilt3DCard>

            <Tilt3DCard className="bg-slate-900/85 border-2 border-emerald-400/40 rounded-2xl p-3 backdrop-blur-md shadow-2xl group">
              <img 
                src="/images/handheld_console.jpg" 
                alt="Retro & Emulation" 
                className="w-full h-28 object-cover rounded-xl mb-2 group-hover:scale-105 transition-transform" 
              />
              <div className="text-xs font-display font-bold text-white">Retro & Emulation</div>
              <div className="text-[10px] text-emerald-300 font-medium">Miyoo • Anbernic • Dreamcast</div>
            </Tilt3DCard>
          </div>

        </div>
      </div>

      {/* 1. ADVANCED INTERACTIVE SEARCH & ERA FILTER ENGINE */}
      <section className="bg-white border-2 border-emerald-500/20 rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-emerald-100 pb-4">
          <div className="space-y-1">
            <div className="flex items-center gap-2 text-emerald-700">
              <Search className="w-5 h-5 animate-pulse" />
              <span className="font-display font-extrabold text-xs uppercase tracking-wider">INSTANT DEVICE & PLATFORM SEARCH ENGINE</span>
            </div>
            <h2 className="font-display font-extrabold text-xl sm:text-2xl text-slate-900">
              <BouncyText text="Search 100+ Hardware Models & Platforms" />
            </h2>
          </div>

          {/* Quick Search Input */}
          <div className="relative w-full md:w-80">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input 
              type="text"
              placeholder="Search e.g. PS5 Pro, Miyoo Mini, GBA SP, Quest 3..."
              value={deviceSearchQuery}
              onChange={(e) => setDeviceSearchQuery(e.target.value)}
              className="w-full bg-slate-50 border-2 border-slate-200 focus:border-emerald-500 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-slate-900 font-bold focus:bg-white focus:outline-none transition-all shadow-inner"
            />
            {deviceSearchQuery && (
              <button 
                onClick={() => setDeviceSearchQuery('')}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700"
              >
                <X className="w-4 h-4" />
              </button>
            )}
          </div>
        </div>

        {/* Era Filter Chips */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1" style={{ scrollbarWidth: 'none' }}>
          {eraFilters.map((era) => (
            <button
              key={era.id}
              onClick={() => {
                playClickSound();
                setSelectedEraFilter(era.id);
              }}
              className={`px-4 py-2 rounded-xl text-xs font-display font-extrabold whitespace-nowrap transition-all shadow-xs ${
                selectedEraFilter === era.id
                  ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/25 ring-2 ring-emerald-400'
                  : 'bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700'
              }`}
            >
              {era.label}
            </button>
          ))}
        </div>

        {/* Live Search Quick Results Preview (If user typed a query) */}
        {deviceSearchQuery.trim() && (
          <div className="bg-slate-50 border border-emerald-200 rounded-2xl p-4 space-y-3 animate-fade-in">
            <div className="flex items-center justify-between text-xs font-bold text-slate-600">
              <span>{filteredDevices.length} Hardware Devices matching "{deviceSearchQuery}":</span>
              <button 
                onClick={() => setDeviceSearchQuery('')}
                className="text-emerald-700 hover:underline text-[11px]"
              >
                Clear Search
              </button>
            </div>

            {filteredDevices.length === 0 ? (
              <p className="text-xs text-slate-500 italic py-2">No hardware devices found matching "{deviceSearchQuery}". Try searching for "PS5", "OLED", "Dreamcast", or "Anbernic".</p>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5">
                {filteredDevices.map(d => (
                  <div
                    key={d.id}
                    onClick={() => {
                      playClickSound();
                      setSelectedDeviceModal(d);
                    }}
                    className="p-3 bg-white hover:bg-emerald-100/60 border border-slate-200 hover:border-emerald-400 rounded-xl cursor-pointer transition-all flex items-center justify-between shadow-2xs group"
                  >
                    <div className="min-w-0 pr-2">
                      <h4 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                        {d.name}
                      </h4>
                      <span className="text-[10px] text-slate-400 block truncate">
                        {d.categoryTitle} • {d.year || "Active"}
                      </span>
                    </div>
                    <ArrowRight className="w-3.5 h-3.5 text-slate-300 group-hover:text-emerald-600 shrink-0 group-hover:translate-x-0.5 transition-transform" />
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </section>

      {/* 2. All 15 Gaming Platform Categories Grid with 3D Physics Cards */}
      <section className="space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="font-display font-extrabold text-2xl text-slate-900 flex items-center gap-2">
              <Layers className="w-6 h-6 text-emerald-600" />
              <BouncyText text="15 Gaming Platform Categories" />
            </h2>
            <p className="text-xs sm:text-sm text-slate-500">
              Select any platform hub to explore its full hardware lineup, FPS targets, and game library.
            </p>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {categories.map((cat) => (
            <a 
              key={cat.id}
              href={`/categories/${cat.slug}/`}
              onClick={(e) => {
                if (!e.ctrlKey && !e.metaKey && e.button !== 1) {
                  e.preventDefault();
                  playClickSound();
                  setSelectedCategorySlug(cat.slug);
                  navigateTo('categories', cat.slug);
                }
              }}
              className="block no-underline"
            >
              <Tilt3DCard 
                onMouseEnter={playHoverSound}
                className="game-card group cursor-pointer flex flex-col justify-between h-full"
              >
                {/* Category Cover Image */}
                <div className="relative h-48 bg-slate-900 overflow-hidden">
                  <img 
                    src={cat.image} 
                    alt={cat.title} 
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  <span className="absolute top-3 left-3 bg-emerald-600 text-white text-[10px] font-extrabold px-2.5 py-1 rounded shadow-sm badge-glow">
                    {cat.badge}
                  </span>
                  <span className="absolute bottom-3 right-3 bg-slate-900/90 text-white text-[10px] font-bold px-2 py-0.5 rounded backdrop-blur-sm">
                    {cat.devices?.length || 8}+ Models Listed
                  </span>
                </div>

                {/* Category Info */}
                <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <span className="text-[10px] font-bold text-emerald-700 uppercase tracking-wider bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                        {cat.era || "Platform Hub"}
                      </span>
                    </div>

                    <h3 className="font-display font-extrabold text-lg text-slate-900 group-hover:text-emerald-700 transition-colors mb-1.5">
                      <BouncyText text={cat.title} />
                    </h3>
                    <p className="text-xs text-slate-500 leading-relaxed mb-3 line-clamp-2">
                      {cat.desc}
                    </p>

                    {/* Devices Tags Preview */}
                    {cat.devices && (
                      <div className="flex flex-wrap gap-1.5 pt-2 border-t border-slate-100">
                        {cat.devices.slice(0, 4).map(dev => (
                          <span key={dev} className="bg-slate-100 text-slate-600 text-[10px] font-semibold px-2 py-0.5 rounded group-hover:bg-emerald-50 group-hover:text-emerald-700 transition-colors truncate max-w-[130px]">
                            {dev}
                          </span>
                        ))}
                        {cat.devices.length > 4 && (
                          <span className="text-[10px] text-emerald-600 font-bold px-1 py-0.5">
                            +{cat.devices.length - 4} more
                          </span>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-emerald-600 group-hover:text-emerald-700">
                    <span>Enter {cat.title} Hub</span>
                    <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                  </div>
                </div>
              </Tilt3DCard>
            </a>
          ))}
        </div>
      </section>

      {/* 3. Platform FAQs */}
      <FAQSection 
        faqs={pageFaqs.categories}
        title="Gaming Platforms & Ecosystem FAQs"
        subtitle="Answers to platform performance, emulation, and console generations."
      />

      {/* Device Detail Modal */}
      {selectedDeviceModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fade-in">
          <div className="bg-white border-2 border-emerald-500 rounded-3xl p-6 sm:p-8 max-w-2xl w-full shadow-2xl relative space-y-6 max-h-[90vh] overflow-y-auto">
            
            <button 
              onClick={() => setSelectedDeviceModal(null)}
              className="absolute top-5 right-5 p-2 bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-600 rounded-full transition-colors"
            >
              <X className="w-5 h-5" />
            </button>

            <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
              <div className="w-12 h-12 rounded-2xl bg-emerald-800 text-emerald-300 flex items-center justify-center shadow-md shrink-0">
                <Gamepad2 className="w-6 h-6" />
              </div>
              <div>
                <span className="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider bg-emerald-50 px-2.5 py-0.5 rounded border border-emerald-200">
                  {selectedDeviceModal.categoryTitle}
                </span>
                <h3 className="font-display font-extrabold text-xl sm:text-2xl text-slate-900 mt-0.5">
                  {selectedDeviceModal.name}
                </h3>
              </div>
            </div>

            <div className="space-y-4 text-xs sm:text-sm">
              <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2">
                <div className="font-bold text-slate-700 uppercase text-xs tracking-wider flex items-center gap-1.5">
                  <Cpu className="w-4 h-4 text-emerald-600" />
                  <span>Technical Architecture & Specs</span>
                </div>
                <p className="text-slate-800 font-mono text-xs leading-relaxed">
                  {selectedDeviceModal.specs || "Custom architecture and hardware profile verified by Run On Console Laboratory."}
                </p>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                  <span className="text-[10px] font-bold text-emerald-800 uppercase block">Generation / Release</span>
                  <span className="font-display font-extrabold text-slate-900 text-sm">
                    {selectedDeviceModal.year || selectedDeviceModal.era || "Active Generation"}
                  </span>
                </div>
                <div className="p-3.5 bg-emerald-50/60 border border-emerald-200 rounded-xl">
                  <span className="text-[10px] font-bold text-emerald-800 uppercase block">Status</span>
                  <span className="font-display font-extrabold text-emerald-700 text-sm">
                    {selectedDeviceModal.status || "Lab Tested ✓"}
                  </span>
                </div>
              </div>

              {selectedDeviceModal.iconicGames && (
                <div>
                  <span className="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 block flex items-center gap-1.5">
                    <Award className="w-4 h-4 text-amber-500" />
                    <span>Benchmark & Defining Game Titles</span>
                  </span>
                  <div className="flex flex-wrap gap-1.5">
                    {selectedDeviceModal.iconicGames.map((g, idx) => (
                      <span key={idx} className="bg-slate-100 text-slate-800 text-xs font-bold px-3 py-1.5 rounded-xl border border-slate-200">
                        {g}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <div className="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
              <button
                onClick={() => {
                  setSelectedCategorySlug(selectedDeviceModal.categorySlug);
                  setSelectedDeviceModal(null);
                }}
                className="text-xs font-bold text-emerald-700 hover:text-emerald-900 flex items-center gap-1"
              >
                <span>Enter {selectedDeviceModal.categoryTitle} Hub</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>

              <button 
                onClick={() => setSelectedDeviceModal(null)}
                className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs py-2.5 px-6 rounded-xl shadow-md transition-all hover:scale-105"
              >
                Close Spec Sheet
              </button>
            </div>

          </div>
        </div>
      )}

    </div>
  );
};
