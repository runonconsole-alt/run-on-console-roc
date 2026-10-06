export const INITIAL_SITE_IMAGES = [
  { id: 'img-1', title: 'Black Ops 6 Omnidirectional Movement', url: '/images/hero_cod.jpg', alt: 'Call of Duty Black Ops 6 PC Hardware Benchmark Hero', category: 'Hero & Banners', dimensions: '1920x1080' },
  { id: 'img-2', title: 'Elden Ring Shadow of the Erdtree', url: '/images/trending_elden.jpg', alt: 'Elden Ring DLC Shadow of the Erdtree Boss Performance', category: 'Blogs', dimensions: '1280x720' },
  { id: 'img-3', title: 'Spider-Man 2 Ray Tracing', url: '/images/trending_spiderman.jpg', alt: 'Spider-Man 2 PS5 Ray Tracing and Frame Pacing Analysis', category: 'Blogs', dimensions: '1280x720' },
  { id: 'img-4', title: 'Forza Motorsport 2024 Ray Tracing', url: '/images/trending_forza.jpg', alt: 'Forza Motorsport PC Benchmark and Hardware Comparison', category: 'Blogs', dimensions: '1280x720' },
  { id: 'img-5', title: 'Esports Championship Stage', url: '/images/trending_esports.jpg', alt: 'Professional Esports Gaming Arena and High Refresh Displays', category: 'Blogs', dimensions: '1280x720' },
  { id: 'img-6', title: 'Apex Gaming Mouse', url: '/images/apex_mouse.jpg', alt: 'Lightweight Esports Optical Gaming Mouse Review', category: 'Products', dimensions: '800x800' },
  { id: 'img-7', title: 'Battlestation Custom Gaming PC', url: '/images/battlestation_pc.jpg', alt: 'Custom Watercooled PC Battlestation with High-End GPU', category: 'Hardware', dimensions: '800x800' },
  { id: 'img-8', title: 'Cyberpunk Mechanical Keyboard', url: '/images/cyber_keyboard.jpg', alt: 'Hot-Swappable Mechanical Gaming Keyboard with RGB Backlighting', category: 'Products', dimensions: '800x800' },
  { id: 'img-9', title: 'Braided Gaming Cables and Hubs', url: '/images/gaming_cables_accessories.jpg', alt: 'High Speed HDMI 2.1 and DisplayPort 1.4 Gaming Cables', category: 'Hardware', dimensions: '800x800' },
  { id: 'img-10', title: 'Next-Gen Gaming Laptop', url: '/images/gaming_laptop.jpg', alt: 'High Refresh Rate Gaming Laptop with RTX Dedicated Graphics', category: 'Products', dimensions: '800x800' },
  { id: 'img-11', title: 'OLED Ultrawide Gaming Monitor', url: '/images/gaming_monitor.jpg', alt: '240Hz Fast IPS OLED Curved Gaming Monitor for PC', category: 'Products', dimensions: '800x800' },
  { id: 'img-12', title: 'Handheld Gaming Console', url: '/images/handheld_console.jpg', alt: 'Portable PC Handheld Gaming Device and Console OLED Screen', category: 'Hardware', dimensions: '800x800' },
  { id: 'img-13', title: 'Pro Wireless Gamepad Controller', url: '/images/pro_controller.jpg', alt: 'Custom Hall Effect Joystick Pro Gaming Controller for PC and Console', category: 'Products', dimensions: '800x800' },
  { id: 'img-14', title: 'Ergonomic Esports Gaming Chair', url: '/images/review_chair.jpg', alt: 'Lumbar Support Ergonomic Gaming Chair for Long Sessions', category: 'Products', dimensions: '800x800' },
  { id: 'img-15', title: 'VR Headset and Streaming Camera', url: '/images/streaming_vr_gear.jpg', alt: 'Virtual Reality Headset and 4K 60FPS Streaming Studio Gear', category: 'Hardware', dimensions: '800x800' },
  { id: 'img-16', title: 'Surround Sound Gaming Headset', url: '/images/tactical_headset.jpg', alt: 'Spatial Audio Low Latency Wireless Gaming Headset with Mic', category: 'Products', dimensions: '800x800' }
];

