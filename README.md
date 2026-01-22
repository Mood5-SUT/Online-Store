# E-Commerce Admin Management System

A comprehensive e-commerce administration system built with PHP, MySQL, and Bootstrap for managing online stores, products, orders, and brand settings.

## 📋 Features

### 🛡️ **Authentication & Security**
- Dual login system (Admin/User)
- Social login integration (Google, Microsoft, Yahoo via Firebase)
- Secure password hashing
- Session-based authentication
- Role-based access control

### 📊 **Admin Dashboard**
- Real-time analytics and metrics
- Sales statistics and revenue tracking
- Product inventory overview
- Active users monitoring
- Low-stock alerts

### 🛒 **Product Management**
- Add/edit/delete products with images
- Bulk product upload via CSV
- Category management
- Stock tracking and status management
- Multi-image support with main image selection
- Video upload capability
- Product attributes (JSON format)

### 📦 **Order Management**
- Complete order lifecycle management (Pending → Paid → Shipped → Completed → Cancelled)
- Order tracking number integration
- Customer information display
- Stock restoration on cancellation
- Professional invoice generation

### 🏷️ **Brand Management**
- Customizable brand settings
- Logo upload and management
- SEO meta tags configuration
- Social media links
- Contact information management

### 🧾 **Invoice System**
- Professional invoice generation
- Print-friendly design
- PDF export capability
- Invoice verification with QR code
- Branding integration

## 🏗️ **System Architecture**

### **Database Structure**
- **users**: Customer accounts
- **admin_users**: Administrator accounts
- **products**: Product catalog
- **categories**: Product categories
- **orders**: Customer orders
- **order_items**: Order line items
- **order_notes**: Admin notes on orders
- **product_images**: Product gallery
- **brand_settings**: Store configuration

### **Technology Stack**
- **Backend**: PHP 7.4+, PDO for database operations
- **Frontend**: Bootstrap 5.3, Font Awesome icons
- **Database**: MySQL 5.7+
- **Authentication**: Firebase Authentication for social logins
- **File Storage**: Local uploads with image/video processing

## 📁 **File Structure**

```
/
├── admin_page.php              # Admin dashboard
├── admin_orders.php           # Order listing
├── admin_order_view.php       # Order detail management
├── brand_manage.php          # Brand settings
├── db_connect.php            # Database connection
├── home_page.php             # Store frontend
├── invoice.php               # Invoice generator
├── login_page.php            # Login system
├── logout_page.php           # Session logout
├── products_page.php         # Product management
├── register_page.php         # User registration
├── social_login_handler.php  # Firebase authentication
├── uploads/                  # Uploaded files
│   ├── brand/               # Brand logos
│   ├── images/              # Product images
│   └── videos/              # Product videos
└── vendor/                  # Composer dependencies (Firebase)
```

## ⚙️ **Installation**

### **Prerequisites**
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer (for Firebase dependencies)
- Web server (Apache/Nginx)

### **Setup Steps**

1. **Clone or upload files to your web server**
   ```bash
   git clone [repository-url]
   cd [project-directory]
   ```

2. **Database Setup**
   - Create a MySQL database named `online_store`
   - Import the SQL structure (see `database_schema.sql`)
   - Configure database credentials in `db_connect.php`

