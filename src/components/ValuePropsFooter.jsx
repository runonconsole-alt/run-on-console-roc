import React from 'react';
import { 
  ShieldCheck, Compass, FileText, Tag, Gamepad2, PenTool, 
  CheckCircle, Mail, ExternalLink, Download, Cpu, ShoppingBag, BookOpen, Info,
  PackageCheck, Megaphone, FileCheck, Scale, Handshake, Home, LayoutGrid, Sparkles
} from 'lucide-react';
import { useApp } from '../context/AppContext';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { BrandLogo } from './BrandLogo';
import { 
  SteamLogo, PlayStationLogo, XboxLogo, EpicGamesLogo, GooglePlayLogo, 
  AppleLogo, GOGLogo, BattleNetLogo, DiscordLogo, RedditLogo, 
  YouTubeLogo, TwitterXLogo, InstagramLogo, LinkedInLogo, QuoraLogo, FacebookLogo, PinterestLogo 
} from './OfficialBrandLogos';

export const ValuePropsFooter = () => {
  const { navigateTo, navigateToCategory } = useApp();

  const valueProps = [
    {
      icon: ShieldCheck,
      title: "HONEST LAB REVIEWS",
      desc: "100% independent oscilloscope & frame-time testing standards. Zero sponsored bias."
    },
    {
      icon: Cpu,
      title: "CONSOLE & PC MATRIX",
      desc: "Instant system compatibility verification across 15 platforms & 100+ hardware devices."
    },
    {
      icon: Tag,
      title: "DIRECT AMAZON LINKS",
      desc: "Every product links straight to Amazon, where you see the current price and stock."
    },
    {
      icon: Gamepad2,
      title: "GEAR PICKS BY CATEGORY",
      desc: "Keyboards, mice, headsets, speakers, monitors and GPUs, grouped by what each is best for."
    }
  ];

  const gameDownloadPlatforms = [
    { name: "Steam Store", desc: "PC & Steam Deck", badge: "Valve Direct", color: "hover:border-cyan-400 hover:bg-cyan-950/40", url: "https://store.steampowered.com", Logo: SteamLogo },
    { name: "PlayStation Store", desc: "PS5 & PS4 Digital", badge: "Sony Direct", color: "hover:border-blue-400 hover:bg-blue-950/40", url: "https://store.playstation.com", Logo: PlayStationLogo },
    { name: "Xbox Game Pass", desc: "PC & Series X/S", badge: "100+ Games", color: "hover:border-emerald-400 hover:bg-emerald-950/40", url: "https://www.xbox.com/game-pass", Logo: XboxLogo },
    { name: "Epic Games Store", desc: "Weekly Free Drops", badge: "Epic Launcher", color: "hover:border-slate-300 hover:bg-slate-800/60", url: "https://store.epicgames.com", Logo: EpicGamesLogo },
    { name: "Google Play Games", desc: "Android PC & Mobile", badge: "Google Direct", color: "hover:border-green-400 hover:bg-green-950/40", url: "https://play.google.com/store/games", Logo: GooglePlayLogo },
    { name: "Apple App Store", desc: "iOS & iPad Gaming", badge: "Apple Arcade", color: "hover:border-sky-400 hover:bg-sky-950/40", url: "https://www.apple.com/app-store", Logo: AppleLogo },
    { name: "GOG.com Galaxy", desc: "DRM-Free Classics", badge: "CD PROJEKT", color: "hover:border-purple-400 hover:bg-purple-950/40", url: "https://www.gog.com", Logo: GOGLogo },
    { name: "Battle.net", desc: "Blizzard & COD Ops", badge: "Activision", color: "hover:border-blue-400 hover:bg-blue-950/40", url: "https://battle.net", Logo: BattleNetLogo }
  ];

  // Social profiles. Platforms without a URL stay hidden; the CMS (Social profiles)
  // can change or add links later through /roc-site.json, read by /roc-nav.js.
  const socialPlatforms = [
    { key: "facebook", name: "Facebook", desc: "News, gear picks & community", color: "hover:border-[#1877F2] hover:bg-[#1877F2]/20", url: "https://www.facebook.com/profile.php?id=61594369295787", Logo: FacebookLogo },
    { key: "instagram", name: "Instagram", desc: "Setups, desks & gear photos", color: "hover:border-[#E4405F] hover:bg-[#E4405F]/20", url: "https://www.instagram.com/runonconsole/", Logo: InstagramLogo },
    { key: "pinterest", name: "Pinterest", desc: "Setup ideas & gear boards", color: "hover:border-[#E60023] hover:bg-[#E60023]/20", url: "https://www.pinterest.com/runonconsole/", Logo: PinterestLogo },
    { key: "twitter", name: "X (Twitter)", desc: "Updates & quick picks", color: "hover:border-slate-400 hover:bg-slate-800/40", url: "https://x.com/RunOnConsole", Logo: TwitterXLogo },
    { key: "youtube", name: "YouTube", desc: "Videos & guides", color: "hover:border-[#FF0000] hover:bg-[#FF0000]/20", url: "", Logo: YouTubeLogo },
    { key: "discord", name: "Discord", desc: "Community chat", color: "hover:border-[#5865F2] hover:bg-[#5865F2]/20", url: "", Logo: DiscordLogo },
    { key: "reddit", name: "Reddit", desc: "Community discussions", color: "hover:border-[#FF4500] hover:bg-[#FF4500]/20", url: "", Logo: RedditLogo },
    { key: "linkedin", name: "LinkedIn", desc: "Company page", color: "hover:border-[#0A66C2] hover:bg-[#0A66C2]/20", url: "", Logo: LinkedInLogo },
    { key: "quora", name: "Quora", desc: "Questions & answers", color: "hover:border-[#B92B27] hover:bg-[#B92B27]/20", url: "", Logo: QuoraLogo }
  ];


  return (
    <footer className="footer-emerald-gradient text-white relative overflow-hidden border-t-2 border-emerald-500/40 mt-16">
      
      <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[800px] h-[350px] bg-emerald-500/10 blur-[120px] pointer-events-none"></div>

      {/* 1. Value Proposition Cards */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-b border-emerald-800/60 relative z-10">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
          {valueProps.map((vp, idx) => {
            const Icon = vp.icon;
            return (
              <Tilt3DCard
                key={idx}
                className="bg-emerald-950/70 border border-emerald-700/60 hover:border-emerald-400 p-5 rounded-2xl backdrop-blur-md shadow-lg transition-all group"
              >
                <div className="w-10 h-10 rounded-xl bg-emerald-800 text-emerald-300 flex items-center justify-center mb-3 group-hover:scale-110 group-hover:bg-emerald-700 transition-all shadow-md">
                  <Icon className="w-5 h-5" />
                </div>
                <h4 className="font-display font-extrabold text-xs sm:text-sm text-white uppercase tracking-wider mb-1 group-hover:text-emerald-300 transition-colors">
                  <BouncyText text={vp.title} />
                </h4>
                <p className="text-xs text-emerald-200/75 leading-relaxed font-medium">
                  {vp.desc}
                </p>
              </Tilt3DCard>
            );
          })}
        </div>
      </div>

      {/* 2. DIRECT GAME DOWNLOAD & LAUNCHER STORES HUB */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-b border-emerald-800/60 relative z-10">
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <span className="text-xs font-display font-extrabold uppercase text-emerald-300 tracking-wider flex items-center gap-1.5">
                <Download className="w-3.5 h-3.5 animate-bounce" /> OFFICIAL DIRECT GAME LAUNCHERS & DOWNLOAD STORES
              </span>
              <h3 className="font-display font-extrabold text-lg sm:text-xl text-white">
                <BouncyText text="Download Compatible Games & Official Launchers" />
              </h3>
            </div>
            <span className="text-xs text-emerald-300/80 font-medium">
              Direct links to certified platform storefronts
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
            {gameDownloadPlatforms.map((store) => {
              const StoreLogo = store.Logo;
              return (
                <a
                  key={store.name}
                  href={store.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={`bg-emerald-950/80 border border-emerald-700/60 ${store.color} p-3.5 rounded-2xl flex items-center justify-between gap-3 shadow-md transition-all hover:scale-102 hover:-translate-y-0.5 group no-underline`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="w-8 h-8 rounded-xl bg-slate-900 border border-emerald-500/30 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-inner p-1.5">
                      <StoreLogo className="w-5 h-5" />
                    </div>
                    <div className="min-w-0">
                      <h4 className="font-display font-bold text-xs text-white group-hover:text-emerald-300 truncate">
                        {store.name}
                      </h4>
                      <span className="text-[10px] text-emerald-300/70 block truncate">
                        {store.desc}
                      </span>
                    </div>
                  </div>

                  <span className="text-[9px] font-extrabold bg-emerald-900 text-emerald-200 border border-emerald-600/40 px-2 py-0.5 rounded shrink-0">
                    {store.badge}
                  </span>
                </a>
              );
            })}
          </div>
        </div>
      </div>

      {/* 3. GAMING SOCIAL MEDIA PLATFORMS HUB */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-b border-emerald-800/60 relative z-10">
        <div className="space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <span className="text-xs font-display font-extrabold uppercase text-emerald-300 tracking-wider flex items-center gap-1.5">
                <Sparkles className="w-3.5 h-3.5" /> JOIN THE RUN ON CONSOLE GAMING COMMUNITY
              </span>
              <h3 className="font-display font-extrabold text-lg sm:text-xl text-white">
                <BouncyText text="Follow Run On Console" />
              </h3>
            </div>
            <span className="text-xs text-emerald-300/80 font-medium">
              Gear picks, setups and updates
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
            {socialPlatforms.map((soc) => {
              const PlatformLogo = soc.Logo;
              return (
                <a
                  key={soc.key}
                  href={soc.url || undefined}
                  hidden={!soc.url}
                  data-roc-social={soc.key}
                  target="_blank"
                  rel="noopener me"
                  className={`bg-emerald-950/80 border border-emerald-700/60 ${soc.color} p-3.5 rounded-2xl ${soc.url ? "flex" : "hidden"} items-center justify-between gap-3 shadow-md transition-all hover:scale-102 hover:-translate-y-0.5 group no-underline`}
                >
                  <div className="flex items-center gap-3 min-w-0">
                    <div className="w-8 h-8 rounded-xl bg-slate-900 border border-emerald-500/30 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-inner p-1.5">
                      <PlatformLogo className="w-5 h-5" />
                    </div>
                    <div className="min-w-0">
                      <h4 className="font-display font-bold text-xs text-white group-hover:text-emerald-300 truncate">
                        {soc.name}
                      </h4>
                      <span className="text-[10px] text-emerald-300/70 block truncate">
                        {soc.desc}
                      </span>
                    </div>
                  </div>

                  <ExternalLink className="w-3.5 h-3.5 text-emerald-400 group-hover:text-white shrink-0 transition-colors" />
                </a>
              );
            })}
          </div>
        </div>
      </div>

      {/* 4. Main Clean 4-Column Crawlable Footer Navigation */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 border-b border-emerald-800/60 relative z-10">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-12">
          
          <div className="lg:col-span-5 space-y-4">
            <a href="/" onClick={(e) => { e.preventDefault(); navigateTo('home'); }} className="no-underline block">
              <BrandLogo size="large" theme="dark" />
            </a>

            <p className="text-xs text-emerald-100/90 leading-relaxed max-w-md">
              <BouncyText text="ROC (Run On Console) is an independent gaming authority dedicated to providing 100% honest, benchmark-tested reviews on consoles, PC hardware, and accessories with verified game compatibility matrices." />
            </p>

            <div className="pt-1 space-y-1.5">
              <div className="flex items-center gap-2 text-xs text-emerald-300 font-semibold">
                <CheckCircle className="w-4 h-4 text-emerald-400 shrink-0" />
                <span>Independent Laboratory Benchmark Testing Standards</span>
              </div>
              <div className="flex items-center gap-2 text-xs text-emerald-300/80 font-mono">
                <ShieldCheck className="w-4 h-4 text-emerald-400 shrink-0" />
                <span>Zero Sponsored Bias • Verified Storefront Pricing</span>
              </div>
            </div>
          </div>

          <div className="lg:col-span-3 space-y-3">
            <h5 className="font-display font-extrabold text-xs text-emerald-300 uppercase tracking-wider flex items-center gap-1.5">
              <Compass className="w-3.5 h-3.5 text-emerald-400" />
              <span>EXPLORE HUB</span>
            </h5>
            <ul className="space-y-2 text-xs text-emerald-100/80 font-medium">
              <li>
                <a href="/" onClick={(e) => { e.preventDefault(); navigateTo('home'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Home className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Home Overview</span>
                </a>
              </li>
              <li>
                <a href="/categories/" onClick={(e) => { e.preventDefault(); navigateToCategory('all'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <LayoutGrid className="w-3.5 h-3.5 text-emerald-400" />
                  <span>15 Gaming Platforms (100+ Devices)</span>
                </a>
              </li>
              <li>
                <a href="/compatibility/" onClick={(e) => { e.preventDefault(); navigateTo('compatibility'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Cpu className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Can I Run It? Compatibility Matrix</span>
                </a>
              </li>
              <li>
                <a href="/products/" onClick={(e) => { e.preventDefault(); navigateTo('products'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <ShoppingBag className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Peripherals, Gear & Loadouts</span>
                </a>
              </li>
              <li>
                <a href="/products/" onClick={(e) => { e.preventDefault(); navigateTo('products'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Tag className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Hardware Reviews & Deals</span>
                </a>
              </li>
              <li>
                <a href="/blogs/" onClick={(e) => { e.preventDefault(); navigateTo('blogs'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <BookOpen className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Editorial Guides & Benchmarks</span>
                </a>
              </li>
              <li>
                <a href="/about/" onClick={(e) => { e.preventDefault(); navigateTo('about'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Info className="w-3.5 h-3.5 text-emerald-400" />
                  <span>About Run On Console</span>
                </a>
              </li>
            </ul>
          </div>

          <div className="lg:col-span-2 space-y-3">
            <h5 className="font-display font-extrabold text-xs text-emerald-300 uppercase tracking-wider flex items-center gap-1.5">
              <Handshake className="w-3.5 h-3.5 text-emerald-400" />
              <span>PARTNERS</span>
            </h5>
            <ul className="space-y-2 text-xs text-emerald-100/80 font-medium">
              <li>
                <a href="/write-for-us/" onClick={(e) => { e.preventDefault(); navigateTo('write-for-us'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <PenTool className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Write For Us</span>
                </a>
              </li>
              <li>
                <a href="/contact/" onClick={(e) => { e.preventDefault(); navigateTo('contact'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <PackageCheck className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Submit Review Sample</span>
                </a>
              </li>
              <li>
                <a href="/partnerships/" onClick={(e) => { e.preventDefault(); navigateTo('partnerships'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Megaphone className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Advertising & Partnerships</span>
                </a>
              </li>
            </ul>
          </div>

          <div className="lg:col-span-2 space-y-3">
            <h5 className="font-display font-extrabold text-xs text-emerald-300 uppercase tracking-wider flex items-center gap-1.5">
              <FileText className="w-3.5 h-3.5 text-emerald-400" />
              <span>POLICIES</span>
            </h5>
            <ul className="space-y-2 text-xs text-emerald-100/80 font-medium">
              <li>
                <a href="/terms-and-conditions/" onClick={(e) => { e.preventDefault(); navigateTo('terms-and-conditions'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Scale className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Terms & Conditions</span>
                </a>
              </li>
              <li>
                <a href="/privacy-policy/" onClick={(e) => { e.preventDefault(); navigateTo('privacy-policy'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <FileCheck className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Privacy Policy</span>
                </a>
              </li>
              <li>
                <a href="/policy/" onClick={(e) => { e.preventDefault(); navigateTo('policy'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <FileCheck className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Affiliate Disclosure</span>
                </a>
              </li>
              <li>
                <a href="/contact/" onClick={(e) => { e.preventDefault(); navigateTo('contact'); }} className="hover:text-emerald-300 transition-colors flex items-center gap-2 text-left no-underline">
                  <Mail className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Contact Us</span>
                </a>
              </li>
            </ul>
          </div>

        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-emerald-200/80 relative z-10">
        <div>
          © {new Date().getFullYear()} ROC (Run On Console). All rights reserved. Built for gamers & console enthusiasts.
        </div>
        <div className="text-[11px] text-emerald-300/90 font-mono">
          Amazon Associate • Prices and stock availability verified daily.
        </div>
      </div>

    </footer>
  );
};
