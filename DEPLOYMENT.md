# EspoCRM Deployment with Dokploy

This guide provides step-by-step instructions for deploying EspoCRM using Dokploy, a self-hosted deployment platform.

## Prerequisites

- A VPS or dedicated server with Ubuntu 20.04+ or Debian 11+
- Minimum 2GB RAM (4GB recommended)
- 20GB storage space
- Domain name pointed to your server IP
- Root or sudo access to the server

## Step 1: Install Dokploy

SSH into your server and run the official installation script:

```bash
curl -sSL https://dokploy.com/install.sh | sh
```

This will:
- Install Docker and Docker Swarm
- Set up Dokploy with Traefik for reverse proxy
- Create necessary networks and services

After installation, access Dokploy at: `http://your-server-ip:3000`

## Step 2: Configure DNS

### For your main domain (e.g., crm.yourdomain.com):

1. Go to your DNS provider
2. Add an A record:
   - Host: `crm` (or `@` for root domain)
   - Value: Your server's IP address
   - TTL: 3600

### For phpMyAdmin (optional):

1. Add another A record:
   - Host: `pma`
   - Value: Your server's IP address
   - TTL: 3600

## Step 3: Set Up GitHub/GitLab Integration

### GitHub:
1. In Dokploy, go to Settings → Git
2. Click "Create GitHub App"
3. Name it (e.g., "Dokploy-EspoCRM")
4. Click "Create GitHub App"
5. Click "Install" and authorize access to your repository

### GitLab:
1. Go to GitLab → Settings → Applications
2. Create new application with:
   - Name: Dokploy-EspoCRM
   - Redirect URI: Copy from Dokploy
   - Scopes: api, read_user, read_repository
3. Copy Application ID and Secret to Dokploy

## Step 4: Create Project in Dokploy

1. Click "Create Project"
2. Name: "EspoCRM"
3. Description: "EspoCRM Customer Relationship Management"

## Step 5: Deploy Docker Compose

1. In your project, click "Create Service" → "Docker Compose"
2. Configure:
   - **Name**: espocrm-stack
   - **Repository**: Your GitHub/GitLab repo URL
   - **Branch**: master (or main)
   - **Compose File**: docker-compose.dokploy.yml
   - **Build Path**: / (root)

## Step 6: Configure Environment Variables

In Dokploy, go to your Docker Compose service → Environment:

```env
# Database
DB_NAME=espocrm
DB_USER=espocrm
DB_PASSWORD=GenerateSecurePassword123!
MYSQL_ROOT_PASSWORD=AnotherSecurePassword456!

# Application
DOMAIN=crm.yourdomain.com
ESPOCRM_SITE_URL=https://crm.yourdomain.com
ESPOCRM_ADMIN_USERNAME=admin
ESPOCRM_ADMIN_PASSWORD=YourAdminPassword789!

# Email (example for Gmail)
ESPOCRM_SMTP_SERVER=smtp.gmail.com
ESPOCRM_SMTP_PORT=587
ESPOCRM_SMTP_USERNAME=your-email@gmail.com
ESPOCRM_SMTP_PASSWORD=your-app-specific-password
ESPOCRM_OUTBOUND_EMAIL_FROM_ADDRESS=noreply@yourdomain.com

# phpMyAdmin (optional)
PMA_DOMAIN=pma.yourdomain.com
```

### Generate Secure Passwords:
```bash
# Generate random passwords
openssl rand -base64 32
```

## Step 7: Configure Domains

1. Go to your Docker Compose service → Domains
2. Click "Add Domain" for EspoCRM:
   - **Host**: crm.yourdomain.com
   - **Service Name**: espocrm
   - **Container Port**: 80
   - **HTTPS**: ON
   - **Certificate**: Let's Encrypt

3. (Optional) Add domain for phpMyAdmin:
   - **Host**: pma.yourdomain.com
   - **Service Name**: phpmyadmin
   - **Container Port**: 80
   - **HTTPS**: ON
   - **Certificate**: Let's Encrypt

## Step 8: Configure Automatic Deployments

### For GitHub:
1. Go to Deployments tab
2. Copy the Webhook URL
3. In GitHub repo → Settings → Webhooks:
   - Add webhook with the copied URL
   - Content type: application/json
   - Events: Push events
   - Active: Yes

### For GitLab:
Similar process in GitLab → Settings → Webhooks

## Step 9: Deploy

1. Click "Deploy" button
2. Monitor logs for deployment progress
3. Wait for all services to be healthy

## Step 10: Initial Setup

1. Visit https://crm.yourdomain.com
2. If EspoCRM installer appears:
   - Database settings are pre-configured
   - Complete the setup wizard
3. Log in with your admin credentials

## Step 11: Configure Backups

### Manual Backup:
```bash
docker-compose -f docker-compose.dokploy.yml run --rm backup
```

### Automatic Backups:
In Dokploy, create a scheduled task:
1. Go to Settings → Scheduled Tasks
2. Add new task:
   - Name: EspoCRM Backup
   - Schedule: `0 2 * * *` (daily at 2 AM)
   - Command: `docker-compose run --rm backup`

## Step 12: Set Up Monitoring

### Health Checks:
Already configured in docker-compose.dokploy.yml

### Telegram Notifications:
1. Create bot with @BotFather on Telegram
2. Get chat ID from @userinfobot
3. In Dokploy → Settings → Notifications:
   - Add Telegram notification
   - Enter Bot Token and Chat ID
   - Test connection

## Maintenance

### Update EspoCRM:
```bash
# Pull latest changes
git pull origin master

# Rebuild and deploy via Dokploy
# Or trigger via webhook
```

### View Logs:
In Dokploy, click on any service → Logs

### Scale Services:
In Advanced → Cluster Settings:
- Set Replicas count
- Click Redeploy

### Database Management:
Access phpMyAdmin at: https://pma.yourdomain.com

## Troubleshooting

### Issue: Bad Gateway Error
**Solution**: Check if services are running:
```bash
docker ps
docker service logs espocrm
```

### Issue: Database Connection Failed
**Solution**: Restart services:
```bash
docker-compose restart
```

### Issue: SSL Certificate Issues
**Solution**: 
1. Verify DNS is properly configured
2. Check Traefik logs: `docker logs dokploy-traefik`

### Issue: Low Disk Space
**Solution**: Clean Docker cache:
```bash
docker system prune -a
docker volume prune
```

## Security Best Practices

1. **Use Strong Passwords**: Generate with `openssl rand -base64 32`
2. **Enable Basic Auth**: For phpMyAdmin in Dokploy UI
3. **Regular Backups**: Automated daily backups configured
4. **Keep Updated**: Regular updates via Git webhooks
5. **Monitor Logs**: Check logs regularly for anomalies
6. **Firewall Rules**: Only expose ports 80, 443, and 3000

## Performance Optimization

1. **Adjust PHP Settings**: In environment variables
2. **Database Tuning**: MariaDB settings in docker-compose
3. **Redis Caching**: Already configured
4. **CDN**: Consider Cloudflare for static assets

## Rollback Procedure

If deployment fails:
1. Dokploy automatically rolls back (configured)
2. Manual rollback: Deploy previous Git commit
3. Restore from backup if needed

## Support Resources

- [EspoCRM Documentation](https://docs.espocrm.com/)
- [Dokploy Documentation](https://docs.dokploy.com/)
- [Docker Documentation](https://docs.docker.com/)
- [Traefik Documentation](https://doc.traefik.io/)

## License

This deployment configuration is provided as-is. Ensure you comply with EspoCRM's license terms.