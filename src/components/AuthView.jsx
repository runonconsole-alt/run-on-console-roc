import React, { useState, useEffect, useRef } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Gamepad2, Mail, Lock, User, Check, AlertCircle, 
  Sparkles, RefreshCw, Eye, EyeOff, ShieldCheck, 
  ArrowRight, KeyRound, CheckCircle2, AtSign, Info
} from 'lucide-react';
import { Tilt3DCard } from './Tilt3DCard';
import { BouncyText } from './BouncyText';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';

export const AuthView = () => {
  const { 
    registerUser, 
    loginUser, 
    verifyUserEmail,
    requestPasswordReset,
    resetPasswordWithToken,
    resendVerification,
    navigateTo, 
    currentUser, 
    authConfig,
    authMode,
    setAuthMode,
    csrfToken,
    isSessionLoading
  } = useApp();

  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  // Form Fields
  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [agreeTerms, setAgreeTerms] = useState(false);
  const [captchaAnswer, setCaptchaAnswer] = useState('');
  
  // Status State
  const [errorMessage, setErrorMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState('');
  const [requestId, setRequestId] = useState(null);
  const [unverifiedEmail, setUnverifiedEmail] = useState(null);
  const [loading, setLoading] = useState(false);
  const [verificationToken, setVerificationToken] = useState('');

  // 6-Digit Code Verification State
  const [codeDigits, setCodeDigits] = useState(['', '', '', '', '', '']);
  const [resendCooldown, setResendCooldown] = useState(0);
  const inputRefs = useRef([]);

  useEffect(() => {
    if (resendCooldown <= 0) return;
    const timer = setInterval(() => {
      setResendCooldown((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(timer);
  }, [resendCooldown]);

  const handleDigitChange = (index, value) => {
    const digit = value.replace(/\D/g, '').slice(-1);
    const newDigits = [...codeDigits];
    newDigits[index] = digit;
    setCodeDigits(newDigits);

    if (digit && index < 5 && inputRefs.current[index + 1]) {
      inputRefs.current[index + 1].focus();
    }
  };

  const handleDigitKeyDown = (index, e) => {
    if (e.key === 'Backspace' && !codeDigits[index] && index > 0 && inputRefs.current[index - 1]) {
      inputRefs.current[index - 1].focus();
    }
  };

  const handleDigitPaste = (e) => {
    e.preventDefault();
    const pastedData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
    if (pastedData) {
      const newDigits = [...codeDigits];
      for (let i = 0; i < 6; i++) {
        newDigits[i] = pastedData[i] || '';
      }
      setCodeDigits(newDigits);
      const nextIndex = Math.min(pastedData.length - 1, 5);
      if (inputRefs.current[nextIndex]) {
        inputRefs.current[nextIndex].focus();
      }
    }
  };

  const handleVerifyCodeSubmit = async (e) => {
    if (e) e.preventDefault();
    const fullCode = codeDigits.join('');
    if (fullCode.length !== 6) {
      setErrorMessage("Please enter all 6 digits of your verification code.");
      return;
    }

    setLoading(true);
    setErrorMessage('');
    setSuccessMessage('');

    try {
      const targetEmail = unverifiedEmail || email;
      const res = await fetch('/api/v1/auth.php?action=verify-email-code', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken || '' },
        body: JSON.stringify({ email: targetEmail, code: fullCode })
      });
      const data = await res.json();
      if (data.success) {
        setSuccessMessage(data.message || "Email verified successfully! Redirecting...");
        setTimeout(() => {
          window.location.href = '/profile/';
        }, 1200);
      } else {
        setErrorMessage(data.message || data.error || "Verification failed.");
      }
    } catch (err) {
      setErrorMessage("Network error during verification. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleResendWithCooldown = async () => {
    if (resendCooldown > 0) return;
    const targetEmail = unverifiedEmail || email;
    if (!targetEmail) return;

    setLoading(true);
    setErrorMessage('');
    setSuccessMessage('');

    try {
      const res = await resendVerification(targetEmail);
      if (res.success) {
        setSuccessMessage(res.message || "A new verification code has been dispatched!");
        setResendCooldown(60);
      } else {
        setErrorMessage(res.message || res.error || "Failed to resend code.");
      }
    } catch (err) {
      setErrorMessage("Error resending code.");
    } finally {
      setLoading(false);
    }
  };

  const captchaContainerRef = useRef(null);
  const turnstileWidgetId = useRef(null);

  // Load CAPTCHA Widget dynamically when site_key exists
  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (authMode !== 'signup') return;
    if (!authConfig?.captcha_configured || !authConfig?.captcha_site_key) return;

    const provider = authConfig.captcha_provider || 'turnstile';

    if (provider === 'turnstile') {
      const scriptId = 'cloudflare-turnstile-script';
      if (!document.getElementById(scriptId)) {
        const script = document.createElement('script');
        script.id = scriptId;
        script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        script.defer = true;
        document.body.appendChild(script);
      }

      const renderTurnstile = () => {
        if (window.turnstile && captchaContainerRef.current) {
          try {
            captchaContainerRef.current.innerHTML = '';
            turnstileWidgetId.current = window.turnstile.render(captchaContainerRef.current, {
              sitekey: authConfig.captcha_site_key,
              callback: (token) => setCaptchaAnswer(token),
              'expired-callback': () => setCaptchaAnswer(''),
              'error-callback': () => setCaptchaAnswer('')
            });
          } catch (e) {}
        }
      };

      const interval = setInterval(() => {
        if (window.turnstile) {
          renderTurnstile();
          clearInterval(interval);
        }
      }, 200);

      return () => clearInterval(interval);
    }
  }, [authMode, authConfig]);

  const resetCaptchaWidget = () => {
    setCaptchaAnswer('');
    if (typeof window !== 'undefined' && window.turnstile && turnstileWidgetId.current !== null) {
      try {
        window.turnstile.reset(turnstileWidgetId.current);
      } catch (e) {}
    }
  };

  // Detect URL query parameters for token verification or password reset
  useEffect(() => {
    if (typeof window === 'undefined') return;
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token') || urlParams.get('activate');
    const err = urlParams.get('error');
    const reqId = urlParams.get('request_id');
    const path = window.location.pathname.toLowerCase();

    if (reqId) {
      setRequestId(reqId);
    }

    if (err === 'oauth_unconfigured') {
      setErrorMessage('Google sign-in is temporarily unavailable. Please set GOOGLE_CLIENT_ID in env.php.');
    } else if (err === 'oauth_access_denied') {
      setErrorMessage('Google Sign-In was cancelled or access was denied (GOOGLE_ACCESS_DENIED).');
    } else if (err === 'oauth_state_invalid') {
      setErrorMessage('OAuth session validation failed (GOOGLE_STATE_INVALID). Please try signing in again.');
    } else if (err === 'oauth_token_exchange_failed') {
      setErrorMessage('Failed to exchange Google authorization code for token (GOOGLE_TOKEN_EXCHANGE_FAILED).');
    } else if (err === 'oauth_id_token_invalid') {
      setErrorMessage('Invalid Google profile or ID token signature (GOOGLE_ID_TOKEN_INVALID).');
    } else if (err === 'oauth_unverified_email') {
      setErrorMessage('Your Google account email address is unverified (GOOGLE_EMAIL_UNVERIFIED).');
    } else if (err === 'oauth_link_required') {
      setErrorMessage('This email already has an account. Sign in with your password, then use Link Google in your profile settings.');
    } else if (err === 'oauth_email_mismatch') {
      setErrorMessage('Choose the Google account with the same email as your profile.');
    } else if (err === 'oauth_reauthentication_required') {
      setErrorMessage('Please sign in again before linking Google.');
    } else if (err === 'oauth_server_error') {
      const ref = reqId || `req_${Date.now().toString(36)}`;
      setErrorMessage(`Google authentication server error occurred (GOOGLE_SERVER_ERROR). Reference ID: ${ref}`);
    } else if (err) {
      setErrorMessage(`Authentication error: ${err}. Please try again.`);
    }

    if (path.includes('/verify-email') || (token && !path.includes('/reset-password'))) {
      if (token) {
        setVerificationToken(token);
      }
    }
  }, []);

  const handleAutoVerify = async (token) => {
    setLoading(true);
    setErrorMessage('');
    try {
      const res = await verifyUserEmail(token);
      if (res.success) {
        setSuccessMessage("Your account is verified. Opening your profile.");
        window.history.replaceState({}, '', '/auth/verify-email/');
        window.location.assign('/profile/');
      } else {
        setErrorMessage(res.error || res.message || "Invalid or expired verification token.");
      }
    } catch (err) {
      setErrorMessage("Email verification failed. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const handleSignup = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');
    setRequestId(null);

    if (!authConfig?.captcha_configured) {
      setErrorMessage("Registration is temporarily unavailable because security verification is not configured.");
      return;
    }

    if (!name || !username || !email || !password || !confirmPassword) {
      setErrorMessage("Please fill in all required fields including your unique username.");
      return;
    }

    if (password !== confirmPassword) {
      setErrorMessage("Passwords do not match.");
      return;
    }

    if (password.length < 8) {
      setErrorMessage("Password must be at least 8 characters long.");
      return;
    }

    if (!agreeTerms) {
      setErrorMessage("You must agree to the Terms of Service and Privacy Policy.");
      return;
    }

    if (!captchaAnswer) {
      setErrorMessage("Please complete the CAPTCHA security verification.");
      return;
    }

    setLoading(true);
    try {
      const res = await registerUser({ 
        name, 
        username, 
        email, 
        password, 
        confirmPassword, 
        agreeTerms, 
        captchaToken: captchaAnswer 
      });

      if (res.success) {
        setSuccessMessage(res.message || "Account created! A verification link has been sent to your email.");
        navigateTo('auth', 'verify-email');
        setPassword('');
        setConfirmPassword('');
        setUnverifiedEmail(email);
      } else {
        const errorText = res.message || res.error || "Registration failed.";
        setErrorMessage(errorText);
        if (res.request_id) setRequestId(res.request_id);
        resetCaptchaWidget();
      }
    } catch (err) {
      setErrorMessage("Registration error. Please try again.");
      resetCaptchaWidget();
    } finally {
      setLoading(false);
    }
  };

  const handleLogin = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');
    setRequestId(null);

    const loginIdentifier = email.trim();

    if (!loginIdentifier || !password) {
      setErrorMessage("Please enter both username/email and password.");
      return;
    }

    setLoading(true);
    try {
      const res = await loginUser({ identifier: loginIdentifier, email: loginIdentifier, password });
      if (res.success) {
        const urlParams = new URLSearchParams(window.location.search);
        let returnUrl = urlParams.get('return') || '/profile/';
        
        // Filter safe internal paths starting with a single '/'
        if (!returnUrl.startsWith('/') || returnUrl.startsWith('//') || returnUrl.includes('\\')) {
          returnUrl = '/profile/';
        }

        window.location.href = returnUrl;
      } else {
        const errorText = res.message || res.error || "Invalid username/email or password";
        setErrorMessage(errorText);
        if (res.request_id) setRequestId(res.request_id);
        if (res.error === "VERIFICATION_REQUIRED") {
          setUnverifiedEmail(res.email || loginIdentifier);
        }
      }
    } catch (err) {
      setErrorMessage("Invalid username/email or password");
    } finally {
      setLoading(false);
    }
  };

  const handleResend = async () => {
    if (!unverifiedEmail) return;
    setLoading(true);
    try {
      const res = await resendVerification(unverifiedEmail);
      if (res.success) {
        setSuccessMessage(res.message || "Verification email sent.");
      } else {
        setErrorMessage(res.error || res.message || "Resend failed.");
      }
    } catch (err) {
      setErrorMessage("Resend error.");
    } finally {
      setLoading(false);
    }
  };

  const handleForgotPassword = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');
    setRequestId(null);

    if (!email) {
      setErrorMessage("Please enter your email address.");
      return;
    }

    setLoading(true);
    try {
      const res = await requestPasswordReset(email);
      if (res.success) {
        setSuccessMessage(res.message || "If your email is registered, a reset link has been sent.");
      } else {
        setErrorMessage(res.error || res.message || "Password reset request failed.");
      }
    } catch (err) {
      setErrorMessage("Password reset request failed.");
    } finally {
      setLoading(false);
    }
  };

  const handleResetPassword = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');
    setRequestId(null);

    const token = new URLSearchParams(window.location.search).get('token');
    if (!token) {
      setErrorMessage("Missing password reset token in URL.");
      return;
    }

    if (!password || !confirmPassword) {
      setErrorMessage("Please fill in both password fields.");
      return;
    }

    if (password !== confirmPassword) {
      setErrorMessage("Passwords do not match.");
      return;
    }

    setLoading(true);
    try {
      const res = await resetPasswordWithToken({ token, password, confirmPassword });
      if (res.success) {
        setSuccessMessage("Your password has been reset successfully! You can now log in.");
        setAuthMode('login');
        navigateTo('auth', 'login');
      } else {
        setErrorMessage(res.error || res.message || "Password reset failed.");
      }
    } catch (err) {
      setErrorMessage("Password reset failed. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  const isSignupDisabled = loading || isSessionLoading || !csrfToken || !name || !username || !email || !password || !confirmPassword || !agreeTerms || (authConfig?.captcha_configured && !captchaAnswer) || !authConfig?.captcha_configured;

  return (
    <div className="py-8 max-w-xl mx-auto space-y-8 animate-page-in">

      {/* Cyber Matrix Hero Card */}
      <Tilt3DCard className="gradient-hero-bg text-white rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden border border-emerald-500/30">
        <CyberMatrixHoloBackground />

        <div className="relative z-10 text-center space-y-3">
          <div className="inline-flex items-center gap-2 bg-emerald-400 text-slate-950 text-xs font-extrabold uppercase px-3.5 py-1.5 rounded-full tracking-wider shadow-sm">
            <ShieldCheck className="w-4 h-4" /> SECURE GAMER PORTAL
          </div>
          <h1 className="font-display font-extrabold text-3xl sm:text-4xl text-white">
            {authMode === 'login' && "Sign In to Run On Console"}
            {authMode === 'signup' && "Create Your Gamer Account"}
            {authMode === 'verify-email' && "Email Verification"}
            {authMode === 'forgot-password' && "Forgot Password"}
            {authMode === 'reset-password' && "Choose New Password"}
          </h1>
          <p className="text-emerald-100 text-xs sm:text-sm">
            Independent gaming hardware reviews, custom FPS specs compatibility matrix & verified deals.
          </p>
        </div>
      </Tilt3DCard>

      {/* Status Notifications */}
      {errorMessage && (
        <div className="p-4 bg-rose-950/80 border border-rose-500/50 text-rose-200 rounded-2xl flex items-start gap-3 text-sm shadow-md animate-fade-in">
          <AlertCircle className="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" />
          <div className="space-y-1">
            <p>{errorMessage}</p>
            {requestId && (
              <p className="text-[10px] text-rose-400/80 font-mono">Reference ID: {requestId}</p>
            )}
            {unverifiedEmail && (
              <button 
                onClick={handleResend} 
                className="mt-2 text-xs bg-rose-800 hover:bg-rose-700 text-white font-bold px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5"
              >
                <RefreshCw className="w-3.5 h-3.5" /> Resend Verification Email
              </button>
            )}
          </div>
        </div>
      )}

      {successMessage && (
        <div className="p-4 bg-emerald-950/80 border border-emerald-500/50 text-emerald-200 rounded-2xl flex items-start gap-3 text-sm shadow-md animate-fade-in">
          <CheckCircle2 className="w-5 h-5 text-emerald-400 flex-shrink-0 mt-0.5" />
          <p>{successMessage}</p>
        </div>
      )}

      {/* Main Form Container */}
      <div className="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-md">
        
        {/* Navigation Tabs */}
        {(authMode === 'login' || authMode === 'signup') && (
          <div className="grid grid-cols-2 gap-2 p-1.5 bg-slate-950/80 rounded-2xl border border-slate-800 mb-6">
            <button
              onClick={() => { setAuthMode('login'); navigateTo('auth', 'login'); setErrorMessage(''); setSuccessMessage(''); }}
              className={`py-2.5 rounded-xl font-display font-extrabold text-sm transition-all ${
                authMode === 'login'
                  ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              Sign In
            </button>
            <button
              onClick={() => { setAuthMode('signup'); navigateTo('auth', 'signup'); setErrorMessage(''); setSuccessMessage(''); }}
              className={`py-2.5 rounded-xl font-display font-extrabold text-sm transition-all ${
                authMode === 'signup'
                  ? 'bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 shadow-md'
                  : 'text-slate-400 hover:text-white'
              }`}
            >
              Create Account
            </button>
          </div>
        )}

        {/* Authentication Choices */}
        {(authMode === 'login' || authMode === 'signup') && (
          <div className="space-y-3 mb-6">
            {authConfig?.google_oauth_configured ? (
              <a
                href="/api/v1/oauth/google-start.php"
                className="w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-display font-bold text-xs rounded-xl border border-slate-700 transition flex items-center justify-center gap-2.5 no-underline shadow-sm"
              >
                <svg className="w-4 h-4" viewBox="0 0 24 24">
                  <path fill="#EA4335" d="M12 5c1.6 0 3 .6 4.1 1.6l3.1-3.1C17.3 1.7 14.8 1 12 1 7.5 1 3.7 3.6 1.9 7.3l3.7 2.9C6.5 7.4 9 5 12 5z"/>
                  <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5c-.3 1.5-1.1 2.8-2.4 3.7l3.7 2.9c2.2-2 3.7-5 3.7-8.8z"/>
                  <path fill="#FBBC05" d="M5.6 14.8c-.2-.7-.4-1.5-.4-2.3s.2-1.6.4-2.3L1.9 7.3C.7 9.7 0 12.3 0 15s.7 5.3 1.9 7.7l3.7-2.9c-.2-.8-.4-1.6-.4-2.3z"/>
                  <path fill="#34A853" d="M12 23c3.2 0 6-1.1 8-3l-3.7-2.9c-1.1.7-2.5 1.2-4.3 1.2-3 0-5.5-2.4-6.4-5.2L1.9 16c1.8 3.7 5.6 6.3 10.1 6.3z"/>
                </svg>
                <span>Continue with Google</span>
              </a>
            ) : (
              <div className="p-3 bg-slate-950/60 border border-slate-800 rounded-xl text-center">
                <p className="text-xs text-slate-400 flex items-center justify-center gap-1.5">
                  <Info className="w-3.5 h-3.5 text-amber-400" />
                  <span>Google sign-in is temporarily unavailable. Please continue with email.</span>
                </p>
              </div>
            )}

            <div className="flex items-center justify-between text-xs text-slate-500 font-bold uppercase tracking-wider my-3">
              <span className="h-px bg-slate-800 flex-1"></span>
              <span className="px-3">Or Continue With Email</span>
              <span className="h-px bg-slate-800 flex-1"></span>
            </div>
          </div>
        )}

        {/* 1. SIGN IN FORM */}
        {authMode === 'login' && (
          <form onSubmit={handleLogin} className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Username or Email</label>
              <div className="relative">
                <User className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="text"
                  required
                  autoComplete="username"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="username or gamer@example.com"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <div className="flex justify-between items-center">
                <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Password</label>
                <button
                  type="button"
                  onClick={() => { setAuthMode('forgot-password'); navigateTo('auth', 'forgot-password'); }}
                  className="text-xs text-emerald-400 hover:underline"
                >
                  Forgot password?
                </button>
              </div>
              <div className="relative">
                <Lock className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type={showPassword ? "text" : "password"}
                  required
                  autoComplete="current-password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-11 py-3 text-sm focus:outline-none transition-colors"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-3.5 top-3.5 text-slate-400 hover:text-white"
                >
                  {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                </button>
              </div>
            </div>

            <button
              type="submit"
              disabled={loading || isSessionLoading}
              className="w-full py-3.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 hover:from-emerald-400 hover:to-cyan-400 text-slate-950 font-display font-extrabold text-sm uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2"
            >
              {loading ? <RefreshCw className="w-5 h-5 animate-spin" /> : <>Sign In <ArrowRight className="w-4 h-4" /></>}
            </button>
          </form>
        )}

        {/* 2. SIGNUP FORM */}
        {authMode === 'signup' && (
          <form onSubmit={handleSignup} className="space-y-4">
            {!authConfig?.captcha_configured && (
              <div className="p-3.5 bg-rose-950/60 border border-rose-500/40 rounded-2xl text-rose-200 text-xs flex items-center gap-2.5">
                <AlertCircle className="w-4 h-4 text-rose-400 shrink-0" />
                <span>Registration is temporarily unavailable because security verification is not configured.</span>
              </div>
            )}

            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Display Name</label>
              <div className="relative">
                <User className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="text"
                  required
                  disabled={!authConfig?.captcha_configured}
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Alex Mercer"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Unique Username</label>
              <div className="relative">
                <AtSign className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="text"
                  required
                  disabled={!authConfig?.captcha_configured}
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="gamer_tag99"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
              <p className="text-[10px] text-slate-500">3–30 characters, letters, numbers & underscores only.</p>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Email Address</label>
              <div className="relative">
                <Mail className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="email"
                  required
                  disabled={!authConfig?.captcha_configured}
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="gamer@example.com"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Password</label>
                <div className="relative">
                  <Lock className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                  <input
                    type={showPassword ? "text" : "password"}
                    required
                    autoComplete="new-password"
                    disabled={!authConfig?.captcha_configured}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="Min 8 chars"
                    className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-10 py-3 text-sm focus:outline-none transition-colors"
                  />
                </div>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Confirm Password</label>
                <div className="relative">
                  <Lock className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                  <input
                    type={showConfirmPassword ? "text" : "password"}
                    required
                    autoComplete="new-password"
                    disabled={!authConfig?.captcha_configured}
                    value={confirmPassword}
                    onChange={(e) => setConfirmPassword(e.target.value)}
                    placeholder="Repeat password"
                    className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-10 py-3 text-sm focus:outline-none transition-colors"
                  />
                </div>
              </div>
            </div>

            <div className="flex items-start gap-2.5 pt-2">
              <input
                type="checkbox"
                id="terms"
                required
                disabled={!authConfig?.captcha_configured}
                checked={agreeTerms}
                onChange={(e) => setAgreeTerms(e.target.checked)}
                className="mt-1 w-4 h-4 accent-emerald-500 rounded cursor-pointer"
              />
              <label htmlFor="terms" className="text-xs text-slate-300 leading-relaxed cursor-pointer">
                I agree to the <a href="/terms-and-conditions/" onClick={(e) => { e.preventDefault(); navigateTo('terms-and-conditions'); }} className="text-emerald-400 hover:underline">Terms of Service</a> and <a href="/privacy-policy/" onClick={(e) => { e.preventDefault(); navigateTo('privacy-policy'); }} className="text-emerald-400 hover:underline">Privacy Policy</a>. Account will be created in pending verification status.
              </label>
            </div>

            {/* CAPTCHA Widget Container */}
            {authConfig?.captcha_configured && (
              <div className="pt-2 flex justify-center min-h-[65px]">
                <div ref={captchaContainerRef}></div>
              </div>
            )}

            <button
              type="submit"
              disabled={isSignupDisabled}
              className={`w-full py-3.5 font-display font-extrabold text-sm uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 ${
                isSignupDisabled 
                  ? 'bg-slate-800 text-slate-500 cursor-not-allowed border border-slate-700' 
                  : 'bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 hover:from-emerald-400 hover:to-cyan-400 text-slate-950'
              }`}
            >
              {loading ? <RefreshCw className="w-5 h-5 animate-spin" /> : <>Create Account <ArrowRight className="w-4 h-4" /></>}
            </button>
          </form>
        )}

        {/* 3. FORGOT PASSWORD FORM */}
        {authMode === 'forgot-password' && (
          <form onSubmit={handleForgotPassword} className="space-y-4">
            <p className="text-xs text-slate-300 leading-relaxed">
              Enter your email address below and we will send you a secure password reset link.
            </p>
            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Email Address</label>
              <div className="relative">
                <Mail className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="gamer@example.com"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
            </div>
            <button
              type="submit"
              disabled={loading}
              className="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-display font-extrabold text-sm uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2"
            >
              {loading ? <RefreshCw className="w-5 h-5 animate-spin" /> : "Send Password Reset Link"}
            </button>
            <div className="text-center pt-2">
              <button
                type="button"
                onClick={() => { setAuthMode('login'); navigateTo('auth', 'login'); }}
                className="text-xs text-slate-400 hover:text-white underline"
              >
                Back to Sign In
              </button>
            </div>
          </form>
        )}

        {/* 4. RESET PASSWORD FORM */}
        {authMode === 'reset-password' && (
          <form onSubmit={handleResetPassword} className="space-y-4">
            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">New Password</label>
              <div className="relative">
                <Lock className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Min 8 chars"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300">Confirm New Password</label>
              <div className="relative">
                <Lock className="w-5 h-5 absolute left-3.5 top-3.5 text-slate-400" />
                <input
                  type="password"
                  required
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="Repeat new password"
                  className="w-full bg-slate-950 border border-slate-800 focus:border-emerald-500 text-white rounded-xl pl-11 pr-4 py-3 text-sm focus:outline-none transition-colors"
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-slate-950 font-display font-extrabold text-sm uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2"
            >
              {loading ? <RefreshCw className="w-5 h-5 animate-spin" /> : "Save New Password"}
            </button>
          </form>
        )}

        {/* 5. VERIFY EMAIL VIEW (6-DIGIT CODE + SECURE LINK) */}
        {authMode === 'verify-email' && (
          <form onSubmit={e => { if (verificationToken) { e.preventDefault(); handleAutoVerify(verificationToken); } else handleVerifyCodeSubmit(e); }} className="space-y-6 text-center">
            {verificationToken ? <p>Confirm your email to activate your account and open your gaming profile.</p> : <label className="block text-left text-sm text-slate-300">Your registration email
              <input type="email" autoComplete="email" required value={unverifiedEmail || email}
                onChange={e => { setUnverifiedEmail(null); setEmail(e.target.value); }}
                className="mt-2 w-full rounded-xl bg-slate-950 p-3 border border-slate-700" />
            </label>}
            <div className="space-y-2">
              <p className="text-xs text-slate-300">
                We sent a 6-digit verification code and link to:
              </p>
              <p className="text-sm font-bold font-mono text-emerald-400 bg-slate-950/80 py-1.5 px-3 rounded-xl inline-block border border-emerald-500/30">
                {unverifiedEmail ? (unverifiedEmail.length > 5 ? unverifiedEmail.slice(0, 3) + '***@' + unverifiedEmail.split('@')[1] : unverifiedEmail) : (email ? email.slice(0, 3) + '***@' + email.split('@')[1] : 'your inbox')}
              </p>
            </div>

            {/* 6-Digit Code Input Boxes */}
            <div className="space-y-2" hidden={!!verificationToken}>
              <label className="text-xs font-bold uppercase tracking-wider text-slate-300 block">
                Enter 6-Digit Verification Code
              </label>
              <div className="flex justify-center gap-2 sm:gap-3" onPaste={handleDigitPaste}>
                {codeDigits.map((digit, idx) => (
                  <input
                    key={idx}
                    ref={(el) => (inputRefs.current[idx] = el)}
                    type="text"
                    inputMode="numeric"
                    maxLength={1}
                    autoComplete="one-time-code"
                    value={digit}
                    onChange={(e) => handleDigitChange(idx, e.target.value)}
                    onKeyDown={(e) => handleDigitKeyDown(idx, e)}
                    className="w-10 h-12 sm:w-12 sm:h-14 text-center font-mono font-extrabold text-xl bg-slate-950 border border-slate-800 focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/20 text-emerald-300 rounded-xl focus:outline-none transition-all shadow-inner"
                  />
                ))}
              </div>
              <p className="text-[10px] text-slate-400">Code expires in 10 minutes. You can also click the verification link in your email.</p>
            </div>

            <button
              type="submit"
              disabled={loading || isSessionLoading || (!verificationToken && codeDigits.join('').length !== 6)}
              className="w-full py-3.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-500 hover:from-emerald-400 hover:to-cyan-400 text-slate-950 font-display font-extrabold text-sm uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50"
            >
              {loading ? <RefreshCw className="w-5 h-5 animate-spin" /> : <>Verify Account <CheckCircle2 className="w-4 h-4" /></>}
            </button>

            <div className="pt-2 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs border-t border-slate-800">
              <button
                type="button"
                onClick={handleResendWithCooldown}
                disabled={loading || resendCooldown > 0}
                className="text-emerald-400 hover:underline font-bold disabled:text-slate-500 disabled:no-underline flex items-center gap-1"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
                {resendCooldown > 0 ? `Resend Code (${resendCooldown}s)` : "Resend Verification Code"}
              </button>

              <button
                type="button"
                onClick={() => { setAuthMode('signup'); navigateTo('auth', 'signup'); }}
                className="text-slate-400 hover:text-white underline"
              >
                Change Email / Register Again
              </button>
            </div>
          </form>
        )}

        {/* Guest Continue Link */}
        {(authMode === 'login' || authMode === 'signup') && (
          <div className="pt-4 mt-6 border-t border-slate-800/80 text-center">
            <button
              type="button"
              onClick={() => {
                const urlParams = new URLSearchParams(window.location.search);
                const returnUrl = urlParams.get('return') || '/';
                window.location.href = returnUrl.startsWith('/') && !returnUrl.startsWith('//') && !returnUrl.includes('\\') ? returnUrl : '/';
              }}
              className="text-xs font-bold text-slate-400 hover:text-emerald-400 inline-flex items-center gap-1 transition"
            >
              <span>Continue as Guest</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>
        )}
      </div>
    </div>
  );
};
