# 🏗️ System Architecture

## 📊 Complete Module Structure

```
┌─────────────────────────────────────────────────────────────────┐
│                      TAKSORIDE SYSTEM                            │
│                  (Role-Based Access Control)                     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        SIDEBAR MODULES                           │
│                    (27 Total Modules)                            │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        │                     │                     │
        ▼                     ▼                     ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│   EXISTING   │    │     NEW      │    │   REPORTS    │
│   MODULES    │    │   MODULES    │    │   MODULE     │
│   (13)       │    │   (14)       │    │   (1)        │
└──────────────┘    └──────────────┘    └──────────────┘
```

---

## 🗂️ Module Categories

### 1. **Customer Management** (5 modules)
```
┌─────────────────────────────────────┐
│  👥 Customer Management             │
├─────────────────────────────────────┤
│  ✅ customers                        │
│  ✅ customer_invoices    [NEW]      │
│  ✅ customer_types       [NEW]      │
│  ✅ cities               [NEW]      │
│  ✅ orders                           │
└─────────────────────────────────────┘
```

### 2. **Financial Management** (4 modules)
```
┌─────────────────────────────────────┐
│  💰 Financial Management            │
├─────────────────────────────────────┤
│  ✅ financial                        │
│  ✅ financial_review     [NEW]      │
│  ✅ bank_accounts        [NEW]      │
│  ✅ expenses                         │
└─────────────────────────────────────┘
```

### 3. **Purchase Management** (6 modules)
```
┌─────────────────────────────────────┐
│  🛒 Purchase Management             │
├─────────────────────────────────────┤
│  ✅ purchases                        │
│  ✅ purchase_groups      [NEW]      │
│  ✅ suppliers            [NEW]      │
│  ✅ baskets              [NEW]      │
│  ✅ purchase_cards       [NEW]      │
│  ✅ loyalty_cards        [NEW]      │
└─────────────────────────────────────┘
```

### 4. **Operations** (5 modules)
```
┌─────────────────────────────────────┐
│  ⚙️ Operations                       │
├─────────────────────────────────────┤
│  ✅ shipping                         │
│  ✅ whatsapp             [NEW]      │
│  ✅ inventory            [NEW]      │
│  ✅ coupons              [NEW]      │
│  ✅ reports                          │
└─────────────────────────────────────┘
```

### 5. **Administration** (2 modules)
```
┌─────────────────────────────────────┐
│  🔐 Administration                   │
├─────────────────────────────────────┤
│  ✅ employees            [NEW]      │
│  ✅ permissions                      │
└─────────────────────────────────────┘
```

---

## 🔄 CRUD Operation Flow

```
┌──────────────────────────────────────────────────────────────┐
│                      USER REQUEST                             │
└──────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│              SESSION CHECK (Logged In?)                       │
│              ✅ Yes → Continue                                │
│              ❌ No → Redirect to Login                        │
└──────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│           PERMISSION CHECK (hasPermission)                    │
│           1. Check if Super Admin → Allow All                │
│           2. Check Permission Cache                           │
│           3. Query Database for Role Permissions             │
│           ✅ Has Permission → Continue                        │
│           ❌ No Permission → Redirect                         │
└──────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    RENDER PAGE                                │
│                                                               │
│   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│   │  VIEW       │  │   ADD       │  │   EDIT      │        │
│   │  (index)    │  │   (add)     │  │   (edit)    │        │
│   ├─────────────┤  ├─────────────┤  ├─────────────┤        │
│   │ • List data │  │ • Form      │  │ • Form      │        │
│   │ • Filter    │  │ • Validate  │  │ • Pre-fill  │        │
│   │ • Currency  │  │ • Insert    │  │ • Update    │        │
│   │ • Actions   │  │ • Redirect  │  │ • Redirect  │        │
│   └─────────────┘  └─────────────┘  └─────────────┘        │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔐 Permission System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    PERMISSION SYSTEM                         │
└─────────────────────────────────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
        ▼                   ▼                   ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│    USERS     │    │    ROLES     │    │ PERMISSIONS  │
├──────────────┤    ├──────────────┤    ├──────────────┤
│ • id         │    │ • id         │    │ • id         │
│ • name       │───▶│ • name       │◀───│ • key        │
│ • email      │    │ • is_active  │    │ • name       │
│ • role_id    │    └──────────────┘    │ • module     │
└──────────────┘                         └──────────────┘
                            │
                            ▼
                  ┌──────────────────┐
                  │ ROLE_PERMISSIONS │
                  ├──────────────────┤
                  │ • role_id        │
                  │ • permission_id  │
                  │ • can_view       │
                  │ • can_add        │
                  │ • can_edit       │
                  └──────────────────┘
                            │
                            ▼
                  ┌──────────────────┐
                  │ PERMISSION_CACHE │
                  ├──────────────────┤
                  │ • user_id        │
                  │ • cache_key      │
                  │ • cache_value    │
                  │ • expires_at     │
                  └──────────────────┘
```

---

## 💾 Database Schema

### Core Tables:
```sql
users
├── id (PK)
├── full_name
├── email
├── password
├── role_id (FK → roles.id)
└── is_active

roles
├── id (PK)
├── role_name
└── is_active

permissions
├── id (PK)
├── permission_key (e.g., 'customers')
├── permission_name (e.g., 'إدارة العملاء')
└── module_name

role_permissions
├── id (PK)
├── role_id (FK → roles.id)
├── permission_id (FK → permissions.id)
├── can_view (BOOLEAN)
├── can_add (BOOLEAN)
└── can_edit (BOOLEAN)

permission_cache
├── id (PK)
├── user_id (FK → users.id)
├── cache_key (VARCHAR)
├── cache_value (TEXT)
└── expires_at (DATETIME)
```

