"""Writes public/roc-theme.css: the night theme of runonconsole.com (html.roc-dark).

    python scripts/gen-roc-theme.py public/roc-theme.css

Add a Tailwind class to the lists below when a new light colour shows up on the site."""
import re, sys

D = 'html.roc-dark'
BG0, BG1, SURF, SURF2 = '#020617', '#0b1220', '#0f172a', '#152036'
LINE, LINE2 = '#1e293b', '#334155'
TXT_STRONG, TXT, TXT2, MUTED = '#f8fafc', '#e2e8f0', '#cbd5e1', '#94a3b8'
ACC, ACC2 = '#34d399', '#6ee7b7'
TINT, TINT2 = 'rgba(16,185,129,.10)', 'rgba(16,185,129,.18)'


def esc(cls):
    return re.sub(r'([:/\[\]#.])', r'\\\1', cls)


def sel(cls):
    """Tailwind class (with hover:/group-hover:/focus:/sm: prefix) -> CSS selector under html.roc-dark."""
    parts = cls.split(':')
    base = '.' + esc(cls)
    pre = parts[:-1]
    s = base
    if 'hover' in pre: s += ':hover'
    if 'focus' in pre: s += ':focus'
    if 'group-hover' in pre: s = '.group:hover ' + s
    return D + ' ' + s


rules = []
def rule(classes, decl):
    rules.append(',\n'.join(sel(c) for c in classes) + '{' + decl + '}')


def alpha(c, a):
    return c  # same colour; Tailwind's /NN variants just get the dark surface


# Surfaces
rule(['bg-white', 'bg-white/95', 'bg-white/90', 'bg-white/80', 'focus:bg-white', 'bg-[#FFFFFF]'], f'background-color:{SURF}!important')
rule(['bg-white/95'], 'background-color:rgba(15,23,42,.92)!important')
rule(['bg-[#F8FAFC]', 'bg-slate-50', 'bg-slate-50/80'], f'background-color:{BG0}!important')
rule(['bg-slate-100', 'bg-slate-100/90'], f'background-color:{BG1}!important')
rule(['bg-slate-200'], f'background-color:{LINE}!important')
rule(['hover:bg-slate-100', 'hover:bg-white', 'hover:bg-slate-200'], f'background-color:{SURF2}!important')
rule(['bg-emerald-50', 'bg-emerald-50/50', 'bg-emerald-50/70', 'bg-emerald-50/80', 'hover:bg-emerald-50', 'hover:bg-emerald-50/70', 'group-hover:bg-emerald-50'], f'background-color:{TINT}!important')
rule(['bg-emerald-100', 'bg-emerald-100/60', 'bg-emerald-100/70', 'bg-emerald-100/80', 'group-hover:bg-emerald-100', 'hover:bg-emerald-100/60'], f'background-color:{TINT2}!important')
rule(['bg-amber-50/70'], 'background-color:rgba(245,158,11,.10)!important')
rule(['bg-rose-50'], 'background-color:rgba(244,63,94,.10)!important')

# Text
rule(['text-[#0F172A]', 'text-slate-900', 'text-slate-950', 'hover:text-slate-900'], f'color:{TXT_STRONG}!important')
rule(['text-slate-800'], f'color:{TXT}!important')
rule(['text-slate-700'], f'color:{TXT2}!important')
rule(['text-slate-600', 'text-slate-500'], f'color:{MUTED}!important')
rule(['text-emerald-700', 'text-emerald-800', 'text-emerald-900', 'hover:text-emerald-700', 'hover:text-emerald-900',
      'group-hover:text-emerald-700', 'group-hover:text-emerald-800', 'group-hover:text-emerald-900'], f'color:{ACC2}!important')
rule(['text-emerald-600', 'hover:text-emerald-600', 'group-hover:text-emerald-600'], f'color:{ACC}!important')
rule(['text-amber-800'], 'color:#fcd34d!important')

