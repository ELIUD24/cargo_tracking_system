<?php
// api/messaging.php - SMS & WhatsApp Integration

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once "database.php";

// Configuration
define('AFRICAS_TALKING_USERNAME', 'sandbox'); // Change to your username
define('AFRICAS_TALKING_API_KEY', 'atsk_28b843c00b4234728ecbd38107361f3cae17920c4a8027ab701a3e478fdf5c3d0dbd2549'); // Get from Africa's Talking
define('AFRICAS_TALKING_SMS_URL', 'https://api.africastalking.com/version1/messaging');
define('AFRICAS_TALKING_WHATSAPP_URL', 'https://api.africastalking.com/version1/whatsapp');

// For Twilio (alternative)
define('TWILIO_SID', 'your_twilio_sid');
define('TWILIO_TOKEN', 'your_twilio_token');
define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886');

$endpoint = $_GET['endpoint'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? [];

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit;
}

// ==================== SEND SMS ====================
if ($endpoint === 'send-sms') {
    $phone = $input['phone'] ?? '';
    $message = $input['message'] ?? '';
    $customer_id = $input['customer_id'] ?? null;
    
    if (!$phone || !$message) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Phone and message required"]);
        exit;
    }
    
    // Format phone number (Kenya format)
    $phone = formatPhoneNumber($phone);
    
    try {
        // Log message as pending
        $stmt = $conn->prepare("INSERT INTO message_logs (recipient, message_type, message, status) VALUES (?, 'sms', ?, 'pending')");
        $stmt->execute([$phone, $message]);
        $message_id = $conn->lastInsertId();
        
        // Send via Africa's Talking
        $fields = [
            'username' => AFRICAS_TALKING_USERNAME,
            'to' => $phone,
            'message' => $message,
            'apiKey' => AFRICAS_TALKING_API_KEY
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, AFRICAS_TALKING_SMS_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 201 && isset($result['SMSMessageData']['Recipients'][0]['status'])) {
            $status = $result['SMSMessageData']['Recipients'][0]['status'];
            $provider_id = $result['SMSMessageData']['Recipients'][0]['messageId'] ?? '';
            
            // Update log
            $update_stmt = $conn->prepare("UPDATE message_logs SET status = ?, provider_message_id = ?, sent_at = NOW() WHERE id = ?");
            $update_stmt->execute([$status === 'Success' ? 'sent' : 'failed', $provider_id, $message_id]);
            
            echo json_encode([
                "success" => true,
                "message" => "SMS sent successfully",
                "data" => [
                    "message_id" => $message_id,
                    "provider_id" => $provider_id,
                    "status" => $status
                ]
            ]);
        } else {
            // Update log as failed
            $update_stmt = $conn->prepare("UPDATE message_logs SET status = 'failed' WHERE id = ?");
            $update_stmt->execute([$message_id]);
            
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "SMS sending failed", "response" => $response]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit;
}

// ==================== SEND WHATSAPP ====================
if ($endpoint === 'send-whatsapp') {
    $phone = $input['phone'] ?? '';
    $message = $input['message'] ?? '';
    $customer_id = $input['customer_id'] ?? null;
    
    if (!$phone || !$message) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Phone and message required"]);
        exit;
    }
    
    // Format phone number
    $phone = formatPhoneNumber($phone);
    
    try {
        // Log message as pending
        $stmt = $conn->prepare("INSERT INTO message_logs (recipient, message_type, message, status) VALUES (?, 'whatsapp', ?, 'pending')");
        $stmt->execute([$phone, $message]);
        $message_id = $conn->lastInsertId();
        
        // Send via Africa's Talking WhatsApp
        $fields = [
            'username' => AFRICAS_TALKING_USERNAME,
            'to' => $phone,
            'message' => $message,
            'apiKey' => AFRICAS_TALKING_API_KEY
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, AFRICAS_TALKING_WHATSAPP_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 201) {
            // Update log
            $update_stmt = $conn->prepare("UPDATE message_logs SET status = 'sent', provider_message_id = ?, sent_at = NOW() WHERE id = ?");
            $update_stmt->execute([$result['messageId'] ?? '', $message_id]);
            
            echo json_encode([
                "success" => true,
                "message" => "WhatsApp sent successfully",
                "data" => [
                    "message_id" => $message_id,
                    "provider_id" => $result['messageId'] ?? ''
                ]
            ]);
        } else {
            $update_stmt = $conn->prepare("UPDATE message_logs SET status = 'failed' WHERE id = ?");
            $update_stmt->execute([$message_id]);
            
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "WhatsApp sending failed", "response" => $response]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit;
}

