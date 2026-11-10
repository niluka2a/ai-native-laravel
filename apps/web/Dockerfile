# Development image. The application code is bind-mounted at /app by docker-compose.yml.
FROM node:22-alpine

WORKDIR /app
EXPOSE 3000

CMD ["sh", "-c", "npm ci && npm run dev -- -H 0.0.0.0"]