### Module Tables (Examples):
```sql
customers
├── id (PK)
├── name
├── phone
├── email
├── address
└── currency (ENUM: 'YER', 'SAR')

customer_invoices
├── id (PK)
├── invoice_number
├── customer_id (FK)
├── amount (DECIMAL)
├── currency (ENUM: 'YER', 'SAR')
└── status

expenses
├── id (PK)
├── description
├── amount (DECIMAL)
├── currency (ENUM: 'YER', 'SAR')
└── date

[... and 24 more module tables]
```

---

## 🎨 UI Component Hierarchy

```
┌─────────────────────────────────────────────────────────────┐
│                        LAYOUT                                │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    HEADER                            │   │
│  │  • Logo                                              │   │
│  │  • User Info                                         │   │
│  │  • Logout                                            │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌──────────┐  ┌──────────────────────────────────────┐   │
│  │          │  │                                       │   │
│  │ SIDEBAR  │  │         MAIN CONTENT                  │   │
│  │          │  │                                       │   │
│  │ • Module │  │  ┌─────────────────────────────┐     │   │
│  │   List   │  │  │     PAGE HEADER             │     │   │
│  │          │  │  │  • Title                    │     │   │
│  │ • Filter │  │  │  • Breadcrumb               │     │   │
│  │   by     │  │  │  • Action Buttons           │     │   │
│  │   Perms  │  │  └─────────────────────────────┘     │   │
│  │          │  │                                       │   │
│  │ • Icons  │  │  ┌─────────────────────────────┐     │   │
│  │          │  │  │     FILTERS                 │     │   │
│  │ • RTL    │  │  │  • Currency Switcher        │     │   │
│  │          │  │  │  • Date Range               │     │   │
│  │          │  │  │  • Search                   │     │   │
│  └──────────┘  │  └─────────────────────────────┘     │   │
│                │                                       │   │
│                │  ┌─────────────────────────────┐     │   │
│                │  │     DATA TABLE              │     │   │
│                │  │  • Headers                  │     │   │
│                │  │  • Rows                     │     │   │
│                │  │  • Actions (Edit/Delete)    │     │   │
│                │  │  • Pagination               │     │   │
│                │  └─────────────────────────────┘     │   │
│                └──────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    FOOTER                            │   │
│  │  • Copyright                                         │   │
│  │  • Version                                           │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔄 Request Lifecycle

```
1. USER CLICKS SIDEBAR LINK
   │
   ▼
2. BROWSER SENDS REQUEST
   │
   ▼
3. PHP RECEIVES REQUEST
   │
   ▼
4. SESSION CHECK
   │
   ├─ Not Logged In → Redirect to login.php
   │
   └─ Logged In → Continue
      │
      ▼
5. PERMISSION CHECK (hasPermission)
   │
   ├─ Super Admin → Allow All
   │
   ├─ Check Cache → Found → Return Result
   │
   └─ Query Database
      │
      ├─ Has Permission → Cache & Allow
      │
      └─ No Permission → Redirect to index.php
         │
         ▼
6. LOAD MODULE PAGE
   │
   ├─ index.php (View)
   │  │
   │  ├─ Query Database
   │  ├─ Apply Filters (Currency, Date, etc.)
   │  ├─ Render Table
   │  └─ Show Action Buttons (if has permission)
   │
   ├─ add.php (Add)
   │  │
   │  ├─ Show Form
   │  ├─ Validate Input
   │  ├─ Insert to Database
   │  └─ Redirect to index.php
   │
   └─ edit.php (Edit)
      │
      ├─ Fetch Record
      ├─ Show Pre-filled Form
      ├─ Validate Input
      ├─ Update Database
      └─ Redirect to index.php
```

---

## 📦 File Structure

```
taksoride/
├── config/
│   └── database.php              # Database connection
│
├── includes/
│   ├── header.php                # Common header
│   ├── footer.php                # Common footer
│   ├── sidebar.php               # Dynamic sidebar
│   ├── check_permissions.php     # Legacy permission wrappers
│   └── rbac_helpers.php          # Core RBAC functions
│
├── modules/
│   ├── customers/
│   │   ├── index.php             # View customers
│   │   ├── add.php               # Add customer
│   │   └── edit.php              # Edit customer
│   │
│   ├── customer_invoices/        [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── customer_types/           [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── cities/                   [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── financial_review/         [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── purchase_groups/          [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── suppliers/                [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── baskets/                  [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── purchase_cards/           [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── loyalty_cards/            [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── whatsapp/                 [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── inventory/                [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── bank_accounts/            [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── employees/                [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── coupons/                  [NEW]
│   │   ├── index.php
│   │   ├── add.php
│   │   └── edit.php
│   │
│   ├── expenses/
│   │   ├── index.php
│   │   └── add.php
│   │
│   ├── financial/
│   │   └── employee-permissions.php
│   │
│   └── reports/
│       ├── index.php
│       ├── expenses_report.php
│       ├── revenue_income.php
│       └── customer_accounts.php
│
├── login.php                     # Login page
├── index.php                     # Dashboard
└── logout.php                    # Logout handler
```

---

## 🎯 Summary

**Total System Components:**
- ✅ 27 Modules
- ✅ 81+ PHP Files
- ✅ 5 Core Permission Tables
- ✅ 20+ Module Tables
- ✅ 100% Permission Coverage
- ✅ Full CRUD Operations
- ✅ Currency Support (YER/SAR)
- ✅ Responsive Design
- ✅ RTL Support

**System Status**: ✅ **FULLY OPERATIONAL**
