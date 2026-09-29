-- Create Database
CREATE DATABASE IF NOT EXISTS yassin_admin_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE yassin_admin_system;

-- Users table for authentication
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'manager', 'employee') DEFAULT 'employee',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Customers table (إدارة العملاء)
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    email VARCHAR(100),
    customer_type ENUM('individual', 'company') DEFAULT 'individual',
    credit_limit DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Suppliers table (الموردين)
CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_code VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    email VARCHAR(100),
    contact_person VARCHAR(100),
    payment_terms VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products/Items table (المنتجات)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(30) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    unit VARCHAR(20) DEFAULT 'قطعة',
    cost_price DECIMAL(15,2) DEFAULT 0,
    selling_price DECIMAL(15,2) DEFAULT 0,
    min_stock_level INT DEFAULT 0,
    current_stock INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Purchase Orders table (طلبات الشراء)
CREATE TABLE purchase_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(30) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE,
    status ENUM('pending', 'ordered', 'received', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    final_amount DECIMAL(15,2) DEFAULT 0,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Purchase Order Items table (تفاصيل طلبات الشراء)
CREATE TABLE purchase_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    purchase_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    received_quantity INT DEFAULT 0,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Sales Orders table (طلبات المبيعات)
CREATE TABLE sales_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(30) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    order_date DATE NOT NULL,
    delivery_date DATE,
    status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(15,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    final_amount DECIMAL(15,2) DEFAULT 0,
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Sales Order Items table (تفاصيل طلبات المبيعات)
CREATE TABLE sales_order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sales_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    total_price DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (sales_order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Stock Movements table (حركات المخزون)
CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference_type ENUM('purchase', 'sale', 'adjustment', 'initial') NOT NULL,
    reference_id INT,
    notes TEXT,
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Coupons table (الكوبونات)
CREATE TABLE coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    coupon_code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('fixed', 'percentage') NOT NULL,
    discount_value DECIMAL(15,2) NOT NULL,
    min_order_amount DECIMAL(15,2) DEFAULT 0,
    max_discount_amount DECIMAL(15,2),
    usage_limit INT,
    used_count INT DEFAULT 0,
    valid_from DATE NOT NULL,
    valid_to DATE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Financial Accounts table (الحسابات المالية)
CREATE TABLE financial_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_code VARCHAR(20) UNIQUE NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    parent_id INT,
    balance DECIMAL(15,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES financial_accounts(id)
);

-- Financial Transactions table (المعاملات المالية)
CREATE TABLE financial_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_number VARCHAR(30) UNIQUE NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    reference_type ENUM('sale', 'purchase', 'payment', 'receipt', 'adjustment') NOT NULL,
    reference_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Financial Transaction Details table (تفاصيل المعاملات المالية)
CREATE TABLE financial_transaction_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id INT NOT NULL,
    account_id INT NOT NULL,
    debit_amount DECIMAL(15,2) DEFAULT 0,
    credit_amount DECIMAL(15,2) DEFAULT 0,
    FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES financial_accounts(id)
);

-- System Settings table (إعدادات النظام)
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password, full_name, role) 
VALUES ('admin', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('company_name', 'شركة ياسين للإدارة', 'اسم الشركة'),
('company_address', 'الرياض، المملكة العربية السعودية', 'عنوان الشركة'),
('company_phone', '966xxxxxxxxx', 'هاتف الشركة'),
('tax_rate', '15', 'نسبة الضريبة المضافة'),
('currency', 'ريال سعودي', 'العملة الأساسية');

-- Insert sample financial accounts
INSERT INTO financial_accounts (account_code, account_name, account_type) VALUES
('1000', 'الأصول', 'asset'),
('1100', 'الأصول المتداولة', 'asset'),
('1110', 'النقدية في الصندوق', 'asset'),
('1120', 'البنك', 'asset'),
('1200', 'المدينون', 'asset'),
('1300', 'المخزون', 'asset'),
('2000', 'الخصوم', 'liability'),
('2100', 'الخصوم المتداولة', 'liability'),
('2110', 'الدائنون', 'liability'),
('3000', 'رأس المال', 'equity'),
('4000', 'الإيرادات', 'revenue'),
('4100', 'إيرادات المبيعات', 'revenue'),
('5000', 'المصروفات', 'expense'),
('5100', 'تكلفة البضاعة المباعة', 'expense');