export const INITIAL_SITE_CONTENT = {
  hero: {
    badge: 'GAMING BENCHMARKS & HARDWARE INTELLIGENCE',
    headline: 'Master Your Frame Rates. Discover Verified Gear.',
    subtitle: 'Real-world benchmark lab testing, hardware specs comparisons, and unbiased hardware editorial guides for PC enthusiasts and esports gamers.',
    primaryCta: 'EXPLORE 15 PLATFORMS',
    secondaryCta: 'CHECK COMPATIBILITY',
    tickerText: '⚡ BENCHMARK LAB: RTX 4080 Super tested in Black Ops 6 across 1080p, 1440p and 4K'
  },
  about: {
    headline: 'Independent Hardware Intelligence for Enthusiasts and Esports Pros',
    mission: 'Run On Console (ROC) delivers rigorous, lab-tested hardware benchmarks and game compatibility intelligence. We bridge the gap between manufacturer marketing claims and real-world performance.',
    editorialPledge: 'All reviews and benchmarks are executed in-house. We do not accept payment for score improvements, hardware rankings, or editorial favoritism.',
    methodology: 'Frametime capture, 1% low percentiles, acoustic dB metering, thermal imaging, and real gaming session battery draw measurements.'
  },
  faqs: [
    { question: 'How does Run On Console verify gaming hardware benchmarks?', answer: 'We run repeatable 10-minute standardized gameplay loops with FCAT and CapFrameX to log average FPS, 1% lows, and 0.1% micro-stutter frametimes.' },
    { question: 'Can I check if my PC can run upcoming PC games?', answer: 'Yes! Our interactive Game Compatibility tool matches your CPU, GPU, and RAM against verified minimum and recommended system requirements.' },
    { question: 'Do you charge for access to reviews or tools?', answer: 'No. All compatibility tools, buying guides, and reviews on Run On Console are 100% free to access.' },
    { question: 'How can I submit hardware or game guides?', answer: 'Visit our Write For Us portal to submit editorial pitches or reach out to our editorial desk for testing partnerships.' }
  ],
  footer: {
    tagline: 'Your definitive independent source for gaming hardware benchmarks, reviews, and platform compatibility.',
    contactEmail: 'contact@runonconsole.com',
    copyright: '© 2026 Run On Console. All rights reserved.',
    socialTwitter: 'https://twitter.com/runonconsole',
    socialYoutube: 'https://youtube.com/@runonconsole',
    affiliateNotice: 'Run On Console participates in verified affiliate partner programs. When you purchase through links on our site, we may earn an affiliate commission at no extra cost to you.'
  }
};