# Borders
rule(['border-slate-100', 'border-slate-200', 'border-slate-200/80', 'border-slate-200/60', 'sm:border-slate-200'], f'border-color:{LINE}!important')
rule(['border-slate-300', 'hover:border-slate-300'], f'border-color:{LINE2}!important')
rule(['border-emerald-100', 'border-emerald-200', 'border-emerald-200/60', 'border-emerald-300', 'border-emerald-300/60', 'group-hover:border-emerald-300'], 'border-color:rgba(52,211,153,.28)!important')
rule(['border-amber-200'], 'border-color:rgba(245,158,11,.35)!important')
rule(['border-rose-200'], 'border-color:rgba(244,63,94,.35)!important')

base = f'''/* Run On Console - night theme. Turned on by html.roc-dark (roc-nav.js: the sun/moon button, saved in
   localStorage "roc-theme"). Written by scripts/gen-roc-theme.py; edit the generator, not this file. */
{D}{{color-scheme:dark}}
{D} body{{background:{BG0}!important;color:{TXT}}}
{D} ::selection{{background:rgba(52,211,153,.35)}}
{D} input:not([type=checkbox]):not([type=radio]):not([type=range]):not([type=color]),{D} select,{D} textarea{{background-color:{BG1};color:{TXT};border-color:{LINE2}}}
{D} input::placeholder,{D} textarea::placeholder{{color:#64748b}}
{D} .shadow-xs,{D} .shadow-sm,{D} .shadow,{D} .shadow-md,{D} .shadow-lg{{--tw-shadow-color:rgba(0,0,0,.45)}}
{D} hr{{border-color:{LINE}}}
'''

# Server-rendered pages (blog-render, game-render, news, guides, updates) with their own style blocks
custom = f'''
/* blogs, guides, news */
{D} .roc-article{{color:{TXT2}}}
{D} .roc-article h2,{D} .roc-article h3,{D} .roc-article h4,{D} .roc-article h5,{D} .roc-article strong{{color:{TXT_STRONG}}}
{D} .roc-article blockquote{{background:{TINT};color:{ACC2}}}
{D} .roc-article th,{D} .roc-article td{{border-color:{LINE}}}
{D} .roc-article th{{background:{SURF2};color:{TXT_STRONG}}}
{D} .roc-article hr{{border-top-color:{LINE}}}
{D} .roc-article a{{color:{ACC}}}
{D} .rn-t{{color:{TXT_STRONG}}}
{D} .rn-top,{D} a.rn-row,{D} a.rn-side-item{{border-color:{LINE}}}
{D} .rn-ad,{D} .rn-box{{background:{SURF};border-color:{LINE}}}
{D} .rn-live{{background:rgba(225,29,72,.15);color:#fda4af}}
{D} .rn-pg a,{D} .rn-pg span{{background:{SURF};border-color:{LINE2};color:{TXT2}}}
{D} .rn-pg .on{{background:#047857;border-color:#047857;color:#fff}}
{D} .gd-chip{{background:{SURF};border-color:{LINE2};color:{TXT2}}}
{D} .gd-chip.on{{background:#047857;border-color:#047857;color:#fff}}
/* /updates/ */
{D} .up-col{{background:{SURF};border-color:{LINE}}}
{D} .up-col h2,{D} .up-item b{{color:{TXT_STRONG}}}
{D} .up-item{{border-top-color:{LINE}}}
{D} .up-more a{{background:{TINT};color:{ACC2}}}
/* Can I run pages */
{D} .gp-card,{D} .gp-p,{D} .gp-faq details,{D} .gp-sim a,{D} .gp-search,{D} .gp-az a,{D} .gp-btn.s,{D} .gp-build li{{background:{SURF};border-color:{LINE}}}
{D} .gp-card h2,{D} .gp-parts h3,{D} .gp-build .t,{D} .gp-p a.t,{D} .gp-faq summary,{D} .gp-sim strong,{D} .gp-search,{D} .gp-list a,{D} .gp-table thead th{{color:{TXT_STRONG}}}
{D} .gp-table th,{D} .gp-table td{{border-top-color:{LINE}}}
{D} .gp-table thead th{{background:{SURF2}}}
{D} .gp-table tbody th,{D} .gp-check label,{D} .gp-parts ul,{D} .gp-faq .a,{D} .gp-p .a .m,{D} .gp-btn.s,{D} .gp-az a{{color:{TXT2}}}
{D} .gp-table td{{color:{TXT}}}
{D} .gp-check select{{background:{BG1};border-color:{LINE2};color:{TXT_STRONG}}}
{D} .gp-check select:focus{{background:{SURF}}}
{D} .gp-res{{background:{SURF2};color:{TXT2}}}
{D} .gp-res.ok{{background:rgba(16,185,129,.14);color:{ACC2}}}
{D} .gp-res.mid{{background:rgba(234,179,8,.14);color:#fde047}}
{D} .gp-res.low{{background:rgba(249,115,22,.14);color:#fdba74}}
{D} .gp-res.no{{background:rgba(239,68,68,.14);color:#fca5a5}}
{D} .gp-parts>div{{background:{BG1};border-color:{LINE}}}
{D} .gp-build{{background:rgba(16,185,129,.06);border-color:rgba(52,211,153,.3)}}
{D} .gp-p .a .m{{border-color:{LINE2}}}
{D} .gp-faq details[open],{D} .gp-sim a:hover{{background:{TINT};border-color:#10b981}}
{D} .gp-tag.w{{background:{SURF};color:{ACC2};border-color:{SURF}}}
/* React app components (src/index.css) */
{D} .game-card{{background:{SURF};border-color:{LINE}}}
{D} .mega-dropdown-mirror{{background:rgba(15,23,42,.97);box-shadow:0 25px 70px -15px rgba(0,0,0,.7),0 0 30px rgba(16,185,129,.18)}}
{D} .dropdown-tile{{background:rgba(30,41,59,.6);border-color:{LINE}}}
{D} .dropdown-tile:hover{{background:rgba(16,185,129,.14);border-color:rgba(52,211,153,.6)}}
{D} .bg-white .text-emerald-950:not([class*="bg-"]),{D} .game-card .text-emerald-950:not([class*="bg-"]){{color:#d1fae5!important}}
{D} .bg-white .text-emerald-950:not([class*="bg-"]),{D} .bg-emerald-50 .text-emerald-950:not([class*="bg-"]),{D} [class*="bg-emerald-50"] .text-emerald-950:not([class*="bg-"]){{color:#d1fae5!important}}
{D} .gp-build h3{{color:{TXT_STRONG}}}
{D} .rn-head h1,{D} .rn-h2{{color:{TXT_STRONG}}}
{D} [data-roc-wl]{{background:{SURF}!important;border-color:{LINE2}!important;color:{TXT2}!important}}
{D} [data-roc-wl][aria-pressed="true"]{{background:rgba(244,63,94,.15)!important;border-color:rgba(244,63,94,.5)!important;color:#fda4af!important}}
/* Product photos keep their white background (they are shot on white) */
{D} img[src*="media-amazon.com"],{D} img[src*="/images/products/photos/"]{{background:#fff}}
'''

