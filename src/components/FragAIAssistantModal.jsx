import React, { useState, useEffect, useRef } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Bot, MessageSquare, X, Send, Sparkles, Cpu, Monitor, Laptop, 
  Gamepad2, CheckCircle2, AlertTriangle, HelpCircle, HardDrive, 
  Cloud, Smartphone, ArrowRight, RefreshCw, Volume2, ShieldCheck,
  ChevronDown, ExternalLink, ThumbsUp, Wrench, AlertCircle, Scan
} from 'lucide-react';
import { playClickSound, playPowerUpSound, playHoverSound } from '../utils/audioEffects';

// Known popular laptop & device database
const KNOWN_DEVICE_DATABASE = [
  {
    keywords: ['hp 650', 'hp 650 g4', 'probook 650', 'hp probook 650 g4', '650 g4'],
    name: 'HP ProBook 650 G4 (Business & General Laptop)',
    image: '/images/gaming_laptop.jpg',
    cpu: 'Intel Core i5-8250U / i5-8350U vPro (8th Gen Quad-Core up to 3.60GHz)',
    gpu: 'Intel UHD Graphics 620 (Integrated Shared VRAM)',
    ram: '8GB DDR4 (Expandable to 32GB)',
    storage: '256GB / 512GB M.2 NVMe SSD',
    display: '15.6" Full HD (1920x1080) 60Hz Anti-Glare',
    tier: 'budget_integrated',
    powerScore: 32
  },
  {
    keywords: ['hp elitebook 840', 'elitebook 840 g5', '840 g5', '840 g6', 'elitebook 840'],
    name: 'HP EliteBook 840 G5 / G6',
    image: '/images/gaming_laptop.jpg',
    cpu: 'Intel Core i5-8365U vPro / i7-8665U (8th Gen)',
    gpu: 'Intel UHD Graphics 620',
    ram: '8GB / 16GB DDR4',
    storage: '256GB / 512GB NVMe SSD',
    display: '14.0" Full HD IPS',
    tier: 'budget_integrated',
    powerScore: 34
  },
  {
    keywords: ['dell inspiron 15', 'inspiron 3520', 'dell inspiron', 'inspiron 15'],
    name: 'Dell Inspiron 15 3520 / 3511',
    image: '/images/gaming_laptop.jpg',
    cpu: 'Intel Core i5-1135G7 / i5-1235U (11th/12th Gen)',
    gpu: 'Intel Iris Xe Graphics (Shared VRAM)',
    ram: '8GB / 16GB DDR4',
    storage: '512GB NVMe SSD',
    display: '15.6" 120Hz FHD WVA Display',
    tier: 'budget_integrated',
    powerScore: 48
  },
  {
    keywords: ['lenovo thinkpad t480', 'thinkpad t480', 't480', 't490', 'thinkpad'],
    name: 'Lenovo ThinkPad T480 / T490',
    image: '/images/gaming_laptop.jpg',
    cpu: 'Intel Core i5-8250U / i7-8550U Quad-Core',
    gpu: 'Intel UHD 620 (Optional MX150 2GB)',
    ram: '8GB / 16GB Dual-Channel DDR4',
    storage: '256GB / 512GB NVMe SSD',
    display: '14.0" FHD IPS Matte',
    tier: 'budget_integrated',
    powerScore: 35
  },
  {
    keywords: ['asus tuf', 'tuf gaming', 'tuf a15', 'tuf f15'],
    name: 'ASUS TUF Gaming A15 / F15',
    image: '/images/gaming_laptop.jpg',
    cpu: 'AMD Ryzen 7 7735HS / Intel Core i7-13620H',
    gpu: 'NVIDIA GeForce RTX 4060 8GB GDDR6 (140W TGP)',
    ram: '16GB DDR5 4800MHz',
    storage: '1TB PCIe 4.0 NVMe SSD',
    display: '15.6" 144Hz Full HD Adaptive-Sync',
    tier: 'high_dedicated',
    powerScore: 88
  },
  {
    keywords: ['acer nitro 5', 'nitro 5', 'acer nitro'],
    name: 'Acer Nitro 5 Gaming Laptop',
    image: '/images/gaming_laptop.jpg',
    cpu: 'Intel Core i5-12500H / AMD Ryzen 5 5600H',
    gpu: 'NVIDIA GeForce RTX 3050 / RTX 3060 6GB GDDR6',
    ram: '16GB DDR4 3200MHz',
    storage: '512GB NVMe SSD',
    display: '15.6" 144Hz IPS FHD',
    tier: 'mid_dedicated',
    powerScore: 74
  },
  {
    keywords: ['steam deck', 'steam deck oled', 'valve steam deck'],
    name: 'Valve Steam Deck OLED / LCD',
    image: '/images/handheld_console.jpg',
    cpu: 'Custom AMD Zen 2 (4-Core / 8-Thread up to 3.5GHz)',
    gpu: 'AMD RDNA 2 (8 CUs @ 1.6GHz 1.6 TFlops)',
    ram: '16GB LPDDR5 Unified Memory',
    storage: '512GB / 1TB High-Speed NVMe SSD',
    display: '7.4" 90Hz HDR OLED (1280x800)',
    tier: 'handheld_apu',
    powerScore: 65
  },
  {
    keywords: ['ps5', 'playstation 5', 'ps5 pro'],
    name: 'Sony PlayStation 5 / PS5 Pro',
    image: '/images/trending_spiderman.jpg',
    cpu: 'AMD Zen 2 8-Core 3.5GHz',
    gpu: 'Custom RDNA 2 10.28 TFlops (PS5 Pro 16.7 TFlops PSSR)',
    ram: '16GB GDDR6 Unified RAM',
    storage: '825GB / 1TB / 2TB Ultra-High Speed Custom SSD',
    display: 'HDMI 2.1 4K 120Hz HDR Output',
    tier: 'console',
    powerScore: 92
  }
];