export const INITIAL_SITE_METAS = {
  '/': {
    title: 'Run On Console | Gaming Hardware, Compatibility & Peripherals Hub',
    description: 'Independent gaming hardware intelligence lab. Test PC and console game compatibility, frametime analysis, and unbiased gaming gear benchmarks.',
    keywords: 'gaming hardware, pc game compatibility, console benchmarks, gaming laptops, fps testing',
    canonical: 'https://runonconsole.com/',
    robots: 'index, follow',
    ogTitle: 'Run On Console | Gaming Hardware & Compatibility Lab',
    ogDescription: 'Real-world benchmarks, frame pacing tests, and hardware intelligence for gamers.',
    ogImage: 'https://runonconsole.com/images/hero_cod.jpg',
    ogType: 'website',
    twitterCard: 'summary_large_image',
    twitterCreator: '@runonconsole'
  },
  '/compatibility/': {
    title: 'PC & Console Game Compatibility Checker | Run On Console',
    description: 'Check whether your gaming PC, handheld, or console can run latest games. Verify CPU, GPU, and RAM minimum & recommended system requirements.',
    keywords: 'can i run it, pc game compatibility, system requirements test, fps calculator',
    canonical: 'https://runonconsole.com/compatibility/',
    robots: 'index, follow',
    ogTitle: 'Game Hardware Compatibility Engine | Run On Console',
    ogDescription: 'Instant compatibility assessment for modern PC and console titles.',
    ogImage: 'https://runonconsole.com/images/hero_cod.jpg'
  },
  '/products/': {
    title: 'Gaming Hardware Reviews & Tested Deals | Run On Console',
    description: 'In-depth lab reviews of gaming GPUs, monitors, laptops, controllers, and peripherals. Unbiased scores and live price tracking.',
    keywords: 'gaming gear reviews, best gpu 2024, gaming laptops, monitor reviews',
    canonical: 'https://runonconsole.com/products/',
    robots: 'index, follow',
    ogTitle: 'Tested Gaming Products & Hardware Reviews',
    ogDescription: 'Explore expert benchmark scores and verified buyer recommendations.',
    ogImage: 'https://runonconsole.com/images/battlestation_pc.jpg'
  },
  '/blogs/': {
    title: 'Gaming News, Hardware Guides & Analysis | Run On Console',
    description: 'Expert editorial guides, ray tracing performance deep dives, game settings optimization, and hardware comparison articles.',
    keywords: 'gaming guides, graphics settings guide, ray tracing test, gaming news',
    canonical: 'https://runonconsole.com/blogs/',
    robots: 'index, follow',
    ogTitle: 'Gaming Guides & Hardware Analysis Editorial',
    ogDescription: 'Master your PC settings and game performance with expert guides.',
    ogImage: 'https://runonconsole.com/images/trending_elden.jpg'
  },
  '/categories/': {
    title: '15 Gaming Platforms & Hardware Hubs | Run On Console',
    description: 'Browse specialized hardware hubs for PC Battlestations, Steam Deck Handhelds, PlayStation, Xbox, Nintendo, and VR Gaming.',
    keywords: 'gaming platforms, pc handhelds, retro consoles, vr gear',
    canonical: 'https://runonconsole.com/categories/',
    robots: 'index, follow',
    ogTitle: '15 Gaming Platform Hubs | Run On Console',
    ogDescription: 'Curated platforms and gear categories tested by our benchmark lab.',
    ogImage: 'https://runonconsole.com/images/handheld_console.jpg'
  },
  '/about/': {
    title: 'About Our Hardware Benchmark Lab | Run On Console',
    description: 'Learn how Run On Console tests gaming hardware. Our testing facility, ethics policy, and independent benchmark methodology.',
    keywords: 'about run on console, testing methodology, benchmark lab',
    canonical: 'https://runonconsole.com/about/',
    robots: 'index, follow',
    ogTitle: 'About Run On Console Testing Facility',
    ogDescription: 'Independent gaming hardware intelligence lab and editorial desk.',
    ogImage: 'https://runonconsole.com/images/battlestation_pc.jpg'
  },
  '/author/': {
    title: 'Omar Abobakar — Lead Hardware Benchmark Specialist | Run On Console',
    description: 'Author profile and verified articles by Omar Abobakar, Lead Hardware Benchmark Specialist at Run On Console.',
    keywords: 'omar abobakar, hardware reviewer, benchmark tester',
    canonical: 'https://runonconsole.com/author/',
    robots: 'index, follow',
    ogTitle: 'Omar Abobakar — Author Profile',
    ogDescription: 'Hardware testing articles and performance deep dives.',
    ogImage: 'https://runonconsole.com/images/hero_cod.jpg'
  },
  '/write-for-us/': {
    title: 'Write For Us — Gaming Editorial Program | Run On Console',
    description: 'Contribute to Run On Console. Submit gaming hardware pitches, performance benchmarks, and game guide proposals.',
    keywords: 'gaming write for us, tech guest post, hardware writer',
    canonical: 'https://runonconsole.com/write-for-us/',
    robots: 'index, follow',
    ogTitle: 'Write For Run On Console',
    ogDescription: 'Join our hardware testing and gaming editorial contributor network.',
    ogImage: 'https://runonconsole.com/images/cyber_keyboard.jpg'
  },
  '/partnerships/': {
    title: 'Hardware Testing Partnerships | Run On Console',
    description: 'Collaborate with our independent hardware testing facility for verified product benchmarks and launch coverage.',
    keywords: 'hardware review samples, tech partnerships, brand contact',
    canonical: 'https://runonconsole.com/partnerships/',
    robots: 'index, follow',
    ogTitle: 'Testing Partnerships & Media Inquiries',
    ogDescription: 'Work with Run On Console testing lab.',
    ogImage: 'https://runonconsole.com/images/battlestation_pc.jpg'
  },
  '/contact/': {
    title: 'Contact Editorial & Support Desk | Run On Console',
    description: 'Get in touch with the Run On Console editorial desk, benchmark lab, and technical support team.',
    keywords: 'contact run on console, editorial support, feedback',
    canonical: 'https://runonconsole.com/contact/',
    robots: 'index, follow',
    ogTitle: 'Contact Run On Console',
    ogDescription: 'We are here to answer your gaming hardware and technical questions.',
    ogImage: 'https://runonconsole.com/images/hero_cod.jpg'
  }
};

