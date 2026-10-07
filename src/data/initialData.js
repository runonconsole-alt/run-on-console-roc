import { GAME_COMPATIBILITY_DATA } from './gameCompatibilityData.js';
export { GAME_COMPATIBILITY_DATA };

export const HERO_SLIDES = [
  {
    id: "blog-1",
    title: "Call of Duty: Black Ops 6 – Everything We Know So Far",
    badge: "FEATURED",
    date: "May 20, 2024",
    author: "Omar Abobakar",
    image: "/images/hero_cod.jpg",
    summary: "From full omnidirectional movement to round-based zombies and multiplayer gameplay deep dive.",
    category: "PC / Handheld PC"
  },
  {
    id: "blog-2",
    title: "Elden Ring: Shadow of the Erdtree Full Boss & Performance Analysis",
    badge: "FEATURED",
    date: "May 19, 2024",
    author: "Alex Vance",
    image: "/images/trending_elden.jpg",
    summary: "Exploring the Realm of Shadow with deep combat breakdowns and frame pacing tests across RTX 40 series and PS5.",
    category: "PC / Handheld PC"
  },
  {
    id: "blog-3",
    title: "Spider-Man 2 Next-Gen Ray Tracing & Performance Masterclass",
    badge: "FEATURED",
    date: "May 18, 2024",
    author: "Sarah Kai",
    image: "/images/trending_spiderman.jpg",
    summary: "How Insomniac pushed dynamic resolution and ray-traced reflections on the PlayStation 5 hardware.",
    category: "Sony PlayStation"
  }
];

export const INITIAL_TRENDING = [
  {
    id: "trend-1",
    title: "Elden Ring DLC Review – Worth the Hype?",
    category: "PC GAMING",
    date: "May 19, 2024",
    image: "/images/trending_elden.jpg",
    readTime: "6 min read",
    author: "Alex Vance"
  },
  {
    id: "trend-2",
    title: "Spider-Man 2 on PS5 – Performance Review",
    category: "PLAYSTATION",
    date: "May 18, 2024",
    image: "/images/trending_spiderman.jpg",
    readTime: "5 min read",
    author: "Sarah Kai"
  },
  {
    id: "trend-3",
    title: "Forza Motorsport 2024 – First Impressions",
    category: "XBOX",
    date: "May 17, 2024",
    image: "/images/trending_forza.jpg",
    readTime: "8 min read",
    author: "Omar Abobakar"
  },
  {
    id: "trend-4",
    title: "VALORANT Champions Tour 2024 – Schedule & Teams",
    category: "ESPORTS",
    date: "May 16, 2024",
    image: "/images/trending_esports.jpg",
    readTime: "4 min read",
    author: "Derrick Vance"
  }
];

