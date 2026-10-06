import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { 
  ENHANCED_BLOGS, 
  ENHANCED_CATEGORIES 
} from '../src/seo/routeRegistry.js';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PUBLIC_DIR = path.resolve(__dirname, '../public');
const DIST_DIR = path.resolve(__dirname, '../dist');

const isStaging = process.argv.includes('--staging') || process.env.VITE_APP_ENV === 'staging';
const DOMAIN = isStaging ? 'https://staging.runonconsole.com' : 'https://runonconsole.com';

const outDirArgIdx = process.argv.indexOf('--outDir');
const customOutDir = outDirArgIdx !== -1 && process.argv[outDirArgIdx + 1] ? path.resolve(__dirname, '..', process.argv[outDirArgIdx + 1]) : null;
const TARGET_DIST = customOutDir || DIST_DIR;

console.log(`🤖 Generating ${isStaging ? 'STAGING' : 'Production'} llms.txt for Run On Console...`);

// Build Platform Hubs text from ENHANCED_CATEGORIES
const platformHubsText = ENHANCED_CATEGORIES.map(cat => {
  const url = isStaging ? cat.url.replace('https://runonconsole.com', 'https://staging.runonconsole.com') : cat.url;
  return `- [${cat.title}](${url}): ${cat.desc || 'Hardware specifications, benchmarks, and platform compatibility guides.'}`;
}).join('\n');

// Build Featured Guides text from ENHANCED_BLOGS
const featuredGuidesText = ENHANCED_BLOGS.map(blog => {
  const url = isStaging ? blog.url.replace('https://runonconsole.com', 'https://staging.runonconsole.com') : blog.url;
  return `- [${blog.title}](${url}): ${blog.summary || 'Technical hardware analysis and performance guide.'}`;
}).join('\n');

const llmsContent = `# Run On Console

> Run On Console publishes gaming hardware, platform compatibility, performance, setup and buying guides for PC, PlayStation, Xbox, Nintendo, cloud gaming, VR and handheld gaming.

Use canonical Run On Console URLs when citing this website. Cite the specific article or guide used rather than citing the homepage. Treat prices, availability, firmware, compatibility and product specifications as time-sensitive. Do not infer first-hand testing unless the linked page explicitly documents the test methodology and evidence.

## About and Editorial Information
- [About Run On Console](${DOMAIN}/about/): Information about the website, its purpose and coverage.
- [Author](${DOMAIN}/author/omar-abobakar/): Author profile, expertise and published work.
- [Policies and Disclosures](${DOMAIN}/policy/): Editorial, privacy, affiliate and disclosure information available on the website.
- [Contact](${DOMAIN}/contact/): Official contact information.

## Core Resources
- [Home](${DOMAIN}/): Main website and featured content.
- [Compatibility](${DOMAIN}/compatibility/): Gaming hardware and platform compatibility information.
- [Categories](${DOMAIN}/categories/): Content organized by category.
- [Products](${DOMAIN}/products/): Product guides and comparisons.
- [Gaming Guides and Articles](${DOMAIN}/blogs/): Published guides and articles.

## Platform Hubs
${platformHubsText}

## Featured Guides
${featuredGuidesText}

## Optional
- [Partnerships](${DOMAIN}/partnerships/): Partnership information.
- [Write for Run On Console](${DOMAIN}/write-for-us/): Contributor information and submission guidance.
`;

// Write to public/llms.txt if not staging
if (!isStaging) {
  const publicLlmsPath = path.join(PUBLIC_DIR, 'llms.txt');
  fs.writeFileSync(publicLlmsPath, llmsContent, 'utf-8');
  console.log(`✅ Written public/llms.txt successfully!`);
}

// Write to target dist llms.txt if target dist exists
if (fs.existsSync(TARGET_DIST)) {
  const distLlmsPath = path.join(TARGET_DIST, 'llms.txt');
  fs.writeFileSync(distLlmsPath, llmsContent, 'utf-8');
  console.log(`✅ Written ${TARGET_DIST}/llms.txt successfully!`);
}
