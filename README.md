# 📦 Yaman — Order & Shipping Management System

A comprehensive business management system built in **PHP & MySQL** with Arabic-first UI, covering orders, shipping, customers, and financials.

## Features

- **Order Management** — create, edit, track, and print orders with full status lifecycle
- **Shipping & Courier Portal** — dedicated courier login, basket/shipment tracking
- **Customer Portal** — customers can track orders and view invoices
- **Coupons & Discounts** — coupon system with discount tiers and loyalty cards
- **Purchase Orders** — supplier purchase order management
- **Financial Accounts** — bank accounts, payments, and invoice management
- **Granular Permissions (RBAC)** — role-based access control with per-user permission management
- **Sender Portal** — dedicated portal for senders to manage their shipments
- **Reports** — purchase cards, payment reports, and order summaries
- **Email Queue** — automated email notifications system

## Tech Stack

- **Backend:** PHP 7.4+, MySQL
- **Frontend:** HTML5, Tailwind CSS, JavaScript, Font Awesome
- **Fonts:** Google Fonts (Cairo — Arabic support)
- **Security:** CSRF protection, session management, security middleware

## Setup

```bash
# 1. Copy project to your web server
# 2. Create database and import schema
mysql -u root -p < database/schema.sql
# 3. Configure DB connection
nano config/database.php
# 4. Set file permissions
chmod -R 755 uploads/
# 5. Visit http://localhost/yaman
```

## Default Login

| User | Password | Role |
|------|----------|------|
| admin | password | System Admin |
