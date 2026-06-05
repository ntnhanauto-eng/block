<?php
// cron_update_tuya.php
// FILE NÀY CHẠY TỰ ĐỘNG TRÊN SERVER - KHÔNG CẦN CHECK LOGIN

include 'config.php'; 
// Bỏ hàm checkLogin(); ở đây để server có thể tự chạy ngầm

header('Content-Type: application/json');

// 1. CẤU HÌNH TUYA
$accessId  = 'ffddfdfsaddafdfad';
$secret    = 'vsdfgsfdsgfgfsfgs';
$baseUrl   = 'https://openapi.tuyaus.com';

$devices = [
    1 => 'esagfgfgafgn',
    2 => 'ecvCvdsadsnu',
    3 => 'dsfggfgfffgaf',
];

// 2. LẤY ACCESS TOKEN
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

// 3. GỌI API SONG SONG (CURL MULTI)
$all_tuya_statuses = [];

if (!empty($token) && !empty($devices)) {
    $mh = curl_multi_init();
    $curl_handles = [];

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_multi_add_handle($mh, $ch);
        $curl_handles[$active_device_id] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($curl_handles as $active_device_id => $ch) {
        $statusResponse = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $status_val = 'Đóng'; 
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
        $all_tuya_statuses[$active_device_id] = $status_val;
    }
    curl_multi_close($mh);
}

// 4. DUYỆT DATABASE VÀ ĐỐI CHIẾU
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");

while ($row = mysqli_fetch_assoc($rooms_query)) {
    $current_room_id = (int)$row['id'];
    $tuya_device_id = $devices[$current_room_id] ?? null;
    
    $tuya_door_state = ($tuya_device_id && isset($all_tuya_statuses[$tuya_device_id])) ? $all_tuya_statuses[$tuya_device_id] : 'Đóng';

    $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
    $last_db_log = mysqli_fetch_assoc($last_db_log_q);
    $previous_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';

    // Nếu có sự thay đổi trạng thái cửa (Ví dụ: Từ Đóng sang Mở)
    if ($tuya_door_state !== $previous_state) {
        // Ghi log vào Database
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'CẢM BIẾN', '$tuya_door_state', 0)");
        
        // Bắn Telegram thông báo thông thường
        if (in_array($row['status'], ['trong', 've_sink', 've_sinh'])) {
            if (function_exists('sendTelegramNotification')) {
                $icon = ($tuya_door_state === 'Mở') ? "🔓" : "🔒";
                sendTelegramNotification("$icon <b>CẢM BIẾN CỬA:</b>\n🏨 <b>{$row['room_name']}</b> vừa <b>" . strtoupper($tuya_door_state) . "</b>");
            }
        }
        
        // Bắn Telegram thông báo BẤT THƯỜNG (Có trộm đột nhập vào phòng trống)
        if ($tuya_door_state === 'Mở' && $row['status'] === 'trong') {
            $alert_details = "🚨 HỆ THỐNG: Phát hiện cửa mở bất thường tại phòng trống!";
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'BẤT THƯỜNG', '$alert_details', 0)");
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("🚨 <b>CỬA MỞ BẤT THƯỜNG:</b> {$row['room_name']}");
            }
        }
    }
}

echo json_encode(["success" => true, "msg" => "Đồng bộ Tuya thành công lúc " . date('H:i:s')]);
?>
