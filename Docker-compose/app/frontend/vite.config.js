import { defineConfig } from 'vite'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [tailwindcss()],
  server: {
    // Accessible depuis l'extérieur du conteneur (le reverse-proxy joint Vite)
    host: true,
    port: 5173,
    // Autorise n'importe quel Host (requêtes arrivant via le reverse-proxy)
    allowedHosts: true,
    // Le navigateur passe par le reverse-proxy sur le port 80 pour le HMR
    hmr: { clientPort: 80 },
  },
})
