# Project Structure Plan - Backend, Frontend & Database

## 🎯 Objective
Organize the project into clear Backend, Frontend, and Database layers with proper separation of concerns.

## 📊 New Purchase Order System
✅ **Complete system for managing purchase orders with multiple customer orders per purchase**
- Database tables created
- Backend API endpoints
- Frontend forms and views
- Tracking and status management

## 📋 Files to Delete (Test/Setup/Mock Data Files)

### Setup & Sample Data Files
- [ ] add_sample_data.php
- [ ] create_sample_orders.php
- [ ] setup_sample_data.php
- [ ] add_inventory_sample_data.php
- [ ] test_reports_system.php
- [ ] test_orders.php
- [ ] test_db.php
- [ ] final_test.php
- [ ] debug_db.php
- [ ] debug_stock_movements.php

### Database Fix/Setup Files (Keep for reference but move to /setup folder)
- [ ] fix_view_warnings.php
- [ ] fix_reports_database.php
- [ ] complete_database_fix.php
- [ ] check_db_structure.php
- [ ] check_tables.php
- [ ] complete_fix.php
- [ ] fix_database_tables.php
- [ ] create_advanced_purchases.php
- [ ] create_purchases_database.php
- [ ] create_reports_system.php
- [ ] check_database_structure.php
- [ ] complete_coupon_fix.php
- [ ] fix_stock_movements_table.php
- [ ] setup_coupons_system.php
- [ ] fix_notifications_table.php
- [ ] setup_invoice_tables.php
- [ ] final_coupon_verification.php

### Summary Files
- [ ] reports_system_summary.php
- [ ] purchases_system_summary.php

## 🔍 Files to Review & Clean

### Customer Portal
- [x] customer_portal/login.php - Already using real DB data
- [x] customer_portal/dashboard.php - Already using real DB data

### Modules to Verify
- [ ] modules/customers/*.php - Check for hardcoded customer data
- [ ] modules/orders/*.php - Check for hardcoded order data
- [ ] modules/inventory/*.php - Check for hardcoded product data
- [ ] modules/reports/*.php - Ensure all reports query DB
- [ ] modules/purchases/*.php - Check for hardcoded purchase data

## ✅ Database Tables Structure

### 🛒 Purchase Order System (NEW)
- **purchase_orders** - طلبات الشراء الرئيسية
- **purchase_groups** - مجموعات الشراء
- **purchase_order_items** - عناصر الطلبات (طلبات العملاء)
- **purchase_order_tracking** - تتبع حالة الشحنات
- **purchase_order_status_log** - سجل تغييرات الحالة
- **purchase_order_additional_codes** - رموز التتبع الإضافية

### 👥 Customer Management
- **customers** - بيانات العملاء
- **customer_orders** - طلبات العملاء
- **customer_invoices** - فواتير العملاء
- **customer_payments** - مدفوعات العملاء
- **customer_otps** - رموز OTP للتحقق

### 📦 Inventory Management
- **products** - المنتجات
- **categories** - التصنيفات
- **stock_movements** - حركة المخزون
- **coupons** - كوبونات الخصم

### 🏪 Legacy Purchase System
- **purchases** - المشتريات القديمة
- **purchase_items** - عناصر المشتريات
- **suppliers** - الموردين

### ⚙️ System Tables
- **users** - المستخدمين
- **cities** - المدن
- **offices** - المكاتب
- **notifications** - الإشعارات

## 🏗️ Project Architecture

### Backend (PHP)
```
modules/
├── purchase_orders/
│   ├── index.php              # List all purchase orders
│   ├── create.php             # Create new purchase order
│   ├── edit.php               # Edit purchase order
│   ├── view.php               # View purchase order details
│   └── actions/
│       ├── create_purchase_order.php
│       ├── update_purchase_order.php
│       ├── delete_purchase_order.php
│       ├── get_purchase_orders.php
│       ├── add_tracking_code.php
│       └── update_status.php
```

### Frontend (HTML/CSS/JS)
```
assets/
├── css/
│   └── purchase_orders.css    # Styles for purchase order pages
└── js/
    └── purchase_orders.js     # JavaScript functions
```

### Database
```
database/
├── PURCHASE_ORDER_DATABASE.md # Complete SQL schema
└── views/
    ├── v_purchase_orders_summary
    ├── v_purchase_order_items_details
    └── v_purchase_tracking_status
```

## 🗑️ Actions to Take

1. **✅ Create Purchase Order Database Tables**
2. **✅ Document Complete System Architecture**
3. **⏳ Implement Backend API Endpoints**
4. **⏳ Create Frontend Forms and Views**
5. **⏳ Add JavaScript for Dynamic Forms**
6. **⏳ Test Complete Workflow**

## 📊 Verification Steps

1. Check all forms use DB for dropdowns
2. Verify all reports query real data
3. Test customer portal with real customer
4. Ensure no hardcoded IDs or names
5. Validate all CRUD operations use DB

## 🎯 Expected Result

- Clean, production-ready codebase
- All data from database
- No mock/test files
- Organized setup scripts in /setup folder
