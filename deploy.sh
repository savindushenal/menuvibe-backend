#!/bin/bash

echo "🚀 Deploying MenuVibe Backend..."

# Pull latest code
echo "📥 Pulling latest code from Git..."
git pull origin main

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force

# Clear and cache config
echo "🔧 Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear

# Optimize autoloader
echo "⚡ Optimizing autoloader..."
composer dump-autoload --optimize

echo "✅ Deployment complete!"
