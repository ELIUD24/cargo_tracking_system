#!/usr/bin/env php
<?php
/**
 * Database Setup Script for CargoTrack
 * 
 * This script initializes the database by:
 * 1. Creating the database if it doesn't exist
 * 2. Creating all required tables
 * 3. Inserting default data (users, drivers, vehicles)
 * 
 * Usage:
 *   php setup_database.php
 * 
 * Or with custom credentials:
 *   DB_USER=root DB_PASS=mypassword php setup_database.php
 */

echo "===========================================\n";
echo "  CargoTrack Database Setup\n";
echo "===========================================\n\n";

// Get credentials from environment or use defaults
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'cargo_db';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

echo "Configuration:\n";
echo "  Host: $dbHost\n";
echo "  Database: $dbName\n";
echo "  Username: $dbUser\n";
echo "  Password: " . (empty($dbPass) ? '(empty)' : '***') . "\n\n";

// Read schema file
$schemaFile = __DIR__ . '/../database/schema.sql';

if (!file_exists($schemaFile)) {
    echo "ERROR: Schema file not found at: $schemaFile\n";
    exit(1);
}

echo "Found schema file: $schemaFile\n\n";

try {
    // Connect without database selection
    $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to MySQL server\n\n";
    
    // Read and execute schema
    $schema = file_get_contents($schemaFile);
    
    // Split by semicolons (but handle multi-line statements)
    $statements = array_filter(
        array_map('trim', preg_split('/;(?=\s*(?:CREATE|INSERT|USE|SET|START|COMMIT))/', $schema)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "Executing schema (" . count($statements) . " statements)...\n\n";
    
    $created = 0;
    $inserted = 0;
    $errors = 0;
    
    foreach ($statements as $statement) {
        try {
            if (stripos($statement, 'CREATE DATABASE') !== false ||
                stripos($statement, 'USE') !== false) {
                // Skip these, we'll handle separately
                continue;
            }
            
            $pdo->exec($statement);
            
            if (stripos($statement, 'CREATE TABLE') !== false) {
                $created++;
                // Extract table name
                preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $statement, $matches);
                if (isset($matches[1])) {
                    echo "  ✓ Table created: {$matches[1]}\n";
                }
            } elseif (stripos($statement, 'INSERT') !== false) {
                $inserted++;
            }
            
        } catch (PDOException $e) {
            // Skip if table already exists
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "  ⚠ Table already exists (skipping)\n";
            } else {
                echo "  ✗ Error: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }
    
    echo "\n===========================================\n";
    echo "  Setup Complete!\n";
    echo "===========================================\n\n";
    echo "Summary:\n";
    echo "  Tables created: $created\n";
    echo "  Records inserted: $inserted\n";
    echo "  Errors: $errors\n\n";
    
    if ($errors === 0) {
        echo "✓ Database setup completed successfully!\n\n";
        
        echo "Default Users:\n";
        echo "  Admin:   admin@cargotrack.co.ke / admin123\n";
        echo "  Staff:   staff@cargotrack.co.ke / staff123\n";
        echo "  Customer: customer@example.com / customer123\n\n";
        
        echo "Next steps:\n";
        echo "  1. Update php/database.php with your database credentials\n";
        echo "  2. Start the PHP server: php -S localhost:8000\n";
        echo "  3. Open http://localhost:8000 in your browser\n\n";
    } else {
        echo "⚠ Setup completed with errors. Please review the messages above.\n\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Connection error: " . $e->getMessage() . "\n\n";
    echo "Troubleshooting:\n";
    echo "  1. Ensure MySQL server is running\n";
    echo "  2. Check your credentials (username, password)\n";
    echo "  3. Verify MySQL user has CREATE DATABASE privileges\n\n";
    echo "You can also set environment variables:\n";
    echo "  export DB_USER=root\n";
    echo "  export DB_PASS=yourpassword\n";
    echo "  php setup_database.php\n\n";
    exit(1);
}
?>
