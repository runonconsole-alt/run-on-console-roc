<?php
/**
 * Run On Console — GTA 6 hub (Deploy 185): /gta-6/ and its language versions.
 *
 * Owner, 2026-11-18: GTA 6 comes out on 19 November 2026 (New Zealand first). One hub page that answers what people
 * search (release time per country, why it is earlier in some countries, platforms, price, size, PC version, length,
 * where to buy) and links every GTA 6 post on the site; the same page in the languages of the regions we write for,
 * with hreflang. ROC_GTA6_FACTS is the checked fact sheet the blog writer must use for GTA 6 posts (blog-engine-lib.php)
 * so the AI never invents a PC date, requirements, FPS or prices. Facts as of 18 November 2026, with sources below.
 */

const ROC_GTA6_CHANGED = '2026-11-18';
/** Language => page path (each a single slug, so page-render.php serves it without new rewrite rules). */
const ROC_GTA6_PAGES = ['en' => '/gta-6/', 'ur' => '/gta-6-urdu/', 'de' => '/gta-6-de/', 'es' => '/gta-6-es/', 'pt' => '/gta-6-pt/', 'fr' => '/gta-6-fr/', 'ru' => '/gta-6-ru/'];
/** Unlock zones (Rockstar: midnight local time on 19 November in all regions; US stores use Eastern time). */
const ROC_GTA6_ZONES = [['nz', 'Pacific/Auckland'], ['au', 'Australia/Sydney'], ['jp', 'Asia/Tokyo'], ['in', 'Asia/Kolkata'], ['pk', 'Asia/Karachi'],
    ['ae', 'Asia/Dubai'], ['ru', 'Europe/Moscow'], ['eu', 'Europe/Berlin'], ['uk', 'Europe/London'], ['br', 'America/Sao_Paulo'], ['us', 'America/New_York']];
const ROC_GTA6_SOURCES = [
    ['Rockstar Games Newswire', 'https://www.rockstargames.com/newswire'],
    ['Forbes: GTA VI release date, time and download size', 'https://www.forbes.com/sites/erikkain/2026/11/16/gta-6-release-date-time-ps5-xbox-series-x-download-size-day-one-update/'],
    ['Forbes: GTA 6 unlocks at midnight on November 19', 'https://www.forbes.com/sites/brianmazique/2026/11/12/gta-6-unlocks-at-midnight-on-november-19-but-millions-of-americans-can-start-playing-the-night-before/'],
    ['Quartz: GTA 6 release time in every US time zone', 'https://qz.com/gta-6-release-time-us-time-zones'],
    ['IBTimes UK: release date, file size, pricing and platforms', 'https://www.ibtimes.co.uk/gta-6-release-date-price-storage-console-launch-1831921'],
    ['GTABoom: a Rockstar playthrough took about 80 hours', 'https://www.gtaboom.com/a-full-gta-6-playthrough-took-this-rockstar-dev-about-80-hours-ae3c'],
    ['TweakTown: GTA 6 map size compared', 'https://www.tweaktown.com/news/114382/gta-6s-map-is-roughly-twice-gta-5s-and-almost-as-big-as-gta-4-gta-5-and-rdr2-combined/index.html'],
];

