import React from 'react';
import { playHoverSound } from '../utils/audioEffects';

/**
 * BouncyText - Optimized semantic container renderer.
 * Replaces per-letter DOM node inflation with clean container-level micro-interactions.
 * Supports prefers-reduced-motion out of the box.
 */
export const BouncyText = ({ 
  text = "", 
  className = "", 
  as = "span",
  enableAudio = false 
}) => {
  const Component = as;

  if (!text) return null;

  return (
    <Component 
      onMouseEnter={() => {
        if (enableAudio) playHoverSound();
      }}
      className={`inline-block transition-[transform,color,filter] duration-200 motion-reduce:transition-none hover:-translate-y-0.5 hover:text-emerald-400 ${className}`}
    >
      {text}
    </Component>
  );
};
