-- Create cities table
CREATE TABLE IF NOT EXISTS cities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    region_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default Saudi cities
INSERT INTO cities (name, is_active) VALUES
('الرياض', TRUE),
('جدة', TRUE),
('الدمام', TRUE),
('مكة المكرمة', TRUE),
('المدينة المنورة', TRUE),
('الطائف', TRUE),
('تبوك', TRUE),
('الخبر', TRUE),
('القصيم', TRUE),
('حائل', TRUE),
('أبها', TRUE),
('نجران', TRUE),
('جازان', TRUE),
('الباحة', TRUE),
('سكاكا', TRUE),
('عرعر', TRUE),
('الجبيل', TRUE),
('ينبع', TRUE),
('بريدة', TRUE),
('الخرج', TRUE)
ON DUPLICATE KEY UPDATE name = VALUES(name), is_active = TRUE;
