import React, { useState, useRef, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Gamepad2, Search, SlidersHorizontal, Menu, X, ChevronDown, 
  Sparkles, Layers, ShoppingBag, ArrowRight, Star, ShoppingCart, 
  Cable, Laptop, Monitor, Headphones, Mouse, Cpu, ShieldCheck, User, LogOut,
  Tv, Glasses, Wrench, BookOpen, Tag, Flame, Activity, HardDrive, Zap, Compass,
  Scale, FileText, CheckCircle2, Play, Building2, Calendar
} from 'lucide-react';
import { playClickSound } from '../utils/audioEffects';
import { BrandLogo } from './BrandLogo';
import { GameDetailModal } from './GameDetailModal';
import { GamingAvatar } from './GamingProfileEditor';

export const Header = () => {
  const { 
    currentPage, 
    navigateTo, 
    products = [], 
    categories = [], 
    gameCompatibility = [],
    searchQuery, 
    setSearchQuery, 
    compareIds = [], 
    setIsCompareOpen,
    currentUser,
    logoutUser 
  } = useApp();

  const [activeDropdown, setActiveDropdown] = useState(null);
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [searchFocused, setSearchFocused] = useState(false);
  const [searchTab, setSearchTab] = useState('all');
  const [selectedGameForModal, setSelectedGameForModal] = useState(null);

  const dropdownTimeoutRef = useRef(null);
  const searchRef = useRef(null);

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (searchRef.current && !searchRef.current.contains(e.target)) {
        setSearchFocused(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setMobileMenuOpen(false);
        closeDropdown();
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  useEffect(() => {
    if (mobileMenuOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [mobileMenuOpen]);

  const handleMouseEnter = (menuKey) => {
    if (dropdownTimeoutRef.current) clearTimeout(dropdownTimeoutRef.current);
    setActiveDropdown(menuKey);
  };

  const handleMouseLeave = () => {
    dropdownTimeoutRef.current = setTimeout(() => {
      setActiveDropdown(null);
    }, 180);
  };

  const closeDropdown = () => {
    if (dropdownTimeoutRef.current) clearTimeout(dropdownTimeoutRef.current);
    setActiveDropdown(null);
  };

  const handleNavClick = (e, page, param = null) => {
    if (e && (e.ctrlKey || e.metaKey || e.button === 1)) {
      return; // Allow native browser tab open
    }
    if (e) e.preventDefault();
    playClickSound();
    navigateTo(page, param);
    closeDropdown();
  };

  const componentsMenu = [
    { title: "Graphics Cards (GPUs)", desc: "NVIDIA RTX, AMD Radeon, DLSS & Ray Tracing", icon: Cpu, slug: "gpu" },
    { title: "CPUs & Processors", desc: "AMD Ryzen 7000/9000, Intel Core 14th Gen", icon: Zap, slug: "cpu" },
    { title: "Memory (RAM) & Storage", desc: "DDR5 CL30, PCIe Gen 4/5 NVMe SSDs", icon: HardDrive, slug: "storage-memory" },
    { title: "Power Supplies & Cooling", desc: "ATX 3.1 12V-2x6, 360mm Liquid AIOs", icon: Flame, slug: "cables-accessories" },
    { title: "Cases & PC Aesthetics", desc: "Dual-Chamber Fishtank Cases, ARGB Sync", icon: Layers, slug: "pc-gaming" },
  ];

  const peripheralsMenu = [
    { title: "Gaming Monitors & Displays", desc: "360Hz QD-OLED, 4K 144Hz, 0.03ms Fast IPS", icon: Monitor, slug: "monitors" },
    { title: "Gaming Mice & Mousepads", desc: "63g Ultra-lightweight, 8000Hz Polling, PTFE", icon: Mouse, slug: "mice" },
    { title: "Mechanical Keyboards", desc: "Magnetic Rapid-Trigger Hall Effect", icon: Cable, slug: "keyboards" },
    { title: "Headsets & Audio Gear", desc: "Wireless DTS Headsets, USB DAC/Amps", icon: Headphones, slug: "audio" },
    { title: "Speakers & Soundbars", desc: "THX AI Head-Tracking Soundbars", icon: Tv, slug: "speakers" },
  ];

  const systemsMenu = [
    { title: "Gaming Laptops", desc: "RTX 4080/4090 175W TGP Laptops", icon: Laptop, slug: "pc-handheld-pc" },
    { title: "Steam Deck & Handheld PCs", desc: "Steam Deck OLED, ROG Ally X, Legion Go", icon: Gamepad2, slug: "retro-handheld-brands" },
    { title: "Pre-Built Desktop PCs", desc: "Liquid Battlestations, i9-14900KS Rigs", icon: Cpu, slug: "pc-handheld-pc" },
    { title: "15 Gaming Platforms Directory", desc: "PlayStation 5 Pro, Xbox Series X, Switch 2 Hubs", icon: Layers, slug: "all" },
  ];

  const guidesMenu = [
    { title: "Step-by-Step PC Building", desc: "$800, $1,500, $3,000+ Budget Bracket Builds", icon: Wrench, route: "blogs" },
    { title: "Troubleshooting & Fixes", desc: "Thermal Throttling, Driver Fixes, BIOS Updates", icon: Activity, route: "blogs" },
    { title: "Windows & Game Optimization", desc: "Windows 11 Gaming Tweaks, Undervolting", icon: Zap, route: "blogs" },
    { title: "Can I Run It? Hardware Matrix", desc: "Test Custom PC Specs for GTA 5, BO6 & Cyberpunk", icon: Cpu, route: "compatibility" },
  ];

  const query = (searchQuery || '').toLowerCase().trim();

  const filteredGames = gameCompatibility.filter(g => 
    !query || 
    g.gameTitle.toLowerCase().includes(query) || 
    g.genre.toLowerCase().includes(query)
  );

  const filteredProducts = products.filter(p => 
    !query || 
    p.name.toLowerCase().includes(query) || 
    p.category.toLowerCase().includes(query) ||
    p.brand.toLowerCase().includes(query)
  );

  return (
    <>
      <header className="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-[1000] shadow-xs transition-all w-full">
        <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16 sm:h-18 gap-1.5 sm:gap-3">
            
            {/* Brand Logo Anchor Link */}
            <div className="pr-3 sm:pr-4 mr-2 sm:mr-4 border-r border-slate-200 shrink-0">
              <a href="/" onClick={(e) => handleNavClick(e, 'home')} className="no-underline block">
                <BrandLogo theme="light" />
              </a>
            </div>

            {/* 5 Pillar Crawlable Navigation Anchors */}
            <nav className="hidden lg:flex items-center gap-0.5 xl:gap-1.5 flex-1 justify-center min-w-0 px-1">
              
              {/* 0. HOME */}
              <a
                href="/"
                onClick={(e) => handleNavClick(e, 'home')}
                className={`px-2 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-wide rounded-xl transition-all whitespace-nowrap ${
                  currentPage === 'home' 
                    ? 'bg-emerald-600 text-white shadow-sm' 
                    : 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50'
                }`}
              >
                HOME
              </a>

              {/* 1. COMPONENTS */}
              <div 
                className="relative"
                onMouseEnter={() => handleMouseEnter('components')}
                onMouseLeave={handleMouseLeave}
              >
                <a
                  href="/products/"
                  onClick={(e) => handleNavClick(e, 'products')}
                  className={`px-1.5 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-tight rounded-xl flex items-center gap-0.5 transition-all whitespace-nowrap ${
                    activeDropdown === 'components'
                      ? 'bg-emerald-600 text-white shadow-sm' 
                      : 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50'
                  }`}
                >
                  <span>COMPONENTS</span>
                  <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${activeDropdown === 'components' ? 'rotate-180' : ''}`} />
                </a>

                {activeDropdown === 'components' && (
                  <div className="mega-dropdown-mirror absolute top-full left-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 w-[360px] rounded-3xl p-4 shadow-2xl space-y-2 animate-page-in z-[99999]">
                    <div className="flex items-center justify-between pb-2 border-b border-emerald-100">
                      <span className="font-display font-extrabold text-xs text-slate-900 flex items-center gap-1.5">
                        <Cpu className="w-4 h-4 text-emerald-600" />
                        <span>CORE GAMING HARDWARE</span>
                      </span>
                      <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">The Build</span>
                    </div>

                    <div className="space-y-1">
                      {componentsMenu.map((item, idx) => {
                        const Icon = item.icon;
                        return (
                          <a
                            key={idx}
                            href={`/products/`}
                            onClick={(e) => handleNavClick(e, 'products', item.slug)}
                            className="dropdown-tile p-2.5 rounded-xl cursor-pointer flex items-center justify-between gap-2.5 group no-underline"
                          >
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                <Icon className="w-4 h-4" />
                              </div>
                              <div className="min-w-0">
                                <h5 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                                  {item.title}
                                </h5>
                                <span className="text-[10px] text-slate-500 block truncate">
                                  {item.desc}
                                </span>
                              </div>
                            </div>
                            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                          </a>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* 2. PERIPHERALS */}
              <div 
                className="relative"
                onMouseEnter={() => handleMouseEnter('peripherals')}
                onMouseLeave={handleMouseLeave}
              >
                <a
                  href="/products/"
                  onClick={(e) => handleNavClick(e, 'products')}
                  className={`px-1.5 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-tight rounded-xl flex items-center gap-0.5 transition-all whitespace-nowrap ${
                    activeDropdown === 'peripherals'
                      ? 'bg-emerald-600 text-white shadow-sm' 
                      : 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50'
                  }`}
                >
                  <span>PERIPHERALS</span>
                  <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${activeDropdown === 'peripherals' ? 'rotate-180' : ''}`} />
                </a>

                {activeDropdown === 'peripherals' && (
                  <div className="mega-dropdown-mirror absolute top-full left-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 w-[360px] rounded-3xl p-4 shadow-2xl space-y-2 animate-page-in z-[99999]">
                    <div className="flex items-center justify-between pb-2 border-b border-emerald-100">
                      <span className="font-display font-extrabold text-xs text-slate-900 flex items-center gap-1.5">
                        <Gamepad2 className="w-4 h-4 text-emerald-600" />
                        <span>GEAR & CONTROLLERS</span>
                      </span>
                      <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">Accessories</span>
                    </div>

                    <div className="space-y-1">
                      {peripheralsMenu.map((item, idx) => {
                        const Icon = item.icon;
                        return (
                          <a
                            key={idx}
                            href={`/products/`}
                            onClick={(e) => handleNavClick(e, 'products', item.slug)}
                            className="dropdown-tile p-2.5 rounded-xl cursor-pointer flex items-center justify-between gap-2.5 group no-underline"
                          >
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                <Icon className="w-4 h-4" />
                              </div>
                              <div className="min-w-0">
                                <h5 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                                  {item.title}
                                </h5>
                                <span className="text-[10px] text-slate-500 block truncate">
                                  {item.desc}
                                </span>
                              </div>
                            </div>
                            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                          </a>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* 3. SYSTEMS */}
              <div 
                className="relative"
                onMouseEnter={() => handleMouseEnter('systems')}
                onMouseLeave={handleMouseLeave}
              >
                <a
                  href="/categories/"
                  onClick={(e) => handleNavClick(e, 'categories', 'all')}
                  className={`px-1.5 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-tight rounded-xl flex items-center gap-0.5 transition-all whitespace-nowrap ${
                    currentPage === 'categories' || activeDropdown === 'systems'
                      ? 'bg-emerald-600 text-white shadow-sm' 
                      : 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50'
                  }`}
                >
                  <span>SYSTEMS</span>
                  <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${activeDropdown === 'systems' ? 'rotate-180' : ''}`} />
                </a>

                {activeDropdown === 'systems' && (
                  <div className="mega-dropdown-mirror absolute top-full left-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 w-[360px] rounded-3xl p-4 shadow-2xl space-y-2 animate-page-in z-[99999]">
                    <div className="flex items-center justify-between pb-2 border-b border-emerald-100">
                      <span className="font-display font-extrabold text-xs text-slate-900 flex items-center gap-1.5">
                        <Monitor className="w-4 h-4 text-emerald-600" />
                        <span>GAMING ECOSYSTEMS</span>
                      </span>
                      <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">15 Platforms</span>
                    </div>

                    <div className="space-y-1">
                      {systemsMenu.map((item, idx) => {
                        const Icon = item.icon;
                        const catUrl = item.slug && item.slug !== 'all' ? `/categories/${item.slug}/` : '/categories/';
                        return (
                          <a
                            key={idx}
                            href={catUrl}
                            onClick={(e) => handleNavClick(e, 'categories', item.slug || 'all')}
                            className="dropdown-tile p-2.5 rounded-xl cursor-pointer flex items-center justify-between gap-2.5 group no-underline"
                          >
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                <Icon className="w-4 h-4" />
                              </div>
                              <div className="min-w-0">
                                <h5 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                                  {item.title}
                                </h5>
                                <span className="text-[10px] text-slate-500 block truncate">
                                  {item.desc}
                                </span>
                              </div>
                            </div>
                            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                          </a>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* 4. PC GUIDES */}
              <div 
                className="relative"
                onMouseEnter={() => handleMouseEnter('guides')}
                onMouseLeave={handleMouseLeave}
              >
                <a
                  href="/blogs/"
                  onClick={(e) => handleNavClick(e, 'blogs')}
                  className={`px-1.5 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-tight rounded-xl flex items-center gap-0.5 transition-all whitespace-nowrap ${
                    activeDropdown === 'guides'
                      ? 'bg-emerald-600 text-white shadow-sm' 
                      : 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50'
                  }`}
                >
                  <span>PC GUIDES</span>
                  <ChevronDown className={`w-3 h-3 transition-transform duration-300 ${activeDropdown === 'guides' ? 'rotate-180' : ''}`} />
                </a>

                {activeDropdown === 'guides' && (
                  <div className="mega-dropdown-mirror absolute top-full left-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 w-[360px] rounded-3xl p-4 shadow-2xl space-y-2 animate-page-in z-[99999]">
                    <div className="flex items-center justify-between pb-2 border-b border-emerald-100">
                      <span className="font-display font-extrabold text-xs text-slate-900 flex items-center gap-1.5">
                        <Wrench className="w-4 h-4 text-emerald-600" />
                        <span>PC BUILDING & HOW-TOS</span>
                      </span>
                      <span className="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">Tutorials</span>
                    </div>

                    <div className="space-y-1">
                      {guidesMenu.map((item, idx) => {
                        const Icon = item.icon;
                        const guideUrl = item.route === 'compatibility' ? '/compatibility/' : '/blogs/';
                        return (
                          <a
                            key={idx}
                            href={guideUrl}
                            onClick={(e) => handleNavClick(e, item.route)}
                            className="dropdown-tile p-2.5 rounded-xl cursor-pointer flex items-center justify-between gap-2.5 group no-underline"
                          >
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                <Icon className="w-4 h-4" />
                              </div>
                              <div className="min-w-0">
                                <h5 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                                  {item.title}
                                </h5>
                                <span className="text-[10px] text-slate-500 block truncate">
                                  {item.desc}
                                </span>
                              </div>
                            </div>
                            <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                          </a>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>

              {/* 5. GAME COMPATIBILITY */}
              <a
                href="/compatibility/"
                onClick={(e) => handleNavClick(e, 'compatibility')}
                className={`px-2 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-wide rounded-xl transition-all whitespace-nowrap mr-1 lg:mr-2 ${
                  currentPage === 'compatibility' 
                    ? 'bg-emerald-600 text-white shadow-sm' 
                    : 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50'
                }`}
              >
                COMPATIBILITY
              </a>

              {/* UNLOCKED MASTER DASHBOARD LINK */}
              <a
                href="/admin/"
                onClick={(e) => handleNavClick(e, 'admin')}
                className={`px-2 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-black uppercase tracking-wide rounded-xl transition-all whitespace-nowrap flex items-center gap-1 border ${
                  currentPage === 'admin' 
                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' 
                    : 'text-emerald-800 bg-emerald-50 hover:bg-emerald-100 hover:text-emerald-950 border-emerald-300'
                }`}
              >
                <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                <span>DASHBOARD</span>
              </a>

            </nav>

            {/* Right Actions */}
            <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
              
              {/* Search Bar */}
              <div ref={searchRef} className="relative hidden md:block w-32 lg:w-36 xl:w-48 shrink-0">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
                <input 
                  type="text"
                  placeholder="Search gear & games..."
                  aria-label="Search gaming hardware and games"
                  value={searchQuery}
                  onFocus={() => setSearchFocused(true)}
                  onChange={(e) => {
                    setSearchQuery(e.target.value);
                    setSearchFocused(true);
                  }}
                  className="w-full bg-slate-100/90 hover:bg-slate-100 border border-slate-200 rounded-xl pl-8 pr-2.5 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:bg-white transition-all shadow-inner font-medium"
                />

                {searchFocused && (
                  <div className="absolute right-0 top-full mt-2 w-80 sm:w-96 bg-white border-2 border-emerald-400 rounded-3xl p-3 shadow-2xl z-[99999] space-y-2.5 animate-page-in">
                    <div className="grid grid-cols-3 gap-1 p-1 bg-slate-100 rounded-xl border border-slate-200 text-[10px] font-extrabold">
                      <button
                        type="button"
                        onClick={() => setSearchTab('all')}
                        className={`py-1 rounded-lg transition-colors ${searchTab === 'all' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:text-slate-900'}`}
                      >
                        ALL
                      </button>
                      <button
                        type="button"
                        onClick={() => setSearchTab('games')}
                        className={`py-1 rounded-lg transition-colors ${searchTab === 'games' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:text-slate-900'}`}
                      >
                        🎮 GAMES ({filteredGames.length})
                      </button>
                      <button
                        type="button"
                        onClick={() => setSearchTab('devices')}
                        className={`py-1 rounded-lg transition-colors ${searchTab === 'devices' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:text-slate-900'}`}
                      >
                        💻 DEVICES ({filteredProducts.length})
                      </button>
                    </div>

                    <div className="max-h-72 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                      {(searchTab === 'all' || searchTab === 'games') && filteredGames.length > 0 && (
                        <div className="space-y-1">
                          <div className="text-[10px] font-extrabold text-emerald-800 uppercase px-2 flex items-center gap-1">
                            <Gamepad2 className="w-3 h-3 text-emerald-600" />
                            <span>GAMES & PERFORMANCE TARGETS</span>
                          </div>
                          {filteredGames.slice(0, 4).map((game) => (
                            <div
                              key={game.id}
                              onClick={() => {
                                playClickSound();
                                setSelectedGameForModal(game);
                                setSearchFocused(false);
                              }}
                              className="p-2.5 rounded-xl hover:bg-emerald-50 cursor-pointer flex items-center justify-between gap-2 border border-transparent hover:border-emerald-200 transition-all group"
                            >
                              <div className="min-w-0">
                                <div className="font-display font-extrabold text-xs text-slate-900 group-hover:text-emerald-700 truncate">
                                  {game.gameTitle}
                                </div>
                                <div className="text-[10px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                  <span>{game.genre}</span>
                                  <span>•</span>
                                  <span className="text-emerald-600 font-bold">{game.fpsTarget.split('|')[0]}</span>
                                </div>
                              </div>
                              <span className="text-[10px] font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-md shrink-0">
                                View Hub →
                              </span>
                            </div>
                          ))}
                        </div>
                      )}

                      {(searchTab === 'all' || searchTab === 'devices') && filteredProducts.length > 0 && (
                        <div className="space-y-1 pt-1 border-t border-slate-100">
                          <div className="text-[10px] font-extrabold text-cyan-800 uppercase px-2 flex items-center gap-1">
                            <Cpu className="w-3 h-3 text-cyan-600" />
                            <span>DEVICES & ACCESSORIES</span>
                          </div>
                          {filteredProducts.slice(0, 4).map((prod) => (
                            <a
                              key={prod.id}
                              href={`/products/${prod.slug || prod.id}/`}
                              onClick={(e) => {
                                setSearchFocused(false);
                                handleNavClick(e, 'products', prod.id);
                              }}
                              className="p-2 rounded-xl hover:bg-cyan-50 cursor-pointer flex items-center justify-between gap-2 border border-transparent hover:border-cyan-200 transition-all group no-underline"
                            >
                              <div className="min-w-0">
                                <div className="font-display font-bold text-xs text-slate-900 group-hover:text-cyan-700 truncate">
                                  {prod.name}
                                </div>
                                <div className="text-[10px] text-slate-500">
                                  {prod.brand} • <span className="text-emerald-700 font-bold">{prod.price}</span>
                                </div>
                              </div>
                              <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-cyan-600 shrink-0" />
                            </a>
                          ))}
                        </div>
                      )}

                      {filteredGames.length === 0 && filteredProducts.length === 0 && (
                        <div className="p-4 text-center text-xs text-slate-500">
                          No matching games or devices found for "{searchQuery}".
                        </div>
                      )}
                    </div>

                    <a
                      href="/compatibility/"
                      onClick={(e) => {
                        setSearchFocused(false);
                        handleNavClick(e, 'compatibility');
                      }}
                      className="w-full bg-slate-900 hover:bg-slate-800 text-emerald-300 font-display font-extrabold text-[11px] py-2 rounded-xl flex items-center justify-center gap-1.5 transition-colors no-underline"
                    >
                      <Zap className="w-3.5 h-3.5 text-emerald-400" />
                      <span>Test Custom PC Specs on Compatibility Matrix</span>
                    </a>
                  </div>
                )}
              </div>

              {/* Compare Trigger Badge */}
              {compareIds.length > 0 && (
                <button
                  type="button"
                  aria-label="Open hardware comparison drawer"
                  onClick={() => {
                    playClickSound();
                    setIsCompareOpen(true);
                  }}
                  className="flex items-center gap-1 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all shadow-xs shrink-0 whitespace-nowrap"
                >
                  <SlidersHorizontal className="w-3.5 h-3.5 text-emerald-600" />
                  <span className="hidden sm:inline">Compare</span>
                  <span className="w-4 h-4 bg-emerald-600 text-white rounded-full flex items-center justify-center text-[10px] font-extrabold">
                    {compareIds.length}
                  </span>
                </button>
              )}

              {/* User Dropdown Trigger */}
              {currentUser ? (
                <div className="relative shrink-0">
                  <button
                    type="button"
                    aria-label="Open user account menu"
                    onClick={() => {
                      playClickSound();
                      setUserDropdownOpen(!userDropdownOpen);
                    }}
                    className="flex items-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-950 px-2.5 py-1.5 rounded-xl text-xs font-extrabold shadow-xs transition-all shrink-0"
                  >
                    <GamingAvatar profile={currentUser} className="w-5 h-5 rounded-lg border-0 p-0" size={20} />
                    <span className="hidden sm:inline truncate max-w-[80px]">
                      {currentUser.name.split(' ')[0]}
                    </span>
                    <ChevronDown className="w-3 h-3 text-emerald-700" />
                  </button>

                  {userDropdownOpen && (
                    <div className="absolute right-0 top-full mt-2 w-56 bg-white border-2 border-emerald-400 rounded-2xl p-2.5 shadow-2xl z-[99999] space-y-1 animate-page-in">
                      <div className="px-3 py-2 border-b border-emerald-100 bg-emerald-50/80 rounded-xl mb-1">
                        <div className="font-extrabold text-xs text-slate-900 truncate">{currentUser.name}</div>
                        <div className="text-[10px] text-emerald-700 font-mono font-bold">@{currentUser.username?.toLowerCase() || 'gamer'}</div>
                      </div>
                      
                      <a
                        href="/admin/"
                        onClick={(e) => {
                          setUserDropdownOpen(false);
                          handleNavClick(e, 'admin');
                        }}
                        className="w-full text-left px-3 py-2 text-xs font-extrabold bg-emerald-50 text-emerald-950 hover:bg-emerald-100 rounded-xl flex items-center gap-2 transition-colors border border-emerald-300 no-underline"
                      >
                        <ShieldCheck className="w-4 h-4 text-emerald-700" />
                        <span>ROC Admin Dashboard</span>
                      </a>

                      <a
                        href="/profile/"
                        onClick={(e) => {
                          setUserDropdownOpen(false);
                          handleNavClick(e, 'profile');
                        }}
                        className="w-full text-left px-3 py-2 text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800 rounded-xl flex items-center gap-2 transition-colors no-underline"
                      >
                        <User className="w-3.5 h-3.5 text-emerald-600" />
                        <span>My Gamer Profile</span>
                      </a>

                      <button
                        type="button"
                        aria-label="Log out of account"
                        onClick={() => {
                          logoutUser();
                          setUserDropdownOpen(false);
                        }}
                        className="w-full text-left px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl flex items-center gap-2 transition-colors"
                      >
                        <LogOut className="w-3.5 h-3.5 text-rose-500" />
                        <span>Log Out</span>
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                <div className="flex items-center gap-1.5 shrink-0">
                  <a
                    href="/admin/"
                    onClick={(e) => handleNavClick(e, 'admin')}
                    className="flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-display font-black bg-emerald-50 text-emerald-900 hover:bg-emerald-100 border border-emerald-300 transition-all no-underline shrink-0"
                    title="Open Master Dashboard"
                  >
                    <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                    <span>Dashboard</span>
                  </a>
                  <a
                    href="/auth/login/"
                    onClick={(e) => handleNavClick(e, 'auth', 'login')}
                    className="px-2.5 py-1.5 rounded-xl text-xs font-display font-extrabold text-slate-800 hover:text-emerald-700 hover:bg-emerald-50 border border-slate-200 transition-all no-underline shrink-0"
                  >
                    Sign In
                  </a>
                  <a
                    href="/auth/signup/"
                    onClick={(e) => handleNavClick(e, 'auth', 'signup')}
                    className="px-2.5 py-1.5 rounded-xl text-xs font-display font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-sm transition-all no-underline shrink-0 flex items-center gap-1"
                  >
                    <User className="w-3.5 h-3.5" />
                    <span className="hidden sm:inline">Join Free</span>
                  </a>
                </div>
              )}

              {/* Mobile Menu Toggle Button */}
              <button
                type="button"
                aria-label="Toggle mobile navigation menu"
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="lg:hidden p-2 rounded-xl text-slate-700 hover:bg-slate-100 transition-colors shrink-0"
              >
                {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
              </button>

            </div>

          </div>
        </div>

        {/* Mobile Navigation Drawer */}
        {mobileMenuOpen && (
          <div className="lg:hidden bg-white border-b border-slate-200 p-4 space-y-3 shadow-xl animate-page-in">
            <div className="grid grid-cols-2 gap-2 text-xs font-display font-extrabold">
              <a
                href="/admin/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'admin'); }}
                className="col-span-2 p-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white text-left font-display font-extrabold text-xs flex items-center justify-between no-underline shadow-sm"
              >
                <div className="flex items-center gap-2">
                  <ShieldCheck className="w-4 h-4 text-emerald-200" />
                  <span>MASTER DASHBOARD (ALL ACCESS)</span>
                </div>
                <ArrowRight className="w-3.5 h-3.5 text-emerald-200" />
              </a>
              <a
                href="/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'home'); }}
                className="p-2.5 rounded-xl bg-slate-50 text-slate-900 text-left no-underline block"
              >
                HOME
              </a>
              <a
                href="/compatibility/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'compatibility'); }}
                className="p-2.5 rounded-xl bg-emerald-50 text-emerald-800 text-left no-underline block"
              >
                COMPATIBILITY
              </a>
              <a
                href="/products/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'products'); }}
                className="p-2.5 rounded-xl bg-slate-50 text-slate-900 text-left no-underline block"
              >
                COMPONENTS
              </a>
              <a
                href="/products/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'products'); }}
                className="p-2.5 rounded-xl bg-slate-50 text-slate-900 text-left no-underline block"
              >
                PERIPHERALS
              </a>
              <a
                href="/categories/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'categories', 'all'); }}
                className="p-2.5 rounded-xl bg-slate-50 text-slate-900 text-left no-underline block"
              >
                SYSTEMS
              </a>
              <a
                href="/blogs/"
                onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'blogs'); }}
                className="p-2.5 rounded-xl bg-slate-50 text-slate-900 text-left no-underline block"
              >
                PC GUIDES
              </a>
            </div>
          </div>
        )}
      </header>

      {selectedGameForModal && (
        <GameDetailModal 
          game={selectedGameForModal}
          onClose={() => setSelectedGameForModal(null)}
          onRunCompatibilityTest={() => {
            setSelectedGameForModal(null);
            navigateTo('compatibility');
          }}
        />
      )}
    </>
  );
};
