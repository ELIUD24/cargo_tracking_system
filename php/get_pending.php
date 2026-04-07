<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once '../database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "SELECT * FROM clearance_requests WHERE status = 'pending' ORDER BY created_at DESC";
    $stmt = $conn->query($sql);
    $requests = $stmt->fetchAll();
    
    echo json_encode($requests);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>