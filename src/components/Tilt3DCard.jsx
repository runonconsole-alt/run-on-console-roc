import React, { useRef } from 'react';

/**
 * Tilt3DCard - Performance-Optimized GPU 3D Micro-Tilt
 * Optimizations:
 * - Uses direct ref DOM style mutations on mousemove to avoid React re-renders.
 * - Skips 3D tilt calculations on mobile touch devices (<768px).
 * - Instant static fallback when prefers-reduced-motion is active.
 */
export const Tilt3DCard = ({ 
  children, 
  className = "", 
  onClick, 
  maxTilt = 3.5
}) => {
  const cardRef = useRef(null);

  const handleMouseMove = (e) => {
    if (!cardRef.current) return;
    if (window.innerWidth < 768) return; // Skip tilt on mobile
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const rect = cardRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const rotateX = ((y - centerY) / centerY) * -maxTilt;
    const rotateY = ((x - centerX) / centerX) * maxTilt;

    cardRef.current.style.transform = `perspective(1200px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) scale3d(1.012, 1.012, 1.012)`;
    cardRef.current.style.transition = 'transform 0.08s ease-out';
  };

  const handleMouseLeave = () => {
    if (!cardRef.current) return;
    cardRef.current.style.transform = 'perspective(1200px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
    cardRef.current.style.transition = 'transform 0.4s cubic-bezier(0.16, 1, 0.3, 1)';
  };

  return (
    <div
      ref={cardRef}
      onMouseMove={handleMouseMove}
      onMouseLeave={handleMouseLeave}
      onClick={onClick}
      className={`transition-shadow duration-300 will-change-transform ${className}`}
    >
      {children}
    </div>
  );
};