export const FragAIAssistantModal = () => {
  const { 
    currentUser, 
    gameCompatibility = [], 
    navigateTo, 
    navigateToCategory, 
    navigateToProduct 
  } = useApp();

  const [isOpen, setIsOpen] = useState(false);
  const [inputText, setInputText] = useState('');
  const [isTyping, setIsTyping] = useState(false);

  // Dynamic Conversation State: 'idle' | 'awaiting_device' | 'awaiting_game' | 'completed'
  const [chatStep, setChatStep] = useState('idle');
  const [activeDevice, setActiveDevice] = useState(null);

  const [messages, setMessages] = useState([
    {
      sender: 'bot',
      text: "Assalam-o-Alaikum & Welcome Gamer! 🎮 Main **ROC AI** hoon — Run On Console ka official Hardware & Game Compatibility Assistant.\n\nMain aapko kisi bhi laptop ya PC ke specs check kar ke bata sakta hoon ke aapki manpasand game (GTA 5, GTA 6, BO6, Cyberpunk) us par chalegi ya nahi.",
      quickActions: [
        { label: "🔍 Check My PC Specs & Game", action: "start_pc_check" },
        { label: "⚡ 1-Click Browser Auto-Scan", action: "scan_browser" },
        { label: "🕹️ Browse 15 Gaming Platforms", action: "browse_platforms" },
        { label: "☁️ Cloud Gaming Guide", action: "cloud_guide" }
      ]
    }
  ]);

  const chatEndRef = useRef(null);

  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping]);

  useEffect(() => {
    const handleOpenBot = (e) => {
      setIsOpen(true);
      playPowerUpSound();
      if (e.detail?.query) {
        setTimeout(() => {
          handleUserSend(e.detail.query);
        }, 300);
      }
    };
    window.addEventListener('open-ai-bot', handleOpenBot);
    return () => window.removeEventListener('open-ai-bot', handleOpenBot);
  }, []);

  // WebGL 1-Click Hardware Scanner
  const handleBrowserAutoScan = () => {
    playPowerUpSound();
    setIsTyping(true);

    let detectedGpu = "Integrated Graphics";
    let cores = navigator.hardwareConcurrency || 4;
    let ramEst = navigator.deviceMemory || 8;

    try {
      const canvas = document.createElement('canvas');
      const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
      if (gl) {
        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        if (debugInfo) {
          const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
          detectedGpu = renderer.replace(/ANGLE \(|, Direct3D.*|\)/g, '').trim();
        }
      }
    } catch (err) {}

    const scannedDev = {
      name: "Your Current Scanned Device",
      image: '/images/gaming_laptop.jpg',
      cpu: `${cores}-Core Hardware Processor (Detected)`,
      gpu: detectedGpu,
      ram: `~${ramEst}GB Detected System Memory`,
      storage: 'Fast SSD Storage',
      display: `${window.screen.width}x${window.screen.height} Display`,
      tier: detectedGpu.toLowerCase().includes('rtx') ? 'high_dedicated' : detectedGpu.toLowerCase().includes('gtx') ? 'mid_dedicated' : 'budget_integrated',
      powerScore: detectedGpu.toLowerCase().includes('rtx') ? 85 : detectedGpu.toLowerCase().includes('gtx') ? 65 : 35
    };

    setActiveDevice(scannedDev);
    setChatStep('awaiting_game');

    setTimeout(() => {
      setIsTyping(false);
      setMessages(prev => [
        ...prev,
        {
          sender: 'bot',
          deviceData: scannedDev,
          text: `⚡ **Live Hardware Scan Completed!**\n\n` +
                `🔹 **GPU:** ${scannedDev.gpu}\n` +
                `🔹 **CPU Cores:** ${scannedDev.cpu}\n` +
                `🔹 **RAM:** ${scannedDev.ram}\n\n` +
                `Ab batayein aap is hardware par **kaun si game** check karna chahte hain?`,
          quickActions: [
            { label: "Grand Theft Auto V (GTA 5)", action: "game_gta5" },
            { label: "Grand Theft Auto VI (GTA 6)", action: "game_gta6" },
            { label: "Cyberpunk 2077", action: "game_cyberpunk" },
            { label: "Call of Duty: BO6", action: "game_bo6" },
            { label: "Valorant & CS2", action: "game_valorant" },
            { label: "Minecraft", action: "game_minecraft" }
          ]
        }
      ]);
    }, 700);
  };

  // Main User Send Processor
  const handleUserSend = (textToSend = null) => {
    const query = (textToSend || inputText).trim();
    if (!query) return;

    playClickSound();

    const newMessages = [...messages, { sender: 'user', text: query }];
    setMessages(newMessages);
    setInputText('');
    setIsTyping(true);

    setTimeout(() => {
      processBotResponse(query, newMessages);
    }, 600);
  };

  const processBotResponse = (query, currentMessages) => {
    const qLower = query.toLowerCase();

    // 1. Navigation / General Intents
    if (qLower === 'browse_platforms' || qLower.includes('platform list')) {
      setIsTyping(false);
      setMessages([
        ...currentMessages,
        {
          sender: 'bot',
          text: "Humari website par 15 verified Gaming Platforms aur 100+ hardware devices listed hain: PC/Handhelds, PS5, Xbox, Nintendo Switch, PSP/Vita, Sega Retro, Atari, Anbernic, VR, aur Cloud!\n\nAap Categories page par ja kar kisi bhi system ke full teardown specs dekh sakte hain.",
          quickActions: [
            { label: "Open 15 Platforms Hub", action: "nav_categories" },
            { label: "Check My PC Compatibility", action: "start_pc_check" }
          ]
        }
      ]);
      return;
    }

    if (qLower === 'cloud_guide' || qLower.includes('cloud') || qLower.includes('jugaar')) {
      setIsTyping(false);
      setMessages([
        ...currentMessages,
        {
          sender: 'bot',
          text: "💡 **Cloud Gaming Shortcut (Zero Download & High FPS):**\n\nAgar aapka laptop/PC purana hai to aap **NVIDIA GeForce NOW** ya **Xbox Cloud Gaming** use kar sakte hain. Isme game aapke browser/app me 60-120 FPS par stream hoti hai bina heavy graphics card ke!",
          quickActions: [
            { label: "Test My Laptop Model", action: "start_pc_check" },
            { label: "Open Compatibility Page", action: "nav_compatibility" }
          ]
        }
      ]);
      return;
    }

    // 2. Start PC Check Step: Ask for Device Model First (No hardcoded assumption)
    if (qLower === 'start_pc_check' || qLower === 'check_specs') {
      setChatStep('awaiting_device');
      setIsTyping(false);
      setMessages([
        ...currentMessages,
        {
          sender: 'bot',
          text: "Zabardast! Sabse pehle mujhe apne **Laptop ya PC ka model naam** batayein.\n\n*(Misal ke tor par: 'HP 650 G4', 'Dell Inspiron 15', 'Lenovo ThinkPad', 'Core i5 10th gen with GTX 1650', 'MacBook Air M2', ya 'ASUS TUF Gaming')*",
          quickActions: [
            { label: "HP ProBook 650 G4", action: "dev_hp650" },
            { label: "Dell Inspiron 15", action: "dev_dell" },
            { label: "Lenovo ThinkPad T480", action: "dev_lenovo" },
            { label: "ASUS TUF RTX 4060", action: "dev_tuf" },
            { label: "⚡ 1-Click Auto Scan My Screen", action: "scan_browser" }
          ]
        }
      ]);
      return;
    }

    // 3. Match / Parse Device Model from Input
    let matchedDevice = null;
    for (const dev of KNOWN_DEVICE_DATABASE) {
      if (dev.keywords.some(kw => qLower.includes(kw))) {
        matchedDevice = dev;
        break;
      }
    }

    // Fallback: Dynamic NLP parser for custom specs typed by user
    if (!matchedDevice && (qLower.includes('core i') || qLower.includes('ryzen') || qLower.includes('rtx') || qLower.includes('gtx') || qLower.includes('hp') || qLower.includes('dell') || qLower.includes('laptop') || qLower.includes('pc') || qLower.includes('intel') || qLower.includes('amd'))) {
      let detectedCpu = "Intel Core i5 (General Quad-Core)";
      let detectedGpu = "Integrated Intel UHD / Iris Graphics";
      let power = 40;

      if (qLower.includes('i7') || qLower.includes('ryzen 7')) { detectedCpu = "Intel Core i7 / AMD Ryzen 7 Processor"; power += 20; }
      if (qLower.includes('i9') || qLower.includes('ryzen 9') || qLower.includes('7800x3d')) { detectedCpu = "High-End Enthusiast CPU (Core i9 / Ryzen 9)"; power += 35; }
      if (qLower.includes('i3') || qLower.includes('core 2') || qLower.includes('pentium')) { detectedCpu = "Budget Entry Dual/Quad Core CPU"; power -= 15; }

      if (qLower.includes('rtx 40') || qLower.includes('rtx 3080') || qLower.includes('rx 7900')) { detectedGpu = "NVIDIA RTX High-End Dedicated GPU"; power += 45; }
      else if (qLower.includes('rtx 3060') || qLower.includes('rtx 4060') || qLower.includes('rx 6600')) { detectedGpu = "NVIDIA RTX 3060 / 4060 Dedicated GPU"; power += 35; }
      else if (qLower.includes('gtx 1650') || qLower.includes('gtx 1060') || qLower.includes('rx 580')) { detectedGpu = "Entry Dedicated GPU (GTX 1650 / 1060)"; power += 20; }

      matchedDevice = {
        name: query.length < 40 ? query.toUpperCase() : "Custom Detected PC / Laptop",
        image: '/images/gaming_laptop.jpg',
        cpu: detectedCpu,
        gpu: detectedGpu,
        ram: qLower.includes('16gb') ? '16GB DDR4' : qLower.includes('32gb') ? '32GB DDR5' : '8GB DDR4',
        storage: '256GB / 512GB Fast SSD',
        display: '1080p Full HD Display',
        tier: power > 70 ? 'high_dedicated' : power > 50 ? 'mid_dedicated' : 'budget_integrated',
        powerScore: power
      };
    }

    // If device was recognized (or already stored in memory)
    const currentDev = matchedDevice || activeDevice;

    // Check if user also provided a game title in query
    let matchedGame = gameCompatibility.find(g => 
      qLower.includes(g.gameTitle.toLowerCase()) || 
      (g.gameTitle.toLowerCase().includes('gta 5') && (qLower.includes('gta 5') || qLower.includes('gta v') || qLower.includes('gta5'))) ||
      (g.gameTitle.toLowerCase().includes('gta 6') && (qLower.includes('gta 6') || qLower.includes('gta vi'))) ||
      (g.gameTitle.toLowerCase().includes('cyberpunk') && qLower.includes('cyberpunk')) ||
      (g.gameTitle.toLowerCase().includes('black ops') && (qLower.includes('cod') || qLower.includes('black ops') || qLower.includes('bo6'))) ||
      (g.gameTitle.toLowerCase().includes('san andreas') && (qLower.includes('san andreas') || qLower.includes('samp'))) ||
      (g.gameTitle.toLowerCase().includes('elden ring') && qLower.includes('elden')) ||
      (g.gameTitle.toLowerCase().includes('valorant') && (qLower.includes('valorant') || qLower.includes('cs2') || qLower.includes('cs 1.6'))) ||
      (g.gameTitle.toLowerCase().includes('minecraft') && qLower.includes('minecraft'))
    );

    // Case A: Device found, but no game mentioned yet -> Ask for Game
    if (currentDev && !matchedGame && chatStep !== 'completed') {
      setActiveDevice(currentDev);
      setChatStep('awaiting_game');
      setIsTyping(false);
      setMessages([
        ...currentMessages,
        {
          sender: 'bot',
          deviceData: currentDev,
          text: `Ji haan! Maine aapke **${currentDev.name}** ke specifications fetch kar liye hain:\n\n` +
                `🔹 **CPU:** ${currentDev.cpu}\n` +
                `🔹 **GPU:** ${currentDev.gpu}\n` +
                `🔹 **RAM:** ${currentDev.ram}\n` +
                `🔹 **Storage:** ${currentDev.storage}\n\n` +
                `Ab batayein aap is device par **kaun si game** chalana chahte hain?`,
          quickActions: [
            { label: "Grand Theft Auto V (GTA 5)", action: "game_gta5" },
            { label: "Grand Theft Auto VI (GTA 6)", action: "game_gta6" },
            { label: "Cyberpunk 2077", action: "game_cyberpunk" },
            { label: "Call of Duty: BO6", action: "game_bo6" },
            { label: "Valorant / CS2", action: "game_valorant" },
            { label: "Minecraft", action: "game_minecraft" }
          ]
        }
      ]);
      return;
    }

    // Case B: Both Device & Game are now present -> Calculate full verdict
    if (currentDev && matchedGame) {
      setActiveDevice(currentDev);
      setChatStep('completed');

      let compScore = 45;
      let compVerdict = "Playable on Basic Settings";
      let adviceText = "";

      if (currentDev.tier === 'high_dedicated') {
        compScore = 98;
        compVerdict = "High Performance Profile";
        adviceText = "Aapka system high performance Tier me hai. Game high settings aur Ray Tracing par smooth chalegi.";
      } else if (currentDev.tier === 'mid_dedicated') {
        compScore = 80;
        compVerdict = "Recommended Hardware Profile";
        adviceText = "Aapka PC ready hai. Recommended performance profile milegi.";
      } else {
        // Budget / Integrated like HP 650 G4
        if (matchedGame.gameTitle.includes('GTA 5') || matchedGame.gameTitle.includes('San Andreas') || matchedGame.gameTitle.includes('CS 1.6') || matchedGame.gameTitle.includes('Minecraft')) {
          compScore = 55;
          compVerdict = "Playable on Basic Low Settings";
          adviceText = `Aapke ${currentDev.name} me integrated graphics hai. ${matchedGame.gameTitle} low settings par playable rahegi agar aap RAM ko 16GB rakhein.`;
        } else {
          compScore = 18;
          compVerdict = "Below Minimum Hardware Requirements";
          adviceText = `Aapke ${currentDev.name} par heavy AAA 3D games (like ${matchedGame.gameTitle}) native nahi chal sakengi kyunke isme dedicated GPU nahi hai.`;
        }
      }

      setIsTyping(false);
      setMessages([
        ...currentMessages,
        {
          sender: 'bot',
          deviceData: currentDev,
          gameData: matchedGame,
          compScore,
          compVerdict,
          text: `📊 **Hardware Compatibility Result:**\n\n` +
                `💻 **Device:** ${currentDev.name}\n` +
                `🎮 **Target Game:** ${matchedGame.gameTitle}\n` +
                `⚡ **Compatibility Rating:** ${compScore}% (${compVerdict})\n\n` +
                `💡 **Expert Verdict:**\n${adviceText}\n\n` +
                (compScore < 70 ? 
                  `🔧 **Hardware Upgrade Recommendation:**\n` +
                  `1. RAM ko **16GB Dual-Channel** karein taake integrated graphics ko double bandwidth mile.\n` +
                  `2. Mechanical hard drive ki jagah fast **M.2 NVMe SSD** use karein.\n\n` +
                  `⚠️ **Jugaaru / Alternative Shortcut (Fun Only):**\n` +
                  `Agar aapne upgrade nahi karwana to aap **NVIDIA GeForce NOW Cloud** par free stream kar lein, ya dost se **USB 3.2 External Drive** me pre-installed game backup copy karwa lein.\n` +
                  `*(Note: Hamari lab official safe platforms ko refer karti hai taake security aur ban ka risk na ho, lekin casual fun ke liye ye working alternatives hain!)*`
                  :
                  `Aapka PC hardware verified hai. Aap official Steam ya Epic Games se install kar ke enjoy kar sakte hain!`
                ),
          quickActions: [
            { label: "🎮 Test Another Game", action: "test_another_game" },
            { label: "💻 Change PC / Laptop Model", action: "start_pc_check" },
            { label: "⚡ Open Full Compatibility Page", action: "nav_compatibility" },
            { label: "🛒 View RAM/SSD Upgrades", action: "nav_products" }
          ]
        }
      ]);
      return;
    }

    // Default polite conversational reply
    setIsTyping(false);
    setMessages([
      ...currentMessages,
      {
        sender: 'bot',
        text: `Ji main aapki puri madad karne ke liye hazir hoon! 😊\n\nAap mujhe apne laptop/PC ka naam batayein (jaise: 'HP 650 G4', 'Dell Inspiron', 'RTX 3060 PC') aur game ka naam likhein. Main aapko exact specs aur compatibility percentage bata dunga!`,
        quickActions: [
          { label: "🔍 Check My PC Specs", action: "start_pc_check" },
          { label: "⚡ 1-Click Screen Scan", action: "scan_browser" },
          { label: "🕹️ 15 Gaming Platforms", action: "browse_platforms" },
          { label: "☁️ Cloud Gaming Guide", action: "cloud_guide" }
        ]
      }
    ]);
  };

  const handleQuickAction = (action) => {
    playClickSound();
    if (action === 'auth_login') {
      navigateTo('auth', 'login');
      setIsOpen(false);
      return;
    } else if (action === 'auth_register') {
      navigateTo('auth', 'register');
      setIsOpen(false);
      return;
    } else if (action === 'nav_categories') {
      navigateToCategory('all');
      setIsOpen(false);
    } else if (action === 'nav_compatibility') {
      navigateTo('compatibility');
      setIsOpen(false);
    } else if (action === 'nav_products') {
      navigateTo('products');
      setIsOpen(false);
    } else if (action === 'scan_browser') {
      handleBrowserAutoScan();
    } else if (action === 'start_pc_check') {
      processBotResponse('start_pc_check', messages);
    } else if (action === 'dev_hp650') {
      handleUserSend("HP ProBook 650 G4 Core i5 8th gen");
    } else if (action === 'dev_dell') {
      handleUserSend("Dell Inspiron 15 Core i5 12th gen");
    } else if (action === 'dev_lenovo') {
      handleUserSend("Lenovo ThinkPad T480 Core i5");
    } else if (action === 'dev_tuf') {
      handleUserSend("ASUS TUF Gaming RTX 4060 Ryzen 7");
    } else if (action === 'game_gta5') {
      handleUserSend("GTA 5");
    } else if (action === 'game_gta6') {
      handleUserSend("GTA 6");
    } else if (action === 'game_cyberpunk') {
      handleUserSend("Cyberpunk 2077");
    } else if (action === 'game_bo6') {
      handleUserSend("Call of Duty: Black Ops 6");
    } else if (action === 'game_valorant') {
      handleUserSend("Valorant and CS2");
    } else if (action === 'game_minecraft') {
      handleUserSend("Minecraft");
    } else if (action === 'test_another_game') {
      setChatStep('awaiting_game');
      setIsTyping(false);
      setMessages(prev => [
        ...prev,
        {
          sender: 'bot',
          text: "Kaun si doosri game test karni hai? Niche se game select karein ya naam type karein:",
          quickActions: [
            { label: "Grand Theft Auto V", action: "game_gta5" },
            { label: "Cyberpunk 2077", action: "game_cyberpunk" },
            { label: "Call of Duty: BO6", action: "game_bo6" },
            { label: "Elden Ring", action: "game_gta6" },
            { label: "Valorant", action: "game_valorant" }
          ]
        }
      ]);
    }
  };

  return (
    <>
      {/* Global Floating AI Bot Trigger Button with Pulsing Holographic Ring */}
      <div className="fixed bottom-5 sm:bottom-6 right-4 sm:right-6 z-50 flex items-center gap-2.5">
        
        {/* Friendly Tooltip Bubble */}
        {!isOpen && (
          <div 
            onClick={() => {
              playPowerUpSound();
              setIsOpen(true);
            }}
            className="hidden sm:flex items-center gap-2 bg-slate-950/95 text-white text-xs font-bold px-3.5 py-2 rounded-2xl border border-emerald-500/40 shadow-2xl cursor-pointer hover:bg-emerald-950/90 transition-all hover:scale-105 animate-bounce"
          >
            <Sparkles className="w-4 h-4 text-emerald-400 animate-pulse" />
            <span>Ask AI: "Can My PC Run This Game?"</span>
          </div>
        )}

        {/* Floating Glowing Bot Icon Button */}
        <button
          onClick={() => {
            if (!isOpen) playPowerUpSound();
            else playClickSound();
            setIsOpen(!isOpen);
          }}
          className="relative w-13 h-13 sm:w-14 sm:h-14 rounded-3xl bg-gradient-to-tr from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 text-white flex items-center justify-center shadow-2xl shadow-emerald-500/40 border-2 border-emerald-300 transition-all hover:scale-110 active:scale-95 group"
          title="ROC AI Hardware Assistant"
        >
          <span className="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-400 rounded-full border-2 border-slate-900 animate-ping"></span>
          <span className="absolute -top-1 -right-1 w-3.5 h-3.5 bg-emerald-400 rounded-full border-2 border-slate-900"></span>
          
          {isOpen ? (
            <X className="w-6 h-6 group-hover:rotate-90 transition-transform duration-300" />
          ) : (
            <Bot className="w-6 h-6 sm:w-7 sm:h-7 animate-pulse group-hover:scale-110 transition-transform" />
          )}
        </button>
      </div>

      {/* Floating AI Bot Assistant Chat Modal */}
      {isOpen && (
        <div className="fixed bottom-20 sm:bottom-24 right-3 sm:right-6 z-50 w-[94vw] sm:w-[420px] max-h-[78vh] sm:max-h-[82vh] bg-white border-2 border-emerald-500 rounded-3xl shadow-2xl flex flex-col overflow-hidden animate-page-in">
          
          {/* Header with Cyber Green Gradient */}
          <div className="bg-gradient-to-r from-slate-950 via-emerald-950 to-slate-900 text-white p-3.5 sm:p-4 border-b border-emerald-500/30 flex items-center justify-between relative overflow-hidden shrink-0">
            <div className="flex items-center gap-2.5 sm:gap-3 relative z-10 min-w-0">
              <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 text-slate-950 flex items-center justify-center font-extrabold shadow-md shrink-0">
                <Bot className="w-5 h-5 sm:w-6 sm:h-6" />
              </div>
              <div className="min-w-0">
                <div className="flex items-center gap-1.5">
                  <h3 className="font-display font-extrabold text-xs sm:text-sm text-white truncate">
                    FragBot AI Assistant
                  </h3>
                  <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shrink-0"></span>
                </div>
                <span className="text-[9px] sm:text-[10px] text-emerald-300 font-bold truncate block">
                  Hardware Specs & Compatibility Engine
                </span>
              </div>
            </div>

            <button
              onClick={() => {
                playClickSound();
                setIsOpen(false);
              }}
              className="p-1.5 rounded-full bg-slate-800/80 hover:bg-rose-500/20 hover:text-rose-400 text-slate-400 transition-colors shrink-0"
            >
              <X className="w-4 h-4" />
            </button>
          </div>

          {/* Chat Messages Body */}
          <div className="flex-1 p-3.5 sm:p-4 overflow-y-auto space-y-3 bg-slate-50/70 text-xs sm:text-sm" style={{ scrollbarWidth: 'thin' }}>
            {messages.map((msg, idx) => (
              <div
                key={idx}
                className={`flex flex-col ${msg.sender === 'user' ? 'items-end' : 'items-start'}`}
              >
                {/* Message Bubble */}
                <div
                  className={`p-3 sm:p-3.5 rounded-2xl max-w-[92%] space-y-2 leading-relaxed whitespace-pre-line shadow-xs ${
                    msg.sender === 'user'
                      ? 'bg-emerald-600 text-white font-medium rounded-br-none'
                      : 'bg-white border border-slate-200 text-slate-800 rounded-bl-none shadow-sm'
                  }`}
                >
                  {/* If bot returned a device spec photo card */}
                  {msg.deviceData && (
                    <div className="bg-slate-900 text-white p-2.5 rounded-xl border border-emerald-500/30 space-y-1.5 mb-2">
                      <div className="relative h-24 rounded-lg overflow-hidden bg-slate-800">
                        <img 
                          src={msg.deviceData.image} 
                          alt={msg.deviceData.name} 
                          className="w-full h-full object-cover" 
                        />
                        <span className="absolute bottom-1.5 left-1.5 bg-emerald-600 text-white text-[8px] font-extrabold px-2 py-0.5 rounded shadow-sm">
                          Detected Device Model
                        </span>
                      </div>
                      <div className="font-display font-extrabold text-xs text-emerald-300 truncate">
                        {msg.deviceData.name}
                      </div>
                    </div>
                  )}

                  <div>{msg.text}</div>
                </div>

                {/* Interactive Quick Action Buttons */}
                {msg.quickActions && (
                  <div className="flex flex-wrap gap-1.5 mt-1.5 max-w-[95%]">
                    {msg.quickActions.map((qa, qIdx) => (
                      <button
                        key={qIdx}
                        onClick={() => handleQuickAction(qa.action)}
                        className="bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-300 text-[10px] sm:text-[11px] font-extrabold px-2.5 py-1 rounded-xl transition-all hover:scale-102 flex items-center gap-1 shadow-2xs"
                      >
                        <span>{qa.label}</span>
                        <ArrowRight className="w-3 h-3 text-emerald-600" />
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ))}

            {/* Typing Indicator */}
            {isTyping && (
              <div className="flex items-center gap-1.5 p-2.5 bg-white border border-slate-200 rounded-2xl w-24 text-slate-400">
                <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-bounce"></span>
                <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></span>
                <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-bounce" style={{ animationDelay: '0.4s' }}></span>
              </div>
            )}

            <div ref={chatEndRef} />
          </div>

          {/* Quick Preset Prompts Strip */}
          <div className="bg-slate-100 px-3 py-1.5 border-t border-slate-200 flex items-center gap-1.5 overflow-x-auto text-[10px] shrink-0" style={{ scrollbarWidth: 'none' }}>
            <span className="font-bold text-slate-500 shrink-0">Suggestions:</span>
            <button 
              onClick={() => handleBrowserAutoScan()}
              className="bg-emerald-600 text-white font-bold px-2 py-0.5 rounded-lg shrink-0 flex items-center gap-1"
            >
              <Scan className="w-2.5 h-2.5" /> Auto-Scan Screen
            </button>
            <button 
              onClick={() => handleUserSend("HP 650 G4 Core i5 8th gen")}
              className="bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 px-2 py-0.5 rounded-lg shrink-0"
            >
              HP 650 G4
            </button>
            <button 
              onClick={() => handleUserSend("Dell Inspiron 15 Core i5")}
              className="bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 px-2 py-0.5 rounded-lg shrink-0"
            >
              Dell Inspiron 15
            </button>
            <button 
              onClick={() => handleUserSend("ASUS TUF RTX 4060")}
              className="bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 px-2 py-0.5 rounded-lg shrink-0"
            >
              ASUS TUF
            </button>
          </div>

          {/* Input Box Bar */}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleUserSend();
            }}
            className="p-2.5 sm:p-3 bg-white border-t border-slate-200 flex items-center gap-2 shrink-0"
          >
            <input
              type="text"
              placeholder={chatStep === 'awaiting_game' ? "Type game (e.g. GTA 5, Cyberpunk)..." : "Type your laptop or PC model..."}
              value={inputText}
              onChange={(e) => setInputText(e.target.value)}
              className="flex-1 bg-slate-50 border border-slate-300 focus:border-emerald-500 rounded-xl px-3 py-2 text-xs text-slate-900 font-semibold focus:outline-none focus:bg-white transition-all shadow-inner"
            />

            <button
              type="submit"
              disabled={!inputText.trim()}
              className="bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white p-2 rounded-xl transition-all shadow-md shrink-0"
            >
              <Send className="w-4 h-4" />
            </button>
          </form>

        </div>
      )}
    </>
  );
};
