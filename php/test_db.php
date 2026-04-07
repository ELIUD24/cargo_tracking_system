<?php
/**
 * Database Connection Test Script
 * 
 * Run this to verify database connectivity
 * Usage: php test_db.php
 */

echo "===========================================\n";
echo "  CargoTrack Database Connection Test\n";
echo "===========================================\n\n";

require_once __DIR__ . '/database.php';

try {
    echo "1. Testing database connection...\n";
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($conn) {
        echo "   ✓ Database connection successful!\n\n";
        
        echo "2. Checking if tables exist...\n";
        $tables = ['users', 'shipments', 'clearances', 'drivers', 'vehicles'];
        
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "   ✓ Table '$table' exists\n";
            } else {
                echo "   ✗ Table '$table' NOT FOUND\n";
            }
        }
        echo "\n";
        
        echo "3. Testing user registration...\n";
        $testEmail = 'test_' . time() . '@example.com';
        $testPassword = password_hash('test123', PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'customer', 'active')");
            $result = $stmt->execute(['Test User', $testEmail, $testPassword, '+254700000000']);
            
            if ($result) {
                $userId = $conn->lastInsertId();
                echo "   ✓ Test user created with ID: $userId\n";
                
                // Clean up
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                echo "   ✓ Test user cleaned up\n";
            } else {
                echo "   ✗ Failed to insert test user\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
        }
        echo "\n";
        
        echo "4. Checking existing users...\n";
        $stmt = $conn->query("SELECT id, name, email, role FROM users");
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            echo "   ✓ Found " . count($users) . " users:\n";
            foreach ($users as $user) {
                echo "      - {$user['name']} ({$user['email']}) - {$user['role']}\n";
            }
        } else {
            echo "   ⚠ No users found in database\n";
            echo "   → Run: mysql -u root -p < database/schema.sql\n";
        }
        
    } else {
        echo "   ✗ Connection failed - conn is null\n\n";
    }
    
} catch (PDOException $e) {
    echo "   ✗ PDO Exception: " . $e->getMessage() . "\n\n";
    echo "Troubleshooting:\n";
    echo "  1. Check if MySQL is running: sudo systemctl status mysql\n";
    echo "  2. Verify credentials in php/database.php\n";
    echo "  3. Test MySQL access: mysql -u root -p\n";
    echo "  4. Import schema: mysql -u root -p < database/schema.sql\n\n";
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n\n";
}

echo "===========================================\n";
echo "  Test Complete\n";
echo "===========================================\n";
?>
