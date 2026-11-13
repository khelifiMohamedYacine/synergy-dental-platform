# Production edge: Caddy with the dashboard's static build baked in.
# Build context: repository root.
FROM node:24-alpine AS dashboard
WORKDIR /app
COPY dashboard/package.json dashboard/package-lock.json ./
RUN npm ci
COPY dashboard/ ./
RUN npm run build

FROM caddy:2-alpine
COPY infra/Caddyfile.prod /etc/caddy/Caddyfile
COPY --from=dashboard /app/dist /srv/dashboard
