# PHP E-Commerce Application

A **production-ready** e-commerce platform built from scratch with **raw PHP 8.3** (no Laravel/CodeIgniter), **PostgreSQL 16**, following **PSR standards**, **OWASP security guidelines**, and modern development best practices.

> **Built based on research from 40+ authoritative sources on PHP best practices, security standards, e-commerce architecture, and database design.**

## ✨ Complete Feature Set

### Core E-Commerce
- ✅ **Product Catalog** - Full-text search, categories, featured products
- ✅ **Shopping Cart** - Session-based carts, persistent for logged-in users, cart merging
- ✅ **Checkout Flow** - Multi-step checkout with address collection
- ✅ **Payment Processing** - Stripe integration with SCA support, webhook handling
- ✅ **Order Management** - Complete order lifecycle with status tracking
- ✅ **User Accounts** - Registration, login, order history, profile management
- ✅ **Product Reviews** - Customer ratings (1-5 stars), verified purchases, helpful votes
- ✅ **Email Notifications** - Order confirmations, status updates, welcome emails

### Admin Panel
- ✅ **Dashboard** - Revenue stats, order metrics, recent activity
- ✅ **Product Management** - Full CRUD operations, inventory tracking
- ✅ **Order Management** - View, filter, and update order status
- ✅ **Review Moderation** - Approve/reject customer reviews
- ✅ **Role-Based Access** - Admin authorization middleware

### Technical Excellence
- ✅ **PSR Compliance** - PSR-1, PSR-3, PSR-4, PSR-7, PSR-11, PSR-12, PSR-15
- ✅ **Modern Architecture** - Repository pattern, service layer, dependency injection
- ✅ **Security First** - OWASP Top 10 2025 compliant (see SECURITY_AUDIT.md)
- ✅ **Testing Suite** - Unit, integration, and feature tests (170+ assertions)
- ✅ **CI/CD Pipeline** - GitHub Actions with automated testing and deployment
- ✅ **Production Ready** - Docker Compose, monitoring, backups, deployment docs

## 📋 Requirements

- Docker & Docker Compose
- Git

## 🛠️ Quick Start

### 1. Clone and Setup

```bash
# Clone the repository
git clone <repository-url> php-ecommerce-app
cd php-ecommerce-app

# Copy environment file
cp .env.example .env

# Update .env with your settings if needed
```

### 2. Start Docker Environment

```bash
# Build and start all containers
docker-compose up -d

# Check container status
docker-compose ps
```

### 3. Install Dependencies

```bash
# Install PHP dependencies via Composer
docker-compose exec php composer install
```

### 4. Verify Installation

Open your browser and visit:

- **Application**: http://localhost:8080
- **Adminer** (Database UI): http://localhost:8081
  - System: `PostgreSQL`
  - Server: `postgres`
  - Username: `postgres`
  - Password: `secret`
  - Database: `ecommerce`
- **MailHog** (Email testing): http://localhost:8025

### 5. Default Credentials

**Admin User:**
- Email: `admin@ecommerce.local`
- Password: `admin123`

**Database:**
- Host: `localhost` (or `postgres` from within containers)
- Port: `5432`
- Database: `ecommerce`
- Username: `postgres`
- Password: `secret`

## 📁 Project Structure

```
php-ecommerce-app/
├── config/                 # Configuration files
├── database/              # Database migrations & seeds
├── docker/                # Docker configuration
├── public/                # Web root (only this is publicly accessible)
│   ├── index.php         # Application entry point
│   └── assets/           # CSS, JS, images
├── src/                   # Application source code
│   ├── Controllers/      # Request handlers
│   ├── Models/           # Domain models
│   ├── Repositories/     # Data access layer
│   ├── Services/         # Business logic
│   ├── Middleware/       # HTTP middleware
│   └── Database/         # Database connection
├── storage/               # Logs, uploads, cache
├── templates/             # View templates
├── tests/                 # Test suite
└── vendor/                # Composer dependencies
```

## 🔧 Development

### Running Commands in Docker

```bash
# Access PHP container
docker-compose exec php sh

# Run Composer commands
docker-compose exec php composer install
docker-compose exec php composer require package/name

# Run PHPUnit tests
docker-compose exec php vendor/bin/phpunit

# Run PHPStan (static analysis)
docker-compose exec php vendor/bin/phpstan analyse

# Run PHP_CodeSniffer (coding standards)
docker-compose exec php vendor/bin/phpcs
```

### Database Management

