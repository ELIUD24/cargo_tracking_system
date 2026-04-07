<?php
header("Content-Type: application/json");

require_once "database.php";

$db = new Database();
$conn = $db->getConnection();

try {

    // Total shipments
    $total = $conn->query("SELECT COUNT(*) as total FROM shipments")->fetch();

    // In transit
    $inTransit = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status='in_transit'")->fetch();

    // Delivered
    $delivered = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status='delivered'")->fetch();

    // Pending
    $pending = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status='pending'")->fetch();

    echo json_encode([
        "success" => true,
        "data" => [
            "total_shipments" => $total['total'],
            "in_transit" => $inTransit['total'],
            "delivered" => $delivered['total'],
            "pending" => $pending['total']
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}