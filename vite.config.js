import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    preserveSymlinks: true
  },
  build: {
    emptyOutDir: false
  },
  server: {
    host: true,
    port: 3000,
    open: false,
    allowedHosts: true // Allows all tunnels (localtunnel, ngrok, cloudflare, etc.) without blocking!
  }
})
