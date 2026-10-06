import React from 'react';
import { CheckCircle2, AlertTriangle, ShieldCheck, MailCheck, Loader2 } from 'lucide-react';

/**
 * ActionFeedbackCard - Reusable accessible action-feedback component with clean white background
 */
export function ActionFeedbackCard({
  status = 'idle',
  variant = 'general',
  title = '',
  message = '',
  requestId = null
}) {
  if (status === 'idle' && !message && !title) return null;

  const isFailed = status === 'failed';
  const isSubmitting = status === 'submitting';
  const isSuccess = status === 'completed' || status === 'accepted';

  return (
    <div
      role="status"
      aria-live="polite"
      aria-atomic="true"
      className={`relative overflow-hidden rounded-2xl border p-5 sm:p-6 shadow-md transition-all duration-200 ${
        isFailed
          ? 'bg-rose-50 border-rose-200 text-rose-950'
          : isSuccess
          ? 'bg-white border-emerald-500/40 text-slate-900 shadow-emerald-500/10'
          : isSubmitting
          ? 'bg-white border-emerald-400 text-slate-900 shadow-sm'
          : 'bg-white border-slate-200 text-slate-900'
      }`}
    >
      <div className="flex items-start gap-4">
        {/* Illustration Badge */}
        <div className="shrink-0 pt-0.5">
          {isSubmitting && (
            <div className="p-3 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200">
              <Loader2 className="w-6 h-6 animate-spin" />
            </div>
          )}

          {isFailed && (
            <div className="p-3 rounded-xl bg-rose-100 text-rose-600 border border-rose-200">
              <AlertTriangle className="w-6 h-6" />
            </div>
          )}

          {isSuccess && variant === 'signup' && (
            <div className="p-3 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200">
              <CourierTruckIcon className="w-7 h-7" />
            </div>
          )}

          {isSuccess && variant === 'verification' && (
            <div className="p-3 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200">
              <MailCheck className="w-6 h-6" />
            </div>
          )}

          {isSuccess && variant === 'profile' && (
            <div className="p-3 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200">
              <ShieldCheck className="w-6 h-6" />
            </div>
          )}

          {isSuccess && variant === 'general' && (
            <div className="p-3 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200">
              <CheckCircle2 className="w-6 h-6" />
            </div>
          )}
        </div>

        {/* Text Details */}
        <div className="space-y-1.5 flex-1 min-w-0">
          {title && (
            <h4 className={`text-sm font-extrabold tracking-wide uppercase ${
              isFailed ? 'text-rose-700' : 'text-emerald-700'
            }`}>
              {title}
            </h4>
          )}

          {message && (
            <p className="text-xs sm:text-sm text-slate-700 leading-relaxed font-medium">
              {message}
            </p>
          )}

          {requestId && (
            <p className="text-[10px] font-mono text-slate-400 pt-1">
              Ref ID: <span className="text-slate-600 font-bold">{requestId}</span>
            </p>
          )}
        </div>
      </div>
    </div>
  );
}

function CourierTruckIcon({ className = "w-6 h-6" }) {
  return (
    <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <rect x="1" y="3" width="15" height="13" rx="2" />
      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
      <circle cx="5.5" cy="18.5" r="2.5" />
      <circle cx="18.5" cy="18.5" r="2.5" />
      <path d="M7 8h5" />
      <path d="M7 11h3" />
    </svg>
  );
}
