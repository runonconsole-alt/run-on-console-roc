import React, { useState } from 'react';
import { useApp } from '../../context/AppContext';
import { AdminSidebar } from './AdminSidebar';
import { AdminOverview } from './AdminOverview';
import { AdminMediaManager } from './AdminMediaManager';
import { AdminContentManager } from './AdminContentManager';
import { AdminMetasManager } from './AdminMetasManager';
import { AdminInternalLinksManager } from './AdminInternalLinksManager';
import { AdminSchemasManager } from './AdminSchemasManager';
import { AdminProductList } from './AdminProductList';
import { AdminReviewForm } from './AdminReviewForm';
import { AdminBlogList } from './AdminBlogList';
import { AdminBlogForm } from './AdminBlogForm';
import { AdminSubmissionsList } from './AdminSubmissionsList';
import { 
  CheckCircle, ShieldCheck, Sparkles, ExternalLink, 
  Gamepad2, Unlock, Eye, ArrowLeft
} from 'lucide-react';
import { BouncyText } from '../BouncyText';

export const AdminLayout = () => {
  const { 
    currentUser, navigateTo,
    adminTab, setAdminTab, adminNotification 
  } = useApp();

  const [editingProduct, setEditingProduct] = useState(null);
  const [editingBlog, setEditingBlog] = useState(null);

  const handleEditProduct = (item) => {
    setEditingProduct(item);
    setAdminTab('edit-product');
  };

  const handleEditBlog = (blog) => {
    setEditingBlog(blog);
    setAdminTab('edit-blog');
  };

  return (
    <div className="min-h-screen bg-[#F8FAFC] text-slate-900 flex flex-col lg:flex-row font-body">
      
      {/* Invisible H1 for SEO audit and accessibility while keeping sleek header */}
      <h1 className="sr-only">ROC Admin Console</h1>

      {/* ROC Admin Master Sidebar */}
      <AdminSidebar />

      {/* Main Admin Dashboard Region */}
      <div className="flex-1 flex flex-col min-w-0">
        
        {/* Top Control Bar with Unlocked Status & Fast Access */}
        <header className="bg-white border-b border-slate-200 px-6 py-3.5 flex items-center justify-between gap-4 sticky top-0 z-30 shadow-xs">
          <div className="flex items-center gap-3">
            <div className="flex items-center gap-2">
              <span className="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
              <span className="font-display font-black text-sm text-slate-900 uppercase tracking-wide">
                <BouncyText text="ROC ADMIN CONSOLE" />
              </span>
            </div>
            
            <div className="hidden sm:flex items-center gap-1.5 bg-emerald-50 text-emerald-800 border border-emerald-300 px-2.5 py-1 rounded-full text-[10px] font-extrabold">
              <Unlock className="w-3 h-3 text-emerald-600" />
              <span>Full Access Unlocked</span>
            </div>
          </div>

          <div className="flex items-center gap-2.5">
            <button
              onClick={() => navigateTo('home')}
              className="bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold py-1.5 px-3 rounded-xl flex items-center gap-1.5 transition-colors"
            >
              <Eye className="w-3.5 h-3.5 text-slate-600" />
              <span className="hidden sm:inline">Preview Live Site</span>
            </button>
            
            <div className="px-3 py-1 bg-slate-900 text-emerald-400 font-mono text-xs font-bold rounded-xl border border-slate-700">
              Admin: Master
            </div>
          </div>
        </header>

        {/* Dashboard Main Workspace */}
        <main className="flex-1 p-6 lg:p-8 overflow-x-hidden">
          
          {/* Toast / Notification Banner */}
          {adminNotification && (
            <div className={`mb-6 p-4 rounded-2xl border flex items-center gap-3 text-xs font-semibold ${
              adminNotification.type === 'warning'
                ? 'bg-rose-50 border-rose-200 text-rose-700'
                : 'bg-emerald-50 border-emerald-200 text-emerald-700'
            }`}>
              <CheckCircle className="w-5 h-5 text-emerald-600" />
              <span>{adminNotification.msg}</span>
            </div>
          )}

          {/* Tab Routing */}
          {adminTab === 'overview' && <AdminOverview />}
          {adminTab === 'images' && <AdminMediaManager />}
          {adminTab === 'content' && <AdminContentManager />}
          {adminTab === 'metas' && <AdminMetasManager />}
          {adminTab === 'internal-links' && <AdminInternalLinksManager />}
          {adminTab === 'schemas' && <AdminSchemasManager />}
          {adminTab === 'products' && <AdminProductList onEdit={handleEditProduct} />}
          {adminTab === 'blogs' && <AdminBlogList onEdit={handleEditBlog} />}
          {adminTab === 'submissions' && <AdminSubmissionsList />}
          {adminTab === 'new-product' && <AdminReviewForm onDone={() => setAdminTab('products')} />}
          {adminTab === 'edit-product' && <AdminReviewForm initialData={editingProduct} onDone={() => { setEditingProduct(null); setAdminTab('products'); }} />}
          {adminTab === 'new-blog' && <AdminBlogForm onDone={() => setAdminTab('blogs')} />}
          {adminTab === 'edit-blog' && <AdminBlogForm initialData={editingBlog} onDone={() => { setEditingBlog(null); setAdminTab('blogs'); }} />}

        </main>
      </div>

    </div>
  );
};
