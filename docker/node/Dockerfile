# syntax=docker/dockerfile:1

# --- dev: source is bind-mounted, HMR via the Nuxt dev server ---
FROM node:24-alpine AS dev

WORKDIR /app

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

# --- build: static SPA output consumed by the nginx prod image ---
FROM node:24-alpine AS build

WORKDIR /app

COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ .
RUN npm run generate
