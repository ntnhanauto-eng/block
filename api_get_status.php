<?php
// api_get_status.php
include 'config.php';
checkLogin(); 

header('Content-Type: application/json');

// ========================================================
// 1. CẤU HÌNH MẢNG CẢM BIẾN CHO TỪNG PHÒNG (SỬA Ở ĐÂY)
// ========================================================
$accessId  = 'qap98nweqkmufpdp5d3r';
$secret    = 'cb7684adc56045bdb5f77c1d7a541d48';
$baseUrl   = 'https://openapi.tuyaus.com';

// Sau này có thêm phòng, bạn chỉ cần viết thêm một dòng vào mảng này:
$devices = [
    1 => 'eb9530c1bda34fc126kdqn',       // ID phòng 1 (Phòng 101) => Mã thiết bị Tuya
    2 => 'eb27e8cde676d1752cnznu',   // ID phòng 2 (Phòng 102) => Mã thiết bị Tuya
    3 => 'eb4b84fb4b534fbe2ahss6',   // ID phòng 3 (Phòng 103) => Mã thiết bị Tuya
];

// ========================================================
// 2. BƯỚC 1: LẤY ACCESS TOKEN DÙNG CHUNG
// ========================================================
$token = '';
$timestamp = round(microtime(true) * 1000);
$easySignStr = "GET\n" . hash('sha256', "") . "\n" . "" . "\n" . "/v1.0/token?grant_type=1";
$sign = strtoupper(hash_hmac('sha256', $accessId . $timestamp . $easySignStr, $secret));

