import React from 'react';
import { renderToString } from 'react-dom/server';
import App from './App.jsx';

export function renderServer(urlPath) {
  return renderToString(
    <React.StrictMode>
      <App initialUrl={urlPath} />
    </React.StrictMode>
  );
}
