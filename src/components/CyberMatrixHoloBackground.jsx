import React, { useEffect, useRef } from 'react';

/**
 * CyberMatrixHoloBackground - Unique flowing 3D sinusoidal cyber-wave mesh & matrix laser energy
 * Specially designed for Page Banners (Categories, Products, Blogs, About, Contact)
 */
export const CyberMatrixHoloBackground = () => {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let animationFrameId;

    let width = (canvas.width = canvas.offsetWidth);
    let height = (canvas.height = canvas.offsetHeight);

    const handleResize = () => {
      if (!canvas) return;
      width = canvas.width = canvas.offsetWidth;
      height = canvas.height = canvas.offsetHeight;
    };

    window.addEventListener('resize', handleResize);

    let step = 0;

    const render = () => {
      ctx.clearRect(0, 0, width, height);

      step += 0.025;

      // 1. Draw flowing cyber wave mesh
      const lines = 7;
      for (let l = 0; l < lines; l++) {
        ctx.beginPath();
        const baseAlpha = 0.12 + (l / lines) * 0.18;
        const colorHue = l % 2 === 0 ? '16, 185, 129' : '6, 182, 212';
        ctx.strokeStyle = `rgba(${colorHue}, ${baseAlpha})`;
        ctx.lineWidth = 1.5;

        for (let x = 0; x <= width; x += 15) {
          const y =
            height * 0.5 +
            Math.sin(x * 0.006 + step + l * 0.7) * 35 * Math.sin(step * 0.4 + l) +
            Math.cos(x * 0.012 - step * 0.8 + l) * 20;

          if (x === 0) {
            ctx.moveTo(x, y);
          } else {
            ctx.lineTo(x, y);
          }
        }
        ctx.stroke();
      }

      // 2. Draw glowing cyber matrix energy nodes
      const nodeCount = 18;
      for (let n = 0; n < nodeCount; n++) {
        const nx = (width / nodeCount) * n + (Math.sin(step + n) * 20);
        const ny = height * 0.5 + Math.sin(nx * 0.006 + step + (n % 4)) * 35;
        
        ctx.beginPath();
        ctx.arc(nx, ny, 2.5, 0, Math.PI * 2);
        ctx.fillStyle = '#34D399';
        ctx.shadowBlur = 12;
        ctx.shadowColor = '#10B981';
        ctx.fill();
        ctx.shadowBlur = 0;
      }

      // 3. Subtle floating ambient hexagonal dust
      ctx.fillStyle = 'rgba(52, 211, 153, 0.15)';
      for (let d = 0; d < 12; d++) {
        const dx = ((d * 85 + step * 15) % width);
        const dy = ((d * 45 + Math.sin(step + d) * 30 + height) % height);
        ctx.fillRect(dx, dy, 2, 2);
      }

      animationFrameId = requestAnimationFrame(render);
    };

    render();

    return () => {
      window.removeEventListener('resize', handleResize);
      cancelAnimationFrame(animationFrameId);
    };
  }, []);

  return (
    <canvas 
      ref={canvasRef} 
      className="absolute inset-0 w-full h-full pointer-events-none opacity-80"
    />
  );
};
