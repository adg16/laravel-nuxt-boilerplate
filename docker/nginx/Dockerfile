# syntax=docker/dockerfile:1

# Static SPA build (ssr:false, so `nuxi generate` produces a plain .output/public).
FROM node:24-alpine AS frontend-build

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .
RUN npm run generate

# No Node process in production — nginx serves the static build directly.
FROM nginx:1.30-alpine AS prod

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/app.prod.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend-build /app/.output/public /usr/share/nginx/html
