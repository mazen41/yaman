# 🎁 Loyalty Cards System - Quick Setup Guide
## دليل الإعداد السريع لنظام بطاقات الهدية

---

## ✅ **STEP 1: Run Database Setup** (2 minutes)

### **Open this URL in your browser:**

```
http://localhost/yassin-admin-system/setup/create_loyalty_cards_tables.php
```

### **Expected Result:**
- ✅ 5 tables created successfully
- ✅ 3 sample cards added
- ✅ 2 promotional offers added
- ✅ Green success messages displayed

### **Tables Created:**
1. `loyalty_cards` - البطاقات الرئيسية
2. `loyalty_card_transactions` - المعاملات
3. `loyalty_card_goals` - الأهداف
4. `loyalty_card_promotions` - العروض الترويجية
5. `loyalty_card_promo_usage` - استخدام العروض

---

## ✅ **STEP 2: Access the System** (1 minute)

### **Navigate to:**

```
http://localhost/yassin-admin-system/modules/loyalty-cards/index.php
```

### **Or from Sidebar:**
```
القائمة الجانبية > إدارة المشتريات > بطاقات الهدية
```

### **You Should See:**
- ✅ 4 statistics cards at top
- ✅ Search and filter form
- ✅ Table with 3 sample cards
- ✅ "إضافة بطاقة جديدة" button

---

## ✅ **STEP 3: Test Features** (5 minutes)

### **A. View Sample Cards**

Sample cards created:
1. **GIFT-2025-001** (بطاقة هدية)
   - Balance: 1000 SAR
   - Password: 1234
   - Customer: أحمد محمد

2. **LOYALTY-2025-001** (بطاقة ولاء)
   - Balance: 500 SAR + 50 SAR bonus
   - Password: 5678
   - Customer: فاطمة علي

3. **PROMO-2025-SUMMER** (بطاقة ترويجية)
   - Balance: 900 SAR + 100 SAR bonus
   - Password: 9999
   - Customer: خالد سعيد

### **B. Create New Card**

1. Click "إضافة بطاقة جديدة"
2. Fill form:
   ```
   رقم البطاقة: TEST-CARD-001
   كلمة المرور: test123
   نوع البطاقة: بطاقة هدية
   الرصيد الأولي: 500.000
   اسم العميل: اسم تجريبي
   رقم الهاتف: 0501234567
   ```
3. Click "حفظ البطاقة"
4. **Expected:** Success message + redirect to card details

### **C. Test Search**

1. Enter "GIFT" in search box
2. Click "بحث"
3. **Expected:** Only gift cards displayed

### **D. Test Filters**

1. Select "نوع البطاقة" = "بطاقة ولاء"
2. Click "بحث"
3. **Expected:** Only loyalty cards displayed

### **E. Test Actions**

1. Click eye icon (👁️) to view card details
2. Click edit icon (✏️) to edit card
3. Click transactions icon (🔄) to view transactions
4. **Expected:** All pages load correctly

---

## 📊 **STEP 4: Understand the System**

### **How It Works:**

#### **Scenario 1: Simple Gift Card Purchase**
```
Customer buys card → 500 SAR
System creates card → GIFT-2025-XXX
Customer receives card number + password
Customer uses card for purchases
Balance decreases with each purchase
```

#### **Scenario 2: Card with Promotion**
```
Promotion: Buy 900 SAR, Get 100 SAR bonus
Customer buys card → 900 SAR
System applies promo automatically
Initial Balance: 900 SAR
Bonus Balance: 100 SAR
Current Balance: 1000 SAR
Customer can spend 1000 SAR total
```

#### **Scenario 3: Transfer Between Cards**
```
Card A has 500 SAR
Card B has 200 SAR
Transfer 100 SAR from A to B
Result:
  Card A: 400 SAR
  Card B: 300 SAR
Both transactions recorded
```

#### **Scenario 4: Savings Goal**
```
Customer sets goal: "Buy laptop - 2000 SAR"
Customer adds money monthly
System tracks progress
When goal reached → notification
Customer can use balance
```

---

## 🎯 **STEP 5: Key Features Overview**

