<?php
// cron_update_tuya.php
// FILE NÀY CHẠY TỰ ĐỘNG TRÊN SERVER - KHÔNG CẦN CHECK LOGIN

include 'config.php'; 
// Đã nhúng config.php nên file này tự động thừa hưởng TUYA_CLIENT_ID, TUYA_SECRET, TUYA_API_URL

header('Content-Type: application/json');

// ========================================================
// 1. CẤU HÌNH ĐỐI CHIẾU HẰNG SỐ (Tối ưu từ config.php)
// ========================================================
$accessId  = TUYA_CLIENT_ID;   // Lấy từ define('TUYA_CLIENT_ID', '...') của config.php
$secret    = TUYA_SECRET;      // Lấy từ define('TUYA_SECRET', '...') của config.php
$baseUrl   = TUYA_API_URL;     // Lấy từ define('TUYA_API_URL', '...') của config.php

// Bản đồ ánh xạ ID phòng trong DB với ID thiết bị thực tế của Tuya
$devices = [
     1 => 'eb9530c1bda34fc126kdqn', // cửa phòng
    2 => 'eb27e8cde676d1752cnznu', // cửa sắt
    3 => 'eb4b84fb4b534fbe2ahss6',  // cửa sắt trước
    // ... Thêm các phòng khác ở đây nếu có
];

// ========================================================
// 2. LẤY ACCESS TOKEN TỪ TUYA CLOUD
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
// 3. GỌI API SONG SONG HÀNG LOẠT (CURL MULTI)
// ========================================================
$all_tuya_statuses = [];

if (!empty($token) && !empty($devices)) {
    $mh = curl_multi_init();
    $curl_handles = [];

    // Tạo đồng thời các luồng kết nối cho từng thiết bị
    foreach ($devices as $room_id => $active_device_id) {
        $timestamp = round(microtime(true) * 1000);
        $endpoint = "/v1.0/devices/$active_device_id/status";
        $strToSign = "GET\n" . hash('sha256', "") . "\n" . "" . "\n" . $endpoint;
        $source = $accessId . $token . $timestamp . $strToSign;
        $sign2 = strtoupper(hash_hmac('sha256', $source, $secret));

        $ch2_headers = [
            "client_id: $accessId", 
            "access_token: $token", 
            "sign: $sign2", 
            "t: $timestamp", 
            "sign_method: HMAC-SHA256", 
            "Content-Type: application/json"
        ];

        $ch = curl_init($baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $ch2_headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Giới hạn 5 giây tránh treo luồng

        curl_multi_add_handle($mh, $ch);
        $curl_handles[$active_device_id] = $ch;
    }

    // Thực thi đồng thời tất cả các cuộc gọi API (Chạy song song)
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    // Thu thập dữ liệu phản hồi từ các phòng và đóng luồng
    foreach ($curl_handles as $active_device_id => $ch) {
        $statusResponse = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $status_val = 'Đóng'; // Mặc định ban đầu
        $data = json_decode($statusResponse, true);

        if (isset($data['success']) && $data['success'] == true && isset($data['result'])) {
            foreach ($data['result'] as $status) {
                if ($status['code'] == 'doorcontact_state' || $status['code'] == 'switch') {
                    if ($status['value'] === true || $status['value'] === 'open' || $status['value'] === 'opened') {
                        $status_val = 'Mở';
                    }
                }
            }
        }
        // Ghi nhận trạng thái thực tế trả về từ luồng đơn lẻ này
        $all_tuya_statuses[$active_device_id] = $status_val;
    }
    
    curl_multi_close($mh);
}

// ========================================================
// 4. DUYỆT DATABASE VÀ ĐỐI CHIẾU (XỬ LÝ LOGIC NGẦM)
// ========================================================
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");

while ($row = mysqli_fetch_assoc($rooms_query)) {
    $current_room_id = (int)$row['id'];
    $tuya_device_id = $devices[$current_room_id] ?? null;
    
    // Lấy trạng thái từ mảng đã truy vấn hàng loạt ở trên
    $tuya_door_state = ($tuya_device_id && isset($all_tuya_statuses[$tuya_device_id])) ? $all_tuya_statuses[$tuya_device_id] : 'Đóng';

    // Lấy log cảm biến gần nhất trong DB ra đối chiếu
    $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
    $last_db_log = mysqli_fetch_assoc($last_db_log_q);
    $previous_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';

    // Nếu trạng thái cửa từ Tuya báo về KHÁC với lịch sử trong DB (Cửa vừa đổi trạng thái)
    if ($tuya_door_state !== $previous_state) {
        // 1. Ghi log mới vào database
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'CẢM BIẾN', '$tuya_door_state', 0)");
        
        // 2. Bắn thông báo Telegram thông thường cho các phòng đang check-in/vệ sinh
        if (in_array($row['status'], ['trong', 've_sink', 've_sinh'])) {
            if (function_exists('sendTelegramNotification')) {
                $icon = ($tuya_door_state === 'Mở') ? "🔓" : "🔒";
                sendTelegramNotification("$icon <b>CẢM BIẾN CỬA:</b>\n🏨 <b>{$row['room_name']}</b> vừa <b>" . strtoupper($tuya_door_state) . "</b>");
            }
        }
        
        // 3. Xử lý logic BẤT THƯỜNG (Cửa mở tại phòng trống - Báo động chống trộm ban đêm)
        if ($tuya_door_state === 'Mở' && $row['status'] === 'trong') {
            $alert_details = "🚨 HỆ THỐNG: Phát hiện cửa mở bất thường tại phòng trống!";
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'BẤT THƯỜNG', '$alert_details', 0)");
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("🚨 <b>CỬA MỞ BẤT THƯỜNG:</b> {$row['room_name']} đang trống nhưng bị mở cửa!");
            }
        }
    }
}

// Trả về kết quả xác nhận cho hệ thống Cronjob đọc (nếu cần kiểm tra log chạy ngầm)
echo json_encode([
    "success" => true, 
    "msg" => "Đồng bộ trạng thái Tuya hoàn tất!", 
    "time" => date('Y-m-d H:i:s')
], JSON_UNESCAPED_UNICODE);
?>
