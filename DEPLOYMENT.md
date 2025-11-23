# Deployment Guide
## PHP E-Commerce Application

This guide provides comprehensive instructions for deploying the PHP e-commerce application to production.

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Requirements](#server-requirements)
3. [Pre-Deployment Checklist](#pre-deployment-checklist)
4. [Production Setup](#production-setup)
5. [Database Migration](#database-migration)
6. [SSL/TLS Configuration](#ssltls-configuration)
7. [Environment Configuration](#environment-configuration)
8. [Deployment Steps](#deployment-steps)
9. [Post-Deployment Verification](#post-deployment-verification)
10. [Monitoring & Maintenance](#monitoring--maintenance)
11. [Backup & Recovery](#backup--recovery)
12. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Tools
- Docker Engine 24.0+
- Docker Compose 2.20+
- Git 2.40+
- SSL certificate (Let's Encrypt recommended)
- Domain name configured with DNS

### Access Requirements
- SSH access to production server
- Sudo/root privileges
- GitHub/GitLab repository access

---

## Server Requirements

### Minimum Specifications
- **CPU**: 2 cores
- **RAM**: 4 GB
- **Storage**: 50 GB SSD
- **OS**: Ubuntu 22.04 LTS / Debian 12 / RHEL 9
- **Network**: Public IP address

### Recommended Specifications
- **CPU**: 4+ cores
- **RAM**: 8+ GB
- **Storage**: 100+ GB SSD
- **OS**: Ubuntu 22.04 LTS
- **Network**: Load balancer with multiple app servers

---

## Pre-Deployment Checklist

### Code Preparation
- [ ] All tests passing (`composer test`)
- [ ] Code quality checks passed (`composer phpcs`, `composer phpstan`)
- [ ] Security audit reviewed
- [ ] Dependencies up to date (`composer update`)
- [ ] Version tagged in Git (`git tag v1.0.0`)

### Infrastructure
- [ ] Production server provisioned
- [ ] Domain name configured
- [ ] SSL certificate obtained
- [ ] Firewall rules configured
- [ ] Backup system in place

### Configuration
- [ ] Production `.env` file prepared
- [ ] Stripe production keys obtained
- [ ] Email service configured (SMTP/SendGrid/Mailgun)
- [ ] Database credentials created
- [ ] Redis password set

### Third-Party Services
- [ ] Stripe account verified
- [ ] Payment webhooks configured
- [ ] Monitoring services set up (optional)
- [ ] Log aggregation configured (optional)

---

## Production Setup

### 1. Server Preparation

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify installations
docker --version
docker-compose --version
```

### 2. Clone Repository

```bash
# Create application directory
sudo mkdir -p /var/www/ecommerce
sudo chown $USER:$USER /var/www/ecommerce

# Clone repository
cd /var/www/ecommerce
git clone https://github.com/yourusername/php-ecommerce-app.git .

# Checkout production branch/tag
git checkout v1.0.0
```

### 3. Install Dependencies

```bash
# Install Composer dependencies (production only)
docker run --rm -v $(pwd):/app composer:latest install --no-dev --optimize-autoloader --no-interaction

# Set correct permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## Database Migration

### 1. Create Production Database

```bash
# Access PostgreSQL container
docker-compose -f docker-compose.prod.yml up -d postgres
docker exec -it ecommerce-postgres-prod psql -U postgres

# Create database
CREATE DATABASE ecommerce;
\q
```

### 2. Run Migrations

```bash
# Run initial schema migration
docker exec -i ecommerce-postgres-prod psql -U postgres -d ecommerce < database/migrations/001_initial_schema.sql

# Verify tables created
docker exec -it ecommerce-postgres-prod psql -U postgres -d ecommerce -c "\dt"
```

### 3. Seed Initial Data (Optional)

```bash
# Create admin user
docker exec -it ecommerce-postgres-prod psql -U postgres -d ecommerce <<EOF
INSERT INTO users (email, password_hash, name, role)
VALUES (
    'admin@example.com',
    '\$argon2id\$v=19\$m=65536,t=4,p=1\$...',
    'Admin User',
    'admin'
);
EOF

# Create sample categories
# See database/seeds/categories.sql
```

---

## SSL/TLS Configuration

### Option 1: Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com

# Certificate files will be at:
# /etc/letsencrypt/live/yourdomain.com/fullchain.pem
# /etc/letsencrypt/live/yourdomain.com/privkey.pem

# Copy certificates to project
sudo cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem docker/nginx/ssl/cert.pem
sudo cp /etc/letsencrypt/live/yourdomain.com/privkey.pem docker/nginx/ssl/key.pem

# Auto-renewal (runs twice daily)
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
```

### Option 2: Commercial SSL Certificate

```bash
# Copy your certificate files
sudo cp /path/to/your/certificate.crt docker/nginx/ssl/cert.pem
sudo cp /path/to/your/private.key docker/nginx/ssl/key.pem
sudo cp /path/to/your/ca-bundle.crt docker/nginx/ssl/ca.pem
```

---

## Environment Configuration

### Create Production `.env`

```bash
# Copy example file
cp .env.example .env

# Edit with production values
nano .env
```

### Production `.env` Template

```env
# Application
APP_NAME="Your E-Commerce Store"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_NAME=ecommerce
DB_USER=ecommerce_user
DB_PASSWORD=STRONG_RANDOM_PASSWORD_HERE

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=STRONG_RANDOM_PASSWORD_HERE

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Mail (Production SMTP)
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=YOUR_SENDGRID_API_KEY
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Stripe (PRODUCTION KEYS - DO NOT COMMIT)
STRIPE_PUBLISHABLE_KEY=pk_live_YOUR_LIVE_KEY
STRIPE_SECRET_KEY=sk_live_YOUR_LIVE_SECRET
STRIPE_WEBHOOK_SECRET=whsec_YOUR_WEBHOOK_SECRET

# Security
ENCRYPTION_KEY=base64:GENERATE_32_BYTE_KEY_HERE
CSRF_TOKEN_EXPIRE=7200

# Logging
LOG_LEVEL=warning
LOG_CHANNEL=daily

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_PER_MINUTE=60

# File Upload
MAX_UPLOAD_SIZE=10485760
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,webp
```

### Generate Encryption Key

```bash
# Generate a secure 32-byte key
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

---

## Deployment Steps

### 1. Build and Start Containers

```bash
# Build production images
docker-compose -f docker-compose.prod.yml build --no-cache

# Start all services
docker-compose -f docker-compose.prod.yml up -d

# View logs
docker-compose -f docker-compose.prod.yml logs -f
```

### 2. Verify All Services Running

```bash
# Check container status
docker-compose -f docker-compose.prod.yml ps

# Should show all services as "Up" or "healthy"
```

### 3. Configure Stripe Webhooks

1. Log in to Stripe Dashboard: https://dashboard.stripe.com
2. Navigate to: Developers → Webhooks
3. Click "Add endpoint"
4. Enter URL: `https://yourdomain.com/webhooks/stripe`
5. Select events:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
6. Copy the webhook signing secret to `.env`

### 4. Test Payment Flow

```bash
# Use Stripe test card (in test mode)
Card: 4242 4242 4242 4242
Expiry: Any future date
CVC: Any 3 digits
ZIP: Any 5 digits
```

---

## Post-Deployment Verification

### Health Checks

```bash
# Application health check
curl -f https://yourdomain.com/health

# Expected response:
# {"status":"ok","timestamp":"2025-11-23T12:00:00+00:00","environment":"production"}

# Database connectivity
docker exec ecommerce-postgres-prod pg_isready -U ecommerce_user

# Redis connectivity
docker exec ecommerce-redis-prod redis-cli -a YOUR_PASSWORD ping
```

### Security Verification

```bash
# Check SSL certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com

# Verify HTTPS redirect
curl -I http://yourdomain.com
# Should return 301/302 redirect to HTTPS

# Check security headers
curl -I https://yourdomain.com
# Should include:
# - X-Frame-Options: SAMEORIGIN
# - X-Content-Type-Options: nosniff
# - Strict-Transport-Security: max-age=31536000
```

### Functional Testing

- [ ] Homepage loads correctly
- [ ] Product pages display
- [ ] Search functionality works
- [ ] User registration works
- [ ] User login works
- [ ] Add to cart works
- [ ] Checkout flow completes
- [ ] Payment processing works
- [ ] Order confirmation email sent
- [ ] Admin login works
- [ ] Admin dashboard displays

---

## Monitoring & Maintenance

### Application Monitoring

```bash
# Access Grafana dashboard
https://yourdomain.com:3000
# Login: admin / YOUR_GRAFANA_PASSWORD

# View application logs
docker-compose -f docker-compose.prod.yml logs -f php

# View Nginx access logs
docker exec ecommerce-nginx-prod tail -f /var/log/nginx/access.log

# View Nginx error logs
docker exec ecommerce-nginx-prod tail -f /var/log/nginx/error.log
```

### Performance Monitoring

Key metrics to monitor:
- **Response Time**: < 200ms average
- **Error Rate**: < 0.1%
- **Database Connections**: Monitor pool usage
- **Redis Memory**: Should stay under 80%
- **Disk Usage**: Alert at 80% full
- **CPU Usage**: Alert at 80%

### Log Rotation

```bash
# Configure logrotate for application logs
sudo nano /etc/logrotate.d/ecommerce

# Add:
/var/www/ecommerce/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    missingok
}
```

---

## Backup & Recovery

### Automated Database Backups

Backups run automatically via the backup container (see `docker-compose.prod.yml`).

```bash
# Manual backup
docker exec ecommerce-postgres-prod pg_dump -U ecommerce_user ecommerce > backup-$(date +%Y%m%d-%H%M%S).sql

# Backup to compressed file
docker exec ecommerce-postgres-prod pg_dump -U ecommerce_user ecommerce | gzip > backup-$(date +%Y%m%d-%H%M%S).sql.gz
```

### Backup Schedule

- **Database**: Daily at 2 AM (automated)
- **Redis**: Automatic AOF persistence
- **Uploaded Files**: Daily at 3 AM (rsync to S3/backup server)
- **Application Files**: On each deployment

### Restore from Backup

```bash
# Stop application
docker-compose -f docker-compose.prod.yml stop php

# Restore database
gunzip < backup-20251123-020000.sql.gz | docker exec -i ecommerce-postgres-prod psql -U ecommerce_user ecommerce

# Restart application
docker-compose -f docker-compose.prod.yml start php
```

---

## Troubleshooting

### Application Won't Start

```bash
# Check container logs
docker-compose -f docker-compose.prod.yml logs php

# Common issues:
# 1. Database connection failed → Check DB_HOST, DB_PASSWORD in .env
# 2. Redis connection failed → Check REDIS_HOST, REDIS_PASSWORD
# 3. Permission denied → Run: sudo chown -R www-data:www-data storage
```

### 500 Internal Server Error

```bash
# Check PHP error logs
docker exec ecommerce-php-prod tail -f /var/log/php-fpm/error.log

# Check Nginx error logs
docker exec ecommerce-nginx-prod tail -f /var/log/nginx/error.log

# Enable debug mode temporarily
# In .env: APP_DEBUG=true (NEVER leave this on in production!)
```

### Database Connection Issues

```bash
# Test connection
docker exec ecommerce-php-prod php -r "new PDO('pgsql:host=postgres;port=5432;dbname=ecommerce', 'ecommerce_user', 'password');"

# Check PostgreSQL logs
docker-compose -f docker-compose.prod.yml logs postgres
```

### SSL Certificate Issues

```bash
# Test SSL configuration
curl -vI https://yourdomain.com

# Renew Let's Encrypt certificate
sudo certbot renew --dry-run
sudo certbot renew
```

### High Memory Usage

```bash
# Check container memory usage
docker stats

# Restart specific service
docker-compose -f docker-compose.prod.yml restart php

# Clear Redis cache
docker exec ecommerce-redis-prod redis-cli -a YOUR_PASSWORD FLUSHDB
```

---

## Rollback Procedure

If issues occur after deployment:

```bash
# 1. Stop current version
docker-compose -f docker-compose.prod.yml down

# 2. Checkout previous version
git checkout v1.0.0  # Previous stable tag

# 3. Restore database backup
gunzip < backups/backup-before-deployment.sql.gz | docker exec -i ecommerce-postgres-prod psql -U ecommerce_user ecommerce

# 4. Restart services
docker-compose -f docker-compose.prod.yml up -d

# 5. Verify functionality
curl -f https://yourdomain.com/health
```

---

## Support & Resources

- **Documentation**: `/docs` directory
- **Security Audit**: `SECURITY_AUDIT.md`
- **Roadmap**: `ROADMAP.md`
- **GitHub Issues**: https://github.com/yourusername/php-ecommerce-app/issues

---

**Deployment Checklist**: Print and check off each step during deployment!

✅ **Prepared**: Pre-deployment checklist complete
✅ **Deployed**: Application running in production
✅ **Verified**: All health checks passing
✅ **Monitored**: Monitoring dashboards configured
✅ **Backed Up**: First backup completed successfully

**Deployment Date**: _____________
**Deployed By**: _____________
**Version**: _____________
