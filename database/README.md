# CargoTrack Database Documentation

## Overview

This directory contains all database-related files for the CargoTrack system.

## Files

- `schema.sql` - Complete database schema with all tables and default data
- `setup_database.php` - Automated setup script to initialize the database

## Database Schema

### Tables

| Table | Description |
|-------|-------------|
| `users` | System users (admin, staff, customer) |
| `customers` | Extended customer profiles |
| `drivers` | Driver information |
| `vehicles` | Vehicle fleet |
| `shipments` | Main shipment records |
| `shipment_tracking` | Tracking history and locations |
| `clearances` | Customs clearance requests |
| `clearance_requests` | Legacy clearance table |
| `notifications` | User notifications |
| `message_logs` | SMS/WhatsApp logs |
| `payments` | Payment records |
| `audit_logs` | System activity logs |

### Entity Relationships

```
users (1) ── (M) shipments
users (1) ── (M) notifications
users (1) ── (1) customers

shipments (1) ── (M) shipment_tracking
shipments (1) ── (1) clearances
shipments (1) ── (M) payments

clearances (M) ── (1) drivers
clearances (M) ── (1) vehicles
```

## Setup Instructions

### Method 1: Automated Setup (Recommended)

```bash
# Navigate to database directory
cd database

# Run setup script
php setup_database.php

# Or with custom credentials
DB_USER=root DB_PASS=mypassword php setup_database.php
```

### Method 2: Manual Setup

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE cargo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Use database
USE cargo_db;

# Import schema
source /path/to/schema.sql;
```

### Method 3: Using MySQL Command Line

```bash
mysql -u root -p < database/schema.sql
```

## Configuration

Update `php/database.php` with your credentials:

```php
private $host = "localhost";
private $db_name = "cargo_db";
private $username = "root";
private $password = "your_password";
```

Or use environment variables:

```bash
export DB_HOST=localhost
export DB_NAME=cargo_db
export DB_USER=root
export DB_PASS=yourpassword
```

## Default Users

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@cargotrack.co.ke | admin123 |
| Staff | staff@cargotrack.co.ke | staff123 |
| Customer | customer@example.com | customer123 |

**⚠️ Change these passwords in production!**

## Database Agent

The `DatabaseAgent` class (`php/DatabaseAgent.php`) provides a unified interface for all database operations.

### Usage Examples

```php
<?php
require_once 'php/DatabaseAgent.php';

$dbAgent = new DatabaseAgent();

// Get all shipments
$shipments = $dbAgent->getAllShipments();

// Get shipment by tracking number
$shipment = $dbAgent->getShipmentByTrackingNumber('CG20260324ABC123KE');

// Get dashboard statistics
$stats = $dbAgent->getDashboardStats();

// Create notification
$dbAgent->createNotification($userId, 'Shipment Approved', 'Your shipment is on the way!', 'success');

// Update shipment status
$dbAgent->updateShipmentStatus($shipmentId, 'in_transit', 'Departed from warehouse');
?>
```

### Available Methods

**Users:**
- `getUserByEmail($email)`
- `getUserById($id)`
- `getAllUsers($role = null)`
- `createUser($data)`
- `updateUser($id, $data)`
- `deleteUser($id)`

**Shipments:**
- `getShipmentByTrackingNumber($trackingNumber)`
- `getShipmentById($id)`
- `getAllShipments($filters = [])`
- `createShipment($data)`
- `updateShipmentStatus($id, $status, $notes = null)`
- `deleteShipment($id)`

**Tracking:**
- `addTrackingUpdate($shipmentId, $data)`
- `getTrackingHistory($shipmentId)`

**Clearances:**
- `getClearanceById($id)`
- `getAllClearances($filters = [])`
- `createClearance($data)`
- `updateClearanceStatus($id, $status, $adminId = null)`

**Drivers & Vehicles:**
- `getAllDrivers($status = null)`
- `createDriver($data)`
- `getAllVehicles($status = null)`
- `createVehicle($data)`

**Notifications:**
- `createNotification($userId, $title, $message, $type = 'info')`
- `getUserNotifications($userId, $limit = 20, $unreadOnly = false)`
- `markNotificationAsRead($id)`
- `markAllNotificationsAsRead($userId)`

**Statistics:**
- `getDashboardStats()`
- `getShipmentsByStatus()`
- `getRecentShipments($limit = 10)`

**Utilities:**
- `generateTrackingNumber()`
- `beginTransaction()`, `commit()`, `rollback()`
- `query($sql, $params = [])`
- `logAudit($userId, $action, $entityType, $entityId, $oldValues, $newValues)`

## Troubleshooting

### Connection Error

```
Database connection failed. Please check configuration.
```

**Solution:**
1. Verify MySQL is running: `sudo systemctl status mysql`
2. Check credentials in `php/database.php`
3. Test connection: `mysql -u root -p`

### Access Denied

```
Access denied for user 'root'@'localhost'
```

**Solution:**
1. Reset MySQL password
2. Grant privileges: `GRANT ALL ON cargo_db.* TO 'root'@'localhost';`

### Table Already Exists

```
Table 'users' already exists
```

**Solution:**
- Drop existing database: `DROP DATABASE cargo_db;`
- Or run setup with fresh database

## Backup & Restore

### Backup

```bash
mysqldump -u root -p cargo_db > backup_$(date +%Y%m%d).sql
```

### Restore

```bash
mysql -u root -p cargo_db < backup_20260324.sql
```

## Security Notes

1. **Change default passwords** immediately after setup
2. Use **strong passwords** for database users
3. **Limit database user privileges** to only what's needed
4. Enable **MySQL query logging** in production
5. Use **prepared statements** (DatabaseAgent does this by default)
6. **Sanitize all inputs** before database queries
7. **Regular backups** of production database

## Performance Tips

1. Add indexes for frequently queried columns
2. Use query caching for static data
3. Archive old shipments to separate tables
4. Monitor slow query log
5. Optimize table structure as data grows

## Support

For database issues, check:
- MySQL error logs: `/var/log/mysql/error.log`
- PHP error logs
- Application logs in `logs/` directory
