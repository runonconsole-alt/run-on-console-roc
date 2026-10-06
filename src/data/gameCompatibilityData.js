// GAME COMPATIBILITY MATRIX - EXPANDED WITH CLASSIC, MODERN & UPCOMING 2025/2026 TITLES
export const GAME_COMPATIBILITY_DATA = [
  // 1. GRAND THEFT AUTO V (GTA 5 / GTA Online) - EXPLICIT USER HIGHLIGHT
  {
    id: "game-gta-5",
    gameTitle: "Grand Theft Auto V (GTA 5 & GTA Online)",
    era: "modern",
    eraLabel: "Modern Blockbuster",
    genre: "Open World Action Crime",
    platforms: [
      "PC (Steam/Epic/Rockstar)", 
      "PS5 (Expanded & Enhanced 4K High Refresh)", 
      "PS4 / PS4 Pro", 
      "PS3 (Original 2013)", 
      "Xbox Series X/S (Fidelity Ray Tracing Mode)", 
      "Xbox One / One X", 
      "Xbox 360", 
      "Steam Deck / Steam Deck OLED (Verified Handheld Performance)", 
      "ROG Ally / Legion Go (Full HD Handheld Performance)", 
      "Cloud (GeForce NOW via Boosteroid / Xbox Cloud)"
    ],
    fpsTarget: "High-Resolution Ray Tracing (PC/Console) | Smooth Handheld Performance",
    minSpecs: "Intel Core 2 Quad Q6600 / 4GB RAM / NVIDIA 9800 GT 1GB / 110GB Storage",
    recommendedSpecs: "Core i5-12400F / 16GB RAM / RTX 3060 12GB / NVMe SSD",
    compatibilityVerdict: "Broadly Compatible across Multiple Hardware Platforms",
    compatibilityScore: "10/10 Universal",
    recommendedGear: "Apex Pro Wireless Controller + 144Hz Gaming Monitor",
    categorySlug: "pc-handheld-pc",
    status: "Verified Platinum on All Devices",
    coverImage: "/images/gta5_cover.jpg",
    lowEndAlternatives: "Can copy backup files via USB 3.2 External HDD/SSD, play via GeForce NOW Cloud, or run GTA San Andreas / Vice City on ultra low-end PCs."
  },

  // 2. GRAND THEFT AUTO VI (GTA 6)
  {
    id: "game-up-1",
    gameTitle: "Grand Theft Auto VI (GTA 6)",
    era: "upcoming",
    eraLabel: "Upcoming 2025/2026",
    genre: "Next-Gen Open World Action",
    platforms: [
      "PS5", 
      "PS5 Pro (Enhanced PSSR High-Resolution Output)", 
      "Xbox Series X", 
      "Xbox Series S (Target 1080p Resolution Mode)", 
      "PC (Post-Console Launch)",
      "GeForce NOW (Expected Day 1 PC Cloud)"
    ],
    fpsTarget: "High-Resolution Dynamic / Ray-Traced Fidelity Mode",
    minSpecs: "Anticipated: RTX 4070 / Ryzen 7 7800X3D / 32GB RAM / Gen4 NVMe SSD (150GB+)",
    recommendedSpecs: "RTX 4080 Super / Core i7-14700K / 32GB DDR5 / 2TB NVMe SSD",
    compatibilityVerdict: "PS5, PS5 Pro & Xbox Series X/S Confirmed; PC & Cloud post-launch",
    compatibilityScore: "9th Gen Consoles Only at Launch",
    recommendedGear: "custom-rtx4090-desktop RTX 4090 Battlestation + PS5 Pro DualSense Edge",
    categorySlug: "sony-playstation",
    status: "Confirmed Release Fall 2025",
    coverImage: "/images/gta6_hero.jpg",
    lowEndAlternatives: "Upgrade to PS5 / Xbox Series S, or play GTA V / modded FiveM servers until cloud streaming launches."
  },

  // 3. RED DEAD REDEMPTION 2 (RDR2)
  {
    id: "game-rdr2",
    gameTitle: "Red Dead Redemption 2 & Online",
    era: "modern",
    eraLabel: "Masterpiece",
    genre: "Open World Western RPG",
    platforms: [
      "PC Desktop / Laptop", 
      "PS5 (via Backwards Backwards Compatibility Mode)", 
      "PS4 / PS4 Pro", 
      "Xbox Series X/S", 
      "Xbox One / One X", 
      "Steam Deck OLED (Verified Handheld Preset)", 
      "ROG Ally X (FSR Resolution Scaling)"
    ],
    fpsTarget: "High-Resolution DLSS Scaling (PC) | Smooth Handheld Preset",
    minSpecs: "Core i5-2500K / 8GB RAM / GTX 770 2GB / 150GB Storage",
    recommendedSpecs: "Ryzen 5 5600X / 16GB RAM / RTX 3070 8GB / Fast NVMe SSD",
    compatibilityVerdict: "Runs on all modern PC and 8th/9th Gen Consoles",
    compatibilityScore: "9.5/10 High Parity",
    recommendedGear: "Apex Pro Wireless Gamepad + HyperX Cloud III Headset",
    categorySlug: "pc-handheld-pc",
    status: "Verified Flawless",
    coverImage: "/images/rdr2_cover.jpg",
    lowEndAlternatives: "Red Dead Redemption 1 Remaster on Switch, PS4, or PC (runs on budget laptops)."
  },

  // 4. CALL OF DUTY: BLACK OPS 6
  {
    id: "game-mod-1",
    gameTitle: "Call of Duty: Black Ops 6 & Warzone",
    era: "modern",
    eraLabel: "Modern Hit 2024",
    genre: "Fast-Paced First-Person Shooter",
    platforms: [
      "PC Desktop (DirectX 12)", 
      "Gaming Laptop", 
      "PS5", 
      "PS4", 
      "Xbox Series X/S", 
      "Xbox One", 
      "Xbox Cloud Gaming", 
      "GeForce NOW (High Refresh Rate Cloud Streaming)"
    ],
    fpsTarget: "120 FPS 4K (Consoles) / 240+ FPS 1440p Tournament (PC)",
    minSpecs: "Core i5-6600 / 8GB RAM / GTX 960 or GTX 1650 / 128GB SSD",
    recommendedSpecs: "Ryzen 5 7600X / 16GB RAM / RTX 4070 / 240Hz OLED",
    compatibilityVerdict: "Cross-Play across PC, PlayStation, Xbox, and GeForce NOW Cloud",
    compatibilityScore: "10/10 Universal Crossplay",
    recommendedGear: "Logitech G Pro X TKL + Razer DeathAdder V3 Pro 8000Hz",
    categorySlug: "pc-handheld-pc",
    status: "Fully Verified",
    coverImage: "/images/call_of_duty_warzone.jpg",
    lowEndAlternatives: "Use Xbox Cloud Gaming / GeForce NOW to stream without downloading 150GB, or play Warzone Mobile APK on Android/iOS."
  },

  // 5. CYBERPUNK 2077: PHANTOM LIBERTY
  {
    id: "game-mod-3",
    gameTitle: "Cyberpunk 2077: Phantom Liberty",
    era: "modern",
    eraLabel: "Visual Benchmark",
    genre: "Sci-Fi Cyberpunk RPG",
    platforms: [
      "PC Desktop (Full Path Tracing)", 
      "Gaming Laptop (DLSS 3.5)", 
      "PS5 (Performance Mode / Ray Tracing Mode)", 
      "Xbox Series X", 
      "Xbox Series S (Dynamic Resolution Mode)", 
      "GeForce NOW (Ultimate Cloud Performance Mode)", 
      "Steam Deck (Optimized Handheld Preset)"
    ],
    fpsTarget: "Path Tracing Overdrive / DLSS Frame Generation Mode",
    minSpecs: "Core i7-6700 / 12GB RAM / GTX 1060 6GB / 70GB SSD (SSD Required)",
    recommendedSpecs: "Core i7-14700K / 32GB RAM / RTX 4080 / Gen4 SSD",
    compatibilityVerdict: "Available on PC, PS5, Xbox Series & GeForce NOW",
    compatibilityScore: "9/10 Next-Gen Only",
    recommendedGear: "custom-rtx4090-desktop RTX 4090 Battlestation Rig + 4K OLED Monitor",
    categorySlug: "pc-handheld-pc",
    status: "Fully Verified",
    coverImage: "/images/cyberpunk2077.jpg",
    lowEndAlternatives: "Stream over NVIDIA GeForce NOW at max settings on any old laptop or phone."
  },

  // 6. ELDEN RING & SHADOW OF THE ERDTREE
  {
    id: "game-mod-2",
    gameTitle: "Elden Ring: Shadow of the Erdtree",
    era: "modern",
    eraLabel: "GOTY Masterpiece",
    genre: "Dark Fantasy Action RPG Souls-like",
    platforms: [
      "PC Desktop", 
      "Gaming Laptop", 
      "PS5", 
      "PS4 / PS4 Pro", 
      "Xbox Series X/S", 
      "Xbox One", 
      "Steam Deck (Verified Handheld Performance)", 
      "ROG Ally / Legion Go (Smooth Handheld Performance)"
    ],
    fpsTarget: "High-Resolution Ray Tracing / Smooth Handheld Output",
    minSpecs: "Core i5-8400 / 12GB RAM / GTX 1060 3GB / 60GB Storage",
    recommendedSpecs: "Core i7-12700K / 16GB RAM / RTX 3070 8GB / SSD",
    compatibilityVerdict: "Flawless support across PC, PlayStation, Xbox, and Handhelds",
    compatibilityScore: "10/10 Verified",
    recommendedGear: "Apex Pro Wireless Controller + ROG 360Hz OLED Monitor",
    categorySlug: "sony-playstation",
    status: "Fully Verified",
    coverImage: "/images/elden_ring.jpg",
    lowEndAlternatives: "Dark Souls 1 Remastered or Dark Souls 2: Scholar of the First Sin (runs on most older PCs)."
  },

  // 7. MINECRAFT (Java & Bedrock Edition)
  {
    id: "game-minecraft",
    gameTitle: "Minecraft (Java & Bedrock)",
    era: "modern",
    eraLabel: "Universal Classic",
    genre: "Sandbox Survival & Building",
    platforms: [
      "PC (Windows/Mac/Linux)", 
      "PS5 / PS4 / PS3 / PS Vita", 
      "Xbox Series / One / 360", 
      "Nintendo Switch / Wii U / 3DS", 
      "Android & iOS (Bedrock Mobile)", 
      "Raspberry Pi", 
      "Chromebook", 
      "Steam Deck (Java via Prism Launcher)"
    ],
    fpsTarget: "High Refresh Rate Shaders (Java) | Smooth Multiplatform Output",
    minSpecs: "Intel Core i3-3210 / 4GB RAM / Intel HD Graphics 4000 / 4GB Storage",
    recommendedSpecs: "Core i5 / 16GB RAM / GTX 1660 / SSD",
    compatibilityVerdict: "Runs on literally every gaming platform in existence",
    compatibilityScore: "10/10 Universal King",
    recommendedGear: "Logitech G Pro X Keyboard + Razer DeathAdder V3 Pro",
    categorySlug: "pc-handheld-pc",
    status: "Universal Compatibility",
    coverImage: "/images/minecraft_cover.jpg",
    lowEndAlternatives: "Use Sodium + Lithium optimization mods on Java, or play Minecraft Bedrock / Minetest on budget phones."
  },

  // 8. VALORANT & COUNTER-STRIKE 2
  {
    id: "game-mod-4",
    gameTitle: "Valorant & Counter-Strike 2",
    era: "modern",
    eraLabel: "Tactical Esports",
    genre: "Competitive First-Person Shooter",
    platforms: [
      "PC Desktop (Vanguard / VAC)", 
      "Gaming Laptop", 
      "PS5 (Valorant Console)", 
      "Xbox Series X/S (Valorant Console)"
    ],
    fpsTarget: "Tournament Low Latency Performance | High Refresh Rate Console Mode",
    minSpecs: "Intel Core i3-4150 / 4GB RAM / GeForce GT 730 / 30GB Storage",
    recommendedSpecs: "Ryzen 7 7800X3D / 32GB RAM / RTX 4070 Ti / 360Hz Monitor",
    compatibilityVerdict: "PC Native with 8000Hz Polling; Valorant now on PS5 & Xbox Series",
    compatibilityScore: "9/10 PC & Modern Console",
    recommendedGear: "ROG Swift 360Hz QD-OLED + PTFE Mouse Skates",
    categorySlug: "pc-handheld-pc",
    status: "Fully Verified",
    coverImage: "/images/battlestation_pc.jpg",
    lowEndAlternatives: "CS 1.6 or CS:Source (runs smoothly on lightweight PCs and laptops)."
  },

  // 9. FORTNITE CHAPTER 5 & UNREAL ENGINE 5.4
  {
    id: "game-mod-8",
    gameTitle: "Fortnite & Unreal Editor for Fortnite (UEFN)",
    era: "modern",
    eraLabel: "Battle Royale & Creative",
    genre: "Battle Royale & Sandbox",
    platforms: [
      "PC Desktop / Laptop", 
      "PS5 / PS4", 
      "Xbox Series X/S / Xbox One", 
      "Nintendo Switch (30Hz Mode)", 
      "Android APK (Direct from Epic Games)", 
      "iOS (EU Epic Store & Cloud)", 
      "Xbox Cloud Gaming & GeForce NOW (Free to Play)"
    ],
    fpsTarget: "High-Resolution Console Output / Performance Mode (PC)",
    minSpecs: "Core i3-3225 / 8GB RAM / Intel HD 4000 / 30GB Storage",
    recommendedSpecs: "Core i5-12400 / 16GB RAM / RTX 3060 / NVMe SSD",
    compatibilityVerdict: "Playable on all consoles, PC, Android, and Cloud for Free",
    compatibilityScore: "10/10 Free Everywhere",
    recommendedGear: "Razer DeathAdder V3 Pro + Coiled USB-C Cable",
    categorySlug: "pc-handheld-pc",
    status: "Fully Verified",
    coverImage: "/images/fortnite_cover.jpg",
    lowEndAlternatives: "Switch to 'Performance Mode' in PC settings for smooth performance on entry-level laptops, or stream free on Xbox Cloud."
  },

  // 10. MARVEL'S SPIDER-MAN 2
  {
    id: "game-mod-5",
    gameTitle: "Marvel's Spider-Man 2",
    era: "modern",
    eraLabel: "PlayStation Exclusive / PC Upcoming",
    genre: "Superhero Cinematic Action",
    platforms: [
      "PS5 (Performance Ray Tracing / High Refresh Rate Fidelity)", 
      "PS5 Pro (PSSR High-Ray Tracing Output)", 
      "PC (Expected 2025 Port)"
    ],
    fpsTarget: "Performance Ray Tracing / VRR Smoothness",
    minSpecs: "PlayStation 5 Console Hardware / Ultra High-Speed HDMI 2.1 Cable",
    recommendedSpecs: "PS5 Pro + 4K 120Hz OLED TV / DualSense Edge",
    compatibilityVerdict: "PS5 and PS5 Pro Exclusive",
    compatibilityScore: "Sony Hardware Exclusive",
    recommendedGear: "DualSense Edge + 8K Ultra High Speed HDMI 2.1 Cable",
    categorySlug: "sony-playstation",
    status: "Fully Verified",
    coverImage: "/images/playstation5_pro.jpg",
    lowEndAlternatives: "Marvel's Spider-Man Remastered & Miles Morales on PC (playable on GTX 1060 or Steam Deck)."
  },

  // 11. GENSHIN IMPACT & HONKAI: STAR RAIL
  {
    id: "game-genshin",
    gameTitle: "Genshin Impact & Honkai: Star Rail",
    era: "modern",
    eraLabel: "Anime Action RPG",
    genre: "Open World Gacha RPG",
    platforms: [
      "PC Desktop / Laptop", 
      "PS5 (4K Enhanced Resolution Mode)", 
      "PS4", 
      "Android Phones & Tablets (Google Play / APK)", 
      "iPhone & iPad (High Refresh Rate Output)", 
      "GeForce NOW Cloud"
    ],
    fpsTarget: "High-Resolution Output (PC/Console) | High Performance on Flagship Mobile",
    minSpecs: "Core i5 / 8GB RAM / GT 1030 2GB / 100GB Storage (PC) | Snapdragon 660 (Mobile)",
    recommendedSpecs: "Core i7 / 16GB RAM / RTX 3060 / SSD | Snapdragon 8 Gen 2/3 / A17 Pro",
    compatibilityVerdict: "Cross-Save & Cross-Play between PC, Mobile, and PlayStation",
    compatibilityScore: "10/10 Cross-Save",
    recommendedGear: "Backbone One Mobile Controller + HyperX Cloud III Wireless",
    categorySlug: "mobile",
    status: "Fully Verified",
    coverImage: "/images/genshin_cover.jpg",
    lowEndAlternatives: "Stream directly via GeForce NOW Cloud on any potato laptop or browser without installing 100GB."
  },

  // 12. THE LEGEND OF ZELDA: TEARS OF THE KINGDOM
  {
    id: "game-zelda",
    gameTitle: "The Legend of Zelda: Tears of the Kingdom",
    era: "modern",
    eraLabel: "Nintendo Masterpiece",
    genre: "Open World Physics Adventure",
    platforms: [
      "Nintendo Switch OLED", 
      "Nintendo Switch (Standard/Lite)", 
      "Nintendo Switch 2 (Backwards Compatible Enhanced Mode)", 
      "PC Handhelds / PC via Emulators (High-Resolution Emulation Mode)"
    ],
    fpsTarget: "Native Switch Resolution | High-Resolution Emulation (PC)",
    minSpecs: "Nintendo Switch Hardware | PC: Core i5-11400 / 16GB RAM / RTX 2060",
    recommendedSpecs: "Switch OLED + Pro Controller | PC: Core i7-13700K / 32GB RAM / RTX 4070",
    compatibilityVerdict: "Native to Nintendo Switch; Smooth Output on High-Performance PC Rigs",
    compatibilityScore: "Nintendo Platform",
    recommendedGear: "Nintendo Switch Pro Controller + SanDisk 512GB MicroSDXC",
    categorySlug: "nintendo-home",
    status: "Fully Verified",
    coverImage: "/images/nintendo_switch_oled.jpg",
    lowEndAlternatives: "Zelda: Breath of the Wild (Wii U / Switch) or classic Zelda games on Game Boy Advance / N64 emulators."
  },

  // 13. GRAND THEFT AUTO: SAN ANDREAS (ORIGINAL & DEFINITIVE)
  {
    id: "game-clas-2",
    gameTitle: "Grand Theft Auto: San Andreas (Original, SAMP & Definitive)",
    era: "classic",
    eraLabel: "Classic Legend",
    genre: "Open World Classic Action",
    platforms: [
      "PC Desktop / Laptop (Original 2004 + SAMP Multiplayer)", 
      "PS5 / PS4 / PS3 / PS2 (Original Disc)", 
      "Xbox Series / Xbox One / Original Xbox (2001)", 
      "Nintendo Switch (Definitive Edition)", 
      "Handhelds (PSP via GTA Stories, Handheld Compatibility, PS Vita)", 
      "Android & iOS (Netflix GTA Trilogy & APK)", 
      "China Handhelds (Anbernic RG35XX, Miyoo Mini via PortMaster)"
    ],
    fpsTarget: "Widescreen Support / Smooth Online Multiplayer",
    minSpecs: "Core 2 Duo / 1GB VRAM / 2GB RAM / 4GB Storage",
    recommendedSpecs: "Any Modern PC, Laptop, or Smartphone",
    compatibilityVerdict: "Runs on a wide range of PC hardware",
    compatibilityScore: "10/10 Immortal Compatibility",
    recommendedGear: "Apex Pro Wireless Gamepad + Fast Charging Dock",
    categorySlug: "pc-handheld-pc",
    status: "Broad System Compatibility",
    coverImage: "/images/gta_sa_cover.jpg",
    lowEndAlternatives: "Plays smoothly on literally every $30 office PC, old Android phone, or retro handheld (Miyoo/Anbernic)."
  },

  // 14. COUNTER-STRIKE 1.6 & SOURCE
  {
    id: "game-clas-1",
    gameTitle: "Counter-Strike 1.6 & CS: Source",
    era: "classic",
    eraLabel: "Immortal Esports",
    genre: "Classic First-Person Shooter",
    platforms: [
      "PC Desktop (Windows XP/7/10/11)", 
      "Gaming Laptop & Budget Office Laptops", 
      "Handhelds (Steam Deck Compatibility)", 
      "Raspberry Pi 4/5", 
      "Android (via Xash3D CS 1.6 APK Port)"
    ],
    fpsTarget: "Low Latency Performance Across Hardware",
    minSpecs: "Pentium 4 1.2GHz / 512MB RAM / 64MB Video Card / 2GB Storage",
    recommendedSpecs: "Any Dual-Core PC / 4GB RAM / 144Hz Monitor",
    compatibilityVerdict: "Broadly Compatible with Low Input Latency",
    compatibilityScore: "10/10 Legendary",
    recommendedGear: "Logitech G Pro X TKL + Pure PTFE Skates",
    categorySlug: "pc-handheld-pc",
    status: "Compatible Across Most Modern Hardware",
    coverImage: "/images/gaming_desk_setup.jpg",
    lowEndAlternatives: "Can be played directly inside web browsers or on Android with touch / gamepad via Xash3D engine."
  },

  // 15. THE ELDER SCROLLS V: SKYRIM (SPECIAL EDITION)
  {
    id: "game-clas-3",
    gameTitle: "The Elder Scrolls V: Skyrim (Special Edition & Modded)",
    era: "classic",
    eraLabel: "Legendary RPG",
    genre: "Open World Fantasy RPG",
    platforms: [
      "PC Desktop / Laptop", 
      "PS5 / PS4 / PS3", 
      "Xbox Series X/S / Xbox One / Xbox 360", 
      "Nintendo Switch (Smooth Console Port)", 
      "Handhelds (Steam Deck Verified, ROG Ally, Legion Go)"
    ],
    fpsTarget: "High-Resolution Modded Output / Smooth Handheld Output",
    minSpecs: "Intel i5-750 / 8GB RAM / GTX 470 1GB / 12GB Storage",
    recommendedSpecs: "Ryzen 5 3600 / 16GB RAM / RTX 2060 6GB / SSD",
    compatibilityVerdict: "Runs flawlessly on PC, consoles, and handhelds",
    compatibilityScore: "10/10 Legendary",
    recommendedGear: "7-Inch OLED Gaming Handheld + Extended Desk Pad",
    categorySlug: "pc-handheld-pc",
    status: "Broad System Compatibility",
    coverImage: "/images/skyrim_cover.jpg",
    lowEndAlternatives: "Skyrim Legendary Edition (Original 2011 32-bit version) runs on budget laptops with integrated graphics."
  },

  // 16. NEED FOR SPEED: MOST WANTED (2005) & UNDERGROUND 2
  {
    id: "game-nfs-mw",
    gameTitle: "Need for Speed: Most Wanted (2005) & Underground 2",
    era: "classic",
    eraLabel: "Racing Legend",
    genre: "Arcade Street Racing",
    platforms: [
      "PC Desktop (Widescreen Patch & Remaster Mods)", 
      "PS2 (Original Disc)", 
      "Xbox 360 & Original Xbox", 
      "Nintendo GameCube", 
      "Sony PSP (Most Wanted 5-1-0)", 
      "Handhelds (Steam Deck, Retroid Pocket 4 Pro, Anbernic RG556 via PS2 Emulation)"
    ],
    fpsTarget: "High-Resolution Remastered / Smooth Handheld Emulation",
    minSpecs: "Pentium 4 1.4GHz / 256MB RAM / 32MB DirectX 9 GPU / 3GB Storage",
    recommendedSpecs: "Any Modern PC / Laptop with Xbox 360 Controller",
    compatibilityVerdict: "Runs on all PCs and retro handheld emulators",
    compatibilityScore: "10/10 Classic",
    recommendedGear: "Direct-Drive Racing Wheel or Apex Pro Gamepad",
    categorySlug: "pc-handheld-pc",
    status: "Broad System Compatibility",
    coverImage: "/images/nfs_mw_cover.jpg",
    lowEndAlternatives: "PPSSPP emulator on Android / iPhone plays NFS Most Wanted 5-1-0 smoothly on supported mobile devices."
  }
];
