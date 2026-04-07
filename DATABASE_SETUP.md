# CargoTrack Database Setup Guide

## Quick Start

### Step 1: Check MySQL Status

```bash
# Check if MySQL is running
sudo systemctl status mysql

# If not running, start it
sudo systemctl start mysql
sudo systemctl enable mysql
```

### Step 2: Setup Database

**Option A: Using setup script (Recommended)**

```bash
# With password
DB_PASS=your_mysql_password php database/setup_database.php

# Or export first
export DB_USER=root
export DB_PASS=your_mysql_password
php database/setup_database.php
```

**Option B: Manual import**

```bash
# Login to MySQL
mysql -u root -p

# Then in MySQL prompt
source /home/nyanaro/VSCODE\ FILES/cargo_tracking_system/database/schema.sql;
```

**Option C: One-liner**

```bash
mysql -u root -p < database/schema.sql
```

### Step 3: Update Configuration

Edit `php/database.php` and set your MySQL password:

```php
private $password = "your_mysql_password";
```

### Step 4: Test Connection

```bash
# Start PHP server
php -S localhost:8000

# Open in browser
# http://localhost:8000
```

## Commands Reference

### Database Setup
```bash
# Run setup script
php database/setup_database.php

# With custom credentials
DB_USER=root DB_PASS=mypass php database/setup_database.php
```

### MySQL Access
```bash
# Login to MySQL
mysql -u root -p

# Login with specific host
mysql -u root -h localhost -p

# Access cargo_db directly
mysql -u root -p cargo_db
```

### Import/Export
```bash
# Import schema
mysql -u root -p < database/schema.sql

# Backup database
mysqldump -u root -p cargo_db > backup.sql

# Restore from backup
mysql -u root -p cargo_db < backup.sql
```

### Check Tables
```bash
# In MySQL
USE cargo_db;
SHOW TABLES;
DESCRIBE users;
SELECT * FROM users;
```

## Default Login Credentials

After setup, use these credentials:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@cargotrack.co.ke | admin123 |
| **Staff** | staff@cargotrack.co.ke | staff123 |
| **Customer** | customer@example.com | customer123 |

## Troubleshooting

### Error: Access denied for user 'root'@'localhost'

```bash
# Reset MySQL root password
sudo systemctl stop mysql
sudo mysqld_safe --skip-grant-tables &

# In another terminal
mysql -u root
> UPDATE mysql.user SET authentication_string=PASSWORD('newpassword') WHERE User='root';
> FLUSH PRIVILEGES;
> EXIT;

# Restart MySQL
sudo systemctl start mysql
```

### Error: MySQL server not running

```bash
# Start MySQL
sudo systemctl start mysql

# Enable on boot
sudo systemctl enable mysql

# Check status
sudo systemctl status mysql
```

### Error: Database already exists

```bash
# Drop and recreate
mysql -u root -p -e "DROP DATABASE IF EXISTS cargo_db;"
php database/setup_database.php
```

## File Structure

```
cargo_tracking_system/
├── database/
│   ├── schema.sql              # Database schema
│   ├── setup_database.php      # Setup script
│   └── README.md              # This file
├── php/
│   ├── database.php           # DB connection class
│   └── DatabaseAgent.php      # DB query agent
└── ...
```

## Next Steps

1. ✅ Run setup script
2. ✅ Update `php/database.php` with credentials
3. ✅ Start PHP server: `php -S localhost:8000`
4. ✅ Open http://localhost:8000
5. ✅ Login with default credentials
6. ✅ Change default passwords!
