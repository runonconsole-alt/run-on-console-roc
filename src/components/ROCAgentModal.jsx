import React, { useState, useEffect, useRef } from 'react';
import { useApp } from '../context/AppContext';
import { 
  Bot, MessageSquare, X, Send, Sparkles, Cpu, Monitor, Laptop, 
  Gamepad2, CheckCircle2, AlertTriangle, HelpCircle, HardDrive, 
  ArrowRight, RefreshCw, ShieldCheck, ChevronDown, ExternalLink, 
  ShoppingBag, Star, BookOpen, PenTool, Menu, Plus, Trash2, Edit3, Check
} from 'lucide-react';
import { playClickSound, playPowerUpSound } from '../utils/audioEffects';

export const CompatResultCard = ({ result }) => {
  if (!result) return null;

  const titleText = result.overallResult || (result.verdict ? (result.title || result.verdict) : result.title);

  return (
    <div className="bg-slate-900 border border-slate-800 p-3 rounded-xl space-y-2">
      {titleText && (
        <div className="flex items-center justify-between">
          <span className="text-xs font-extrabold text-amber-400 uppercase tracking-wider">
            {titleText}
          </span>
        </div>
      )}

      {result.summaryText && (
        <p className="text-xs text-slate-300 font-mono leading-relaxed">{result.summaryText}</p>
      )}

      {result.componentResults && typeof result.componentResults === 'object' && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 border-t border-slate-800 text-[11px]">
          {Object.entries(result.componentResults).map(([key, val]) => {
            const statusStr = typeof val === 'object' ? (val.status || (val.pass ? 'Pass' : 'Fail')) : String(val);
            return (
              <div key={key} className="bg-slate-950/70 p-2 rounded-lg border border-slate-800 flex items-center justify-between">
                <span className="font-bold text-slate-400 uppercase text-[10px]">{key}</span>
                <span className="font-mono text-slate-200">{statusStr}</span>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
};

const getScopedStorageKey = (currentUser, conversationId) => {
  const userScope = currentUser?.id || currentUser?.email || 'guest';
  const convScope = conversationId || 'active';
  return `roc_agent_msgs_${userScope}_${convScope}`;
};

const sanitizeComponentResultsForStorage = (compResults) => {
  if (!compResults) return null;
  if (Array.isArray(compResults)) {
    return compResults.slice(0, 20).map(item => {
      if (typeof item !== 'object' || !item) return { name: 'Component', status: String(item) };
      const cleanItem = {
        name: String(item.name || item.component || item.componentName || ''),
        status: String(item.status || item.verdict || (item.pass || item.met ? 'Met' : 'Unmet'))
      };
      if (item.componentResults) {
        cleanItem.componentResults = sanitizeComponentResultsForStorage(item.componentResults);
      }
      return cleanItem;
    });
  }
  if (typeof compResults === 'object') {
    const result = {};
    Object.entries(compResults).slice(0, 20).forEach(([key, val]) => {
      const statusStr = typeof val === 'object' ? String(val.status || (val.pass || val.met ? 'Met' : 'Unmet')) : String(val);
      result[key] = {
        name: key,
        status: statusStr
      };
    });
    return result;
  }
  return null;
};

const sanitizeMessageForStorage = (msg) => {
  if (!msg) return null;
  const safe = {
    id: String(msg.id),
    sender: msg.sender === 'user' ? 'user' : 'bot',
    text: msg.text || ''
  };
  if (msg.type) safe.type = msg.type;
  if (msg.showActions) safe.showActions = true;
  if (Array.isArray(msg.products)) {
    safe.products = msg.products.slice(0, 10).map(p => ({
      id: String(p.id || ''),
      name: String(p.name || p.title || ''),
      price: String(p.price || ''),
      rating: p.rating ? Number(p.rating) : null,
      image: String(p.image || ''),
      slug: String(p.slug || ''),
      category: String(p.category || '')
    }));
  }
  if (Array.isArray(msg.games)) {
    safe.games = msg.games.slice(0, 10).map(g => ({
      id: String(g.id || ''),
      name: String(g.name || g.gameTitle || ''),
      platform: String(g.platform || ''),
      genre: String(g.genre || ''),
      slug: String(g.slug || ''),
      minSpecs: String(g.minSpecs || ''),
      recommendedSpecs: String(g.recommendedSpecs || '')
    }));
  }
  if (Array.isArray(msg.blogs)) {
    safe.blogs = msg.blogs.slice(0, 10).map(b => ({
      id: String(b.id || ''),
      title: String(b.title || ''),
      category: String(b.category || ''),
      slug: String(b.slug || ''),
      excerpt: String(b.excerpt || ''),
      readUrl: String(b.readUrl || '')
    }));
  }
  if (msg.type === 'categories') {
    if (Array.isArray(msg.productCategories)) {
      safe.productCategories = msg.productCategories.slice(0, 20).map(c => ({
        id: String(c.id || ''),
        name: String(c.name || ''),
        count: Number(c.count || 0)
      }));
    }
    if (Array.isArray(msg.gameCategories)) {
      safe.gameCategories = msg.gameCategories.slice(0, 20).map(g => ({
        id: String(g.id || ''),
        name: String(g.name || ''),
        count: Number(g.count || 0)
      }));
    }
  }
  if (msg.type === 'search_results') {
    if (Array.isArray(msg.matchedProducts)) {
      safe.matchedProducts = msg.matchedProducts.slice(0, 10).map(p => ({
        id: String(p.id || ''),
        name: String(p.name || p.title || ''),
        price: String(p.price || ''),
        rating: p.rating ? Number(p.rating) : null,
        image: String(p.image || ''),
        slug: String(p.slug || ''),
        category: String(p.category || '')
      }));
    }
    if (Array.isArray(msg.matchedGames)) {
      safe.matchedGames = msg.matchedGames.slice(0, 10).map(g => ({
        id: String(g.id || ''),
        name: String(g.name || g.gameTitle || ''),
        platform: String(g.platform || ''),
        genre: String(g.genre || ''),
        slug: String(g.slug || '')
      }));
    }
    if (Array.isArray(msg.matchedBlogs)) {
      safe.matchedBlogs = msg.matchedBlogs.slice(0, 10).map(b => ({
        id: String(b.id || ''),
        title: String(b.title || ''),
        category: String(b.category || ''),
        slug: String(b.slug || ''),
        excerpt: String(b.excerpt || ''),
        readUrl: String(b.readUrl || '')
      }));
    }
  }
  if (msg.type === 'compat_result' && msg.result) {
    const res = msg.result;
    safe.result = {
      overallResult: String(res.overallResult || res.title || res.verdict || ''),
      title: String(res.title || ''),
      verdict: String(res.verdict || ''),
      success: Boolean(res.success),
      componentResults: sanitizeComponentResultsForStorage(res.componentResults)
    };
  }
  return safe;
};

const loadSavedSessionMessages = (key) => {
  try {
    const raw = sessionStorage.getItem(key);
    if (raw) {
      const parsed = JSON.parse(raw);
      if (Array.isArray(parsed) && parsed.length > 0) {
        return parsed;
      }
    }
  } catch (e) {}
  return null;
};

const clearIdentityScopedStorage = (userScope) => {
  try {
    const keysToRemove = [];
    for (let i = 0; i < sessionStorage.length; i++) {
      const key = sessionStorage.key(i);
      if (key && (key.startsWith(`roc_agent_msgs_${userScope}_`) || key === `roc_agent_msgs_${userScope}`)) {
        keysToRemove.push(key);
      }
    }
    keysToRemove.forEach(k => sessionStorage.removeItem(k));
  } catch (e) {}
};

export const ROCAgentModal = () => {
  const { 
    currentUser, 
    csrfToken,
    navigateTo, 
    addToCart
  } = useApp();

  const [isOpen, setIsOpen] = useState(false);
  const [inputText, setInputText] = useState('');
  const [isTyping, setIsTyping] = useState(false);
  const [isCreatingNewChat, setIsCreatingNewChat] = useState(false);

  // Quick Action Options List
  const QUICK_ACTIONS = [
    { id: 'products', label: 'Products', icon: ShoppingBag, color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' },
    { id: 'games', label: 'Games', icon: Gamepad2, color: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30' },
    { id: 'compatibility', label: 'Check Compatibility', icon: Cpu, color: 'bg-amber-500/20 text-amber-300 border-amber-500/30' },
    { id: 'categories', label: 'Categories', icon: Monitor, color: 'bg-purple-500/20 text-purple-300 border-purple-500/30' },
    { id: 'blogs', label: 'Blogs', icon: BookOpen, color: 'bg-blue-500/20 text-blue-300 border-blue-500/30' },
    { id: 'write_for_us', label: 'Write for Us', icon: PenTool, color: 'bg-rose-500/20 text-rose-300 border-rose-500/30' },
    { id: 'ask_roc', label: 'Ask ROC', icon: Sparkles, color: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' }
  ];

  // Conversation history and dialog states
  const [activeConversationId, setActiveConversationId] = useState(null);
  const activeConversationIdRef = useRef(activeConversationId);
  useEffect(() => {
    activeConversationIdRef.current = activeConversationId;
  }, [activeConversationId]);

  const conversationCreationPromiseRef = useRef(null);

  const ensureActiveConversationId = async () => {
    if (activeConversationIdRef.current) {
      return activeConversationIdRef.current;
    }
    if (conversationCreationPromiseRef.current) {
      return await conversationCreationPromiseRef.current;
    }

    conversationCreationPromiseRef.current = (async () => {
      try {
        const resp = await fetch('/api/v1/agent/conversations', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({ action: 'new' })
        });
        const data = await resp.json();
        if (data && data.success && data.conversationId) {
          const newId = String(data.conversationId);
          setActiveConversationId(newId);
          activeConversationIdRef.current = newId;
          return newId;
        }
      } catch (e) {} finally {
        conversationCreationPromiseRef.current = null;
      }
      return null;
    })();

    return await conversationCreationPromiseRef.current;
  };

  const [conversationHistory, setConversationHistory] = useState([]);
  const [showHistoryModal, setShowHistoryModal] = useState(false);
  const [showNewChatConfirm, setShowNewChatConfirm] = useState(false);

  const currentStorageKey = getScopedStorageKey(currentUser, activeConversationId);

  // Chat Messages State initialized from scoped sessionStorage if available
  const [messages, setMessages] = useState(() => {
    const saved = loadSavedSessionMessages(currentStorageKey);
    if (saved) return saved;
    return [
      {
        id: 'welcome-1',
        sender: 'bot',
        text: "Hi, I’m ROC Agent. I’m built to make gaming and shopping easier for you. How can I help?",
        showActions: true
      }
    ];
  });

  // Sync messages to scoped sessionStorage whenever state updates
  useEffect(() => {
    try {
      const key = getScopedStorageKey(currentUser, activeConversationId);
      const safeMsgs = messages.map(sanitizeMessageForStorage).filter(Boolean);
      sessionStorage.setItem(key, JSON.stringify(safeMsgs));
    } catch (e) {}
  }, [messages, currentUser, activeConversationId]);

  // Account switch / Logout cleanup effect
  const prevUserScopeRef = useRef(currentUser?.id || currentUser?.email || 'guest');
  useEffect(() => {
    const currentUserScope = currentUser?.id || currentUser?.email || 'guest';
    if (prevUserScopeRef.current && prevUserScopeRef.current !== currentUserScope) {
      clearIdentityScopedStorage(prevUserScopeRef.current);
      prevUserScopeRef.current = currentUserScope;
    }

    setActiveConversationId(null);
    activeConversationIdRef.current = null;
    setActiveFlow(null);
    setSelectedGame(null);
    setManualSpecs({ cpu: '', gpu: '', ramGb: '', operatingSystem: '' });
    setMessages([
      {
        id: 'welcome-1',
        sender: 'bot',
        text: "Hi, I’m ROC Agent. I’m built to make gaming and shopping easier for you. How can I help?",
        showActions: true
      }
    ]);
  }, [currentUser?.id, currentUser?.email]);

  // Dynamic Flow Sub-States: null | 'compatibility' | 'devices' | 'write_for_us'
  const [activeFlow, setActiveFlow] = useState(null);

  // Compatibility Form State
  const [selectedGame, setSelectedGame] = useState(null);
  const [manualSpecs, setManualSpecs] = useState({
    cpu: '',
    gpu: '',
    ramGb: '',
    operatingSystem: ''
  });

  // Saved Devices State
  const [userDevices, setUserDevices] = useState([]);
  const [showDeviceForm, setShowDeviceForm] = useState(false);
  const [editingDeviceId, setEditingDeviceId] = useState(null);
  const [deviceForm, setDeviceForm] = useState({
    deviceName: '',
    deviceType: '',
    cpu: '',
    gpu: '',
    ramGb: '',
    storage: '',
    operatingSystem: '',
    isDefault: false
  });

  const resetDeviceForm = () => {
    setEditingDeviceId(null);
    setDeviceForm({
      deviceName: '',
      deviceType: '',
      cpu: '',
      gpu: '',
      ramGb: '',
      storage: '',
      operatingSystem: '',
      isDefault: false
    });
  };

  // Write for Us Form State
  const [proposal, setProposal] = useState({
    name: currentUser?.name || '',
    email: currentUser?.email || '',
    proposedTopic: '',
    shortPitch: '',
    experience: 'Gaming Hardware Writer',
    portfolioUrl: ''
  });
  const [proposalSubmitted, setProposalSubmitted] = useState(false);

  const chatEndRef = useRef(null);

  useEffect(() => {
    chatEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isTyping, activeFlow]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isOpen) {
        setIsOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isOpen]);

  // Load conversation history and user devices when modal opens
  useEffect(() => {
    if (isOpen) {
      loadConversationHistory();
      if (currentUser) fetchUserDevices();
    }
  }, [isOpen, currentUser]);

  const loadConversationHistory = async (targetConvId = null) => {
    try {
      const url = targetConvId 
        ? `/api/v1/agent/conversations?conversation_id=${encodeURIComponent(targetConvId)}`
        : '/api/v1/agent/conversations';
      const resp = await fetch(url);
      const data = await resp.json();
      const welcomeMsg = {
        id: 'welcome-1',
        sender: 'bot',
        text: "Hi, I’m ROC Agent. I’m built to make gaming and shopping easier for you. How can I help?",
        showActions: true
      };

      if (data && data.success) {
        if (data.conversationId) {
          const convIdStr = String(data.conversationId);
          setActiveConversationId(convIdStr);
          activeConversationIdRef.current = convIdStr;
        }

        if (Array.isArray(data.messages) && data.messages.length > 0) {
          // Replace active message list COMPLETELY with loaded conversation
          const loadedMsgs = data.messages.map(m => {
            const baseMsg = {
              id: String(m.id),
              sender: m.role === 'assistant' ? 'bot' : 'user',
              text: m.message
            };
            if (m.metadata && typeof m.metadata === 'object') {
              return { ...baseMsg, ...m.metadata };
            }
            return baseMsg;
          });

          setMessages([welcomeMsg, ...loadedMsgs]);
        } else {
          // Selecting an empty conversation must reset the UI to ONLY the welcome message
          setMessages([welcomeMsg]);
        }
      }
    } catch (e) {}
  };

  const saveQueueRef = useRef(Promise.resolve());

  const saveMessageToHistory = (role, messageText, intent = '', metadata = null) => {
    saveQueueRef.current = saveQueueRef.current.then(async () => {
      try {
        let convId = activeConversationIdRef.current;
        if (!convId) {
          convId = await ensureActiveConversationId();
        }

        const body = { role, message: messageText, intent };
        if (convId) body.conversationId = convId;
        if (metadata && typeof metadata === 'object') body.metadata = metadata;

        const resp = await fetch('/api/v1/agent/conversations', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify(body)
        });
        const data = await resp.json();
        if (data && data.success && data.conversationId) {
          const updatedId = String(data.conversationId);
          setActiveConversationId(updatedId);
          activeConversationIdRef.current = updatedId;
        }
      } catch (e) {}
    });

    return saveQueueRef.current;
  };

  const fetchConversationHistoryList = async () => {
    if (!currentUser) return;
    try {
      const resp = await fetch('/api/v1/agent/conversations?action=list');
      const data = await resp.json();
      if (data && data.success && Array.isArray(data.conversations)) {
        setConversationHistory(data.conversations);
      }
    } catch (e) {}
  };

  const handleOpenHistoryModal = () => {
    playClickSound();
    fetchConversationHistoryList();
    setShowHistoryModal(true);
  };

  const handleSelectHistoryConversation = (convId) => {
    playClickSound();
    setShowHistoryModal(false);
    loadConversationHistory(convId);
  };

  const handleConfirmNewChat = async () => {
    playClickSound();

    // Disable input immediately while creating new chat
    setIsCreatingNewChat(true);

    try {
      // Await saveQueueRef.current so any pending message saves complete first
      await saveQueueRef.current;

      const key = getScopedStorageKey(currentUser, activeConversationIdRef.current);
      sessionStorage.removeItem(key);
    } catch (e) {}

    setActiveFlow(null);
    setSelectedGame(null);
    setManualSpecs({ cpu: '', gpu: '', ramGb: '', operatingSystem: '' });
    setIsTyping(false);
    setProposalSubmitted(false);
    setShowNewChatConfirm(false);
    setShowHistoryModal(false);

    const initialWelcome = [
      {
        id: 'welcome-1',
        sender: 'bot',
        text: "Hi, I’m ROC Agent. I’m built to make gaming and shopping easier for you. How can I help?",
        showActions: true
      }
    ];
    setMessages(initialWelcome);

    try {
      // Create new backend conversation
      const resp = await fetch('/api/v1/agent/conversations', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ action: 'new' })
      });
      const data = await resp.json();
      if (data && data.success && data.conversationId) {
        const newId = String(data.conversationId);
        // Immediately update BOTH activeConversationId state and activeConversationIdRef before enabling input
        setActiveConversationId(newId);
        activeConversationIdRef.current = newId;
      } else {
        setActiveConversationId(null);
        activeConversationIdRef.current = null;
      }
    } catch (e) {
      setActiveConversationId(null);
      activeConversationIdRef.current = null;
    } finally {
      // Enable input only after state and ref are updated
      setIsCreatingNewChat(false);
    }
  };

  // Global trigger event listener
  useEffect(() => {
    const handleOpenBot = (e) => {
      setIsOpen(true);
      playPowerUpSound();
      if (e.detail?.query) {
        setTimeout(() => {
          handleUserSend(e.detail.query);
        }, 300);
      }
    };
    window.addEventListener('open-ai-bot', handleOpenBot);
    window.addEventListener('open-roc-agent', handleOpenBot);
    return () => {
      window.removeEventListener('open-ai-bot', handleOpenBot);
      window.removeEventListener('open-roc-agent', handleOpenBot);
    };
  }, []);

  const fetchUserDevices = async () => {
    try {
      const resp = await fetch('/api/v1/agent/devices');
      const data = await resp.json();
      if (data && data.success && Array.isArray(data.devices)) {
        setUserDevices(data.devices);
      }
    } catch (e) {}
  };

  const addBotMessage = (text, extra = {}) => {
    saveMessageToHistory('assistant', text, extra.type || '', extra);
    setMessages(prev => [
      ...prev,
      {
        id: 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4),
        sender: 'bot',
        text,
        ...extra
      }
    ]);
  };

  const addUserMessage = (text) => {
    saveMessageToHistory('user', text);
    setMessages(prev => [
      ...prev,
      {
        id: 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4),
        sender: 'user',
        text
      }
    ]);
  };

  // Reopen Options Menu
  const handleReopenMenu = () => {
    playClickSound();
    setActiveFlow(null);
    setMessages(prev => [
      ...prev,
      {
        id: 'menu-' + Date.now(),
        sender: 'bot',
        text: "Here are the main options. How can I assist you?",
        showActions: true
      }
    ]);
  };

  // 1. PRODUCTS FLOW (Strict Backend Data Only)
  const handleProductsAction = async (categoryFilter = null) => {
    const rawFilter = typeof categoryFilter === 'string' ? categoryFilter : (categoryFilter?.name || null);
    playClickSound();
    addUserMessage(rawFilter ? `Show products in category "${rawFilter}"` : "Show me recommended products");
    setIsTyping(true);

    try {
      const url = rawFilter 
        ? `/api/v1/agent/products?category=${encodeURIComponent(rawFilter)}&limit=5`
        : '/api/v1/agent/products?limit=3';

      const resp = await fetch(url);
      if (!resp.ok) {
        setIsTyping(false);
        addBotMessage("Unable to connect to ROC Agent product service. Please check your connection and try again.");
        return;
      }
      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success && Array.isArray(data.products) && data.products.length > 0) {
        addBotMessage(`Here are products ${rawFilter ? `in "${rawFilter}"` : 'from Run On Console'}:`, {
          type: 'products',
          products: data.products
        });
      } else {
        addBotMessage(`No products found ${rawFilter ? `in category "${rawFilter}"` : 'in database'}.`);
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Unable to connect to ROC Agent product service. Please check your connection and try again.");
    }
  };

  // 2. GAMES FLOW (Strict Backend Data Only)
  const handleGamesAction = async (genreFilter = null, emitUserMsg = true) => {
    const rawFilter = typeof genreFilter === 'string' ? genreFilter : (genreFilter?.name || genreFilter?.genre || null);
    playClickSound();
    if (emitUserMsg) addUserMessage(rawFilter ? `Show games in genre "${rawFilter}"` : "Browse games database");
    setIsTyping(true);

    try {
      const url = rawFilter 
        ? `/api/v1/agent/games?q=${encodeURIComponent(rawFilter)}`
        : '/api/v1/agent/games';

      const resp = await fetch(url);
      if (!resp.ok) {
        setIsTyping(false);
        addBotMessage("Unable to connect to ROC Agent game database. Please check your connection and try again.");
        return;
      }
      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success && Array.isArray(data.games) && data.games.length > 0) {
        addBotMessage(`Select a game ${rawFilter ? `matching "${rawFilter}"` : ''} to check hardware requirements:`, {
          type: 'games',
          games: data.games
        });
      } else {
        addBotMessage(rawFilter ? `No games found matching "${rawFilter}".` : 'No games found in database.');
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Unable to connect to ROC Agent game database. Please check your connection and try again.");
    }
  };

  // 3. CATEGORIES FLOW (Strict Backend Data Only)
  const handleCategoriesAction = async () => {
    playClickSound();
    addUserMessage("Show product and game categories");
    setIsTyping(true);

    try {
      const resp = await fetch('/api/v1/agent/categories');
      if (!resp.ok) {
        setIsTyping(false);
        addBotMessage("Unable to connect to ROC Agent category service. Please check your connection and try again.");
        return;
      }
      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success && ((data.productCategories && data.productCategories.length > 0) || (data.gameCategories && data.gameCategories.length > 0))) {
        addBotMessage("Select any category below to filter products or games directly in chat:", {
          type: 'categories',
          productCategories: data.productCategories || [],
          gameCategories: data.gameCategories || []
        });
      } else {
        addBotMessage("No category data available.");
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Unable to connect to ROC Agent category service. Please check your connection and try again.");
    }
  };

  // 4. BLOGS FLOW (Strict Backend Data Only)
  const handleBlogsAction = async () => {
    playClickSound();
    addUserMessage("Show latest hardware guides & blogs");
    setIsTyping(true);

    try {
      const resp = await fetch('/api/v1/agent/blogs?limit=4');
      if (!resp.ok) {
        setIsTyping(false);
        addBotMessage("Unable to connect to ROC Agent blog service. Please check your connection and try again.");
        return;
      }
      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success && Array.isArray(data.blogs) && data.blogs.length > 0) {
        addBotMessage("Here are recent published Run On Console hardware articles and PC guides:", {
          type: 'blogs',
          blogs: data.blogs
        });
      } else {
        addBotMessage("No published blog articles available.");
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Unable to connect to ROC Agent blog service. Please check your connection and try again.");
    }
  };

  // 5. COMPATIBILITY CHECKER FLOW (Strict Backend Evaluation Only)
  const handleStartCompatibility = (game = null, emitUserMsg = true) => {
    playClickSound();
    if (game) {
      setSelectedGame(game);
      if (emitUserMsg) addUserMessage(`Check compatibility for ${game.name}`);
    } else {
      if (emitUserMsg) addUserMessage("Check PC Game Compatibility");
    }
    setActiveFlow('compatibility');
  };

  const handleRunCompatibilityCheck = async (e) => {
    e.preventDefault();
    if (!selectedGame) return;
    if (!manualSpecs.cpu.trim() || !manualSpecs.gpu.trim() || !manualSpecs.operatingSystem.trim()) {
      addBotMessage("Please specify your exact CPU, GPU, and Operating System.");
      return;
    }

    playClickSound();
    setIsTyping(true);

    try {
      const resp = await fetch('/api/v1/agent/check-compatibility', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          gameId: selectedGame.id,
          cpu: manualSpecs.cpu,
          gpu: manualSpecs.gpu,
          ramGb: manualSpecs.ramGb,
          operatingSystem: manualSpecs.operatingSystem
        })
      });

      if (!resp.ok) {
        setIsTyping(false);
        addBotMessage("Unable to Determine - Hardware evaluation service returned error or is unavailable.", {
          type: 'compat_result',
          result: {
            success: false,
            verdict: 'UNABLE_TO_DETERMINE',
            title: 'Unable to Determine',
            summaryText: 'Unable to connect to hardware compatibility evaluation server.'
          }
        });
        return;
      }

      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success) {
        addBotMessage(data.summaryText, {
          type: 'compat_result',
          result: data
        });
      } else {
        addBotMessage(data.error || "Unable to Determine - Hardware evaluation incomplete.", {
          type: 'compat_result',
          result: {
            success: false,
            verdict: 'UNABLE_TO_DETERMINE',
            title: 'Unable to Determine',
            summaryText: data.error || 'Unable to determine hardware compatibility.'
          }
        });
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Unable to Determine - Network error connecting to compatibility evaluation service.", {
        type: 'compat_result',
        result: {
          success: false,
          verdict: 'UNABLE_TO_DETERMINE',
          title: 'Unable to Determine',
          summaryText: 'Network connection failure. Unable to evaluate hardware compatibility.'
        }
      });
    }
  };

  // 6. SAVED DEVICES CRUD HANDLERS
  const handleSaveDeviceSubmit = async (e) => {
    e.preventDefault();
    if (!currentUser) return;
    playClickSound();
    setIsTyping(true);

    try {
      const isEdit = Boolean(editingDeviceId);
      const resp = await fetch('/api/v1/agent/devices', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          action: isEdit ? 'edit' : 'add',
          deviceId: editingDeviceId,
          ...deviceForm
        })
      });

      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success) {
        resetDeviceForm();
        setShowDeviceForm(false);
        fetchUserDevices();
        addBotMessage(isEdit ? "Device updated successfully!" : "New gaming device saved to your account!");
      } else {
        addBotMessage(data.error || "Failed to save device.");
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Error communicating with device server.");
    }
  };

  const handleDeleteDevice = async (deviceId) => {
    if (!currentUser) return;
    playClickSound();
    try {
      const resp = await fetch('/api/v1/agent/devices', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ action: 'delete', deviceId: String(deviceId) })
      });
      const data = await resp.json();
      if (data && data.success) {
        fetchUserDevices();
      }
    } catch (e) {}
  };

  const handleSetDefaultDevice = async (deviceId) => {
    if (!currentUser) return;
    playClickSound();
    try {
      const resp = await fetch('/api/v1/agent/devices', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ action: 'set_default', deviceId: String(deviceId) })
      });
      const data = await resp.json();
      if (data && data.success) {
        fetchUserDevices();
      }
    } catch (e) {}
  };

  const handleStartEditDevice = (device) => {
    playClickSound();
    setEditingDeviceId(device.id);
    setDeviceForm({
      deviceName: device.device_name || device.deviceName || '',
      deviceType: device.device_type || device.deviceType || '',
      cpu: device.cpu || '',
      gpu: device.gpu || '',
      ramGb: device.ram_gb ?? device.ramGb ?? '',
      storage: device.storage || '',
      operatingSystem: device.operating_system || device.operatingSystem || '',
      isDefault: Boolean(device.is_default || device.isDefault)
    });
    setShowDeviceForm(true);
  };

  const handleUseDeviceForCompat = (device) => {
    playClickSound();
    const cpu = device.cpu || '';
    const gpu = device.gpu || '';
    const ramGb = device.ram_gb ?? device.ramGb ?? '';
    const operatingSystem = device.operating_system || device.operatingSystem || '';

    setManualSpecs({
      cpu,
      gpu,
      ramGb,
      operatingSystem
    });
    setActiveFlow('compatibility');

    const missingFields = [];
    if (!cpu) missingFields.push('CPU');
    if (!gpu) missingFields.push('GPU');
    if (ramGb === '' || ramGb === null || ramGb === undefined) missingFields.push('RAM');
    if (!operatingSystem) missingFields.push('Operating System');

    const deviceName = device.device_name || device.deviceName || 'Saved Device';

    if (missingFields.length > 0) {
      addBotMessage(`Loaded saved specs from "${deviceName}". Missing required specifications: ${missingFields.join(', ')}. Please complete these fields below.`);
    } else {
      addBotMessage(`Loaded specs from "${deviceName}": ${cpu} • ${gpu} • ${ramGb}GB RAM • ${operatingSystem}.`);
    }
  };

  // 7. WRITE FOR US FLOW
  const handleWriteForUsAction = (emitUserMsg = true) => {
    playClickSound();
    if (emitUserMsg) {
      addUserMessage("I want to write for Run On Console");
    }
    setActiveFlow('write_for_us');
    setProposalSubmitted(false);
  };

  const handleSubmitProposal = async (e) => {
    e.preventDefault();
    playClickSound();
    setIsTyping(true);

    try {
      const resp = await fetch('/api/v1/agent/write-for-us', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(proposal)
      });

      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success) {
        setProposalSubmitted(true);
        addBotMessage(data.message, {
          type: 'proposal_success'
        });
      } else {
        addBotMessage(data.error || "Failed to submit proposal.");
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Error submitting article proposal.");
    }
  };

  // 8. NATURAL LANGUAGE ROUTER & BACKEND SEARCH INTERFACE
  const handleUserSend = async (customText = null) => {
    const query = (customText || inputText).trim();
    if (!query) return;
    if (!customText) setInputText('');

    playClickSound();
    addUserMessage(query);
    setIsTyping(true);

    // 1. Strict Harmless Greetings & Help Only (Using word boundaries)
    const pureGreetingRegex = /^\s*(hi|hello|hey|salam|kasay ho|kaise ho|how are you|who are you|help)\s*$/i;
    const isGreetingWord = /\b(hi|hello|hey|salam|kasay ho|kaise ho|how are you|who are you|help)\b/i.test(query);
    const containsDomainTerms = /\b(pc|game|specs|run|cyberpunk|gta|gpu|cpu|ram|mouse|mice|keyboard|product|blog|guide|price)\b/i.test(query);

    if ((pureGreetingRegex.test(query) || isGreetingWord) && !containsDomainTerms) {
      setIsTyping(false);
      addBotMessage("Hi, I’m ROC Agent! How can I help you with gaming hardware, PC specs, or game compatibility?", {
        showActions: true
      });
      return;
    }

    // 2. Query Backend Search Service (No canned recommendations or fake specs)
    try {
      const resp = await fetch(`/api/v1/agent/search?q=${encodeURIComponent(query)}`);
      if (!resp.ok) {
        setIsTyping(false);
        addBotMessage("Unable to connect to ROC Agent server. Please check your connection and try again.");
        return;
      }

      const data = await resp.json();
      setIsTyping(false);

      if (data && data.success) {
        const intent = data.intent;

        if (intent === 'products_search' && Array.isArray(data.matchedProducts) && data.matchedProducts.length > 0) {
          addBotMessage(`Here are products matching "${query}":`, {
            type: 'products',
            products: data.matchedProducts
          });
        } else if (intent === 'compatibility_check') {
          const matchedGames = data.matchedGames || [];
          if (matchedGames.length === 1) {
            handleStartCompatibility(matchedGames[0], false);
          } else if (matchedGames.length > 1) {
            addBotMessage(`Select a game below to check compatibility for "${query}":`, {
              type: 'games',
              games: matchedGames
            });
          } else {
            const termToSearch = data.cleanTerm || query;
            handleGamesAction(termToSearch, false);
          }
        } else if (intent === 'blog_search' && Array.isArray(data.matchedBlogs) && data.matchedBlogs.length > 0) {
          addBotMessage(`Here are hardware guides matching "${query}":`, {
            type: 'blogs',
            blogs: data.matchedBlogs
          });
        } else if (intent === 'write_for_us') {
          handleWriteForUsAction(false);
        } else if ((Array.isArray(data.matchedProducts) && data.matchedProducts.length > 0) ||
                   (Array.isArray(data.matchedGames) && data.matchedGames.length > 0) ||
                   (Array.isArray(data.matchedBlogs) && data.matchedBlogs.length > 0)) {
          addBotMessage(`Found matching entries for "${query}":`, {
            type: 'search_results',
            matchedProducts: data.matchedProducts || [],
            matchedGames: data.matchedGames || [],
            matchedBlogs: data.matchedBlogs || []
          });
        } else {
          addBotMessage(`No matching data found for "${query}".`);
        }
      } else {
        addBotMessage(data?.error || "Unable to connect to ROC Agent server. Please check your connection and try again.");
      }
    } catch (err) {
      setIsTyping(false);
      addBotMessage("Unable to connect to ROC Agent server. Please check your connection and try again.");
    }
  };

  const handleQuickAction = (actionId) => {
    if (actionId === 'products') handleProductsAction();
    else if (actionId === 'games') handleGamesAction();
    else if (actionId === 'compatibility') handleStartCompatibility();
    else if (actionId === 'categories') handleCategoriesAction();
    else if (actionId === 'blogs') handleBlogsAction();
    else if (actionId === 'write_for_us') handleWriteForUsAction();
    else if (actionId === 'ask_roc') {
      addUserMessage("Ask ROC");
      addBotMessage("Type any question about gaming hardware, mice, keyboards, or PC game compatibility!");
    }
  };

  return (
    <>
      {/* Floating Trigger Button */}
      {!isOpen && (
        <button
          type="button"
          onClick={() => {
            setIsOpen(true);
            playPowerUpSound();
          }}
          className="fixed bottom-6 right-6 z-40 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 p-4 rounded-full shadow-2xl transition-all duration-300 hover:scale-110 flex items-center justify-center border-2 border-emerald-300/40 group cursor-pointer"
          aria-label="Open ROC Agent Assistant"
        >
          <Sparkles className="w-6 h-6 text-slate-950 animate-pulse" />
          <span className="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300 ease-in-out whitespace-nowrap text-xs font-extrabold ml-0 group-hover:ml-2">
            ROC Agent
          </span>
        </button>
      )}

      {/* Main Agent Modal Window */}
      {isOpen && (
        <div 
          onClick={() => setIsOpen(false)}
          className="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-sm flex items-end sm:items-center justify-center sm:justify-end p-0 sm:p-6 transition-all duration-300 animate-fadeIn"
        >
          <div 
            onClick={(e) => e.stopPropagation()}
            className="bg-slate-900 border border-slate-800/90 w-full sm:w-[480px] md:w-[540px] h-[88vh] sm:h-[680px] sm:max-h-[85vh] rounded-t-3xl sm:rounded-3xl flex flex-col shadow-2xl overflow-hidden relative border-emerald-500/20 sm:mr-2"
          >
            {/* Mobile Drag Indicator */}
            <div className="w-12 h-1.5 bg-slate-700/60 rounded-full mx-auto my-2 sm:hidden shrink-0" />
            
            {/* Header Bar */}
            <div className="bg-slate-950/90 px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-slate-950 shadow-md">
                  <Sparkles className="w-5 h-5 fill-current" />
                </div>
                <div>
                  <h3 className="font-extrabold text-slate-100 text-base flex items-center gap-2">
                    <span>ROC Agent</span>
                    <span className="text-[10px] bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/30">
                      Phase 1 Verified
                    </span>
                  </h3>
                  <p className="text-[11px] text-slate-400">Gaming, Hardware Discovery & Compatibility Assistant</p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                {/* New Chat Button */}
                <button
                  type="button"
                  onClick={() => {
                    playClickSound();
                    setShowNewChatConfirm(true);
                  }}
                  className="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition-colors border border-slate-700 cursor-pointer"
                  title="Start New Chat"
                >
                  <Plus className="w-3.5 h-3.5 text-emerald-400" />
                  <span className="hidden sm:inline">New Chat</span>
                </button>

                {currentUser && (
                  <>
                    <button
                      type="button"
                      onClick={handleOpenHistoryModal}
                      className="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition-colors border border-slate-700 cursor-pointer"
                      title="Recent Conversations History"
                    >
                      <MessageSquare className="w-3.5 h-3.5 text-blue-400" />
                      <span className="hidden sm:inline">History</span>
                    </button>

                    <button
                      type="button"
                      onClick={() => setActiveFlow(activeFlow === 'devices' ? null : 'devices')}
                      className="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition-colors border border-slate-700 cursor-pointer"
                      title="Manage Saved Devices"
                    >
                      <Laptop className="w-3.5 h-3.5 text-cyan-400" />
                      <span className="hidden sm:inline">Devices ({userDevices.length})</span>
                    </button>
                  </>
                )}

                {/* Persistent Menu Button */}
                <button
                  type="button"
                  onClick={handleReopenMenu}
                  className="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition-colors border border-slate-700 cursor-pointer"
                  title="Reopen Options Menu"
                >
                  <Menu className="w-3.5 h-3.5 text-emerald-400" />
                  <span>Menu</span>
                </button>

                <button
                  type="button"
                  onClick={() => setIsOpen(false)}
                  className="p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800 transition-colors cursor-pointer"
                  aria-label="Close ROC Agent"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* New Chat Confirmation Dialog Overlay */}
            {showNewChatConfirm && (
              <div className="absolute inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
                <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 max-w-sm w-full space-y-4 shadow-2xl animate-fadeIn">
                  <div className="flex items-center gap-3 text-amber-400 font-bold text-sm">
                    <AlertTriangle className="w-5 h-5 shrink-0" />
                    <span>Start New Conversation?</span>
                  </div>
                  <p className="text-xs text-slate-300 leading-relaxed">
                    Are you sure you want to start a new chat? Your current conversation will be saved in your chat history.
                  </p>
                  <div className="flex justify-end gap-2 pt-2">
                    <button
                      type="button"
                      onClick={() => setShowNewChatConfirm(false)}
                      className="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition-colors cursor-pointer"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={handleConfirmNewChat}
                      className="px-4 py-2 rounded-xl text-xs font-extrabold bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition-colors shadow-md cursor-pointer"
                    >
                      Confirm New Chat
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* Recent Conversations History Overlay */}
            {showHistoryModal && (
              <div className="absolute inset-0 z-50 bg-slate-950/95 backdrop-blur-sm flex flex-col p-4 animate-fadeIn">
                <div className="flex items-center justify-between border-b border-slate-800 pb-3 mb-4 shrink-0">
                  <div className="flex items-center gap-2 font-extrabold text-sm text-slate-100">
                    <MessageSquare className="w-4 h-4 text-blue-400" />
                    <span>Recent Conversations</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setShowHistoryModal(false)}
                    className="p-1 text-slate-400 hover:text-white transition-colors cursor-pointer"
                  >
                    <X className="w-5 h-5" />
                  </button>
                </div>

                <div className="flex-1 overflow-y-auto space-y-2 pr-1">
                  {conversationHistory.length === 0 ? (
                    <div className="text-center text-xs text-slate-400 py-10">
                      No previous conversations found.
                    </div>
                  ) : (
                    conversationHistory.map((c) => (
                      <button
                        key={c.id}
                        type="button"
                        onClick={() => handleSelectHistoryConversation(c.id)}
                        className={`w-full p-3 rounded-xl border text-left transition-all cursor-pointer flex items-center justify-between gap-3 ${
                          activeConversationId === c.id
                            ? 'bg-slate-800 border-emerald-500/50 text-white'
                            : 'bg-slate-900 border-slate-800 hover:border-slate-700 text-slate-300'
                        }`}
                      >
                        <div className="min-w-0 flex-1">
                          <div className="font-bold text-xs truncate text-slate-200">{c.title}</div>
                          <div className="text-[10px] text-slate-400 mt-1">{c.updatedAt ? new Date(c.updatedAt).toLocaleString() : ''}</div>
                        </div>
                        {activeConversationId === c.id && (
                          <span className="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded-full border border-emerald-500/40 shrink-0 font-bold">
                            Active
                          </span>
                        )}
                      </button>
                    ))
                  )}
                </div>
              </div>
            )}

            {/* Chat Conversation Body */}
            <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 text-slate-100">
              {messages.map((msg) => (
                <div
                  key={msg.id}
                  className={`flex gap-3 ${msg.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  {msg.sender === 'bot' && (
                    <div className="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 flex items-center justify-center shrink-0 mt-1">
                      <Bot className="w-4 h-4" />
                    </div>
                  )}

                  <div className={`max-w-[85%] space-y-3 ${msg.sender === 'user' ? 'bg-emerald-600 text-slate-950 font-medium px-4 py-3 rounded-2xl rounded-tr-none' : 'bg-slate-950/80 border border-slate-800 p-4 rounded-2xl rounded-tl-none'}`}>
                    <p className="text-sm whitespace-pre-line leading-relaxed">{msg.text}</p>

                    {/* Quick Action Buttons */}
                    {msg.showActions && (
                      <div className="flex flex-wrap gap-2 pt-2 border-t border-slate-800">
                        {QUICK_ACTIONS.map((act) => {
                          const Icon = act.icon;
                          return (
                            <button
                              key={act.id}
                              type="button"
                              onClick={() => handleQuickAction(act.id)}
                              className={`px-3 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 border transition-all hover:scale-105 cursor-pointer ${act.color}`}
                            >
                              <Icon className="w-3.5 h-3.5" />
                              <span>{act.label}</span>
                            </button>
                          );
                        })}
                      </div>
                    )}

                    {/* Product Cards Flow */}
                    {msg.type === 'products' && Array.isArray(msg.products) && (
                      <div className="space-y-3 pt-2">
                        <div className="grid grid-cols-1 gap-3">
                          {msg.products.map((prod) => (
                            <div key={String(prod.id)} className="bg-slate-900 border border-slate-800 p-3 rounded-xl flex gap-3 items-center">
                              {prod.image && <img src={prod.image} alt={prod.name} className="w-16 h-16 rounded-lg object-cover bg-slate-950 shrink-0" />}
                              <div className="flex-1 min-w-0">
                                <h5 className="font-bold text-xs text-white truncate">{prod.name}</h5>
                                <div className="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                  {prod.price && <span className="font-bold text-emerald-400">{prod.price}</span>}
                                  {prod.rating && (
                                    <span className="flex items-center gap-0.5 text-amber-400">
                                      <Star className="w-3 h-3 fill-current" /> {prod.rating}
                                    </span>
                                  )}
                                </div>
                                <div className="flex gap-2 mt-2">
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setIsOpen(false);
                                      navigateTo('products', prod.slug || String(prod.id));
                                    }}
                                    className="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-white text-[11px] font-bold rounded-lg transition-colors inline-flex items-center gap-1 cursor-pointer"
                                  >
                                    <span>View Product</span>
                                    <ExternalLink className="w-3 h-3" />
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => {
                                      if (addToCart) addToCart(prod);
                                    }}
                                    className="px-3 py-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-[11px] font-extrabold rounded-lg transition-colors cursor-pointer"
                                  >
                                    Add to Cart
                                  </button>
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                        <button
                          type="button"
                          onClick={() => {
                            setIsOpen(false);
                            navigateTo('products');
                          }}
                          className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition-colors flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                          <span>Explore More Products</span>
                          <ArrowRight className="w-3.5 h-3.5 text-emerald-400" />
                        </button>
                      </div>
                    )}

                    {/* Games List Flow */}
                    {msg.type === 'games' && Array.isArray(msg.games) && (
                      <div className="grid grid-cols-1 gap-2 pt-2">
                        {msg.games.map((g) => (
                          <div key={String(g.id)} className="bg-slate-900 border border-slate-800 p-2.5 rounded-xl flex items-center justify-between gap-3">
                            <div>
                              <div className="font-bold text-xs text-white">{g.name}</div>
                              <div className="text-[10px] text-slate-400">{g.platform} • {g.genre}</div>
                            </div>
                            <button
                              type="button"
                              onClick={() => handleStartCompatibility(g)}
                              className="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold rounded-lg transition-colors shrink-0 flex items-center gap-1 cursor-pointer"
                            >
                              <Cpu className="w-3 h-3" />
                              <span>Check Specs</span>
                            </button>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Both Product AND Game Categories Flow */}
                    {msg.type === 'categories' && (
                      <div className="space-y-4 pt-2">
                        {/* Product Categories */}
                        {msg.productCategories?.length > 0 && (
                          <div className="space-y-2">
                            <div className="text-xs font-bold uppercase text-emerald-400 tracking-wider flex items-center gap-1.5">
                              <ShoppingBag className="w-3.5 h-3.5" />
                              <span>Product Categories</span>
                            </div>
                            <div className="flex flex-wrap gap-2">
                              {msg.productCategories.map((c) => (
                                <button
                                  key={c.id}
                                  type="button"
                                  onClick={() => handleProductsAction(c.name)}
                                  className="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-emerald-500 text-xs font-bold text-slate-200 transition-colors cursor-pointer"
                                >
                                  {c.name} ({c.count})
                                </button>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Game Categories */}
                        {msg.gameCategories?.length > 0 && (
                          <div className="space-y-2">
                            <div className="text-xs font-bold uppercase text-cyan-400 tracking-wider flex items-center gap-1.5">
                              <Gamepad2 className="w-3.5 h-3.5" />
                              <span>Game Genres & Categories</span>
                            </div>
                            <div className="flex flex-wrap gap-2">
                              {msg.gameCategories.map((g) => (
                                <button
                                  key={g.id}
                                  type="button"
                                  onClick={() => handleGamesAction(g.name)}
                                  className="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-cyan-500 text-xs font-bold text-slate-200 transition-colors cursor-pointer"
                                >
                                  {g.name} ({g.count})
                                </button>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    )}

                    {/* Blogs Flow */}
                    {msg.type === 'blogs' && Array.isArray(msg.blogs) && (
                      <div className="grid grid-cols-1 gap-2.5 pt-2">
                        {msg.blogs.map((b) => (
                          <div key={String(b.id)} className="bg-slate-900 border border-slate-800 p-3 rounded-xl">
                            <div className="text-[10px] font-bold text-blue-400 uppercase tracking-wider">{b.category}</div>
                            <h5 className="font-bold text-xs text-white mt-0.5">{b.title}</h5>
                            {b.excerpt && <p className="text-[11px] text-slate-400 mt-1 line-clamp-2">{b.excerpt}</p>}
                            <a
                              href={b.readUrl || `/blogs/${b.slug || b.id}/`}
                              target="_blank"
                              rel="noreferrer"
                              className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-400 mt-2 hover:underline"
                            >
                              <span>Read Article</span>
                              <ExternalLink className="w-3 h-3" />
                            </a>
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Mixed Search Results Flow */}
                    {msg.type === 'search_results' && (
                      <div className="space-y-3 pt-2">
                        {/* Matched Products */}
                        {Array.isArray(msg.matchedProducts) && msg.matchedProducts.length > 0 && (
                          <div className="space-y-2">
                            <div className="text-xs font-bold uppercase text-emerald-400 tracking-wider flex items-center gap-1.5">
                              <ShoppingBag className="w-3.5 h-3.5" />
                              <span>Matching Products</span>
                            </div>
                            <div className="grid grid-cols-1 gap-2">
                              {msg.matchedProducts.map((p) => (
                                <div key={String(p.id)} className="bg-slate-900 border border-slate-800 p-2.5 rounded-xl flex items-center gap-3">
                                  {p.image && <img src={p.image} alt={p.name || p.title} className="w-12 h-12 object-cover rounded-lg bg-slate-950 border border-slate-800" />}
                                  <div className="flex-1 min-w-0">
                                    <h5 className="font-bold text-xs text-slate-100 truncate">{p.name || p.title}</h5>
                                    <div className="text-[10px] text-slate-400">{p.category}</div>
                                    {p.price && <div className="text-xs font-extrabold text-emerald-400 mt-0.5">{p.price}</div>}
                                  </div>
                                  <div className="flex gap-2 shrink-0">
                                    <button
                                      type="button"
                                      onClick={() => {
                                        setIsOpen(false);
                                        navigateTo('products', p.slug || String(p.id));
                                      }}
                                      className="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-bold rounded-lg transition-colors inline-flex items-center gap-1 cursor-pointer"
                                    >
                                      <span>View</span>
                                      <ExternalLink className="w-2.5 h-2.5" />
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => {
                                        if (addToCart) addToCart(p);
                                        addBotMessage(`Added "${p.name || p.title}" to cart!`);
                                      }}
                                      className="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-[10px] rounded-lg transition-colors cursor-pointer"
                                    >
                                      Add to Cart
                                    </button>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Matched Games */}
                        {Array.isArray(msg.matchedGames) && msg.matchedGames.length > 0 && (
                          <div className="space-y-2">
                            <div className="text-xs font-bold uppercase text-amber-400 tracking-wider flex items-center gap-1.5">
                              <Gamepad2 className="w-3.5 h-3.5" />
                              <span>Matching Games</span>
                            </div>
                            <div className="grid grid-cols-1 gap-2">
                              {msg.matchedGames.map((g) => (
                                <div key={String(g.id)} className="bg-slate-900 border border-slate-800 p-2.5 rounded-xl flex items-center justify-between gap-3">
                                  <div>
                                    <div className="font-bold text-xs text-white">{g.name || g.gameTitle}</div>
                                    <div className="text-[10px] text-slate-400">{g.platform} • {g.genre}</div>
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => handleStartCompatibility(g)}
                                    className="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold rounded-lg transition-colors shrink-0 flex items-center gap-1 cursor-pointer"
                                  >
                                    <Cpu className="w-3 h-3" />
                                    <span>Check Specs</span>
                                  </button>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Matched Blogs */}
                        {Array.isArray(msg.matchedBlogs) && msg.matchedBlogs.length > 0 && (
                          <div className="space-y-2">
                            <div className="text-xs font-bold uppercase text-blue-400 tracking-wider flex items-center gap-1.5">
                              <BookOpen className="w-3.5 h-3.5" />
                              <span>Matching Hardware Articles</span>
                            </div>
                            <div className="grid grid-cols-1 gap-2">
                              {msg.matchedBlogs.map((b) => (
                                <div key={String(b.id)} className="bg-slate-900 border border-slate-800 p-2.5 rounded-xl">
                                  <div className="text-[10px] font-bold text-blue-400 uppercase tracking-wider">{b.category}</div>
                                  <h5 className="font-bold text-xs text-white mt-0.5">{b.title}</h5>
                                  {b.excerpt && <p className="text-[11px] text-slate-400 mt-1 line-clamp-2">{b.excerpt}</p>}
                                  <a
                                    href={b.readUrl || `/blogs/${b.slug || b.id}/`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-400 mt-1.5 hover:underline"
                                  >
                                    <span>Read Article</span>
                                    <ExternalLink className="w-3 h-3" />
                                  </a>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    )}

                    {/* Compatibility Result Breakdown Card (Strict Backend Data Only) */}
                    {msg.type === 'compat_result' && msg.result && (
                      <CompatResultCard result={msg.result} />
                    )}
                  </div>
                </div>
              ))}

              {/* Saved Devices Manager Flow */}
              {activeFlow === 'devices' && (
                <div className="bg-slate-950 border border-cyan-500/30 rounded-2xl p-4 space-y-4">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                    <h4 className="font-bold text-xs text-cyan-400 flex items-center gap-1.5">
                      <Laptop className="w-4 h-4" />
                      <span>Saved Gaming Hardware Devices</span>
                    </h4>
                    <button type="button" onClick={() => setActiveFlow(null)} className="text-slate-400 text-xs hover:text-white">Close</button>
                  </div>

                  {!currentUser ? (
                    <p className="text-xs text-slate-400">
                      Sign in to your account to save your desktop or laptop hardware specs permanently.
                    </p>
                  ) : (
                    <>
                      <div className="space-y-2">
                        {userDevices.length === 0 ? (
                          <p className="text-xs text-slate-400">No saved devices found. Add your PC specs below:</p>
                        ) : (
                          userDevices.map((dev) => (
                            <div key={String(dev.id)} className="bg-slate-900 border border-slate-800 p-3 rounded-xl flex items-center justify-between gap-3">
                              <div>
                                <div className="font-bold text-xs text-white flex items-center gap-2">
                                  <span>{dev.device_name}</span>
                                  {Boolean(dev.is_default) && (
                                    <span className="text-[9px] bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/30">
                                      Default
                                    </span>
                                  )}
                                </div>
                                <div className="text-[11px] text-slate-400 mt-0.5">
                                  {dev.cpu} • {dev.gpu} • {dev.ram_gb}GB RAM
                                </div>
                              </div>
                              <div className="flex items-center gap-2">
                                <button
                                  type="button"
                                  onClick={() => handleUseDeviceForCompat(dev)}
                                  className="px-2.5 py-1 bg-amber-500 hover:bg-amber-400 text-slate-950 text-[11px] font-extrabold rounded-lg transition-colors cursor-pointer"
                                >
                                  Use Specs
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleStartEditDevice(dev)}
                                  className="p-1 text-slate-400 hover:text-cyan-400 transition-colors cursor-pointer"
                                  title="Edit Device Specs"
                                >
                                  <Edit3 className="w-4 h-4" />
                                </button>
                                {!Boolean(dev.is_default) && (
                                  <button
                                    type="button"
                                    onClick={() => handleSetDefaultDevice(dev.id)}
                                    className="p-1 text-slate-400 hover:text-emerald-400 transition-colors cursor-pointer"
                                    title="Set Default"
                                  >
                                    <Check className="w-4 h-4" />
                                  </button>
                                )}
                                <button
                                  type="button"
                                  onClick={() => handleDeleteDevice(dev.id)}
                                  className="p-1 text-slate-400 hover:text-rose-400 transition-colors cursor-pointer"
                                  title="Delete Device"
                                >
                                  <Trash2 className="w-4 h-4" />
                                </button>
                              </div>
                            </div>
                          ))
                        )}
                      </div>

                      {!showDeviceForm ? (
                        <button
                          type="button"
                          onClick={() => {
                            resetDeviceForm();
                            setShowDeviceForm(true);
                          }}
                          className="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition-colors flex items-center justify-center gap-1.5"
                        >
                          <Plus className="w-4 h-4 text-emerald-400" />
                          <span>Add New Device</span>
                        </button>
                      ) : (
                        <form onSubmit={handleSaveDeviceSubmit} className="space-y-3 pt-2 border-t border-slate-800">
                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <input
                              type="text"
                              required
                              placeholder="Device Name (e.g. My Gaming Rig)"
                              value={deviceForm.deviceName}
                              onChange={(e) => setDeviceForm({ ...deviceForm, deviceName: e.target.value })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            />
                            <input
                              type="text"
                              placeholder="Device Type (e.g. Desktop PC)"
                              value={deviceForm.deviceType}
                              onChange={(e) => setDeviceForm({ ...deviceForm, deviceType: e.target.value })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            />
                            <input
                              type="text"
                              required
                              placeholder="CPU (e.g. Intel Core i5-12400)"
                              value={deviceForm.cpu}
                              onChange={(e) => setDeviceForm({ ...deviceForm, cpu: e.target.value })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            />
                            <input
                              type="text"
                              required
                              placeholder="GPU (e.g. NVIDIA RTX 3060)"
                              value={deviceForm.gpu}
                              onChange={(e) => setDeviceForm({ ...deviceForm, gpu: e.target.value })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            />
                            <input
                              type="number"
                              required
                              min={2}
                              max={128}
                              placeholder="RAM (GB)"
                              value={deviceForm.ramGb}
                              onChange={(e) => setDeviceForm({ ...deviceForm, ramGb: e.target.value === '' ? '' : Number(e.target.value) })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            />
                            <input
                              type="text"
                              placeholder="Storage (e.g. 512GB NVMe SSD)"
                              value={deviceForm.storage}
                              onChange={(e) => setDeviceForm({ ...deviceForm, storage: e.target.value })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            />
                            <select
                              value={deviceForm.operatingSystem}
                              onChange={(e) => setDeviceForm({ ...deviceForm, operatingSystem: e.target.value })}
                              className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-cyan-500"
                            >
                              <option value="">Select operating system</option>
                              <option value="Windows 11 64-bit">Windows 11 64-bit</option>
                              <option value="Windows 10 64-bit">Windows 10 64-bit</option>
                              <option value="Windows 7 64-bit">Windows 7 64-bit</option>
                              <option value="Linux">Linux</option>
                            </select>
                            <label className="flex items-center gap-2 text-xs text-slate-300 px-1">
                              <input
                                type="checkbox"
                                checked={deviceForm.isDefault}
                                onChange={(e) => setDeviceForm({ ...deviceForm, isDefault: e.target.checked })}
                                className="rounded border-slate-800 text-cyan-500 focus:ring-cyan-500"
                              />
                              <span>Set as default device</span>
                            </label>
                          </div>

                          <div className="flex justify-end gap-2 pt-1">
                            <button
                              type="button"
                              onClick={() => {
                                resetDeviceForm();
                                setShowDeviceForm(false);
                              }}
                              className="px-3 py-1.5 text-xs text-slate-400 hover:text-white"
                            >
                              Cancel
                            </button>
                            <button
                              type="submit"
                              className="px-5 py-1.5 bg-cyan-600 hover:bg-cyan-500 text-white font-extrabold text-xs rounded-xl shadow-md transition-colors"
                            >
                              Save Device
                            </button>
                          </div>
                        </form>
                      )}
                    </>
                  )}
                </div>
              )}

              {/* Compatibility Spec Form */}
              {activeFlow === 'compatibility' && (
                <div className="bg-slate-950 border border-amber-500/30 rounded-2xl p-4 space-y-4">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                    <h4 className="font-bold text-xs text-amber-400 flex items-center gap-1.5">
                      <Cpu className="w-4 h-4" />
                      <span>Hardware Compatibility Checker</span>
                    </h4>
                    <button type="button" onClick={() => setActiveFlow(null)} className="text-slate-400 text-xs hover:text-white">Cancel</button>
                  </div>

                  {selectedGame ? (
                    <div className="space-y-2">
                      <div className="bg-slate-900 p-2.5 rounded-xl border border-slate-800 flex items-center justify-between">
                        <div>
                          <span className="text-[10px] text-slate-400 uppercase block font-bold">Target Game</span>
                          <span className="text-xs font-extrabold text-white">{selectedGame.name}</span>
                        </div>
                        <button type="button" onClick={() => setSelectedGame(null)} className="text-[10px] text-amber-400 hover:underline cursor-pointer">Change</button>
                      </div>

                      {/* Official Game Requirements Breakdown from Backend API */}
                      {(selectedGame.minSpecs || selectedGame.recommendedSpecs) && (
                        <div className="bg-slate-900/90 border border-slate-800 rounded-xl p-3 space-y-2 text-xs">
                          <div className="flex items-center justify-between">
                            <span className="text-[10px] text-amber-400 font-extrabold uppercase tracking-wider flex items-center gap-1">
                              <ShieldCheck className="w-3.5 h-3.5" />
                              <span>Official Database Requirements</span>
                            </span>
                          </div>

                          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
                            {selectedGame.minSpecs && (
                              <div className="bg-slate-950/80 p-2.5 rounded-lg border border-rose-500/20">
                                <div className="font-extrabold text-rose-400 text-[10px] uppercase flex items-center gap-1">
                                  <AlertTriangle className="w-3 h-3" />
                                  <span>Minimum Requirements</span>
                                </div>
                                <p className="text-slate-300 text-[11px] font-mono mt-1 leading-snug">{selectedGame.minSpecs}</p>
                              </div>
                            )}

                            {selectedGame.recommendedSpecs && (
                              <div className="bg-slate-950/80 p-2.5 rounded-lg border border-emerald-500/20">
                                <div className="font-extrabold text-emerald-400 text-[10px] uppercase flex items-center gap-1">
                                  <CheckCircle2 className="w-3 h-3" />
                                  <span>Recommended Requirements</span>
                                </div>
                                <p className="text-slate-300 text-[11px] font-mono mt-1 leading-snug">{selectedGame.recommendedSpecs}</p>
                              </div>
                            )}
                          </div>
                        </div>
                      )}
                    </div>
                  ) : (
                    <div className="space-y-1">
                      <label className="text-xs font-bold text-slate-300">Select Game</label>
                      <button
                        type="button"
                        onClick={handleGamesAction}
                        className="w-full p-2.5 rounded-xl bg-slate-900 border border-slate-800 text-left text-xs font-bold text-slate-300 hover:border-amber-500 cursor-pointer"
                      >
                        Click to select a game from list...
                      </button>
                    </div>
                  )}

                  <form onSubmit={handleRunCompatibilityCheck} className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <label className="space-y-1">
                        <span className="text-[11px] font-bold text-slate-300">Processor (CPU)</span>
                        <input
                          type="text"
                          required
                          value={manualSpecs.cpu}
                          onChange={(e) => setManualSpecs({ ...manualSpecs, cpu: e.target.value })}
                          className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                          placeholder="e.g. Intel Core i5-12400"
                        />
                      </label>

                      <label className="space-y-1">
                        <span className="text-[11px] font-bold text-slate-300">Graphics Card (GPU)</span>
                        <input
                          type="text"
                          required
                          value={manualSpecs.gpu}
                          onChange={(e) => setManualSpecs({ ...manualSpecs, gpu: e.target.value })}
                          className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                          placeholder="e.g. NVIDIA RTX 3060"
                        />
                      </label>

                      <label className="space-y-1">
                        <span className="text-[11px] font-bold text-slate-300">System RAM (GB)</span>
                        <input
                          type="number"
                          min={2}
                          max={128}
                          required
                          value={manualSpecs.ramGb}
                          onChange={(e) => setManualSpecs({ ...manualSpecs, ramGb: e.target.value === '' ? '' : Number(e.target.value) })}
                          className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                        />
                      </label>

                      <label className="space-y-1">
                        <span className="text-[11px] font-bold text-slate-300">Operating System</span>
                        <select
                          required
                          value={manualSpecs.operatingSystem}
                          onChange={(e) => setManualSpecs({ ...manualSpecs, operatingSystem: e.target.value })}
                          className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-amber-500"
                        >
                          <option value="">Select operating system</option>
                          <option value="Windows 11 64-bit">Windows 11 64-bit</option>
                          <option value="Windows 10 64-bit">Windows 10 64-bit</option>
                          <option value="Windows 7 64-bit">Windows 7 64-bit</option>
                          <option value="Linux">Linux</option>
                        </select>
                      </label>
                    </div>

                    <button
                      type="submit"
                      disabled={!selectedGame}
                      className="w-full py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-md transition-colors disabled:opacity-50 cursor-pointer"
                    >
                      Run Verified Compatibility Check
                    </button>
                  </form>
                </div>
              )}

              {/* Write for Us Proposal Form */}
              {activeFlow === 'write_for_us' && !proposalSubmitted && (
                <form onSubmit={handleSubmitProposal} className="bg-slate-950 border border-rose-500/30 rounded-2xl p-4 space-y-3">
                  <div className="flex items-center justify-between border-b border-slate-800 pb-2">
                    <h4 className="font-bold text-xs text-rose-400 flex items-center gap-1.5">
                      <PenTool className="w-4 h-4" />
                      <span>Submit Article Proposal (ROC Admin Review)</span>
                    </h4>
                    <button type="button" onClick={() => setActiveFlow(null)} className="text-slate-400 text-xs hover:text-white">Cancel</button>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <input
                      type="text"
                      required
                      placeholder="Your Full Name"
                      value={proposal.name}
                      onChange={(e) => setProposal({ ...proposal, name: e.target.value })}
                      className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-rose-500"
                    />
                    <input
                      type="email"
                      required
                      placeholder="Your Email"
                      value={proposal.email}
                      onChange={(e) => setProposal({ ...proposal, email: e.target.value })}
                      className="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-rose-500"
                    />
                  </div>

                  <input
                    type="text"
                    required
                    placeholder="Proposed Article Topic (e.g. Best Mice for FPS)"
                    value={proposal.proposedTopic}
                    onChange={(e) => setProposal({ ...proposal, proposedTopic: e.target.value })}
                    className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-rose-500"
                  />

                  <textarea
                    rows={3}
                    required
                    placeholder="Short Pitch / Article Summary"
                    value={proposal.shortPitch}
                    onChange={(e) => setProposal({ ...proposal, shortPitch: e.target.value })}
                    className="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-rose-500 resize-none"
                  />

                  <button
                    type="submit"
                    className="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-extrabold text-xs rounded-xl shadow-md transition-colors cursor-pointer"
                  >
                    Submit Proposal to Admin Review
                  </button>
                </form>
              )}

              {isCreatingNewChat && (
                <div className="flex gap-2 items-center text-slate-400 text-xs py-2">
                  <Bot className="w-4 h-4 text-emerald-400 animate-spin" />
                  <span>Creating new conversation thread...</span>
                </div>
              )}

              {isTyping && (
                <div className="flex gap-2 items-center text-slate-400 text-xs py-2">
                  <Bot className="w-4 h-4 text-emerald-400 animate-spin" />
                  <span>ROC Agent evaluating...</span>
                </div>
              )}

              <div ref={chatEndRef} />
            </div>

            {/* Chat Input Footer */}
            <div className="bg-slate-950/90 p-4 border-t border-slate-800 shrink-0">
              <form
                onSubmit={(e) => {
                  e.preventDefault();
                  if (!isCreatingNewChat && !isTyping) handleUserSend();
                }}
                className="flex gap-2"
              >
                <input
                  type="text"
                  disabled={isCreatingNewChat || isTyping}
                  value={inputText}
                  onChange={(e) => setInputText(e.target.value)}
                  placeholder={isCreatingNewChat ? "Initializing new chat..." : "Ask ROC Agent or type query..."}
                  className="flex-1 bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-xs text-slate-100 focus:outline-none focus:border-emerald-500 disabled:opacity-50"
                />
                <button
                  type="submit"
                  disabled={isCreatingNewChat || isTyping}
                  className="px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs rounded-xl transition-colors flex items-center justify-center shrink-0 cursor-pointer disabled:opacity-50"
                >
                  <Send className="w-4 h-4" />
                </button>
              </form>
            </div>

          </div>
        </div>
      )}
    </>
  );
};
