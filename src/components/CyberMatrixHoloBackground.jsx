import React from 'react';

/**
 * CyberMatrixHoloBackground - banner backdrop.
 *
 * This used to draw an animated wave mesh on a canvas every frame, which made pages
 * lag and drew over the banner text. It is now a still, CSS-only glow.
 */
export const CyberMatrixHoloBackground = () => (
  <div
    aria-hidden="true"
    className="absolute inset-0 pointer-events-none"
    style={{ background: 'radial-gradient(600px circle at 85% 20%, rgba(52, 211, 153, 0.18), transparent 60%), radial-gradient(500px circle at 10% 90%, rgba(45, 212, 191, 0.14), transparent 60%)' }}
  />
);
