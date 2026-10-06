import React, { useEffect } from 'react';
import { Gamepad2, Sparkles, Check, PartyPopper, ArrowRight, Heart } from 'lucide-react';
import { BouncyText } from './BouncyText';
import confetti from 'canvas-confetti';

/**
 * SubmissionSuccessCard - Multi-burst party popper fireworks explosion with
 * customized respective form punchlines, glowing Run On Console logo, and
 * "Enjoy your life and have fun, we will reply soon" celebration banner!
 */
export const SubmissionSuccessCard = ({
  formType = "contact", // 'contact' | 'write-for-us' | 'partnership' | 'suggestion' | 'comment'
  userName = "Gamer",
  referenceId = "",
  onReset
}) => {
  // Trigger multi-stage realistic party popper fireworks blast on mount
  useEffect(() => {
    try {
      // Stage 1: Dual Cannon Blast
      confetti({
        particleCount: 70,
        spread: 80,
        origin: { x: 0.2, y: 0.6 },
        colors: ['#10B981', '#34D399', '#06B6D4', '#F59E0B', '#EC4899', '#FFFFFF'],
        ticks: 140,
        gravity: 0.7,
        scalar: 1.15
      });
      confetti({
        particleCount: 70,
        spread: 80,
        origin: { x: 0.8, y: 0.6 },
        colors: ['#10B981', '#34D399', '#06B6D4', '#F59E0B', '#EC4899', '#FFFFFF'],
        ticks: 140,
        gravity: 0.7,
        scalar: 1.15
      });

      // Stage 2: Center Starburst Firework Delay
      const timer = setTimeout(() => {
        confetti({
          particleCount: 90,
          spread: 100,
          origin: { x: 0.5, y: 0.45 },
          colors: ['#34D399', '#10B981', '#6EE7B7', '#FCD34D', '#FFFFFF'],
          ticks: 160,
          gravity: 0.8,
          scalar: 1.2,
          shapes: ['circle', 'square']
        });
      }, 250);

      return () => clearTimeout(timer);
    } catch (err) {}
  }, []);

  // Form-Specific Hooks & Punchlines
  const formConfigs = {
    contact: {
      badge: "MESSAGE RECEIVED & LOGGED",
      hook: "Your transmission has safely landed at the Run On Console Lab. Our benchmarking columnists are already warming up the oscilloscopes!",
      metaLabel: "Inquiry Channel",
      metaValue: "Editorial Lab Desk (24-48h SLA)"
    },
    'write-for-us': {
      badge: "GUEST PITCH QUEUED FOR REVIEW",
      hook: "Your high-tier editorial outline is queued for priority review. Let's forge high-authority Do-Follow backlinks together!",
      metaLabel: "Review Desk",
      metaValue: "Senior Content Editor (24h SLA)"
    },
    partnership: {
      badge: "COMMERCIAL PROPOSAL REGISTERED",
      hook: "Your commercial campaign proposal is on our desk. Get ready to amplify your gaming brand across 500,000+ active hardware buyers!",
      metaLabel: "Partnership Desk",
      metaValue: "Commercial Advertising Director"
    },
    suggestion: {
      badge: "REVIEW SUGGESTION LOCKED IN",
      hook: "Your hardware test suggestion is locked into our test queue. Time to push maximum FPS together and test real latency limits!",
      metaLabel: "Queue Placement",
      metaValue: "Hardware Lab Queue (Pending Admin)"
    },
    comment: {
      badge: "DISCUSSION POST DISPATCHED",
      hook: "Your insights have been added to the reader queue. Keep the battlestation banter rolling!",
      metaLabel: "Discussion Status",
      metaValue: "Community Moderation Desk"
    }
  };

  const config = formConfigs[formType] || formConfigs.contact;

  return (
    <div className="bg-gradient-to-b from-emerald-950 via-slate-900 to-emerald-950 border-2 border-emerald-400 text-white p-6 sm:p-10 rounded-3xl text-center space-y-6 shadow-2xl relative overflow-hidden animate-page-in">
      
      {/* Decorative background glows */}
      <div className="absolute -top-20 -left-20 w-60 h-60 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
      <div className="absolute -bottom-20 -right-20 w-60 h-60 bg-teal-500/20 rounded-full blur-3xl pointer-events-none"></div>

      {/* Top Party Popper Celebration Icon */}
      <div className="relative z-10 flex justify-center items-center gap-3">
        <div className="w-16 h-16 rounded-3xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white shadow-xl shadow-emerald-500/40 border-2 border-emerald-300 animate-bounce">
          <PartyPopper className="w-8 h-8" />
        </div>
      </div>

      {/* Main Thank You & Life Celebration Header */}
      <div className="relative z-10 space-y-2">
        <div className="inline-flex items-center gap-2 bg-emerald-800/90 text-emerald-200 text-xs font-display font-extrabold px-4 py-1 rounded-full uppercase tracking-wider border border-emerald-400/40 shadow-sm">
          <Sparkles className="w-3.5 h-3.5 text-emerald-300" />
          <span>{config.badge}</span>
        </div>

        <h3 className="font-display font-extrabold text-2xl sm:text-4xl text-white">
          <BouncyText text="Thank You So Much!" />
        </h3>

        <p className="text-sm sm:text-base text-emerald-300 font-bold max-w-xl mx-auto flex items-center justify-center gap-2">
          <span>Enjoy your life and have fun! We will reply to you soon.</span>
          <Heart className="w-4 h-4 text-emerald-400 fill-emerald-400 inline" />
        </p>
      </div>

      {/* Form-Specific Respective Punchline / Hook Box */}
      <div className="relative z-10 bg-slate-900/90 border border-emerald-500/40 p-5 sm:p-6 rounded-2xl max-w-xl mx-auto shadow-inner text-left space-y-3">
        <div className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wider text-emerald-400">
          <Sparkles className="w-3.5 h-3.5 animate-pulse" />
          <span>MISSION DISPATCH HOOK</span>
        </div>

        <p className="text-xs sm:text-sm text-emerald-100/90 font-medium leading-relaxed">
          "{config.hook}"
        </p>

        <div className="pt-2 border-t border-slate-800 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-300">
          <div>
            <span className="text-slate-400">Sender:</span> <strong className="text-white ml-1">{userName}</strong>
          </div>
          {referenceId && (
            <div>
              <span className="text-slate-400">Reference:</span> <span className="font-mono text-emerald-400 font-bold ml-1">{referenceId}</span>
            </div>
          )}
          <div>
            <span className="text-slate-400">{config.metaLabel}:</span> <strong className="text-emerald-300 ml-1">{config.metaValue}</strong>
          </div>
        </div>
      </div>

      {/* Run On Console Brand Logo & Punchline Watermark */}
      <div className="relative z-10 pt-2 flex flex-col items-center justify-center gap-2">
        <div className="flex items-center gap-2.5 bg-emerald-950/80 border border-emerald-500/30 px-4 py-2 rounded-2xl">
          <div className="w-7 h-7 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white shadow-sm">
            <Gamepad2 className="w-4 h-4 animate-pulse" />
          </div>
          <div className="flex flex-col text-left">
            <span className="font-display font-extrabold text-xs text-white tracking-tight leading-none">
              RUN ON CONSOLE
            </span>
            <span className="text-[9px] text-emerald-300 font-bold uppercase tracking-wider">
              Independent Gaming Authority
            </span>
          </div>
        </div>
      </div>

      {/* Action Button to Submit Another */}
      {onReset && (
        <div className="relative z-10 pt-2">
          <button
            onClick={onReset}
            className="bg-emerald-600 hover:bg-emerald-500 text-white font-display font-extrabold text-xs sm:text-sm py-3 px-6 rounded-xl shadow-lg border border-emerald-400/40 inline-flex items-center gap-2 transition-all hover:scale-105"
          >
            <span>Submit Another Message</span>
            <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      )}

    </div>
  );
};