3. **Firebase Setup**
   ```bash
   composer require kreait/firebase-php
   ```
   - Create a Firebase project at [firebase.google.com](https://firebase.google.com)
   - Download service account key as `serviceAccountKey.json`
   - Update Firebase config in `login_page.php`

4. **File Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/brand/
   chmod 755 uploads/images/
   chmod 755 uploads/videos/
   ```

5. **Configuration**
   - Update `db_connect.php` with your database credentials
   - Configure `login_page.php` with your Firebase config
   - Set proper file paths for uploads

## 🔧 **Configuration**

### **Database Connection**
Edit `db_connect.php`:
```php
$host = 'localhost';
$dbname = 'online_store';
$username = 'your_username';
$password = 'your_password';
```

### **Firebase Setup**
1. Enable authentication providers in Firebase Console
2. Add web app to Firebase project
3. Copy config to `login_page.php`
4. Place service account key in project root

### **File Uploads**
- Default upload directory: `/uploads/`
- Max image size: 5MB
- Max video size: 50MB
- Supported formats: JPG, PNG, GIF, WebP, MP4, WebM, OGG

## 👥 **User Roles**

### **Admin**
- Access: `/admin_page.php`
- Permissions:
  - Manage all products
  - Process orders
  - View customer information
  - Configure brand settings
  - Access analytics dashboard

### **Customer**
- Access: Store frontend
- Permissions:
  - Browse products
  - Place orders
  - View personal order history
  - Download invoices

## 📄 **Database Schema**

### **Main Tables**

#### **users**
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(50),
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### **admin_users**
```sql
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### **products**
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    category_id INT,
    price DECIMAL(10,2),
    discount_price DECIMAL(10,2) DEFAULT 0,
    stock INT DEFAULT 0,
    stock_status ENUM('in_stock','out_of_stock','preorder') DEFAULT 'in_stock',
    description TEXT,
    attributes JSON,
    video VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## 🔐 **Security Features**

- **SQL Injection Prevention**: PDO prepared statements
- **XSS Protection**: HTML escaping with `htmlspecialchars()`
- **CSRF Protection**: Session-based form validation
- **Password Security**: Bcrypt hashing via `password_hash()`
- **File Upload Security**: Extension validation, size limits
- **Session Management**: Secure session handling with timeout
- **Access Control**: Role-based permissions

## 🚀 **Usage Guide**

### **Admin Login**
1. Navigate to `/login_page.php`
2. Use admin email credentials
3. Access dashboard at `/admin_page.php`

### **Adding Products**
1. Go to Products Management
2. Click "Add Product"
3. Fill product details
4. Upload images/videos
5. Set pricing and stock
6. Save product

### **Processing Orders**
1. Access Orders Management
2. View order details
3. Update order status
4. Add tracking numbers
5. Generate invoices
6. Handle cancellations

### **Brand Configuration**
1. Access Brand Management
2. Upload logo
3. Set contact information
4. Configure SEO settings
5. Add social media links

## 📱 **Responsive Design**

- Mobile-first approach with Bootstrap 5
- Responsive tables and forms
- Touch-friendly interfaces
- Print-optimized invoices

## 🔍 **SEO Features**

- Configurable meta tags
- Brand-specific SEO settings
- Clean URL structure
- Mobile-optimized pages

## 🛠️ **Troubleshooting**

### **Common Issues**

1. **Database Connection Error**
   - Check credentials in `db_connect.php`
   - Verify MySQL service is running
   - Ensure database exists

2. **File Upload Failures**
   - Check directory permissions
   - Verify PHP upload limits
   - Check file size restrictions

3. **Firebase Authentication Issues**
   - Verify service account key
   - Check Firebase project configuration
   - Enable authentication providers

4. **Session Problems**
   - Check PHP session configuration
   - Verify file permissions for session storage
   - Check cookie settings

### **Debug Mode**
Enable debug logging in PHP files:
```php
error_log("Debug message");
```
Check web server error logs for details.

## 📈 **Performance Optimization**

- Efficient database indexing
- Pagination for large datasets
- Image optimization on upload
- Minified CSS/JS libraries
- Database query optimization

## 🔄 **Updates & Maintenance**

### **Regular Tasks**
- Monitor error logs
- Backup database regularly
- Update product inventory
- Review security logs
- Clean up old uploads

### **Security Updates**
- Keep PHP version updated
- Regular security patches
- Monitor for vulnerabilities
- Update dependencies

## 📄 **License**

This project is proprietary software. All rights reserved.

## 🤝 **Support**

For technical support:
1. Check the troubleshooting section
2. Review error logs
3. Verify configuration settings
4. Contact system administrator

## 📚 **Documentation Files**

- This README file
- Database schema documentation
- API documentation (if applicable)
- User manual (separate document)

---

**System Version**: 1.0.0  
**Last Updated**: 2024  
**PHP Version**: 7.4+  
**Database**: MySQL 5.7+  
**Frontend**: Bootstrap 5.3
