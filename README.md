# PHP E-Commerce Application

A modern, production-ready e-commerce application built with **raw PHP** (no Laravel/CodeIgniter), **PostgreSQL**, and following **PSR standards** and best practices for 2025.

## 🚀 Features

- ✅ Raw PHP 8.3+ with PSR standards compliance
- ✅ PostgreSQL 16 database
- ✅ Redis for caching and sessions
- ✅ Docker development environment
- ✅ PSR-4 autoloading with Composer
- ✅ PSR-7 HTTP messages
- ✅ PSR-11 dependency injection
- ✅ PSR-15 middleware support
- ✅ Comprehensive security (OWASP Top 10 2025)
- ✅ Full-text product search
- ✅ RESTful routing
- ✅ Stripe payment integration ready
- ✅ Email notifications
- ✅ Admin panel architecture
- ✅ Testing framework setup

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

- [ROADMAP.md](ROADMAP.md) - Complete development roadmap with detailed implementation guide
- [PSR Standards](https://www.php-fig.org/psr/) - Official PSR documentation
- [Database Schema](database/migrations/001_initial_schema.sql) - Complete database structure

## 🧪 Testing

```bash
# Run all tests
docker-compose exec php vendor/bin/phpunit

# Run with coverage
docker-compose exec php vendor/bin/phpunit --coverage-html coverage

# Run specific test suite
docker-compose exec php vendor/bin/phpunit --testsuite Unit
docker-compose exec php vendor/bin/phpunit --testsuite Integration
```

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

```
GET  /                      # Home page
GET  /health                # Health check endpoint

# Authentication
GET  /login                 # Login form
POST /login                 # Process login
GET  /register              # Registration form
POST /register              # Process registration
POST /logout                # Logout

# Products
GET  /products              # Product listing
GET  /products/{slug}       # Product detail
GET  /categories/{slug}     # Category products
GET  /search                # Product search

# Shopping Cart
GET  /cart                  # View cart
POST /cart/add              # Add to cart
POST /cart/update           # Update cart
POST /cart/remove           # Remove from cart
POST /cart/clear            # Clear cart
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

## 📈 Next Steps

1. ✅ Environment is running
2. 📝 Review [ROADMAP.md](ROADMAP.md) for implementation phases
3. 🔨 Start building features (see Phase 1 in ROADMAP)
4. 🧪 Write tests as you develop
5. 📊 Monitor logs and performance

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
