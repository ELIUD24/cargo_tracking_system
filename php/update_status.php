<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once '../database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // ✅ Read from $_POST (form data)
    $request_id = $_POST['request_id'] ?? 0;
    $new_status = $_POST['status'] ?? 'pending';
    $approver_id = $_POST['approved_by'] ?? 0;
    
    if (!in_array($new_status, ['approved', 'rejected'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
        exit;
    }
    
    $sql = "UPDATE clearance_requests 
            SET status = :status, approved_by = :approved_by, approved_at = NOW() 
            WHERE id = :id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':status' => $new_status,
        ':approved_by' => $approver_id,
        ':id' => $request_id
    ]);
    
    echo json_encode(['status' => 'success', 'message' => 'Clearance request updated']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>