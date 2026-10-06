<?php
/**
 * Run On Console (ROC) - Staging-Only Database Content Seeder Script
 * CLI execution only. Populates products and draft blogs directly from initialData.js dataset.
 *
 * Safety & Schema Rules:
 * 1. Aborts unless SELECT DATABASE() returns 'runoncon_rocstage'.
 * 2. Preview mode by default (php api/v1/cron/seed-roc-agent-content.php).
 * 3. Database writes strictly require the literal CLI --confirm flag.
 * 4. Blog table uses exact baseline schema: id, title, slug, category, read_time, author_name, image, excerpt, content, views_count, status, created_at.
 * 5. On duplicate key update for blogs, both status and views_count are EXCLUDED so published status and view counts are NEVER overwritten on rerun.
 * 6. All writes wrapped in PDO transaction with rollback on Throwable.
 */

if (php_sapi_name() !== 'cli' && empty($_SERVER['ROC_CLI_RUN'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden. CLI execution required.']);
    exit(1);
}

require_once dirname(__DIR__) . '/config.php';

function rocAgentSeedContent(): void {
    $args = $_SERVER['argv'] ?? [];
    $isConfirmed = in_array('--confirm', $args, true);

    echo "============================================================\n";
    echo "RUN ON CONSOLE (ROC) - STAGING CONTENT SEEDER\n";
    echo "============================================================\n\n";

    $pdo = getDBConnection();
    if (!$pdo) {
        echo "❌ Error: Database connection unavailable.\n";
        exit(1);
    }

    // 1. Strict Database Isolation Guard: Must be runoncon_rocstage
    $dbStmt = $pdo->query("SELECT DATABASE()");
    $currentDb = $dbStmt ? $dbStmt->fetchColumn() : null;

    if ($currentDb !== 'runoncon_rocstage') {
        echo "❌ ABORTED: Script is strictly restricted to staging database 'runoncon_rocstage'.\n";
        echo "   Current database is: '" . ($currentDb ?: 'NONE') . "'\n";
        echo "   This script will NEVER write to production or any other database.\n";
        exit(1);
    }
    echo "✓ Verified Database Isolation: Connected to '{$currentDb}'\n";

    if (!$isConfirmed) {
        echo "⚠️ PREVIEW MODE ONLY (No database writes performed).\n";
        echo "   To apply seeding, run with explicit approval flag:\n";
        echo "   php api/v1/cron/seed-roc-agent-content.php --confirm\n\n";
    } else {
        echo "🚀 CONFIRMED WRITE MODE ENABLED (--confirm flag provided).\n\n";
    }

    // 2. Canonical Products Dataset (29 Products directly from src/data/initialData.js)
    $productsSeed = [
        [
            'id' => 'prod-101',
            'title' => 'Logitech G Pro X TKL LIGHTSPEED',
            'category' => 'Keyboards',
            'price' => '$199.99',
            'rating' => 4.8,
            'image' => '/images/cyber_keyboard.jpg',
            'summary' => 'Championship-grade wireless mechanical keyboard engineered with LIGHTSPEED sub-1ms response time and dual-shot PBT keycaps.',
            'pros' => '["Low input latency via LIGHTSPEED wireless","Hard-shell zippered travel case included","Standard keycap bottom row for custom keycaps"]',
            'cons' => '["Switches are factory soldered","Premium price tier"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BCW7S66F?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/logitech-g-pro-x-tkl',
            'affiliate_official' => 'https://www.logitechg.com'
        ],
        [
            'id' => 'prod-102',
            'title' => 'Razer DeathAdder V3 Pro Wireless',
            'category' => 'Gaming Mice',
            'price' => '$149.99',
            'rating' => 4.9,
            'image' => '/images/apex_mouse.jpg',
            'summary' => 'Lightweight 63g ergonomic esports mouse featuring Razer Focus Pro 30K optical sensor and Gen-3 optical switches.',
            'pros' => '["Accurate optical tracking across surfaces","Optical switches designed to prevent double-click issues","Ergonomic contour supports palm and claw grips"]',
            'cons' => '["Designed specifically for right-handed players","HyperPolling dongle for higher polling rates sold separately"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0B6Y7N4P4?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/razer-deathadder-v3-pro',
            'affiliate_official' => 'https://www.razer.com'
        ],
        [
            'id' => 'prod-103',
            'title' => 'ROG Swift 27-Inch 360Hz QD-OLED Monitor',
            'category' => 'Gaming Monitors',
            'price' => '$799.99',
            'rating' => 4.9,
            'image' => '/images/gaming_monitor.jpg',
            'summary' => 'Fast 0.03ms GtG pixel response and high contrast ratio with a 360Hz refresh rate on a QHD QD-OLED panel.',
            'pros' => '["High motion clarity with fast pixel response","Wide color gamut with 99% DCI-P3 coverage","Custom passive heatsink for fanless cooling"]',
            'cons' => '["OLED panel maintenance recommended to mitigate burn-in risk"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0CS7Z8Z6P?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/rog-swift-oled-360hz',
            'affiliate_official' => 'https://rog.asus.com'
        ],
        [
            'id' => 'prod-104',
            'title' => 'HyperX Cloud III Wireless Gaming Headset',
            'category' => 'Headsets & Audio',
            'price' => '$149.99',
            'rating' => 4.7,
            'image' => '/images/tactical_headset.jpg',
            'summary' => 'Memory foam ear cushions paired with up to 120 hours of wireless battery life and tuned 53mm angled acoustic drivers.',
            'pros' => '["Long 120-hour wireless battery life","Comfortable memory foam ear cushions"]',
            'cons' => '["2.4GHz wireless connection only (no Bluetooth support)"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0C3BV19Q3?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/hyperx-cloud-iii',
            'affiliate_official' => 'https://hyperx.com'
        ],
        [
            'id' => 'prod-105',
            'title' => 'Secretlab Titan Evo Ergonomic Chair',
            'category' => 'Chairs & Desks',
            'price' => '$549.00',
            'rating' => 4.9,
            'image' => '/images/review_chair.jpg',
            'summary' => 'Cold-cure foam ergonomic gaming chair with integrated 4-way dynamic lumbar support and magnetic memory foam head pillow.',
            'pros' => '["Adjustable internal lumbar support system","Magnetic armrest replacement ecosystem"]',
            'cons' => '["Firm cold-cure foam seating feel"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B09HN2D7Q9?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/secretlab-titan-evo',
            'affiliate_official' => 'https://secretlab.co'
        ],
        [
            'id' => 'prod-106',
            'title' => 'Wireless Hall-Effect Gaming Controller',
            'category' => 'Controllers & Gear',
            'price' => '$179.99',
            'rating' => 4.8,
            'image' => '/images/pro_controller.jpg',
            'summary' => 'Wireless gaming controller featuring contactless Hall-Effect magnetic thumbsticks, microswitch triggers, and 4 remappable back buttons.',
            'pros' => '["Hall-Effect magnetic sensors resist analog stick drift","Short-throw microswitch trigger stops for fast actuation"]',
            'cons' => '["Back paddle layout may require familiarization period"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0D185QZ6P?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/apex-pro-controller',
            'affiliate_official' => 'https://www.scufgaming.com'
        ],
        [
            'id' => 'prod-107',
            'title' => '7-Inch OLED Gaming Handheld PC',
            'category' => 'Handheld Gaming Consoles',
            'price' => '$649.00',
            'rating' => 4.9,
            'image' => '/images/handheld_console.jpg',
            'summary' => 'Portable x86 gaming handheld featuring a 7-inch 120Hz OLED display, ergonomic grips, and broad PC game library compatibility.',
            'pros' => '["Vibrant 120Hz OLED display with deep contrast","High-speed LPDDR5X memory for handheld PC performance"]',
            'cons' => '["Battery life varies based on power profile TDP settings"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0D999QZ6P?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/oled-handheld-pc',
            'affiliate_official' => 'https://store.steampowered.com'
        ],
        [
            'id' => 'prod-108',
            'title' => 'Custom Liquid-Cooled RTX 4090 Desktop Rig',
            'category' => 'PC Gaming Rigs',
            'price' => '$4,299.00',
            'rating' => 5,
            'image' => '/images/battlestation_pc.jpg',
            'summary' => 'High-end desktop gaming PC featuring custom hardline acrylic liquid cooling for low operating temperatures under heavy load.',
            'pros' => '["High-end 4K graphics and compute performance","Quiet custom hardline liquid cooling loop"]',
            'cons' => '["Large enclosure size and heavy chassis"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0CX87QZ6P?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/custom-rtx4090-desktop',
            'affiliate_official' => 'https://www.cyberpowerpc.com'
        ],
        [
            'id' => 'prod-109',
            'title' => 'Pro Coiled Aviator Keyboard Cable (Type-C)',
            'category' => 'Cables & Mod Gear',
            'price' => '$34.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'Double-sleeved coiled aviator cable featuring reverse-coil heat-treated elasticity, gold-plated USB-C to USB-A connectors, and durable GX12 metal aviator lock.',
            'pros' => '["Heat-set tight coils designed to maintain shape","Metal GX12 aviator connector provides secure attachment","Multiple colorway options"]',
            'cons' => '["Not intended for high-wattage fast-charging mobile devices"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B08V89XYZ1?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/coiled-cable-gaming',
            'affiliate_official' => 'https://store.epomaker.com'
        ],
        [
            'id' => 'prod-110',
            'title' => 'Flexible Zero-Drag Mouse Bungee',
            'category' => 'Cables & Mod Gear',
            'price' => '$19.99',
            'rating' => 4.8,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'Reduces cable drag and snagging. High-flexibility dual spring suspension arm keeps wired mouse cables elevated during play.',
            'pros' => '["Reduces cable friction on mouse pad surfaces","Weighted base prevents sliding during mouse movements"]',
            'cons' => '["Requires small dedicated desk area"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B07FL1XYZ2?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/mouse-bungee-pro',
            'affiliate_official' => 'https://zowie.benq.com'
        ],
        [
            'id' => 'prod-111',
            'title' => 'Pure Virgin PTFE Curved Mouse Skates & Grip Kit',
            'category' => 'Cables & Mod Gear',
            'price' => '$14.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'Die-cut pure virgin PTFE replacement skates with 2.5D rounded edges for smooth mouse glide.',
            'pros' => '["Low dynamic friction on cloth and hybrid mouse pads","Beveled edges reduce pad snagging"]',
            'cons' => '["Requires thorough cleaning of old adhesive prior to installation"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B09X7XYZ03?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/ptfe-mouse-skates',
            'affiliate_official' => 'https://corepad.de'
        ],
        [
            'id' => 'prod-112',
            'title' => '8K Ultra High Speed HDMI 2.1 Cable (48Gbps)',
            'category' => 'Cables & Mod Gear',
            'price' => '$22.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'HDMI Forum certified Ultra High Speed cable delivering full 48Gbps uncompressed bandwidth for 4K 120Hz/144Hz and 8K 60Hz displays.',
            'pros' => '["Full 48Gbps bandwidth supports high refresh rate 4K displays","Braided outer jacket for durability"]',
            'cons' => '["Thick cable diameter is less flexible in tight spaces"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B08M9XYZ04?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/hdmi-21-8k-cable',
            'affiliate_official' => 'https://belkin.com'
        ],
        [
            'id' => 'prod-113',
            'title' => 'USB-C High-Resolution Gaming DAC & Amp',
            'category' => 'Headsets & Audio',
            'price' => '$79.99',
            'rating' => 4.8,
            'image' => '/images/streaming_vr_gear.jpg',
            'summary' => 'External USB-C discrete DAC and headphone amplifier featuring high-resolution audio decoding for studio headphones.',
            'pros' => '["Isolates audio signal from internal motherboard interference","Drives high-impedance headphones up to 600Ω"]',
            'cons' => '["Requires available USB port"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B09V7XYZ05?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/gaming-dac-amp',
            'affiliate_official' => 'https://soundblaster.com'
        ],
        [
            'id' => 'prod-114',
            'title' => 'Dual Controller Fast-Charging Station',
            'category' => 'Controllers & Gear',
            'price' => '$29.99',
            'rating' => 4.8,
            'image' => '/images/pro_controller.jpg',
            'summary' => 'Simultaneously fast-charges two gamepads with magnetic contact charging and integrated overvoltage protection.',
            'pros' => '["Stores and charges two controllers simultaneously","Magnetic contact pins simplify docking"]',
            'cons' => '["Requires dedicated USB power source"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B08H7XYZ06?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/controller-dock',
            'affiliate_official' => 'https://razer.com'
        ],
        [
            'id' => 'prod-115',
            'title' => 'Mechanical Keyboard Switch Lubing Mod Kit',
            'category' => 'Cables & Mod Gear',
            'price' => '$24.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'Complete mechanical keyboard modding kit including authentic Krytox 205g0 lubricant, dual switch openers, stem holder, and detail brushes.',
            'pros' => '["Includes essential tools for mechanical switch maintenance","Dual MX and Kailh switch opener included"]',
            'cons' => '["Manual switch lubrication requires significant time"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0998XYZ07?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/switch-mod-kit',
            'affiliate_official' => 'https://kineticlabs.com'
        ],
        [
            'id' => 'prod-116',
            'title' => 'Extended Micro-Woven Gaming Desk Pad (900x400x4mm)',
            'category' => 'Cables & Mod Gear',
            'price' => '$29.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'Large 900x400mm micro-woven gaming mouse pad featuring a water-resistant coating, 4mm natural rubber base, and anti-fray stitched edges.',
            'pros' => '["Water-resistant coating simplifies cleaning","Extended 900mm surface covers desk space","4mm thickness provides wrist cushioning"]',
            'cons' => '["Requires time to unroll and lay flat out of packaging"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B07Z8XYZ08?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/extended-deskpad',
            'affiliate_official' => 'https://artisan-jp.com'
        ],
        [
            'id' => 'prod-gpu-1',
            'title' => 'ASUS ROG Strix GeForce RTX 4090 OC 24GB GDDR6X',
            'category' => 'Graphic Cards (GPU)',
            'price' => '$1,899.99',
            'rating' => 5,
            'image' => '/images/battlestation_pc.jpg',
            'summary' => 'High-performance flagship graphics card powered by the NVIDIA Ada Lovelace architecture with 24GB of GDDR6X memory for demanding 4K gaming.',
            'pros' => '["Exceptional 4K rendering performance for ray-traced gaming","Axial-tech fans with 0dB idle mode"]',
            'cons' => '["Requires 3.5 slots of PCIe chassis clearance"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BHD8Z3SD?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/asus-rtx-4090-strix',
            'affiliate_official' => 'https://rog.asus.com'
        ],
        [
            'id' => 'prod-gpu-2',
            'title' => 'MSI Gaming GeForce RTX 4070 Ti Super 16GB GDDR6X',
            'category' => 'Graphic Cards (GPU)',
            'price' => '$799.99',
            'rating' => 4.9,
            'image' => '/images/battlestation_pc.jpg',
            'summary' => 'High-performance 1440p and 4K graphics card featuring 16GB of GDDR6X VRAM on a 256-bit bus for modern graphics workloads.',
            'pros' => '["16GB VRAM buffer for high-resolution textures","Efficient 285W TGP power consumption"]',
            'cons' => '["Card length requires verifying case clearance"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0CSK3G4PS?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/msi-rtx-4070-ti-super',
            'affiliate_official' => 'https://msi.com'
        ],
        [
            'id' => 'prod-cpu-1',
            'title' => 'AMD Ryzen 7 7800X3D Gaming Processor',
            'category' => 'Processors (CPU)',
            'price' => '$379.99',
            'rating' => 5,
            'image' => '/images/battlestation_pc.jpg',
            'summary' => '8-core, 16-thread desktop processor built on Zen 4 architecture with 104MB of combined L2+L3 3D V-Cache technology for high-efficiency gaming performance.',
            'pros' => '["High gaming efficiency and framerate performance","Low power consumption under gaming workloads"]',
            'cons' => '["Requires Socket AM5 motherboard and DDR5 memory"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BTZB7F88?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/amd-ryzen-7-7800x3d',
            'affiliate_official' => 'https://amd.com'
        ],
        [
            'id' => 'prod-cpu-2',
            'title' => 'Intel Core i9-14900K Desktop Processor',
            'category' => 'Processors (CPU)',
            'price' => '$549.99',
            'rating' => 4.8,
            'image' => '/images/battlestation_pc.jpg',
            'summary' => '24-core desktop processor with up to 6.0GHz max turbo frequency for gaming, streaming, and multithreaded content creation.',
            'pros' => '["High single-core turbo frequency up to 6.0GHz","Strong multithreaded performance for content creation"]',
            'cons' => '["Requires high-performance liquid cooling for peak workloads"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0CGJDKLB8?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/intel-core-i9-14900k',
            'affiliate_official' => 'https://intel.com'
        ],
        [
            'id' => 'prod-mem-1',
            'title' => 'Samsung 990 PRO 2TB PCIe 4.0 NVMe M.2 SSD with Heatsink',
            'category' => 'Storage & Memory Cards',
            'price' => '$179.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'PCIe 4.0 NVMe M.2 SSD delivering sequential read speeds up to 7450MB/s with an integrated low-profile heatsink compatible with PC and PS5 expansion slots.',
            'pros' => '["Sequential read speeds up to 7450 MB/s","Low-profile heatsink fits PS5 console expansion bay"]',
            'cons' => '["Priced higher than entry-level PCIe 4.0 SSDs"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BHJJ9Y77?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/samsung-990-pro-2tb',
            'affiliate_official' => 'https://samsung.com'
        ],
        [
            'id' => 'prod-mem-2',
            'title' => 'Corsair Vengeance RGB 32GB (2x16GB) DDR5 6000MHz CL30',
            'category' => 'Storage & Memory Cards',
            'price' => '$119.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'DDR5-6000 memory kit with CL30 latency timings, featuring dual AMD EXPO and Intel XMP 3.0 profile compatibility.',
            'pros' => '["CL30 low-latency timings at 6000MHz","Dual AMD EXPO + Intel XMP profile support"]',
            'cons' => '["Module height requires checking CPU cooler clearance"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0C3RYHZJQ?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/corsair-vengeance-rgb-32gb',
            'affiliate_official' => 'https://corsair.com'
        ],
        [
            'id' => 'prod-mem-3',
            'title' => 'SanDisk 1TB Extreme MicroSDXC UHS-I Memory Card',
            'category' => 'Storage & Memory Cards',
            'price' => '$99.99',
            'rating' => 4.8,
            'image' => '/images/handheld_console.jpg',
            'summary' => 'Expandable 1TB microSDXC memory card with UHS-I U3 and A2 performance ratings for portable handheld consoles and mobile devices.',
            'pros' => '["1TB capacity for handheld game storage","A2 app performance classification"]',
            'cons' => '["Maximum read speeds require compatible QuickFlow card reader"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B09X7C6159?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/sandisk-extreme-1tb-microsd',
            'affiliate_official' => 'https://westerndigital.com'
        ],
        [
            'id' => 'prod-spk-1',
            'title' => 'Razer Leviathan V2 Pro Soundbar with Subwoofer',
            'category' => 'Speakers & Soundbars',
            'price' => '$399.99',
            'rating' => 4.8,
            'image' => '/images/streaming_vr_gear.jpg',
            'summary' => 'Desktop PC soundbar featuring infrared camera head-tracking beamforming audio and a dedicated down-firing subwoofer.',
            'pros' => '["Beamforming audio spatial separation","Dedicated down-firing subwoofer for low frequencies"]',
            'cons' => '["Head-tracking requires clear camera view of user"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BQ59DXZ7?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/razer-leviathan-v2-pro',
            'affiliate_official' => 'https://razer.com'
        ],
        [
            'id' => 'prod-spk-2',
            'title' => 'Creative Pebble Pro Minimalist RGB USB-C Desktop Speakers',
            'category' => 'Speakers & Soundbars',
            'price' => '$59.99',
            'rating' => 4.8,
            'image' => '/images/streaming_vr_gear.jpg',
            'summary' => 'Compact USB-C desktop speakers featuring 45-degree elevated drivers, passive bass radiators, and customizable RGB lighting.',
            'pros' => '["Compact desktop footprint","45-degree elevated driver alignment"]',
            'cons' => '["Maximum peak output requires optional 30W USB-PD adapter"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BKT8K28P?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/creative-pebble-pro',
            'affiliate_official' => 'https://creative.com'
        ],
        [
            'id' => 'prod-ear-1',
            'title' => 'Razer Hammerhead HyperSpeed Wireless Gaming Earbuds',
            'category' => 'Handsfree & Earbuds',
            'price' => '$149.99',
            'rating' => 4.7,
            'image' => '/images/tactical_headset.jpg',
            'summary' => 'In-ear gaming wireless earbuds featuring a low-latency 2.4GHz USB-C dongle, Bluetooth 5.2, and Active Noise Cancellation.',
            'pros' => '["Low-latency 2.4GHz USB-C wireless dongle connection","Active Noise Cancellation support"]',
            'cons' => '["Earbud battery runtime varies with ANC enabled"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0BH4W7W6Z?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/razer-hammerhead-hyperspeed',
            'affiliate_official' => 'https://razer.com'
        ],
        [
            'id' => 'prod-ear-2',
            'title' => 'Moondrop Chu II High-Resolution In-Ear Monitors (Esports Handsfree)',
            'category' => 'Handsfree & Earbuds',
            'price' => '$24.99',
            'rating' => 4.9,
            'image' => '/images/tactical_headset.jpg',
            'summary' => 'Budget in-ear monitor featuring a zinc-alloy housing, aluminum-magnesium alloy dome dynamic driver, and detachable 0.78mm 2-pin cable.',
            'pros' => '["Detailed audio tuning for in-ear monitor entry level","Detachable 0.78mm 2-pin cable design"]',
            'cons' => '["Passive noise isolation only (no ANC)"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0CB8HHS8V?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/moondrop-chu-ii',
            'affiliate_official' => 'https://moondroplab.com'
        ],
        [
            'id' => 'prod-chg-1',
            'title' => 'Anker Prime 100W GaN Fast Charger & 240W Braided Cable Kit',
            'category' => 'Chargers, Docks & Power',
            'price' => '$69.99',
            'rating' => 4.9,
            'image' => '/images/gaming_cables_accessories.jpg',
            'summary' => 'Compact GaN wall charger providing up to 100W multi-device power distribution across two USB-C ports and one USB-A port.',
            'pros' => '["Multi-port 100W GaN power delivery","Foldable wall prongs for travel"]',
            'cons' => '["Higher cost than single-port chargers"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0C47FB9G7?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/anker-prime-100w',
            'affiliate_official' => 'https://anker.com'
        ],
        [
            'id' => 'prod-chg-2',
            'title' => '6-in-1 Handheld Console Fast Charging Dock',
            'category' => 'Chargers, Docks & Power',
            'price' => '$49.99',
            'rating' => 4.8,
            'image' => '/images/handheld_console.jpg',
            'summary' => 'Multi-port docking station for handheld gaming PCs, featuring 4K HDMI video output, Gigabit Ethernet, 3 USB 3.0 ports, and 100W USB-C power delivery.',
            'pros' => '["Expands handheld PC to external display and Ethernet","Includes 100W USB-C PD power pass-through"]',
            'cons' => '["Cradle slot width may require removing thick protective covers"]',
            'affiliate_amazon' => 'https://www.amazon.com/dp/B0B79VMSZZ?tag=fragreviews-20',
            'affiliate_bestbuy' => 'https://www.bestbuy.com/site/steam-deck-dock-station',
            'affiliate_official' => 'https://jsaux.com'
        ]
    ];

    // 3. Canonical Blogs Dataset (4 Blogs directly from src/data/initialData.js matching baseline schema)
    $blogsSeed = [
        [
            'id' => 'blog-1',
            'title' => 'Call of Duty: Black Ops 6 – Everything We Know So Far',
            'slug' => 'call-of-duty-black-ops-6-everything-we-know-so-far',
            'category' => 'Guides & Deals',
            'read_time' => '7 min read',
            'author_name' => 'Omar Abobakar',
            'image' => '/images/hero_cod.jpg',
            'excerpt' => 'A deep dive into Black Ops 6 omnidirectional sprinting and sliding mechanics, campaign story details, multiplayer weapon balancing, and recommended PC hardware requirements.',
            'content' => '
### The Next Evolution of Fast-Paced Combat
Treyarch and Raven Software have officially pulled back the curtain on *Call of Duty: Black Ops 6*. Set in the early 1990s during the aftermath of the Cold War, the title introduces what the developers call **Omnidirectional Movement**—allowing players to sprint, slide, and dive in any direction (forward, backward, sideways) seamlessly.

### How to Prepare Your Gaming Setup
To fully take advantage of the fluid movement mechanics in Black Ops 6, having low input latency hardware is essential:
- **Mouse Recommendation:** For rapid 180-degree turn flicks, we recommend the [Razer DeathAdder V3 Pro](internal-link:prod-102) with its 63g ultralight chassis and 30K sensor.
- **Keyboard Recommendation:** For instant direction changes with rapid trigger stops, check out the [Logitech G Pro X TKL](internal-link:prod-101) paired with a [Pro Coiled Aviator Cable](internal-link:prod-109).
- **Audio Advantage:** Footstep clarity is critical in Search and Destroy; the [HyperX Cloud III](internal-link:prod-104) or [USB-C High-Resolution Gaming DAC](internal-link:prod-113) delivers superior acoustic spatial awareness.

### System Requirements & Performance Targets
Black Ops 6 targets a solid 120 FPS on PlayStation 5 and Xbox Series X, with PC users able to push beyond 240 FPS on high-refresh 1440p displays like the [ROG Swift 360Hz QD-OLED](internal-link:prod-103).
    ',
            'views_count' => 0,
            'status' => 'draft'
        ],
        [
            'id' => 'blog-2',
            'title' => 'Elden Ring: Shadow of the Erdtree – Full Hardware & Boss Guide',
            'slug' => 'elden-ring-shadow-of-the-erdtree-full-hardware-boss-guide',
            'category' => 'PC / Handheld PC',
            'read_time' => '9 min read',
            'author_name' => 'Alex Vance',
            'image' => '/images/trending_elden.jpg',
            'excerpt' => 'Comprehensive technical analysis of the Land of Shadow expansion, testing ray tracing frame pacing, difficulty curves, and optimal controller setups.',
            'content' => '
### Exploring the Land of Shadow
FromSoftware\'s *Shadow of the Erdtree* expansion is the largest DLC ever crafted by the studio. Featuring vast layered vertical biomes, challenging remembrance bosses, and over 100 new weapons.

### Controller Precision for Tight Dodge Windows
Boss timings in the expansion demand millimeter-precise inputs without joystick drift. Using the [Apex Pro Hall-Effect Controller](internal-link:prod-106) with a [Dual Controller Fast Dock](internal-link:prod-114) provides immediate responsiveness on rear dodge paddles.

### Handheld Performance on the Go
For gamers exploring the Realm of Shadow portably, the [7-Inch OLED Gaming Handheld PC](internal-link:prod-107) renders the expansion with stunning true blacks on its HDR OLED panel.
    ',
            'views_count' => 0,
            'status' => 'draft'
        ],
        [
            'id' => 'blog-3',
            'title' => 'Spider-Man 2 on PS5 – Performance & Ray Tracing Masterclass',
            'slug' => 'spider-man-2-on-ps5-performance-ray-tracing-masterclass',
            'category' => 'Sony PlayStation',
            'read_time' => '6 min read',
            'author_name' => 'Sarah Kai',
            'image' => '/images/trending_spiderman.jpg',
            'excerpt' => 'Deep dive into 40 FPS Fidelity and 60 FPS Performance Ray Tracing modes in Marvel\'s Spider-Man 2 on PlayStation 5.',
            'content' => '
### Next-Gen City Traversals
*Marvel\'s Spider-Man 2* showcases the true potential of high-speed SSD streaming and hardware ray tracing. Peter Parker and Miles Morales web-wing across Manhattan at near 70 MPH with instantaneous asset loading.

### Audio & Visual Immersion
Paired with DTS 3D spatial audio on headsets like the [HyperX Cloud III](internal-link:prod-104) and certified [8K HDMI 2.1 Cables](internal-link:prod-112), ambient siren echoes and atmospheric city chatter wrap around the player with cinematic depth.
    ',
            'views_count' => 0,
            'status' => 'draft'
        ],
        [
            'id' => 'blog-4',
            'title' => 'Best Gaming Laptops Under $1000 in 2024 – Buyer\'s Guide',
            'slug' => 'best-gaming-laptops-under-1000-in-2024-buyers-guide',
            'category' => 'Guides & Deals',
            'read_time' => '8 min read',
            'author_name' => 'Derrick Vance',
            'image' => '/images/gaming_laptop.jpg',
            'excerpt' => 'Finding maximum FPS value on a budget. We benchmark thermal headroom, battery efficiency, and screen color accuracy under $1000.',
            'content' => '
### Budget Laptop Revolution
Gone are the days when a budget gaming laptop meant thermal throttling and washed-out 45% NTSC displays. The latest generation of sub-$1000 gaming laptops pack Ada Lovelace RTX 4050 and RTX 4060 graphics chips with DLSS 3 Frame Generation support.
    ',
            'views_count' => 0,
            'status' => 'draft'
        ]
    ];

    echo "📊 PREVIEW SUMMARY OF CANONICAL DATA TO SEED:\n";
    echo "------------------------------------------------------------\n";
    echo "Products Count: " . count($productsSeed) . " (Source: src/data/initialData.js ALL_PRODUCTS)\n";
    foreach ($productsSeed as $p) {
        echo "  - Product ID [{$p['id']}]: {$p['title']} ({$p['category']} - {$p['price']})\n";
    }

    echo "\nBlogs Count: " . count($blogsSeed) . " (Source: src/data/initialData.js ALL_BLOGS with baseline schema mapping)\n";
    foreach ($blogsSeed as $b) {
        echo "  - Blog ID [{$b['id']}]: {$b['title']} -> Slug: {$b['slug']} (Author: {$b['author_name']}, ReadTime: {$b['read_time']}, Status: {$b['status']})\n";
    }
    echo "------------------------------------------------------------\n\n";

    if (!$isConfirmed) {
        echo "✓ Preview completed successfully. No changes made to database.\n";
        return;
    }

    // 4. Executing Confirmed Database Writes inside PDO Transaction
    try {
        $pdo->beginTransaction();

        $prodStmt = $pdo->prepare("
            INSERT INTO products (id, title, category, price, rating, image, summary, pros, cons, affiliate_amazon, affiliate_bestbuy, affiliate_official, created_at)
            VALUES (:id, :title, :category, :price, :rating, :image, :summary, :pros, :cons, :affiliate_amazon, :affiliate_bestbuy, :affiliate_official, NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                category = VALUES(category),
                price = VALUES(price),
                rating = VALUES(rating),
                image = VALUES(image),
                summary = VALUES(summary),
                pros = VALUES(pros),
                cons = VALUES(cons),
                affiliate_amazon = VALUES(affiliate_amazon),
                affiliate_bestbuy = VALUES(affiliate_bestbuy),
                affiliate_official = VALUES(affiliate_official)
        ");

        $prodInserted = 0;
        foreach ($productsSeed as $p) {
            $prodStmt->execute([
                ':id' => $p['id'],
                ':title' => $p['title'],
                ':category' => $p['category'],
                ':price' => $p['price'],
                ':rating' => $p['rating'],
                ':image' => $p['image'],
                ':summary' => $p['summary'],
                ':pros' => $p['pros'],
                ':cons' => $p['cons'],
                ':affiliate_amazon' => $p['affiliate_amazon'],
                ':affiliate_bestbuy' => $p['affiliate_bestbuy'],
                ':affiliate_official' => $p['affiliate_official']
            ]);
            $prodInserted++;
        }

        // Exact baseline blog insert SQL: status and views_count are EXCLUDED from ON DUPLICATE KEY UPDATE
        $blogStmt = $pdo->prepare("
            INSERT INTO blogs (id, title, slug, category, read_time, author_name, image, excerpt, content, views_count, status, created_at)
            VALUES (:id, :title, :slug, :category, :read_time, :author_name, :image, :excerpt, :content, :views_count, :status, NOW())
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                slug = VALUES(slug),
                category = VALUES(category),
                read_time = VALUES(read_time),
                author_name = VALUES(author_name),
                image = VALUES(image),
                excerpt = VALUES(excerpt),
                content = VALUES(content)
        ");

        $blogInserted = 0;
        foreach ($blogsSeed as $b) {
            $blogStmt->execute([
                ':id' => $b['id'],
                ':title' => $b['title'],
                ':slug' => $b['slug'],
                ':category' => $b['category'],
                ':read_time' => $b['read_time'],
                ':author_name' => $b['author_name'],
                ':image' => $b['image'],
                ':excerpt' => $b['excerpt'],
                ':content' => $b['content'],
                ':views_count' => $b['views_count'],
                ':status' => $b['status']
            ]);
            $blogInserted++;
        }

        // Post-Insert Verification Queries
        $prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $blogCount = (int)$pdo->query("SELECT COUNT(*) FROM blogs")->fetchColumn();

        if ($prodCount < count($productsSeed) || $blogCount < count($blogsSeed)) {
            $pdo->rollBack();
            echo "❌ Verification Error: Database table record count after insertion is below expected thresholds.\n";
            echo "   Products: {$prodCount} (Expected >= " . count($productsSeed) . ")\n";
            echo "   Blogs: {$blogCount} (Expected >= " . count($blogsSeed) . ")\n";
            exit(1);
        }

        $pdo->commit();
        echo "============================================================\n";
        echo "✅ SUCCESS: Content Seeder Completed Successfully.\n";
        echo "   - Products Seeded: {$prodInserted} (Total in DB: {$prodCount})\n";
        echo "   - Blogs Seeded: {$blogInserted} (Total in DB: {$blogCount})\n";
        echo "============================================================\n";

    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "❌ CRITICAL FAILURE during database write: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    rocAgentSeedContent();
}
