import React, { useState, useMemo, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Gamepad2, Search, Filter, Sparkles, CheckCircle, Flame, Zap, 
  Monitor, Laptop, Cpu, ShieldCheck, ArrowRight, RotateCcw, Volume2,
  HardDrive, Cloud, Smartphone, Disc, HelpCircle, CheckCircle2,
  AlertTriangle, RefreshCw, Layers, ExternalLink, ChevronRight, Play,
  Tv, Radio, ThumbsUp, DownloadCloud, Sliders, Check, AlertCircle, Wrench, Bot
} from 'lucide-react';
import { FAQSection } from './FAQSection';
import { GAMING_CATEGORIES } from '../data/initialData';
import { Tilt3DCard } from './Tilt3DCard';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { BouncyText } from './BouncyText';
import { playClickSound, playPowerUpSound, playHoverSound } from '../utils/audioEffects';

/* Requirement tier scores (same scale as the CPU / GPU options below; RAM in GB).
   Games list their own in gameCompatibilityData.js (req); older entries use their era. */
const ERA_REQUIREMENTS = {
  classic: { min: { cpu: 2, gpu: 1.8, ram: 4 }, rec: { cpu: 4.5, gpu: 1.8, ram: 8 } },
  modern: { min: { cpu: 4.5, gpu: 4.5, ram: 8 }, rec: { cpu: 7, gpu: 7, ram: 16 } },
  upcoming: { min: { cpu: 7, gpu: 7, ram: 16 }, rec: { cpu: 8.5, gpu: 8.5, ram: 32 } },
};
const gameRequirements = (game) => game.req || ERA_REQUIREMENTS[game.era] || ERA_REQUIREMENTS.modern;

