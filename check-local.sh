#!/bin/bash

# Quick check script for local environment
echo "🔍 Checking EspoCRM Local Environment Status..."
echo "=============================================="

# Check if containers are running
echo "📊 Container Status:"
docker-compose -f docker-compose.local.yml ps 2>/dev/null || echo "No containers running"

echo ""
echo "🌐 Service URLs:"
echo "   EspoCRM:    http://localhost:8080"
echo "   phpMyAdmin: http://localhost:8081"

echo ""
echo "🔧 Quick Commands:"
echo "   Start:      ./start-local.sh"
echo "   Stop:       docker-compose -f docker-compose.local.yml down"
echo "   Logs:       docker logs espocrm-local"
echo "   Rebuild:    docker exec espocrm-local php /var/www/html/bin/command rebuild"

# Test if services are responding
echo ""
echo "🚀 Testing connectivity..."

# Test EspoCRM
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8080 | grep -q "200\|302\|404"; then
    echo "   ✅ EspoCRM: Responding"
else
    echo "   ❌ EspoCRM: Not responding"
fi

# Test phpMyAdmin
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8081 | grep -q "200\|302"; then
    echo "   ✅ phpMyAdmin: Responding"
else
    echo "   ❌ phpMyAdmin: Not responding"
fi

echo ""