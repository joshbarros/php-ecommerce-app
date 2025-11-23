-- Coupon and Discount System Migration
-- Enables promotional codes, discounts, and marketing campaigns

-- Coupon types enum
CREATE TYPE coupon_type AS ENUM ('percentage', 'fixed_amount', 'free_shipping');

-- Create coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    type coupon_type NOT NULL,
    value DECIMAL(10, 2) NOT NULL CHECK (value >= 0),
    description TEXT,

    -- Usage limits
    usage_limit INTEGER, -- NULL = unlimited
    usage_count INTEGER DEFAULT 0,
    usage_limit_per_user INTEGER DEFAULT 1,

    -- Minimum requirements
    minimum_order_amount DECIMAL(10, 2) DEFAULT 0.00,

    -- Restrictions
    applicable_product_ids INTEGER[], -- NULL = all products
    applicable_category_ids INTEGER[], -- NULL = all categories
    excluded_product_ids INTEGER[],
    excluded_category_ids INTEGER[],

    -- Customer restrictions
    first_order_only BOOLEAN DEFAULT FALSE,
    customer_ids INTEGER[], -- NULL = all customers, specific IDs = targeted

    -- Validity period
    starts_at TIMESTAMP,
    expires_at TIMESTAMP,

    -- Status
    is_active BOOLEAN DEFAULT TRUE,

    -- Metadata
    created_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Constraints
    CONSTRAINT valid_percentage CHECK (
        type != 'percentage' OR (value >= 0 AND value <= 100)
    ),
    CONSTRAINT valid_dates CHECK (
        starts_at IS NULL OR expires_at IS NULL OR starts_at < expires_at
    )
);

-- Create coupon usage tracking table
CREATE TABLE IF NOT EXISTS coupon_usages (
    id SERIAL PRIMARY KEY,
    coupon_id INTEGER NOT NULL REFERENCES coupons(id) ON DELETE CASCADE,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    discount_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(coupon_id, order_id)
);

-- Add coupon columns to orders table
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_id INTEGER REFERENCES coupons(id) ON DELETE SET NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS coupon_code VARCHAR(50);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10, 2) DEFAULT 0.00;

-- Create indexes for performance
CREATE INDEX idx_coupons_code ON coupons(code);
CREATE INDEX idx_coupons_active ON coupons(is_active) WHERE is_active = true;
CREATE INDEX idx_coupons_expires_at ON coupons(expires_at) WHERE expires_at IS NOT NULL;
CREATE INDEX idx_coupon_usages_coupon_id ON coupon_usages(coupon_id);
CREATE INDEX idx_coupon_usages_user_id ON coupon_usages(user_id);
CREATE INDEX idx_orders_coupon_id ON orders(coupon_id);

-- Function to increment coupon usage count
CREATE OR REPLACE FUNCTION increment_coupon_usage()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE coupons
    SET usage_count = usage_count + 1
    WHERE id = NEW.coupon_id;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger to auto-increment usage count
CREATE TRIGGER trg_increment_coupon_usage
    AFTER INSERT ON coupon_usages
    FOR EACH ROW
    EXECUTE FUNCTION increment_coupon_usage();

-- Update timestamp trigger for coupons
CREATE TRIGGER trg_coupons_update_timestamp
    BEFORE UPDATE ON coupons
    FOR EACH ROW
    EXECUTE FUNCTION update_timestamp();

-- Insert some example coupons for testing
INSERT INTO coupons (code, type, value, description, usage_limit, minimum_order_amount, starts_at, expires_at, is_active)
VALUES
    ('WELCOME10', 'percentage', 10.00, 'Welcome discount - 10% off', NULL, 50.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '30 days', true),
    ('SAVE20', 'fixed_amount', 20.00, 'Save $20 on orders over $100', NULL, 100.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '14 days', true),
    ('FREESHIP', 'free_shipping', 0.00, 'Free shipping on all orders', 100, 0.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '7 days', true),
    ('FIRSTORDER', 'percentage', 15.00, 'First order discount - 15% off', NULL, 0.00, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP + INTERVAL '90 days', true)
ON CONFLICT (code) DO NOTHING;

-- Update first order coupon to be first-order-only
UPDATE coupons SET first_order_only = true WHERE code = 'FIRSTORDER';

COMMENT ON TABLE coupons IS 'Promotional discount coupons and codes';
COMMENT ON TABLE coupon_usages IS 'Tracks coupon redemptions';
COMMENT ON COLUMN coupons.type IS 'percentage: discount %, fixed_amount: discount $, free_shipping: waive shipping';
COMMENT ON COLUMN coupons.value IS 'Percentage (0-100) or fixed dollar amount';
COMMENT ON COLUMN coupons.usage_limit IS 'Total times this coupon can be used (NULL = unlimited)';
COMMENT ON COLUMN coupons.usage_limit_per_user IS 'Times each user can use this coupon';
COMMENT ON COLUMN coupons.applicable_product_ids IS 'Array of product IDs this coupon applies to (NULL = all)';
COMMENT ON COLUMN coupons.first_order_only IS 'Only valid for customers first order';
