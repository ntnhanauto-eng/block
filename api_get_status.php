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
    1 => 'eb9530c1bda34fc126kdqn', // cửa phòng
    2 => 'eb27e8cde676d1752cnznu', // cửa sắt
    3 => 'eb4b84fb4b534fbe2ahss6',  // cửa sắt trước
    4 => 'eb7e2afed97c896d52kdyw',  // cửa sắt nhà trà
    5 => 'eb5bd98332c838c398ovin',  // công tắc bếp
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
// 4. DUYỆT DATABASE VÀ ĐỐI CHIẾU (TÍCH HỢP LOGIC LƯU Ý)
// ========================================================
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
$rooms = [];

while ($row = mysqli_fetch_assoc($rooms_query)) {
    $current_room_id = (int)$row['id'];
    $tuya_device_id = $devices[$current_room_id] ?? null;
    
    // Lấy trạng thái từ mảng đã truy vấn song song bằng curl_multi
    $tuya_door_state = ($tuya_device_id && isset($all_tuya_statuses[$tuya_device_id])) ? $all_tuya_statuses[$tuya_device_id] : 'Đóng';
    $is_forget_warning = false;

    // Lấy trạng thái cảm biến cũ nhất trong DB để so sánh xem có biến cố đóng/mở hay không
    $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
    $last_db_log = mysqli_fetch_assoc($last_db_log_q);
    $previous_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';

    // Nếu trạng thái vừa quét từ Tuya khác trạng thái cũ lưu trong DB -> Có biến cố xảy ra
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

        // 3. LOGIC BẤT THƯỜNG (PHÒNG TRỐNG + CỬA MỞ) -> BÔI ĐỎ
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
        // 🔥 LOGIC MỚI: CHÈN "LƯU Ý" KHI PHÒNG ĐANG VỆ SINH MÀ MỞ CỬA -> BÔI VÀNG
        // =========================================================================
        if ($tuya_door_state === 'Mở' && ($row['status'] === 've_sink' || $row['status'] === 've_sinh')) {
            $notice_details = "⚠️ HỆ THỐNG: Cửa phòng mở trong lúc nhân viên đang dọn dẹp vệ sinh.";
            
            // Ghi bản ghi LƯU Ý vào DB (Cột event_type lưu chữ 'LƯU Ý')
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'LƯU Ý', '$notice_details', 0)");
            
            // Tùy chọn: Nếu bạn muốn bắn Telegram cho trường hợp này thì mở đoạn code dưới ra
            /*
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("⚠️ <b>LƯU Ý:</b> 🏨 {$row['room_name']} đang dọn phòng và vừa mở cửa.");
            }
            */
        }
        // =========================================================================
    }

    // TÍNH TOÁN CẢNH BÁO QUÊN ĐÓNG CỬA > 5 PHÚT (Giữ nguyên)
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
