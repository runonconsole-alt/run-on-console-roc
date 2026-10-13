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
import { useSiteNav, navIsActive, iconSvg } from '../data/siteNav';

const NAV_ON = 'bg-emerald-600 text-white shadow-sm';
const NAV_OFF = 'text-slate-700 hover:text-emerald-700 hover:bg-emerald-50';

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
    logoutUser,
    currentPath
  } = useApp();
  const siteNav = useSiteNav();

  const [activeDropdown, setActiveDropdown] = useState(null);
  // Menus open on hover with CSS too (server-rendered pages have no React). After a click
  // the menu stays hidden until the pointer leaves it, so it does not linger over the new page.
  const [closedDropdown, setClosedDropdown] = useState(null);
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
    setClosedDropdown(null);
    dropdownTimeoutRef.current = setTimeout(() => {
      setActiveDropdown(null);
    }, 180);
  };

  const closeDropdown = () => {
    if (dropdownTimeoutRef.current) clearTimeout(dropdownTimeoutRef.current);
    setClosedDropdown(activeDropdown);
    setActiveDropdown(null);
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
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


  const query = (searchQuery || '').toLowerCase().trim();

  const filteredGames = gameCompatibility.filter(g => 
    !query || 
    g.gameTitle.toLowerCase().includes(query) || 
    g.genre.toLowerCase().includes(query)
  );

  const filteredProducts = products.filter(p => 
    !query || 
    (p.title || p.name || '').toLowerCase().includes(query) || 
    (p.category || '').toLowerCase().includes(query) ||
    (p.brand || '').toLowerCase().includes(query)
  );

  return (
    <>
      <header className="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-[1000] shadow-xs transition-all w-full">
        <div className="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-16 sm:h-18 gap-1.5 sm:gap-3">
            
            {/* Brand Logo Anchor Link */}
            <div className="shrink-0 sm:pr-4 sm:mr-4 sm:border-r sm:border-slate-200">
              <a href="/" onClick={(e) => handleNavClick(e, 'home')} className="no-underline block">
                <BrandLogo theme="light" />
              </a>
            </div>

            {/* 5 Pillar Crawlable Navigation Anchors */}
            {/* Main menu (CMS > Menus & footer). The server draws the same markup from the
                CMS menu (rocNavHeader in api/v1/cms/site-layer-lib.php): keep them in step. */}
            <nav data-roc-region="header-nav" className="hidden lg:flex items-center gap-0.5 xl:gap-1.5 flex-1 justify-center min-w-0 px-1">
              {siteNav.header.map((item, i) => {
                const active = navIsActive(item.url, currentPath);
                const last = i === siteNav.header.length - 1;
                if (!item.children || !item.children.length) {
                  return (
                    <a key={'n' + i} href={item.url} onClick={closeDropdown}
                      className={'px-2 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-wide rounded-xl transition-all whitespace-nowrap' + (last ? ' mr-1 lg:mr-2' : '') + ' ' + (active ? NAV_ON : NAV_OFF)}>
                      {item.label}
                    </a>
                  );
                }
                const key = 'dd' + i;
                const open = activeDropdown === key;
                return (
                  <div key={key} className="relative roc-dd" onMouseEnter={() => handleMouseEnter(key)} onMouseLeave={handleMouseLeave}>
                    <a href={item.url} onClick={closeDropdown}
                      className={'px-1.5 xl:px-2.5 py-1.5 text-[10px] xl:text-xs font-display font-extrabold uppercase tracking-tight rounded-xl flex items-center gap-0.5 transition-all whitespace-nowrap ' + (open || active ? NAV_ON : NAV_OFF)}>
                      <span>{item.label}</span>
                      <span className="inline-flex" aria-hidden="true" dangerouslySetInnerHTML={{ __html: iconSvg('chevron-down', 'w-3 h-3 transition-transform duration-300' + (open ? ' rotate-180' : '')) }} />
                    </a>
                    <div className={'roc-dd-panel' + (open ? ' is-open' : '') + (closedDropdown === key ? ' is-closed' : '') + ' mega-dropdown-mirror absolute top-full left-0 sm:left-1/2 sm:-translate-x-1/2 mt-2 w-[320px] rounded-3xl p-3 shadow-2xl space-y-1.5 animate-page-in z-[99999]'}>
                      {item.children.map((c, j) => (
                        <a key={'c' + j} href={c.url} onClick={closeDropdown} className="dropdown-tile p-2.5 rounded-xl cursor-pointer flex items-center justify-between gap-2.5 group no-underline">
                          <div className="flex items-center gap-2.5 min-w-0">
                            <div className="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                              <span className="inline-flex" aria-hidden="true" dangerouslySetInnerHTML={{ __html: iconSvg(c.icon || 'link', 'w-4 h-4') }} />
                            </div>
                            <div className="min-w-0">
                              <span className="block font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 truncate">{c.title}</span>
                              <span className="text-[10px] text-slate-500 block truncate">{c.desc}</span>
                            </div>
                          </div>
                          <span className="inline-flex" aria-hidden="true" dangerouslySetInnerHTML={{ __html: iconSvg('arrow-right', 'w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0') }} />
                        </a>
                      ))}
                      {item.button && item.button.label ? (
                        <a href={item.button.url} onClick={closeDropdown} className="block text-center text-xs font-display font-extrabold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl py-2 no-underline">
                          {item.button.label}
                        </a>
                      ) : null}
                    </div>
                  </div>
                );
              })}
            </nav>

            {/* Right Actions */}
            <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
              
              {/* Search: products, blogs or games. roc-nav.js shows live results and opens the right page. */}
              <form
                data-roc-search=""
                role="search"
                action="/products/"
                className="relative hidden md:flex items-center w-48 lg:w-56 xl:w-72 shrink-0 bg-slate-100/90 border border-slate-200 rounded-xl focus-within:border-emerald-500 focus-within:bg-white transition-all shadow-inner"
              >
                <select
                  name="in"
                  aria-label="Search in"
                  defaultValue="all"
                  className="bg-transparent border-0 border-r border-slate-200 text-[11px] font-bold text-slate-700 pl-2 pr-1 py-1.5 rounded-l-xl focus:outline-none cursor-pointer"
                >
                  <option value="all">All</option>
                  <option value="products">Products</option>
                  <option value="blogs">Blogs</option>
                  <option value="games">Games</option>
                </select>
                <input
                  type="search"
                  name="q"
                  autoComplete="off"
                  placeholder="Search..."
                  aria-label="Search products, blogs and games"
                  className="flex-1 min-w-0 bg-transparent border-0 pl-2 pr-7 py-1.5 text-xs text-slate-900 placeholder:text-slate-400 focus:outline-none font-medium"
                />
                <button type="submit" aria-label="Search" className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600">
                  <Search className="w-3.5 h-3.5" />
                </button>
              </form>

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
                    href="/auth/login/"
                    onClick={(e) => handleNavClick(e, 'auth', 'login')}
                    className="hidden sm:block px-3 py-1.5 rounded-xl text-xs font-display font-extrabold text-slate-800 hover:text-emerald-700 hover:bg-emerald-50 border border-slate-200 transition-all no-underline shrink-0"
                  >
                    Sign In
                  </a>
                  <a
                    href="/auth/signup/"
                    onClick={(e) => handleNavClick(e, 'auth', 'signup')}
                    aria-label="Join Free"
                    className="px-3 py-1.5 rounded-xl text-xs font-display font-extrabold bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-sm transition-all no-underline shrink-0 flex items-center gap-1"
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
                aria-expanded={mobileMenuOpen}
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
          <div className="lg:hidden bg-white border-b border-slate-200 p-4 space-y-3 shadow-xl animate-page-in max-h-[calc(100vh-4rem)] overflow-y-auto">
            {/* Search (the header search bar is hidden below md) */}
            <form data-roc-search="" role="search" action="/products/" className="relative md:hidden flex gap-1.5">
              <select name="in" aria-label="Search in" defaultValue="all" className="bg-slate-100 border border-slate-200 rounded-xl px-2 text-xs font-bold text-slate-700">
                <option value="all">All</option>
                <option value="products">Products</option>
                <option value="blogs">Blogs</option>
                <option value="games">Games</option>
              </select>
              <input
                type="search"
                name="q"
                autoComplete="off"
                placeholder="Search products, blogs, games..."
                aria-label="Search products, blogs and games"
                className="flex-1 min-w-0 bg-slate-100 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-emerald-500 focus:bg-white"
              />
            </form>

            {/* Sign in / join (Sign In is hidden from the header bar below sm) */}
            {!currentUser && (
              <div className="grid grid-cols-2 gap-2 sm:hidden">
                <a
                  href="/auth/login/"
                  onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'auth', 'login'); }}
                  className="p-2.5 rounded-xl text-xs font-display font-extrabold text-center text-slate-800 border border-slate-200 no-underline"
                >
                  Sign In
                </a>
                <a
                  href="/auth/signup/"
                  onClick={(e) => { setMobileMenuOpen(false); handleNavClick(e, 'auth', 'signup'); }}
                  className="p-2.5 rounded-xl text-xs font-display font-extrabold text-center bg-gradient-to-r from-emerald-600 to-teal-600 text-white no-underline"
                >
                  Join Free
                </a>
              </div>
            )}

            <div className="grid grid-cols-2 gap-2 text-xs font-display font-extrabold">
              {siteNav.header.flatMap((item, i) => [
                <a key={'m' + i} href={item.url} onClick={() => setMobileMenuOpen(false)} className="p-2.5 rounded-xl bg-slate-50 text-slate-900 text-left no-underline block">
                  {item.label}
                </a>,
                ...(item.children || []).map((c, j) => (
                  <a key={'m' + i + '-' + j} href={c.url} onClick={() => setMobileMenuOpen(false)} className="p-2.5 rounded-xl bg-emerald-50 text-emerald-800 text-left no-underline block uppercase">
                    {c.title}
                  </a>
                )),
              ])}
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
