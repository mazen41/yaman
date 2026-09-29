-- Create customer orders table
CREATE TABLE IF NOT EXISTS customer_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    final_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('new', 'processing', 'completed', 'cancelled') DEFAULT 'new',
    payment_method VARCHAR(50),
    shipping_method VARCHAR(50),
    shipping_cost DECIMAL(10,2) DEFAULT 0,
    expected_delivery_date DATE NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_id INT,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
);

-- Create order notifications table
CREATE TABLE IF NOT EXISTS order_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    notification_type ENUM('email', 'whatsapp') NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_to VARCHAR(255),
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
);

-- Create order attachments table
CREATE TABLE IF NOT EXISTS order_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create order status history table
CREATE TABLE IF NOT EXISTS order_status_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create order shipping addresses table
CREATE TABLE IF NOT EXISTS order_shipping_addresses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Saudi Arabia',
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
);
