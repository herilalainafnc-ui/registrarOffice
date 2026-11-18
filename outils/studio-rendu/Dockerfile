# Studio UAZ — service de rendu des visuels (outils/studio-rendu/)
# Image officielle Playwright : Chromium et ses dependances, a la version de playwright-core.
FROM mcr.microsoft.com/playwright:v1.49.1-noble

WORKDIR /service
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --no-audit --no-fund
COPY moteur.js serveur.js ./

# Les fichiers du Studio (src/studio du registrar) sont montes en lecture seule sur /studio.
# Dans le conteneur, le service ecoute partout ; docker ne le publie que sur 127.0.0.1 (installer.sh).
ENV NODE_ENV=production PORT=3310 HOTE=0.0.0.0 STUDIO_DIR=/studio HOME=/tmp
USER pwuser
EXPOSE 3310
HEALTHCHECK --interval=60s --timeout=5s --start-period=20s --retries=3 \
	CMD node -e "fetch('http://127.0.0.1:3310/sante').then((r) => process.exit(r.ok ? 0 : 1)).catch(() => process.exit(1))"
CMD ["node", "serveur.js"]
