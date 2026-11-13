# Production nginx (E6.1, E6.5): the built SPA plus the /api proxy to php-fpm. Build context: the repository root.
FROM node:26.13.0-alpine AS frontend

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM nginx:1.30.6-alpine

COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend /app/dist /usr/share/nginx/html