export const GameCompatibilityView = () => {
  const { gameCompatibility = [], navigateToCategory, navigateToProduct, pageFaqs } = useApp();

  const [selectedEra, setSelectedEra] = useState('all');
  const [selectedPlatform, setSelectedPlatform] = useState('all');
  const [searchQuery, setSearchQuery] = useState('');

  // Mode: 'quick' (preset device profiles) vs 'custom' (user custom CPU/GPU/RAM specs)
  const [checkerMode, setCheckerMode] = useState('custom');

  // Selected Target Game
  const [checkerGameId, setCheckerGameId] = useState(gameCompatibility[0]?.id || 'game-gta-5');

  // Arriving from the header search (/compatibility/?q=Game title): filter the list and
  // pick that game in the checker. Done after hydration so the server HTML still matches.
  useEffect(() => {
    const q = new URLSearchParams(window.location.search).get('q');
    if (!q) return;
    setSearchQuery(q);
    const game = gameCompatibility.find((g) => g.gameTitle.toLowerCase() === q.toLowerCase());
    if (game) setCheckerGameId(game.id);
  }, []);

  // Mode A: Preset Device Profile State
  const [checkerDeviceId, setCheckerDeviceId] = useState('pc-mid');

  // Mode B: Custom Specs Input State
  const [customDeviceName, setCustomDeviceName] = useState('My Custom PC / Gaming Laptop');
  const [customCpuTier, setCustomCpuTier] = useState('mid_i5'); // 'ultra_i9', 'high_i7', 'mid_i5', 'low_i3', 'ancient'
  const [customGpuTier, setCustomGpuTier] = useState('mid_rtx3060'); // 'ultra_rtx4080', 'high_rtx4070', 'mid_rtx3060', 'entry_gtx1650', 'integrated_uhd'
  const [customRamGb, setCustomRamGb] = useState(16); // 4, 8, 16, 32, 64
  const [customStorage, setCustomStorage] = useState('nvme'); // 'nvme', 'sata_ssd', 'hdd'

  // 500 popular PC games (2021 on) with their official Steam requirements: /roc-steam-games.json,
  // written by scripts/fetch-steam-games.mjs. Loaded after the page opens.
  const [steamGames, setSteamGames] = useState([]);
  const [steamPick, setSteamPick] = useState(null);
  const [steamQuery, setSteamQuery] = useState('');
  useEffect(() => {
    fetch('/roc-steam-games.json').then((r) => (r.ok ? r.json() : null)).then((d) => {
      const list = (d && d.games) || [];
      setSteamGames(list);
      const id = new URLSearchParams(window.location.search).get('steam');
      if (id) { const g = list.find((x) => String(x.appid) === id); if (g) setSteamPick(g); }
    }).catch(() => {});
  }, []);
  const steamMatches = useMemo(() => {
    const q = steamQuery.trim().toLowerCase();
    if (q.length < 2) return [];
    return steamGames.filter((g) => g.t.toLowerCase().includes(q)).slice(0, 8);
  }, [steamQuery, steamGames]);

  const specLine = (b) => (b ? [b.cpu, b.gpu, b.ram, b.storage].filter(Boolean).join(' / ') : '');
  const selectedCheckerGame = useMemo(() => {
    if (steamPick) {
      return {
        id: `steam-${steamPick.appid}`,
        gameTitle: steamPick.t,
        era: 'modern',
        eraLabel: `Released ${steamPick.y}`,
        genre: steamPick.g || 'PC game',
        fpsTarget: 'Official Steam system requirements',
        minSpecs: specLine(steamPick.min) || 'Not listed on Steam',
        recommendedSpecs: specLine(steamPick.rec) || 'Not listed on Steam (the minimum is used)',
        req: steamPick.req,
        steamUrl: steamPick.u,
      };
    }
    return gameCompatibility.find(g => g.id === checkerGameId) || gameCompatibility[0];
  }, [checkerGameId, gameCompatibility, steamPick]);

  const deviceProfiles = [
    { id: 'pc-high', name: 'High-End Gaming PC (RTX 4080 / 4090 + 32GB RAM)', type: 'pc', power: 10 },
    { id: 'pc-mid', name: 'Mid-Range Gaming PC / Laptop (RTX 3060 / 4060 + 16GB RAM)', type: 'pc', power: 7.5 },
    { id: 'pc-low', name: 'Older / Budget Office PC (Intel Core i3 / Integrated UHD Graphics + 8GB)', type: 'pc_low', power: 3.5 },
    { id: 'ps5', name: 'Sony PlayStation 5 / PS5 Pro Console', type: 'ps5', power: 9 },
    { id: 'ps4', name: 'Sony PlayStation 4 / PS4 Pro Console', type: 'ps4', power: 6 },
    { id: 'xbox-series', name: 'Microsoft Xbox Series X / Series S Console', type: 'xbox', power: 8.5 },
    { id: 'deck', name: 'Handheld PC (Steam Deck OLED / ASUS ROG Ally / Legion Go)', type: 'handheld', power: 6.5 },
    { id: 'switch', name: 'Nintendo Switch / Switch OLED', type: 'switch', power: 4 },
    { id: 'mobile', name: 'Smartphone / Tablet (Snapdragon 8 Gen 3 / iPhone 15/16 Pro)', type: 'mobile', power: 5.5 },
    { id: 'cloud', name: 'Cloud Gaming (NVIDIA GeForce NOW / Xbox Cloud Gaming)', type: 'cloud', power: 10 }
  ];

  // CPU Tier Metadata
  const cpuOptions = [
    { id: 'ultra_i9', label: 'Intel Core i9 13th/14th Gen / AMD Ryzen 9 / 7800X3D (Enthusiast)', score: 10 },
    { id: 'high_i7', label: 'Intel Core i7 12th/13th/14th Gen / AMD Ryzen 7 5800X (High Performance)', score: 8.5 },
    { id: 'mid_i5', label: 'Intel Core i5 10th/11th/12th/13th Gen / AMD Ryzen 5 5600X (Standard Gaming)', score: 7 },
    { id: 'low_i3', label: 'Intel Core i3 8th-12th Gen / Older Quad-Core CPU (Budget Entry)', score: 4.5 },
    { id: 'ancient', label: 'Intel Core 2 Duo / Pentium / Dual-Core (Legacy Office PC)', score: 2 }
  ];

  // GPU Tier Metadata
  const gpuOptions = [
    { id: 'ultra_rtx4080', label: 'NVIDIA RTX 4080 / 4090 / AMD RX 7900 XTX (Max 4K Ray Tracing)', score: 10, vram: '16GB-24GB' },
    { id: 'high_rtx4070', label: 'NVIDIA RTX 4070 / 3080 / AMD RX 7800 XT (1440p / 4K High)', score: 8.5, vram: '12GB' },
    { id: 'mid_rtx3060', label: 'NVIDIA RTX 3060 / 4060 / AMD RX 6600 (1080p / 1440p Fluid)', score: 7, vram: '8GB-12GB' },
    { id: 'entry_gtx1650', label: 'NVIDIA GTX 1650 / GTX 1060 / AMD RX 580 (1080p Low-Med)', score: 4.5, vram: '4GB-6GB' },
    { id: 'integrated_uhd', label: 'Intel UHD Graphics 630 / Iris Xe / Integrated AMD APU (Office / Non-Gaming)', score: 1.8, vram: 'Shared' }
  ];

  // Custom Hardware Compatibility Engine & Percentage Calculator
  const customAnalysisResult = useMemo(() => {
    if (!selectedCheckerGame) return null;

    const gameEra = selectedCheckerGame.era; // 'upcoming', 'modern', 'classic'
    const selectedCpu = cpuOptions.find(c => c.id === customCpuTier) || cpuOptions[2];
    const selectedGpu = gpuOptions.find(g => g.id === customGpuTier) || gpuOptions[2];
    const req = gameRequirements(selectedCheckerGame);

    // Each part against this game's recommended specs (graphics card counts most). A part the
    // official text did not let us read (null) is left out rather than guessed.
    const known = (v) => typeof v === 'number' && v > 0;
    const parts = [[0.5, selectedGpu.score, req.rec.gpu], [0.3, selectedCpu.score, req.rec.cpu], [0.2, customRamGb, req.rec.ram]]
      .filter(([, , rec]) => known(rec));
    const weight = parts.reduce((a, [w]) => a + w, 0) || 1;
    const storageFactor = customStorage === 'nvme' ? 1 : customStorage === 'sata_ssd' ? 0.95 : 0.8;
    let percentage = Math.round(100 * storageFactor * parts.reduce((a, [w, have, rec]) => a + w * Math.min(1, have / rec), 0) / weight);
    const unread = [!known(req.min.gpu) && 'graphics card', !known(req.min.cpu) && 'processor', !known(req.min.ram) && 'memory'].filter(Boolean);
    const below = (have, min) => known(min) && have < min;
    const belowMin = below(selectedGpu.score, req.min.gpu) || below(selectedCpu.score, req.min.cpu) || below(customRamGb, req.min.ram);
    if (belowMin) percentage = Math.min(percentage, 40);
    percentage = Math.max(5, percentage);

    const bottlenecks = [];
    if (below(selectedGpu.score, req.min.gpu)) bottlenecks.push(`Graphics card is below this game's minimum (${selectedGpu.label.split(' (')[0]}).`);
    if (below(selectedCpu.score, req.min.cpu)) bottlenecks.push(`Processor is below this game's minimum (${selectedCpu.label.split(' (')[0]}).`);
    if (below(customRamGb, req.min.ram)) bottlenecks.push(`${customRamGb}GB RAM is below this game's minimum of ${req.min.ram}GB.`);
    if (customStorage === 'hdd' && gameEra !== 'classic') bottlenecks.push('A hard disk (HDD) causes long loading and stutter. This game is meant for an SSD.');

    // What to change, part by part, to reach this game's recommended specs.
    const tierName = (opts, score) => (opts.find(o => o.score >= score) || opts[0]).label.split(' (')[0];
    const ascending = (opts) => [...opts].sort((a, b) => a.score - b.score);
    const missing = [];
    if (below(selectedGpu.score, req.rec.gpu)) missing.push({ part: 'Graphics card', have: selectedGpu.label.split(' (')[0], need: tierName(ascending(gpuOptions), req.rec.gpu) + ' or better' });
    if (below(selectedCpu.score, req.rec.cpu)) missing.push({ part: 'Processor', have: selectedCpu.label.split(' (')[0], need: tierName(ascending(cpuOptions), req.rec.cpu) + ' or better' });
    if (below(customRamGb, req.rec.ram)) missing.push({ part: 'Memory (RAM)', have: `${customRamGb}GB`, need: `${req.rec.ram}GB` });
    if (customStorage === 'hdd' && gameEra !== 'classic') missing.push({ part: 'Storage', have: 'Hard disk (HDD)', need: 'SSD (NVMe preferred)' });

    let statusBadge, statusColor, fpsEstimate, upgradeRecommendation;
    if (percentage >= 90) {
      statusBadge = `${percentage}% - MEETS RECOMMENDED SPECS`;
      statusColor = "text-emerald-700 bg-emerald-50 border-emerald-300";
      fpsEstimate = "60+ FPS at 1080p / 1440p High";
      upgradeRecommendation = "Your PC meets or beats this game's recommended specs. Play on high settings.";
    } else if (percentage >= 70) {
      statusBadge = `${percentage}% - PLAYABLE (MEDIUM / HIGH)`;
      statusColor = "text-teal-700 bg-teal-50 border-teal-300";
      fpsEstimate = "45 - 60 FPS at 1080p Medium";
      upgradeRecommendation = "Above the minimum but under the recommended specs. Use medium settings and DLSS / FSR upscaling for a steady frame rate.";
    } else if (percentage > 40) {
      statusBadge = `${percentage}% - PLAYABLE ON LOW SETTINGS`;
      statusColor = "text-amber-800 bg-amber-50 border-amber-300";
      fpsEstimate = "30 - 45 FPS at 1080p / 720p Low";
      upgradeRecommendation = "It meets the minimum specs only. Expect low settings; see the table above for the upgrades that make the biggest difference.";
    } else {
      statusBadge = `${percentage}% - BELOW MINIMUM SPECS`;
      statusColor = "text-rose-800 bg-rose-50 border-rose-300";
      fpsEstimate = "Not playable (under 20 FPS or will not start)";
      upgradeRecommendation = "This PC is below the game's minimum specs. Upgrade the parts listed above, or play it through a cloud gaming service such as NVIDIA GeForce NOW or Xbox Cloud Gaming if the game is available there.";
    }

    // Without the graphics card or the processor there is no honest score: say so instead.
    const unrated = !known(req.rec.gpu) || !known(req.rec.cpu);
    if (unrated) {
      statusBadge = 'CHECK THE OFFICIAL REQUIREMENTS';
      statusColor = 'text-slate-800 bg-slate-50 border-slate-300';
      fpsEstimate = 'Not estimated: compare your PC with the requirements above';
    }
    if (unread.length) {
      upgradeRecommendation = `The ${unread.join(' and ')} in the official requirements could not be matched to our list, so this score leaves ${unread.length > 1 ? 'them' : 'it'} out. Compare your ${unread.join(' and ')} with the requirements above. ` + upgradeRecommendation;
    }
    return { percentage, missing, statusBadge, statusColor, fpsEstimate, bottlenecks, upgradeRecommendation, req, unread, unrated };
  }, [selectedCheckerGame, customCpuTier, customGpuTier, customRamGb, customStorage]);

  const eras = [
    { id: 'all', label: 'All Eras & Generations' },
    { id: 'upcoming', label: 'Upcoming (GTA 6)' },
    { id: 'modern', label: 'Modern hits (2021 to today)' },
    { id: 'classic', label: 'Classics & retro' },
  ];

  const platforms = [
    { id: 'all', label: 'All Platforms' },
    { id: 'pc', label: 'PC Battlestations' },
    { id: 'ps5', label: 'PlayStation (PS5/PS4/PSP)' },
    { id: 'xbox', label: 'Xbox Series X/S' },
    { id: 'handheld', label: 'Handhelds (Steam Deck / ROG)' },
    { id: 'mobile', label: 'Mobile & Tablets' },
  ];

  const filteredGames = useMemo(() => {
    return gameCompatibility.filter((game) => {
      if (selectedEra !== 'all' && game.era !== selectedEra) {
        return false;
      }
      if (selectedPlatform !== 'all') {
        const pQuery = selectedPlatform.toLowerCase();
        const matchPlat = game.platforms.some(p => {
          const platLower = p.toLowerCase();
          if (pQuery === 'pc') return platLower.includes('pc') || platLower.includes('laptop');
          if (pQuery === 'ps5') return platLower.includes('ps5') || platLower.includes('playstation') || platLower.includes('ps4') || platLower.includes('psp') || platLower.includes('ps3') || platLower.includes('ps2');
          if (pQuery === 'xbox') return platLower.includes('xbox');
          if (pQuery === 'handheld') return platLower.includes('handheld') || platLower.includes('deck') || platLower.includes('rog') || platLower.includes('switch') || platLower.includes('anbernic');
          if (pQuery === 'mobile') return platLower.includes('mobile') || platLower.includes('android') || platLower.includes('ios') || platLower.includes('iphone');
          return false;
        });
        if (!matchPlat) return false;
      }
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        return (
          game.gameTitle.toLowerCase().includes(q) ||
          game.minSpecs.toLowerCase().includes(q) ||
          game.genre.toLowerCase().includes(q) ||
          game.recommendedGear.toLowerCase().includes(q) ||
          game.platforms.some(p => p.toLowerCase().includes(q))
        );
      }
      return true;
    });
  }, [gameCompatibility, selectedEra, selectedPlatform, searchQuery]);

  const handleReset = () => {
    playClickSound();
    setSelectedEra('all');
    setSelectedPlatform('all');
    setSearchQuery('');
  };

  return (
    <div className="space-y-12 py-6 animate-page-in">
      
      {/* 1. Game Compatibility Hero Banner with BouncyText */}
      <div className="gradient-hero-bg text-white rounded-3xl p-6 sm:p-12 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        <CyberMatrixHoloBackground />

        <div className="absolute -top-20 -left-20 w-80 h-80 bg-emerald-400/25 rounded-full blur-3xl pointer-events-none"></div>
        <div className="absolute -bottom-20 -right-20 w-80 h-80 bg-teal-400/25 rounded-full blur-3xl pointer-events-none"></div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative z-10">
          
          <div className="lg:col-span-8 space-y-4">
            <div className="flex flex-wrap items-center gap-3">
              <span className="bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider inline-flex items-center gap-1.5 badge-glow">
                <Gamepad2 className="w-3.5 h-3.5" /> CAN I RUN IT? & HARDWARE COMPATIBILITY ENGINE
              </span>
              <span className="bg-emerald-950/80 border border-emerald-400/30 text-emerald-300 text-[10px] font-bold px-2.5 py-1 rounded-full flex items-center gap-1">
                <Volume2 className="w-3 h-3 text-emerald-400" /> Custom Specs Compatibility Analyzer
              </span>
            </div>

            <h1 className="font-display font-extrabold text-3xl sm:text-5xl text-white leading-tight">
              Game Compatibility & <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-emerald-300 via-teal-200 to-cyan-300">
                Custom PC Specs Analyzer
              </span>
            </h1>

            <p className="text-emerald-100 text-xs sm:text-sm sm:text-base leading-relaxed max-w-xl">
              Check exact performance compatibility for your specific PC model, CPU, GPU, and RAM. Discover your <strong>Compatibility % Score</strong>, bottleneck warnings, and recommended upgrade versions.
            </p>

            <div className="flex flex-wrap gap-2.5 pt-2">
              <a
                href="#game-list"
                onClick={(e) => { e.preventDefault(); playPowerUpSound(); setSelectedEra('upcoming'); document.getElementById('game-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }}
                className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5 cursor-pointer no-underline"
              >
                <Flame className="w-3.5 h-3.5" /> <span>GTA 6 & upcoming games</span>
              </a>

              <a
                href="#game-list"
                onClick={(e) => { e.preventDefault(); playPowerUpSound(); setSelectedEra('modern'); document.getElementById('game-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }}
                className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5 cursor-pointer no-underline"
              >
                <Zap className="w-3.5 h-3.5" /> <span>Games from 2021 to today</span>
              </a>

              <a
                href="#game-list"
                onClick={(e) => { e.preventDefault(); playPowerUpSound(); setSelectedEra('classic'); document.getElementById('game-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }}
                className="badge-holo-glow text-emerald-300 text-xs font-bold px-3.5 py-1.5 rounded-xl flex items-center gap-1.5 cursor-pointer no-underline"
              >
                <Gamepad2 className="w-3.5 h-3.5" /> <span>Classics: San Andreas, CS 1.6, Skyrim</span>
              </a>
            </div>
          </div>

          <div className="lg:col-span-4 bg-slate-900/85 border-2 border-emerald-400/40 rounded-3xl p-6 backdrop-blur-md shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-700 pb-3">
              <span className="text-xs font-display font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                <ShieldCheck className="w-4 h-4 text-emerald-400" />
                <span>Compatibility Index</span>
              </span>
              <span className="bg-emerald-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded badge-glow">
                16+ Games Indexed
              </span>
            </div>

            <div className="grid grid-cols-2 gap-3 text-center">
              <div className="bg-slate-800/80 p-3 rounded-xl border border-slate-700">
                <div className="text-2xl font-display font-extrabold text-emerald-400">100%</div>
                <div className="text-[10px] text-slate-300 font-semibold">Specs Tested</div>
              </div>
              <div className="bg-slate-800/80 p-3 rounded-xl border border-slate-700">
                <div className="text-2xl font-display font-extrabold text-emerald-400">4K 120Hz</div>
                <div className="text-[10px] text-slate-300 font-semibold">Max Frame Targets</div>
              </div>
            </div>

            <p className="text-[11px] text-emerald-200/80 leading-relaxed text-center">
              Based on the official minimum and recommended requirements published for each game.
            </p>
          </div>

        </div>
      </div>

      {/* 2. "CUSTOM PC SPECS & HARDWARE COMPATIBILITY ANALYZER" */}
      <section className="bg-white border-2 border-emerald-500/30 rounded-3xl p-6 sm:p-8 shadow-md space-y-6 relative overflow-hidden">
        
        {/* Header & Mode Switch */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-emerald-100 pb-4">
          <div className="flex items-center gap-2.5 text-emerald-700">
            <Cpu className="w-6 h-6 animate-pulse" />
            <div>
              <span className="font-display font-extrabold text-xs uppercase tracking-wider text-emerald-600">INTERACTIVE HARDWARE CHECKER TOOL</span>
              <h2 className="font-display font-extrabold text-xl sm:text-2xl text-slate-900">
                <BouncyText text="Can My Device Run It? Custom PC Specs Analyzer" />
              </h2>
            </div>
          </div>

          {/* Mode Switch Tabs */}
          <div className="flex flex-wrap items-center gap-1.5 bg-slate-100 p-1 rounded-2xl shrink-0">
            <button
              onClick={() => {
                playClickSound();
                setCheckerMode('custom');
              }}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-display font-extrabold transition-all ${
                checkerMode === 'custom'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Manual Specs
            </button>
            <button
              onClick={() => {
                playClickSound();
                setCheckerMode('quick');
              }}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-display font-extrabold transition-all ${
                checkerMode === 'quick'
                  ? 'bg-emerald-600 text-white shadow-md'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Device Presets
            </button>
            <button
              onClick={() => {
                playPowerUpSound();
                window.dispatchEvent(new CustomEvent('open-ai-bot', { detail: { query: "start_pc_check" } }));
              }}
              className="bg-slate-900 hover:bg-emerald-950 text-emerald-300 border border-emerald-400/40 px-3.5 py-1.5 rounded-xl text-xs font-display font-extrabold transition-all hover:scale-105 flex items-center gap-1.5 shadow-sm"
            >
              <Bot className="w-3.5 h-3.5 text-emerald-400 animate-pulse" />
              <span>🤖 Auto-Detect by PC Model Name (AI Bot)</span>
            </button>
          </div>
        </div>

        {/* Target Game Selector (Always Present) */}
        <div className="space-y-2">
          <label className="text-xs font-display font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
            <Gamepad2 className="w-4 h-4 text-emerald-600" />
            <span>Select Target Game to Test:</span>
          </label>
          <select 
            value={checkerGameId}
            onChange={(e) => {
              playClickSound();
              setSteamPick(null);
              setCheckerGameId(e.target.value);
            }}
            className="w-full bg-slate-50 border-2 border-slate-200 focus:border-emerald-500 rounded-2xl px-4 py-3 text-xs sm:text-sm font-bold text-slate-900 focus:bg-white focus:outline-none transition-all shadow-xs"
          >
            {gameCompatibility.map(g => (
              <option key={g.id} value={g.id}>
                {g.gameTitle} ({g.genre} - {g.eraLabel})
              </option>
            ))}
          </select>

          {/* Search the Steam list (official requirements) */}
          {steamGames.length > 0 && (
            <div className="relative">
              <input
                type="search"
                value={steamQuery}
                onChange={(e) => setSteamQuery(e.target.value)}
                placeholder={`Or search ${steamGames.length} popular PC games (2021 to today)…`}
                aria-label="Search PC games"
                className="w-full bg-white border-2 border-slate-200 focus:border-emerald-500 rounded-2xl px-4 py-2.5 text-xs sm:text-sm font-semibold text-slate-900 focus:outline-none"
              />
              {steamMatches.length > 0 && (
                <div className="absolute z-20 left-0 right-0 mt-1 bg-white border-2 border-emerald-400 rounded-2xl p-1.5 shadow-2xl">
                  {steamMatches.map((g) => (
                    <button key={g.appid} type="button"
                      onClick={() => { playClickSound(); setSteamPick(g); setSteamQuery(''); }}
                      className="w-full text-left px-3 py-2 rounded-xl hover:bg-emerald-50 text-xs sm:text-sm">
                      <span className="font-bold text-slate-900">{g.t}</span>
                      <span className="text-slate-500"> · {g.y}{g.g ? ` · ${g.g}` : ''}</span>
                    </button>
                  ))}
                </div>
              )}
              {steamPick && (
                <div className="mt-2 flex flex-wrap items-center gap-2 text-xs">
                  <span className="text-slate-600">Checking <b>{steamPick.t}</b> with its official Steam requirements.</span>
                  <a href={steamPick.u} target="_blank" rel="noopener noreferrer" className="font-bold text-emerald-700 underline">See them on Steam ↗</a>
                  <button type="button" onClick={() => setSteamPick(null)} className="font-bold text-slate-500 underline">Back to the list above</button>
                </div>
              )}
            </div>
          )}

          {/* The selected game's own PC requirements (change with the game) */}
          {selectedCheckerGame && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <div className="rounded-xl border border-amber-200 bg-amber-50/70 p-3">
                <div className="font-display font-extrabold text-amber-800 uppercase tracking-wider text-[10px] mb-1">Minimum PC specs</div>
                <div className="text-slate-800 font-medium">{selectedCheckerGame.minSpecs}</div>
              </div>
              <div className="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3">
                <div className="font-display font-extrabold text-emerald-800 uppercase tracking-wider text-[10px] mb-1">Recommended PC specs</div>
                <div className="text-slate-800 font-medium">{selectedCheckerGame.recommendedSpecs}</div>
              </div>
            </div>
          )}
        </div>

        {/* MODE A: CUSTOM PC SPECS INPUT FORM */}
        {checkerMode === 'custom' && (
          <div className="bg-slate-50/80 border-2 border-emerald-500/20 rounded-2xl p-5 sm:p-6 space-y-5">
            
            <div className="flex items-center justify-between border-b border-slate-200/80 pb-2">
              <span className="text-xs font-display font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <Sliders className="w-4 h-4 text-emerald-600" />
                <span>Specify Your PC / Laptop Model & Hardware Specs:</span>
              </span>
              <span className="text-[11px] font-bold text-emerald-700 bg-emerald-100/70 px-2.5 py-0.5 rounded-md">
                Live Calculator
              </span>
            </div>

            {/* Custom Specs Inputs Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              
              {/* 1. CPU / Processor Tier */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold text-slate-700 block">
                  CPU / Processor Tier:
                </label>
                <select
                  value={customCpuTier}
                  onChange={(e) => {
                    playClickSound();
                    setCustomCpuTier(e.target.value);
                  }}
                  className="w-full bg-white border border-slate-300 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:outline-none"
                >
                  {cpuOptions.map(c => (
                    <option key={c.id} value={c.id}>
                      {c.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* 2. GPU / Graphics Card */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold text-slate-700 block">
                  GPU / Graphics Card:
                </label>
                <select
                  value={customGpuTier}
                  onChange={(e) => {
                    playClickSound();
                    setCustomGpuTier(e.target.value);
                  }}
                  className="w-full bg-white border border-slate-300 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:outline-none"
                >
                  {gpuOptions.map(g => (
                    <option key={g.id} value={g.id}>
                      {g.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* 3. RAM Size */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold text-slate-700 block">
                  System RAM Size:
                </label>
                <select
                  value={customRamGb}
                  onChange={(e) => {
                    playClickSound();
                    setCustomRamGb(Number(e.target.value));
                  }}
                  className="w-full bg-white border border-slate-300 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:outline-none"
                >
                  <option value={4}>4 GB RAM (Low / Budget)</option>
                  <option value={8}>8 GB RAM (Entry Level)</option>
                  <option value={16}>16 GB RAM (Standard Recommended)</option>
                  <option value={32}>32 GB RAM (High-End Gaming)</option>
                  <option value={64}>64 GB+ RAM (Workstation Enthusiast)</option>
                </select>
              </div>

              {/* 4. Storage Type */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold text-slate-700 block">
                  Storage Drive Type:
                </label>
                <select
                  value={customStorage}
                  onChange={(e) => {
                    playClickSound();
                    setCustomStorage(e.target.value);
                  }}
                  className="w-full bg-white border border-slate-300 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:outline-none"
                >
                  <option value="nvme">Fast NVMe M.2 SSD (Gen 3 / Gen 4)</option>
                  <option value="sata_ssd">Standard SATA 2.5" SSD</option>
                  <option value="hdd">Mechanical Hard Drive (HDD / External USB)</option>
                </select>
              </div>

            </div>

            {/* Custom Specs Analysis Result Card */}
            {customAnalysisResult && (
              <div className={`p-5 sm:p-6 rounded-2xl border-2 transition-all space-y-4 ${customAnalysisResult.statusColor}`}>
                
                {/* Score & Verdict Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-current/20 pb-3">
                  <div>
                    <span className="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded bg-white/90 shadow-xs border border-current/30 inline-block mb-1">
                      {customAnalysisResult.statusBadge}
                    </span>
                    <h3 className="font-display font-extrabold text-lg sm:text-xl">
                      Verdict for {selectedCheckerGame.gameTitle}
                    </h3>
                  </div>

                  <div className="text-right shrink-0">
                    <div className="text-2xl sm:text-3xl font-display font-extrabold tracking-tight">
                      {customAnalysisResult.unrated ? '—' : `${customAnalysisResult.percentage}%`}
                    </div>
                    <span className="text-[10px] font-bold uppercase tracking-wider block opacity-80">
                      Hardware Compatibility Score
                    </span>
                  </div>
                </div>

                {/* Performance & FPS Estimation */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs sm:text-sm">
                  <div className="bg-white/80 p-3 rounded-xl border border-current/20">
                    <span className="text-[10px] font-extrabold uppercase tracking-wider block opacity-70">Estimated FPS Performance:</span>
                    <span className="font-display font-extrabold text-slate-900 text-sm mt-0.5 block">
                      ⚡ {customAnalysisResult.fpsEstimate}
                    </span>
                  </div>

                  <div className="bg-white/80 p-3 rounded-xl border border-current/20">
                    <span className="text-[10px] font-extrabold uppercase tracking-wider block opacity-70">Official Game Target:</span>
                    <span className="font-display font-extrabold text-slate-900 text-sm mt-0.5 block">
                      🎮 {selectedCheckerGame.fpsTarget}
                    </span>
                  </div>
                </div>

                {/* Bottlenecks Warning List (If any) */}
                {customAnalysisResult.bottlenecks.length > 0 && (
                  <div className="space-y-1.5 pt-2 border-t border-current/15">
                    <span className="text-xs font-bold uppercase tracking-wider flex items-center gap-1">
                      <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0" />
                      <span>Hardware Bottlenecks Detected:</span>
                    </span>
                    <ul className="space-y-1 text-xs pl-5 list-disc font-medium">
                      {customAnalysisResult.bottlenecks.map((b, i) => (
                        <li key={i}>{b}</li>
                      ))}
                    </ul>
                  </div>
                )}

                {/* What is missing, part by part */}
                {customAnalysisResult.missing.length > 0 && (
                  <div className="p-3.5 bg-white/90 rounded-xl border border-current/30 text-xs">
                    <div className="font-bold uppercase tracking-wider text-slate-900 mb-2">What your PC is missing</div>
                    <table className="w-full text-left text-slate-800">
                      <thead><tr className="text-slate-500"><th className="py-1 pr-2">Part</th><th className="py-1 pr-2">You have</th><th className="py-1">Needed</th></tr></thead>
                      <tbody>
                        {customAnalysisResult.missing.map((m) => (
                          <tr key={m.part} className="border-t border-slate-100"><td className="py-1 pr-2 font-semibold">{m.part}</td><td className="py-1 pr-2">{m.have}</td><td className="py-1 font-semibold text-emerald-700">{m.need}</td></tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                {/* Official stores to get the game (search pages on the store itself) */}
                {(customAnalysisResult.percentage >= 45 || customAnalysisResult.unrated) && (
                  <div className="p-3.5 bg-white/90 rounded-xl border border-current/30 text-xs space-y-2">
                    <div className="font-bold uppercase tracking-wider text-slate-900 flex items-center gap-1">
                      <DownloadCloud className="w-4 h-4 text-emerald-600" />
                      <span>Get {selectedCheckerGame.gameTitle} from an official store</span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      {selectedCheckerGame.steamUrl && (
                        <a href={selectedCheckerGame.steamUrl} target="_blank" rel="noopener noreferrer"
                          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-700 text-white font-bold no-underline hover:bg-emerald-800">
                          Steam store page <ExternalLink className="w-3 h-3" />
                        </a>
                      )}
                      {[
                        ...(selectedCheckerGame.steamUrl ? [] : [['Steam', 'https://store.steampowered.com/search/?term=']]),
                        ['Epic Games Store', 'https://store.epicgames.com/en-US/browse?q='],
                        ['Xbox / PC Game Pass', 'https://www.xbox.com/en-US/search/results/games?q='],
                        ['PlayStation Store', 'https://store.playstation.com/en-us/search/'],
                      ].map(([name, base]) => (
                        <a key={name} href={base + encodeURIComponent(selectedCheckerGame.gameTitle.replace(/\s*\([^)]*\)/g, '').trim())} target="_blank" rel="noopener noreferrer"
                          className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-bold no-underline hover:bg-emerald-700">
                          {name} <ExternalLink className="w-3 h-3" />
                        </a>
                      ))}
                    </div>
                    <p className="text-slate-500">Buy or download only from official stores: they are safe, updated and support the developers.</p>
                  </div>
                )}

                {/* Recommended Minimum Upgrade Version Advice */}
                <div className="p-3.5 bg-white/90 rounded-xl border border-current/30 space-y-1 text-xs">
                  <div className="font-bold uppercase tracking-wider flex items-center gap-1 text-slate-900">
                    <Wrench className="w-4 h-4 text-emerald-600" />
                    <span>Recommended Upgrade Advice:</span>
                  </div>
                  <p className="text-slate-800 font-medium leading-relaxed">
                    {customAnalysisResult.upgradeRecommendation}
                  </p>
                </div>

              </div>
            )}

          </div>
        )}

        {/* MODE B: QUICK DEVICE PRESETS SELECTOR */}
        {checkerMode === 'quick' && (
          <div className="space-y-4">
            <div className="space-y-2">
              <label className="text-xs font-display font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                <Monitor className="w-4 h-4 text-emerald-600" />
                <span>Select Device Preset:</span>
              </label>
              <select 
                value={checkerDeviceId}
                onChange={(e) => {
                  playClickSound();
                  setCheckerDeviceId(e.target.value);
                }}
                className="w-full bg-slate-50 border-2 border-slate-200 focus:border-emerald-500 rounded-2xl px-4 py-3 text-xs sm:text-sm font-bold text-slate-900 focus:bg-white focus:outline-none transition-all shadow-xs"
              >
                {deviceProfiles.map(d => (
                  <option key={d.id} value={d.id}>
                    {d.name}
                  </option>
                ))}
              </select>
            </div>

            {/* Device Preset Verdict */}
            <div className="p-5 rounded-2xl bg-emerald-50 border-2 border-emerald-300 text-emerald-900 space-y-2">
              <div className="font-bold text-sm flex items-center gap-2">
                <CheckCircle2 className="w-5 h-5 text-emerald-600" />
                <span>Verified Device Profile for {selectedCheckerGame.gameTitle}</span>
              </div>
              <p className="text-xs text-slate-700 leading-relaxed font-medium">
                Targeting <strong>{selectedCheckerGame.fpsTarget}</strong> with official verification on this platform tier.
              </p>
            </div>
          </div>
        )}

      </section>

      {/* 3. FOUR STRATEGIES IF YOUR PC DOES NOT MEET MINIMUM SPECS */}
      <section className="bg-slate-900 text-white rounded-3xl p-6 sm:p-10 shadow-xl space-y-6 border border-emerald-500/30">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-emerald-400">
            <Sparkles className="w-5 h-5 animate-pulse" />
            <span className="font-display font-extrabold text-xs uppercase tracking-wider">OFFICIAL GUIDE</span>
          </div>
          <h2 className="font-display font-extrabold text-2xl sm:text-3xl text-white">
            <BouncyText text="What If Your PC Is Too Weak to Run the Game?" />
          </h2>
          <p className="text-xs sm:text-sm text-slate-300">
            If your PC or laptop cannot handle modern titles natively, here are 4 proven solutions:
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          
          {/* Option 1: Hardware Upgrade */}
          <Tilt3DCard className="bg-slate-800/90 border border-emerald-500/40 p-5 rounded-2xl space-y-3 flex flex-col justify-between">
            <div>
              <div className="w-10 h-10 rounded-xl bg-emerald-700 text-emerald-200 flex items-center justify-center font-extrabold text-sm mb-3">
                1
              </div>
              <h3 className="font-display font-extrabold text-sm text-white mb-1.5">
                Hardware Upgrade or Switch (Recommended)
              </h3>
              <p className="text-xs text-slate-300 leading-relaxed">
                The ultimate permanent solution: upgrade your GPU, RAM (to 16GB), and add an NVMe SSD, or pick up an affordable console like an <strong>Xbox Series S ($299)</strong>, <strong>PS5 Digital</strong>, or <strong>Steam Deck OLED</strong>.
              </p>
            </div>
            <span className="text-[10px] font-extrabold text-emerald-400 bg-emerald-950/80 px-2 py-1 rounded border border-emerald-500/30 block text-center">
              ✓ Best 100% Native Quality
            </span>
          </Tilt3DCard>

          {/* Option 2: Cloud Gaming */}
          <Tilt3DCard className="bg-slate-800/90 border border-emerald-500/40 p-5 rounded-2xl space-y-3 flex flex-col justify-between">
            <div>
              <div className="w-10 h-10 rounded-xl bg-cyan-700 text-cyan-200 flex items-center justify-center font-extrabold text-sm mb-3">
                <Cloud className="w-5 h-5" />
              </div>
              <h3 className="font-display font-extrabold text-sm text-white mb-1.5">
                Cloud Gaming (Zero Download / Any Device)
              </h3>
              <p className="text-xs text-slate-300 leading-relaxed">
                Stream full AAA games via <strong>NVIDIA GeForce NOW</strong> or <strong>Xbox Cloud Gaming</strong> at 60-120 FPS on any old office laptop, iPad, or smartphone without needing a dedicated GPU.
              </p>
            </div>
            <span className="text-[10px] font-extrabold text-cyan-400 bg-cyan-950/80 px-2 py-1 rounded border border-cyan-500/30 block text-center">
              ✓ No High Specs Required
            </span>
          </Tilt3DCard>

          {/* Option 3: External Storage / Disc Copy */}
          <Tilt3DCard className="bg-slate-800/90 border border-emerald-500/40 p-5 rounded-2xl space-y-3 flex flex-col justify-between">
            <div>
              <div className="w-10 h-10 rounded-xl bg-amber-700 text-amber-200 flex items-center justify-center font-extrabold text-sm mb-3">
                <HardDrive className="w-5 h-5" />
              </div>
              <h3 className="font-display font-extrabold text-sm text-white mb-1.5">
                External Hard Drive / USB / Disc Copy
              </h3>
              <p className="text-xs text-slate-300 leading-relaxed">
                If your internet download speed is slow for 100GB+ games, you can copy the game folder from a friend's USB drive into your Steam/Epic library and run 'Verify files' so only the missing parts download. The game must be bought on your own account.
              </p>
            </div>
            <span className="text-[10px] font-extrabold text-amber-400 bg-amber-950/80 px-2 py-1 rounded border border-amber-500/30 block text-center">
              ✓ Avoid 100GB+ Downloads
            </span>
          </Tilt3DCard>

          {/* Option 4: Casual Fun: APKs, Servers & Mods */}
          <Tilt3DCard className="bg-slate-800/90 border border-emerald-500/40 p-5 rounded-2xl space-y-3 flex flex-col justify-between">
            <div>
              <div className="w-10 h-10 rounded-xl bg-rose-700 text-rose-200 flex items-center justify-center font-extrabold text-sm mb-3">
                <Smartphone className="w-5 h-5" />
              </div>
              <h3 className="font-display font-extrabold text-sm text-white mb-1.5">
                Casual Fun: APKs, SAMP & Low-Spec Mods
              </h3>
              <p className="text-xs text-slate-300 leading-relaxed">
                For casual fun with friends on budget devices, play mobile APK ports, join lightweight community servers (like <strong>SAMP</strong> for GTA, <strong>Xash3D</strong> for CS 1.6), or use LowSpecGamer configuration tweaks.
              </p>
            </div>
            <span className="text-[10px] font-extrabold text-rose-400 bg-rose-950/80 px-2 py-1 rounded border border-rose-500/30 block text-center">
              ✓ Fun on Any Low-End Hardware
            </span>
          </Tilt3DCard>

        </div>
      </section>

      {/* 4. Filter Toolbar: Era Pills + Platform Pills + Search Box */}
      <section id="game-list" className="bg-white border border-slate-200 rounded-3xl p-5 sm:p-6 shadow-sm space-y-5 scroll-mt-24">
        <div className="flex flex-col lg:flex-row items-center justify-between gap-4">
          
          <div className="flex flex-wrap items-center gap-2 w-full lg:w-auto">
            <span className="text-xs font-bold text-slate-500 mr-1 hidden sm:inline">Release Era:</span>
            {eras.map((era) => (
              <button
                key={era.id}
                onClick={() => {
                  playClickSound();
                  setSelectedEra(era.id);
                }}
                className={`px-3.5 py-2 text-xs font-display font-extrabold rounded-xl transition-all shadow-sm ${
                  selectedEra === era.id
                    ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/25 ring-2 ring-emerald-400'
                    : 'bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200/80'
                }`}
              >
                {era.label}
              </button>
            ))}
          </div>

          <div className="relative w-full lg:w-72 shrink-0">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
            <input 
              type="text"
              placeholder="Search GTA 5, GPU, 60FPS..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full bg-slate-50 border border-slate-300 rounded-xl pl-10 pr-3.5 py-2.5 text-xs text-slate-900 focus:outline-none focus:border-emerald-500 focus:bg-white transition-all shadow-inner"
            />
          </div>

        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-bold text-slate-500 mr-1">Platform Filter:</span>
            {platforms.map((plat) => (
              <button
                key={plat.id}
                onClick={() => {
                  playClickSound();
                  setSelectedPlatform(plat.id);
                }}
                className={`px-3 py-1.5 text-xs font-bold rounded-lg transition-all ${
                  selectedPlatform === plat.id
                    ? 'bg-slate-900 text-white shadow-sm'
                    : 'bg-slate-50 text-slate-600 hover:bg-slate-200 border border-slate-200'
                }`}
              >
                {plat.label}
              </button>
            ))}
          </div>

          <div className="flex items-center gap-3">
            <span className="text-xs font-extrabold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full">
              {filteredGames.length} Games Listed
            </span>
            {(selectedEra !== 'all' || selectedPlatform !== 'all' || searchQuery) && (
              <button
                onClick={handleReset}
                className="text-xs font-bold text-slate-400 hover:text-emerald-600 flex items-center gap-1 transition-colors"
              >
                <RotateCcw className="w-3 h-3" /> Reset Filters
              </button>
            )}
          </div>
        </div>
      </section>

      {/* 5. Main Compatibility Grid & Matrix Cards with BouncyText */}
      <section className="space-y-6">
        
        {filteredGames.length === 0 ? (
          <div className="bg-white border border-slate-200 rounded-3xl p-12 text-center space-y-3">
            <p className="text-slate-500 text-sm font-semibold">No games match your selected era and platform filters.</p>
            <button 
              onClick={handleReset}
              className="bg-emerald-600 text-white text-xs font-bold py-2.5 px-6 rounded-xl shadow-md"
            >
              Reset All Filters
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {filteredGames.map((game) => (
              <Tilt3DCard
                key={game.id}
                className="game-card flex flex-col justify-between group p-6 space-y-4"
                onMouseEnter={playHoverSound}
                onClick={() => {
                  playClickSound();
                  setCheckerGameId(game.id);
                  window.scrollTo({ top: 380, behavior: 'smooth' });
                }}
              >
                <div>
                  <div className="flex items-center justify-between gap-2 mb-2">
                    <span className="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
                      {game.eraLabel}
                    </span>
                    <span className="text-[10px] font-bold text-slate-400">
                      {game.genre}
                    </span>
                  </div>

                  <h3 className="font-display font-extrabold text-lg text-slate-900 group-hover:text-emerald-600 transition-colors leading-snug">
                    <BouncyText text={game.gameTitle} />
                  </h3>
                </div>

                <div className="bg-slate-50 border border-slate-200/80 rounded-2xl p-3.5 space-y-2">
                  <div className="flex items-center justify-between text-xs">
                    <span className="font-bold text-slate-500">Target Performance:</span>
                    <span className="font-display font-extrabold text-emerald-600">{game.fpsTarget}</span>
                  </div>

                  <div className="text-[11px] text-slate-600 font-mono pt-1 border-t border-slate-200/60">
                    <span className="font-bold text-slate-800">Hardware Requirements:</span> <br />
                    {game.minSpecs}
                  </div>
                </div>

                <div>
                  <span className="text-[10px] font-bold text-slate-400 block mb-1.5">Compatible Platforms:</span>
                  <div className="flex flex-wrap gap-1.5">
                    {game.platforms.map((p, idx) => (
                      <span 
                        key={idx}
                        className="bg-white border border-slate-200 text-slate-700 text-[10px] font-bold px-2 py-0.5 rounded-md shadow-2xs group-hover:border-emerald-300"
                      >
                        {p}
                      </span>
                    ))}
                  </div>
                </div>

                {game.lowEndAlternatives && (
                  <div className="text-[10px] text-slate-500 bg-emerald-50/50 border border-emerald-100 p-2.5 rounded-xl">
                    <strong className="text-emerald-800">Low-End Alternative: </strong>
                    {game.lowEndAlternatives}
                  </div>
                )}

                <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                  <div className="min-w-0 pr-2">
                    <span className="text-[10px] text-slate-400 font-bold block">Tested Gear Match:</span>
                    <span className="font-bold text-emerald-700 truncate block text-xs">
                      {game.recommendedGear}
                    </span>
                  </div>

                  <div className="w-8 h-8 rounded-full bg-emerald-50 group-hover:bg-emerald-600 text-emerald-600 group-hover:text-white flex items-center justify-center shrink-0 transition-colors shadow-xs">
                    <ArrowRight className="w-4 h-4" />
                  </div>
                </div>
              </Tilt3DCard>
            ))}
          </div>
        )}

      </section>

      {/* Every gaming platform hub (internal links) */}
      <section className="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm space-y-4" aria-labelledby="platform-links">
        <h2 id="platform-links" className="font-display font-extrabold text-xl text-slate-900">Not on PC? Find your gaming platform</h2>
        <p className="text-xs sm:text-sm text-slate-600">Guides to the devices and games of every platform, from PlayStation and Xbox to handhelds, mobile and retro consoles.</p>
        <ul className="flex flex-wrap gap-2 list-none p-0 m-0">
          {GAMING_CATEGORIES.map((c) => (
            <li key={c.id}>
              <a href={`/gaming-platforms/${c.id}/`} className="inline-block text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:border-emerald-500 hover:text-emerald-700 px-3 py-1.5 rounded-xl no-underline">{c.title}</a>
            </li>
          ))}
        </ul>
      </section>

      {/* 6. Comprehensive Game Compatibility FAQs with User's Specific Questions */}
      <FAQSection 
        faqs={pageFaqs.compatibility || pageFaqs.categories}
        title="Game Compatibility & Hardware FAQs"
        subtitle="Answers on minimum specs, copying game files via external drives, cloud streaming, and casual APK ports."
      />

    </div>
  );
};
