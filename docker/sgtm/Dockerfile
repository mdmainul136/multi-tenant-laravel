FROM node:20-alpine

WORKDIR /app

# Install dependencies
COPY package.json package-lock.json* ./
RUN npm ci --production

# Copy application
COPY . .

# Security: non-root user
RUN addgroup -S sgtm && adduser -S sgtm -G sgtm
USER sgtm

# Health check
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:8080/healthz || exit 1

EXPOSE 8080

CMD ["node", "server.js"]
