<?php
// api_get_status.php
include 'config.php';
checkLogin(); 

header('Content-Type: application/json');

// 1. CẤU HÌNH
$accessId  = 'qap98nweqkmufpdp5d3r';
$secret    = 'cb7684adc56045bdb5f77c1d7a541d48';
$baseUrl   = 'https://openapi.tuyaus.com';

$devices = [
    1 => 'eb9530c1bda34fc126kdqn',
    2 => 'eb27e8cde676d1752cnznu',
    3 => 'eb4b84fb4b534fbe2ahss6',
    // ... Thêm các phòng khác ở đây
];

// 2. LẤY ACCESS TOKEN (Giữ nguyên logic)
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
// 3. TỐI ƯU TUYỆT ĐỐI: GỌI API SONG SONG (CURL MULTI)
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout 5 giây tránh treo luồng

        // Thêm luồng này vào trình quản lý đa luồng
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
    
    // Đóng trình quản lý đa luồng
    curl_multi_close($mh);
}

// ========================================================
// 4. DUYỆT DATABASE VÀ ĐỐI CHIẾU (KHÔNG GỌI API NỮA)
// ========================================================
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
$rooms = [];

while ($row = mysqli_fetch_assoc($rooms_query)) {
    $current_room_id = (int)$row['id'];
    $tuya_device_id = $devices[$current_room_id] ?? null;
    
    // Lấy trạng thái từ mảng đã truy vấn hàng loạt ở trên
    $tuya_door_state = ($tuya_device_id && isset($all_tuya_statuses[$tuya_device_id])) ? $all_tuya_statuses[$tuya_device_id] : 'Đóng';
    $is_forget_warning = false;

    // Logic xử lý Log, Telegram, Bất thường (Giữ nguyên của bạn)
    // ... (Phần logic INSERT INTO room_logs và sendTelegramNotification của bạn giữ nguyên tại đây)
    // Lưu ý: Vì đã có $tuya_door_state, bạn chỉ cần thực hiện so sánh với DB như cũ.
    
    // [Đoạn code xử lý so sánh logic cũ của bạn...]
    $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
    $last_db_log = mysqli_fetch_assoc($last_db_log_q);
    $previous_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';

    if ($tuya_door_state !== $previous_state) {
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'CẢM BIẾN', '$tuya_door_state', 0)");
        if (in_array($row['status'], ['trong', 've_sink', 've_sinh'])) {
            if (function_exists('sendTelegramNotification')) {
                $icon = ($tuya_door_state === 'Mở') ? "🔓" : "🔒";
                sendTelegramNotification("$icon <b>CẢM BIẾN CỬA:</b>\n🏨 <b>{$row['room_name']}</b> vừa <b>" . strtoupper($tuya_door_state) . "</b>");
            }
        }
        // Logic Bất thường...
        if ($tuya_door_state === 'Mở' && $row['status'] === 'trong') {
            $alert_details = "🚨 HỆ THỐNG: Phát hiện cửa mở bất thường tại phòng trống!";
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'BẤT THƯỜNG', '$alert_details', 0)");
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("🚨 <b>CỬA MỞ BẤT THƯỜNG:</b> {$row['room_name']}");
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

// Lấy 5 log cuối (Giữ nguyên)
$logs_query = mysqli_query($conn, "SELECT l.id, l.event_time, r.room_name, l.event_type, l.amount, l.details FROM room_logs l JOIN rooms r ON l.room_id = r.id ORDER BY l.id DESC LIMIT 5");
$logs = [];
while ($row_log = mysqli_fetch_assoc($logs_query)) {
    $logs[] = $row_log;
}

echo json_encode(["rooms" => $rooms, "logs" => $logs], JSON_UNESCAPED_UNICODE);
?>
