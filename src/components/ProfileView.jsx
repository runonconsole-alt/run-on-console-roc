import React, { useState, useEffect } from 'react';
import { useApp } from '../context/AppContext';
import { 
  User, Mail, ShieldCheck, Gamepad2, Settings, Heart, Bell, MessageSquare, 
  Trash2, Key, RefreshCw, CheckCircle2, AlertCircle, Eye, EyeOff, X, Plus
} from 'lucide-react';
import { Tilt3DCard } from './Tilt3DCard';
import { CyberMatrixHoloBackground } from './CyberMatrixHoloBackground';
import { GamingProfileEditor, GamingAvatar } from './GamingProfileEditor';

export const ProfileView = () => {
  const { currentUser, isSessionLoading, csrfToken, logoutUser, navigateTo } = useApp();

  const [activeTab, setActiveTab] = useState('overview');
  const [loading, setLoading] = useState(true);
  const [profileData, setProfileData] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  // Edit Profile Form State
  const [name, setName] = useState('');
  const [country, setCountry] = useState('United States');
  const [currency, setCurrency] = useState('USD');
  const [bio, setBio] = useState('');
  const [avatarIcon, setAvatarIcon] = useState('avatar_01');

  // Change Email Form State
  const [newEmail, setNewEmail] = useState('');
  const [emailCurrentPassword, setEmailCurrentPassword] = useState('');

  // Change Password Form State
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmNewPassword, setConfirmNewPassword] = useState('');

  // Modal State for Account Deletion
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Price Alert Form State
  const [alertProductId, setAlertProductId] = useState('');
  const [alertTargetPrice, setAlertTargetPrice] = useState('');

  // Redirect anonymous users immediately to /auth/login/?return=%2Fprofile%2F
  useEffect(() => {
    if (!isSessionLoading && !currentUser) {
      if (typeof window !== 'undefined') {
        window.location.href = '/auth/login/?return=%2Fprofile%2F';
      }
    }
  }, [isSessionLoading, currentUser]);

  // Fetch complete profile from server when authenticated
  useEffect(() => {
    if (currentUser) {
      fetchProfile();
    } else {
      setLoading(false);
    }
  }, [currentUser]);

  const fetchProfile = async () => {
    setLoading(true);
    try {
      const resp = await fetch('/api/v1/profile.php?action=get-profile');
      if (resp.status === 401) {
        if (typeof window !== 'undefined') {
          window.location.href = '/auth/login/?return=%2Fprofile%2F';
        }
        return;
      }
      const data = await resp.json();
      if (data && data.success && data.profile) {
        setProfileData(data.profile);
        setName(data.profile.name || '');
        setCountry(data.profile.country || 'United States');
        setCurrency(data.profile.currency || 'USD');
        setBio(data.profile.bio || '');
        setAvatarIcon(data.profile.avatarIcon || 'avatar_01');
      } else {
        setErrorMessage(data.error || "Failed to load profile data.");
      }
    } catch (err) {
      setErrorMessage("Network error loading profile.");
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');
    setLoading(true);

    try {
      const resp = await fetch('/api/v1/profile.php?action=update-profile', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ name, country, currency, bio, avatarIcon })
      });
      const data = await resp.json();
      if (data && data.success) {
        setSuccessMessage(data.message || "Profile updated successfully!");
        fetchProfile();
      } else {
        setErrorMessage(data.error || "Failed to update profile.");
      }
    } catch (err) {
      setErrorMessage("Error updating profile.");
    } finally {
      setLoading(false);
    }
  };

  const handleChangeEmail = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');
    setLoading(true);

    try {
      const resp = await fetch('/api/v1/profile.php?action=change-email', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ newEmail, currentPassword: emailCurrentPassword })
      });
      const data = await resp.json();
      if (data && data.success) {
        setSuccessMessage(data.message);
        setNewEmail('');
        setEmailCurrentPassword('');
      } else {
        setErrorMessage(data.error || "Failed to update email.");
      }
    } catch (err) {
      setErrorMessage("Error updating email.");
    } finally {
      setLoading(false);
    }
  };

  const handleChangePassword = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setSuccessMessage('');

    if (newPassword !== confirmNewPassword) {
      setErrorMessage("New passwords do not match.");
      return;
    }

    setLoading(true);

    try {
      const resp = await fetch('/api/v1/profile.php?action=change-password', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ currentPassword, newPassword, confirmPassword: confirmNewPassword })
      });
      const data = await resp.json();
      if (data && data.success) {
        setSuccessMessage(data.message);
        setCurrentPassword('');
        setNewPassword('');
        setConfirmNewPassword('');
      } else {
        setErrorMessage(data.error || "Failed to change password.");
      }
    } catch (err) {
      setErrorMessage("Error changing password.");
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteAccountConfirm = async (e) => {
    e.preventDefault();
    setErrorMessage('');
    setDeleteLoading(true);

    try {
      const resp = await fetch('/api/v1/profile.php?action=delete-account', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ currentPassword: deletePassword })
      });
      const data = await resp.json();
      if (data && data.success) {
        setShowDeleteModal(false);
        if (typeof window !== 'undefined') {
          window.location.href = '/auth/login/';
        }
      } else {
        setErrorMessage(data.error || "Account deletion failed.");
      }
    } catch (err) {
      setErrorMessage("Error deleting account.");
    } finally {
      setDeleteLoading(false);
    }
  };

  const handleCreatePriceAlert = async (e) => {
    e.preventDefault();
    if (!alertProductId || !alertTargetPrice) return;
    try {
      const resp = await fetch('/api/v1/profile.php?action=create-price-alert', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ productId: alertProductId, targetPrice: parseFloat(alertTargetPrice) })
      });
      const data = await resp.json();
      if (data && data.success) {
        setSuccessMessage("Price alert created!");
        setAlertProductId('');
        setAlertTargetPrice('');
        fetchProfile();
      } else {
        setErrorMessage(data.error || "Failed to create price alert.");
      }
    } catch (err) {
      setErrorMessage("Error creating price alert.");
    }
  };

  const handleDeletePriceAlert = async (alertId) => {
    try {
      const resp = await fetch('/api/v1/profile.php?action=delete-price-alert', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ alertId })
      });
      const data = await resp.json();
      if (data && data.success) {
        setSuccessMessage("Price alert removed.");
        fetchProfile();
      }
    } catch (err) {}
  };

  // Neutral loading state while session verification is pending (matches server pre-rendered state)
  if (isSessionLoading || (loading && !profileData)) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
        <div className="text-center space-y-4">
          <div className="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
          <p className="text-slate-400 font-mono text-sm">Verifying Gamer Authentication Session...</p>
        </div>
      </div>
    );
  }

  // Do NOT render dashboard, "Welcome back", or logout for unauthenticated users
  if (!currentUser) {
    return null;
  }

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 pt-24 pb-20 px-4 sm:px-6 lg:px-8 relative overflow-hidden">
      <CyberMatrixHoloBackground />

      <div className="max-w-7xl mx-auto space-y-8 relative z-10">
        {/* Header Banner */}
        <div className="bg-slate-900/80 border border-slate-800 rounded-3xl p-6 sm:p-8 backdrop-blur-xl relative overflow-hidden shadow-2xl">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-6">
            <div className="flex items-center space-x-6">
              <div className={`w-20 h-20 rounded-2xl bg-gradient-to-tr ${profileData?.avatarBg || 'from-emerald-600 to-teal-500'} flex items-center justify-center text-white text-3xl font-bold shadow-lg shadow-emerald-500/20 ring-2 ring-emerald-500/30`}>
                <GamingAvatar profile={profileData} className="w-16 h-16" />
              </div>
              <div>
                <div className="flex items-center space-x-3">
                  <h1 className="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                    {profileData?.name || currentUser?.name || 'Gamer Profile Dashboard'}
                  </h1>
                  {currentUser && (currentUser.emailVerified || currentUser.isVerified) && (
                    <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                      <ShieldCheck className="w-3.5 h-3.5 mr-1" /> Verified Gamer
                    </span>
                  )}
                </div>
                <p className="text-slate-400 text-sm mt-1 flex items-center">
                  <Mail className="w-4 h-4 mr-1.5 text-slate-500" />
                  {currentUser?.email || 'gamer@runonconsole.com'}
                </p>
              </div>
            </div>

            <div className="flex items-center space-x-3 w-full sm:w-auto">
              <button
                onClick={logoutUser}
                className="flex-1 sm:flex-none inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-sm font-semibold transition border border-rose-500/30"
              >
                Logout
              </button>
            </div>
          </div>
        </div>

        {/* Global Alert Messages */}
        {errorMessage && (
          <div className="bg-rose-500/10 border border-rose-500/30 rounded-2xl p-4 flex items-center space-x-3 text-rose-400 text-sm">
            <AlertCircle className="w-5 h-5 flex-shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}
        {successMessage && (
          <div className="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex items-center space-x-3 text-emerald-400 text-sm">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
            <span>{successMessage}</span>
          </div>
        )}

        {/* Tabs Navigation */}
        <div className="flex space-x-2 border-b border-slate-800 pb-2 overflow-x-auto">
          {[
            { id: 'overview', label: 'Overview', icon: User },
            { id: 'gaming', label: 'Edit Gaming Profile', icon: Gamepad2 },
            { id: 'saved', label: 'Saved Wishlist', icon: Heart },
            { id: 'alerts', label: 'Price Alerts', icon: Bell },
            { id: 'comments', label: 'Comments History', icon: MessageSquare },
            { id: 'settings', label: 'Account Settings', icon: Settings },
          ].map(tab => {
            const Icon = tab.icon;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl font-medium text-sm transition whitespace-nowrap ${
                  activeTab === tab.id
                    ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span>{tab.label}</span>
              </button>
            );
          })}
        </div>

        {activeTab === 'gaming' && profileData && <GamingProfileEditor profile={profileData} csrfToken={csrfToken} onSaved={fetchProfile} />}
        {/* Tab 1: Overview */}
        {activeTab === 'overview' && (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Tilt3DCard className="md:col-span-2 bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl">
              <h2 className="text-xl font-bold text-white mb-4 flex items-center">
                <User className="w-5 h-5 mr-2 text-emerald-400" /> Gamer Profile Details
              </h2>
              <div className="space-y-4 text-sm text-slate-300">
                <p><strong className="text-slate-400">Bio:</strong> {profileData?.bio || 'No bio specified.'}</p>
                <p><strong className="text-slate-400">Location:</strong> {[profileData?.gaming?.city, profileData?.country].filter(Boolean).join(', ') || 'Not specified'}</p>
                <p><strong className="text-slate-400">Favourite games:</strong> {profileData?.gaming?.favoriteGames?.join(', ') || 'Add your favourite games'}</p>
                {Object.entries(profileData?.gaming?.gamingIds || {}).filter(([,v]) => v).map(([k,v]) => <p key={k}><strong className="text-slate-400">{k}:</strong> {v}</p>)}
                <div className="flex flex-wrap gap-3">{Object.entries(profileData?.gaming?.socialLinks || {}).filter(([,v]) => v?.startsWith('https://')).map(([k,v]) => <a key={k} href={v} target="_blank" rel="noopener noreferrer nofollow ugc" className="text-emerald-400 underline">{k}</a>)}</div>
                <button type="button" onClick={() => setActiveTab('gaming')} className="bg-emerald-500 text-slate-950 px-5 py-3 font-bold rounded-xl">Edit gaming profile</button>
                <p><strong className="text-slate-400">Preferred Currency:</strong> {profileData?.currency || 'USD'}</p>
                <p><strong className="text-slate-400">Member Since:</strong> {new Date(profileData?.createdAt || Date.now()).toLocaleDateString()}</p>
              </div>
            </Tilt3DCard>

            <Tilt3DCard className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl">
              <h2 className="text-xl font-bold text-white mb-4 flex items-center">
                <Heart className="w-5 h-5 mr-2 text-rose-400" /> Activity Summary
              </h2>
              <div className="space-y-3 text-sm">
                <div className="flex justify-between py-2 border-b border-slate-800">
                  <span className="text-slate-400">Saved Wishlist</span>
                  <span className="font-bold text-white">{profileData?.savedProducts?.length || 0}</span>
                </div>
                <div className="flex justify-between py-2 border-b border-slate-800">
                  <span className="text-slate-400">Active Price Alerts</span>
                  <span className="font-bold text-white">{profileData?.priceAlerts?.length || 0}</span>
                </div>
                <div className="flex justify-between py-2">
                  <span className="text-slate-400">Comments Posted</span>
                  <span className="font-bold text-white">{profileData?.comments?.length || 0}</span>
                </div>
              </div>
            </Tilt3DCard>
          </div>
        )}

        {/* Tab 2: Saved Wishlist */}
        {activeTab === 'saved' && (
          <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl">
            <h2 className="text-xl font-bold text-white mb-4 flex items-center">
              <Heart className="w-5 h-5 mr-2 text-rose-400" /> Saved Products Wishlist
            </h2>
            {profileData?.savedProducts?.length === 0 ? (
              <p className="text-slate-400 text-sm">No saved products in your wishlist yet.</p>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                {profileData?.savedProducts?.map(prodId => (
                  <div key={prodId} className="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <span className="text-sm font-semibold text-slate-200">{prodId}</span>
                    <button
                      onClick={() => navigateTo('products', prodId)}
                      className="text-xs bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 px-3 py-1.5 rounded-lg border border-emerald-500/30"
                    >
                      View Product →
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Tab 3: Price Alerts */}
        {activeTab === 'alerts' && (
          <div className="space-y-6">
            <form onSubmit={handleCreatePriceAlert} className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl space-y-4">
              <h2 className="text-xl font-bold text-white flex items-center">
                <Bell className="w-5 h-5 mr-2 text-amber-400" /> Create New Price Alert
              </h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <input
                  type="text"
                  placeholder="Product ID (e.g. logitech-g-pro-x-tkl-lightspeed)"
                  value={alertProductId}
                  onChange={(e) => setAlertProductId(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                  required
                />
                <input
                  type="number"
                  step="0.01"
                  placeholder="Target Price ($ USD)"
                  value={alertTargetPrice}
                  onChange={(e) => setAlertTargetPrice(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                  required
                />
              </div>
              <button
                type="submit"
                className="inline-flex items-center px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm rounded-xl transition"
              >
                <Plus className="w-4 h-4 mr-1.5" /> Create Alert
              </button>
            </form>

            <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl">
              <h3 className="text-lg font-bold text-white mb-4">Active Price Alerts</h3>
              {profileData?.priceAlerts?.length === 0 ? (
                <p className="text-slate-400 text-sm">No price alerts set.</p>
              ) : (
                <div className="space-y-3">
                  {profileData?.priceAlerts?.map(alert => (
                    <div key={alert.id} className="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                      <div>
                        <p className="text-sm font-semibold text-white">{alert.product_id}</p>
                        <p className="text-xs text-slate-400">Target: ${alert.target_price} | Status: {alert.status}</p>
                      </div>
                      <button
                        onClick={() => handleDeletePriceAlert(alert.id)}
                        className="text-xs text-rose-400 hover:text-rose-300 p-2"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {/* Tab 4: Comments History */}
        {activeTab === 'comments' && (
          <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl">
            <h2 className="text-xl font-bold text-white mb-4 flex items-center">
              <MessageSquare className="w-5 h-5 mr-2 text-cyan-400" /> Your Comment History
            </h2>
            {profileData?.comments?.length === 0 ? (
              <p className="text-slate-400 text-sm">You have not posted any comments yet.</p>
            ) : (
              <div className="space-y-4">
                {profileData?.comments?.map(c => (
                  <div key={c.id} className="bg-slate-950/80 border border-slate-800 rounded-2xl p-4">
                    <p className="text-sm text-slate-200">{c.comment_text}</p>
                    <div className="flex items-center justify-between text-xs text-slate-500 mt-2">
                      <span>Status: <strong className="text-slate-400">{c.status}</strong></span>
                      <span>{new Date(c.created_at).toLocaleDateString()}</span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Tab 5: Account Settings */}
        {activeTab === 'settings' && (
          <div className="space-y-6">
            {/* Update Profile Info */}
            <form onSubmit={handleUpdateProfile} className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl space-y-4">
              <h2 className="text-lg font-bold text-white flex items-center">
                <User className="w-5 h-5 mr-2 text-emerald-400" /> Edit Profile Information
              </h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Display Name</label>
                  <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-400 mb-1">Country</label>
                  <input
                    type="text"
                    value={country}
                    onChange={(e) => setCountry(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-400 mb-1">Gamer Bio</label>
                <textarea
                  value={bio}
                  onChange={(e) => setBio(e.target.value)}
                  rows="3"
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500 resize-none"
                />
              </div>
              <button
                type="submit"
                className="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm rounded-xl transition"
              >
                Save Profile Changes
              </button>
            </form>

            {/* Change Password */}
            <form onSubmit={handleChangePassword} className="bg-slate-900/60 border border-slate-800 rounded-3xl p-6 backdrop-blur-xl space-y-4">
              <h2 className="text-lg font-bold text-white flex items-center">
                <Key className="w-5 h-5 mr-2 text-amber-400" /> Change Security Password
              </h2>
              <div className="space-y-4 max-w-md">
                <input
                  type="password"
                  placeholder="Current Password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                  required
                />
                <input
                  type="password"
                  placeholder="New Password (min 8 chars)"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                  required
                />
                <input
                  type="password"
                  placeholder="Confirm New Password"
                  value={confirmNewPassword}
                  onChange={(e) => setConfirmNewPassword(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-emerald-500"
                  required
                />
              </div>
              <button
                type="submit"
                className="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm rounded-xl transition"
              >
                Update Password
              </button>
            </form>

            {/* Account Deletion Section */}
            <div className="bg-rose-500/5 border border-rose-500/20 rounded-3xl p-6 backdrop-blur-xl space-y-4">
              <h2 className="text-lg font-bold text-rose-400 flex items-center">
                <Trash2 className="w-5 h-5 mr-2" /> Danger Zone: Delete Account
              </h2>
              <p className="text-xs text-slate-400">
                Permanently remove your profile, wishlist, price alerts, and comments. This action is irreversible.
              </p>
              <button
                onClick={() => setShowDeleteModal(true)}
                className="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold text-sm rounded-xl transition"
              >
                Delete Account
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Account Deletion Password Confirmation Modal */}
      {showDeleteModal && (
        <div className="fixed inset-0 bg-slate-950/80 backdrop-blur-md z-50 flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-md w-full space-y-6 shadow-2xl relative">
            <div className="flex items-center justify-between">
              <h3 className="text-xl font-bold text-rose-400 flex items-center">
                <Trash2 className="w-5 h-5 mr-2" /> Confirm Account Deletion
              </h3>
              <button onClick={() => setShowDeleteModal(false)} className="text-slate-400 hover:text-slate-200">
                <X className="w-5 h-5" />
              </button>
            </div>
            <p className="text-sm text-slate-300">
              To confirm permanent deletion of your Run On Console account, please enter your current password below:
            </p>
            <form onSubmit={handleDeleteAccountConfirm} className="space-y-4">
              <input
                type="password"
                placeholder="Current Password"
                value={deletePassword}
                onChange={(e) => setDeletePassword(e.target.value)}
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-100 focus:outline-none focus:border-rose-500"
                required
              />
              <div className="flex items-center justify-end space-x-3">
                <button
                  type="button"
                  onClick={() => setShowDeleteModal(false)}
                  className="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={deleteLoading}
                  className="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-sm font-bold disabled:opacity-50"
                >
                  {deleteLoading ? 'Deleting...' : 'Permanently Delete'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