/** The fact sheet for the blog writer (English). */
const ROC_GTA6_FACTS = <<<'TXT'
GTA 6 FACT SHEET (checked 18 November 2026). Use ONLY these facts about GTA 6; if something is not here, say it is not announced or leave it out.
- Status: the game is OUT. It launched worldwide between 18 November (New Zealand) and 19 November 2026 (Americas); write about the launch in the past tense.
- Name: Grand Theft Auto VI (GTA 6, GTA VI), by Rockstar Games (owned by Take-Two Interactive).
- Release: 19 November 2026 on PlayStation 5 and Xbox Series X|S only. Rockstar says it unlocks at midnight local time in every region, so New Zealand gets it first (00:00 NZDT = 11:00 UTC on 18 November = 16:00 Pakistan time on 18 November). Pakistan: 00:00 PKT on 19 November (19:00 UTC on 18 November). US stores use Eastern time, so the US unlocks at 00:00 ET (21:00 on 18 November on the West Coast). Xbox Cloud Gaming is reported to start at 1:00 a.m. ET on 19 November.
- Why the times differ: time zones. A midnight local launch reaches countries east of the date line first (New Zealand, Australia, Japan) and the Americas last; it is not a delay for anyone.
- Not on PS4, Xbox One, Nintendo Switch or PC. Rockstar has announced NO PC version and NO PC date. There are NO official PC system requirements: any list online is a guess. History only (not a promise): GTA V reached PC about 19 months after consoles (Sept 2013 -> April 2015), Red Dead Redemption 2 about 13 months after (Oct 2018 -> Nov 2019).
- Price (US, as reported): Standard Edition $79.99, Ultimate Edition $99.99; prices differ by country and store, so tell readers to check their store.
- Storage: Rockstar says the pre-load needs up to 212 GB depending on the console; Sony asks PS5 owners to have at least 212 GB free.
- Length: no official campaign length. Rockstar's Rob Nelson said his own playthrough took about 80 hours, including some optional content.
- World: the state of Leonida (Rockstar's take on Florida) with Vice City and six regions: Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn and Ambrosia. Reports put the map at roughly twice the size of GTA V's; Rockstar has given no official size.
- Main characters: Jason and Lucia.
- Delays: first planned for 2025, moved to 26 May 2026 (announced May 2025), then to 19 November 2026 (announced November 2025). Trailer 1 came in December 2023 and trailer 2 in May 2025.
- Reviews: reported (not confirmed by Rockstar) to go live on 18 November, one day before launch, as with GTA V and Red Dead Redemption 2.
- Pakistan: Sony and Microsoft do not list Pakistan as an official store country, so many players there buy a disc from a local shop or use an account from another region; tell readers to check each store's terms.
- Never invent: PC requirements, FPS, resolution or performance modes, PS5 Pro differences, PKR prices, GTA Online details, sales numbers or review scores.
RULES FOR GTA 6 POSTS: you MAY state the announced game prices above (the "no prices" rule is about hardware). Link the GTA 6 hub /gta-6/ in the first two paragraphs with the anchor "GTA 6 release date, price and PC status". Write in plain English for gamers worldwide; when the topic is a country, give that country's exact local unlock time from the facts.
TXT;

function rocGta6Facts(): string { return ROC_GTA6_FACTS; }

/** The writer topics for 19-30 November (cli-gta6-plan.php): [pillar, stage, title, keyword, secondary]. */
function rocGta6Topics(): array {
    return [
        ['games', 'tofu', 'GTA 6 release time in every country: when it unlocks for you', 'gta 6 release time', ['gta 6 unlock time', 'gta 6 release date']],
        ['games', 'tofu', 'Why GTA 6 comes out first in New Zealand, and when it reaches you', 'gta 6 new zealand release', ['why is gta 6 out in new zealand first', 'gta 6 time zones']],
        ['games', 'tofu', 'Is GTA 6 on PC? What Rockstar has and has not announced', 'gta 6 pc', ['gta 6 pc release date', 'gta 6 on pc']],
        ['games', 'tofu', 'GTA 6 release time in Pakistan and India: the exact hour', 'gta 6 release time pakistan', ['gta 6 release time india', 'gta 6 pakistan']],
        ['games', 'tofu', 'GTA 6 PC requirements: why every list online is a guess', 'gta 6 pc requirements', ['gta 6 system requirements', 'can my pc run gta 6']],
        ['games', 'tofu', 'How long is GTA 6? Story length and what we know', 'how long is gta 6', ['gta 6 story length', 'gta 6 hours']],
        ['games', 'tofu', 'GTA 6 price and editions: Standard vs Ultimate explained', 'gta 6 price', ['gta 6 ultimate edition', 'gta 6 editions']],
        ['games', 'tofu', 'GTA 6 file size: how much space you need on PS5 and Xbox', 'gta 6 file size', ['gta 6 storage', 'gta 6 download size']],
        ['games', 'tofu', 'GTA 6 map: Leonida, Vice City and the six regions explained', 'gta 6 map', ['leonida', 'vice city']],
        ['games', 'tofu', 'Can you play GTA 6 on Xbox Cloud Gaming, phones and laptops?', 'gta 6 cloud gaming', ['gta 6 xbox cloud', 'play gta 6 without console']],
        ['games', 'tofu', 'GTA 6 on PS4, Xbox One or Switch? Which consoles can play it', 'gta 6 ps4', ['gta 6 xbox one', 'gta 6 platforms']],
        ['games', 'tofu', 'How to buy GTA 6 in Pakistan: disc, digital and store regions', 'gta 6 in pakistan', ['buy gta 6 pakistan', 'gta 6 pakistan release']],
        ['games', 'tofu', 'GTA 6 characters: who are Jason and Lucia?', 'gta 6 characters', ['jason and lucia', 'gta 6 story']],
        ['games', 'tofu', 'GTA 6 delays timeline: from 2025 to 19 November 2026', 'gta 6 delay', ['gta 6 release date history', 'gta 6 trailer']],
        ['games', 'mofu', 'GTA 6 vs GTA 5: what is new in Rockstar\'s next game', 'gta 6 vs gta 5', ['gta 5 vs gta 6 map', 'gta 6 differences']],
        ['games', 'tofu', 'Games like GTA to play on PC while GTA 6 is console only', 'games like gta for pc', ['open world games pc', 'gta alternatives']],
        ['games', 'tofu', 'GTA 6 pre-load guide: how to download it early on PS5 and Xbox', 'gta 6 preload', ['gta 6 pre download', 'gta 6 ps5 preload']],
        ['cat-handhelds', 'tofu', 'GTA 6 on Steam Deck and handheld PCs: can you play it?', 'gta 6 steam deck', ['gta 6 rog ally', 'gta 6 handheld']],
        ['games', 'tofu', 'GTA 6 release time in Europe: UK, Germany, France and Spain', 'gta 6 release time uk', ['gta 6 release time germany', 'gta 6 release time europe']],
        ['games', 'tofu', 'GTA 6 release time in Brazil and the US: night-before launch explained', 'gta 6 release time us', ['gta 6 release time brazil', 'gta 6 release time est']],
        ['cat-audio', 'bofu', 'Best gaming headsets to play GTA 6 on PS5 and Xbox', 'best headset for gta 6', ['ps5 headset', 'xbox headset']],
        ['cat-monitors', 'mofu', 'Gaming monitor or TV for GTA 6 on PS5 and Xbox: what to look for', 'best monitor for gta 6', ['4k monitor for ps5', 'gaming tv vs monitor']],
        ['cat-gpu', 'mofu', 'Getting your PC ready for a future GTA 6 PC release: GPU basics', 'pc for gta 6', ['graphics card for gta 6', 'upgrade pc for gta']],
        ['games', 'tofu', 'Red Dead Redemption 2 and GTA V on PC: can your PC run Rockstar\'s games?', 'can i run red dead redemption 2', ['gta v enhanced requirements', 'rockstar pc games']],
        // Deploy 188 (owner, 19 November): GTA 6 topics until 31 December, on the normal blog days.
        ['games', 'mofu', 'GTA 6 vs Red Dead Redemption 2: how Rockstar\'s two biggest worlds compare', 'gta 6 vs rdr2', ['rockstar open world', 'red dead redemption 2']],
        ['games', 'tofu', 'Every GTA game in order: release dates from 1997 to GTA 6', 'gta games in order', ['gta release dates', 'gta series timeline']],
        ['games', 'tofu', 'The history of Vice City: from GTA Vice City (2002) to GTA 6', 'vice city history', ['gta vice city', 'gta 6 vice city']],
        ['games', 'mofu', 'GTA 6 Standard or Ultimate Edition: which one should you buy?', 'gta 6 standard vs ultimate', ['gta 6 which edition', 'gta 6 ultimate edition worth it']],
        ['games', 'mofu', 'PS5 or Xbox Series X for GTA 6: which console should you buy?', 'ps5 or xbox for gta 6', ['best console for gta 6', 'ps5 vs xbox series x']],
        ['games', 'tofu', 'Xbox Series S and GTA 6: what to know before you buy', 'gta 6 xbox series s', ['xbox series s gta 6', 'cheapest console for gta 6']],
        ['games', 'tofu', 'How long does it take to download GTA 6? Internet speed and 212 GB explained', 'gta 6 download time', ['internet speed for gta 6', 'gta 6 212 gb']],
        ['games', 'tofu', 'Adding storage to your PS5 for GTA 6: M.2 SSD basics', 'ps5 ssd for gta 6', ['ps5 storage upgrade', 'ps5 m.2 ssd']],
        ['games', 'tofu', 'Xbox Series X|S storage for GTA 6: expansion cards and USB drives', 'xbox storage for gta 6', ['xbox expansion card', 'xbox external storage']],
        ['games', 'tofu', 'Why GTA 6 is not on PC at launch: Rockstar\'s console-first history', 'why gta 6 not on pc', ['gta 6 pc delay', 'rockstar pc ports']],
        ['games', 'tofu', 'GTA 6 PC release date: what history says, and what it does not', 'gta 6 pc release date', ['when is gta 6 coming to pc', 'gta 6 pc 2027']],
        ['cat-gpu', 'mofu', 'What a strong gaming PC looks like in 2026, while GTA 6 is console only', 'gaming pc for gta 6', ['best pc for open world games', 'gta 6 pc build']],
        ['cat-cpu', 'mofu', 'Best CPUs for big open-world games while you wait for GTA 6 on PC', 'best cpu for open world games', ['cpu for gta 6', 'gaming cpu 2026']],
        ['cat-ram', 'tofu', 'How much RAM do big open-world games need? Rockstar games as the guide', 'ram for open world games', ['ram for gta 6', '16gb vs 32gb gaming']],
        ['games', 'tofu', 'Rockstar games on PC ranked by how demanding they are', 'rockstar pc games requirements', ['gta v requirements', 'rdr2 requirements']],
        ['games', 'tofu', 'GTA 5 Enhanced on PC: is it worth playing while you wait for GTA 6?', 'gta 5 enhanced worth it', ['gta v enhanced pc', 'gta 5 before gta 6']],
        ['games', 'tofu', 'Open-world games with the biggest maps you can play on PC', 'biggest open world games pc', ['largest game maps', 'open world pc games']],
        ['games', 'mofu', 'Mafia, Saints Row, Watch Dogs or Sleeping Dogs: GTA-style series on PC compared', 'games like gta comparison', ['gta alternatives pc', 'mafia vs saints row']],
        ['games', 'tofu', 'GTA 6 vs GTA 4: how Rockstar\'s open worlds have changed', 'gta 6 vs gta 4', ['gta 4 vs gta 5', 'gta evolution']],
        ['games', 'tofu', 'GTA 6 in India: release time, platforms and how to buy', 'gta 6 india', ['gta 6 release time india', 'gta 6 price india']],
        ['games', 'tofu', 'GTA 6 in the UAE and the Gulf: release time and where to buy', 'gta 6 uae', ['gta 6 dubai', 'gta 6 release time uae']],
        ['games', 'tofu', 'GTA 6 in Australia: launch time, platforms and where to buy', 'gta 6 australia', ['gta 6 release time australia', 'gta 6 australia price']],
        ['games', 'tofu', 'GTA 6 file size compared with the biggest console games', 'gta 6 file size comparison', ['biggest game file sizes', 'largest ps5 games']],
        ['cat-monitors', 'mofu', 'Is a 4K TV worth it for GTA 6 on PS5 and Xbox?', '4k tv for gta 6', ['4k vs 1440p console', 'tv for ps5']],
        ['cat-monitors', 'tofu', 'HDR, 120 Hz and VRR explained for GTA 6 players on console', 'hdr 120hz vrr explained', ['tv settings for ps5', 'vrr gaming']],
        ['cat-audio', 'mofu', 'Wireless or wired headset for GTA 6 on PS5 and Xbox?', 'wireless vs wired headset ps5', ['headset for gta 6', 'console headset']],
        ['cat-chairs', 'bofu', 'Best gaming chairs for long GTA 6 sessions', 'gaming chair for long sessions', ['best gaming chair', 'comfortable gaming chair']],
        ['cat-laptops', 'mofu', 'Gaming laptop or console for GTA 6: which makes sense right now?', 'gaming laptop or console for gta 6', ['laptop vs ps5', 'gta 6 laptop']],
        ['cat-handhelds', 'tofu', 'Playing GTA 6 away from the TV: PlayStation Portal, Remote Play and cloud', 'play gta 6 remotely', ['playstation portal gta 6', 'ps5 remote play']],
        ['games', 'tofu', 'How to stream GTA 6 from PS5 or Xbox to Twitch and YouTube', 'stream gta 6', ['stream ps5 to twitch', 'gta 6 streaming']],
        ['cat-mice', 'bofu', 'Best gaming mice and keyboards for Rockstar games on PC', 'best mouse for rockstar games', ['gaming mouse for gta', 'keyboard for gta']],
        ['cat-gpu', 'mofu', 'Upgrading from a GTX 1060: graphics cards ready for the next big open-world games', 'gtx 1060 upgrade', ['gpu upgrade 2026', 'graphics card for gta 6']],
        ['games', 'tofu', 'Games to play after GTA 6: open-world picks on PC and console', 'games to play after gta 6', ['games like gta 6', 'open world games 2026']],
    ];
}

/** Words per language (the facts never change between languages). */
function rocGta6Lang(string $lang): array {
    $L = [
    'en' => ['name' => 'English', 'mt' => 'GTA 6 Release Time, Price & PC Version (Nov 19, 2026)',
        'md' => 'GTA 6 unlocks at midnight local time on Nov 19, 2026: release time in every country, price, 212 GB size, length and why there is no PC version yet.',
        'h1' => 'GTA 6: release time in every country, price, size and PC version',
        'lead' => 'Grand Theft Auto VI comes out on <b>19 November 2026</b> on PlayStation 5 and Xbox Series X|S. It unlocks at midnight local time, so New Zealand plays first and the Americas last. Here is everything confirmed so far, with sources, and nothing made up.',
        'out' => 'Out now in: %s', 'soon' => 'Next unlock: %s',
        'f' => ['Release date', '19 Nov 2026', 'Platforms', 'PS5, Xbox Series X|S', 'Price (US)', '$79.99 / $99.99', 'Download size', 'up to 212 GB', 'PC version', 'not announced'],
        'why_h' => 'Why does GTA 6 come out earlier in some countries?',
        'why_p' => 'It is not a delay for anyone. Rockstar releases GTA 6 at <b>midnight local time on 19 November in every region</b>. Midnight arrives first in countries just west of the international date line, so New Zealand unlocks at 00:00 NZDT, which is still 18 November (11:00 UTC) for most of the world. Australia, Japan, India and Pakistan follow, then the Middle East, Europe and finally the Americas. US stores use Eastern time for the whole country, so players on the West Coast can start at 9 p.m. on 18 November.',
        'tbl_h' => 'GTA 6 unlock time by country', 'th' => ['Country', 'Unlocks (local time)', 'Pakistan time', 'UTC'],
        'tbl_n' => 'Midnight local time per Rockstar; US stores use Eastern time. Times worked out from each country\'s time zone on 19 November 2026.',
        'pc_h' => 'Is GTA 6 on PC? Can my PC run it?',
        'pc_p' => 'Not yet. Rockstar has announced <b>no PC version and no PC date</b>, so there are no official PC requirements: every "GTA 6 PC requirements" list online is a guess. Rockstar has brought its big games to PC later before: GTA V about 19 months after consoles and Red Dead Redemption 2 about 13 months after, but that is history, not a promise. Meanwhile you can check how your PC handles Rockstar\'s latest PC games:',
        'len_h' => 'How long is GTA 6?',
        'len_p' => 'There is no official campaign length. Rockstar\'s Rob Nelson said his own playthrough took about <b>80 hours</b>, including some optional content, so expect a long game; your time depends on how much side content you do.',
        'map_h' => 'Map, story and characters',
        'map_p' => 'GTA 6 is set in the state of <b>Leonida</b>, Rockstar\'s take on Florida, with Vice City and six regions: Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn and Ambrosia. Reports put the map at roughly twice the size of GTA V\'s (Rockstar has not given an official size). You play as <b>Jason and Lucia</b>.',
        'buy_h' => 'Where can you buy and play GTA 6?',
        'buy_p' => 'On PlayStation 5 (PlayStation Store or disc) and Xbox Series X|S (Microsoft Store or disc). Xbox Cloud Gaming is reported to start at 1 a.m. ET on 19 November. It is not on PS4, Xbox One, Switch or PC. In Pakistan, Sony and Microsoft do not list the country as an official store region, so many players buy a disc from a local shop or use an account from another region; check each store\'s terms first.',
        'gear_h' => 'Gear for GTA 6',
        'gear_p' => 'The game needs up to 212 GB free, so clear space before the pre-load. A good headset and a fast monitor or TV make a big open world feel better:',
        'del_h' => 'GTA 6 timeline', 'del' => ['December 2023: trailer 1', 'May 2025: delayed from 2025 to 26 May 2026; trailer 2', 'November 2025: delayed to 19 November 2026', '18 November 2026: unlocks in New Zealand (reviews reported the same day)', '19 November 2026: launch day on PS5 and Xbox Series X|S'],
        'posts_h' => 'GTA 6 news and guides on Run On Console', 'posts_none' => 'Our GTA 6 guides go live from 19 November.',
        'faq_h' => 'GTA 6 questions',
        'faq' => [['What time does GTA 6 come out?', 'At midnight local time on 19 November 2026 in every region. New Zealand is first (11:00 UTC on 18 November); in Pakistan it is 00:00 PKT on 19 November; US stores unlock at 00:00 Eastern time.'],
                  ['Is GTA 6 coming to PC?', 'Rockstar has not announced a PC version or date. GTA V and Red Dead Redemption 2 reached PC 13-19 months after consoles, but nothing is confirmed for GTA 6.'],
                  ['How much does GTA 6 cost?', 'In the US the Standard Edition is reported at $79.99 and the Ultimate Edition at $99.99. Prices differ by country and store.'],
                  ['How big is GTA 6?', 'Rockstar says the pre-load needs up to 212 GB depending on the console; Sony asks PS5 owners for at least 212 GB free.']],
        'src_h' => 'Sources', 'upd' => 'Updated %s. Facts are checked against the sources below; we change this page when Rockstar announces more.',
        'other' => 'This page in other languages:', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'New Zealand', 'au' => 'Australia (Sydney)', 'jp' => 'Japan', 'in' => 'India', 'pk' => 'Pakistan', 'ae' => 'UAE', 'ru' => 'Russia (Moscow)', 'eu' => 'Germany, France, Spain', 'uk' => 'United Kingdom', 'br' => 'Brazil (São Paulo)', 'us' => 'USA (Eastern time, all states)'],
        'links' => ['Can I run Red Dead Redemption 2?', 'Can I run GTA V Enhanced?', 'Bottleneck calculator', 'Upcoming PC games', 'Best gaming headsets', 'Best gaming monitors', 'PlayStation guide', 'Xbox guide', 'Cloud gaming guide', 'Gaming news']],

    'ur' => ['name' => 'اردو', 'mt' => 'GTA 6 پاکستان میں کب ریلیز ہوگا؟ وقت، قیمت اور PC ورژن',
        'md' => 'GTA 6 پاکستان میں 19 نومبر 2026 رات 12 بجے کھلے گا۔ ہر ملک کا وقت، قیمت، 212 GB سائز، گیم کی لمبائی اور PC ورژن کی پوری معلومات۔',
        'h1' => 'GTA 6 پاکستان میں کب آئے گا؟ وقت، قیمت، سائز اور PC',
        'lead' => 'Grand Theft Auto VI <b>19 نومبر 2026</b> کو PlayStation 5 اور Xbox Series X|S پر ریلیز ہو رہا ہے۔ یہ ہر ملک میں مقامی وقت کے مطابق رات 12 بجے کھلتا ہے، اس لیے نیوزی لینڈ میں سب سے پہلے آتا ہے۔ <b>پاکستان میں 19 نومبر رات 12:00 بجے</b> (یعنی 18 اور 19 نومبر کی درمیانی رات)۔ نیچے صرف تصدیق شدہ معلومات ہیں، ذرائع کے ساتھ۔',
        'out' => 'ابھی ریلیز ہو چکا: %s', 'soon' => 'اگلا ملک: %s',
        'f' => ['ریلیز کی تاریخ', '19 نومبر 2026', 'پلیٹ فارم', 'PS5، Xbox Series X|S', 'قیمت (امریکہ)', '$79.99 / $99.99', 'ڈاؤن لوڈ سائز', '212 GB تک', 'PC ورژن', 'اعلان نہیں ہوا'],
        'why_h' => 'GTA 6 کچھ ملکوں میں پہلے کیوں آ رہا ہے؟',
        'why_p' => 'یہ کسی ملک کے لیے تاخیر نہیں۔ Rockstar ہر ملک میں <b>19 نومبر کو مقامی وقت کے مطابق رات 12 بجے</b> گیم کھولتا ہے۔ رات 12 سب سے پہلے نیوزی لینڈ میں بجتے ہیں، جب پاکستان میں 18 نومبر شام 4 بجے ہوتے ہیں۔ پھر آسٹریلیا، جاپان، بھارت اور پاکستان، اس کے بعد خلیج، یورپ اور آخر میں امریکہ۔ امریکی اسٹور پورے ملک کے لیے ایسٹرن ٹائم استعمال کرتے ہیں۔',
        'tbl_h' => 'ہر ملک میں GTA 6 کھلنے کا وقت', 'th' => ['ملک', 'مقامی وقت', 'پاکستانی وقت', 'UTC'],
        'tbl_n' => 'Rockstar کے مطابق ہر جگہ مقامی وقت رات 12 بجے؛ امریکی اسٹور ایسٹرن ٹائم۔ وقت ہر ملک کے ٹائم زون سے نکالا گیا ہے۔',
        'pc_h' => 'کیا GTA 6 PC پر آئے گا؟ کیا میرا PC چلا لے گا؟',
        'pc_p' => 'ابھی نہیں۔ Rockstar نے <b>PC ورژن یا اس کی کوئی تاریخ کا اعلان نہیں کیا</b>، اس لیے PC کی کوئی سرکاری requirements موجود نہیں: انٹرنیٹ پر "GTA 6 PC requirements" کی ہر فہرست اندازہ ہے۔ پہلے GTA V تقریباً 19 مہینے اور Red Dead Redemption 2 تقریباً 13 مہینے بعد PC پر آئے تھے، مگر یہ وعدہ نہیں۔ تب تک دیکھیں آپ کا PC Rockstar کی تازہ PC گیمز کیسے چلاتا ہے:',
        'len_h' => 'GTA 6 کتنا لمبا ہے؟',
        'len_p' => 'کہانی کی سرکاری لمبائی نہیں بتائی گئی۔ Rockstar کے Rob Nelson کے مطابق ان کا پورا کھیل تقریباً <b>80 گھنٹے</b> کا تھا، کچھ اضافی مشنز سمیت۔',
        'map_h' => 'نقشہ، کہانی اور کردار',
        'map_p' => 'GTA 6 کی کہانی <b>Leonida</b> نامی ریاست میں ہے (فلوریڈا جیسی)، جس میں Vice City سمیت چھ علاقے ہیں: Vice City، Leonida Keys، Grassrivers، Mount Kalaga، Port Gellhorn اور Ambrosia۔ رپورٹس کے مطابق نقشہ GTA V سے تقریباً دگنا ہے (Rockstar نے سرکاری سائز نہیں بتایا)۔ مرکزی کردار <b>Jason اور Lucia</b> ہیں۔',
        'buy_h' => 'پاکستان میں GTA 6 کہاں سے لیں؟',
        'buy_p' => 'یہ صرف PlayStation 5 اور Xbox Series X|S پر ہے (ڈسک یا ڈیجیٹل)۔ PS4، Xbox One، Switch یا PC پر نہیں۔ Sony اور Microsoft پاکستان کو باقاعدہ اسٹور ملک کے طور پر نہیں رکھتے، اس لیے بہت سے لوگ مقامی دکان سے ڈسک لیتے ہیں یا کسی اور ملک کا اکاؤنٹ استعمال کرتے ہیں؛ خریدنے سے پہلے اسٹور کی شرائط دیکھ لیں۔ Xbox Cloud Gaming رپورٹس کے مطابق 19 نومبر کو صبح 11 بجے پاکستانی وقت (1 بجے ET) شروع ہوگا۔',
        'gear_h' => 'GTA 6 کے لیے سامان',
        'gear_p' => 'گیم کو 212 GB تک جگہ چاہیے، اس لیے پہلے جگہ خالی کریں۔ اچھا ہیڈسیٹ اور تیز مانیٹر یا ٹی وی بڑی اوپن ورلڈ کا مزہ بڑھاتے ہیں:',
        'del_h' => 'GTA 6 کی ٹائم لائن', 'del' => ['دسمبر 2023: پہلا ٹریلر', 'مئی 2025: 2025 سے 26 مئی 2026 تک تاخیر؛ دوسرا ٹریلر', 'نومبر 2025: 19 نومبر 2026 تک تاخیر', '18 نومبر 2026: نیوزی لینڈ میں ریلیز (پاکستانی وقت شام 4 بجے)', '19 نومبر 2026: پاکستان میں رات 12 بجے ریلیز'],
        'posts_h' => 'Run On Console پر GTA 6 کی خبریں اور گائیڈز (انگریزی)', 'posts_none' => 'ہماری GTA 6 گائیڈز 19 نومبر سے آئیں گی۔',
        'faq_h' => 'GTA 6 کے بارے میں سوالات',
        'faq' => [['GTA 6 پاکستان میں کتنے بجے ریلیز ہوگا؟', '19 نومبر 2026 کو رات 12:00 بجے پاکستانی وقت (18 نومبر شام 7 بجے UTC)۔ نیوزی لینڈ میں یہ 18 نومبر کو پاکستانی وقت شام 4 بجے کھل جائے گا۔'],
                  ['کیا GTA 6 PC پر ہے؟', 'نہیں۔ Rockstar نے ابھی PC ورژن یا تاریخ کا اعلان نہیں کیا۔'],
                  ['GTA 6 کی قیمت کیا ہے؟', 'امریکہ میں Standard Edition کی قیمت $79.99 اور Ultimate Edition کی $99.99 بتائی گئی ہے۔ پاکستان میں قیمت دکان اور اسٹور کے حساب سے مختلف ہوگی۔'],
                  ['GTA 6 کا سائز کتنا ہے؟', 'Rockstar کے مطابق 212 GB تک؛ PS5 پر کم از کم 212 GB خالی جگہ چاہیے۔']],
        'src_h' => 'ذرائع', 'upd' => 'آخری تبدیلی %s۔ معلومات نیچے دیے گئے ذرائع سے چیک کی گئی ہیں؛ Rockstar کے نئے اعلان پر یہ صفحہ بدلا جائے گا۔',
        'other' => 'یہ صفحہ دوسری زبانوں میں:', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'نیوزی لینڈ', 'au' => 'آسٹریلیا (سڈنی)', 'jp' => 'جاپان', 'in' => 'بھارت', 'pk' => 'پاکستان', 'ae' => 'متحدہ عرب امارات', 'ru' => 'روس (ماسکو)', 'eu' => 'جرمنی، فرانس، اسپین', 'uk' => 'برطانیہ', 'br' => 'برازیل', 'us' => 'امریکہ (ایسٹرن ٹائم)'],
        'links' => ['کیا Red Dead Redemption 2 چلے گا؟', 'کیا GTA V Enhanced چلے گا؟', 'Bottleneck کیلکولیٹر', 'آنے والی PC گیمز', 'بہترین گیمنگ ہیڈسیٹ', 'بہترین گیمنگ مانیٹر', 'PlayStation گائیڈ', 'Xbox گائیڈ', 'کلاؤڈ گیمنگ گائیڈ', 'گیمنگ خبریں']],

    'de' => ['name' => 'Deutsch', 'mt' => 'GTA 6 Release-Uhrzeit, Preis & PC-Version (19.11.2026)',
        'md' => 'GTA 6 startet am 19. November 2026 um Mitternacht Ortszeit: Uhrzeit in Deutschland und weltweit, Preis, 212 GB Größe, Spielzeit und PC-Version.',
        'h1' => 'GTA 6: Release-Uhrzeit, Preis, Größe und PC-Version',
        'lead' => 'Grand Theft Auto VI erscheint am <b>19. November 2026</b> für PlayStation 5 und Xbox Series X|S. Das Spiel wird um Mitternacht Ortszeit freigeschaltet: in Deutschland, Österreich und der Schweiz also am 19. November um 0:00 Uhr. Hier steht alles Bestätigte, mit Quellen.',
        'out' => 'Schon erschienen in: %s', 'soon' => 'Als Nächstes: %s',
        'f' => ['Release', '19.11.2026', 'Plattformen', 'PS5, Xbox Series X|S', 'Preis (USA)', '79,99 $ / 99,99 $', 'Downloadgröße', 'bis zu 212 GB', 'PC-Version', 'nicht angekündigt'],
        'why_h' => 'Warum erscheint GTA 6 in manchen Ländern früher?',
        'why_p' => 'Niemand muss länger warten: Rockstar schaltet GTA 6 <b>überall am 19. November um Mitternacht Ortszeit</b> frei. Mitternacht kommt zuerst westlich der Datumsgrenze an, deshalb spielt Neuseeland schon am 18. November um 12:00 Uhr deutscher Zeit. Danach folgen Australien, Asien, Europa und zuletzt Amerika.',
        'tbl_h' => 'GTA 6 Freischaltung nach Land', 'th' => ['Land', 'Ortszeit', 'Pakistan-Zeit', 'UTC'],
        'tbl_n' => 'Mitternacht Ortszeit laut Rockstar; US-Stores nutzen Eastern Time. Aus den Zeitzonen am 19.11.2026 berechnet.',
        'pc_h' => 'Kommt GTA 6 für PC? Läuft es auf meinem PC?',
        'pc_p' => 'Noch nicht. Rockstar hat <b>keine PC-Version und kein PC-Datum</b> angekündigt, daher gibt es keine offiziellen PC-Anforderungen: Jede Liste im Netz ist geraten. GTA V kam etwa 19 Monate, Red Dead Redemption 2 etwa 13 Monate nach den Konsolen auf den PC, das ist aber kein Versprechen. Prüfe bis dahin Rockstars aktuelle PC-Spiele:',
        'len_h' => 'Wie lang ist GTA 6?',
        'len_p' => 'Eine offizielle Spielzeit gibt es nicht. Rob Nelson von Rockstar sagte, sein eigener Durchlauf habe etwa <b>80 Stunden</b> gedauert, inklusive einiger Nebeninhalte.',
        'map_h' => 'Karte, Story und Figuren',
        'map_p' => 'GTA 6 spielt im Bundesstaat <b>Leonida</b> (Rockstars Florida) mit sechs Regionen: Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn und Ambrosia. Berichten zufolge ist die Karte etwa doppelt so groß wie die von GTA V (keine offizielle Angabe). Die Hauptfiguren sind <b>Jason und Lucia</b>.',
        'buy_h' => 'Wo kann man GTA 6 kaufen und spielen?',
        'buy_p' => 'Auf PlayStation 5 und Xbox Series X|S, digital oder auf Disc. Xbox Cloud Gaming startet Berichten zufolge am 19. November um 7:00 Uhr deutscher Zeit (1:00 Uhr ET). Nicht auf PS4, Xbox One, Switch oder PC.',
        'gear_h' => 'Hardware für GTA 6', 'gear_p' => 'Das Spiel braucht bis zu 212 GB freien Speicher. Ein gutes Headset und ein schneller Monitor oder Fernseher lohnen sich:',
        'del_h' => 'GTA 6 Zeitleiste', 'del' => ['Dezember 2023: Trailer 1', 'Mai 2025: Verschiebung von 2025 auf 26. Mai 2026; Trailer 2', 'November 2025: Verschiebung auf 19. November 2026', '18. November 2026: Freischaltung in Neuseeland', '19. November 2026: Launch in Europa um 0:00 Uhr Ortszeit'],
        'posts_h' => 'GTA 6 News und Guides auf Run On Console (Englisch)', 'posts_none' => 'Unsere GTA 6 Guides erscheinen ab dem 19. November.',
        'faq_h' => 'Fragen zu GTA 6',
        'faq' => [['Um wie viel Uhr erscheint GTA 6 in Deutschland?', 'Am 19. November 2026 um 0:00 Uhr deutscher Zeit (23:00 UTC am 18. November).'],
                  ['Gibt es GTA 6 für PC?', 'Rockstar hat noch keine PC-Version und kein Datum angekündigt.'],
                  ['Was kostet GTA 6?', 'In den USA werden 79,99 $ (Standard) und 99,99 $ (Ultimate) genannt; Preise in Euro hängen vom Store ab.'],
                  ['Wie groß ist GTA 6?', 'Laut Rockstar bis zu 212 GB, je nach Konsole.']],
        'src_h' => 'Quellen', 'upd' => 'Aktualisiert am %s. Alle Angaben sind mit den Quellen unten geprüft.',
        'other' => 'Diese Seite in anderen Sprachen:', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'Neuseeland', 'au' => 'Australien (Sydney)', 'jp' => 'Japan', 'in' => 'Indien', 'pk' => 'Pakistan', 'ae' => 'VAE', 'ru' => 'Russland (Moskau)', 'eu' => 'Deutschland, Frankreich, Spanien', 'uk' => 'Großbritannien', 'br' => 'Brasilien', 'us' => 'USA (Eastern Time)'],
        'links' => ['Läuft Red Dead Redemption 2?', 'Läuft GTA V Enhanced?', 'Bottleneck-Rechner', 'Kommende PC-Spiele', 'Beste Gaming-Headsets', 'Beste Gaming-Monitore', 'PlayStation', 'Xbox', 'Cloud Gaming', 'Gaming-News']],

    'es' => ['name' => 'Español', 'mt' => 'GTA 6: hora de lanzamiento, precio y versión PC (19 nov 2026)',
        'md' => 'GTA 6 sale el 19 de noviembre de 2026 a medianoche hora local: hora en cada país, precio, 212 GB de tamaño, duración y por qué aún no hay versión PC.',
        'h1' => 'GTA 6: hora de lanzamiento por país, precio, tamaño y PC',
        'lead' => 'Grand Theft Auto VI sale el <b>19 de noviembre de 2026</b> en PlayStation 5 y Xbox Series X|S. Se desbloquea a medianoche hora local en cada región: en España el 19 de noviembre a las 0:00. Aquí tienes todo lo confirmado, con fuentes.',
        'out' => 'Ya disponible en: %s', 'soon' => 'Próximo desbloqueo: %s',
        'f' => ['Fecha', '19 nov 2026', 'Plataformas', 'PS5, Xbox Series X|S', 'Precio (EE. UU.)', '79,99 $ / 99,99 $', 'Tamaño', 'hasta 212 GB', 'Versión PC', 'sin anunciar'],
        'why_h' => '¿Por qué GTA 6 sale antes en algunos países?',
        'why_p' => 'No es un retraso para nadie: Rockstar lanza GTA 6 <b>a medianoche hora local del 19 de noviembre en todas las regiones</b>. La medianoche llega primero a Nueva Zelanda (el 18 de noviembre a las 12:00 en España), luego a Australia, Asia, Europa y por último a América. En Estados Unidos las tiendas usan la hora del Este para todo el país.',
        'tbl_h' => 'Hora de desbloqueo de GTA 6 por país', 'th' => ['País', 'Hora local', 'Hora de Pakistán', 'UTC'],
        'tbl_n' => 'Medianoche hora local según Rockstar; las tiendas de EE. UU. usan la hora del Este. Calculado con la zona horaria de cada país.',
        'pc_h' => '¿GTA 6 sale en PC? ¿Lo puede mover mi PC?',
        'pc_p' => 'Todavía no. Rockstar <b>no ha anunciado versión PC ni fecha</b>, así que no hay requisitos oficiales para PC: cualquier lista en internet es una suposición. GTA V llegó a PC unos 19 meses después de consolas y Red Dead Redemption 2 unos 13 meses después, pero eso no es una promesa. Mientras tanto, comprueba los juegos de Rockstar en PC:',
        'len_h' => '¿Cuánto dura GTA 6?',
        'len_p' => 'No hay una duración oficial. Rob Nelson, de Rockstar, dijo que su partida duró unas <b>80 horas</b>, incluyendo contenido opcional.',
        'map_h' => 'Mapa, historia y personajes',
        'map_p' => 'GTA 6 transcurre en el estado de <b>Leonida</b> (la Florida de Rockstar) con seis regiones: Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn y Ambrosia. Según varios medios, el mapa es aproximadamente el doble que el de GTA V (sin cifra oficial). Los protagonistas son <b>Jason y Lucia</b>.',
        'buy_h' => '¿Dónde comprar y jugar GTA 6?',
        'buy_p' => 'En PlayStation 5 y Xbox Series X|S, en digital o en disco. Xbox Cloud Gaming empezaría el 19 de noviembre a la 1:00 (hora del Este de EE. UU.). No sale en PS4, Xbox One, Switch ni PC.',
        'gear_h' => 'Equipo para GTA 6', 'gear_p' => 'El juego necesita hasta 212 GB libres. Unos buenos auriculares y un monitor o TV rápido mejoran la experiencia:',
        'del_h' => 'Cronología de GTA 6', 'del' => ['Diciembre 2023: tráiler 1', 'Mayo 2025: retraso de 2025 al 26 de mayo de 2026; tráiler 2', 'Noviembre 2025: retraso al 19 de noviembre de 2026', '18 de noviembre de 2026: desbloqueo en Nueva Zelanda', '19 de noviembre de 2026: lanzamiento a medianoche hora local'],
        'posts_h' => 'Noticias y guías de GTA 6 en Run On Console (inglés)', 'posts_none' => 'Nuestras guías de GTA 6 se publican desde el 19 de noviembre.',
        'faq_h' => 'Preguntas sobre GTA 6',
        'faq' => [['¿A qué hora sale GTA 6 en España y México?', 'A medianoche hora local del 19 de noviembre de 2026: en España a las 0:00 (23:00 UTC del 18) y en Ciudad de México a las 0:00 (6:00 UTC del 19).'],
                  ['¿GTA 6 sale en PC?', 'Rockstar no ha anunciado versión PC ni fecha.'],
                  ['¿Cuánto cuesta GTA 6?', 'En EE. UU. se indican 79,99 $ (Standard) y 99,99 $ (Ultimate); el precio cambia según el país y la tienda.'],
                  ['¿Cuánto ocupa GTA 6?', 'Hasta 212 GB según la consola, según Rockstar.']],
        'src_h' => 'Fuentes', 'upd' => 'Actualizado el %s. Datos comprobados con las fuentes de abajo.',
        'other' => 'Esta página en otros idiomas:', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'Nueva Zelanda', 'au' => 'Australia (Sídney)', 'jp' => 'Japón', 'in' => 'India', 'pk' => 'Pakistán', 'ae' => 'Emiratos Árabes', 'ru' => 'Rusia (Moscú)', 'eu' => 'Alemania, Francia, España', 'uk' => 'Reino Unido', 'br' => 'Brasil', 'us' => 'EE. UU. (hora del Este)'],
        'links' => ['¿Puedo jugar Red Dead Redemption 2?', '¿Puedo jugar GTA V Enhanced?', 'Calculadora de cuello de botella', 'Próximos juegos de PC', 'Mejores auriculares gaming', 'Mejores monitores gaming', 'PlayStation', 'Xbox', 'Juego en la nube', 'Noticias gaming']],

    'pt' => ['name' => 'Português', 'mt' => 'GTA 6: horário de lançamento, preço e versão PC (19/11/2026)',
        'md' => 'GTA 6 chega em 19 de novembro de 2026 à meia-noite local: horário no Brasil e no mundo, preço, 212 GB, duração e por que ainda não há versão PC.',
        'h1' => 'GTA 6: horário de lançamento, preço, tamanho e versão PC',
        'lead' => 'Grand Theft Auto VI chega em <b>19 de novembro de 2026</b> ao PlayStation 5 e Xbox Series X|S. O jogo libera à meia-noite no horário local: em Brasília, 0h do dia 19 de novembro. Aqui está tudo o que foi confirmado, com fontes.',
        'out' => 'Já liberado em: %s', 'soon' => 'Próxima liberação: %s',
        'f' => ['Lançamento', '19/11/2026', 'Plataformas', 'PS5, Xbox Series X|S', 'Preço (EUA)', 'US$ 79,99 / 99,99', 'Tamanho', 'até 212 GB', 'Versão PC', 'não anunciada'],
        'why_h' => 'Por que GTA 6 sai antes em alguns países?',
        'why_p' => 'Não é atraso para ninguém: a Rockstar libera GTA 6 <b>à meia-noite do horário local de 19 de novembro em todas as regiões</b>. A meia-noite chega primeiro à Nova Zelândia (8h da manhã de 18 de novembro em Brasília), depois Austrália, Ásia, Europa e por último as Américas.',
        'tbl_h' => 'Horário de liberação de GTA 6 por país', 'th' => ['País', 'Horário local', 'Horário do Paquistão', 'UTC'],
        'tbl_n' => 'Meia-noite local segundo a Rockstar; lojas dos EUA usam o horário do Leste. Calculado pelo fuso de cada país.',
        'pc_h' => 'GTA 6 vai sair para PC? Meu PC roda?',
        'pc_p' => 'Ainda não. A Rockstar <b>não anunciou versão PC nem data</b>, então não existem requisitos oficiais para PC: toda lista na internet é palpite. GTA V chegou ao PC cerca de 19 meses depois dos consoles e Red Dead Redemption 2 cerca de 13 meses depois, mas isso não é promessa. Enquanto isso, veja os jogos da Rockstar no PC:',
        'len_h' => 'Quanto tempo dura GTA 6?',
        'len_p' => 'Não há duração oficial. Rob Nelson, da Rockstar, disse que sua jogatina levou cerca de <b>80 horas</b>, com parte do conteúdo opcional.',
        'map_h' => 'Mapa, história e personagens',
        'map_p' => 'GTA 6 se passa no estado de <b>Leonida</b> (a Flórida da Rockstar), com seis regiões: Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn e Ambrosia. Segundo reportagens, o mapa tem cerca do dobro do tamanho do de GTA V (sem número oficial). Os protagonistas são <b>Jason e Lucia</b>.',
        'buy_h' => 'Onde comprar e jogar GTA 6?',
        'buy_p' => 'No PlayStation 5 e Xbox Series X|S, digital ou em disco. O Xbox Cloud Gaming deve começar em 19 de novembro às 3h de Brasília (1h ET). Não sai para PS4, Xbox One, Switch ou PC.',
        'gear_h' => 'Equipamento para GTA 6', 'gear_p' => 'O jogo precisa de até 212 GB livres. Um bom headset e um monitor ou TV rápidos fazem diferença:',
        'del_h' => 'Linha do tempo de GTA 6', 'del' => ['Dezembro de 2023: trailer 1', 'Maio de 2025: adiado de 2025 para 26 de maio de 2026; trailer 2', 'Novembro de 2025: adiado para 19 de novembro de 2026', '18 de novembro de 2026: liberado na Nova Zelândia', '19 de novembro de 2026: lançamento à meia-noite local'],
        'posts_h' => 'Notícias e guias de GTA 6 no Run On Console (inglês)', 'posts_none' => 'Nossos guias de GTA 6 saem a partir de 19 de novembro.',
        'faq_h' => 'Perguntas sobre GTA 6',
        'faq' => [['Que horas GTA 6 sai no Brasil?', 'À 0h de 19 de novembro de 2026 no horário de Brasília (3h UTC). Nos EUA, as lojas liberam à 0h do horário do Leste.'],
                  ['GTA 6 vai sair para PC?', 'A Rockstar ainda não anunciou versão PC nem data.'],
                  ['Quanto custa GTA 6?', 'Nos EUA, US$ 79,99 (Standard) e US$ 99,99 (Ultimate); no Brasil o preço depende da loja.'],
                  ['Qual o tamanho de GTA 6?', 'Até 212 GB, dependendo do console, segundo a Rockstar.']],
        'src_h' => 'Fontes', 'upd' => 'Atualizado em %s. Dados conferidos nas fontes abaixo.',
        'other' => 'Esta página em outros idiomas:', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'Nova Zelândia', 'au' => 'Austrália (Sydney)', 'jp' => 'Japão', 'in' => 'Índia', 'pk' => 'Paquistão', 'ae' => 'Emirados Árabes', 'ru' => 'Rússia (Moscou)', 'eu' => 'Alemanha, França, Espanha', 'uk' => 'Reino Unido', 'br' => 'Brasil (Brasília)', 'us' => 'EUA (horário do Leste)'],
        'links' => ['Meu PC roda Red Dead Redemption 2?', 'Meu PC roda GTA V Enhanced?', 'Calculadora de gargalo', 'Próximos jogos de PC', 'Melhores headsets gamer', 'Melhores monitores gamer', 'PlayStation', 'Xbox', 'Jogos na nuvem', 'Notícias de games']],

    'fr' => ['name' => 'Français', 'mt' => 'GTA 6 : heure de sortie, prix et version PC (19 nov. 2026)',
        'md' => 'GTA 6 sort le 19 novembre 2026 à minuit heure locale : heure de sortie par pays, prix, 212 Go, durée de vie et pourquoi il n\'y a pas encore de version PC.',
        'h1' => 'GTA 6 : heure de sortie, prix, taille et version PC',
        'lead' => 'Grand Theft Auto VI sort le <b>19 novembre 2026</b> sur PlayStation 5 et Xbox Series X|S. Le jeu se débloque à minuit heure locale : en France, le 19 novembre à 0 h. Voici tout ce qui est confirmé, avec les sources.',
        'out' => 'Déjà sorti : %s', 'soon' => 'Prochain déblocage : %s',
        'f' => ['Sortie', '19 nov. 2026', 'Plateformes', 'PS5, Xbox Series X|S', 'Prix (États-Unis)', '79,99 $ / 99,99 $', 'Taille', 'jusqu\'à 212 Go', 'Version PC', 'non annoncée'],
        'why_h' => 'Pourquoi GTA 6 sort-il plus tôt dans certains pays ?',
        'why_p' => 'Ce n\'est un retard pour personne : Rockstar débloque GTA 6 <b>à minuit heure locale le 19 novembre dans toutes les régions</b>. Minuit arrive d\'abord en Nouvelle-Zélande (le 18 novembre à 12 h en France), puis en Australie, en Asie, en Europe et enfin en Amérique.',
        'tbl_h' => 'Heure de déblocage de GTA 6 par pays', 'th' => ['Pays', 'Heure locale', 'Heure du Pakistan', 'UTC'],
        'tbl_n' => 'Minuit heure locale selon Rockstar ; les boutiques américaines utilisent l\'heure de l\'Est. Calculé avec le fuseau de chaque pays.',
        'pc_h' => 'GTA 6 sur PC ? Mon PC peut-il le faire tourner ?',
        'pc_p' => 'Pas encore. Rockstar n\'a annoncé <b>ni version PC ni date</b> : il n\'existe donc aucune configuration requise officielle, et toute liste en ligne est une supposition. GTA V est arrivé sur PC environ 19 mois après les consoles et Red Dead Redemption 2 environ 13 mois après, mais ce n\'est pas une promesse. En attendant, vérifiez les jeux Rockstar sur PC :',
        'len_h' => 'Quelle est la durée de vie de GTA 6 ?',
        'len_p' => 'Aucune durée officielle. Rob Nelson, de Rockstar, a indiqué que sa partie avait duré environ <b>80 heures</b>, contenu optionnel compris.',
        'map_h' => 'Carte, histoire et personnages',
        'map_p' => 'GTA 6 se déroule dans l\'État de <b>Leonida</b> (la Floride selon Rockstar), avec six régions : Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn et Ambrosia. Selon la presse, la carte fait environ deux fois celle de GTA V (pas de chiffre officiel). Les héros sont <b>Jason et Lucia</b>.',
        'buy_h' => 'Où acheter et jouer à GTA 6 ?',
        'buy_p' => 'Sur PlayStation 5 et Xbox Series X|S, en numérique ou sur disque. Le Xbox Cloud Gaming démarrerait le 19 novembre à 7 h en France (1 h ET). Pas de version PS4, Xbox One, Switch ni PC.',
        'gear_h' => 'Matériel pour GTA 6', 'gear_p' => 'Le jeu demande jusqu\'à 212 Go libres. Un bon casque et un écran ou une TV réactifs font la différence :',
        'del_h' => 'Chronologie de GTA 6', 'del' => ['Décembre 2023 : bande-annonce 1', 'Mai 2025 : report de 2025 au 26 mai 2026 ; bande-annonce 2', 'Novembre 2025 : report au 19 novembre 2026', '18 novembre 2026 : déblocage en Nouvelle-Zélande', '19 novembre 2026 : sortie à minuit heure locale'],
        'posts_h' => 'Actus et guides GTA 6 sur Run On Console (anglais)', 'posts_none' => 'Nos guides GTA 6 arrivent à partir du 19 novembre.',
        'faq_h' => 'Questions sur GTA 6',
        'faq' => [['À quelle heure sort GTA 6 en France ?', 'Le 19 novembre 2026 à 0 h heure française (23 h UTC le 18 novembre).'],
                  ['GTA 6 sortira-t-il sur PC ?', 'Rockstar n\'a annoncé ni version PC ni date.'],
                  ['Combien coûte GTA 6 ?', 'Aux États-Unis, 79,99 $ (Standard) et 99,99 $ (Ultimate) ; le prix en euros dépend de la boutique.'],
                  ['Quelle est la taille de GTA 6 ?', 'Jusqu\'à 212 Go selon la console, d\'après Rockstar.']],
        'src_h' => 'Sources', 'upd' => 'Mis à jour le %s. Informations vérifiées avec les sources ci-dessous.',
        'other' => 'Cette page dans d\'autres langues :', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'Nouvelle-Zélande', 'au' => 'Australie (Sydney)', 'jp' => 'Japon', 'in' => 'Inde', 'pk' => 'Pakistan', 'ae' => 'Émirats arabes unis', 'ru' => 'Russie (Moscou)', 'eu' => 'Allemagne, France, Espagne', 'uk' => 'Royaume-Uni', 'br' => 'Brésil', 'us' => 'États-Unis (heure de l\'Est)'],
        'links' => ['Mon PC fait-il tourner Red Dead Redemption 2 ?', 'Mon PC fait-il tourner GTA V Enhanced ?', 'Calculateur de goulot', 'Jeux PC à venir', 'Meilleurs casques gaming', 'Meilleurs écrans gaming', 'PlayStation', 'Xbox', 'Cloud gaming', 'Actus gaming']],

    'ru' => ['name' => 'Русский', 'mt' => 'GTA 6: время выхода, цена и версия для ПК (19.11.2026)',
        'md' => 'GTA 6 выходит 19 ноября 2026 года в полночь по местному времени: время выхода по странам, цена, размер 212 ГБ, длительность и версия для ПК.',
        'h1' => 'GTA 6: время выхода по странам, цена, размер и ПК',
        'lead' => 'Grand Theft Auto VI выходит <b>19 ноября 2026 года</b> на PlayStation 5 и Xbox Series X|S. Игра открывается в полночь по местному времени: в Москве 19 ноября в 0:00. Здесь всё подтверждённое, с источниками.',
        'out' => 'Уже вышла: %s', 'soon' => 'Следующая: %s',
        'f' => ['Дата выхода', '19.11.2026', 'Платформы', 'PS5, Xbox Series X|S', 'Цена (США)', '$79,99 / $99,99', 'Размер', 'до 212 ГБ', 'Версия для ПК', 'не анонсирована'],
        'why_h' => 'Почему GTA 6 выходит в одних странах раньше?',
        'why_p' => 'Это не задержка: Rockstar открывает GTA 6 <b>в полночь 19 ноября по местному времени во всех регионах</b>. Полночь сначала наступает в Новой Зеландии (18 ноября в 14:00 по Москве), затем в Австралии, Азии, Европе и в конце в Америке.',
        'tbl_h' => 'Время выхода GTA 6 по странам', 'th' => ['Страна', 'Местное время', 'Время Пакистана', 'UTC'],
        'tbl_n' => 'Полночь по местному времени по данным Rockstar; магазины США используют восточное время. Рассчитано по часовым поясам.',
        'pc_h' => 'Выйдет ли GTA 6 на ПК? Потянет ли мой ПК?',
        'pc_p' => 'Пока нет. Rockstar <b>не анонсировала версию для ПК и дату</b>, поэтому официальных системных требований нет: любой список в интернете — догадка. GTA V вышла на ПК примерно через 19 месяцев после консолей, Red Dead Redemption 2 — примерно через 13, но это не обещание. Пока проверьте игры Rockstar на ПК:',
        'len_h' => 'Сколько длится GTA 6?',
        'len_p' => 'Официальной длительности нет. Роб Нельсон из Rockstar сказал, что его прохождение заняло около <b>80 часов</b>, включая часть дополнительного контента.',
        'map_h' => 'Карта, сюжет и герои',
        'map_p' => 'Действие GTA 6 происходит в штате <b>Леонида</b> (Флорида в версии Rockstar) с шестью регионами: Vice City, Leonida Keys, Grassrivers, Mount Kalaga, Port Gellhorn и Ambrosia. По данным СМИ, карта примерно вдвое больше, чем в GTA V (официального размера нет). Главные герои — <b>Джейсон и Лусия</b>.',
        'buy_h' => 'Где купить и играть в GTA 6?',
        'buy_p' => 'На PlayStation 5 и Xbox Series X|S, цифровая версия или диск. Xbox Cloud Gaming, по данным СМИ, начнёт работу 19 ноября в 9:00 по Москве (1:00 ET). На PS4, Xbox One, Switch и ПК игры нет.',
        'gear_h' => 'Техника для GTA 6', 'gear_p' => 'Игре нужно до 212 ГБ свободного места. Хорошая гарнитура и быстрый монитор или ТВ улучшат впечатления:',
        'del_h' => 'Хронология GTA 6', 'del' => ['Декабрь 2023: трейлер 1', 'Май 2025: перенос с 2025 года на 26 мая 2026; трейлер 2', 'Ноябрь 2025: перенос на 19 ноября 2026', '18 ноября 2026: выход в Новой Зеландии', '19 ноября 2026: выход в полночь по местному времени'],
        'posts_h' => 'Новости и гайды по GTA 6 на Run On Console (англ.)', 'posts_none' => 'Наши гайды по GTA 6 выходят с 19 ноября.',
        'faq_h' => 'Вопросы о GTA 6',
        'faq' => [['Во сколько выходит GTA 6 в Москве?', '19 ноября 2026 года в 0:00 по московскому времени (21:00 UTC 18 ноября).'],
                  ['Будет ли GTA 6 на ПК?', 'Rockstar пока не анонсировала версию для ПК и дату.'],
                  ['Сколько стоит GTA 6?', 'В США — $79,99 (Standard) и $99,99 (Ultimate); цена зависит от страны и магазина.'],
                  ['Сколько весит GTA 6?', 'По данным Rockstar — до 212 ГБ в зависимости от консоли.']],
        'src_h' => 'Источники', 'upd' => 'Обновлено %s. Данные сверены с источниками ниже.',
        'other' => 'Эта страница на других языках:', 'crumb' => 'GTA 6',
        'c' => ['nz' => 'Новая Зеландия', 'au' => 'Австралия (Сидней)', 'jp' => 'Япония', 'in' => 'Индия', 'pk' => 'Пакистан', 'ae' => 'ОАЭ', 'ru' => 'Россия (Москва)', 'eu' => 'Германия, Франция, Испания', 'uk' => 'Великобритания', 'br' => 'Бразилия', 'us' => 'США (восточное время)'],
        'links' => ['Потянет ли ПК Red Dead Redemption 2?', 'Потянет ли ПК GTA V Enhanced?', 'Калькулятор узкого места', 'Будущие игры для ПК', 'Лучшие игровые гарнитуры', 'Лучшие игровые мониторы', 'PlayStation', 'Xbox', 'Облачный гейминг', 'Игровые новости']],
    ];
    return $L[$lang] ?? $L['en'];
}