// COMPREHENSIVE 15 GAMING PLATFORM CATEGORIES
export const GAMING_CATEGORIES = [
  {
    id: "pc-handheld-pc",
    title: "PC / Handheld PC",
    slug: "pc-handheld-pc",
    image: "/images/battlestation_pc.jpg",
    desc: "Custom battlestation rigs, high-refresh gaming laptops, and high-performance x86 handheld PCs running Steam, Game Pass, and Epic.",
    badge: "x86 AAA POWER",
    itemCount: 52,
    era: "Modern & Portable x86",
    devices: [
      "Gaming PC", "Gaming Laptop", "Steam Deck", "Steam Deck OLED", 
      "ROG Ally", "ROG Ally X", "Lenovo Legion Go", "GPD Win 4", 
      "GPD Win Max", "Ayaneo 2", "Ayaneo Air", "MSI Claw", "OneXPlayer"
    ],
    popularGames: ["Cyberpunk 2077", "Valorant", "Counter-Strike 2", "Elden Ring", "Black Myth: Wukong"],
    subcategories: ["Custom Liquid Rigs", "RTX 4090 Desktops", "Steam Deck OLED", "ROG Ally X", "OLED Laptops"]
  },
  {
    id: "sony-playstation",
    title: "Sony PlayStation",
    slug: "sony-playstation",
    image: "/images/trending_spiderman.jpg",
    desc: "From the revolutionary 32-bit PS1 to the 4K 120FPS ray-traced PlayStation 5 Pro, DualSense Edge, and PlayStation Portal.",
    badge: "GENERATION SONY",
    itemCount: 48,
    era: "1994 – Present",
    devices: [
      "PS5 Pro", "PS5", "PS5 Digital Edition", "PS4 Pro", "PS4 Slim", "PS4", 
      "PS3 (Fat/Slim/Super Slim)", "PS2 (Fat/Slim)", "PS1 / PSX"
    ],
    popularGames: ["Spider-Man 2", "God of War Ragnarok", "Ghost of Tsushima", "Bloodborne", "The Last of Us Part I"],
    subcategories: ["PS5 Pro 4K 120FPS", "PS5 Slim", "PS4 Pro HDR", "PS2 Classic Era", "DualSense Edge"]
  },
  {
    id: "microsoft-xbox",
    title: "Microsoft Xbox",
    slug: "microsoft-xbox",
    image: "/images/trending_forza.jpg",
    desc: "12 Teraflops 4K raw power on Xbox Series X, Series S compact powerhouse, Xbox 360 golden era, and Xbox Game Pass Ultimate titles.",
    badge: "GAME PASS READY",
    itemCount: 44,
    era: "2001 – Present",
    devices: [
      "Xbox Series X", "Xbox Series S", "Xbox One X", "Xbox One S", "Xbox One", 
      "Xbox 360 Slim", "Xbox 360", "Original Xbox (2001)"
    ],
    popularGames: ["Forza Motorsport", "Halo Infinite", "Starfield", "Gears of War 5", "Call of Duty: BO6"],
    subcategories: ["Series X 12TF", "Series S 1440p", "Game Pass Ultimate", "Xbox 360 Classics", "Seagate Expansion Cards"]
  },
  {
    id: "nintendo-home",
    title: "Nintendo Home Consoles",
    slug: "nintendo-home",
    image: "/images/hero_cod.jpg",
    desc: "Pioneering family and competitive home console entertainment from NES, SNES, and N64 to GameCube, Wii, and the upcoming Switch 2.",
    badge: "NINTENDO LEGACY",
    itemCount: 39,
    era: "1983 – Present",
    devices: [
      "Switch 2 (Next-Gen)", "Switch OLED", "Switch", "Switch Lite", 
      "Wii U", "Wii", "GameCube", "Nintendo 64", "SNES (Super Nintendo)", "NES (Famicom)", "Virtual Boy"
    ],
    popularGames: ["Zelda: Tears of the Kingdom", "Super Mario Wonder", "Super Smash Bros. Melee", "Mario Kart 8 Deluxe"],
    subcategories: ["Switch 2", "Switch OLED Hybrid", "GameCube DOL-001", "N64 64-Bit", "SNES 16-Bit"]
  },
  {
    id: "sony-handhelds",
    title: "Sony Handhelds",
    slug: "sony-handhelds",
    image: "/images/handheld_console.jpg",
    desc: "Iconic PlayStation portable hardware: PSP 1000/2000/3000 series, slide-out PSP Go, and dual-analog OLED PS Vita.",
    badge: "SONY PORTABLE",
    itemCount: 28,
    era: "2004 – 2019",
    devices: [
      "PSP 1000", "PSP 2000 (Slim & Lite)", "PSP 3000 (Bright)", 
      "PSP Go (Slide-out)", "PSP Street (E1000)", "PS Vita (1000 OLED)", "PS Vita (2000 Slim LCD)"
    ],
    popularGames: ["God of War: Ghost of Sparta", "Persona 4 Golden", "Monster Hunter Freedom Unite", "Killzone: Mercenary"],
    subcategories: ["PS Vita 1000 OLED", "PS Vita 2000 Slim", "PSP 3000", "PSP Go Slide", "Custom Firmware & SD2Vita"]
  },
  {
    id: "nintendo-handhelds",
    title: "Nintendo Handhelds",
    slug: "nintendo-handhelds",
    image: "/images/handheld_console.jpg",
    desc: "The undisputed greatest handheld gaming dynasty: Game & Watch, Game Boy, GBA SP, Nintendo DS Lite, and New 3DS XL.",
    badge: "DUAL SCREEN & GBA",
    itemCount: 42,
    era: "1980 – 2020",
    devices: [
      "New 3DS XL", "New 3DS", "3DS XL", "3DS", "2DS XL", "2DS", 
      "DSi XL", "DSi", "DS Lite", "Original DS (Phat)", 
      "Game Boy Advance SP (AGS-101)", "Game Boy Advance", "Game Boy Color", "Game Boy Pocket", "Game Boy (DMG-01)", "Game & Watch"
    ],
    popularGames: ["Pokemon Emerald", "Pokemon HeartGold", "Zelda: A Link Between Worlds", "Mario Kart DS", "Chrono Trigger DS"],
    subcategories: ["New 3DS XL IPS", "DS Lite", "GBA SP AGS-101 Backlit", "Game Boy Color", "IPS Screen Modding"]
  },
  {
    id: "sega-consoles",
    title: "Sega Consoles (Retro)",
    slug: "sega-consoles",
    image: "/images/trending_elden.jpg",
    desc: "High-speed blast processing and arcade innovation: Sega Genesis / Mega Drive, Sega Saturn, Dreamcast, and Game Gear.",
    badge: "ARCADE BLAST PROCESSING",
    itemCount: 31,
    era: "1985 – 2001",
    devices: [
      "Sega Dreamcast (128-Bit)", "Sega Saturn", "Sega Genesis / Mega Drive (Model 1/2)", 
      "Sega Game Gear", "Sega Master System", "Sega CD", "Sega 32X", "Sega Nomad"
    ],
    popularGames: ["Sonic the Hedgehog 2", "Shenmue I & II", "Sonic Adventure 2", "Streets of Rage 2", "Phantasy Star Online"],
    subcategories: ["Dreamcast GD-EMU", "Genesis 16-Bit Blast", "Saturn 32-Bit 2D Fighter", "Game Gear IPS", "Sega CD Add-on"]
  },
  {
    id: "retro-other",
    title: "Other Retro Consoles",
    slug: "retro-other",
    image: "/images/gaming_cables_accessories.jpg",
    desc: "Legendary vintage machines: Atari 2600, Neo Geo AES/MVS arcade home system, TurboGrafx-16, 3DO, Commodore 64, and Colecovision.",
    badge: "GOLDEN AGE RETRO",
    itemCount: 34,
    era: "1977 – 1996",
    devices: [
      "Atari 2600 (VCS)", "Atari 5200", "Atari 7800", "Atari Jaguar (64-Bit)", "Atari Lynx (Color Handheld)", 
      "Neo Geo AES (24-Bit Arcade)", "Neo Geo CD", "Neo Geo Pocket / Color", 
      "3DO Interactive Multiplayer", "TurboGrafx-16 (PC Engine)", "Colecovision", "Intellivision", "Commodore 64", "Amiga 500"
    ],
    popularGames: ["The King of Fighters '98", "Metal Slug 3", "Space Invaders", "Castlevania: Rondo of Blood", "Pitfall!"],
    subcategories: ["Neo Geo AES 24-Bit", "TurboGrafx-16 / PC Engine", "Atari 2600 VCS", "Commodore 64 SID Audio", "3DO 32-Bit"]
  },
  {
    id: "mobile",
    title: "Mobile Gaming",
    slug: "mobile",
    image: "/images/pro_controller.jpg",
    desc: "120FPS competitive mobile esports on iOS & Android paired with console-grade magnetic snap-on controller grips.",
    badge: "120FPS MOBILE",
    itemCount: 26,
    era: "Modern Smartphone / Tablet",
    devices: [
      "Android Smartphone (Snapdragon 8 Gen 3)", "iPhone (15 Pro / 16 Pro)", 
      "iPad Pro (M2 / M4 120Hz OLED)", "Android Tablet (Galaxy Tab S9)", 
      "Backbone One (USB-C / Lightning)", "Razer Kishi V2 / Ultra", "GameSir G8 Galileo / X2 Pro"
    ],
    popularGames: ["Genshin Impact", "PUBG Mobile", "Call of Duty: Warzone Mobile", "Honkai: Star Rail", "Fortnite Mobile"],
    subcategories: ["iPhone 15/16 Pro AAA", "iPad Pro M4 120Hz", "Backbone One Type-C", "Razer Kishi Ultra", "Snapdragon 8 Gen 3 Rigs"]
  },
  {
    id: "cloud-gaming",
    title: "Cloud Gaming Platforms",
    slug: "cloud-gaming",
    image: "/images/trending_esports.jpg",
    desc: "Instant zero-download low-latency ray-traced streaming: NVIDIA GeForce NOW RTX 4080, Xbox Cloud, and PS Plus Premium.",
    badge: "ZERO DOWNLOAD 4K",
    itemCount: 20,
    era: "Cloud & Low-Latency Stream",
    devices: [
      "NVIDIA GeForce NOW (RTX 4080 Tier)", "Xbox Cloud Gaming (xCloud)", 
      "PS Plus Premium Cloud Streaming (4K PS5)", "Amazon Luna", "Netflix Games (Cloud Streaming)"
    ],
    popularGames: ["Cyberpunk 2077 (4K Ray Tracing)", "Alan Wake 2 (Path Tracing)", "Forza Horizon 5", "Halo Infinite"],
    subcategories: ["GeForce NOW Ultimate", "Xbox Cloud 1080p Streaming", "PS Plus Cloud 4K", "Amazon Luna Prime", "Low-Latency WiFi 6E"]
  },
  {
    id: "vr-ar",
    title: "VR / AR Spatial Systems",
    slug: "vr-ar",
    image: "/images/streaming_vr_gear.jpg",
    desc: "Full 6DoF immersion spatial reality headsets: Meta Quest 3, PlayStation VR2, Valve Index, Pico 4, and Apple Vision Pro.",
    badge: "IMMERSIVE SPATIAL 3D",
    itemCount: 24,
    era: "Spatial Computing",
    devices: [
      "Meta Quest 3", "Meta Quest 2", "Meta Quest Pro", "PlayStation VR2 (PS5)", "PlayStation VR (PS4)", 
      "Valve Index", "HTC Vive Pro 2", "HTC Vive", "Pico 4 / Pico 4 Pro", "Pimax Crystal", "Apple Vision Pro"
    ],
    popularGames: ["Half-Life: Alyx", "Beat Saber", "Resident Evil 4 VR", "Horizon Call of the Mountain", "Gran Turismo 7 VR"],
    subcategories: ["Meta Quest 3 Pancake Lenses", "PS VR2 OLED HDR", "Valve Index 144Hz", "PC VR Link Cables", "Haptic Spatial Grips"]
  },
  {
    id: "retro-handheld-brands",
    title: "China / Retro-Handheld Brands",
    slug: "retro-handheld-brands",
    image: "/images/handheld_console.jpg",
    desc: "Dedicated Linux, Android & FPGA retro emulation consoles: Anbernic, Retroid Pocket, Miyoo Mini, Ayn Odin 2, and Analogue Pocket.",
    badge: "EMULATION ENGINES",
    itemCount: 38,
    era: "2019 – Present",
    devices: [
      "Anbernic RG35XX / RG35XX Plus / RG35XX H", "Anbernic RG40XX H / V", "Anbernic RG556 (OLED)", 
      "Retroid Pocket 5", "Retroid Pocket 4 Pro", "Retroid Pocket 3+", "Retroid Pocket 2S", 
      "Miyoo Mini", "Miyoo Mini Plus (Miyoo Mini+)", "Miyoo A30", 
      "Powkiddy RGB30", "Powkiddy RGB10 Max 3", "Powkiddy V90", 
      "Analogue Pocket (FPGA)", "Analogue Duo (PC Engine FPGA)", 
      "Ayn Odin 2 / Odin 2 Mini (Snapdragon 8 Gen 2)", "Trimui Smart Pro"
    ],
    popularGames: ["PS2 Emulation (God of War II)", "GameCube (Wind Waker)", "GBA (Pokemon Unbound)", "SNES (Super Metroid)", "Arcade Classics"],
    subcategories: ["Analogue Pocket FPGA", "Ayn Odin 2 Snapdragon", "Retroid Pocket 5 OLED", "Miyoo Mini+ OnionOS", "Anbernic RG35XX GarlicOS"]
  },
  {
    id: "arcade",
    title: "Arcade & Custom Pinball",
    slug: "arcade",
    image: "/images/gaming_monitor.jpg",
    desc: "Authentic coin-op arcade cabinets, 3/4 scale Arcade1Up machines, custom MAME emulator rigs, and virtual digital pinball machines.",
    badge: "COIN-OP ARCADE",
    itemCount: 19,
    era: "Arcade & Home Custom",
    devices: [
      "Full-Size Commercial Arcade Cabinet", "Arcade1Up (Street Fighter / Pac-Man / Mortal Kombat)", 
      "MAME Custom Bartop Cabinet", "Digital Virtual Pinball Machine (VPin)", 
      "Neo Geo MVS 4-Slot Arcade Cabinet", "AtGames Legends Ultimate"
    ],
    popularGames: ["Street Fighter II: Champion Edition", "Mortal Kombat II", "Ms. Pac-Man", "The Simpsons Arcade", "Medieval Madness Pinball"],
    subcategories: ["Arcade1Up Deluxe", "MAME Custom Bartop", "Sanwa Arcade Joysticks", "Digital Pinball 4K 120Hz", "Neo Geo MVS Coin-Op"]
  },
  {
    id: "smart-tv-streaming",
    title: "Smart TV & Streaming Devices",
    slug: "smart-tv-streaming",
    image: "/images/gaming_monitor.jpg",
    desc: "Living room cloud micro-consoles and TV apps: NVIDIA Shield TV Pro, Apple TV 4K, Fire TV Stick 4K Max, and Samsung Gaming Hub.",
    badge: "LIVING ROOM HUB",
    itemCount: 16,
    era: "Smart TV & Micro-Console",
    devices: [
      "Smart TV (Samsung Gaming Hub / LG Game Portal)", "Amazon Fire TV Stick 4K Max", 
      "Apple TV 4K (A15 Bionic)", "NVIDIA Shield TV Pro", "Google TV Chromecast 4K", "Roku Ultra (Casual Gaming)"
    ],
    popularGames: ["Xbox Cloud Gaming on TV", "GeForce NOW 4K HDR", "Apple Arcade (Oceanhorn 2)", "Jackbox Party Packs", "Asphalt 9"],
    subcategories: ["NVIDIA Shield TV Pro 4K AI", "Apple TV 4K Apple Arcade", "Samsung Gaming Hub Direct", "Fire TV Stick Xbox Cloud", "Bluetooth Gamepad Sync"]
  },
  {
    id: "misc-other",
    title: "Misc & Niche Gaming Systems",
    slug: "misc-other",
    image: "/images/cyber_keyboard.jpg",
    desc: "Unique, indie, micro, and educational systems: Panic Playdate with mechanical crank, Raspberry Pi RetroPie, Evercade, and Chromebooks.",
    badge: "UNIQUE & INDIE",
    itemCount: 18,
    era: "Indie & Micro Systems",
    devices: [
      "Panic Playdate (Black & White Crank Handheld)", "Raspberry Pi 4 / 5 (RetroPie / Recalbox Setup)", 
      "Evercade EXP / Evercade VS (Cartridge Retro)", "Gaming Chromebook (120Hz Cloud Edition)", 
      "LeapFrog / LeapPad (Kids Interactive Gaming)", "Analogue Mega Sg / Super Nt"
    ],
    popularGames: ["Crankin's Time Travel Adventure (Playdate)", "Evercade Arcade Collections", "RetroPie PS1 Classics", "Chromebook Steam Cloud"],
    subcategories: ["Panic Playdate 1-Bit Crank", "Raspberry Pi 5 RetroPie", "Evercade Physical Cartridges", "Cloud Gaming Chromebook", "LeapPad Kids Learning"]
  }
];

