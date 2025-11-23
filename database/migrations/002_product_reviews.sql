-- Product Reviews Migration
-- Adds product review and rating functionality

-- Create product_reviews table
CREATE TABLE IF NOT EXISTS product_reviews (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    title VARCHAR(255),
    comment TEXT,
    is_verified_purchase BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    helpful_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(product_id, user_id) -- One review per user per product
);

-- Create index on product_id for faster lookups
CREATE INDEX idx_product_reviews_product_id ON product_reviews(product_id);

-- Create index on user_id for user review history
CREATE INDEX idx_product_reviews_user_id ON product_reviews(user_id);

-- Create index on rating for filtering
CREATE INDEX idx_product_reviews_rating ON product_reviews(rating);

-- Create index on approval status
CREATE INDEX idx_product_reviews_approved ON product_reviews(is_approved);

-- Create table for tracking helpful votes
CREATE TABLE IF NOT EXISTS review_helpful_votes (
    id SERIAL PRIMARY KEY,
    review_id INTEGER NOT NULL REFERENCES product_reviews(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(review_id, user_id) -- One vote per user per review
);

-- Create index for faster lookups
CREATE INDEX idx_review_helpful_votes_review_id ON review_helpful_votes(review_id);

-- Add average_rating column to products table
ALTER TABLE products ADD COLUMN IF NOT EXISTS average_rating DECIMAL(3, 2) DEFAULT 0.00;
ALTER TABLE products ADD COLUMN IF NOT EXISTS review_count INTEGER DEFAULT 0;

-- Create index on average_rating for sorting
CREATE INDEX IF NOT EXISTS idx_products_average_rating ON products(average_rating DESC);

-- Function to update product rating stats
CREATE OR REPLACE FUNCTION update_product_rating_stats()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE products
    SET
        average_rating = (
            SELECT COALESCE(AVG(rating), 0)
            FROM product_reviews
            WHERE product_id = NEW.product_id
              AND is_approved = true
        ),
        review_count = (
            SELECT COUNT(*)
            FROM product_reviews
            WHERE product_id = NEW.product_id
              AND is_approved = true
        ),
        updated_at = CURRENT_TIMESTAMP
    WHERE id = NEW.product_id;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update product stats when review is added/updated
CREATE TRIGGER trg_update_product_rating_on_insert
    AFTER INSERT ON product_reviews
    FOR EACH ROW
    EXECUTE FUNCTION update_product_rating_stats();

CREATE TRIGGER trg_update_product_rating_on_update
    AFTER UPDATE ON product_reviews
    FOR EACH ROW
    WHEN (OLD.rating IS DISTINCT FROM NEW.rating OR OLD.is_approved IS DISTINCT FROM NEW.is_approved)
    EXECUTE FUNCTION update_product_rating_stats();

-- Function to update helpful count
CREATE OR REPLACE FUNCTION update_review_helpful_count()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE product_reviews
    SET helpful_count = (
        SELECT COUNT(*)
        FROM review_helpful_votes
        WHERE review_id = NEW.review_id
    )
    WHERE id = NEW.review_id;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger to update helpful count
CREATE TRIGGER trg_update_helpful_count
    AFTER INSERT OR DELETE ON review_helpful_votes
    FOR EACH ROW
    EXECUTE FUNCTION update_review_helpful_count();

-- Update timestamp trigger
CREATE TRIGGER trg_product_reviews_update_timestamp
    BEFORE UPDATE ON product_reviews
    FOR EACH ROW
    EXECUTE FUNCTION update_timestamp();

COMMENT ON TABLE product_reviews IS 'Customer product reviews and ratings';
COMMENT ON TABLE review_helpful_votes IS 'Tracks helpful votes for reviews';
COMMENT ON COLUMN product_reviews.rating IS 'Rating from 1 to 5 stars';
COMMENT ON COLUMN product_reviews.is_verified_purchase IS 'True if user purchased the product';
COMMENT ON COLUMN product_reviews.is_approved IS 'Admin moderation flag';
COMMENT ON COLUMN products.average_rating IS 'Calculated average rating from approved reviews';
COMMENT ON COLUMN products.review_count IS 'Number of approved reviews';
