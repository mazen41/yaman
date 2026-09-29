-- Create invoices table
CREATE TABLE IF NOT EXISTS invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    final_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('draft', 'sent', 'paid', 'cancelled') DEFAULT 'draft',
    payment_method VARCHAR(50),
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create invoice items table
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    product_id INT,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    notes VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Create invoice notifications table
CREATE TABLE IF NOT EXISTS invoice_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    notification_type ENUM('email', 'whatsapp', 'manual') NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    sent_to VARCHAR(255),
    sent_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Create email settings table
CREATE TABLE IF NOT EXISTS email_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create invoice attachments table
CREATE TABLE IF NOT EXISTS invoice_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Create discount coupons table
CREATE TABLE IF NOT EXISTS discount_coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_code VARCHAR(50) NOT NULL UNIQUE,
    discount_type ENUM('percentage', 'fixed') NOT NULL,
    discount_value DECIMAL(15,2) NOT NULL,
    min_order_value DECIMAL(15,2) DEFAULT 0,
    max_usage INT DEFAULT 1,
    usage_count INT DEFAULT 0,
    customer_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    valid_from TIMESTAMP NOT NULL,
    valid_until TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default email settings
INSERT INTO email_settings (smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name)
VALUES ('smtp.example.com', 587, 'user@example.com', 'password', 'tls', 'invoices@example.com', 'نظام ياسين للفواتير')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
