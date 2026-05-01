-- ============================================================
-- FRAGRANCE BY NAWFAL - Complete Database Schema
-- MySQL 8+ Compatible
-- ============================================================

CREATE DATABASE IF NOT EXISTS fragrance_nawfal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fragrance_nawfal;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    role        ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    phone       VARCHAR(20)         NULL,
    address     TEXT                NULL,
    city        VARCHAR(80)         NULL,
    country     VARCHAR(80)         NULL DEFAULT 'Maroc',
    is_active   TINYINT(1)          NOT NULL DEFAULT 1,
    created_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- CATEGORIES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    description TEXT         NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name, slug) VALUES
    ('Homme', 'homme'),
    ('Femme', 'femme'),
    ('Mixte', 'mixte'),
    ('Édition Limitée', 'edition-limitee');

-- ============================================================
-- PRODUCTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS products (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     INT UNSIGNED        NOT NULL,
    name            VARCHAR(200)        NOT NULL,
    slug            VARCHAR(200)        NOT NULL UNIQUE,
    brand           VARCHAR(100)        NOT NULL,
    description     TEXT                NULL,
    top_notes       VARCHAR(255)        NULL,
    heart_notes     VARCHAR(255)        NULL,
    base_notes      VARCHAR(255)        NULL,
    price_50ml      DECIMAL(10,2)       NULL,
    price_75ml      DECIMAL(10,2)       NULL,
    price_100ml     DECIMAL(10,2)       NOT NULL,
    stock_50ml      INT UNSIGNED        NOT NULL DEFAULT 0,
    stock_75ml      INT UNSIGNED        NOT NULL DEFAULT 0,
    stock_100ml     INT UNSIGNED        NOT NULL DEFAULT 0,
    image_url       VARCHAR(500)        NULL,
    is_featured     TINYINT(1)          NOT NULL DEFAULT 0,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    avg_rating      DECIMAL(3,2)        NOT NULL DEFAULT 0.00,
    review_count    INT UNSIGNED        NOT NULL DEFAULT 0,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- SESSIONS TABLE (server-side session store)
-- ============================================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id          VARCHAR(128)    PRIMARY KEY,
    user_id     INT UNSIGNED    NOT NULL,
    ip_address  VARCHAR(45)     NULL,
    user_agent  VARCHAR(500)    NULL,
    expires_at  TIMESTAMP       NOT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CART TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS cart_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NULL,
    session_id  VARCHAR(128)    NULL,
    product_id  INT UNSIGNED    NOT NULL,
    size_ml     SMALLINT        NOT NULL DEFAULT 100,
    quantity    SMALLINT        NOT NULL DEFAULT 1,
    added_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- WISHLIST TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS wishlist (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    product_id  INT UNSIGNED NOT NULL,
    added_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wish (user_id, product_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ORDERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED        NULL,
    order_number    VARCHAR(30)         NOT NULL UNIQUE,
    status          ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded')
                    NOT NULL DEFAULT 'pending',
    customer_name   VARCHAR(100)        NOT NULL,
    customer_email  VARCHAR(150)        NOT NULL,
    customer_phone  VARCHAR(20)         NULL,
    shipping_address TEXT               NOT NULL,
    shipping_city   VARCHAR(80)         NOT NULL,
    shipping_country VARCHAR(80)        NOT NULL DEFAULT 'Maroc',
    subtotal        DECIMAL(10,2)       NOT NULL,
    shipping_cost   DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    tax             DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    total           DECIMAL(10,2)       NOT NULL,
    payment_method  ENUM('card','cash_on_delivery','bank_transfer') NOT NULL DEFAULT 'card',
    payment_status  ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    notes           TEXT                NULL,
    created_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ORDER ITEMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS order_items (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED    NOT NULL,
    product_id  INT UNSIGNED    NULL,
    name        VARCHAR(200)    NOT NULL,
    size_ml     SMALLINT        NOT NULL,
    quantity    SMALLINT        NOT NULL,
    unit_price  DECIMAL(10,2)   NOT NULL,
    subtotal    DECIMAL(10,2)   NOT NULL,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- REVIEWS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id  INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NULL,
    author_name VARCHAR(100)    NOT NULL,
    rating      TINYINT         NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title       VARCHAR(200)    NULL,
    body        TEXT            NULL,
    is_approved TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- CONTACT MESSAGES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)    NOT NULL,
    email       VARCHAR(150)    NOT NULL,
    subject     VARCHAR(250)    NOT NULL,
    message     TEXT            NOT NULL,
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT ADMIN USER  (password: Admin@1234)
-- ============================================================
INSERT IGNORE INTO users (name, email, password, role) VALUES (
    'Nawfal Admin',
    'admin@fragrance-nawfal.ma',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234
    'admin'
);
