# Frontend prod-образ (staging/K8s): next build → standalone server.js
FROM node:20-alpine AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci
COPY frontend/ ./
ENV NEXT_TELEMETRY_DISABLED=1
# output:'standalone' у next.config.js генерує .next/standalone/server.js
RUN npm run build

FROM node:20-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=3000
ENV HOSTNAME=0.0.0.0
# standalone містить мінімальний node_modules + server.js
COPY --from=build /app/.next/standalone ./
COPY --from=build /app/.next/static ./.next/static
# public може бути порожньою — || true щоб build не падав
COPY --from=build /app/public ./public
EXPOSE 3000
CMD ["node", "server.js"]
