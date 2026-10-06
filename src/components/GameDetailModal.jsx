import React from 'react';
import { useApp } from '../context/AppContext';
import { 
  X, Gamepad2, Sparkles, CheckCircle2, Cpu, HardDrive, 
  Monitor, Laptop, Flame, Zap, ArrowRight, ShieldCheck, 
  Calendar, Building2, Users, Download, Play, Trophy, Activity, ExternalLink
} from 'lucide-react';
import { playClickSound, playPowerUpSound } from '../utils/audioEffects';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { BouncyText } from './BouncyText';

export const GameDetailModal = ({ game, onClose, onRunCompatibilityTest }) => {
  const { navigateTo } = useApp();

  if (!game) return null;

  // Enrich game stats with realistic, rich gaming metadata
  const developer = game.developer || (
    game.gameTitle.includes('Grand Theft Auto') || game.gameTitle.includes('GTA') || game.gameTitle.includes('Red Dead') 
      ? 'Rockstar Games (Rockstar North)' 
      : game.gameTitle.includes('Black Ops') || game.gameTitle.includes('Call of Duty')
        ? 'Activision / Treyarch & Raven Software'
        : game.gameTitle.includes('Elden Ring')
          ? 'FromSoftware / Bandai Namco'
          : game.gameTitle.includes('Cyberpunk') || game.gameTitle.includes('Witcher')
            ? 'CD PROJEKT RED'
            : game.gameTitle.includes('Wukong')
              ? 'Game Science'
              : game.gameTitle.includes('Valorant')
                ? 'Riot Games'
                : game.gameTitle.includes('Counter-Strike')
                  ? 'Valve Corporation'
                  : game.gameTitle.includes('Fortnite')
                    ? 'Epic Games'
                    : 'Leading Esports & AAA Game Studios'
  );

  const releaseDate = game.releaseDate || (
    game.gameTitle.includes('GTA 5') || game.gameTitle.includes('Grand Theft Auto V')
      ? 'Sept 17, 2013 (PC Launch: April 14, 2015)'
      : game.gameTitle.includes('GTA 6') || game.gameTitle.includes('Grand Theft Auto VI')
        ? 'Fall 2025 / 2026 (Announced)'
        : game.gameTitle.includes('Red Dead')
          ? 'Oct 26, 2018 (PC: Nov 5, 2019)'
          : game.gameTitle.includes('Black Ops 6')
            ? 'Oct 25, 2024'
            : game.gameTitle.includes('Elden Ring')
              ? 'Feb 25, 2022'
              : game.gameTitle.includes('Cyberpunk')
                ? 'Dec 10, 2020 (Phantom Liberty: Sept 26, 2023)'
                : game.gameTitle.includes('Wukong')
                  ? 'Aug 20, 2024'
                  : 'Multi-Platform Verified'
  );

  const activePlayers = game.activePlayers || (
    game.gameTitle.includes('GTA 5') || game.gameTitle.includes('Grand Theft Auto V')
      ? 'Active Online Multiplayer Community'
      : game.gameTitle.includes('Black Ops 6') || game.gameTitle.includes('Warzone')
        ? 'Active Competitive Esports Title'
        : 'Verified PC Game Catalog Title'
  );

  const installGuide = game.installGuide || (
    game.gameTitle.includes('GTA 5') || game.gameTitle.includes('Grand Theft Auto V')
      ? 'Available via Steam, Epic Games Store, or Rockstar Games Launcher. Minimum 110 GB free storage required. For optimal loading and FiveM multiplayer stability, install on an NVMe PCIe 4.0 SSD and enable DirectX 11.'
      : game.gameTitle.includes('Black Ops 6')
        ? 'Available on Battle.net, Steam, Xbox PC App, and PlayStation Network. Requires Call of Duty HQ (approx 120 GB). Ensure shader pre-compilation finishes before entering multiplayer matches.'
        : 'Download directly via Steam, Epic Games Store, PlayStation Store, or Xbox Marketplace. Always ensure latest GPU drivers (NVIDIA 550+, AMD Adrenalin 24+) are installed.'
  );

  return (
    <div className="fixed inset-0 z-[10000] flex items-center justify-center p-4 sm:p-6 bg-slate-950/80 backdrop-blur-md overflow-y-auto animate-fade-in">
      <div className="relative w-full max-w-4xl bg-slate-900 border-2 border-emerald-500/50 rounded-3xl p-6 sm:p-8 text-white shadow-2xl overflow-hidden my-auto space-y-6">
        
        <CyberMatrixHoloBackground />

        {/* Ambient Glows */}
        <div className="absolute -top-32 -left-32 w-80 h-80 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-32 -right-32 w-80 h-80 bg-teal-500/20 rounded-full blur-3xl pointer-events-none"></div>

        {/* Close Button */}
        <button
          onClick={() => { playClickSound(); onClose(); }}
          className="absolute top-5 right-5 z-20 w-10 h-10 rounded-2xl bg-slate-800/90 hover:bg-rose-600 text-slate-300 hover:text-white flex items-center justify-center transition-all border border-slate-700 shadow-lg hover:scale-105"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Top Game Hero & Title */}
        <div className="relative z-10 space-y-3 pt-2">
          <div className="flex flex-wrap items-center gap-2.5">
            <span className="bg-emerald-400 text-slate-950 text-xs font-display font-extrabold uppercase px-3.5 py-1 rounded-full flex items-center gap-1.5 shadow-md">
              <Gamepad2 className="w-3.5 h-3.5" /> GAME HARDWARE & COMPATIBILITY HUB
            </span>
            <span className="bg-slate-800/90 text-emerald-300 text-xs font-bold px-3 py-1 rounded-full border border-emerald-500/40">
              {game.genre}
            </span>
            <span className="bg-emerald-950/80 text-emerald-400 text-xs font-mono font-bold px-3 py-1 rounded-full border border-emerald-400/40">
              ★ {game.compatibilityScore || 'Verified System Requirements'}
            </span>
          </div>

          <h2 className="font-display font-extrabold text-2xl sm:text-4xl text-white tracking-tight leading-tight">
            {game.gameTitle}
          </h2>
        </div>

        {/* 3 Vital Game Intelligence Metrics */}
        <div className="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-3.5">
          <div className="bg-slate-800/80 border border-slate-700 p-4 rounded-2xl space-y-1">
            <div className="flex items-center gap-2 text-xs font-extrabold text-emerald-400 uppercase tracking-wider">
              <Building2 className="w-4 h-4" />
              <span>DEVELOPER & STUDIO</span>
            </div>
            <div className="text-sm font-bold text-white">{developer}</div>
          </div>

          <div className="bg-slate-800/80 border border-slate-700 p-4 rounded-2xl space-y-1">
            <div className="flex items-center gap-2 text-xs font-extrabold text-cyan-400 uppercase tracking-wider">
              <Calendar className="w-4 h-4" />
              <span>RELEASE DATE</span>
            </div>
            <div className="text-sm font-bold text-white">{releaseDate}</div>
          </div>

          <div className="bg-slate-800/80 border border-slate-700 p-4 rounded-2xl space-y-1">
            <div className="flex items-center gap-2 text-xs font-extrabold text-amber-400 uppercase tracking-wider">
              <Users className="w-4 h-4" />
              <span>COMMUNITY STATUS</span>
            </div>
            <div className="text-xs font-bold text-white leading-snug">{activePlayers}</div>
          </div>
        </div>

        {/* Installation & Storage Guide */}
        <div className="relative z-10 bg-slate-950/80 border border-emerald-500/30 p-5 rounded-2xl space-y-2">
          <div className="flex items-center gap-2 text-xs font-extrabold text-emerald-400 uppercase tracking-wider">
            <Download className="w-4 h-4 text-emerald-400" />
            <span>HOW TO INSTALL & STORAGE OPTIMIZATION</span>
          </div>
          <p className="text-xs sm:text-sm text-slate-300 leading-relaxed">
            {installGuide}
          </p>
        </div>

        {/* PC Specs Requirements Side-by-Side */}
        <div className="relative z-10 grid grid-cols-1 md:grid-cols-2 gap-4">
          
          {/* Minimum Specs */}
          <div className="bg-slate-800/90 border border-slate-700 p-5 rounded-2xl space-y-3">
            <div className="flex items-center justify-between border-b border-slate-700 pb-2">
              <span className="text-xs font-extrabold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                <Cpu className="w-4 h-4" />
                <span>MINIMUM PC SPECS (Basic Settings)</span>
              </span>
            </div>
            <p className="text-xs text-slate-300 font-mono leading-relaxed">
              {game.minSpecs}
            </p>
          </div>

          {/* Recommended Specs */}
          <div className="bg-slate-800/90 border-2 border-emerald-500/50 p-5 rounded-2xl space-y-3">
            <div className="flex items-center justify-between border-b border-emerald-500/30 pb-2">
              <span className="text-xs font-extrabold text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                <Flame className="w-4 h-4 text-emerald-400" />
                <span>RECOMMENDED PC SPECS (High Quality Settings)</span>
              </span>
            </div>
            <p className="text-xs text-slate-300 font-mono leading-relaxed">
              {game.recommendedSpecs}
            </p>
          </div>

        </div>

        {/* Hardware FPS Target Performance */}
        <div className="relative z-10 bg-slate-800/80 border border-slate-700 p-4 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="text-xs font-bold text-emerald-400 uppercase tracking-wider">
              HARDWARE BENCHMARK TARGET
            </div>
            <div className="text-sm font-extrabold text-white">
              {game.fpsTarget}
            </div>
          </div>

          <button
            onClick={() => {
              playPowerUpSound();
              onClose();
              if (onRunCompatibilityTest) {
                onRunCompatibilityTest(game.id);
              } else {
                navigateTo('compatibility');
              }
            }}
            className="w-full sm:w-auto bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white font-display font-extrabold text-xs sm:text-sm py-3.5 px-6 rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2 transition-all hover:scale-105 shrink-0"
          >
            <Zap className="w-4 h-4 text-emerald-200" />
            <span>Test My PC Specs For This Game →</span>
          </button>
        </div>

      </div>
    </div>
  );
};
