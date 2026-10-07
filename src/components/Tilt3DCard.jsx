import React, { useRef } from 'react';

/**
 * Tilt3DCard - hover highlight for cards.
 *
 * The card used to tilt in 3D on hover, which made its text look blurry. Now it stays
 * still and a soft green light follows the pointer behind the content (.roc-spot in
 * index.css). The name is kept so existing pages do not change.
 */
export const Tilt3DCard = ({
  children,
  className = "",
  onClick,
}) => {
  const cardRef = useRef(null);

  const handleMouseMove = (e) => {
    const el = cardRef.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    el.style.setProperty('--mx', `${e.clientX - rect.left}px`);
    el.style.setProperty('--my', `${e.clientY - rect.top}px`);
  };

  return (
    <div
      ref={cardRef}
      onMouseMove={handleMouseMove}
      onClick={onClick}
      className={`roc-spot ${className}`}
    >
      {children}
    </div>
  );
};