/** Language of a slug ('gta-6' => 'en', 'gta-6-urdu' => 'ur'), or '' when the slug is not a GTA 6 page. */
function rocGta6LangOf(string $slug): string {
    foreach (ROC_GTA6_PAGES as $l => $p) if ('/' . $slug . '/' === $p) return $l;
    return '';
}

/** Unlock rows: [zone key, local text, Pakistan text, UTC text, unlock timestamp]. */
function rocGta6Rows(): array {
    $rows = [];
    $pk = new DateTimeZone('Asia/Karachi');
    foreach (ROC_GTA6_ZONES as [$k, $tz]) {
        $d = new DateTime('2026-11-19 00:00:00', new DateTimeZone($tz));
        $ts = $d->getTimestamp();
        $p = (new DateTime('@' . $ts))->setTimezone($pk);
        $u = new DateTime('@' . $ts);
        $rows[] = [$k, '00:00, 19 Nov', $p->format('H:i, j M'), $u->format('H:i, j M'), $ts];
    }
    usort($rows, function ($a, $b) { return $a[4] <=> $b[4]; });
    return $rows;
}

/** GTA 6 posts on the site, newest first (the hub links every one of them). */
function rocGta6Posts(?PDO $pdo, int $limit = 30): array {
    if (!$pdo) return [];
    try {
        $st = $pdo->prepare("SELECT title, slug, published_at, category FROM blogs WHERE status = 'published' AND published_at <= ? AND (LOWER(title) LIKE '%gta 6%' OR LOWER(title) LIKE '%gta vi%' OR LOWER(title) LIKE '%grand theft auto vi%' OR LOWER(title) LIKE '%gta6%') ORDER BY published_at DESC LIMIT " . (int)$limit);
        $st->execute([gmdate('Y-m-d H:i:s')]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $b) {
            $c = trim((string)$b['category']);
            $news = strcasecmp($c, 'News') === 0; $guide = (bool)preg_match('/^guides?\s*(:.*)?$/i', $c);
            $out[] = $b + ['url' => ($news ? '/news/' : ($guide ? '/guides/' : '/blogs/')) . $b['slug'] . '/', 'kind' => $news ? 'News' : ($guide ? 'Guide' : 'Blog')];
        }
        return $out;
    } catch (\Throwable $e) { return []; }
}