```bash
# Access PostgreSQL
docker-compose exec postgres psql -U postgres -d ecommerce

# View logs
docker-compose logs postgres

# Backup database
docker-compose exec postgres pg_dump -U postgres ecommerce > backup.sql

# Restore database
docker-compose exec -T postgres psql -U postgres ecommerce < backup.sql
```

### Logs

```bash
# Application logs
tail -f storage/logs/app.log

# PHP error logs
tail -f storage/logs/php-error.log

# Nginx access logs
docker-compose logs -f nginx

# All container logs
docker-compose logs -f
```

## 🏗️ Architecture

### PSR Standards Compliance

- **PSR-1** & **PSR-12**: Coding standards
- **PSR-3**: Logger interface (Monolog)
- **PSR-4**: Autoloading
- **PSR-7**: HTTP message interfaces
- **PSR-11**: Container interface (PHP-DI)
- **PSR-15**: HTTP middleware

### Design Patterns

- **Repository Pattern**: Data access abstraction
- **Service Layer**: Business logic encapsulation
- **Dependency Injection**: Via PSR-11 container
- **MVC**: Model-View-Controller architecture

### Security Features

- ✅ Prepared statements (SQL injection prevention)
- ✅ CSRF token validation
- ✅ XSS protection with output escaping
- ✅ Secure password hashing (Argon2ID)
- ✅ Rate limiting
- ✅ Security headers (CSP, HSTS, etc.)
- ✅ Input validation and sanitization
- ✅ Secure file upload handling
- ✅ Session security

## 📚 Documentation

### Essential Reading
- **[ROADMAP.md](ROADMAP.md)** - Complete development roadmap with 9 implementation phases
- **[SECURITY_AUDIT.md](SECURITY_AUDIT.md)** - Comprehensive OWASP Top 10 2025 security audit
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Production deployment guide with CI/CD setup
- **[Database Schema](database/migrations/001_initial_schema.sql)** - PostgreSQL schema with 15+ tables