// 100+ MASTER GAMING DEVICES DATABASE (SEARCHABLE & FILTERABLE)
export const ALL_GAMING_DEVICES = [
  // PC / Handheld PC
  { id: "dev-pc-1", name: "Custom Battlestation PC", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2024", era: "Modern", type: "Desktop Battlestation", specs: "RTX 4090 24GB // Ryzen 7800X3D // 64GB DDR5 // 4K 240Hz", status: "Active Flagship", iconicGames: ["Cyberpunk 2077", "Valorant", "CS2", "Elden Ring"], image: "/images/battlestation_pc.jpg" },
  { id: "dev-pc-2", name: "Gaming Laptop (RTX 4080/4090)", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2024", era: "Modern", type: "Portable Laptop", specs: "240Hz QHD OLED // Intel i9-14900HX // RTX 4080 175W", status: "Active Flagship", iconicGames: ["Call of Duty: BO6", "Apex Legends", "Black Myth: Wukong"], image: "/images/gaming_laptop.jpg" },
  { id: "dev-pc-3", name: "Steam Deck OLED", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2023", era: "Modern", type: "Handheld PC", specs: "7.4\" 90Hz HDR OLED // 6nm AMD APU // 50Wh Battery // SteamOS 3.5", status: "Active Benchmark", iconicGames: ["Elden Ring", "Hades II", "Cyberpunk 2077", "Balatro"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-4", name: "Steam Deck (Original LCD)", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2022", era: "Modern", type: "Handheld PC", specs: "7.0\" 60Hz LCD // 7nm AMD Aerith APU // 16GB LPDDR5", status: "Active Legacy", iconicGames: ["Vampire Survivors", "God of War (2018)", "Doom Eternal"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-5", name: "ASUS ROG Ally X", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2024", era: "Modern", type: "Handheld PC", specs: "80Wh Massive Battery // AMD Ryzen Z1 Extreme // 24GB LPDDR5X-7500 // 120Hz VRR", status: "Active Flagship", iconicGames: ["Starfield", "Horizon Forbidden West", "Forza Horizon 5"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-6", name: "ASUS ROG Ally", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2023", era: "Modern", type: "Handheld PC", specs: "7\" 120Hz 1080p FreeSync // AMD Z1 Extreme // 16GB RAM // Windows 11", status: "Active", iconicGames: ["Cyberpunk 2077", "Diablo IV", "Monster Hunter Rise"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-7", name: "Lenovo Legion Go", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2023", era: "Modern", type: "Handheld PC", specs: "8.8\" 144Hz QHD 1600p // Detachable Controllers with FPS Mouse Mode", status: "Active", iconicGames: ["Halo Infinite", "Baldur's Gate 3", "Lies of P"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-8", name: "GPD Win 4 (2024)", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2024", era: "Modern", type: "Handheld PC", specs: "Slide-up 6\" Screen with Physical QWERTY Keyboard // AMD Ryzen 8840U", status: "Active Enthusiast", iconicGames: ["Street Fighter 6", "Armored Core VI", "Final Fantasy VII Remake"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-9", name: "GPD Win Max 2", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2023", era: "Modern", type: "Mini Laptop & Handheld", specs: "10.1\" Touch Display // Built-in Gamepad with Magnetic Covers // OCuLink", status: "Active", iconicGames: ["Civilization VI", "Witcher 3 Next Gen", "No Man's Sky"], image: "/images/gaming_laptop.jpg" },
  { id: "dev-pc-10", name: "Ayaneo 2 / 2S", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2023", era: "Modern", type: "Handheld PC", specs: "Borderless Full-Glass Front // 7\" 1200p IPS // Hall-Effect Sticks", status: "Active Premium", iconicGames: ["Ghost of Tsushima PC", "Hi-Fi Rush", "Spider-Man Remastered"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-11", name: "Ayaneo Air / Air 1S", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2023", era: "Modern", type: "Ultralight Handheld PC", specs: "5.5\" 1080p OLED // Featherlight 450g // AMD Ryzen 7840U", status: "Active", iconicGames: ["Hollow Knight", "Persona 5 Royal", "Dave the Diver"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-12", name: "MSI Claw", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2024", era: "Modern", type: "Handheld PC", specs: "Intel Core Ultra 7 155H // Intel Arc Graphics // 53Wh Battery // 120Hz VRR", status: "Active", iconicGames: ["Assassin's Creed Mirage", "Rainbow Six Siege", "Palworld"], image: "/images/handheld_console.jpg" },
  { id: "dev-pc-13", name: "OneXPlayer 2 Pro", categorySlug: "pc-handheld-pc", categoryTitle: "PC / Handheld PC", year: "2024", era: "Modern", type: "Modular Handheld PC", specs: "8.4\" 2.5K 1600p // Detachable Gamepad // Stylus Support // Harman Audio", status: "Active", iconicGames: ["Elden Ring", "Red Dead Redemption 2", "God of War"], image: "/images/handheld_console.jpg" },

  // Sony PlayStation
  { id: "dev-ps-1", name: "PlayStation 5 Pro", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2024", era: "9th Gen", type: "Home Console", specs: "PSSR AI Super Resolution // 16.7 Teraflops RDNA 3 GPU // 2TB SSD // 4K 120FPS RT", status: "Active Flagship", iconicGames: ["Spider-Man 2", "Gran Turismo 7", "Death Stranding 2", "Demon's Souls"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-2", name: "PlayStation 5 (Slim / Standard)", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2020", era: "9th Gen", type: "Home Console", specs: "10.28 Teraflops // Ultra-High-Speed 5.5GB/s SSD // DualSense Haptic Feedback", status: "Active Standard", iconicGames: ["God of War Ragnarok", "Horizon Forbidden West", "Returnal", "Ratchet & Clank: Rift Apart"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-3", name: "PlayStation 5 Digital Edition", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2020", era: "9th Gen", type: "Digital Home Console", specs: "Disc-Free Slim Chassis // Optional Detachable Disc Drive // DualSense", status: "Active", iconicGames: ["Astro's Playroom", "Gran Turismo 7", "The Last of Us Part I"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-4", name: "PlayStation 4 Pro", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2016", era: "8th Gen", type: "Enhanced Home Console", specs: "4.2 Teraflops GPU // Checkerboard 4K // Boost Mode", status: "Retro Active", iconicGames: ["Bloodborne", "Uncharted 4: A Thief's End", "God of War (2018)", "Spider-Man (2018)"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-5", name: "PlayStation 4 (Slim / Original)", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2013", era: "8th Gen", type: "Home Console", specs: "1.84 Teraflops AMD Radeon // 8GB GDDR5 // 117M Units Sold", status: "Legendary Classic", iconicGames: ["The Last of Us Remastered", "Ghost of Tsushima", "Persona 5"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-6", name: "PlayStation 3 (Fat / Slim / Super Slim)", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2006", era: "7th Gen", type: "Home Console", specs: "Cell Broadband Engine 3.2GHz // RSX 'Reality Synthesizer' // Blu-ray Built-in", status: "Retro Legend", iconicGames: ["Metal Gear Solid 4", "Uncharted 2: Among Thieves", "The Last of Us (PS3)", "Killzone 2"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-7", name: "PlayStation 2 (Fat / Slim)", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "2000", era: "6th Gen", type: "Home Console", specs: "Emotion Engine 294MHz // 155M+ Best-Selling Console of All Time // DVD Playback", status: "All-Time Best Seller", iconicGames: ["GTA: San Andreas", "Shadow of the Colossus", "God of War II", "Metal Gear Solid 3"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-ps-8", name: "PlayStation 1 (PS1 / PSX)", categorySlug: "sony-playstation", categoryTitle: "Sony PlayStation", year: "1994", era: "5th Gen 32-Bit", type: "Home Console", specs: "33.8MHz MIPS R3000A // CD-ROM Media // 3D Polygon Revolution", status: "Historic Icon", iconicGames: ["Final Fantasy VII", "Metal Gear Solid", "Resident Evil 2", "Crash Bandicoot"], image: "/images/trending_spiderman.jpg" },

  // Microsoft Xbox
  { id: "dev-xb-1", name: "Xbox Series X", categorySlug: "microsoft-xbox", categoryTitle: "Microsoft Xbox", year: "2020", era: "9th Gen", type: "Home Console", specs: "12.15 Teraflops RDNA 2 // 1TB Custom NVMe SSD // True 4K Output // Quick Resume", status: "Active Flagship", iconicGames: ["Forza Motorsport", "Halo Infinite", "Starfield", "Hellblade II", "Gears 5"], image: "/images/trending_forza.jpg" },
  { id: "dev-xb-2", name: "Xbox Series S", categorySlug: "microsoft-xbox", categoryTitle: "Microsoft Xbox", year: "2020", era: "9th Gen", type: "Compact Digital Console", specs: "4 Teraflops RDNA 2 // All-Digital // 1440p Target // Ultra-Compact Form Factor", status: "Active Value", iconicGames: ["Palworld", "Sea of Thieves", "Forza Horizon 5", "Flight Simulator 2024"], image: "/images/trending_forza.jpg" },
  { id: "dev-xb-3", name: "Xbox One X", categorySlug: "microsoft-xbox", categoryTitle: "Microsoft Xbox", year: "2017", era: "8th Gen", type: "Enhanced Home Console", specs: "6 Teraflops 'Project Scorpio' // Native 4K Gaming // 12GB GDDR5", status: "Retro Legacy", iconicGames: ["Red Dead Redemption 2 (Native 4K)", "Gears of War 4", "Forza Horizon 4"], image: "/images/trending_forza.jpg" },
  { id: "dev-xb-4", name: "Xbox One / One S", categorySlug: "microsoft-xbox", categoryTitle: "Microsoft Xbox", year: "2013", era: "8th Gen", type: "Home Console", specs: "1.31 / 1.4 Teraflops // 4K Blu-ray (One S) // HDR10 Gaming", status: "Classic", iconicGames: ["Halo: The Master Chief Collection", "Sunset Overdrive", "Quantum Break"], image: "/images/trending_forza.jpg" },
  { id: "dev-xb-5", name: "Xbox 360 / 360 Slim", categorySlug: "microsoft-xbox", categoryTitle: "Microsoft Xbox", year: "2005", era: "7th Gen", type: "Home Console", specs: "3.2GHz Xenon Triple-Core // ATI Xenos 500MHz // Xbox Live Golden Era // 84M Sold", status: "Retro Legend", iconicGames: ["Halo 3", "Gears of War", "Mass Effect 2", "BioShock", "Left 4 Dead"], image: "/images/trending_forza.jpg" },
  { id: "dev-xb-6", name: "Original Xbox (2001)", categorySlug: "microsoft-xbox", categoryTitle: "Microsoft Xbox", year: "2001", era: "6th Gen", type: "Home Console", specs: "Intel Pentium III 733MHz // NVIDIA NV2A GPU // First Built-in Hard Drive // Xbox Live", status: "Historic Icon", iconicGames: ["Halo: Combat Evolved", "Halo 2", "Star Wars: KOTOR", "Ninja Gaiden Black", "Fable"], image: "/images/trending_forza.jpg" },

  // Nintendo Home Consoles
  { id: "dev-nin-1", name: "Nintendo Switch 2 (Next-Gen)", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2025 (Expected)", era: "Next-Gen Hybrid", type: "Hybrid Console", specs: "Custom NVIDIA Tegra T239 // DLSS Upscaling // Backward Compatibility // 8\" Display", status: "Upcoming Flagship", iconicGames: ["Next 3D Mario (Upcoming)", "Mario Kart X (Upcoming)", "Metroid Prime 4"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-2", name: "Nintendo Switch OLED", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2021", era: "8th/9th Gen Hybrid", type: "Hybrid Console", specs: "7.0\" Vibrant 720p OLED // Wide Adjustable Kickstand // LAN Dock // 64GB", status: "Active Flagship", iconicGames: ["Zelda: Tears of the Kingdom", "Super Mario Wonder", "Metroid Dread", "Zelda: Breath of the Wild"], image: "/images/handheld_console.jpg" },
  { id: "dev-nin-3", name: "Nintendo Switch (Original)", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2017", era: "8th Gen Hybrid", type: "Hybrid Console", specs: "6.2\" LCD // NVIDIA Tegra X1 // Detachable Joy-Con HD Rumble // 143M+ Sold", status: "Active Icon", iconicGames: ["Super Mario Odyssey", "Super Smash Bros. Ultimate", "Animal Crossing: New Horizons"], image: "/images/handheld_console.jpg" },
  { id: "dev-nin-4", name: "Nintendo Switch Lite", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2019", era: "8th Gen Handheld", type: "Pure Handheld", specs: "5.5\" Compact LCD // Integrated D-Pad // 275g Lightweight", status: "Active Value", iconicGames: ["Pokemon Scarlet/Violet", "Kirby and the Forgotten Land", "Pikmin 4"], image: "/images/handheld_console.jpg" },
  { id: "dev-nin-5", name: "Nintendo Wii U", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2012", era: "8th Gen", type: "Dual-Screen Home Console", specs: "IBM PowerPC Espresso // 6.2\" GamePad Touch Controller // Off-TV Play", status: "Cult Classic", iconicGames: ["Mario Kart 8", "Super Mario 3D World", "Splatoon", "Pikmin 3"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-6", name: "Nintendo Wii", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2006", era: "7th Gen", type: "Motion Home Console", specs: "Wii Remote Motion Sensor Pointer // Nunchuk // 101M Phenomenon", status: "Historic Icon", iconicGames: ["Wii Sports", "Super Mario Galaxy 1 & 2", "Zelda: Twilight Princess", "Super Smash Bros. Brawl"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-7", name: "Nintendo GameCube (DOL-001)", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "2001", era: "6th Gen", type: "Home Console", specs: "IBM PowerPC Gekko 485MHz // ATI Flipper GPU // MiniDVD Discs // Iconic Handle", status: "Cult Classic", iconicGames: ["Super Smash Bros. Melee", "Zelda: The Wind Waker", "Super Mario Sunshine", "Metroid Prime"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-8", name: "Nintendo 64 (N64)", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "1996", era: "5th Gen 64-Bit", type: "Home Console", specs: "NEC VR4300 64-Bit 93.75MHz // Silicon Graphics RCP // Analog Stick Controller", status: "Historic Legend", iconicGames: ["Super Mario 64", "Zelda: Ocarina of Time", "GoldenEye 007", "Banjo-Kazooie", "Star Fox 64"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-9", name: "Super Nintendo (SNES / Super Famicom)", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "1990", era: "4th Gen 16-Bit", type: "Home Console", specs: "Ricoh 5A22 16-bit // Sony SPC700 Audio // Mode 7 3D Scaling // Super FX Chip", status: "All-Time 16-Bit Peak", iconicGames: ["Super Mario World", "Chrono Trigger", "Zelda: A Link to the Past", "Super Metroid", "Donkey Kong Country"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-10", name: "NES (Nintendo Entertainment System / Famicom)", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "1983", era: "3rd Gen 8-Bit", type: "Home Console", specs: "Ricoh 2A03 8-bit // Saved the Video Game Industry after 1983 Crash", status: "Gaming Industry Savior", iconicGames: ["Super Mario Bros.", "The Legend of Zelda", "Metroid", "Mega Man 2", "Castlevania"], image: "/images/hero_cod.jpg" },
  { id: "dev-nin-11", name: "Nintendo Virtual Boy", categorySlug: "nintendo-home", categoryTitle: "Nintendo Home Consoles", year: "1995", era: "3D Stereoscopic Tabletop", type: "Tabletop 3D", specs: "Red Monochromatic LED Scanner // 3D Parallax Effect // Gunpei Yokoi Design", status: "Rare Collector Item", iconicGames: ["Virtual Boy Wario Land", "Mario's Tennis", "Red Alarm"], image: "/images/streaming_vr_gear.jpg" },

  // Sony Handhelds
  { id: "dev-psph-1", name: "PSP-1000 / 2000 / 3000 (PlayStation Portable)", categorySlug: "sony-handhelds", categoryTitle: "Sony Handhelds", year: "2004", era: "Handheld Legend", type: "Handheld Console", specs: "4.3\" 16:9 LCD // UMD Optical Drive // 333MHz MIPS R4000 // 80M+ Sold", status: "Legendary Classic", iconicGames: ["God of War: Chains of Olympus", "Crisis Core: Final Fantasy VII", "Monster Hunter Portable 3rd", "GTA: Liberty City Stories"], image: "/images/handheld_console.jpg" },
  { id: "dev-psph-2", name: "PSP Go (N1000)", categorySlug: "sony-handhelds", categoryTitle: "Sony Handhelds", year: "2009", era: "Digital Handheld", type: "Sliding Handheld", specs: "3.8\" Sliding Display // 16GB Flash Storage // Bluetooth 2.0 // Ultra-Pocketable", status: "Collector Classic", iconicGames: ["Metal Gear Solid: Peace Walker", "Daxter", "LocoRoco 2"], image: "/images/handheld_console.jpg" },
  { id: "dev-psph-3", name: "PlayStation Vita (PCH-1000 OLED)", categorySlug: "sony-handhelds", categoryTitle: "Sony Handhelds", year: "2011", era: "8th Gen Handheld", type: "OLED Handheld", specs: "5.0\" Samsung OLED Touchscreen // Dual Analog Sticks // Rear Touchpad // 3G/Wi-Fi", status: "Cult OLED Masterpiece", iconicGames: ["Persona 4 Golden", "Uncharted: Golden Abyss", "Gravity Rush", "Killzone: Mercenary"], image: "/images/handheld_console.jpg" },
  { id: "dev-psph-4", name: "PlayStation Vita (PCH-2000 Slim)", categorySlug: "sony-handhelds", categoryTitle: "Sony Handhelds", year: "2013", era: "8th Gen Handheld", type: "Slim Handheld", specs: "15% Lighter // 20% Thinner // Standard Micro-USB Charging // 1GB Internal Storage", status: "Collector Favorite", iconicGames: ["Tearaway", "Ys VIII: Lacrimosa of DANA", "Soul Sacrifice Delta"], image: "/images/handheld_console.jpg" },

  // Nintendo Handhelds
  { id: "dev-nh-1", name: "New Nintendo 3DS XL / New 3DS", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "2014", era: "Stereoscopic 3D", type: "Dual-Screen 3D Handheld", specs: "Super-Stable Face-Tracking 3D // C-Stick Nipple // Quad-Core CPU // NFC Amiibo", status: "Peak 3DS Model", iconicGames: ["Zelda: A Link Between Worlds", "Monster Hunter 4 Ultimate", "Pokemon Sun/Moon", "Xenoblade Chronicles 3D"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-2", name: "Nintendo 3DS / 3DS XL / 2DS / 2DS XL", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "2011", era: "Stereoscopic 3D", type: "Dual-Screen Handheld", specs: "Glasses-Free 3D // StreetPass / SpotPass // Circle Pad // 75M Sold", status: "Historic Legend", iconicGames: ["Fire Emblem: Awakening", "Super Mario 3D Land", "Luigi's Mansion: Dark Moon"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-3", name: "Nintendo DS Lite / DSi / DSi XL / Original DS", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "2004", era: "Dual-Screen Touch", type: "Dual-Screen Handheld", specs: "Touchscreen Stylus // Built-in Mic // GBA Slot-2 // 154M Second Best-Selling System", status: "All-Time Legend", iconicGames: ["Pokemon Platinum", "New Super Mario Bros.", "Mario Kart DS", "The World Ends With You", "Brain Age"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-4", name: "Game Boy Advance SP (AGS-101 Backlit / AGS-001)", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "2003", era: "32-Bit GBA", type: "Clamshell Handheld", specs: "Clamshell Folding Design // Brilliant AGS-101 Backlit Screen // Rechargeable Li-Ion", status: "All-Time Portable Perfection", iconicGames: ["Pokemon Emerald / FireRed", "The Legend of Zelda: The Minish Cap", "Metroid Fusion", "Golden Sun", "Castlevania: Aria of Sorrow"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-5", name: "Game Boy Advance (Original AGB-001)", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "2001", era: "32-Bit GBA", type: "Horizontal Handheld", specs: "32-bit ARM7TDMI 16.78MHz // 2.9\" TFT Screen // Ergonomic Horizontal Grip // 81M Sold", status: "Retro Legend", iconicGames: ["Advance Wars", "Mario & Luigi: Superstar Saga", "Pokemon Ruby/Sapphire"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-6", name: "Game Boy Color (GBC)", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "1998", era: "8-Bit Color", type: "Vertical Handheld", specs: "Sharp 8-Bit CPU 8MHz // 56 Simultaneous Colors // Infrared Comm Port", status: "Retro Icon", iconicGames: ["Pokemon Gold & Silver / Crystal", "The Legend of Zelda: Oracle of Ages/Seasons", "Super Mario Bros. Deluxe"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-7", name: "Game Boy (DMG-01 / Game Boy Pocket)", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "1989", era: "8-Bit Monochrome", type: "Monochrome Handheld", specs: "Gunpei Yokoi Design // Dot Matrix 4-Shade Green Screen // 30-Hour AA Battery Life", status: "Pioneering Legend", iconicGames: ["Tetris (1989)", "Pokemon Red & Blue", "The Legend of Zelda: Link's Awakening", "Super Mario Land 2"], image: "/images/handheld_console.jpg" },
  { id: "dev-nh-8", name: "Nintendo Game & Watch", categorySlug: "nintendo-handhelds", categoryTitle: "Nintendo Handhelds", year: "1980", era: "Segmented LCD", type: "Micro Handheld", specs: "Invented the Modern Cross D-Pad // Segmented Clock & Game Display // 43M Sold", status: "Historic Origin", iconicGames: ["Ball", "Donkey Kong (First D-Pad)", "Fire", "Super Mario Bros. Edition"], image: "/images/handheld_console.jpg" },

  // Sega Consoles
  { id: "dev-seg-1", name: "Sega Dreamcast", categorySlug: "sega-consoles", categoryTitle: "Sega Consoles (Retro)", year: "1998", era: "6th Gen 128-Bit", type: "Home Console", specs: "Hitachi SH-4 200MHz // PowerVR2 CLX2 // 1GB GD-ROM // Built-in 56k Modem // VMU Screen", status: "Cult Legend", iconicGames: ["Shenmue I & II", "Sonic Adventure 1 & 2", "Crazy Taxi", "Jet Set Radio", "Phantasy Star Online"], image: "/images/trending_elden.jpg" },
  { id: "dev-seg-2", name: "Sega Saturn", categorySlug: "sega-consoles", categoryTitle: "Sega Consoles (Retro)", year: "1994", era: "5th Gen 32-Bit", type: "Home Console", specs: "Dual Hitachi SH-2 CPUs // 2D Sprite Powerhouse // CD-ROM", status: "2D Masterpiece", iconicGames: ["Panzer Dragoon Saga", "NiGHTS into Dreams", "Virtua Fighter 2", "Radiant Silvergun", "Sega Rally"], image: "/images/trending_elden.jpg" },
  { id: "dev-seg-3", name: "Sega Genesis / Mega Drive (Model 1 / Model 2)", categorySlug: "sega-consoles", categoryTitle: "Sega Consoles (Retro)", year: "1988", era: "4th Gen 16-Bit", type: "Home Console", specs: "Motorola 68000 7.6MHz // Blast Processing // Yamaha YM2612 FM Audio // 30M+ Sold", status: "All-Time Rival Icon", iconicGames: ["Sonic the Hedgehog 2", "Streets of Rage 2", "Gunstar Heroes", "Shinobi III", "Phantasy Star IV"], image: "/images/trending_elden.jpg" },
  { id: "dev-seg-4", name: "Sega Game Gear", categorySlug: "sega-consoles", categoryTitle: "Sega Consoles (Retro)", year: "1990", era: "8-Bit Color Handheld", type: "Color Handheld", specs: "Full Color Backlit Screen (4096 Palette) // Zilog Z80 3.58MHz // TV Tuner Accessory", status: "Retro Classic", iconicGames: ["Sonic the Hedgehog: Triple Trouble", "Shinobi II", "Columns", "Defender of Oasis"], image: "/images/handheld_console.jpg" },
  { id: "dev-seg-5", name: "Sega Master System", categorySlug: "sega-consoles", categoryTitle: "Sega Consoles (Retro)", year: "1985", era: "3rd Gen 8-Bit", type: "Home Console", specs: "Zilog Z80 3.58MHz // Richer Color Palette than NES // Huge in Europe & Brazil", status: "Retro Icon", iconicGames: ["Alex Kidd in Miracle World", "Phantasy Star (Master System)", "Sonic 1 (8-Bit)", "Wonder Boy III"], image: "/images/trending_elden.jpg" },
  { id: "dev-seg-6", name: "Sega CD (Mega-CD) & Sega 32X", categorySlug: "sega-consoles", categoryTitle: "Sega Consoles (Retro)", year: "1991 / 1994", era: "16-Bit Add-ons", type: "Hardware Expansions", specs: "Hardware Scaling & Rotation ASIC // CD Audio // Dual 32-Bit SH-2 RISC Processors", status: "Cult Add-on", iconicGames: ["Sonic CD", "Snatcher", "Lunar: Silver Star Story", "Knuckles' Chaotix", "Virtua Racing Deluxe"], image: "/images/trending_elden.jpg" },

  // Other Retro Consoles
  { id: "dev-ret-1", name: "Neo Geo AES & Neo Geo MVS (SNK)", categorySlug: "retro-other", categoryTitle: "Other Retro Consoles", year: "1990", era: "24-Bit Arcade Console", type: "High-End Arcade System", specs: "Exact 1-to-1 Coin-Op Arcade Hardware at Home // 330 Mega Pro-Gear Spec // Massive 100MB+ Carts", status: "Crown Jewel of Retro", iconicGames: ["The King of Fighters '98", "Metal Slug 3", "Garou: Mark of the Wolves", "Samurai Shodown II", "Windjammers"], image: "/images/gaming_cables_accessories.jpg" },
  { id: "dev-ret-2", name: "Atari 2600 (VCS)", categorySlug: "retro-other", categoryTitle: "Other Retro Consoles", year: "1977", era: "2nd Gen 8-Bit", type: "Home Console", specs: "MOS 6507 1.19MHz // 128 Bytes RAM // Woodgrain Front // Popularized Interchangable ROM Cartridges", status: "Home Console Pioneer", iconicGames: ["Space Invaders", "Pitfall!", "Adventure", "Pac-Man (2600)", "River Raid", "Asteroids"], image: "/images/gaming_cables_accessories.jpg" },
  { id: "dev-ret-3", name: "TurboGrafx-16 (PC Engine - NEC / Hudson Soft)", categorySlug: "retro-other", categoryTitle: "Other Retro Consoles", year: "1987", era: "4th Gen 16-Bit GPU", type: "Home Console", specs: "HuCard Credit-Card Sized Cartridges // 16-Bit Graphics Chip // Pioneer of CD-ROM Expansion", status: "Cult Masterpiece", iconicGames: ["Castlevania: Rondo of Blood", "Bonk's Adventure", "Soldier Blade", "Blazing Lazers", "Ys Book I & II"], image: "/images/gaming_cables_accessories.jpg" },
  { id: "dev-ret-4", name: "Commodore 64 & Amiga 500", categorySlug: "retro-other", categoryTitle: "Other Retro Consoles", year: "1982 / 1987", era: "Microcomputer Gaming", type: "Home Computer Gaming", specs: "SID 6581 Sound Synthesizer Chip // Motorola 68000 // Best-Selling Single Computer Model (17M)", status: "European Gaming Legend", iconicGames: ["The Great Giana Sisters", "Sensible World of Soccer", "Lemmings", "Shadow of the Beast", "Maniac Mansion"], image: "/images/cyber_keyboard.jpg" },
  { id: "dev-ret-5", name: "3DO Interactive Multiplayer (Panasonic / GoldStar)", categorySlug: "retro-other", categoryTitle: "Other Retro Consoles", year: "1993", era: "5th Gen 32-Bit", type: "Home Console", specs: "ARM60 32-Bit CPU // Full Motion Video CD // Daisy-Chained Controllers", status: "Collector Item", iconicGames: ["Road Rash (3DO)", "The Need for Speed (Original)", "Super Street Fighter II Turbo", "Gex"], image: "/images/gaming_cables_accessories.jpg" },
  { id: "dev-ret-6", name: "Colecovision & Intellivision", categorySlug: "retro-other", categoryTitle: "Other Retro Consoles", year: "1982 / 1979", era: "2nd Gen", type: "Home Console", specs: "Arcade-Accurate Z80 Ports // Keypad Controllers with Overlays // Voice Synthesis", status: "Golden Era Classic", iconicGames: ["Donkey Kong (Coleco Port)", "Zaxxon", "Astrosmash", "Space Battle", "BurgerTime"], image: "/images/gaming_cables_accessories.jpg" },

  // Mobile
  { id: "dev-mob-1", name: "iPhone 15 Pro / 16 Pro Max", categorySlug: "mobile", categoryTitle: "Mobile Gaming", year: "2024", era: "Modern Mobile", type: "Flagship Smartphone", specs: "Apple A17 Pro / A18 Pro 3nm // Hardware Ray Tracing // Native AAA Ports // 120Hz ProMotion OLED", status: "Active Flagship", iconicGames: ["Resident Evil Village", "Death Stranding iOS", "Assassin's Creed Mirage", "Genshin Impact"], image: "/images/pro_controller.jpg" },
  { id: "dev-mob-2", name: "Android Gaming Phone (Snapdragon 8 Gen 3)", categorySlug: "mobile", categoryTitle: "Mobile Gaming", year: "2024", era: "Modern Mobile", type: "Flagship Smartphone", specs: "Snapdragon 8 Gen 3 // Adreno 750 // 165Hz AMOLED // Active Fan Cooling (RedMagic / ROG Phone 8)", status: "Active Esports", iconicGames: ["Warzone Mobile", "PUBG Mobile", "Honkai: Star Rail", "Call of Duty: Mobile"], image: "/images/pro_controller.jpg" },
  { id: "dev-mob-3", name: "iPad Pro (M2 / M4 120Hz Tandem OLED)", categorySlug: "mobile", categoryTitle: "Mobile Gaming", year: "2024", era: "Modern Tablet", type: "Flagship Tablet", specs: "Apple M4 10-Core GPU // Tandem OLED 1000 Nits // Hardware Mesh Shading & Ray Tracing", status: "Peak Tablet Gaming", iconicGames: ["Divinity: Original Sin 2", "Grid Autosport", "Alien: Isolation", "Civilization VI"], image: "/images/pro_controller.jpg" },
  { id: "dev-mob-4", name: "Backbone One (USB-C & Lightning)", categorySlug: "mobile", categoryTitle: "Mobile Gaming", year: "2023", era: "Mobile Accessory", type: "Snap-on Controller", specs: "Zero-Latency Direct Connection // 3.5mm Headphone Jack // Pass-through Charging // Capture Button", status: "Active Benchmark", iconicGames: ["Xbox Cloud Gaming", "PS Remote Play", "Call of Duty: Mobile", "Genshin Impact"], image: "/images/pro_controller.jpg" },
  { id: "dev-mob-5", name: "Razer Kishi Ultra / Kishi V2", categorySlug: "mobile", categoryTitle: "Mobile Gaming", year: "2024", era: "Mobile Accessory", type: "Full-Size Mobile Controller", specs: "Fits Phones & iPad Mini 8\" // Razer Sensa HD Haptics // Microswitch D-Pad // 3.5mm Audio", status: "Active Premium", iconicGames: ["GeForce NOW Cloud", "Diablo Immortal", "Dead Cells iOS"], image: "/images/pro_controller.jpg" },

  // Cloud Gaming
  { id: "dev-cld-1", name: "NVIDIA GeForce NOW Ultimate", categorySlug: "cloud-gaming", categoryTitle: "Cloud Gaming Platforms", year: "2024", era: "Cloud RTX 4080", type: "Cloud Streaming Service", specs: "GeForce RTX 4080 Pods // 4K or 1080p High-Refresh Rate Streaming // Reflex Ultra-Low Latency // Full Path Tracing", status: "Active Benchmark", iconicGames: ["Cyberpunk 2077 Path Tracing", "Alan Wake 2", "Black Myth: Wukong", "Baldur's Gate 3"], image: "/images/trending_esports.jpg" },
  { id: "dev-cld-2", name: "Xbox Cloud Gaming (xCloud)", categorySlug: "cloud-gaming", categoryTitle: "Cloud Gaming Platforms", year: "2024", era: "Cloud Series X", type: "Cloud Streaming Service", specs: "Custom Series X Server Blades // 100s of Game Pass Games on Any Screen // 1080p Streaming", status: "Active Standard", iconicGames: ["Forza Horizon 5", "Halo Infinite", "Starfield", "Sea of Thieves"], image: "/images/trending_esports.jpg" },
  { id: "dev-cld-3", name: "PlayStation Plus Cloud Streaming", categorySlug: "cloud-gaming", categoryTitle: "Cloud Gaming Platforms", year: "2024", era: "Cloud PS5", type: "Cloud Streaming Service", specs: "Direct 4K PS5 Title Cloud Streaming // PS3, PS2, PS1 Classic Streaming Catalog", status: "Active Premium", iconicGames: ["Spider-Man: Miles Morales", "Horizon Forbidden West", "Ratchet & Clank", "God of War III Remastered"], image: "/images/trending_spiderman.jpg" },
  { id: "dev-cld-4", name: "Amazon Luna", categorySlug: "cloud-gaming", categoryTitle: "Cloud Gaming Platforms", year: "2023", era: "Cloud AWS", type: "Cloud Streaming Service", specs: "Direct-to-Cloud Wi-Fi Controller // Luna Couch Co-op // Prime Gaming Channel Integration", status: "Active", iconicGames: ["Fortnite (Luna)", "Control", "Metro Exodus", "Resident Evil 2"], image: "/images/trending_esports.jpg" },

  // VR / AR
  { id: "dev-vr-1", name: "Meta Quest 3", categorySlug: "vr-ar", categoryTitle: "VR / AR Spatial Systems", year: "2023", era: "Spatial Mixed Reality", type: "Standalone & PC VR", specs: "Snapdragon XR2 Gen 2 // 4K+ Infinite Display Pancake Optics // Full-Color Passthrough 4K MR", status: "Active Best-Seller", iconicGames: ["Asgard's Wrath 2", "Beat Saber", "Batman: Arkham Shadow", "Resident Evil 4 VR", "Superhot VR"], image: "/images/streaming_vr_gear.jpg" },
  { id: "dev-vr-2", name: "PlayStation VR2 (PS VR2)", categorySlug: "vr-ar", categoryTitle: "VR / AR Spatial Systems", year: "2023", era: "9th Gen VR", type: "PS5 & PC VR Headset", specs: "4K HDR OLED Displays (2000x2040 per eye) // Eye Tracking Foveated Rendering // Headset Haptics", status: "Active Flagship", iconicGames: ["Horizon Call of the Mountain", "Gran Turismo 7 VR", "Resident Evil Village VR", "Synapse"], image: "/images/streaming_vr_gear.jpg" },
  { id: "dev-vr-3", name: "Valve Index", categorySlug: "vr-ar", categoryTitle: "VR / AR Spatial Systems", year: "2019", era: "PC VR Flagship", type: "Tethered PC VR", specs: "144Hz High-Refresh Dual LCD // Knuckles Controllers with Individual Finger Tracking // Base Stations", status: "PC VR Benchmark", iconicGames: ["Half-Life: Alyx", "Boneworks", "The Walking Dead: Saints & Sinners", "Blade & Sorcery"], image: "/images/streaming_vr_gear.jpg" },
  { id: "dev-vr-4", name: "Apple Vision Pro", categorySlug: "vr-ar", categoryTitle: "VR / AR Spatial Systems", year: "2024", era: "Spatial Computing", type: "Mixed Reality Spatial Computer", specs: "Dual Micro-OLED 4K Displays (23 Million Pixels) // M2 + R1 Spatial Chips // Eye & Hand Tracking", status: "Ultra-Premium", iconicGames: ["Spatial Apple Arcade Games", "Game Room", "Synth Riders Spatial", "Super Fruit Ninja"], image: "/images/streaming_vr_gear.jpg" },

  // China / Retro-Handheld Brands
  { id: "dev-rh-1", name: "Anbernic RG35XX / RG35XX Plus / RG35XX H", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2023/2024", era: "Linux Emulation", type: "Budget Handheld", specs: "3.5\" 640x480 IPS // Allwinner H700 Quad-Core // Dual OS Linux // Dual TF Card Slots", status: "Best-Selling Budget", iconicGames: ["PS1 (Crash Bandicoot)", "GBA (Pokemon Emerald)", "SNES (Chrono Trigger)", "Arcade CPS1/2"], image: "/images/handheld_console.jpg" },
  { id: "dev-rh-2", name: "Anbernic RG556 (OLED)", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2024", era: "Android Emulation", type: "OLED Handheld", specs: "5.48\" 1080p AMOLED // Unisoc T820 6nm // Hall-Effect Sticks // 5500mAh Battery", status: "Active PS2 Handheld", iconicGames: ["PS2 (God of War)", "GameCube (Smash Melee)", "Wii (Super Mario Galaxy)", "3DS Emulation"], image: "/images/handheld_console.jpg" },
  { id: "dev-rh-3", name: "Retroid Pocket 5 & Retroid Pocket 4 Pro", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2024", era: "Android Emulation", type: "Midrange Handheld", specs: "5.5\" 1080p OLED // Snapdragon 865 / Dimensity 1100 // Analog Triggers // Active Cooling", status: "Top Community Favorite", iconicGames: ["PS2 (Burnout 3: Takedown)", "GameCube (F-Zero GX)", "PSP (God of War 3x Upscaling)", "Switch Emulation"], image: "/images/handheld_console.jpg" },
  { id: "dev-rh-4", name: "Miyoo Mini & Miyoo Mini Plus (Miyoo Mini+)", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2022/2023", era: "Micro Linux Handheld", type: "Ultra-Pocketable", specs: "3.5\" 640x480 Laminated IPS // OnionOS Community Firmware // Pocket-sized Game Boy form", status: "Community Cult Hero", iconicGames: ["Pokemon GBA Romhacks (Unbound)", "Castlevania: Symphony of the Night (PS1)", "SNES JRPGs"], image: "/images/handheld_console.jpg" },
  { id: "dev-rh-5", name: "Analogue Pocket (FPGA)", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2021", era: "Hardware FPGA", type: "FPGA Handheld", specs: "Cycle-Accurate Dual Altera Cyclone FPGAs // 3.5\" 1600x1440 615 PPI Display // Original Cartridge Slot", status: "Audiophile-Grade Retro", iconicGames: ["Original Game Boy / GBC / GBA / Game Gear / Neo Geo Pocket Physical Carts"], image: "/images/handheld_console.jpg" },
  { id: "dev-rh-6", name: "Ayn Odin 2 / Odin 2 Mini (Snapdragon 8 Gen 2)", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2023/2024", era: "High-End Android", type: "Flagship Emulation Handheld", specs: "Snapdragon 8 Gen 2 // 8000mAh Battery // Hall Sticks // Plays PS2 & GameCube Emulation at 1080p", status: "Undisputed Emulation King", iconicGames: ["PS2 (Shadow of the Colossus 3x)", "GameCube (Resident Evil 4)", "Wii (Zelda Twilight Princess)", "Switch Games"], image: "/images/handheld_console.jpg" },
  { id: "dev-rh-7", name: "Trimui Smart Pro", categorySlug: "retro-handheld-brands", categoryTitle: "China / Retro-Handheld Brands", year: "2023", era: "Linux 16:9 Handheld", type: "Budget 16:9 Handheld", specs: "4.96\" 720p 16:9 IPS // Allwinner A133 Plus // RGB Stick Lighting // 5000mAh Battery", status: "Active Budget PSP", iconicGames: ["PSP at 2x Resolution", "GBA Pixel-Perfect", "Arcade Neo Geo", "PS1"], image: "/images/handheld_console.jpg" },

  // Arcade
  { id: "dev-arc-1", name: "Arcade1Up 3/4 Scale Cabinets", categorySlug: "arcade", categoryTitle: "Arcade & Custom Pinball", year: "2020+", era: "Home Arcade", type: "3/4 Scale Home Arcade", specs: "17\" LCD // Real Arcade Sticks & Buttons // Light-up Marquee // Riser Included", status: "Active Home Arcade", iconicGames: ["Street Fighter II Turbo", "Mortal Kombat II", "Ms. Pac-Man", "NBA Jam", "Teenage Mutant Ninja Turtles"], image: "/images/gaming_monitor.jpg" },
  { id: "dev-arc-2", name: "Custom MAME Arcade Bartop / Full Cabinet", categorySlug: "arcade", categoryTitle: "Arcade & Custom Pinball", year: "Custom", era: "Custom DIY", type: "MAME Multi-Game Cabinet", specs: "Sanwa JLF Joysticks & Buttons // Raspberry Pi or PC Core // 10,000+ Arcade ROM Compatibility", status: "Enthusiast Custom", iconicGames: ["Metal Slug", "The Simpsons Arcade", "Marvel vs. Capcom 2", "Sunset Riders", "Galaga"], image: "/images/gaming_monitor.jpg" },
  { id: "dev-arc-3", name: "Digital Virtual Pinball Machine (4K VPin)", categorySlug: "arcade", categoryTitle: "Arcade & Custom Pinball", year: "Custom", era: "Digital Pinball", type: "Virtual Pinball Table", specs: "43\" 4K 120Hz Playfield // Solenoid Force Feedback Bumpers // Real Analog Plunger // DMD Score Display", status: "Enthusiast Simulation", iconicGames: ["Medieval Madness", "The Addams Family Pinball", "Attack from Mars", "Twilight Zone Pinball"], image: "/images/gaming_monitor.jpg" },

  // Smart TV & Streaming
  { id: "dev-stv-1", name: "NVIDIA Shield TV Pro", categorySlug: "smart-tv-streaming", categoryTitle: "Smart TV & Streaming Devices", year: "2019", era: "Tegra X1+ Streaming", type: "Streaming Micro-Console", specs: "NVIDIA Tegra X1+ // 4K AI Upscaling // Dolby Vision & Atmos // GeForce NOW 4K Streaming", status: "Active Living Room King", iconicGames: ["GeForce NOW 4K Stream", "Plex Server", "RetroArch Android Emulation", "Half-Life 2 Android Native"], image: "/images/gaming_monitor.jpg" },
  { id: "dev-stv-2", name: "Samsung Gaming Hub (Smart TV)", categorySlug: "smart-tv-streaming", categoryTitle: "Smart TV & Streaming Devices", year: "2023+", era: "Built-in TV Cloud", type: "Smart TV Cloud App", specs: "No Console Needed // Direct Bluetooth Gamepad Pairing // Xbox Cloud + GeForce NOW Built-in", status: "Active Mainstream", iconicGames: ["Xbox Cloud Gaming on 65\" 4K TV", "Fortnite Cloud", "Luna Cloud"], image: "/images/gaming_monitor.jpg" },
  { id: "dev-stv-3", name: "Apple TV 4K (A15 Bionic)", categorySlug: "smart-tv-streaming", categoryTitle: "Smart TV & Streaming Devices", year: "2022", era: "Apple Silicon TV", type: "Micro-Console", specs: "A15 Bionic 5-Core GPU // Apple Arcade Subscription Hub // DualSense & Xbox Controller Support", status: "Active Family Hub", iconicGames: ["Oceanhorn 2", "NBA 2K Arcade Edition", "Sayonara Wild Hearts", "Sneaky Sasquatch"], image: "/images/gaming_monitor.jpg" },
  { id: "dev-stv-4", name: "Amazon Fire TV Stick 4K Max", categorySlug: "smart-tv-streaming", categoryTitle: "Smart TV & Streaming Devices", year: "2023", era: "Compact Streaming Stick", type: "HDMI Streaming Dongle", specs: "Wi-Fi 6E // Official Xbox Cloud Gaming App // Luna Cloud Streaming // Quad-Core 2.0GHz", status: "Active Value Streamer", iconicGames: ["Xbox Game Pass Ultimate Cloud", "Amazon Luna Prime Drops"], image: "/images/gaming_monitor.jpg" },

  // Misc & Niche
  { id: "dev-msc-1", name: "Panic Playdate", categorySlug: "misc-other", categoryTitle: "Misc & Niche Gaming Systems", year: "2022", era: "Indie 1-Bit Crank Handheld", type: "Experimental Handheld", specs: "High-Contrast 400x240 1-Bit Memory LCD // Physical Analog Mechanical Crank // 24 Free Season Games", status: "Indie Cult Darling", iconicGames: ["Crankin's Time Travel Adventure", "Whitewater Wipeout", "Saturday Edition", "Casual Birder"], image: "/images/cyber_keyboard.jpg" },
  { id: "dev-msc-2", name: "Raspberry Pi 5 (RetroPie / Recalbox)", categorySlug: "misc-other", categoryTitle: "Misc & Niche Gaming Systems", year: "2023", era: "Single-Board Computer", type: "DIY Emulation Rig", specs: "Broadcom BCM2712 Quad-Core 2.4GHz // 8GB LPDDR4X // Plays Dreamcast, N64, PSP, PS1 at Full Speed", status: "DIY Maker Standard", iconicGames: ["Complete Retro Multi-Console Library (NES to Dreamcast)"], image: "/images/battlestation_pc.jpg" },
  { id: "dev-msc-3", name: "Evercade EXP & Evercade VS-R (Blaze Entertainment)", categorySlug: "misc-other", categoryTitle: "Misc & Niche Gaming Systems", year: "2022+", era: "Physical Cartridge Retro", type: "Cartridge System", specs: "Official Licensed Physical Cartridge Ecosystem // TATE Vertical Screen Mode // Dual Controller Ports", status: "Active Cartridge Retro", iconicGames: ["Capcom Arcade Collection (Street Fighter II, Ghouls 'n Ghosts)", "Irem Arcade", "Namco Museum", "Data East Classics"], image: "/images/handheld_console.jpg" }
];

// ALL PRODUCTS & PERIPHERALS (Keyboards, Mice, Monitors, Audio, Cables, Desks, Controllers, Rigs)
// Products come from productCatalog.js (generated from the product master sheet).
import { ALL_PRODUCTS as CATALOG_PRODUCTS, PRODUCT_CATEGORIES } from './productCatalog.js';
export { PRODUCT_CATEGORIES };

// The sheet gives every product its category's shared photo. Until a real photo is
// set in the CMS, show the product's own card (scripts/generate-product-cards.mjs).
const SHARED_CATEGORY_PHOTOS = new Set(PRODUCT_CATEGORIES.map((c) => c.image));
export const ALL_PRODUCTS = CATALOG_PRODUCTS.map((p) =>
  SHARED_CATEGORY_PHOTOS.has(p.image) ? { ...p, image: `/images/products/${p.slug}.webp` } : p
);

export const ALL_BLOGS = [
  {
    id: "blog-1",
    title: "Call of Duty: Black Ops 6 – Everything We Know So Far",
    subtitle: "Omnidirectional Movement, Round-Based Zombies & Best Weapons Breakdown",
    category: "Guides & Deals",
    categorySlug: "deals",
    date: "May 20, 2024",
    readTime: "7 min read",
    author: "Omar Abobakar",
    image: "/images/hero_cod.jpg",
    featured: true,
    summary: "A deep dive into Black Ops 6 omnidirectional sprinting and sliding mechanics, campaign story details, multiplayer weapon balancing, and recommended PC hardware requirements.",
    content: `
### The Next Evolution of Fast-Paced Combat
Treyarch and Raven Software have officially pulled back the curtain on *Call of Duty: Black Ops 6*. Set in the early 1990s during the aftermath of the Cold War, the title introduces what the developers call **Omnidirectional Movement**—allowing players to sprint, slide, and dive in any direction (forward, backward, sideways) seamlessly.

### How to Prepare Your Gaming Setup
To fully take advantage of the fluid movement mechanics in Black Ops 6, having low input latency hardware is essential:
- **Mouse Recommendation:** For rapid 180-degree turn flicks, we recommend the [Razer DeathAdder V3 Pro](internal-link:prod-102) with its 63g ultralight chassis and 30K sensor.
- **Keyboard Recommendation:** For instant direction changes with rapid trigger stops, check out the [Logitech G Pro X TKL](internal-link:prod-101) paired with a [Pro Coiled Aviator Cable](internal-link:prod-109).
- **Audio Advantage:** Footstep clarity is critical in Search and Destroy; the [HyperX Cloud III](internal-link:prod-104) or [USB-C High-Resolution Gaming DAC](internal-link:prod-113) delivers superior acoustic spatial awareness.

### System Requirements & Performance Targets
Black Ops 6 targets high-frame-rate output on PlayStation 5 and Xbox Series X, with PC users able to utilize high-refresh 1440p displays like the [ROG Swift 360Hz QD-OLED](internal-link:prod-103).
    `,
    relatedProductIds: ["prod-101", "prod-102", "prod-103", "prod-104", "prod-109", "prod-110"],
    faqs: [
      { q: "Will Black Ops 6 launch on Xbox Game Pass on Day One?", a: "Yes! Microsoft has confirmed that Call of Duty: Black Ops 6 will be available day one on Xbox Game Pass Ultimate and PC Game Pass." },
      { q: "What is the recommended GPU for 1440p high-refresh rate play?", a: "We recommend at least an NVIDIA RTX 4070 or AMD Radeon RX 7800 XT for stable high-framerate 1440p competitive play." }
    ]
  },
  {
    id: "blog-2",
    title: "Elden Ring: Shadow of the Erdtree – Full Hardware & Boss Guide",
    subtitle: "Performance Benchmarks Across PC, PS5 and Handhelds",
    category: "PC / Handheld PC",
    categorySlug: "pc-handheld-pc",
    date: "May 19, 2024",
    readTime: "9 min read",
    author: "Alex Vance",
    image: "/images/trending_elden.jpg",
    featured: true,
    summary: "Comprehensive technical analysis of the Land of Shadow expansion, testing ray tracing frame pacing, difficulty curves, and optimal controller setups.",
    content: `
### Exploring the Land of Shadow
FromSoftware's *Shadow of the Erdtree* expansion is the largest DLC ever crafted by the studio. Featuring vast layered vertical biomes, challenging remembrance bosses, and over 100 new weapons.

### Controller Precision for Tight Dodge Windows
Boss timings in the expansion demand millimeter-precise inputs without joystick drift. Using the [Apex Pro Hall-Effect Controller](internal-link:prod-106) with a [Dual Controller Fast Dock](internal-link:prod-114) provides immediate responsiveness on rear dodge paddles.

### Handheld Performance on the Go
For gamers exploring the Realm of Shadow portably, the [7-Inch OLED Gaming Handheld PC](internal-link:prod-107) renders the expansion with stunning true blacks on its HDR OLED panel.
    `,
    relatedProductIds: ["prod-106", "prod-107", "prod-108", "prod-114"],
    faqs: [
      { q: "Can I play Shadow of the Erdtree on Steam Deck / Handhelds?", a: "Yes, setting graphics to Medium with 40Hz refresh rate cap provides a fantastic balance of visual fidelity and battery life." }
    ]
  },
  {
    id: "blog-3",
    title: "Spider-Man 2 on PS5 – Performance & Ray Tracing Masterclass",
    subtitle: "How Insomniac Mastered High Frame Rate Ray Tracing on Modern Consoles",
    category: "Sony PlayStation",
    categorySlug: "sony-playstation",
    date: "May 18, 2024",
    readTime: "6 min read",
    author: "Sarah Kai",
    image: "/images/trending_spiderman.jpg",
    featured: false,
    summary: "Deep dive into 40Hz Fidelity and 60Hz Performance Ray Tracing modes in Marvel's Spider-Man 2 on PlayStation 5.",
    content: `
### Next-Gen City Traversals
*Marvel's Spider-Man 2* showcases the true potential of high-speed SSD streaming and hardware ray tracing. Peter Parker and Miles Morales web-wing across Manhattan at near 70 MPH with instantaneous asset loading.

### Audio & Visual Immersion
Paired with DTS 3D spatial audio on headsets like the [HyperX Cloud III](internal-link:prod-104) and certified [8K HDMI 2.1 Cables](internal-link:prod-112), ambient siren echoes and atmospheric city chatter wrap around the player with cinematic depth.
    `,
    relatedProductIds: ["prod-104", "prod-103", "prod-112"],
    faqs: [
      { q: "Is the 40Hz mode better than 60Hz?", a: "On 120Hz displays with VRR, the 40Hz mode provides the crisp native 4K ray tracing details with significantly smoother frame times than standard 30Hz mode." }
    ]
  },
  {
    id: "blog-4",
    title: "Best Gaming Laptops Under $1000 in 2024 – Buyer's Guide",
    subtitle: "Top Budget Value Picks with RTX 4050 & 4060 GPUs",
    category: "Guides & Deals",
    categorySlug: "deals",
    date: "May 10, 2024",
    readTime: "8 min read",
    author: "Derrick Vance",
    image: "/images/gaming_laptop.jpg",
    featured: false,
    summary: "Finding maximum gaming performance value on a budget. We benchmark thermal headroom, battery efficiency, and screen color accuracy under $1000.",
    content: `
### Budget Laptop Revolution
Gone are the days when a budget gaming laptop meant thermal throttling and washed-out 45% NTSC displays. The latest generation of sub-$1000 gaming laptops pack Ada Lovelace RTX 4050 and RTX 4060 graphics chips with DLSS 3 Frame Generation support.
    `,
    relatedProductIds: ["prod-101", "prod-102", "prod-104", "prod-116"],
    faqs: [
      { q: "Can a $1000 gaming laptop run modern AAA games at 1080p high settings?", a: "Yes! With DLSS 3 Frame Generation enabled, modern RTX 4060 budget laptops easily deliver smooth performance on High settings in Cyberpunk 2077 and Black Ops 6." }
    ]
  }
];

// 3D TESTIMONIAL COMMUNITY REVIEWS DATA
export const COMMUNITY_TESTIMONIALS = [
  {
    id: "test-1",
    name: "Marcus 'Viper' Vance",
    role: "Valorant Radiant Player",
    avatar: "VV",
    rating: 5,
    tag: "PRO ESPORTS",
    comment: "Run On Console is the only site that actually measures 8000Hz polling rate and click debounce latency with oscilloscope accuracy. Bought the DeathAdder V3 Pro and PTFE Skates through their link!",
    gearMentioned: "Razer DeathAdder V3 Pro"
  },
  {
    id: "test-2",
    name: "Elena Rostova",
    role: "Hardware & Acoustic Modder",
    avatar: "ER",
    rating: 5,
    tag: "KEYBOARD ENTHUSIAST",
    comment: "Their breakdown on custom coiled aviator cables and switch lubing is unmatched. The affiliate pricing tracker helped me snag the Logitech G Pro X TKL on a 15% discount.",
    gearMentioned: "Coiled Aviator Cable"
  },
  {
    id: "test-3",
    name: "Tariq Mahmood",
    role: "Sim Racer & Rig Builder",
    avatar: "TM",
    rating: 5,
    tag: "BATTLESTATION BUILDER",
    comment: "The 360Hz QD-OLED review saved me hundreds. Honest pros and cons without sponsored bias. Run On Console has become my homepage whenever new hardware drops.",
    gearMentioned: "ROG Swift 360Hz OLED"
  }
];

// PAGE FAQS
export const PAGE_FAQS = {
  home: [
    {
      q: "What is Run On Console and how do you test gaming platforms?",
      a: "Run On Console is an independent gaming hardware, platform, and gear intelligence lab. Every gaming platform (PC, Laptop, PS5, Xbox, Handhelds, VR, Retro) and peripheral undergoes weeks of testing with oscilloscope latency probes, acoustic analyzers, and frametime benchmarks."
    },
    {
      q: "How do your affiliate links work and will it cost me extra?",
      a: "No extra cost! When you click our affiliate links to Amazon, Best Buy, or manufacturer stores, we may receive a small referral commission that directly supports our independent lab testing equipment."
    },
    {
      q: "How often do you update prices and deal discounts?",
      a: "Our affiliate pricing and discount badges are verified daily so you always see accurate in-stock availability and verified lowest prices."
    }
  ],
  categories: [
    {
      q: "What gaming platforms and eras are covered on Run On Console?",
      a: "Our platform directory covers 15 comprehensive gaming categories and over 100+ iconic hardware devices: from 8-bit NES and Atari 2600 to modern PC Battlestations, PS5 Pro, Xbox Series X, OLED Handhelds, VR/AR, and China Emulation engines (Anbernic/Retroid/Miyoo/Odin)."
    },
    {
      q: "Can I search for specific devices like Miyoo Mini or PS Vita?",
      a: "Yes! Use our interactive Device Search & Filter Engine to instantly filter by device name, era, generation, and form-factor."
    },
    {
      q: "What are Hall-Effect magnetic controllers?",
      a: "Hall-Effect controllers use contactless magnets rather than physical friction wipers to track analog stick and trigger inputs, making them highly resistant to stick drift."
    }
  ],
  products: [
    {
      q: "How are the products on Run On Console organised?",
      a: "Products are grouped by category (keyboards, mice, headsets and audio, speakers, monitors and graphics cards). Each one shows what it is best for, such as competitive play, wireless use or a tight budget, along with its key specs."
    },
    {
      q: "Where do the Amazon buttons go?",
      a: "They open Amazon, where you can see the current price, stock and seller. Prices and availability change often, so check the final price on Amazon before you buy."
    },
    {
      q: "Does Run On Console earn from these links?",
      a: "Some links are affiliate links. If you buy through them, we may earn a small commission at no extra cost to you. Details are on our policy page."
    },
    {
      q: "Can I compare products side by side?",
      a: "Yes. Use the compare button on any product card or product page to add up to 3 products to the comparison drawer."
    }
  ],
  blogs: [
    {
      q: "Can I request a specific game or platform review?",
      a: "Yes! You can use our Contact Page to submit requests for upcoming games, peripherals, or custom rig build guides."
    },
    {
      q: "Do your blog articles include do-follow internal links and specs?",
      a: "Yes, every blog post cross-references relevant hardware spec sheets, buying guides, and verified retailer links."
    },
    {
      q: "How do you rate products in your reviews?",
      a: "We score gear out of 10.0 (ROC Score) and 5.0 (Star Rating) based on Build Quality, Sensor/Audio Performance, Ergonomics, and Long-Term Value."
    }
  ],
  contact: [
    {
      q: "How can brands and manufacturers submit hardware for lab review?",
      a: "Hardware manufacturers and PR agencies can reach out directly via our Contact form under the 'Hardware Review Submission' inquiry type."
    },
    {
      q: "How quickly does the editorial team respond to inquiries?",
      a: "We aim to respond to all general inquiries, partnership proposals, and reader questions within 24 to 48 business hours."
    }
  ],
  compatibility: [
    {
      q: "What if my PC or device does not meet the minimum specs for games like GTA 5 or Black Ops 6?",
      a: "If your current PC or laptop cannot run the game natively, you have 4 proven options:\n\n1. 🏆 **Hardware Upgrade or Switch (Best Option):** Upgrade your GPU, RAM (to 16GB), and switch to a fast NVMe SSD, or pick up a value-packed modern console like an Xbox Series S ($299), PS5 Digital, or Steam Deck OLED.\n\n2. ☁️ **Cloud Gaming (Zero Download / Any Device):** Use NVIDIA GeForce NOW or Xbox Cloud Gaming to stream the full game in high resolution directly to any low-end office laptop, tablet, Smart TV, or smartphone with zero high-end specs required.\n\n3. 💾 **Copying Game Files via External Hard Drive, USB 3.2, or Disc:** If your internet connection is slow for massive 100GB+ downloads, you can copy the pre-installed game backup files directly from a friend's USB flash drive, external hard drive, or physical disc into your Steam/Epic/Rockstar directory, then click 'Verify Game Files' to start playing immediately.\n\n4. 🕹️ **Casual Fun Options: Community Servers, Mobile APK Ports & Low-End Mods:** For casual gaming with friends on older PCs or phones, you can play optimized mobile APK ports (like GTA San Andreas Definitive on Android, Xash3D for CS 1.6), join lightweight community servers (SAMP for GTA), or install custom low-spec configuration tweaks (LowSpecGamer mods, FSR 3 / DLSS scaling)."
    },
    {
      q: "How do I know if a game will run smoothly on handheld consoles like Steam Deck or ROG Ally?",
      a: "Look for our 'Verified Handheld' badge. Games like GTA 5, Elden Ring, and Skyrim are verified to run smoothly on Steam Deck and ROG Ally using modern FSR 2.2 / XeSS resolution scaling."
    },
    {
      q: "Why do modern games require NVMe SSDs instead of traditional HDDs?",
      a: "Modern open-world games like  Spider-Man 2, and Cyberpunk 2077 stream high-resolution textures in real-time. Traditional mechanical HDDs cause texture pop-in and stuttering, whereas NVMe SSDs stream data at 5000+ MB/s seamlessly."
    },
    {
      q: "Can I play multiplayer games across PC, PlayStation, and Xbox simultaneously?",
      a: "Yes! Modern titles like Call of Duty: Black Ops 6, Fortnite, and Minecraft support full Cross-Play and Cross-Progression, allowing you to party up with friends regardless of their hardware platform."
    }
  ]
};

export const POPULAR_POSTS = [
  {
    id: "pop-1",
    rank: 1,
    title: "Best Gaming Keyboards for Valorant & CS2",
    date: "May 20, 2024",
    image: "/images/cyber_keyboard.jpg"
  },
  {
    id: "pop-2",
    rank: 2,
    title: "Top 5 Ultralight Gaming Mice Tested & Ranked",
    date: "May 19, 2024",
    image: "/images/apex_mouse.jpg"
  },
  {
    id: "pop-3",
    rank: 3,
    title: "360Hz QD-OLED vs Fast IPS – Which Is Best for Esports?",
    date: "May 18, 2024",
    image: "/images/gaming_monitor.jpg"
  },
  {
    id: "pop-4",
    rank: 4,
    title: "Are Custom Coiled Aviator Cables Worth the Upgrade?",
    date: "May 15, 2024",
    image: "/images/gaming_cables_accessories.jpg"
  }
];