export const INITIAL_INTERNAL_LINKS = [
  { id: 'l-1', sourcePage: '/blogs/call-of-duty-black-ops-6-everything-we-know-so-far/', targetPage: '/products/asus-rog-strix-geforce-rtx-4070-ti-super/', anchorText: 'RTX 4070 Ti Super performance testing', type: 'In-Content Editorial', rel: 'follow' },
  { id: 'l-2', sourcePage: '/blogs/call-of-duty-black-ops-6-everything-we-know-so-far/', targetPage: '/compatibility/', anchorText: 'Black Ops 6 minimum system requirements', type: 'In-Content Editorial', rel: 'follow' },
  { id: 'l-3', sourcePage: '/blogs/elden-ring-shadow-of-the-erdtree-full-hardware-boss-guide/', targetPage: '/products/valve-steam-deck-oled/', anchorText: 'Steam Deck OLED 40 FPS preset', type: 'In-Content Editorial', rel: 'follow' },
  { id: 'l-4', sourcePage: '/blogs/elden-ring-shadow-of-the-erdtree-full-hardware-boss-guide/', targetPage: '/compatibility/', anchorText: 'Elden Ring PC hardware benchmark matrix', type: 'In-Content Editorial', rel: 'follow' },
  { id: 'l-5', sourcePage: '/blogs/spider-man-2-on-ps5-performance-ray-tracing-masterclass/', targetPage: '/categories/sony-playstation/', anchorText: 'PlayStation 5 platform hub', type: 'Category Breadcrumb', rel: 'follow' },
  { id: 'l-6', sourcePage: '/blogs/best-gaming-laptops-under-1000-in-2024-buyers-guide/', targetPage: '/products/lenovo-legion-pro-7i-gen-9/', anchorText: 'Lenovo Legion Pro 7i review', type: 'Product Mention', rel: 'follow' },
  { id: 'l-7', sourcePage: '/compatibility/', targetPage: '/products/asus-rog-strix-geforce-rtx-4070-ti-super/', anchorText: 'recommended 1440p graphics cards', type: 'Related Guide Card', rel: 'follow' },
  { id: 'l-8', sourcePage: '/compatibility/', targetPage: '/blogs/best-gaming-laptops-under-1000-in-2024-buyers-guide/', anchorText: 'best budget laptop hardware', type: 'Related Guide Card', rel: 'follow' },
  { id: 'l-9', sourcePage: '/products/', targetPage: '/compatibility/', anchorText: 'verify game compatibility first', type: 'In-Content Editorial', rel: 'follow' },
  { id: 'l-10', sourcePage: '/about/', targetPage: '/author/omar-abobakar/', anchorText: 'meet Lead Specialist Omar Abobakar', type: 'In-Content Editorial', rel: 'follow' }
];