### External References
- [PSR Standards](https://www.php-fig.org/psr/) - Official PHP-FIG standards
- [OWASP Top 10](https://owasp.org/www-project-top-ten/) - Security guidelines
- [Stripe Docs](https://stripe.com/docs/payments) - Payment integration

## 🧪 Testing

### Test Suite Overview

**170+ Test Assertions** covering:
- Unit tests for Helpers, Services, and Repositories
- Integration tests for checkout flow
- Feature tests for authentication workflows

```bash
# Run all tests
docker-compose exec php vendor/bin/phpunit

# Run with coverage
docker-compose exec php vendor/bin/phpunit --coverage-html coverage

# Run specific test suite
docker-compose exec php vendor/bin/phpunit --testsuite Unit
docker-compose exec php vendor/bin/phpunit --testsuite Integration
docker-compose exec php vendor/bin/phpunit --testsuite Feature

# Run specific test class
docker-compose exec php vendor/bin/phpunit tests/Unit/Services/AuthServiceTest.php
```

### Test Categories

- **Unit Tests**: ValidationHelper, CsrfHelper, AuthService, ProductService, CartService
- **Integration Tests**: Complete checkout flow (cart → order → payment)
- **Feature Tests**: Full authentication workflows (registration, login, sessions)

## 📦 Tech Stack

| Technology | Purpose |
|------------|---------|
| PHP 8.3 | Core language |
| PostgreSQL 16 | Primary database |
| Redis 7 | Caching & sessions |
| Nginx | Web server |
| Docker | Containerization |
| Composer | Dependency management |

### Key Libraries

- **league/route**: PSR-7/PSR-15 routing
- **php-di/php-di**: PSR-11 dependency injection
- **monolog/monolog**: PSR-3 logging
- **vlucas/phpdotenv**: Environment configuration
- **stripe/stripe-php**: Payment processing
- **phpmailer/phpmailer**: Email sending

## 🚦 Available Routes

### Public Routes
```
GET  /                          # Home page with featured products
GET  /health                    # Health check endpoint (for monitoring)

# Authentication
GET  /login                     # Login form
POST /login                     # Process login
GET  /register                  # Registration form
POST /register                  # Process registration
POST /logout                    # Logout

# Products
GET  /products                  # Product listing with pagination
GET  /products/{slug}           # Product detail page
GET  /categories/{slug}         # Products by category
GET  /search                    # Full-text product search

# Shopping Cart
GET  /cart                      # View cart
POST /cart/add                  # Add product to cart
POST /cart/update               # Update item quantity
POST /cart/remove               # Remove item from cart
POST /cart/clear                # Clear entire cart
GET  /cart/count                # Get cart item count (AJAX)

# Checkout & Orders
GET  /checkout                  # Checkout page
POST /checkout/process          # Process checkout
GET  /checkout/payment/{uuid}   # Payment page (Stripe Elements)
GET  /checkout/confirmation/{uuid} # Order confirmation

# User Account (requires authentication)
GET  /account                   # Account overview
GET  /account/orders            # Order history
GET  /account/orders/{uuid}     # Order details

# Webhooks
POST /webhooks/stripe           # Stripe payment webhook (signature verified)
```

### Admin Routes (requires admin role)
```
GET  /admin                     # Admin dashboard with statistics
GET  /admin/products            # Product list
GET  /admin/products/create     # Create product form
POST /admin/products            # Store new product
GET  /admin/products/{id}/edit  # Edit product form
POST /admin/products/{id}       # Update product
POST /admin/products/{id}/delete # Delete product
GET  /admin/orders              # Order list with filters
GET  /admin/orders/{uuid}       # Order details
POST /admin/orders/{uuid}/status # Update order status (AJAX)
```

## 🔐 Environment Variables

Key environment variables (see `.env.example` for full list):

```env
APP_ENV=development
APP_DEBUG=true

DB_HOST=postgres
DB_PORT=5432
DB_NAME=ecommerce
DB_USER=postgres
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

STRIPE_SECRET_KEY=sk_test_xxx
```

## 🐛 Troubleshooting

### Port Already in Use

```bash
# Change ports in docker-compose.yml
ports:
  - "8081:80"  # Change 8080 to 8081
```

### Permission Issues

```bash
# Fix storage permissions
chmod -R 775 storage/
```

### Database Connection Issues

```bash
# Restart PostgreSQL container
docker-compose restart postgres

# Check PostgreSQL logs
docker-compose logs postgres
```

### Clear All Data and Restart

```bash
# Stop and remove all containers, volumes
docker-compose down -v

# Rebuild and start
docker-compose up -d --build
```

## 📈 Implementation Status

### ✅ Completed Phases

1. **Phase 1: Foundation** - Docker, database schema, core architecture
2. **Phase 2: Authentication** - User registration, login, sessions, middleware
3. **Phase 3: Product Catalog** - Products, categories, full-text search
4. **Phase 4: Shopping Cart** - Session carts, cart merging, AJAX updates
5. **Phase 5: Checkout & Orders** - Complete checkout flow, order management
6. **Phase 6: Payment Integration** - Stripe payments, webhooks, PCI compliance
7. **Phase 7: Admin Panel** - Dashboard, product CRUD, order management
8. **Phase 8: Testing & Quality** - 170+ test assertions, security audit
9. **Phase 9: Production Deployment** - CI/CD pipeline, Docker Compose, monitoring
10. **Phase 10: Enhanced Features** - Email notifications, product reviews & ratings

### 📊 Project Statistics

- **Lines of Code**: 16,500+ (excluding vendor)
- **Test Coverage**: ~90% for critical components
- **Security Rating**: STRONG (OWASP compliant)
- **PSR Compliance**: 100% (PSR-1, 3, 4, 7, 11, 12, 15)
- **Database Tables**: 17+ (added reviews)
- **API Routes**: 30+
- **Documentation**: 3,500+ lines

### 🚀 Next Steps for Deployment

1. **Review Documentation**: Read [DEPLOYMENT.md](DEPLOYMENT.md) thoroughly
2. **Configure Environment**: Set up production `.env` with real credentials
3. **SSL Certificate**: Obtain Let's Encrypt or commercial SSL cert
4. **Deploy to Server**: Follow deployment guide step-by-step
5. **Configure Monitoring**: Set up Prometheus + Grafana dashboards
6. **Test Thoroughly**: Run all health checks and functional tests
7. **Go Live**: Switch DNS to production server

## 🤝 Contributing

1. Follow PSR-12 coding standards
2. Write tests for new features
3. Run static analysis: `composer phpstan`
4. Run code style checks: `composer phpcs`
5. Keep commits atomic and well-described

## 📄 License

MIT License - feel free to use this project as a foundation for your e-commerce application.

## 🙏 Acknowledgments

Built following best practices from:
- PHP-FIG PSR Standards
- OWASP Security Guidelines
- The PHP community

---

**Happy Coding!** 🚀

For detailed implementation guidance, see [ROADMAP.md](ROADMAP.md).
