# Dockerfile for Railway - PHP Frontend Service
# Uses official PHP CLI image with pdo_mysql and all required extensions.

FROM php:8.3-cli-alpine

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Install additional useful extensions
RUN apk add --no-cache curl libcurl curl-dev \
    && docker-php-ext-install curl

# Set working directory
WORKDIR /app

# Copy all frontend files
COPY . .

# Railway sets PORT automatically; default to 8080 if not set
EXPOSE ${PORT:-8080}

# Start PHP built-in server pointed at the Frontend directory
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t Frontend"]
