#!/bin/bash

# EspoCRM Local Development Starter
# Quick script to start local development environment

echo "🏠 Starting EspoCRM Local Development Environment..."
echo "=================================================="

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker Desktop."
    exit 1
fi

# Create local directories
echo "📁 Creating local directories..."
mkdir -p data-local upload-local

# Stop any existing containers
echo "🛑 Stopping existing containers..."
docker-compose -f docker-compose.local.yml down 2>/dev/null || true

# Build and start containers
echo "🏗️ Building and starting containers..."
docker-compose -f docker-compose.local.yml up -d --build

# Wait for services to be ready
echo "⏳ Waiting for services to start..."
sleep 10

# Check if containers are running
if docker-compose -f docker-compose.local.yml ps | grep -q "Up"; then
    echo ""
    echo "✅ SUCCESS! Local environment is running!"
    echo ""
    echo "🌐 Access URLs:"
    echo "   EspoCRM:    http://localhost:8080"
    echo "   phpMyAdmin: http://localhost:8081"
    echo ""
    echo "🔐 Credentials:"
    echo "   Admin User: admin"
    echo "   Admin Pass: admin123"
    echo ""
    echo "🔧 Development Commands:"
    echo "   Rebuild:    docker exec espocrm-local php /var/www/html/bin/command rebuild"
    echo "   Clear Cache: docker exec espocrm-local php /var/www/html/bin/command clear-cache"
    echo "   View Logs:  docker logs -f espocrm-local"
    echo "   Stop:       docker-compose -f docker-compose.local.yml down"
    echo ""
    echo "📝 Edit these files for branding changes:"
    echo "   CSS: client/custom/res/css/custom.css"
    echo "   JS:  client/custom/lib/custom-footer.js"
    echo ""
    echo "🎯 Test watermark removal at: http://localhost:8080"
    echo ""
else
    echo "❌ Failed to start containers. Check logs:"
    docker-compose -f docker-compose.local.yml logs
fi