// ==================== SEND NOTIFICATION (Auto-trigger) ====================
if ($endpoint === 'send-notification') {
    $type = $input['type'] ?? ''; // clearance_approved, shipment_in_transit, shipment_delivered, etc.
    $customer_id = $input['customer_id'] ?? null;
    $tracking_number = $input['tracking_number'] ?? '';
    $phone = $input['phone'] ?? '';
    $customer_name = $input['customer_name'] ?? 'Customer';
    
    if (!$type || !$phone) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Type and phone required"]);
        exit;
    }
    
    // Get message templates
    $templates = getMessageTemplates();
    $template = $templates[$type] ?? null;
    
    if (!$template) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid notification type"]);
        exit;
    }
    
    // Replace placeholders
    $message = str_replace(
        ['{customer_name}', '{tracking_number}', '{date}'],
        [$customer_name, $tracking_number, date('Y-m-d H:i')],
        $template
    );
    
    // Send both SMS and WhatsApp
    $results = [];
    
    // Send SMS
    $sms_result = sendSMS($phone, $message, $customer_id, $conn);
    $results['sms'] = $sms_result;
    
    // Send WhatsApp
    $whatsapp_result = sendWhatsApp($phone, $message, $customer_id, $conn);
    $results['whatsapp'] = $whatsapp_result;
    
    echo json_encode([
        "success" => true,
        "message" => "Notifications sent",
        "data" => $results
    ]);
    exit;
}

// ==================== GET MESSAGE LOGS ====================
if ($endpoint === 'message-logs') {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401);
        echo json_encode(["success" => false, "message" => "Unauthorized"]);
        exit;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM message_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "success" => true,
            "data" => $logs
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    exit;
}

// ==================== HELPER FUNCTIONS ====================

function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to Kenya format
    if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (strlen($phone) === 9) {
        $phone = '254' . $phone;
    }
    
    return '+' . $phone;
}

function getMessageTemplates() {
    return [
        'clearance_approved' => "Hello {customer_name}, your clearance for tracking #{tracking_number} has been APPROVED. Your shipment will be processed soon. Thank you for choosing CargoTrack!",
        'clearance_rejected' => "Hello {customer_name}, your clearance for tracking #{tracking_number} was REJECTED. Please contact us for more information. CargoTrack Support.",
        'shipment_in_transit' => "Hello {customer_name}, good news! Your shipment #{tracking_number} is now IN TRANSIT. You can track it on our website. CargoTrack",
        'shipment_delivered' => "Hello {customer_name}, your shipment #{tracking_number} has been DELIVERED successfully. Thank you for choosing CargoTrack! Rate your experience.",
        'shipment_pending' => "Hello {customer_name}, your shipment #{tracking_number} is pending clearance. We'll notify you once approved. CargoTrack",
        'payment_received' => "Hello {customer_name}, we've received your payment for shipment #{tracking_number}. Your shipment will be processed. CargoTrack"
    ];
}

function sendSMS($phone, $message, $customer_id, $conn) {
    try {
        // Log message
        $stmt = $conn->prepare("INSERT INTO message_logs (recipient, message_type, message, status) VALUES (?, 'sms', ?, 'pending')");
        $stmt->execute([$phone, $message]);
        $message_id = $conn->lastInsertId();
        
        // In production, send via API here
        // For demo, mark as sent
        $update_stmt = $conn->prepare("UPDATE message_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $update_stmt->execute([$message_id]);
        
        return ["success" => true, "message_id" => $message_id];
    } catch (Exception $e) {
        return ["success" => false, "error" => $e->getMessage()];
    }
}

function sendWhatsApp($phone, $message, $customer_id, $conn) {
    try {
        // Log message
        $stmt = $conn->prepare("INSERT INTO message_logs (recipient, message_type, message, status) VALUES (?, 'whatsapp', ?, 'pending')");
        $stmt->execute([$phone, $message]);
        $message_id = $conn->lastInsertId();
        
        // In production, send via API here
        // For demo, mark as sent
        $update_stmt = $conn->prepare("UPDATE message_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $update_stmt->execute([$message_id]);
        
        return ["success" => true, "message_id" => $message_id];
    } catch (Exception $e) {
        return ["success" => false, "error" => $e->getMessage()];
    }
}
?>