/** Hreflang for every language version. */
function rocGta6Alternates(): string {
    $h = '';
    foreach (ROC_GTA6_PAGES as $l => $p) $h .= '    <link rel="alternate" hreflang="' . $l . '" href="' . ROC_PUBLIC_URL . $p . "\" />\n";
    return $h . '    <link rel="alternate" hreflang="x-default" href="' . ROC_PUBLIC_URL . ROC_GTA6_PAGES['en'] . "\" />\n";
}

/** Sitemap lines for page-render.php. */
function rocGta6SitemapLines(?PDO $pdo): string {
    $posts = rocGta6Posts($pdo, 1);
    $mod = max((int)strtotime(ROC_GTA6_CHANGED . ' 12:00 UTC'), $posts ? (int)strtotime($posts[0]['published_at'] . ' UTC') : 0);
    $out = '';
    foreach (ROC_GTA6_PAGES as $l => $p) $out .= rocSmLine(ROC_PUBLIC_URL . $p, $mod, rocGta6Lang($l)['mt']);
    return $out;
}

function rocGta6Page(string $root, ?PDO $pdo, string $lang): void {
    $L = rocGta6Lang($lang);
    $path = ROC_GTA6_PAGES[$lang] ?? ROC_GTA6_PAGES['en'];
    $url = ROC_PUBLIC_URL . $path;
    [$headTop, $bodyToMain, $mainOpen, , $afterMain] = rocTemplate($root, 'blog-post.html');
    $headTop = (string)preg_replace('/<html\s+lang="en"/i', '<html lang="' . $lang . '"', $headTop, 1);
    $posts = rocGta6Posts($pdo);
    $mod = max((int)strtotime(ROC_GTA6_CHANGED . ' 12:00 UTC'), $posts ? (int)strtotime($posts[0]['published_at'] . ' UTC') : 0);
    $rows = rocGta6Rows();
    $now = time();
    $out = []; $next = '';
    foreach ($rows as $r) { if ($r[4] <= $now) $out[] = $L['c'][$r[0]]; elseif ($next === '') $next = $L['c'][$r[0]] . ' (' . $r[2] . ' PKT)'; }
    $rtl = $lang === 'ur';

    $faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]]; }, $L['faq'])];
    $ld = [
        ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => ROC_PUBLIC_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'GTA 6', 'item' => $url]]],
        ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $L['h1'], 'inLanguage' => $lang, 'url' => $url,
         'datePublished' => '2026-11-18T12:00:00+00:00', 'dateModified' => gmdate('c', $mod),
         'author' => ['@type' => 'Person', 'name' => 'Omar Abobakar', 'url' => ROC_PUBLIC_URL . '/author/omar-abobakar/'],
         'publisher' => ['@type' => 'Organization', 'name' => 'Run On Console', 'url' => ROC_PUBLIC_URL . '/'],
         'about' => ['@type' => 'VideoGame', 'name' => 'Grand Theft Auto VI', 'alternateName' => ['GTA 6', 'GTA VI'],
                     'gamePlatform' => ['PlayStation 5', 'Xbox Series X|S'], 'datePublished' => '2026-11-19',
                     'publisher' => ['@type' => 'Organization', 'name' => 'Rockstar Games'], 'genre' => 'Action-adventure']],
        $faqLd,
    ];
    $h  = '<title>' . rocH($L['mt']) . "</title>\n";
    $h .= '    <meta name="description" content="' . rocH($L['md']) . "\" />\n";
    $h .= "    <meta name=\"robots\" content=\"index, follow, max-image-preview:large\" />\n";
    $h .= '    <link rel="canonical" href="' . $url . "\" />\n" . rocGta6Alternates();
    $h .= "    <meta property=\"og:type\" content=\"article\" />\n";
    $h .= '    <meta property="og:url" content="' . $url . "\" />\n";
    $h .= '    <meta property="og:title" content="' . rocH($L['mt']) . "\" />\n";
    $h .= '    <meta property="og:description" content="' . rocH($L['md']) . "\" />\n";
    $h .= "    <meta property=\"og:site_name\" content=\"Run On Console\" />\n";
    foreach ($ld as $j) $h .= rocJsonLd($j);
    $h .= rocArticleStyles();
    $h .= '<style>.g6-facts{display:grid;grid-template-columns:repeat(2,1fr);gap:.7rem}@media(min-width:720px){.g6-facts{grid-template-columns:repeat(5,1fr)}}'
        . '.g6-facts div{border:1px solid #d1fae5;background:#f0fdf4;border-radius:1rem;padding:.8rem;text-align:center}.g6-facts b{display:block;font:800 1.05rem Outfit,Inter,sans-serif;color:#047857}.g6-facts span{font-size:.78rem;color:#475569}'
        . '.g6-card{border:1px solid #e2e8f0;border-radius:1.25rem;background:#fff;padding:1.2rem}.g6-card h2{font:800 1.3rem Outfit,Inter,sans-serif;color:#0f172a;margin:0 0 .6rem}'
        . '.g6-live{border-radius:1rem;padding:.8rem 1rem;background:#ecfdf5;border:1px solid #6ee7b7;color:#065f46;font-weight:700;font-size:.92rem}'
        . '.g6-tbl{width:100%;border-collapse:collapse;font-size:.88rem}.g6-tbl th,.g6-tbl td{border-bottom:1px solid #e2e8f0;padding:.5rem;text-align:start}.g6-tbl th{background:#f8fafc;color:#334155}.g6-tbl tr.on td{background:#f0fdf4}'
        . '.g6-links{display:flex;flex-wrap:wrap;gap:.5rem}.g6-links a{border-radius:.8rem;padding:.5rem .9rem;font-weight:800;font-size:.85rem;text-decoration:none;border:1px solid #6ee7b7;color:#065f46}'
        . '.g6-posts li{margin:.35rem 0}.g6-posts a{color:#047857;font-weight:700}.g6-tl li{margin:.3rem 0}'
        . 'html.roc-dark .g6-card{background:#0b1220;border-color:#1e293b}html.roc-dark .g6-card h2{color:#e2e8f0}html.roc-dark .g6-facts div,html.roc-dark .g6-live{background:rgba(16,185,129,.08);border-color:#065f46;color:#a7f3d0}'
        . 'html.roc-dark .g6-tbl th{background:#111827;color:#cbd5e1}html.roc-dark .g6-tbl td{border-color:#1e293b}html.roc-dark .g6-tbl tr.on td{background:rgba(16,185,129,.08)}html.roc-dark .g6-links a{color:#a7f3d0;border-color:#065f46}</style>';
    $h .= "    <script src=\"/roc-nav.js\" defer></script>\n  ";

    $p = function (string $html) { return '<p class="text-slate-700 leading-relaxed">' . $html . '</p>'; };
    $lk = $L['links'];
    $m  = $mainOpen . '<article class="max-w-4xl mx-auto py-6 space-y-6" lang="' . $lang . '"' . ($rtl ? ' dir="rtl"' : '') . '>'
        . '<nav aria-label="Breadcrumb" class="text-xs text-slate-500 font-medium"><a href="/" class="hover:text-emerald-600">Home</a> <span aria-hidden="true">›</span> <span class="text-slate-700">' . rocH($L['crumb']) . '</span></nav>'
        . '<header class="space-y-3"><h1 class="font-display font-extrabold text-3xl sm:text-5xl text-slate-900 leading-tight">' . rocH($L['h1']) . '</h1>' . $p($L['lead']) . '</header>';
    if ($out || $next) $m .= '<div class="g6-live" role="status">' . ($out ? rocH(sprintf($L['out'], implode(', ', $out))) : '') . ($out && $next ? '<br>' : '') . ($next ? rocH(sprintf($L['soon'], $next)) : '') . '</div>';
    $m .= '<section class="g6-facts" aria-label="GTA 6">';
    for ($i = 0; $i < 10; $i += 2) $m .= '<div><b>' . rocH($L['f'][$i + 1]) . '</b><span>' . rocH($L['f'][$i]) . '</span></div>';
    $m .= '</section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['why_h']) . '</h2>' . $p($L['why_p']) . '</section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['tbl_h']) . '</h2><div style="overflow-x:auto"><table class="g6-tbl"><thead><tr>';
    foreach ($L['th'] as $t) $m .= '<th scope="col">' . rocH($t) . '</th>';
    $m .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $local = $r[0] === 'us' ? '00:00, 19 Nov (ET) · 21:00, 18 Nov (PT)' : $r[1];
        $m .= '<tr' . ($r[0] === 'pk' && $lang === 'ur' ? ' class="on"' : '') . '><td>' . rocH($L['c'][$r[0]]) . '</td><td dir="ltr">' . rocH($local) . '</td><td dir="ltr">' . rocH($r[2]) . '</td><td dir="ltr">' . rocH($r[3]) . '</td></tr>';
    }
    $m .= '</tbody></table></div><p class="text-xs text-slate-500" style="margin-top:.6rem">' . rocH($L['tbl_n']) . '</p></section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['pc_h']) . '</h2>' . $p($L['pc_p'])
        . '<div class="g6-links" style="margin-top:.7rem"><a href="/can-i-run/red-dead-redemption-2/">' . rocH($lk[0]) . '</a><a href="/can-i-run/grand-theft-auto-v-enhanced/">' . rocH($lk[1]) . '</a>'
        . '<a href="/can-i-run/bottleneck-calculator/">' . rocH($lk[2]) . '</a><a href="/can-i-run/upcoming/">' . rocH($lk[3]) . '</a></div></section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['len_h']) . '</h2>' . $p($L['len_p']) . '</section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['map_h']) . '</h2>' . $p($L['map_p']) . '</section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['buy_h']) . '</h2>' . $p($L['buy_p'])
        . '<div class="g6-links" style="margin-top:.7rem"><a href="/gaming-platforms/sony-playstation/">' . rocH($lk[6]) . '</a><a href="/gaming-platforms/microsoft-xbox/">' . rocH($lk[7]) . '</a><a href="/gaming-platforms/cloud-gaming/">' . rocH($lk[8]) . '</a></div></section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['gear_h']) . '</h2>' . $p($L['gear_p'])
        . '<div class="g6-links" style="margin-top:.7rem"><a href="/category/audio/">' . rocH($lk[4]) . '</a><a href="/category/monitors/">' . rocH($lk[5]) . '</a></div></section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['posts_h']) . '</h2>';
    if ($posts) {
        $m .= '<ul class="g6-posts" dir="ltr" lang="en">';
        foreach ($posts as $b) $m .= '<li><a href="' . rocH($b['url']) . '">' . rocH($b['title']) . '</a> <small class="text-slate-500">' . rocH($b['kind']) . '</small></li>';
        $m .= '</ul>';
    } else $m .= $p(rocH($L['posts_none']));
    $m .= '<p style="margin-top:.6rem"><a class="text-emerald-700 font-bold" href="/news/">' . rocH($lk[9]) . ' &rarr;</a></p></section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['del_h']) . '</h2><ul class="g6-tl list-disc ps-5 text-slate-700">';
    foreach ($L['del'] as $d) $m .= '<li>' . rocH($d) . '</li>';
    $m .= '</ul></section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['faq_h']) . '</h2>';
    foreach ($L['faq'] as $f) $m .= '<h3 class="font-bold text-slate-900" style="margin-top:.8rem">' . rocH($f[0]) . '</h3>' . $p(rocH($f[1]));
    $m .= '</section>';
    $m .= '<section class="g6-card"><h2>' . rocH($L['src_h']) . '</h2><ul class="list-disc ps-5 text-sm" dir="ltr">';
    foreach (ROC_GTA6_SOURCES as $s) $m .= '<li><a class="text-emerald-700" href="' . rocH($s[1]) . '" target="_blank" rel="noopener">' . rocH($s[0]) . '</a></li>';
    $m .= '</ul><p class="text-xs text-slate-500" style="margin-top:.6rem">' . rocH(sprintf($L['upd'], gmdate('Y-m-d', $mod))) . '</p></section>';
    $m .= '<nav class="text-sm text-slate-600" aria-label="Languages">' . rocH($L['other']) . ' ';
    $ls = [];
    foreach (ROC_GTA6_PAGES as $l => $pp) if ($l !== $lang) $ls[] = '<a class="text-emerald-700 font-bold" hreflang="' . $l . '" lang="' . $l . '" href="' . $pp . '">' . rocH(rocGta6Lang($l)['name']) . '</a>';
    $m .= implode(' · ', $ls) . '</nav>';
    $m .= '</article>';
    rocSendHtml($headTop . $h . $bodyToMain . $m . $afterMain, $mod);
}

