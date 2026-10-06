import React, { useEffect, useRef } from 'react';

/**
 * LiveGamingCanvas - Performance-Optimized Interactive Hero Animation
 * Optimizations:
 * - IntersectionObserver pauses animation loop when off-screen.
 * - Visibilitychange listener pauses loop when browser tab is hidden.
 * - 65% particle reduction on mobile screens (<768px).
 * - Instant static fallback when prefers-reduced-motion is active.
 */
export const LiveGamingCanvas = () => {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    // Check prefers-reduced-motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    const ctx = canvas.getContext('2d');
    let animationFrameId;
    let isVisible = true;
    let isInViewport = true;

    const isMobile = window.innerWidth < 768;
    const sparkCount = isMobile ? 12 : 35; // 65% reduction on mobile
    const shrapnelCount = isMobile ? 8 : 22; // 63% reduction on mobile

    let width = (canvas.width = canvas.offsetWidth || window.innerWidth);
    let height = (canvas.height = canvas.offsetHeight || 500);

    const handleResize = () => {
      if (!canvas) return;
      width = canvas.width = canvas.offsetWidth || window.innerWidth;
      height = canvas.height = canvas.offsetHeight || 500;
    };

    window.addEventListener('resize', handleResize);

    // 1. Tracers / Bullet Rounds
    const bullets = [];
    const createBullet = (startX, startY, targetX, targetY, color = '#10B981') => {
      const angle = Math.atan2(targetY - startY, targetX - startX);
      const speed = 14 + Math.random() * 10;
      bullets.push({
        x: startX,
        y: startY,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        length: 25 + Math.random() * 20,
        color,
        life: 0,
        maxLife: 60
      });
    };

    // 2. Explosions & Artillery Shockwaves
    const explosions = [];
    const createExplosion = (x, y, radius = 50, color = '#34D399') => {
      explosions.push({
        x,
        y,
        radius: 5,
        maxRadius: radius,
        alpha: 1,
        color,
        particles: Array.from({ length: shrapnelCount }).map(() => ({
          x,
          y,
          vx: (Math.random() - 0.5) * 8,
          vy: (Math.random() - 0.5) * 8 - 2,
          radius: 1.5 + Math.random() * 2.5,
          color: Math.random() > 0.4 ? '#34D399' : (Math.random() > 0.5 ? '#F59E0B' : '#FFFFFF'),
          life: 1,
          decay: 0.02 + Math.random() * 0.02
        }))
      });
    };

    // 3. Tactical Enemy Lock-on Reticles
    const targets = Array.from({ length: isMobile ? 2 : 4 }).map(() => ({
      x: Math.random() * width,
      y: Math.random() * height * 0.7,
      vx: (Math.random() - 0.5) * 0.8,
      vy: (Math.random() - 0.5) * 0.5,
      size: 20 + Math.random() * 15,
      angle: 0
    }));

    // Ambient floating sparks
    const sparks = Array.from({ length: sparkCount }).map(() => ({
      x: Math.random() * width,
      y: Math.random() * height,
      vy: -(0.5 + Math.random() * 1.5),
      vx: (Math.random() - 0.5) * 0.8,
      radius: 1 + Math.random() * 2,
      alpha: 0.2 + Math.random() * 0.8,
      color: Math.random() > 0.5 ? '#34D399' : '#10B981'
    }));

    // Pointer Interaction
    const handlePointer = (e) => {
      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      createBullet(
        Math.random() > 0.5 ? 0 : width,
        height * 0.8 + Math.random() * 100,
        x,
        y,
        '#34D399'
      );
      createExplosion(x, y, 45, '#34D399');
    };

    canvas.addEventListener('click', handlePointer);

    // Tab Visibility Handler
    const handleVisibilityChange = () => {
      isVisible = !document.hidden;
      if (isVisible && isInViewport) {
        startAnimation();
      } else {
        stopAnimation();
      }
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // IntersectionObserver to pause off-screen rendering
    const observer = new IntersectionObserver(
      (entries) => {
        const entry = entries[0];
        isInViewport = entry ? entry.isIntersecting : true;
        if (isInViewport && isVisible) {
          startAnimation();
        } else {
          stopAnimation();
        }
      },
      { threshold: 0.1 }
    );
    observer.observe(canvas);

    let frameCount = 0;

    const stopAnimation = () => {
      if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
      }
    };

    const startAnimation = () => {
      if (!animationFrameId) {
        render();
      }
    };

    const render = () => {
      if (!isVisible || !isInViewport) return;

      frameCount++;
      ctx.clearRect(0, 0, width, height);

      // Automatic Weapon Fire Cycles
      if (frameCount % (isMobile ? 30 : 18) === 0) {
        const startX = 0;
        const startY = height * 0.3 + Math.random() * (height * 0.5);
        const targetX = width * 0.4 + Math.random() * (width * 0.6);
        const targetY = Math.random() * (height * 0.8);
        createBullet(startX, startY, targetX, targetY, Math.random() > 0.3 ? '#34D399' : '#F59E0B');
      }

      if (frameCount % (isMobile ? 40 : 24) === 0) {
        const startX = width;
        const startY = height * 0.2 + Math.random() * (height * 0.6);
        const targetX = Math.random() * (width * 0.6);
        const targetY = Math.random() * (height * 0.9);
        createBullet(startX, startY, targetX, targetY, '#06B6D4');
      }

      // Render Sparks
      sparks.forEach((s) => {
        s.y += s.vy;
        s.x += s.vx;
        if (s.y < 0) {
          s.y = height;
          s.x = Math.random() * width;
        }

        ctx.beginPath();
        ctx.arc(s.x, s.y, s.radius, 0, Math.PI * 2);
        ctx.fillStyle = s.color;
        ctx.globalAlpha = s.alpha;
        ctx.shadowColor = s.color;
        ctx.shadowBlur = 6;
        ctx.fill();
        ctx.shadowBlur = 0;
      });

      // Render Bullet Tracers
      for (let i = bullets.length - 1; i >= 0; i--) {
        const b = bullets[i];
        b.x += b.vx;
        b.y += b.vy;
        b.life++;

        const tailX = b.x - (b.vx / Math.hypot(b.vx, b.vy)) * b.length;
        const tailY = b.y - (b.vy / Math.hypot(b.vx, b.vy)) * b.length;

        const grad = ctx.createLinearGradient(tailX, tailY, b.x, b.y);
        grad.addColorStop(0, 'transparent');
        grad.addColorStop(1, b.color);

        ctx.beginPath();
        ctx.moveTo(tailX, tailY);
        ctx.lineTo(b.x, b.y);
        ctx.strokeStyle = grad;
        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.shadowColor = b.color;
        ctx.shadowBlur = 8;
        ctx.stroke();
        ctx.shadowBlur = 0;

        ctx.beginPath();
        ctx.arc(b.x, b.y, 2, 0, Math.PI * 2);
        ctx.fillStyle = '#FFFFFF';
        ctx.fill();

        if (b.life > b.maxLife || b.x < -50 || b.x > width + 50 || b.y < -50 || b.y > height + 50) {
          if (Math.random() > 0.5) {
            createExplosion(b.x, b.y, 20, b.color);
          }
          bullets.splice(i, 1);
        }
      }

      // Render Explosions
      for (let i = explosions.length - 1; i >= 0; i--) {
        const exp = explosions[i];
        exp.radius += (exp.maxRadius - exp.radius) * 0.15;
        exp.alpha -= 0.025;

        if (exp.alpha <= 0) {
          explosions.splice(i, 1);
          continue;
        }

        ctx.beginPath();
        ctx.arc(exp.x, exp.y, exp.radius, 0, Math.PI * 2);
        ctx.strokeStyle = exp.color;
        ctx.lineWidth = 2;
        ctx.globalAlpha = exp.alpha;
        ctx.shadowColor = exp.color;
        ctx.shadowBlur = 10;
        ctx.stroke();
        ctx.shadowBlur = 0;

        exp.particles.forEach((p) => {
          p.x += p.vx;
          p.y += p.vy;
          p.vy += 0.12;
          p.life -= p.decay;

          if (p.life > 0) {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = p.life;
            ctx.fill();
          }
        });
      }

      // Render Target Crosshairs
      targets.forEach((t) => {
        t.x += t.vx;
        t.y += t.vy;
        t.angle += 0.02;

        if (t.x < 50 || t.x > width - 50) t.vx *= -1;
        if (t.y < 50 || t.y > height * 0.8) t.vy *= -1;

        ctx.save();
        ctx.translate(t.x, t.y);
        ctx.rotate(t.angle);
        ctx.strokeStyle = '#34D399';
        ctx.lineWidth = 1.5;
        ctx.globalAlpha = 0.45;

        const s = t.size;
        ctx.strokeRect(-s / 2, -s / 2, s, s);

        ctx.beginPath();
        ctx.arc(0, 0, 2, 0, Math.PI * 2);
        ctx.fillStyle = '#EF4444';
        ctx.globalAlpha = 0.7;
        ctx.fill();
        ctx.restore();
      });

      ctx.globalAlpha = 1;
      animationFrameId = requestAnimationFrame(render);
    };

    startAnimation();

    return () => {
      window.removeEventListener('resize', handleResize);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      if (canvas) {
        canvas.removeEventListener('click', handlePointer);
        observer.unobserve(canvas);
      }
      stopAnimation();
    };
  }, []);

  return (
    <canvas
      ref={canvasRef}
      className="absolute inset-0 w-full h-full pointer-events-auto"
      style={{ zIndex: 1 }}
    />
  );
};
