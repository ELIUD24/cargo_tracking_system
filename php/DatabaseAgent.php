<?php
/**
 * Database Agent - Central Reference Point for All Database Queries
 * 
 * This class provides a unified interface for all database operations
 * in the CargoTrack system. It extends the base Database connection
 * class with commonly used query methods and helpers.
 * 
 * Usage:
 *   $dbAgent = new DatabaseAgent();
 *   $conn = $dbAgent->connect();
 *   $users = $dbAgent->getAllUsers();
 * 
 * @package CargoTrack
 * @author CargoTrack System
 * @version 1.0.0
 */

require_once __DIR__ . '/database.php';

class DatabaseAgent {
    
    private $db;
    private $conn;
    
    // Table names constants
    const TABLE_USERS = 'users';
    const TABLE_SHIPMENTS = 'shipments';
    const TABLE_SHIPMENT_TRACKING = 'shipment_tracking';
    const TABLE_CLEARANCES = 'clearances';
    const TABLE_CLEARANCE_REQUESTS = 'clearance_requests';
    const TABLE_DRIVERS = 'drivers';
    const TABLE_VEHICLES = 'vehicles';
    const TABLE_NOTIFICATIONS = 'notifications';
    const TABLE_MESSAGE_LOGS = 'message_logs';
    const TABLE_PAYMENTS = 'payments';
    const TABLE_AUDIT_LOGS = 'audit_logs';
    const TABLE_CUSTOMERS = 'customers';
    
    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    
    // Role constants
    const ROLE_ADMIN = 'admin';
    const ROLE_STAFF = 'staff';
    const ROLE_CUSTOMER = 'customer';
    
    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = null;
    }
    
    /**
     * Get database connection
     * @return PDO Database connection
     */
    public function connect() {
        if ($this->conn === null) {
            $this->conn = $this->db->getConnection();
        }
        return $this->conn;
    }
    
    /**
     * Close database connection
     */
    public function close() {
        $this->conn = null;
    }
    
    // ============================================
    // USER MANAGEMENT
    // ============================================
    
    /**
     * Get user by email
     * @param string $email
     * @return array|false User data or false if not found
     */
    public function getUserByEmail($email) {
        $stmt = $this->connect()->prepare("
            SELECT * FROM " . self::TABLE_USERS . " 
            WHERE email = :email 
            LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }
    
    /**
     * Get user by ID
     * @param int $id
     * @return array|false User data or false if not found
     */
    public function getUserById($id) {
        $stmt = $this->connect()->prepare("
            SELECT id, name, email, phone, role, status, created_at 
            FROM " . self::TABLE_USERS . " 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all users with optional role filter
     * @param string|null $role Filter by role
     * @return array List of users
     */
    public function getAllUsers($role = null) {
        $sql = "SELECT id, name, email, phone, role, status, created_at 
                FROM " . self::TABLE_USERS;
        
        if ($role) {
            $sql .= " WHERE role = :role";
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->connect()->prepare($sql);
        if ($role) {
            $stmt->execute(['role' => $role]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    /**
     * Create new user
     * @param array $data User data
     * @return int|false New user ID or false on failure
     */
    public function createUser($data) {
        $sql = "INSERT INTO " . self::TABLE_USERS . " 
                (name, email, password, phone, role, status) 
                VALUES (:name, :email, :password, :phone, :role, :status)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? self::ROLE_CUSTOMER,
            'status' => $data['status'] ?? self::STATUS_ACTIVE
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    /**
     * Update user
     * @param int $id User ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function updateUser($id, $data) {
        $allowed = ['name', 'email', 'phone', 'role', 'status'];
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE " . self::TABLE_USERS . " 
                SET " . implode(', ', $fields) . " 
                WHERE id = :id";
        
        $stmt = $this->connect()->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete user
     * @param int $id User ID
     * @return bool Success status
     */
    public function deleteUser($id) {
        $stmt = $this->connect()->prepare("
            DELETE FROM " . self::TABLE_USERS . " 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    // ============================================
    // SHIPMENT MANAGEMENT
    // ============================================
    
    /**
     * Get shipment by tracking number
     * @param string $trackingNumber
     * @return array|false Shipment data or false if not found
     */
    public function getShipmentByTrackingNumber($trackingNumber) {
        $stmt = $this->connect()->prepare("
            SELECT s.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
            FROM " . self::TABLE_SHIPMENTS . " s
            LEFT JOIN " . self::TABLE_USERS . " u ON s.customer_id = u.id
            WHERE s.tracking_number = :tracking_number
            LIMIT 1
        ");
        $stmt->execute(['tracking_number' => $trackingNumber]);
        return $stmt->fetch();
    }
    
    /**
     * Get shipment by ID
     * @param int $id Shipment ID
     * @return array|false Shipment data or false if not found
     */
    public function getShipmentById($id) {
        $stmt = $this->connect()->prepare("
            SELECT s.*, u.name as customer_name, u.email as customer_email
            FROM " . self::TABLE_SHIPMENTS . " s
            LEFT JOIN " . self::TABLE_USERS . " u ON s.customer_id = u.id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all shipments with optional filters
     * @param array $filters Filter options
     * @return array List of shipments
     */
    public function getAllShipments($filters = []) {
        $sql = "SELECT s.*, u.name as customer_name, u.email as customer_email
                FROM " . self::TABLE_SHIPMENTS . " s
                LEFT JOIN " . self::TABLE_USERS . " u ON s.customer_id = u.id";
        
        $where = [];
        $params = [];
        
        if (isset($filters['status'])) {
            $where[] = "s.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['customer_id'])) {
            $where[] = "s.customer_id = :customer_id";
            $params['customer_id'] = $filters['customer_id'];
        }
        
        if (isset($filters['transport_mode'])) {
            $where[] = "s.transport_mode = :transport_mode";
            $params['transport_mode'] = $filters['transport_mode'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY s.created_at DESC";
        
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new shipment
     * @param array $data Shipment data
     * @return int|false New shipment ID or false on failure
     */
    public function createShipment($data) {
        $sql = "INSERT INTO " . self::TABLE_SHIPMENTS . " 
                (tracking_number, customer_id, shipment_type, transport_mode, goods_category,
                 goods_description, weight, sender_name, sender_phone, sender_address,
                 receiver_name, receiver_phone, receiver_address, pickup_location,
                 delivery_location, status, service_type, price, notes)
                VALUES (:tracking_number, :customer_id, :shipment_type, :transport_mode,
                        :goods_category, :goods_description, :weight, :sender_name,
                        :sender_phone, :sender_address, :receiver_name, :receiver_phone,
                        :receiver_address, :pickup_location, :delivery_location, :status,
                        :service_type, :price, :notes)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'tracking_number' => $data['tracking_number'],
            'customer_id' => $data['customer_id'],
            'shipment_type' => $data['shipment_type'] ?? 'local',
            'transport_mode' => $data['transport_mode'],
            'goods_category' => $data['goods_category'] ?? null,
            'goods_description' => $data['goods_description'] ?? null,
            'weight' => $data['weight'] ?? null,
            'sender_name' => $data['sender_name'] ?? null,
            'sender_phone' => $data['sender_phone'] ?? null,
            'sender_address' => $data['sender_address'] ?? null,
            'receiver_name' => $data['receiver_name'] ?? null,
            'receiver_phone' => $data['receiver_phone'] ?? null,
            'receiver_address' => $data['receiver_address'] ?? null,
            'pickup_location' => $data['pickup_location'] ?? null,
            'delivery_location' => $data['delivery_location'] ?? null,
            'status' => $data['status'] ?? self::STATUS_PENDING,
            'service_type' => $data['service_type'] ?? 'standard',
            'price' => $data['price'] ?? null,
            'notes' => $data['notes'] ?? null
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    /**
     * Update shipment status
     * @param int $id Shipment ID
     * @param string $status New status
     * @param string|null $notes Optional notes
     * @return bool Success status
     */
    public function updateShipmentStatus($id, $status, $notes = null) {
        $sql = "UPDATE " . self::TABLE_SHIPMENTS . " 
                SET status = :status, updated_at = NOW()";
        
        $params = ['id' => $id, 'status' => $status];
        
        if ($notes !== null) {
            $sql .= ", admin_notes = :notes";
            $params['notes'] = $notes;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->connect()->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete shipment
     * @param int $id Shipment ID
     * @return bool Success status
     */
    public function deleteShipment($id) {
        $stmt = $this->connect()->prepare("
            DELETE FROM " . self::TABLE_SHIPMENTS . " 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    // ============================================
    // SHIPMENT TRACKING
    // ============================================
    
    /**
     * Add tracking update
     * @param int $shipmentId Shipment ID
     * @param array $data Tracking data
     * @return int|false New tracking ID or false on failure
     */
    public function addTrackingUpdate($shipmentId, $data) {
        $sql = "INSERT INTO " . self::TABLE_SHIPMENT_TRACKING . " 
                (shipment_id, status, location, latitude, longitude, description, updated_by)
                VALUES (:shipment_id, :status, :location, :latitude, :longitude, :description, :updated_by)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'shipment_id' => $shipmentId,
            'status' => $data['status'],
            'location' => $data['location'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'description' => $data['description'] ?? null,
            'updated_by' => $data['updated_by'] ?? null
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    /**
     * Get tracking history for shipment
     * @param int $shipmentId Shipment ID
     * @return array List of tracking records
     */
    public function getTrackingHistory($shipmentId) {
        $stmt = $this->connect()->prepare("
            SELECT * FROM " . self::TABLE_SHIPMENT_TRACKING . " 
            WHERE shipment_id = :shipment_id
            ORDER BY timestamp DESC
        ");
        $stmt->execute(['shipment_id' => $shipmentId]);
        return $stmt->fetchAll();
    }
    
    // ============================================
    // CLEARANCE MANAGEMENT
    // ============================================
    
    /**
     * Get clearance by ID
     * @param int $id Clearance ID
     * @return array|false Clearance data or false if not found
     */
    public function getClearanceById($id) {
        $stmt = $this->connect()->prepare("
            SELECT c.*, s.shipment_type, s.transport_mode,
                   u.name as staff_name,
                   d.name as driver_name, d.phone as driver_phone,
                   v.registration_number as vehicle_reg
            FROM " . self::TABLE_CLEARANCES . " c
            JOIN " . self::TABLE_SHIPMENTS . " s ON c.shipment_id = s.id
            JOIN " . self::TABLE_USERS . " u ON c.staff_id = u.id
            LEFT JOIN " . self::TABLE_DRIVERS . " d ON c.driver_id = d.id
            LEFT JOIN " . self::TABLE_VEHICLES . " v ON c.vehicle_id = v.id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all clearances with optional filters
     * @param array $filters Filter options
     * @return array List of clearances
     */
    public function getAllClearances($filters = []) {
        $sql = "SELECT c.*, s.shipment_type, s.transport_mode, s.status as shipment_status,
                       u.name as staff_name,
                       d.name as driver_name, d.phone as driver_phone,
                       v.registration_number as vehicle_reg, v.type as vehicle_type
                FROM " . self::TABLE_CLEARANCES . " c
                JOIN " . self::TABLE_SHIPMENTS . " s ON c.shipment_id = s.id
                JOIN " . self::TABLE_USERS . " u ON c.staff_id = u.id
                LEFT JOIN " . self::TABLE_DRIVERS . " d ON c.driver_id = d.id
                LEFT JOIN " . self::TABLE_VEHICLES . " v ON c.vehicle_id = v.id";
        
        $where = [];
        $params = [];
        
        if (isset($filters['clearance_status'])) {
            $where[] = "c.clearance_status = :clearance_status";
            $params['clearance_status'] = $filters['clearance_status'];
        }
        
        if (isset($filters['shipment_id'])) {
            $where[] = "c.shipment_id = :shipment_id";
            $params['shipment_id'] = $filters['shipment_id'];
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $sql .= " ORDER BY c.created_at DESC";
        
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Create clearance
     * @param array $data Clearance data
     * @return int|false New clearance ID or false on failure
     */
    public function createClearance($data) {
        $sql = "INSERT INTO " . self::TABLE_CLEARANCES . " 
                (shipment_id, tracking_number, staff_id, driver_id, vehicle_id, clearance_type, clearance_status)
                VALUES (:shipment_id, :tracking_number, :staff_id, :driver_id, :vehicle_id, :clearance_type, :clearance_status)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'shipment_id' => $data['shipment_id'],
            'tracking_number' => $data['tracking_number'],
            'staff_id' => $data['staff_id'],
            'driver_id' => $data['driver_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'clearance_type' => $data['clearance_type'] ?? 'import',
            'clearance_status' => $data['clearance_status'] ?? self::STATUS_PENDING
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    /**
     * Update clearance status
     * @param int $id Clearance ID
     * @param string $status New status
     * @param int|null $adminId Admin ID approving/rejecting
     * @return bool Success status
     */
    public function updateClearanceStatus($id, $status, $adminId = null) {
        $sql = "UPDATE " . self::TABLE_CLEARANCES . " 
                SET clearance_status = :status";
        
        $params = ['id' => $id, 'status' => $status];
        
        if ($adminId !== null && $status === self::STATUS_APPROVED) {
            $sql .= ", admin_id = :admin_id, approved_at = NOW()";
            $params['admin_id'] = $adminId;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->connect()->prepare($sql);
        return $stmt->execute($params);
    }
    
    // ============================================
    // DRIVER MANAGEMENT
    // ============================================
    
    /**
     * Get all drivers
     * @param string|null $status Filter by status
     * @return array List of drivers
     */
    public function getAllDrivers($status = null) {
        $sql = "SELECT * FROM " . self::TABLE_DRIVERS;
        
        if ($status) {
            $sql .= " WHERE status = :status";
        }
        
        $sql .= " ORDER BY name";
        
        $stmt = $this->connect()->prepare($sql);
        if ($status) {
            $stmt->execute(['status' => $status]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    /**
     * Create driver
     * @param array $data Driver data
     * @return int|false New driver ID or false on failure
     */
    public function createDriver($data) {
        $sql = "INSERT INTO " . self::TABLE_DRIVERS . " 
                (name, phone, license_number, id_number, status)
                VALUES (:name, :phone, :license_number, :id_number, :status)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'license_number' => $data['license_number'],
            'id_number' => $data['id_number'],
            'status' => $data['status'] ?? self::STATUS_PENDING
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    // ============================================
    // VEHICLE MANAGEMENT
    // ============================================
    
    /**
     * Get all vehicles
     * @param string|null $status Filter by status
     * @return array List of vehicles
     */
    public function getAllVehicles($status = null) {
        $sql = "SELECT * FROM " . self::TABLE_VEHICLES;
        
        if ($status) {
            $sql .= " WHERE status = :status";
        }
        
        $sql .= " ORDER BY registration_number";
        
        $stmt = $this->connect()->prepare($sql);
        if ($status) {
            $stmt->execute(['status' => $status]);
        } else {
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }
    
    /**
     * Create vehicle
     * @param array $data Vehicle data
     * @return int|false New vehicle ID or false on failure
     */
    public function createVehicle($data) {
        $sql = "INSERT INTO " . self::TABLE_VEHICLES . " 
                (registration_number, type, capacity_kg, model, year, color, status)
                VALUES (:registration_number, :type, :capacity_kg, :model, :year, :color, :status)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'registration_number' => $data['registration_number'],
            'type' => $data['type'],
            'capacity_kg' => $data['capacity_kg'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'color' => $data['color'] ?? null,
            'status' => $data['status'] ?? self::STATUS_PENDING
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    // ============================================
    // NOTIFICATION MANAGEMENT
    // ============================================
    
    /**
     * Create notification
     * @param int $userId User ID
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (info, success, warning, error)
     * @return int|false New notification ID or false on failure
     */
    public function createNotification($userId, $title, $message, $type = 'info') {
        $sql = "INSERT INTO " . self::TABLE_NOTIFICATIONS . " 
                (user_id, title, message, type)
                VALUES (:user_id, :title, :message, :type)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
    
    /**
     * Get notifications for user
     * @param int $userId User ID
     * @param int $limit Max number of notifications
     * @param bool $unreadOnly Only get unread notifications
     * @return array List of notifications
     */
    public function getUserNotifications($userId, $limit = 20, $unreadOnly = false) {
        $sql = "SELECT * FROM " . self::TABLE_NOTIFICATIONS . " 
                WHERE user_id = :user_id";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $this->connect()->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Mark notification as read
     * @param int $id Notification ID
     * @return bool Success status
     */
    public function markNotificationAsRead($id) {
        $stmt = $this->connect()->prepare("
            UPDATE " . self::TABLE_NOTIFICATIONS . " 
            SET is_read = 1 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Mark all notifications as read for user
     * @param int $userId User ID
     * @return bool Success status
     */
    public function markAllNotificationsAsRead($userId) {
        $stmt = $this->connect()->prepare("
            UPDATE " . self::TABLE_NOTIFICATIONS . " 
            SET is_read = 1 
            WHERE user_id = :user_id AND is_read = 0
        ");
        return $stmt->execute(['user_id' => $userId]);
    }
    
    // ============================================
    // STATISTICS & REPORTS
    // ============================================
    
    /**
     * Get dashboard statistics
     * @return array Statistics data
     */
    public function getDashboardStats() {
        $conn = $this->connect();
        
        $stats = [];
        
        // Total shipments
        $stmt = $conn->query("SELECT COUNT(*) as total FROM " . self::TABLE_SHIPMENTS);
        $stats['total_shipments'] = $stmt->fetch()['total'];
        
        // In transit
        $stmt = $conn->query("SELECT COUNT(*) as total FROM " . self::TABLE_SHIPMENTS . " WHERE status = 'in_transit'");
        $stats['in_transit'] = $stmt->fetch()['total'];
        
        // Delivered
        $stmt = $conn->query("SELECT COUNT(*) as total FROM " . self::TABLE_SHIPMENTS . " WHERE status = 'delivered'");
        $stats['delivered'] = $stmt->fetch()['total'];
        
        // Pending
        $stmt = $conn->query("SELECT COUNT(*) as total FROM " . self::TABLE_SHIPMENTS . " WHERE status = 'pending'");
        $stats['pending'] = $stmt->fetch()['total'];
        
        // Total users
        $stmt = $conn->query("SELECT COUNT(*) as total FROM " . self::TABLE_USERS . " WHERE role = 'customer'");
        $stats['total_customers'] = $stmt->fetch()['total'];
        
        // Pending clearances
        $stmt = $conn->query("SELECT COUNT(*) as total FROM " . self::TABLE_CLEARANCES . " WHERE clearance_status = 'pending'");
        $stats['pending_clearances'] = $stmt->fetch()['total'];
        
        return $stats;
    }
    
    /**
     * Get shipments count by status
     * @return array Count by status
     */
    public function getShipmentsByStatus() {
        $stmt = $this->connect()->query("
            SELECT status, COUNT(*) as count 
            FROM " . self::TABLE_SHIPMENTS . " 
            GROUP BY status
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent shipments
     * @param int $limit Number of shipments
     * @return array List of shipments
     */
    public function getRecentShipments($limit = 10) {
        $stmt = $this->connect()->prepare("
            SELECT s.*, u.name as customer_name 
            FROM " . self::TABLE_SHIPMENTS . " s
            LEFT JOIN " . self::TABLE_USERS . " u ON s.customer_id = u.id
            ORDER BY s.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // ============================================
    // UTILITY METHODS
    // ============================================
    
    /**
     * Generate unique tracking number
     * @return string Tracking number
     */
    public function generateTrackingNumber() {
        return 'CG' . date('Ymd') . strtoupper(substr(uniqid(), -6)) . 'KE';
    }
    
    /**
     * Begin transaction
     * @return bool Success status
     */
    public function beginTransaction() {
        return $this->connect()->beginTransaction();
    }
    
    /**
     * Commit transaction
     * @return bool Success status
     */
    public function commit() {
        return $this->connect()->commit();
    }
    
    /**
     * Rollback transaction
     * @return bool Success status
     */
    public function rollback() {
        return $this->connect()->rollBack();
    }
    
    /**
     * Execute raw query
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return PDOStatement|false Query result
     */
    public function query($sql, $params = []) {
        $stmt = $this->connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Log audit trail
     * @param int $userId User ID
     * @param string $action Action performed
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @param array $oldValues Old values
     * @param array $newValues New values
     * @return int|false Log ID or false on failure
     */
    public function logAudit($userId, $action, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
        $sql = "INSERT INTO " . self::TABLE_AUDIT_LOGS . " 
                (user_id, action, entity_type, entity_id, old_values, new_values, ip_address)
                VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address)";
        
        $stmt = $this->connect()->prepare($sql);
        $result = $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        return $result ? $this->connect()->lastInsertId() : false;
    }
}
