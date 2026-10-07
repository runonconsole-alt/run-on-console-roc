import React, { useEffect, useState } from 'react';
import { ArrowRight, Clock, Flame, BookOpen } from 'lucide-react';

/**
 * Home page: the newest published blog posts, straight from the CMS (/blogs/?json=1).
 * Left: the four newest as cards. Right: the next ones as a list. Both boxes are the
 * same height. A new post shows up here as soon as it is published; with no posts
 * the section is not shown at all.
 */
const fmtDate = (d) => {
  if (!d) return '';
  const t = new Date(`${d}T00:00:00Z`);
  return Number.isNaN(t.getTime()) ? '' : t.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
};

export const LatestBlogsSection = () => {
  const [posts, setPosts] = useState(null);

  useEffect(() => {
    let alive = true;
    fetch('/blogs/?json=1', { cache: 'no-cache' })
      .then((r) => (r.ok ? r.json() : []))
      .then((list) => { if (alive) setPosts(Array.isArray(list) ? list : []); })
      .catch(() => { if (alive) setPosts([]); });
    return () => { alive = false; };
  }, []);

  if (!posts || posts.length === 0) return null;

  const featured = posts.slice(0, 4);
  const more = posts.slice(4, 9);

  return (
    <section className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch">
      <div className={more.length ? 'lg:col-span-8' : 'lg:col-span-12'}>
        <div className="h-full bg-white border-2 border-emerald-500/20 rounded-3xl p-6 shadow-sm space-y-5">
          <div className="flex items-center justify-between gap-3 pb-3 border-b border-emerald-100">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-emerald-800 text-emerald-300 flex items-center justify-center">
                <Flame className="w-4 h-4 text-emerald-400" />
              </div>
              <div>
                <h2 className="font-display font-extrabold text-base sm:text-lg text-emerald-950 uppercase tracking-wider">Latest articles</h2>
                <p className="text-[11px] text-emerald-700 font-semibold">Guides, reviews and news from the Run On Console desk</p>
              </div>
            </div>
            <a href="/blogs/" className="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-900 no-underline">
              All articles <ArrowRight className="w-3.5 h-3.5" />
            </a>
          </div>

          <div className={`grid grid-cols-1 sm:grid-cols-2 ${more.length ? 'xl:grid-cols-4' : 'lg:grid-cols-4'} gap-4`}>
            {featured.map((p) => (
              <a key={p.u} href={p.u} className="group no-underline flex flex-col rounded-2xl border border-slate-200 hover:border-emerald-400 overflow-hidden bg-white transition-colors">
                <div className="relative aspect-[4/3] bg-slate-100 overflow-hidden">
                  {p.i ? (
                    <img src={p.i} alt={p.a || p.t} loading="lazy" className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-emerald-900 to-slate-900">
                      <BookOpen className="w-8 h-8 text-emerald-400/60" />
                    </div>
                  )}
                  {p.c && (
                    <span className="absolute left-2 bottom-2 bg-emerald-600 text-white text-[10px] font-extrabold uppercase tracking-wide px-2 py-0.5 rounded-md">{p.c}</span>
                  )}
                </div>
                <div className="p-3 flex flex-col flex-1 gap-2">
                  <h3 className="font-display font-bold text-sm text-slate-900 group-hover:text-emerald-700 leading-snug line-clamp-3">{p.t}</h3>
                  <div className="mt-auto flex items-center justify-between text-[11px] text-slate-500">
                    <span className="inline-flex items-center gap-1"><Clock className="w-3 h-3" />{fmtDate(p.d)}</span>
                    <span className="font-bold text-emerald-700">Read →</span>
                  </div>
                </div>
              </a>
            ))}
          </div>
        </div>
      </div>

      {more.length > 0 && (
        <div className="lg:col-span-4">
          <div className="h-full bg-white border-2 border-emerald-500/20 rounded-3xl p-6 shadow-sm flex flex-col">
            <div className="flex items-center justify-between pb-3 mb-4 border-b border-emerald-100">
              <h2 className="font-display font-extrabold text-sm sm:text-base text-emerald-950 uppercase tracking-wider">More articles</h2>
              <a href="/blogs/" className="text-[11px] font-bold text-emerald-700 no-underline">See all</a>
            </div>
            <div className="flex flex-col gap-3 flex-1">
              {more.map((p, i) => (
                <a key={p.u} href={p.u} className="group no-underline p-3 bg-slate-50 hover:bg-emerald-50 border border-slate-200 hover:border-emerald-400 rounded-2xl flex items-center gap-3 transition-colors">
                  <div className="w-8 h-8 rounded-xl bg-emerald-700 text-white font-display font-extrabold text-xs flex items-center justify-center shrink-0">
                    {String(i + 1).padStart(2, '0')}
                  </div>
                  <div className="min-w-0 flex-1">
                    <h3 className="font-display font-bold text-xs text-slate-900 group-hover:text-emerald-700 line-clamp-2 leading-snug">{p.t}</h3>
                    <span className="text-[10px] text-slate-500 block mt-0.5">{[p.c, fmtDate(p.d)].filter(Boolean).join(' • ')}</span>
                  </div>
                  <ArrowRight className="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 shrink-0" />
                </a>
              ))}
            </div>
          </div>
        </div>
      )}
    </section>
  );
};