toggle = f'''
/* The sun/moon button (roc-nav.js puts it in the header) */
.roc-theme-btn{{position:relative;width:32px;height:32px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;color:#0f172a;display:inline-grid;place-items:center;cursor:pointer;flex-shrink:0;overflow:hidden;transition:background .3s,border-color .3s,color .3s,transform .2s}}
.roc-theme-btn:hover{{transform:translateY(-1px);border-color:#10b981}}
.roc-theme-btn svg{{position:absolute;width:16px;height:16px;transition:transform .45s cubic-bezier(.2,.8,.2,1),opacity .3s}}
.roc-theme-btn .moon{{transform:translateY(24px) rotate(-60deg);opacity:0}}
{D} .roc-theme-btn{{background:{SURF};border-color:{LINE2};color:#fde68a}}
{D} .roc-theme-btn .sun{{transform:translateY(-24px) rotate(60deg);opacity:0}}
{D} .roc-theme-btn .moon{{transform:none;opacity:1}}
html.roc-theme-anim,html.roc-theme-anim *{{transition:background-color .35s ease,border-color .35s ease,color .25s ease!important}}
@media (prefers-reduced-motion:reduce){{.roc-theme-btn svg{{transition:none}}html.roc-theme-anim,html.roc-theme-anim *{{transition:none!important}}}}
'''

out = base + '\n'.join(rules) + '\n' + custom + toggle
open(sys.argv[1], 'w', encoding='utf-8', newline='\n').write(out)
print('wrote', sys.argv[1], len(out), 'bytes,', len(rules), 'utility rules')