### **1. Card Types:**
- 🎁 **Gift Card** (بطاقة هدية) - For gifts
- ⭐ **Loyalty Card** (بطاقة ولاء) - For regular customers
- 📢 **Promotional Card** (بطاقة ترويجية) - For campaigns

### **2. Transaction Types:**
- 💰 **Purchase** - Add balance
- 🔄 **Refund** - Return balance
- ➡️ **Transfer Out** - Send to another card
- ⬅️ **Transfer In** - Receive from another card
- 🎁 **Bonus** - Free balance added
- ⚙️ **Adjustment** - Manual correction

### **3. Card Status:**
- ✅ **Active** (نشطة) - Can be used
- ⏸️ **Inactive** (غير نشطة) - Temporarily disabled
- ⏰ **Expired** (منتهية) - Past expiry date
- 🚫 **Blocked** (محظورة) - Blocked by admin

### **4. Promotional Offers:**
- **Percentage Discount** - 10% off
- **Fixed Amount** - 50 SAR off
- **Bonus Balance** - +100 SAR free

---

## 🔐 **STEP 6: Security Features**

### **Password Protection:**
```php
- All passwords are hashed (password_hash)
- Cannot be retrieved, only verified
- Change password feature available
```

### **Transaction Logging:**
```php
- Every transaction recorded
- Balance before/after tracked
- User who performed action logged
- Timestamp for each transaction
```

### **Card Blocking:**
```php
- Admin can block suspicious cards
- Blocked cards cannot be used
- Can be unblocked later
- Block reason can be noted
```

---

## 📱 **STEP 7: User Interface Guide**

### **Main Page (index.php):**
```
┌─────────────────────────────────────┐
│  Statistics Cards (4 cards)         │
│  - Total Cards                      │
│  - Active Cards                     │
│  - Total Balance                    │
│  - Total Bonus                      │
├─────────────────────────────────────┤
│  Search & Filters                   │
│  - Search box                       │
│  - Card type filter                 │
│  - Status filter                    │
│  - Date range                       │
├─────────────────────────────────────┤
│  Cards Table                        │
│  - Card Number                      │
│  - Type                             │
│  - Customer                         │
│  - Current Balance                  │
│  - Bonus Balance                    │
│  - Total Spent                      │
│  - Status                           │
│  - Actions (View/Edit/Delete/Block) │
└─────────────────────────────────────┘
```

### **Add Card Page (add.php):**
```
┌─────────────────────────────────────┐
│  Card Information                   │
│  - Card Number (required)           │
│  - Password (required)              │
│  - Card Type (required)             │
│  - Expiry Date (optional)           │
├─────────────────────────────────────┤
│  Balance Information                │
│  - Initial Balance (required)       │
│  - Bonus Balance (optional)         │
│  - Promo Code (optional)            │
├─────────────────────────────────────┤
│  Customer Information               │
│  - Name (optional)                  │
│  - Phone (optional)                 │
│  - Email (optional)                 │
├─────────────────────────────────────┤
│  Notes                              │
│  - Additional notes (optional)      │
├─────────────────────────────────────┤
│  [Save] [Cancel]                    │
└─────────────────────────────────────┘
```

---

## 🔧 **STEP 8: Troubleshooting**

### **Problem: Tables not created**
```
Solution:
1. Check database connection in config/database.php
2. Ensure MySQL is running
3. Check user has CREATE privileges
4. Run setup script again
```

### **Problem: Cannot access page**
```
Solution:
1. Check URL is correct
2. Ensure you're logged in
3. Clear browser cache
4. Check file permissions
```

### **Problem: Sample cards not showing**
```
Solution:
1. Check database has data:
   SELECT * FROM loyalty_cards;
2. Check is_active = 1
3. Refresh page
```

### **Problem: Promo not applying**
```
Solution:
1. Check promo is active
2. Check dates (start_date <= today <= end_date)
3. Check min_purchase_amount met
4. Check usage_limit not exceeded
```

---

## 📊 **STEP 9: Database Verification**

### **Check Tables Exist:**
```sql
SHOW TABLES LIKE 'loyalty%';
```