$ch = curl_init("$baseUrl/v1.0/token?grant_type=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["client_id: $accessId", "sign: $sign", "t: $timestamp", "sign_method: HMAC-SHA256"]);
$res = json_decode(curl_exec($ch), true);

if (isset($res['result']['access_token'])) {
    $token = $res['result']['access_token'];
}

// ========================================================
// 3. TRUY VẤN VÀ DUYỆT QUA TỪNG PHÒNG ĐỂ ĐỒNG BỘ TRẠNG THÁI
// ========================================================
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
$rooms = [];

while ($row = mysqli_fetch_assoc($rooms_query)) {
    $current_room_id = (int)$row['id'];
    $tuya_door_state = 'Đóng'; // Mặc định ban đầu của phòng là Đóng
    $is_forget_warning = false;

    // KIỂM TRA: Nếu phòng này đã được cấu hình mã cảm biến Tuya trong mảng $devices
    if (!empty($token) && isset($devices[$current_room_id])) {
        $active_device_id = $devices[$current_room_id]; // Lấy đúng mã thiết bị của phòng này

        // Gọi API Tuya lấy trạng thái Real-time cho thiết bị cụ thể này
        $timestamp = round(microtime(true) * 1000);
        $endpoint = "/v1.0/devices/$active_device_id/status";
        $strToSign = "GET\n" . hash('sha256', "") . "\n" . "" . "\n" . $endpoint;
        $source = $accessId . $token . $timestamp . $strToSign;
        $sign2 = strtoupper(hash_hmac('sha256', $source, $secret));

        $ch2 = curl_init($baseUrl . $endpoint);
        $ch2_headers = [
            "client_id: $accessId", 
            "access_token: $token", 
            "sign: $sign2", 
            "t: $timestamp", 
            "sign_method: HMAC-SHA256", 
            "Content-Type: application/json"
        ];
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, $ch2_headers);
        $statusResponse = curl_exec($ch2);
        curl_close($ch2);

        $data = json_decode($statusResponse, true);
        if (isset($data['success']) && $data['success'] == true) {
            foreach ($data['result'] as $status) {
                if ($status['code'] == 'doorcontact_state' || $status['code'] == 'switch') {
                    if ($status['value'] === true || $status['value'] === 'open') {
                        $tuya_door_state = 'Mở';
                    }
                }
            }
        }

        // ĐỒNG BỘ VÀO DATABASE ĐỂ NUÔI LOG & TELEGRAM CHO TỪNG PHÒNG
        $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
        $last_db_log = mysqli_fetch_assoc($last_db_log_q);
        $previous_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';

        // Nếu trạng thái vừa quét từ Tuya khác trạng thái cũ lưu trong DB -> Có biến cố Đóng/Mở cửa vừa xảy ra
        if ($tuya_door_state !== $previous_state) {
            
            // 1. Ghi log trạng thái CẢM BIẾN phần cứng (Logic cũ)
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'CẢM BIẾN', '$tuya_door_state', 0)");
            
            // 2. Bắn Telegram thông báo đóng mở thông thường nếu phòng trống hoặc đang dọn (Logic cũ)
            if ($row['status'] === 'trong' || $row['status'] === 've_sink' || $row['status'] === 've_sinh') {
                if (function_exists('sendTelegramNotification')) {
                    $icon = ($tuya_door_state === 'Mở') ? "🔓" : "🔒";
                    $status_vn = ($row['status'] === 'trong') ? "Phòng Trống" : "Đang Vệ Sinh";
                    sendTelegramNotification("$icon <b>CẢM BIẾN CỬA:</b>\n🏨 <b>{$row['room_name']}</b> vừa <b>" . strtoupper($tuya_door_state) . "</b>\n📋 Trạng thái hiện tại: $status_vn");
                }
            }

            // =========================================================================
            // 🔥 LOGIC CHÈN THÊM CHỮ "BẤT THƯỜNG" ĐỂ TRANG ADMIN_LOGS.PHP BÔI ĐỎ
            // =========================================================================
            if ($tuya_door_state === 'Mở' && $row['status'] === 'trong') {
                $alert_details = "🚨 HỆ THỐNG: Phát hiện cửa mở bất thường tại phòng trống!";
                
                // Ghi bản ghi BẤT THƯỜNG vào DB
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'BẤT THƯỜNG', '$alert_details', 0)");
                
                // Bắn thêm 1 tin Telegram cảnh báo mức độ ĐỘC ĐỎ khẩn cấp
                if (function_exists('sendTelegramNotification')) {
                    $telegram_alert_msg = "🚨 <b>HỆ THỐNG AN NINH KHÁCH SẠN</b>\n";
                    $telegram_alert_msg .= "🏨 <b>Vị trí:</b> {$row['room_name']}\n";
                    $telegram_alert_msg .= "⚠️ <b>LOẠI SỰ CỐ:</b> CỬA MỞ BẤT THƯỜNG!\n";
                    $telegram_alert_msg .= "📌 <b>Trạng thái:</b> PHÒNG TRỐNG\n";
                    $telegram_alert_msg .= "⏰ <b>Thời gian:</b> " . date('Y-m-d H:i:s') . "\n";
                    $telegram_alert_msg .= "❗ <i>Vui lòng rà soát camera hành lang hoặc cử người lên rà soát ngay!</i>";
                    
                    sendTelegramNotification($telegram_alert_msg);
                }
            }
            // =========================================================================
        }

        // TÍNH TOÁN CẢNH BÁO QUÊN ĐÓNG CỬA > 5 PHÚT (300 GIÂY) (Logic cũ)
        if ($tuya_door_state === 'Mở' && $row['status'] === 'trong') {
            $time_q = mysqli_query($conn, "SELECT event_time FROM room_logs WHERE room_id = $current_room_id AND details = 'Mở' ORDER BY id DESC LIMIT 1");
            $time_data = mysqli_fetch_assoc($time_q);
            if ($time_data) {
                $time_passed = time() - strtotime($time_data['event_time']);
                if ($time_passed > 300) {
                    $is_forget_warning = true;
                    if ($time_passed % 60 < 4 && function_exists('sendTelegramNotification')) {
                        sendTelegramNotification("⚠️ <b>CẢNH BÁO AN NINH:</b> 🏨 Phòng <b>{$row['room_name']}</b> đang TRỐNG nhưng cửa đã mở hơn 5 phút!");
                    }
                }
            }
        }
    }

    $rooms[] = [
        "id" => $current_room_id,
        "room_name" => $row['room_name'],
        "status" => $row['status'],
        "door" => $tuya_door_state,
        "is_forget_warning" => $is_forget_warning
    ];
}

// Lấy 5 lịch sử gần nhất cho Dashboard bảng tin (Logic cũ)
$logs_query = mysqli_query($conn, "SELECT l.id, l.event_time, r.room_name, l.event_type, l.amount, l.details FROM room_logs l JOIN rooms r ON l.room_id = r.id ORDER BY l.id DESC LIMIT 5");
$logs = [];
while ($row_log = mysqli_fetch_assoc($logs_query)) {
    $logs[] = [
        "id" => (int)$row_log['id'], 
        "event_time" => $row_log['event_time'], 
        "room_name" => $row_log['room_name'], 
        "event_type" => $row_log['event_type'], 
        "amount" => (int)$row_log['amount'], 
        "details" => $row_log['details']
    ];
}

ob_clean(); // Xóa bộ đệm rác trước khi xuất json
echo json_encode(["rooms" => $rooms, "logs" => $logs], JSON_UNESCAPED_UNICODE);
exit();
?>
