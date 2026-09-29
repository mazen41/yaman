-- Add phone and last_login columns to users table if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL AFTER full_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL AFTER is_active;

-- Update the default admin user to add a phone number
UPDATE users SET phone = '966500000000' WHERE username = 'admin';