**Expected Output:**
```
loyalty_cards
loyalty_card_transactions
loyalty_card_goals
loyalty_card_promotions
loyalty_card_promo_usage
```

### **Check Sample Data:**
```sql
SELECT card_number, card_type, current_balance, status 
FROM loyalty_cards 
WHERE is_active = 1;
```

**Expected Output:**
```
GIFT-2025-001       | gift         | 1000.000 | active
LOYALTY-2025-001    | loyalty      | 550.000  | active
PROMO-2025-SUMMER   | promotional  | 1000.000 | active
```

### **Check Promotions:**
```sql
SELECT promo_code, promo_name, promo_type, promo_value, status 
FROM loyalty_card_promotions 
WHERE status = 'active';
```

**Expected Output:**
```
SUMMER2025  | عرض الصيف 2025      | percentage    | 10.000  | active
BONUS100    | مكافأة 100 ريال     | bonus_balance | 100.000 | active
```

---

## 🎉 **STEP 10: Success Checklist**

### **Verify Everything Works:**

- [ ] Database tables created (5 tables)
- [ ] Sample data inserted (3 cards, 2 promos)
- [ ] Main page loads without errors
- [ ] Statistics cards show correct numbers
- [ ] Sample cards visible in table
- [ ] Search functionality works
- [ ] Filters work correctly
- [ ] Can add new card
- [ ] Can view card details
- [ ] Can edit card
- [ ] Can block/unblock card
- [ ] Can delete card
- [ ] Sidebar link visible
- [ ] Sidebar link active when on page
- [ ] No PHP errors in logs
- [ ] Responsive design works on mobile

---

## 📚 **STEP 11: Next Steps**

### **After Setup:**

1. **Create Real Cards:**
   - Delete or deactivate sample cards
   - Create cards for actual customers
   - Set appropriate expiry dates

2. **Configure Promotions:**
   - Create seasonal promotions
   - Set usage limits
   - Monitor promo effectiveness

3. **Integrate with Orders:**
   - Add card payment option to checkout
   - Link cards to purchase orders
   - Track card usage in orders

4. **Set Up Goals:**
   - Create savings goals for customers
   - Track goal progress
   - Send notifications on completion

5. **Generate Reports:**
   - Monthly card sales report
   - Promotion effectiveness report
   - Customer usage patterns
   - Revenue from cards

---

## 🔗 **Important URLs**

### **Setup:**
```
http://localhost/yassin-admin-system/setup/create_loyalty_cards_tables.php
```

### **Main Pages:**
```
http://localhost/yassin-admin-system/modules/loyalty-cards/index.php
http://localhost/yassin-admin-system/modules/loyalty-cards/add.php
http://localhost/yassin-admin-system/modules/loyalty-cards/view.php?id=1
http://localhost/yassin-admin-system/modules/loyalty-cards/edit.php?id=1
```

### **Documentation:**
```
modules/loyalty-cards/README.md - Complete documentation
LOYALTY_CARDS_SETUP_GUIDE.md - This file
```

---

## 📞 **Support**

### **For Help:**
1. Read `modules/loyalty-cards/README.md`
2. Check code comments
3. Review database structure
4. Test with sample data first

---

## 🎯 **Quick Reference**

### **Sample Card Credentials:**
```
Card 1:
  Number: GIFT-2025-001
  Password: 1234
  Balance: 1000 SAR

Card 2:
  Number: LOYALTY-2025-001
  Password: 5678
  Balance: 550 SAR (500 + 50 bonus)

Card 3:
  Number: PROMO-2025-SUMMER
  Password: 9999
  Balance: 1000 SAR (900 + 100 bonus)
```

### **Sample Promo Codes:**
```
SUMMER2025 - 10% discount
BONUS100 - +100 SAR bonus on 900 SAR purchase
```

---

## ✅ **Installation Complete!**

Your Loyalty Cards System is now ready to use! 🎉

**System Status:** ✅ Production Ready  
**Version:** 1.0.0  
**Date:** 2025-10-11

---

**Need more help?** Check the complete documentation in `modules/loyalty-cards/README.md`
