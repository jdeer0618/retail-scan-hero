# Dockerfile
FROM node:24-alpine

# Set working directory
WORKDIR /app

# Copy package files with ownership to avoid permission issues
COPY --chown=node:node package*.json ./

# Use npm ci for reproducible installs (better than npm install for consistency)
RUN npm ci

# Copy the rest of the application code with ownership
COPY --chown=node:node . .

# Switch to non-root user for security best practices
USER node

# Expose the port Vite runs on
EXPOSE 5173

# Default command (overridden in compose.yaml for dev)
CMD ["npm", "run", "dev", "--", "--host"]