/* ------------------------------------------------------------- Deploy 188 */

/** True when a title or keyword is about GTA 6. */
function rocGta6Is(string $text): bool { return (bool)preg_match('/\b(gta ?(6|vi)|grand theft auto (6|vi))\b/i', $text); }

/**
 * GTA 6 guides for the guide writer (Tuesday, Friday and Saturday, 12:00 and 18:00) until 31 December:
 * [key, title idea, type, hardware group, product category, focus keyword, extra rules]. Console, TV and audio
 * steps that are true for every game, so the AI never has to invent GTA 6 menus.
 */
function rocGta6Guides(): array {
    $x = ROC_GTA6_FACTS . "\nGUIDE RULES: link /gta-6/ once in the first two paragraphs (anchor \"GTA 6 release date, price and PC status\"). Explain only console, TV, network and audio steps that are true for every game; never invent GTA 6 menus, graphics modes or settings names.";
    $g = [
        ['gta6-guide-ps5-ssd', 'How to add an M.2 SSD to your PS5 for GTA 6 and other big games', 'Setup', 'gaming-hardware', 'handhelds', 'ps5 m.2 ssd install'],
        ['gta6-guide-xbox-storage', 'How to add storage to Xbox Series X|S for GTA 6', 'Setup', 'gaming-hardware', 'handhelds', 'xbox series x storage expansion'],
        ['gta6-guide-download-faster', 'How to download GTA 6 faster on PS5 and Xbox', 'How to use', 'gaming-hardware', 'handhelds', 'download gta 6 faster'],
        ['gta6-guide-rest-mode', 'How to keep GTA 6 updated with Rest Mode and background downloads', 'How to use', 'gaming-hardware', 'handhelds', 'ps5 rest mode downloads'],
        ['gta6-guide-ps5-hdr', 'How to set up HDR on PS5 for GTA 6', 'Setup', 'pc-hardware', 'monitors', 'ps5 hdr settings'],
        ['gta6-guide-xbox-hdr', 'How to calibrate HDR on Xbox Series X|S for GTA 6', 'Setup', 'pc-hardware', 'monitors', 'xbox hdr calibration'],
        ['gta6-guide-120hz-vrr', 'How to turn on 120 Hz and VRR on PS5 and Xbox for GTA 6', 'Setup', 'pc-hardware', 'monitors', 'ps5 120hz vrr settings'],
        ['gta6-guide-tv-game-mode', 'How to turn on Game Mode on your TV to cut input lag in GTA 6', 'Setup', 'pc-hardware', 'monitors', 'tv game mode input lag'],
        ['gta6-guide-ps5-3d-audio', 'How to set up 3D Audio on PS5 with a headset for GTA 6', 'Setup', 'gaming-hardware', 'audio', 'ps5 3d audio headset'],
        ['gta6-guide-xbox-spatial', 'How to set up spatial sound on Xbox Series X|S for GTA 6', 'Setup', 'gaming-hardware', 'audio', 'xbox spatial sound'],
        ['gta6-guide-dualsense', 'How to set up and customise your DualSense controller for GTA 6', 'Setup', 'gaming-hardware', 'handhelds', 'dualsense settings'],
        ['gta6-guide-xbox-controller', 'How to update and remap an Xbox controller for GTA 6', 'Setup', 'gaming-hardware', 'handhelds', 'xbox controller remap'],
        ['gta6-guide-remote-play', 'How to play GTA 6 on your phone with PS5 Remote Play', 'How to use', 'gaming-hardware', 'handhelds', 'ps5 remote play phone'],
        ['gta6-guide-ps-portal', 'How to set up PlayStation Portal to play GTA 6 away from the TV', 'Setup', 'gaming-hardware', 'handhelds', 'playstation portal setup'],
        ['gta6-guide-record-ps5', 'How to record and share GTA 6 clips on PS5', 'How to use', 'gaming-hardware', 'handhelds', 'record gameplay ps5'],
        ['gta6-guide-record-xbox', 'How to capture and share GTA 6 clips on Xbox Series X|S', 'How to use', 'gaming-hardware', 'handhelds', 'capture clips xbox'],
        ['gta6-guide-stream-ps5', 'How to stream GTA 6 from PS5 to Twitch or YouTube', 'Setup', 'gaming-hardware', 'audio', 'stream ps5 to twitch'],
        ['gta6-guide-capture-card', 'How to record GTA 6 with a capture card from PS5 or Xbox', 'Setup', 'gaming-hardware', 'laptops', 'capture card setup'],
        ['gta6-guide-parental-ps5', 'How to set up parental controls on PS5 for mature games like GTA 6', 'How to use', 'gaming-hardware', 'handhelds', 'ps5 parental controls'],
        ['gta6-guide-parental-xbox', 'How to set up Xbox family settings for mature games like GTA 6', 'How to use', 'gaming-hardware', 'handhelds', 'xbox family settings'],
        ['gta6-guide-ethernet', 'How to get a stable connection for GTA 6: Wi-Fi or Ethernet', 'Setup', 'gaming-hardware', 'handhelds', 'wifi vs ethernet gaming'],
        ['gta6-guide-nat', 'How to fix NAT type on PS5 and Xbox before playing GTA 6 online', 'How to use', 'gaming-hardware', 'handhelds', 'fix nat type ps5'],
        ['gta6-guide-stick-drift', 'How to fix stick drift on DualSense and Xbox controllers', 'How to use', 'gaming-hardware', 'handhelds', 'fix controller stick drift'],
        ['gta6-guide-cooling', 'How to keep your PS5 or Xbox cool during long GTA 6 sessions', 'How to use', 'gaming-hardware', 'handhelds', 'keep ps5 cool'],
        ['gta6-guide-accessibility', 'How to use accessibility settings on PS5 and Xbox: subtitles, text size and more', 'How to use', 'gaming-hardware', 'handhelds', 'ps5 accessibility settings'],
        ['gta6-guide-soundbar', 'How to connect a soundbar to your TV for better GTA 6 sound', 'Setup', 'gaming-hardware', 'audio', 'connect soundbar to tv'],
        ['gta6-guide-monitor', 'How to connect a gaming monitor to PS5 or Xbox for GTA 6', 'Setup', 'pc-hardware', 'monitors', 'connect monitor to ps5'],
        ['gta6-guide-updates', 'How to update your PS5 or Xbox and GTA 6 the right way', 'How to use', 'gaming-hardware', 'handhelds', 'update ps5 system software'],
        ['gta6-guide-gameshare', 'How to share games with family on PS5 and Xbox', 'How to use', 'gaming-hardware', 'handhelds', 'gameshare ps5'],
        ['gta6-guide-screenshots', 'How to take and share GTA 6 screenshots on PS5 and Xbox', 'How to use', 'gaming-hardware', 'handhelds', 'take screenshots ps5'],
    ];
    return array_map(function ($t) use ($x) { $t[] = $x; return $t; }, $g);
}

