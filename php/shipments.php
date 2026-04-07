<?php
// models/Shipment.php

class Shipment {
    private $conn;
    private $table = "shipments";

    public $id;
    public $tracking_number;
    public $customer_id;
    public $sender_name;
    public $sender_phone;
    public $sender_address;
    public $receiver_name;
    public $receiver_phone;
    public $receiver_address;
    public $status;
    public $weight;
    public $service_type;
    public $estimated_delivery;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Get shipment by tracking number
    public function getByTrackingNumber() {
        $query = "SELECT s.*, u.name as customer_name, u.email as customer_email 
                  FROM " . $this->table . " s 
                  LEFT JOIN users u ON s.customer_id = u.id 
                  WHERE s.tracking_number = :tracking_number LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":tracking_number", $this->tracking_number);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get tracking history
    public function getTrackingHistory() {
        $query = "SELECT * FROM shipment_tracking 
                  WHERE shipment_id = :shipment_id 
                  ORDER BY created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":shipment_id", $this->id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>