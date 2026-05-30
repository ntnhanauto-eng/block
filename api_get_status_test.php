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
// 3. TỐI ƯU: LẤY TRẠNG THÁI HÀNG LOẠT (ĐỔI SANG API LIST)
// ========================================================
$all_tuya_statuses = [];
if (!empty($token)) {
    // 1. Gom danh sách ID thiết bị cách nhau bởi dấu phẩy
    $device_ids_string = implode(',', array_values($devices));
    
    // 2. Sử dụng Endpoint lấy danh sách thiết bị kèm trạng thái (Dễ tạo chữ ký và ổn định nhất)
    $endpoint = "/v1.0/devices";
    $queryParams = "device_ids=" . $device_ids_string;
    
    $timestamp = round(microtime(true) * 1000);
    
    // Hash SHA256 của chuỗi rỗng (Thân request GET luôn trống)
    $emptyBodyHash = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
    
    // Xây dựng chuỗi chữ ký chuẩn hóa theo quy định của Tuya
    $strToSign = "GET\n" . $emptyBodyHash . "\n" . "" . "\n" . $endpoint . "?" . $queryParams;
    $source = $accessId . $token . $timestamp . $strToSign;
    $sign_batch = strtoupper(hash_hmac('sha256', $source, $secret));

    // 3. Khởi tạo cURL gọi API
    $url_api = $baseUrl . $endpoint . "?" . $queryParams;
    $ch_batch = curl_init($url_api);
    curl_setopt($ch_batch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_batch, CURLOPT_HTTPHEADER, [
        "client_id: $accessId", 
        "access_token: $token", 
        "sign: $sign_batch", 
        "t: $timestamp", 
        "sign_method: HMAC-SHA256",
        "Content-Type: application/json"
    ]);
    $batchResponse = curl_exec($ch_batch);
    curl_close($ch_batch);

    $batchData = json_decode($batchResponse, true);
    
    // 4. Bóc tách dữ liệu trả về từ Tuya
    // Đối với API này, danh sách thiết bị sẽ nằm trong $batchData['result']['list'] hoặc $batchData['result']
    $devices_list = [];
    if (isset($batchData['success']) && $batchData['success'] == true && isset($batchData['result'])) {
        $devices_list = isset($batchData['result']['list']) ? $batchData['result']['list'] : $batchData['result'];
    }

    if (is_array($devices_list)) {
        foreach ($devices_list as $dev) {
            if (!isset($dev['id'])) continue;
            
            $dev_id = $dev['id'];
            $status_val = 'Đóng'; // Mặc định ban đầu
            
            if (isset($dev['status']) && is_array($dev['status'])) {
                foreach ($dev['status'] as $s) {
                    if ($s['code'] == 'doorcontact_state' || $s['code'] == 'switch') {
                        if ($s['value'] === true || $s['value'] === 'open' || $s['value'] === 'opened' || $s['value'] == '1') {
                            $status_val = 'Mở';
                        }
                    }
                }
            }
            // Ghi nhận trạng thái thực tế vào mảng tạm để đối chiếu ở Mục 4 bên dưới
            $all_tuya_statuses[$dev_id] = $status_val;
        }
    }
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
