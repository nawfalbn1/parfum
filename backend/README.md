# Fragrance by Nawfal – Backend System

## Project Structure

```
backend/
├── config/
│   ├── config.php          ← App constants (URL, session, shipping)
│   └── database.php        ← PDO singleton connection
├── models/
│   ├── User.php            ← User CRUD + password hashing
│   ├── Product.php         ← Products CRUD + search + stock
│   ├── Cart.php            ← Cart (guest + logged-in)
│   ├── Order.php           ← Order creation + stock deduction
│   ├── Wishlist.php        ← Wishlist toggle/list
│   ├── Review.php          ← Reviews + rating recalc
│   └── Contact.php         ← Contact form storage
├── controllers/
│   ├── AuthController.php  ← Register, login, logout, session
│   ├── ProductController.php
│   └── OrderController.php
├── api/
│   ├── products.php        ← REST: GET/POST/PUT/DELETE products
│   ├── orders.php          ← REST: checkout + order management
│   ├── cart.php            ← REST: add/update/remove cart items
│   ├── auth.php            ← REST: register/login/logout/me
│   └── misc.php            ← REST: contact, reviews, wishlist
├── views/
│   ├── auth/
│   │   ├── login.php       ← Login page
│   │   └── register.php    ← Register page
│   └── admin/
│       └── dashboard.php   ← Full admin panel
├── database/
│   └── schema.sql          ← All table definitions + default admin
└── index.php               ← Entry point / router
```

---

## ⚙️ Setup Instructions

### 1. Install Requirements
- PHP 8.0+
- MySQL 8.0+
- A web server (Apache / Nginx) **or** use PHP built-in server

### 2. Create the Database
Open MySQL and run:
```sql
source /path/to/backend/database/schema.sql;
```
Or import it via **phpMyAdmin**.

### 3. Configure the Database Connection
Open `backend/config/database.php` and update:
```php
private static string $host     = '127.0.0.1';
private static string $dbname   = 'fragrance_nawfal';
private static string $username = 'root';
private static string $password = '';   // your MySQL password
```

### 4. Update the App URL
Open `backend/config/config.php` and set:
```php
define('APP_URL', 'http://localhost:8000'); // or your server URL
```

### 5. Run the Server
```bash
cd "site dyali"
php -S localhost:8000
```

---

## 🔐 Default Admin Account
| Field    | Value                         |
|----------|-------------------------------|
| Email    | admin@fragrance-nawfal.ma     |
| Password | Admin@1234                    |

> ⚠️ Change this password immediately after first login!

**Admin Panel:** http://localhost:8000/backend/views/admin/dashboard.php

---

## 🌐 API Endpoints

### Products
| Method | URL                              | Auth     | Description         |
|--------|----------------------------------|----------|---------------------|
| GET    | `/backend/api/products.php`      | Public   | List + filter       |
| GET    | `/backend/api/products.php?id=X` | Public   | Single product      |
| GET    | `/backend/api/products.php?q=X`  | Public   | Search              |
| POST   | `/backend/api/products.php`      | Admin    | Create product      |
| PUT    | `/backend/api/products.php?id=X` | Admin    | Update product      |
| DELETE | `/backend/api/products.php?id=X` | Admin    | Delete product      |

### Orders
| Method | URL                             | Auth     | Description         |
|--------|---------------------------------|----------|---------------------|
| POST   | `/backend/api/orders.php`       | Public   | Checkout            |
| GET    | `/backend/api/orders.php`       | User     | My orders           |
| PUT    | `/backend/api/orders.php?id=X`  | Admin    | Update status       |

### Cart
| Method | URL                                        | Auth   | Description    |
|--------|--------------------------------------------|--------|----------------|
| GET    | `/backend/api/cart.php`                    | Any    | Get cart       |
| POST   | `/backend/api/cart.php`                    | Any    | Add item       |
| PUT    | `/backend/api/cart.php?item_id=X`          | Any    | Update qty     |
| DELETE | `/backend/api/cart.php?item_id=X`          | Any    | Remove item    |
| DELETE | `/backend/api/cart.php?action=clear`       | Any    | Clear cart     |

### Auth
| Method | URL                                  | Description     |
|--------|--------------------------------------|-----------------|
| POST   | `/backend/api/auth.php?action=register` | Register     |
| POST   | `/backend/api/auth.php?action=login`    | Login        |
| POST   | `/backend/api/auth.php?action=logout`   | Logout       |
| GET    | `/backend/api/auth.php?action=me`       | Current user |

### Other
| Method | URL                              | Description        |
|--------|----------------------------------|--------------------|
| POST   | `/backend/api/misc.php?type=contact`  | Contact form  |
| GET    | `/backend/api/misc.php?type=review&product_id=X` | Get reviews |
| POST   | `/backend/api/misc.php?type=review`   | Submit review |
| GET    | `/backend/api/misc.php?type=wishlist` | My wishlist   |
| POST   | `/backend/api/misc.php?type=wishlist` | Toggle wishlist|

---

## 🔒 Security Features
- **Bcrypt** password hashing (cost 12)
- **PDO prepared statements** — SQL injection prevention
- **Session regeneration** on login
- **HttpOnly + SameSite** session cookies
- **Input sanitization** with `htmlspecialchars` + `strip_tags`
- **Admin-only routes** protected by `requireAdmin()` middleware
- **CSRF protection** via session-based checks
