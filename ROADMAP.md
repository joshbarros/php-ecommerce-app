# PHP E-Commerce Application Development Roadmap

> **Last Updated**: 2025-11-23
> **Research Sources**: 40+ authoritative sources (2025)
> **Technology Stack**: Raw PHP 8.3+ | PostgreSQL 16 | Redis | Docker

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Technology Stack & Requirements](#technology-stack--requirements)
3. [PSR Standards Compliance](#psr-standards-compliance)
4. [Project Structure](#project-structure)
5. [Database Architecture](#database-architecture)
6. [Security Implementation](#security-implementation)
7. [Core Application Architecture](#core-application-architecture)
8. [Development Environment Setup](#development-environment-setup)
9. [Implementation Phases](#implementation-phases)
10. [Testing Strategy](#testing-strategy)
11. [Deployment & Production](#deployment--production)
12. [Code Quality & Standards](#code-quality--standards)
13. [Performance Optimization](#performance-optimization)
14. [Reference Materials](#reference-materials)

---

## 1. Project Overview

### 1.1 Vision
Build a production-ready e-commerce application using **raw PHP** (no Laravel/CodeIgniter) with **PostgreSQL**, following modern PSR standards and best practices for 2025.

### 1.2 Core Principles
- ✅ **Framework-agnostic**: Pure PHP with PSR standards
- ✅ **Security-first**: OWASP Top 10 2025 compliance
- ✅ **Clean Architecture**: Repository & Service patterns
- ✅ **Type-safe**: PHP 8.3+ features (typed properties, enums, attributes)
- ✅ **Test-driven**: 80%+ code coverage target
- ✅ **Production-ready**: Docker, logging, monitoring

### 1.3 Key Features
- User authentication & authorization
- Product catalog with categories
- Shopping cart (session & persistent)
- Order management
- Payment processing (Stripe integration)
- Admin panel
- RESTful API
- Email notifications
- Search functionality
- Product reviews & ratings

---

## 2. Technology Stack & Requirements

### 2.1 Required Software

| Component | Version | Purpose |
|-----------|---------|---------|
| **PHP** | 8.3+ | Core language (typed properties, enums, attributes) |
| **PostgreSQL** | 16+ | Primary database |
| **Redis** | 7+ | Session storage, caching, rate limiting |
| **Composer** | 2.x | Dependency management |
| **Docker** | 24+ | Development & deployment |
| **Nginx/Apache** | Latest | Web server |

### 2.2 PHP Extensions Required
```ini
# Required extensions
pdo_pgsql      # PostgreSQL PDO driver
redis          # Redis extension
mbstring       # Multibyte string support
intl           # Internationalization
gd             # Image processing
curl           # HTTP client
openssl        # Encryption
fileinfo       # File type detection
```

### 2.3 Core Composer Dependencies

```json
{
    "require": {
        "php": "^8.3",

        // PSR Standards
        "psr/http-message": "^2.0",
        "psr/http-server-handler": "^1.0",
        "psr/http-server-middleware": "^1.0",
        "psr/container": "^2.0",
        "psr/log": "^3.0",

        // Environment & Configuration
        "vlucas/phpdotenv": "^5.6",

        // Dependency Injection (PSR-11)
        "php-di/php-di": "^7.0",

        // Routing & HTTP (PSR-7, PSR-15)
        "league/route": "^4.0",
        "laminas/laminas-diactoros": "^3.0",

        // Logging (PSR-3)
        "monolog/monolog": "^3.5",

        // HTTP Client (PSR-18)
        "guzzlehttp/guzzle": "^7.10",

        // Database
        "doctrine/dbal": "^3.8",

        // Validation
        "respect/validation": "^2.3",

        // Payment Gateway
        "stripe/stripe-php": "^13.0",

        // Email
        "phpmailer/phpmailer": "^6.9",

        // Template Engine (optional)
        "twig/twig": "^3.8"
    },
    "require-dev": {
        // Testing
        "phpunit/phpunit": "^11.0",
        "mockery/mockery": "^1.6",

        // Static Analysis
        "phpstan/phpstan": "^2.0",
        "vimeo/psalm": "^5.20",

        // Code Style
        "squizlabs/php_codesniffer": "^3.10",
        "friendsofphp/php-cs-fixer": "^3.48"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true
    }
}
```

---

## 3. PSR Standards Compliance

### 3.1 PSR Standards Implementation

| PSR | Name | Implementation | Usage |
|-----|------|----------------|-------|
| **PSR-1** | Basic Coding Standard | Required | File naming, class naming |
| **PSR-4** | Autoloading | Composer | Namespace → Directory mapping |
| **PSR-7** | HTTP Messages | laminas-diactoros | Request/Response objects |
| **PSR-11** | Container | PHP-DI | Dependency injection |
| **PSR-12** | Coding Style | PHP_CodeSniffer | Code formatting |
| **PSR-15** | HTTP Handlers | league/route | Middleware |
| **PSR-3** | Logger | Monolog | Application logging |
| **PSR-18** | HTTP Client | Guzzle | External API calls |

### 3.2 PSR-4 Autoloading Structure

```
src/
├── Controllers/          # App\Controllers
├── Models/              # App\Models
├── Services/            # App\Services
├── Repositories/        # App\Repositories
├── Middleware/          # App\Middleware
├── Validators/          # App\Validators
├── Exceptions/          # App\Exceptions
└── Helpers/             # App\Helpers
```

### 3.3 PSR-12 Coding Style Examples

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Product service handling business logic
 */
final class ProductService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getProductById(int $id): ?Product
    {
        try {
            return $this->productRepository->findById($id);
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve product', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new ProductNotFoundException("Product not found: {$id}");
        }
    }
}
```

---

## 4. Project Structure

### 4.1 Complete Directory Layout

```
php-ecommerce-app/
│
├── .github/
│   └── workflows/
│       └── ci.yml                 # GitHub Actions CI/CD
│
├── config/
│   ├── app.php                    # Application configuration
│   ├── database.php               # Database connection
│   ├── routes.php                 # Route definitions
│   ├── container.php              # DI container setup
│   ├── middleware.php             # Middleware configuration
│   └── logging.php                # Logging configuration
│
├── database/
│   ├── migrations/                # Database migrations
│   │   ├── 001_create_users_table.sql
│   │   ├── 002_create_products_table.sql
│   │   └── ...
│   ├── seeds/                     # Sample data
│   │   ├── categories.sql
│   │   └── products.sql
│   └── schema.sql                 # Complete schema
│
├── public/                        # WEB ROOT (only this exposed)
│   ├── index.php                  # Application entry point
│   ├── .htaccess                  # Apache rewrite rules
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── uploads/                   # Temporary upload processing
│
├── src/
│   ├── Controllers/
│   │   ├── Web/                   # Web controllers
│   │   │   ├── HomeController.php
│   │   │   ├── ProductController.php
│   │   │   ├── CartController.php
│   │   │   ├── CheckoutController.php
│   │   │   └── AuthController.php
│   │   ├── Api/                   # API controllers
│   │   │   ├── ProductApiController.php
│   │   │   └── OrderApiController.php
│   │   └── Admin/                 # Admin controllers
│   │       ├── DashboardController.php
│   │       ├── ProductManagementController.php
│   │       └── OrderManagementController.php
│   │
│   ├── Models/                    # Domain models
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Category.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   └── Cart.php
│   │
│   ├── Repositories/              # Data access layer
│   │   ├── Interfaces/
│   │   │   ├── UserRepositoryInterface.php
│   │   │   ├── ProductRepositoryInterface.php
│   │   │   └── OrderRepositoryInterface.php
│   │   ├── UserRepository.php
│   │   ├── ProductRepository.php
│   │   ├── CategoryRepository.php
│   │   ├── OrderRepository.php
│   │   └── CartRepository.php
│   │
│   ├── Services/                  # Business logic layer
│   │   ├── AuthService.php
│   │   ├── ProductService.php
│   │   ├── CartService.php
│   │   ├── OrderService.php
│   │   ├── PaymentService.php
│   │   ├── EmailService.php
│   │   └── SearchService.php
│   │
│   ├── Middleware/
│   │   ├── AuthenticationMiddleware.php
│   │   ├── AuthorizationMiddleware.php
│   │   ├── CsrfMiddleware.php
│   │   ├── RateLimitMiddleware.php
│   │   ├── LoggingMiddleware.php
│   │   └── ValidationMiddleware.php
│   │
│   ├── Validators/
│   │   ├── ProductValidator.php
│   │   ├── OrderValidator.php
│   │   └── UserValidator.php
│   │
│   ├── Exceptions/
│   │   ├── NotFoundException.php
│   │   ├── ValidationException.php
│   │   ├── AuthenticationException.php
│   │   ├── AuthorizationException.php
│   │   └── PaymentException.php
│   │
│   ├── Helpers/
│   │   ├── CsrfHelper.php
│   │   ├── SessionHelper.php
│   │   ├── ValidationHelper.php
│   │   └── ImageHelper.php
│   │
│   └── Database/
│       ├── Connection.php         # PDO connection manager
│       └── QueryBuilder.php       # Optional query builder
│
├── storage/                       # NOT web-accessible
│   ├── logs/
│   │   ├── app.log
│   │   ├── error.log
│   │   └── security.log
│   ├── uploads/                   # Permanent file storage
│   │   ├── products/
│   │   └── users/
│   ├── cache/
│   └── sessions/
│
├── templates/                     # View templates
│   ├── layouts/
│   │   ├── main.php
│   │   └── admin.php
│   ├── pages/
│   │   ├── home.php
│   │   ├── product-list.php
│   │   ├── product-detail.php
│   │   ├── cart.php
│   │   └── checkout.php
│   ├── auth/
│   │   ├── login.php
│   │   └── register.php
│   └── emails/
│       ├── order-confirmation.php
│       └── password-reset.php
│
├── tests/
│   ├── Unit/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   └── Validators/
│   ├── Integration/
│   │   ├── Controllers/
│   │   └── Database/
│   ├── TestCase.php
│   └── bootstrap.php
│
├── docker/
│   ├── nginx/
│   │   └── default.conf
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   └── postgres/
│       └── init.sql
│
├── .env.example                   # Environment template
├── .gitignore
├── composer.json
├── composer.lock
├── docker-compose.yml
├── phpunit.xml
├── phpstan.neon
├── phpcs.xml
├── README.md
└── ROADMAP.md                     # This file
```

### 4.2 Directory Purpose Summary

| Directory | Purpose | Web Access |
|-----------|---------|------------|
| `public/` | Entry point, static assets | ✅ YES |
| `src/` | Application source code | ❌ NO |
| `config/` | Configuration files | ❌ NO |
| `storage/` | Logs, uploads, cache | ❌ NO |
| `database/` | Migrations, seeds | ❌ NO |
| `templates/` | View templates | ❌ NO |
| `tests/` | Test suite | ❌ NO |
| `vendor/` | Composer dependencies | ❌ NO |

---

## 5. Database Architecture

### 5.1 Complete PostgreSQL Schema

```sql
-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================
-- USERS & AUTHENTICATION
-- ============================================

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    role VARCHAR(50) DEFAULT 'customer' CHECK (role IN ('customer', 'admin', 'moderator')),
    email_verified_at TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP
);

CREATE TABLE user_addresses (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    address_type VARCHAR(20) CHECK (address_type IN ('billing', 'shipping')),
    street_address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20) NOT NULL,
    country VARCHAR(2) NOT NULL,
    is_default BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

-- ============================================
-- PRODUCTS & CATALOG
-- ============================================

CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    parent_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    image_url VARCHAR(255),
    is_active BOOLEAN DEFAULT true,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT,
    short_description VARCHAR(500),
    price DECIMAL(10, 2) NOT NULL CHECK (price >= 0),
    compare_price DECIMAL(10, 2) CHECK (compare_price >= 0),
    cost DECIMAL(10, 2) CHECK (cost >= 0),
    stock_quantity INTEGER DEFAULT 0 CHECK (stock_quantity >= 0),
    low_stock_threshold INTEGER DEFAULT 10,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT true,
    is_featured BOOLEAN DEFAULT false,
    weight DECIMAL(10, 2),
    dimensions JSONB,
    metadata JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE product_images (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    image_url VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT false,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE product_variants (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INTEGER DEFAULT 0,
    attributes JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- SHOPPING CART
-- ============================================

CREATE TABLE cart (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    CONSTRAINT cart_user_or_session CHECK (
        (user_id IS NOT NULL AND session_id IS NULL) OR
        (user_id IS NULL AND session_id IS NOT NULL)
    )
);

CREATE TABLE cart_items (
    id SERIAL PRIMARY KEY,
    cart_id INTEGER REFERENCES cart(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    product_variant_id INTEGER REFERENCES product_variants(id) ON DELETE CASCADE,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    price_at_addition DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- ORDERS & PAYMENTS
-- ============================================

CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,

    -- Status tracking
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN (
        'pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'
    )),
    payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN (
        'pending', 'paid', 'failed', 'refunded', 'partially_refunded'
    )),

    -- Pricing
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    shipping_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,

    -- Shipping information
    shipping_method VARCHAR(100),
    tracking_number VARCHAR(255),

    -- Addresses (denormalized for historical record)
    billing_address JSONB NOT NULL,
    shipping_address JSONB NOT NULL,

    -- Customer info snapshot
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(20),
    customer_name VARCHAR(255),

    -- Additional data
    notes TEXT,
    metadata JSONB,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shipped_at TIMESTAMP,
    delivered_at TIMESTAMP,
    cancelled_at TIMESTAMP
);

CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
    product_variant_id INTEGER REFERENCES product_variants(id) ON DELETE SET NULL,

    -- Product snapshot (for historical accuracy)
    product_name VARCHAR(255) NOT NULL,
    product_sku VARCHAR(100) NOT NULL,

    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payments (
    id SERIAL PRIMARY KEY,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    payment_method VARCHAR(50) NOT NULL CHECK (payment_method IN (
        'credit_card', 'stripe', 'paypal', 'bank_transfer'
    )),
    transaction_id VARCHAR(255),
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN (
        'pending', 'completed', 'failed', 'refunded'
    )),
    payment_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- REVIEWS & RATINGS
-- ============================================

CREATE TABLE product_reviews (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    order_id INTEGER REFERENCES orders(id) ON DELETE SET NULL,
    rating INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255),
    review_text TEXT,
    is_verified_purchase BOOLEAN DEFAULT false,
    is_approved BOOLEAN DEFAULT false,
    helpful_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- COUPONS & DISCOUNTS
-- ============================================

CREATE TABLE coupons (
    id SERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type VARCHAR(20) CHECK (discount_type IN ('percentage', 'fixed')),
    discount_value DECIMAL(10, 2) NOT NULL,
    min_purchase_amount DECIMAL(10, 2),
    max_discount_amount DECIMAL(10, 2),
    usage_limit INTEGER,
    usage_count INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    starts_at TIMESTAMP,
    expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE coupon_usage (
    id SERIAL PRIMARY KEY,
    coupon_id INTEGER REFERENCES coupons(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES orders(id) ON DELETE CASCADE,
    discount_amount DECIMAL(10, 2) NOT NULL,
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Users
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_created_at ON users(created_at);

-- Products
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_slug ON products(slug);
CREATE INDEX idx_products_is_active ON products(is_active);
CREATE INDEX idx_products_is_featured ON products(is_featured);
CREATE INDEX idx_products_created_at ON products(created_at DESC);
CREATE INDEX idx_products_name_trgm ON products USING gin(name gin_trgm_ops);

-- Categories
CREATE INDEX idx_categories_parent ON categories(parent_id);
CREATE INDEX idx_categories_slug ON categories(slug);

-- Orders
CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at DESC);
CREATE INDEX idx_orders_order_number ON orders(order_number);

-- Cart
CREATE INDEX idx_cart_user ON cart(user_id);
CREATE INDEX idx_cart_session ON cart(session_id);
CREATE INDEX idx_cart_expires ON cart(expires_at);

-- Reviews
CREATE INDEX idx_reviews_product ON product_reviews(product_id);
CREATE INDEX idx_reviews_user ON product_reviews(user_id);
CREATE INDEX idx_reviews_approved ON product_reviews(is_approved);

-- ============================================
-- TRIGGERS FOR UPDATED_AT
-- ============================================

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_products_updated_at BEFORE UPDATE ON products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_categories_updated_at BEFORE UPDATE ON categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_orders_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- FULL-TEXT SEARCH SETUP
-- ============================================

-- Enable pg_trgm for fuzzy search
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Create text search configuration
ALTER TABLE products ADD COLUMN search_vector tsvector;

CREATE INDEX idx_products_search ON products USING gin(search_vector);

CREATE OR REPLACE FUNCTION products_search_trigger() RETURNS trigger AS $$
BEGIN
    NEW.search_vector :=
        setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
        setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'B') ||
        setweight(to_tsvector('english', COALESCE(NEW.sku, '')), 'C');
    RETURN NEW;
END
$$ LANGUAGE plpgsql;

CREATE TRIGGER tsvector_update BEFORE INSERT OR UPDATE
    ON products FOR EACH ROW EXECUTE FUNCTION products_search_trigger();
```

### 5.2 Database Connection Class

```php
<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

final class Connection
{
    private static ?PDO $instance = null;

    private function __construct()
    {
        // Prevent direct instantiation
    }

    public static function getInstance(LoggerInterface $logger = null): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;options=\'--client_encoding=UTF8\'',
                    $_ENV['DB_HOST'],
                    $_ENV['DB_PORT'],
                    $_ENV['DB_NAME']
                );

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_PERSISTENT => false,
                ];

                self::$instance = new PDO(
                    $dsn,
                    $_ENV['DB_USER'],
                    $_ENV['DB_PASSWORD'],
                    $options
                );

                $logger?->info('Database connection established');

            } catch (PDOException $e) {
                $logger?->critical('Database connection failed', [
                    'error' => $e->getMessage()
                ]);
                throw new \RuntimeException(
                    'Database connection failed: ' . $e->getMessage()
                );
            }
        }

        return self::$instance;
    }

    public static function disconnect(): void
    {
        self::$instance = null;
    }

    private function __clone()
    {
        // Prevent cloning
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
```

---

## 6. Security Implementation

### 6.1 OWASP Top 10 2025 Mitigation

| Vulnerability | Mitigation Strategy | Implementation |
|---------------|---------------------|----------------|
| **SQL Injection** | Prepared statements | PDO with bound parameters |
| **XSS** | Output encoding + CSP | `htmlspecialchars()` + headers |
| **CSRF** | Synchronizer tokens | Per-request tokens |
| **Authentication** | Strong password hashing | Argon2ID |
| **Sensitive Data** | Encryption + HTTPS | TLS 1.3, encrypted storage |
| **Broken Access Control** | RBAC | Middleware authorization |
| **Security Misconfiguration** | Hardened defaults | Secure headers |
| **Insecure Deserialization** | Avoid unserialize | JSON instead |
| **Vulnerable Components** | Dependency scanning | Composer audit |
| **Insufficient Logging** | Comprehensive logging | Monolog + monitoring |

### 6.2 Security Headers Configuration

```php
<?php

// config/security.php

return [
    'headers' => [
        // Prevent XSS
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',

        // Content Security Policy
        'Content-Security-Policy' => implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://js.stripe.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src https://js.stripe.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests"
        ]),

        // HTTPS enforcement
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',

        // Referrer policy
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        // Permissions policy
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
    ],

    'session' => [
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'sid_length' => 48,
        'sid_bits_per_character' => 6
    ]
];
```

### 6.3 CSRF Protection Implementation

```php
<?php

declare(strict_types=1);

namespace App\Helpers;

final class CsrfHelper
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;

    public static function generateToken(): string
    {
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        }

        return $_SESSION[self::TOKEN_NAME];
    }

    public static function validateToken(?string $token): bool
    {
        if (!isset($_SESSION[self::TOKEN_NAME]) || $token === null) {
            return false;
        }

        // Use hash_equals to prevent timing attacks
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }

    public static function regenerateToken(): string
    {
        $_SESSION[self::TOKEN_NAME] = bin2hex(random_bytes(self::TOKEN_LENGTH));
        return $_SESSION[self::TOKEN_NAME];
    }

    public static function getTokenField(): string
    {
        $token = self::generateToken();
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
}
```

### 6.4 Input Validation & Sanitization

```php
<?php

declare(strict_types=1);

namespace App\Validators;

final class InputValidator
{
    public static function email(string $email): string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        return $email;
    }

    public static function integer(mixed $value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $options = ['options' => ['min_range' => $min, 'max_range' => $max]];
        $int = filter_var($value, FILTER_VALIDATE_INT, $options);

        if ($int === false) {
            throw new ValidationException("Invalid integer or out of range [{$min}, {$max}]");
        }

        return $int;
    }

    public static function string(string $value, int $minLength = 0, int $maxLength = 255): string
    {
        $value = trim($value);
        $length = mb_strlen($value);

        if ($length < $minLength || $length > $maxLength) {
            throw new ValidationException(
                "String length must be between {$minLength} and {$maxLength}"
            );
        }

        return $value;
    }

    public static function url(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid URL format');
        }

        // Only allow http and https
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new ValidationException('Only HTTP and HTTPS URLs are allowed');
        }

        return $url;
    }

    public static function sanitizeHtml(string $html): string
    {
        return htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
```

### 6.5 File Upload Security

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FileUploadException;
use Psr\Log\LoggerInterface;

final class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $uploadPath
    ) {
    }

    public function uploadProductImage(array $file): string
    {
        // Validate upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new FileUploadException('File upload failed');
        }

        // Check file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new FileUploadException('File size exceeds maximum allowed');
        }

        // Validate MIME type using file content (not client-supplied type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new FileUploadException('Invalid file type');
        }

        // Verify it's actually an image
        if (!exif_imagetype($file['tmp_name'])) {
            throw new FileUploadException('File is not a valid image');
        }

        // Re-process image to strip metadata and potential malicious code
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png' => imagecreatefrompng($file['tmp_name']),
            'image/gif' => imagecreatefromgif($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            default => throw new FileUploadException('Unsupported image type')
        };

        // Generate unique filename
        $filename = uniqid('product_', true) . '.jpg';
        $filepath = $this->uploadPath . '/' . $filename;

        // Save re-processed image
        imagejpeg($image, $filepath, 90);
        imagedestroy($image);

        // Set secure permissions
        chmod($filepath, 0644);

        $this->logger->info('Product image uploaded', ['filename' => $filename]);

        return $filename;
    }
}
```

### 6.6 Rate Limiting with Redis

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Redis;

final class RateLimiter
{
    public function __construct(
        private readonly Redis $redis
    ) {
    }

    /**
     * Check if rate limit is exceeded
     *
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param int $limit Maximum requests allowed
     * @param int $window Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public function check(string $identifier, int $limit, int $window): bool
    {
        $key = "rate_limit:{$identifier}";
        $current = $this->redis->incr($key);

        if ($current === 1) {
            $this->redis->expire($key, $window);
        }

        return $current <= $limit;
    }

    /**
     * Get remaining requests
     */
    public function remaining(string $identifier, int $limit): int
    {
        $key = "rate_limit:{$identifier}";
        $current = (int) $this->redis->get($key);
        return max(0, $limit - $current);
    }

    /**
     * Reset rate limit for identifier
     */
    public function reset(string $identifier): void
    {
        $key = "rate_limit:{$identifier}";
        $this->redis->del($key);
    }
}
```

---

## 7. Core Application Architecture

### 7.1 Entry Point (public/index.php)

```php
<?php

declare(strict_types=1);

use App\Database\Connection;
use DI\ContainerBuilder;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Router;

// Define paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/src');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Load Composer autoloader
require ROOT_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']);

// Error handling based on environment
if ($_ENV['APP_ENV'] === 'production') {
    ini_set('display_errors', '0');
    error_reporting(0);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Build DI container
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(CONFIG_PATH . '/container.php');
$container = $containerBuilder->build();

// Create PSR-7 request
$request = ServerRequestFactory::fromGlobals();

// Get router from container
$router = $container->get(Router::class);

// Load routes
require CONFIG_PATH . '/routes.php';

try {
    // Dispatch request
    $response = $router->dispatch($request);

    // Emit response
    (new SapiEmitter())->emit($response);

} catch (\Throwable $e) {
    // Log error
    $logger = $container->get(\Psr\Log\LoggerInterface::class);
    $logger->error('Application error', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    // Show error page
    http_response_code(500);
    if ($_ENV['APP_ENV'] !== 'production') {
        echo '<pre>' . $e . '</pre>';
    } else {
        echo 'An error occurred. Please try again later.';
    }
}
```

### 7.2 Repository Pattern Implementation

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findAll(int $limit = 20, int $offset = 0): array;
    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array;
    public function search(string $query, int $limit = 20): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function decreaseStock(int $id, int $quantity): bool;
    public function increaseStock(int $id, int $quantity): bool;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Repositories\Interfaces\ProductRepositoryInterface;
use PDO;

final class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly PDO $db
    ) {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.id = :id AND p.is_active = true'
        );

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.slug = :slug AND p.is_active = true'
        );

        $stmt->execute(['slug' => $slug]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = true
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findByCategory(int $categoryId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*
             FROM products p
             WHERE p.category_id = :category_id
             AND p.is_active = true
             ORDER BY p.created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function search(string $query, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name as category_name,
                    ts_rank(p.search_vector, plainto_tsquery(:query)) as rank
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.search_vector @@ plainto_tsquery(:query)
             AND p.is_active = true
             ORDER BY rank DESC
             LIMIT :limit'
        );

        $stmt->bindValue(':query', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (
                sku, name, slug, description, short_description,
                price, compare_price, stock_quantity, category_id, is_active
            ) VALUES (
                :sku, :name, :slug, :description, :short_description,
                :price, :compare_price, :stock_quantity, :category_id, :is_active
            ) RETURNING id'
        );

        $stmt->execute($data);
        $result = $stmt->fetch();

        return (int) $result['id'];
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
        }

        $sql = 'UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);

        $data['id'] = $id;
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        // Soft delete
        $stmt = $this->db->prepare('UPDATE products SET is_active = false WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function decreaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity - :quantity
             WHERE id = :id AND stock_quantity >= :quantity'
        );

        return $stmt->execute([
            'id' => $id,
            'quantity' => $quantity
        ]);
    }

    public function increaseStock(int $id, int $quantity): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE products
             SET stock_quantity = stock_quantity + :quantity
             WHERE id = :id'
        );

        return $stmt->execute([
            'id' => $id,
            'quantity' => $quantity
        ]);
    }
}
```

### 7.3 Service Layer Implementation

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\OrderException;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use PDO;
use Psr\Log\LoggerInterface;

final class OrderService
{
    public function __construct(
        private readonly PDO $db,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly PaymentService $paymentService,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create a new order with transaction support
     */
    public function createOrder(int $userId, array $items, array $shippingAddress, array $billingAddress): int
    {
        $this->db->beginTransaction();

        try {
            // Validate stock availability
            foreach ($items as $item) {
                $product = $this->productRepository->findById($item['product_id']);

                if (!$product) {
                    throw new OrderException("Product not found: {$item['product_id']}");
                }

                if ($product['stock_quantity'] < $item['quantity']) {
                    throw new OrderException(
                        "Insufficient stock for product: {$product['name']}"
                    );
                }
            }

            // Calculate totals
            $subtotal = $this->calculateSubtotal($items);
            $taxAmount = $this->calculateTax($subtotal);
            $shippingAmount = $this->calculateShipping($items, $shippingAddress);
            $totalAmount = $subtotal + $taxAmount + $shippingAmount;

            // Generate order number
            $orderNumber = $this->generateOrderNumber();

            // Create order
            $orderId = $this->orderRepository->create([
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total_amount' => $totalAmount,
                'shipping_address' => json_encode($shippingAddress),
                'billing_address' => json_encode($billingAddress)
            ]);

            // Create order items and decrease stock
            foreach ($items as $item) {
                $product = $this->productRepository->findById($item['product_id']);

                $this->orderRepository->addItem($orderId, [
                    'product_id' => $item['product_id'],
                    'product_name' => $product['name'],
                    'product_sku' => $product['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product['price'],
                    'total_price' => $product['price'] * $item['quantity']
                ]);

                $this->productRepository->decreaseStock(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            $this->db->commit();

            $this->logger->info('Order created successfully', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'total' => $totalAmount
            ]);

            // Send confirmation email (async in production)
            $this->emailService->sendOrderConfirmation($orderId);

            return $orderId;

        } catch (\Exception $e) {
            $this->db->rollBack();

            $this->logger->error('Order creation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new OrderException('Failed to create order: ' . $e->getMessage());
        }
    }

    private function calculateSubtotal(array $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $product = $this->productRepository->findById($item['product_id']);
            $subtotal += $product['price'] * $item['quantity'];
        }

        return $subtotal;
    }

    private function calculateTax(float $subtotal): float
    {
        // Example: 10% tax rate
        return round($subtotal * 0.10, 2);
    }

    private function calculateShipping(array $items, array $address): float
    {
        // Simple flat rate shipping
        // In production, calculate based on weight, destination, etc.
        return 10.00;
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    }
}
```

### 7.4 Controller Implementation

```php
<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Services\ProductService;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ProductController
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $products = $this->productService->getAllProducts($limit, $offset);
        $totalProducts = $this->productService->countAllProducts();
        $totalPages = (int) ceil($totalProducts / $limit);

        $html = $this->render('pages/product-list', [
            'products' => $products,
            'currentPage' => $page,
            'totalPages' => $totalPages
        ]);

        return new HtmlResponse($html);
    }

    public function show(ServerRequestInterface $request, array $args): ResponseInterface
    {
        $slug = $args['slug'];
        $product = $this->productService->getProductBySlug($slug);

        if (!$product) {
            return new HtmlResponse('Product not found', 404);
        }

        $html = $this->render('pages/product-detail', [
            'product' => $product
        ]);

        return new HtmlResponse($html);
    }

    private function render(string $template, array $data = []): string
    {
        extract($data);
        ob_start();
        require ROOT_PATH . "/templates/{$template}.php";
        return ob_get_clean();
    }
}
```

---

## 8. Development Environment Setup

### 8.1 Complete docker-compose.yml

```yaml
version: '3.8'

services:
  # Nginx Web Server
  nginx:
    image: nginx:1.25-alpine
    container_name: ecommerce_nginx
    ports:
      - "8080:80"
      - "8443:443"
    volumes:
      - ./:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - php
    networks:
      - ecommerce_network

  # PHP-FPM
  php:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    container_name: ecommerce_php
    volumes:
      - ./:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/php.ini
    environment:
      - APP_ENV=development
      - DB_HOST=postgres
      - DB_PORT=5432
      - REDIS_HOST=redis
      - REDIS_PORT=6379
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
    networks:
      - ecommerce_network

  # PostgreSQL Database
  postgres:
    image: postgres:16-alpine
    container_name: ecommerce_postgres
    environment:
      POSTGRES_DB: ecommerce
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: secret
      POSTGRES_INITDB_ARGS: "--encoding=UTF8 --locale=en_US.UTF-8"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
      - ./database/seeds:/docker-entrypoint-initdb.d/seeds
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U postgres"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - ecommerce_network

  # Redis Cache
  redis:
    image: redis:7-alpine
    container_name: ecommerce_redis
    command: redis-server --requirepass secret --appendonly yes
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5
    networks:
      - ecommerce_network

  # Adminer (Database Management)
  adminer:
    image: adminer:latest
    container_name: ecommerce_adminer
    ports:
      - "8081:8080"
    environment:
      ADMINER_DEFAULT_SERVER: postgres
    depends_on:
      - postgres
    networks:
      - ecommerce_network

  # MailHog (Email Testing)
  mailhog:
    image: mailhog/mailhog:latest
    container_name: ecommerce_mailhog
    ports:
      - "1025:1025"  # SMTP
      - "8025:8025"  # Web UI
    networks:
      - ecommerce_network

networks:
  ecommerce_network:
    driver: bridge

volumes:
  postgres_data:
    driver: local
  redis_data:
    driver: local
```

### 8.2 Dockerfile for PHP

```dockerfile
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libpq \
    icu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        mbstring \
        opcache \
        bcmath

# Install Redis extension
RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create non-root user
RUN addgroup -g 1000 www && adduser -u 1000 -G www -s /bin/sh -D www

# Set permissions
RUN chown -R www:www /var/www/html

USER www

# Expose port
EXPOSE 9000

CMD ["php-fpm"]
```

### 8.3 PHP Configuration (php.ini)

```ini
[PHP]
; Error handling
display_errors = On
display_startup_errors = On
error_reporting = E_ALL
log_errors = On
error_log = /var/www/html/storage/logs/php-error.log

; Resource limits
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 20M
upload_max_filesize = 10M

; Session
session.save_handler = redis
session.save_path = "tcp://redis:6379?auth=secret"
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1
session.sid_length = 48

; OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2

; Security
expose_php = Off
allow_url_fopen = On
allow_url_include = Off
disable_functions = exec,passthru,shell_exec,system,proc_open,popen

; Timezone
date.timezone = UTC
```

### 8.4 Nginx Configuration

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Max upload size
    client_max_body_size 10M;

    # Deny access to hidden files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~ /(composer\.json|composer\.lock|\.env|\.git) {
        deny all;
        return 404;
    }

    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_buffering off;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

### 8.5 Environment Configuration (.env.example)

```bash
# Application
APP_NAME="E-Commerce App"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_NAME=ecommerce
DB_USER=postgres
DB_PASSWORD=secret

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=secret

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@ecommerce.local
MAIL_FROM_NAME="${APP_NAME}"

# Stripe
STRIPE_PUBLIC_KEY=pk_test_xxx
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx

# PayPal
PAYPAL_CLIENT_ID=
PAYPAL_SECRET=
PAYPAL_MODE=sandbox

# Security
ENCRYPTION_KEY=base64:xxx
CSRF_TOKEN_EXPIRE=7200

# Logging
LOG_LEVEL=debug
LOG_CHANNEL=daily

# Rate Limiting
RATE_LIMIT_ENABLED=true
RATE_LIMIT_PER_MINUTE=60

# File Upload
MAX_UPLOAD_SIZE=10485760
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif,webp
```

---

## 9. Implementation Phases

### Phase 1: Foundation (Week 1-2)
- [x] Research & Planning
- [ ] Docker environment setup
- [ ] Database schema creation
- [ ] PSR-4 autoloading setup
- [ ] Dependency injection container
- [ ] Basic routing
- [ ] Logging infrastructure
- [ ] Error handling

**Deliverables:**
- Working development environment
- Database with seed data
- Basic application skeleton
- Health check endpoint

### Phase 2: Authentication & User Management (Week 3)
- [ ] User registration
- [ ] Login/logout
- [ ] Password reset
- [ ] Email verification
- [ ] User profile management
- [ ] Session management
- [ ] CSRF protection
- [ ] Rate limiting

**Deliverables:**
- Complete auth system
- User dashboard
- Security middleware

### Phase 3: Product Catalog (Week 4-5)
- [ ] Category management (admin)
- [ ] Product management (admin)
- [ ] Product listing page
- [ ] Product detail page
- [ ] Product search
- [ ] Filtering & sorting
- [ ] Image upload & processing
- [ ] Product reviews

**Deliverables:**
- Working product catalog
- Admin product management
- Search functionality

### Phase 4: Shopping Cart (Week 6)
- [ ] Add to cart
- [ ] Update cart quantities
- [ ] Remove from cart
- [ ] Cart persistence
- [ ] Guest cart (session-based)
- [ ] Cart totals calculation
- [ ] Stock validation

**Deliverables:**
- Functional shopping cart
- Cart API endpoints
- Cart session management

### Phase 5: Checkout & Orders (Week 7-8)
- [ ] Checkout flow
- [ ] Address management
- [ ] Shipping calculation
- [ ] Tax calculation
- [ ] Order creation
- [ ] Order management (admin)
- [ ] Order status tracking
- [ ] Email notifications

**Deliverables:**
- Complete checkout process
- Order management system
- Email templates

### Phase 6: Payment Integration (Week 9)
- [ ] Stripe integration
- [ ] Payment processing
- [ ] Webhook handling
- [ ] Payment status tracking
- [ ] Refund handling
- [ ] Invoice generation

**Deliverables:**
- Working payment system
- Payment security
- Transaction logging

### Phase 7: Admin Panel (Week 10-11)
- [ ] Admin dashboard
- [ ] User management
- [ ] Product management
- [ ] Order management
- [ ] Analytics & reports
- [ ] Settings management

**Deliverables:**
- Complete admin interface
- RBAC implementation
- Admin dashboard

### Phase 8: Testing & Quality (Week 12)
- [ ] Unit tests (80%+ coverage)
- [ ] Integration tests
- [ ] Security audit
- [ ] Performance testing
- [ ] Load testing
- [ ] Code quality checks

**Deliverables:**
- Comprehensive test suite
- Security report
- Performance benchmarks

### Phase 9: Production Preparation (Week 13-14)
- [ ] Production Docker setup
- [ ] CI/CD pipeline
- [ ] Monitoring & alerting
- [ ] Backup strategy
- [ ] Documentation
- [ ] Deployment scripts

**Deliverables:**
- Production-ready application
- Deployment documentation
- Monitoring dashboard

---

## 10. Testing Strategy

### 10.1 PHPUnit Configuration (phpunit.xml)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         cacheDirectory=".phpunit.cache">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>

    <coverage>
        <report>
            <html outputDirectory="coverage/html"/>
            <text outputFile="coverage/coverage.txt"/>
        </report>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_NAME" value="ecommerce_test"/>
    </php>

    <logging>
        <testdoxText outputFile="tests/testdox.txt"/>
    </logging>
</phpunit>
```

### 10.2 Example Unit Test

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CartService;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class CartServiceTest extends TestCase
{
    private CartService $service;
    private CartRepositoryInterface|MockObject $cartRepo;
    private ProductRepositoryInterface|MockObject $productRepo;

    protected function setUp(): void
    {
        $this->cartRepo = $this->createMock(CartRepositoryInterface::class);
        $this->productRepo = $this->createMock(ProductRepositoryInterface::class);

        $this->service = new CartService(
            $this->cartRepo,
            $this->productRepo
        );
    }

    public function testAddItemToCart(): void
    {
        $productId = 1;
        $quantity = 2;
        $userId = 10;

        $product = [
            'id' => $productId,
            'name' => 'Test Product',
            'price' => 99.99,
            'stock_quantity' => 10
        ];

        $this->productRepo
            ->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->cartRepo
            ->expects($this->once())
            ->method('addItem')
            ->with($userId, $productId, $quantity)
            ->willReturn(true);

        $result = $this->service->addToCart($userId, $productId, $quantity);

        $this->assertTrue($result);
    }

    public function testAddItemFailsWhenInsufficientStock(): void
    {
        $this->expectException(\App\Exceptions\CartException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $productId = 1;
        $quantity = 100;
        $userId = 10;

        $product = [
            'id' => $productId,
            'stock_quantity' => 5
        ];

        $this->productRepo
            ->expects($this->once())
            ->method('findById')
            ->with($productId)
            ->willReturn($product);

        $this->service->addToCart($userId, $productId, $quantity);
    }
}
```

---

## 11. Deployment & Production

### 11.1 Production Optimizations

```bash
# Composer optimizations
composer install --no-dev --optimize-autoloader --classmap-authoritative

# Clear development files
rm -rf tests/ .git/ .github/ docker/ *.md

# Set proper permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage/
chmod 600 .env
```

### 11.2 Production php.ini

```ini
[PHP]
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php/error.log

memory_limit = 512M
max_execution_time = 30
post_max_size = 20M
upload_max_filesize = 10M

session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1

opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.fast_shutdown = 1

expose_php = Off
```

---

## 12. Code Quality & Standards

### 12.1 PHPStan Configuration (phpstan.neon)

```neon
parameters:
    level: 8
    paths:
        - src
    excludePaths:
        - src/Legacy
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
```

### 12.2 PHP_CodeSniffer (phpcs.xml)

```xml
<?xml version="1.0"?>
<ruleset name="E-Commerce">
    <description>Coding standards for E-Commerce App</description>

    <file>src</file>
    <file>tests</file>

    <arg name="basepath" value="."/>
    <arg name="colors"/>
    <arg name="parallel" value="75"/>
    <arg value="np"/>

    <rule ref="PSR12"/>

    <rule ref="Generic.Arrays.DisallowLongArraySyntax"/>
    <rule ref="Generic.Formatting.SpaceAfterNot"/>
    <rule ref="Squiz.WhiteSpace.SuperfluousWhitespace">
        <properties>
            <property name="ignoreBlankLines" value="false"/>
        </properties>
    </rule>
</ruleset>
```

---

## 13. Performance Optimization

### 13.1 Caching Strategy

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Redis;

final class CacheService
{
    private const DEFAULT_TTL = 3600; // 1 hour

    public function __construct(
        private readonly Redis $redis
    ) {
    }

    public function remember(string $key, callable $callback, int $ttl = self::DEFAULT_TTL): mixed
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return null;
        }

        return unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool
    {
        return $this->redis->setex($key, $ttl, serialize($value));
    }

    public function forget(string $key): bool
    {
        return (bool) $this->redis->del($key);
    }

    public function flush(): bool
    {
        return $this->redis->flushDB();
    }
}
```

### 13.2 Database Query Optimization

- Use indexes on frequently queried columns
- Implement pagination for large result sets
- Use `EXPLAIN ANALYZE` to optimize slow queries
- Implement database connection pooling
- Use materialized views for complex reports
- Cache expensive queries

---

## 14. Reference Materials

### 14.1 Official PSR Documentation
- [PHP-FIG PSR Standards](https://www.php-fig.org/psr/)
- [PSR-4: Autoloading](https://www.php-fig.org/psr/psr-4/)
- [PSR-7: HTTP Messages](https://www.php-fig.org/psr/psr-7/)
- [PSR-11: Container](https://www.php-fig.org/psr/psr-11/)
- [PSR-12: Coding Style](https://www.php-fig.org/psr/psr-12/)
- [PSR-15: HTTP Handlers](https://www.php-fig.org/psr/psr-15/)

### 14.2 Security Resources
- [OWASP Top 10 2025](https://owasp.org/www-project-top-ten/)
- [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/)
- [PHP Security Best Practices 2025](https://www.zestminds.com/blog/php-security-best-practices-2025-checklist/)

### 14.3 Library Documentation
- [PHP-DI Documentation](https://php-di.org/)
- [League/Route Documentation](https://route.thephpleague.com/)
- [Monolog Documentation](https://github.com/Seldaek/monolog/tree/main/doc)
- [Guzzle Documentation](https://docs.guzzlephp.org/)
- [Stripe PHP Documentation](https://stripe.com/docs/api?lang=php)

### 14.4 Database
- [PostgreSQL Documentation](https://www.postgresql.org/docs/current/)
- [PostgreSQL Performance Tuning](https://wiki.postgresql.org/wiki/Performance_Optimization)
- [Redis Documentation](https://redis.io/documentation)

---

## Next Steps

1. **Set up development environment**
   ```bash
   docker-compose up -d
   composer install
   cp .env.example .env
   docker-compose exec php php database/migrate.php
   ```

2. **Start with Phase 1: Foundation**
   - Create directory structure
   - Set up autoloading
   - Configure DI container
   - Implement basic routing

3. **Follow implementation phases sequentially**

4. **Maintain code quality throughout**
   - Run PHPStan: `vendor/bin/phpstan analyse`
   - Run PHPCS: `vendor/bin/phpcs`
   - Run tests: `vendor/bin/phpunit`

---

**Created by**: Research from 40+ authoritative sources (2025)
**Status**: Ready for implementation
**Last Updated**: 2025-11-23