/** The next GTA 6 guide not written yet, or null. */
function rocGta6NextGuide(array $doneKeys, array $titles): ?array {
    foreach (rocGta6Guides() as $t) if (!in_array($t[0], $doneKeys, true) && !in_array(strtolower($t[1]), $titles, true)) return $t;
    return null;
}

/**
 * "More on GTA 6" box under every GTA 6 blog post, news story and guide (blog-render.php): the hub and the latest
 * GTA 6 posts of all three kinds, so readers (and Google) move between them.
 */
function rocGta6Box(?PDO $pdo, string $selfPath): string {
    $all = array_values(array_filter(rocGta6Posts($pdo, 30), function ($b) use ($selfPath) { return $b['url'] !== $selfPath; }));
    $pick = []; $n = ['News' => 0, 'Guide' => 0, 'Blog' => 0];
    foreach ($all as $b) if ($n[$b['kind']] < 3 && count($pick) < 7) { $pick[] = $b; $n[$b['kind']]++; }
    $h = '<h2>More on GTA 6</h2><ul><li><a href="/gta-6/">GTA 6 release date, price and PC status</a></li>';
    foreach ($pick as $b) $h .= '<li><a href="' . htmlspecialchars($b['url'], ENT_QUOTES) . '">' . htmlspecialchars($b['title'], ENT_QUOTES) . '</a> (' . $b['kind'] . ')</li>';
    return $h . '</ul>';
}
