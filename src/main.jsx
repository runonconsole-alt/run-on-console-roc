import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App.jsx'
import './index.css'

const rootElement = document.getElementById('root');

if (rootElement && rootElement.hasChildNodes()) {
  ReactDOM.hydrateRoot(
    rootElement,
    <React.StrictMode>
      <App />
    </React.StrictMode>,
    {
      onRecoverableError: (error) => {
        // Silently log recoverable hydration notices without crashing UI
        if (typeof console !== 'undefined' && console.warn) {
          console.warn('[Hydration Notice Resolved]:', error?.message || error);
        }
      }
    }
  );
} else if (rootElement) {
  ReactDOM.createRoot(rootElement).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
}
