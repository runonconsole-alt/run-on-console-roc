import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const AVATAR_DIR = path.resolve(__dirname, '../public/images/avatars');

if (!fs.existsSync(AVATAR_DIR)) {
  fs.mkdirSync(AVATAR_DIR, { recursive: true });
}

// 20 Distinct Vector SVG Artwork Generators for Esports Avatars
const AVATARS = [
  {
    id: 'avatar_01',
    name: 'Cyber Ninja',
    c1: '#10B981', c2: '#064E3B', bg1: '#ECFDF5', bg2: '#D1FAE5',
    svg: `<path d="M44,40 L64,18 L84,40 L96,80 L64,110 L32,80 Z" fill="#064E3B" stroke="#10B981" stroke-width="4"/>
    <path d="M40,54 L88,54 L80,68 L64,72 L48,68 Z" fill="#10B981"/>
    <line x1="32" y1="54" x2="96" y2="54" stroke="#FFFFFF" stroke-width="3"/>
    <path d="M24,24 L104,104 M104,24 L24,104" stroke="#10B981" stroke-width="3" stroke-dasharray="8 4" opacity="0.4"/>`
  },
  {
    id: 'avatar_02',
    name: 'Shadow Samurai',
    c1: '#F43F5E', c2: '#881337', bg1: '#FFF1F2', bg2: '#FFE4E6',
    svg: `<path d="M64,14 L88,38 L84,80 L64,112 L44,80 L40,38 Z" fill="#881337" stroke="#F43F5E" stroke-width="4"/>
    <path d="M30,22 C45,35 55,20 64,32 C73,20 83,35 98,22" fill="none" stroke="#F59E0B" stroke-width="5" stroke-linecap="round"/>
    <rect x="48" y="52" width="32" height="12" rx="4" fill="#F43F5E"/>
    <line x1="20" y1="100" x2="108" y2="20" stroke="#F59E0B" stroke-width="4"/>`
  },
  {
    id: 'avatar_03',
    name: 'Cyber Soldier',
    c1: '#06B6D4', c2: '#164E63', bg1: '#ECFEFF', bg2: '#CFFAFE',
    svg: `<path d="M36,36 C36,20 92,20 92,36 L96,80 L64,110 L32,80 Z" fill="#164E63" stroke="#06B6D4" stroke-width="4"/>
    <rect x="42" y="44" width="44" height="18" rx="6" fill="#06B6D4"/>
    <circle cx="52" cy="53" r="4" fill="#FFFFFF"/>
    <circle cx="76" cy="53" r="4" fill="#FFFFFF"/>
    <path d="M40,78 L64,88 L88,78" fill="none" stroke="#06B6D4" stroke-width="4" stroke-linecap="round"/>`
  },
  {
    id: 'avatar_04',
    name: 'Frost Wizard',
    c1: '#3B82F6', c2: '#1E3A8A', bg1: '#EFF6FF', bg2: '#DBEAFE',
    svg: `<polygon points="64,10 92,54 36,54" fill="#1E3A8A" stroke="#3B82F6" stroke-width="4"/>
    <path d="M30,54 C30,54 64,44 98,54 L90,96 L64,112 L38,96 Z" fill="#1E3A8A" stroke="#3B82F6" stroke-width="3"/>
    <circle cx="64" cy="72" r="10" fill="#60A5FA" filter="drop-shadow(0 0 6px #60A5FA)"/>
    <polygon points="64,64 70,72 64,80 58,72" fill="#FFFFFF"/>`
  },
  {
    id: 'avatar_05',
    name: 'Ember Dragon',
    c1: '#F59E0B', c2: '#78350F', bg1: '#FFFBEB', bg2: '#FEF3C7',
    svg: `<path d="M64,14 L94,40 L84,90 L64,114 L44,90 L34,40 Z" fill="#78350F" stroke="#F59E0B" stroke-width="4"/>
    <path d="M24,20 Q44,40 50,20 Q56,40 64,14 Q72,40 78,20 Q84,40 104,20 L84,60 L64,74 L44,60 Z" fill="#F59E0B"/>
    <polygon points="46,58 56,66 42,70" fill="#EF4444"/>
    <polygon points="82,58 72,66 86,70" fill="#EF4444"/>`
  },
  {
    id: 'avatar_06',
    name: 'Alpha Wolf',
    c1: '#A855F7', c2: '#581C87', bg1: '#FAF5FF', bg2: '#F3E8FF',
    svg: `<polygon points="28,24 48,54 34,70" fill="#581C87" stroke="#A855F7" stroke-width="3"/>
    <polygon points="100,24 80,54 94,70" fill="#581C87" stroke="#A855F7" stroke-width="3"/>
    <path d="M38,44 L64,24 L90,44 L86,88 L64,114 L42,88 Z" fill="#581C87" stroke="#A855F7" stroke-width="4"/>
    <polygon points="46,58 58,62 48,68" fill="#C084FC"/>
    <polygon points="82,58 70,62 80,68" fill="#C084FC"/>
    <polygon points="56,84 64,96 72,84" fill="#A855F7"/>`
  },
  {
    id: 'avatar_07',
    name: 'Shadow Panther',
    c1: '#EAB308', c2: '#713F12', bg1: '#FEFCE8', bg2: '#FEF08A',
    svg: `<path d="M32,32 L64,14 L96,32 L92,84 L64,112 L36,84 Z" fill="#1E293B" stroke="#EAB308" stroke-width="4"/>
    <circle cx="48" cy="54" r="6" fill="#EAB308"/>
    <circle cx="80" cy="54" r="6" fill="#EAB308"/>
    <polygon points="64,70 54,82 74,82" fill="#EAB308"/>
    <path d="M44,88 Q64,104 84,88" fill="none" stroke="#EAB308" stroke-width="4"/>`
  },
  {
    id: 'avatar_08',
    name: 'Golden Eagle',
    c1: '#F59E0B', c2: '#78350F', bg1: '#FFFBEB', bg2: '#FDE68A',
    svg: `<path d="M64,12 L96,44 L80,94 L64,114 L48,94 L32,44 Z" fill="#78350F" stroke="#F59E0B" stroke-width="4"/>
    <path d="M64,30 L90,56 L64,100 L38,56 Z" fill="#F59E0B"/>
    <polygon points="64,56 84,68 64,88 44,68" fill="#FFFFFF"/>
    <polygon points="64,68 74,78 64,88" fill="#D97706"/>`
  },
  {
    id: 'avatar_09',
    name: 'Mecha Robot',
    c1: '#14B8A6', c2: '#134E4A', bg1: '#F0FDFA', bg2: '#CCFBF1',
    svg: `<rect x="36" y="28" width="56" height="64" rx="10" fill="#134E4A" stroke="#14B8A6" stroke-width="4"/>
    <line x1="64" y1="28" x2="64" y2="10" stroke="#14B8A6" stroke-width="4"/>
    <circle cx="64" cy="10" r="5" fill="#2DD4BF"/>
    <rect x="44" y="44" width="16" height="12" rx="3" fill="#2DD4BF"/>
    <rect x="68" y="44" width="16" height="12" rx="3" fill="#2DD4BF"/>
    <path d="M48,74 H80 V80 H48 Z" fill="#14B8A6"/>`
  },
  {
    id: 'avatar_10',
    name: 'Skull Gamer',
    c1: '#EC4899', c2: '#831843', bg1: '#FDF2F8', bg2: '#FCE7F3',
    svg: `<path d="M40,32 C40,18 88,18 88,32 L92,72 L78,96 L50,96 L36,72 Z" fill="#831843" stroke="#EC4899" stroke-width="4"/>
    <!-- Gaming Headset -->
    <path d="M26,44 C26,18 102,18 102,44" fill="none" stroke="#F472B6" stroke-width="6" stroke-linecap="round"/>
    <rect x="22" y="42" width="12" height="26" rx="4" fill="#EC4899"/>
    <rect x="94" y="42" width="12" height="26" rx="4" fill="#EC4899"/>
    <!-- Skull Eyes -->
    <circle cx="50" cy="54" r="8" fill="#0F172A"/>
    <circle cx="78" cy="54" r="8" fill="#0F172A"/>
    <polygon points="64,66 58,76 70,76" fill="#EC4899"/>`
  },
  {
    id: 'avatar_11',
    name: 'Thunder Bulldog',
    c1: '#38BDF8', c2: '#0C4A6E', bg1: '#F0F9FF', bg2: '#E0F2FE',
    svg: `<path d="M32,32 L96,32 L92,86 L64,112 L36,86 Z" fill="#0C4A6E" stroke="#38BDF8" stroke-width="4"/>
    <circle cx="48" cy="48" r="7" fill="#38BDF8"/>
    <circle cx="80" cy="48" r="7" fill="#38BDF8"/>
    <path d="M40,68 L88,68 L76,92 L52,92 Z" fill="#38BDF8"/>
    <polygon points="68,20 54,54 66,54 58,84 78,44 64,44" fill="#F59E0B"/>`
  },
  {
    id: 'avatar_12',
    name: 'Crown Lion',
    c1: '#FBBF24', c2: '#78350F', bg1: '#FFFBEB', bg2: '#FEF3C7',
    svg: `<path d="M32,44 L64,24 L96,44 L88,96 L64,114 L40,96 Z" fill="#78350F" stroke="#FBBF24" stroke-width="4"/>
    <!-- King Crown -->
    <polygon points="34,36 44,18 64,28 84,18 94,36" fill="#FBBF24" stroke="#D97706" stroke-width="2"/>
    <circle cx="44" cy="18" r="3" fill="#EF4444"/>
    <circle cx="64" cy="28" r="3" fill="#3B82F6"/>
    <circle cx="84" cy="18" r="3" fill="#EF4444"/>
    <!-- Lion Eyes & Mane -->
    <polygon points="46,58 56,64 44,70" fill="#FBBF24"/>
    <polygon points="82,58 72,64 84,70" fill="#FBBF24"/>
    <polygon points="64,74 54,88 74,88" fill="#FBBF24"/>`
  },
  {
    id: 'avatar_13',
    name: 'Cyber Skull',
    c1: '#EF4444', c2: '#7F1D1D', bg1: '#FEF2F2', bg2: '#FEE2E2',
    svg: `<path d="M40,24 C40,12 88,12 88,24 L94,68 L78,98 L50,98 L34,68 Z" fill="#7F1D1D" stroke="#EF4444" stroke-width="4"/>
    <!-- Gas Mask Filters -->
    <circle cx="34" cy="74" r="10" fill="#EF4444"/>
    <circle cx="94" cy="74" r="10" fill="#EF4444"/>
    <circle cx="50" cy="46" r="8" fill="#0F172A"/>
    <circle cx="78" cy="46" r="8" fill="#0F172A"/>
    <polygon points="64,58 58,68 70,68" fill="#EF4444"/>`
  },
  {
    id: 'avatar_14',
    name: 'Storm Trooper',
    c1: '#6366F1', c2: '#312E81', bg1: '#EEF2FF', bg2: '#E0E7FF',
    svg: `<path d="M36,24 L92,24 L98,64 L84,104 L44,104 L30,64 Z" fill="#312E81" stroke="#6366F1" stroke-width="4"/>
    <polygon points="42,44 86,44 80,58 48,58" fill="#6366F1"/>
    <line x1="64" y1="44" x2="64" y2="76" stroke="#4338CA" stroke-width="4"/>
    <rect x="46" y="74" width="36" height="12" rx="3" fill="#6366F1"/>`
  },
  {
    id: 'avatar_15',
    name: 'Phoenix Knight',
    c1: '#FB923C', c2: '#7C2D12', bg1: '#FFF7ED', bg2: '#FFEDD5',
    svg: `<path d="M64,10 L94,40 L84,94 L64,114 L44,94 L34,40 Z" fill="#7C2D12" stroke="#FB923C" stroke-width="4"/>
    <!-- Phoenix Crest Wings -->
    <path d="M20,30 Q44,10 64,30 Q84,10 108,30 L90,56 L64,46 L38,56 Z" fill="#FB923C"/>
    <rect x="46" y="58" width="36" height="8" rx="2" fill="#FFFFFF"/>
    <line x1="64" y1="58" x2="64" y2="92" stroke="#FB923C" stroke-width="3"/>`
  },
  {
    id: 'avatar_16',
    name: 'Phantom Assassin',
    c1: '#C084FC', c2: '#4C1D95', bg1: '#F3E8FF', bg2: '#E9D5FF',
    svg: `<path d="M64,14 L94,44 L82,88 L64,110 L46,88 L34,44 Z" fill="#4C1D95" stroke="#C084FC" stroke-width="4"/>
    <polygon points="40,48 88,48 80,62 64,68 48,62" fill="#C084FC"/>
    <line x1="32" y1="48" x2="96" y2="48" stroke="#FFFFFF" stroke-width="2"/>
    <path d="M26,30 L102,102 M102,30 L26,102" stroke="#C084FC" stroke-width="2.5" opacity="0.3"/>`
  },
  {
    id: 'avatar_17',
    name: 'Venom Cobra',
    c1: '#34D399', c2: '#064E3B', bg1: '#ECFDF5', bg2: '#A7F3D0',
    svg: `<path d="M30,36 C30,16 98,16 98,36 L92,84 L64,112 L36,84 Z" fill="#064E3B" stroke="#34D399" stroke-width="4"/>
    <!-- Cobra Hood Wings -->
    <path d="M22,44 C36,30 46,60 64,60 C82,60 92,30 106,44 L92,80 L64,96 L36,80 Z" fill="#047857"/>
    <circle cx="48" cy="46" r="5" fill="#34D399"/>
    <circle cx="80" cy="46" r="5" fill="#34D399"/>
    <polygon points="56,66 60,78 54,78" fill="#FFFFFF"/>
    <polygon points="72,66 74,78 68,78" fill="#FFFFFF"/>`
  },
  {
    id: 'avatar_18',
    name: 'Cosmic Sorcerer',
    c1: '#D8B4FE', c2: '#581C87', bg1: '#FAF5FF', bg2: '#E9D5FF',
    svg: `<path d="M64,12 L94,44 L80,92 L64,112 L48,92 L34,44 Z" fill="#581C87" stroke="#D8B4FE" stroke-width="4"/>
    <!-- Galaxy Orb -->
    <circle cx="64" cy="62" r="16" fill="#C084FC" filter="drop-shadow(0 0 8px #C084FC)"/>
    <circle cx="64" cy="62" r="8" fill="#FFFFFF"/>
    <polygon points="64,24 68,36 80,40 68,44 64,56 60,44 48,40 60,36" fill="#D8B4FE"/>`
  },
  {
    id: 'avatar_19',
    name: 'Rogue Goblin',
    c1: '#A3E635', c2: '#365314', bg1: '#F7FEE7', bg2: '#D9F99D',
    svg: `<polygon points="18,30 42,48 30,66" fill="#365314" stroke="#A3E635" stroke-width="3"/>
    <polygon points="110,30 86,48 98,66" fill="#365314" stroke="#A3E635" stroke-width="3"/>
    <path d="M38,36 L90,36 L86,84 L64,110 L42,84 Z" fill="#365314" stroke="#A3E635" stroke-width="4"/>
    <rect x="42" y="46" width="44" height="14" rx="4" fill="#A3E635"/>
    <polygon points="56,76 64,88 72,76" fill="#A3E635"/>`
  },
  {
    id: 'avatar_20',
    name: 'Titan Warrior',
    c1: '#E7E5E4', c2: '#292524', bg1: '#FAFAF9', bg2: '#E7E5E4',
    svg: `<path d="M64,10 L96,40 L84,94 L64,114 L44,94 L32,40 Z" fill="#292524" stroke="#E7E5E4" stroke-width="4"/>
    <path d="M26,20 L44,44 M102,20 L84,44" stroke="#E7E5E4" stroke-width="5" stroke-linecap="round"/>
    <rect x="44" y="52" width="40" height="10" rx="2" fill="#E7E5E4"/>
    <line x1="64" y1="52" x2="64" y2="88" stroke="#E7E5E4" stroke-width="4"/>`
  }
];

for (const a of AVATARS) {
  const fullSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">
  <defs>
    <linearGradient id="bgGrad_${a.id}" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="${a.bg1}"/>
      <stop offset="100%" stop-color="${a.bg2}"/>
    </linearGradient>
  </defs>
  <rect width="128" height="128" rx="28" fill="url(#bgGrad_${a.id})" stroke="${a.c1}" stroke-width="4"/>
  ${a.svg}
</svg>`;

  const filePath = path.join(AVATAR_DIR, `${a.id}.svg`);
  fs.writeFileSync(filePath, fullSvg, 'utf-8');
}

console.log('✅ Re-generated 20 DISTINCT, UNIQUE Vector SVG Esports Avatars in public/images/avatars/');
