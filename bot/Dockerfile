FROM node:22-bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends chromium ca-certificates && rm -rf /var/lib/apt/lists/*
WORKDIR /opt/kanal/bot
ENV PUPPETEER_SKIP_DOWNLOAD=true PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
COPY package*.json ./
RUN npm ci --omit=dev && mkdir .wwebjs_cache && chown 33:33 .wwebjs_cache
COPY index.js relay.js ./
USER 33:33
ENV HOME=/tmp
CMD ["node","index.js"]
