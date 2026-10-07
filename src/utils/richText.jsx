import React from 'react';

/* Text written with **bold** and *italic* (bot replies, FAQ answers): show the formatting, not the symbols. */
export const richText = (text) => String(text || '').split(/(\*\*[^*]+\*\*|\*[^*\n]+\*)/g).map((part, i) => {
  if (/^\*\*[^*]+\*\*$/.test(part)) return <strong key={i}>{part.slice(2, -2)}</strong>;
  if (/^\*[^*\n]+\*$/.test(part)) return <em key={i}>{part.slice(1, -1)}</em>;
  return part;
});
