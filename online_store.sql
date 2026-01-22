CREATE DATABASE IF NOT EXISTS online_store;
USE online_store;

------------------------------------------------------------
-- USERS TABLE (customers)
------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

------------------------------------------------------------
-- ADMINS TABLE
------------------------------------------------------------
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('superadmin','editor','manager') DEFAULT 'manager',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

------------------------------------------------------------
-- CATEGORIES
------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    parent_id INT DEFAULT NULL,
    FOREIGN KEY(parent_id) REFERENCES categories(id) ON DELETE SET NULL
);

------------------------------------------------------------
-- PRODUCTS
------------------------------------------------------------
drop table products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    price DECIMAL(10,2) NOT NULL,
    discount_price DECIMAL(10,2),
    stock INT DEFAULT 0,
    sku VARCHAR(100) UNIQUE,
    attributes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(category_id) REFERENCES categories(id)
);

DELIMITER $$

CREATE TRIGGER generate_sku
BEFORE INSERT ON products
FOR EACH ROW
BEGIN
    IF NEW.sku IS NULL OR NEW.sku = '' THEN
        SET NEW.sku = CONCAT('SKU', LPAD((SELECT IFNULL(MAX(id),0) + 1 FROM products), 4, '0'));
    END IF;
END$$

DELIMITER ;




------------------------------------------------------------
-- PRODUCT IMAGES (MULTIPLE)
------------------------------------------------------------
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_main TINYINT(1) DEFAULT 0,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

------------------------------------------------------------
-- PRODUCT VIDEOS
------------------------------------------------------------
CREATE TABLE product_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    video_path VARCHAR(255) NOT NULL,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

------------------------------------------------------------
-- PRODUCT VARIANTS (optional: sizes, colors…)
------------------------------------------------------------
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_name VARCHAR(150),
    variant_value VARCHAR(150),
    additional_price DECIMAL(10,2) DEFAULT NULL,
    stock INT DEFAULT 0,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

------------------------------------------------------------
-- DISCOUNTS
------------------------------------------------------------
CREATE TABLE discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    discount_percent DECIMAL(5,2),
    start_date DATE,
    end_date DATE,
    FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
);

------------------------------------------------------------
-- BULK UPLOAD LOG
------------------------------------------------------------
CREATE TABLE bulk_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    file_name VARCHAR(255),
    total_rows INT,
    successful_rows INT,
    failed_rows INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(admin_id) REFERENCES admin_users(id)
);

------------------------------------------------------------
-- ORDERS
------------------------------------------------------------
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid','shipped','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

------------------------------------------------------------
-- ORDER ITEMS
------------------------------------------------------------
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    variant_id INT DEFAULT NULL,
    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY(product_id) REFERENCES products(id),
    FOREIGN KEY(variant_id) REFERENCES product_variants(id)
);


INSERT INTO admin_users (username, email, password, role)
VALUES ('admin', 'admin@store.com', 
        '$2y$10$5E.XVZpRFN5dQy5yPjWk1.tTfe5nH.YCkZB2YbEAdxZIxI9RBHy1S', 
        'superadmin');

SELECT * FROM admin_users;
show tables

UPDATE admin_users
SET password = '$2y$10$/.xwwlQY2Kwk1DEv2p2n3OtJJUHFfAvqXJmL0I/vg1fjW.UWh2Q9C'
WHERE email = 'admin@store.com';


ALTER TABLE products ADD COLUMN stock_status ENUM('in_stock','out_of_stock','preorder') DEFAULT 'in_stock';


ALTER TABLE products 
RENAME COLUMN discount TO discount_pce;
ALTER TABLE products CHANGE title name VARCHAR(255);


DESCRIBE product_images;
SELECT * FROM products;


ALTER TABLE product_images
ADD COLUMN filename VARCHAR(255) NOT NULL AFTER product_id;

ALTER TABLE product_images
ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE products
ADD COLUMN video VARCHAR(255) DEFAULT NULL;

ALTER TABLE product_images
image_path
