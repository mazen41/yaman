-- Enhanced customers table with additional fields based on requirements
ALTER TABLE customers 
    ADD COLUMN IF NOT EXISTS mobile_number VARCHAR(20) AFTER phone,
    ADD COLUMN IF NOT EXISTS whatsapp_number VARCHAR(20) AFTER mobile_number,
    ADD COLUMN IF NOT EXISTS alternative_number VARCHAR(20) AFTER whatsapp_number,
    ADD COLUMN IF NOT EXISTS customer_type ENUM('individual', 'company', 'delegate') DEFAULT 'individual' AFTER email,
    ADD COLUMN IF NOT EXISTS location_area VARCHAR(100) AFTER address,
    ADD COLUMN IF NOT EXISTS pickup_location VARCHAR(255) AFTER location_area,
    ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(15,2) DEFAULT 0 AFTER customer_type,
    ADD COLUMN IF NOT EXISTS office_id INT AFTER credit_limit,
    ADD COLUMN IF NOT EXISTS office_name VARCHAR(100) AFTER office_id,
    ADD COLUMN IF NOT EXISTS city_id INT AFTER office_name,
    ADD COLUMN IF NOT EXISTS city_name VARCHAR(100) AFTER city_id,
    ADD COLUMN IF NOT EXISTS pickup_options JSON AFTER city_name,
    ADD COLUMN IF NOT EXISTS pickup_notes TEXT AFTER pickup_options,
    ADD COLUMN IF NOT EXISTS notes TEXT AFTER is_active;

-- Create table for customer locations if it doesn't exist
CREATE TABLE IF NOT EXISTS customer_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    location_name VARCHAR(100) NOT NULL,
    address TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Create table for customer contacts if it doesn't exist
CREATE TABLE IF NOT EXISTS customer_contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    position VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Create table for offices/branches if it doesn't exist
CREATE TABLE IF NOT EXISTS offices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    manager_name VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add some sample data for offices
INSERT INTO offices (name, address, phone, manager_name) VALUES
('المكتب الرئيسي', 'الرياض، المملكة العربية السعودية', '966500000001', 'أحمد محمد'),
('مكتب جدة', 'جدة، المملكة العربية السعودية', '966500000002', 'خالد عبدالله');

-- Add some sample data for customers if the table is empty
INSERT INTO customers (customer_code, name, phone, mobile_number, whatsapp_number, email, address, customer_type, location_area, pickup_location, office_id, office_name)
SELECT 'CUST001', 'محمد أحمد', '966512345678', '966512345678', '966512345678', 'mohammed@example.com', 'الرياض، حي النزهة', 'individual', 'الرياض', 'أمام محطة البنزين', 1, 'المكتب الرئيسي'
WHERE NOT EXISTS (SELECT 1 FROM customers LIMIT 1);

INSERT INTO customers (customer_code, name, phone, mobile_number, whatsapp_number, email, address, customer_type, location_area, pickup_location, office_id, office_name)
SELECT 'CUST002', 'شركة الأمل للتجارة', '966598765432', '966598765432', '966598765432', 'info@alamal.com', 'جدة، حي الروضة', 'company', 'جدة', 'بجانب البنك الأهلي', 2, 'مكتب جدة'
WHERE NOT EXISTS (SELECT 1 FROM customers LIMIT 1);

INSERT INTO customers (customer_code, name, phone, mobile_number, whatsapp_number, email, address, customer_type, location_area, pickup_location, office_id, office_name)
SELECT 'CUST003', 'عبدالله مندوب', '966555555555', '966555555555', '966555555555', 'abdullah@example.com', 'الدمام، حي الشاطئ', 'delegate', 'الدمام', 'مجمع التجاري', 1, 'المكتب الرئيسي'
WHERE NOT EXISTS (SELECT 1 FROM customers LIMIT 1);
