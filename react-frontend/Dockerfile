# =============================================================================
# Project NEXUS - React Frontend Dockerfile
# =============================================================================
# Node 20 with Vite dev server for development
# =============================================================================

FROM node:20-alpine

# Build arguments
ARG NODE_ENV=development

# Set working directory
WORKDIR /app

# Install dependencies first (better layer caching)
COPY package.json package-lock.json ./
RUN npm ci

# Copy source code
COPY . .

# Expose Vite dev server port
EXPOSE 5173

# Default command - run Vite dev server with host flag for Docker
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
