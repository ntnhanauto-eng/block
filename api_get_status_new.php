<?php
// api_get_status.php
include 'config.php';
checkLogin(); 

header('Content-Type: application/json');

// ========================================================
// 1. CẤU HÌNH MẢNG CẢM BIẾN CHO TỪNG PHÒNG
// ========================================================
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

// ========================================================
// 2. LẤY ACCESS TOKEN TUYA DÙNG CHUNG
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
    $tuya_door_state = 'Đóng'; 
    $is_forget_warning = false;

    if (!empty($token) && isset($devices[$current_room_id])) {
        $active_device_id = $devices[$current_room_id];

        // Gọi API Tuya lấy trạng thái Real-time
        $timestamp = round(microtime(true) * 1000);
        $endpoint = "/v1.0/devices/$active_device_id/status";
        $strToSign = "GET\n" . hash('sha256', "") . "\n" . "" . "\n" . $endpoint;
        $source = $accessId . $token . $timestamp . $strToSign;
        $sign2 = strtoupper(hash_hmac('sha256', $source, $secret));

        $ch2 = curl_init($baseUrl . $endpoint);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            "client_id: $accessId", "access_token: $token", "sign: $sign2", 
            "t: $timestamp", "sign_method: HMAC-SHA256", "Content-Type: application/json"
        ]);
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

        // Lấy thông tin bản ghi CẢM BIẾN gần nhất trong DB để so sánh đổi trạng thái cửa
        $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
        $last_db_log = mysqli_fetch_assoc($last_db_log_q);
        $previous_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';

        // Có biến cố thay đổi trạng thái cửa Đóng <=> Mở
        if ($tuya_door_state !== $previous_state) {
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'CẢM BIẾN', '$tuya_door_state', 0)");
            
            // 1. Nếu phòng đang TRỐNG mà CỬA MỞ -> Báo động ngay lập tức (Logic cũ)
            if ($tuya_door_state === 'Mở' && $row['status'] === 'trong') {
                $alert_details = "🚨 HỆ THỐNG: Phát hiện cửa mở bất thường tại phòng trống!";
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'BẤT THƯỜNG', '$alert_details', 0)");
                
                if (function_exists('sendTelegramNotification')) {
                    sendTelegramNotification("🚨 <b>CẢNH BÁO NGUY HIỂM:</b>\n🏨 <b>{$row['room_name']}</b> đang TRỐNG nhưng bất ngờ bị MỞ CỬA!\n⏰ Thời gian: " . date('Y-m-d H:i:s'));
                }
            }

            // =========================================================================
            // 🔥 LOGIC MỚI: PHÂN TẦNG THỜI GIAN KHI PHÒNG Ở TRẠNG THÁI VỆ SINH
            // =========================================================================
            if ($tuya_door_state === 'Mở' && $row['status'] === 've_sinh') {
                
                // Lấy mốc thời gian Lễ tân bấm Check-out chuyển phòng sang trạng thái Vệ sinh
                $checkout_log_q = mysqli_query($conn, "SELECT event_time FROM room_logs WHERE room_id = $current_room_id AND (details LIKE '%vệ sinh%' OR details LIKE '%ve_sinh%') AND details NOT LIKE '%hoàn tất%' ORDER BY id DESC LIMIT 1");
                $checkout_log = mysqli_fetch_assoc($checkout_log_q);
                
                // Kiểm tra xem Cleaner đã bấm "BẮT ĐẦU DỌN PHÒNG" thông qua mã QR chưa
                $cleaner_start_q = mysqli_query($conn, "SELECT id FROM room_logs WHERE room_id = $current_room_id AND details = 'BẮT ĐẦU DỌN PHÒNG' AND event_time > '" . ($checkout_log['event_time'] ?? '1970-01-01 00:00:00') . "'");
                $has_started_cleaning = mysqli_num_rows($cleaner_start_q) > 0;

                if (!$has_started_cleaning) {
                    // Nếu chưa bấm bắt đầu dọn -> Tính số phút kể từ lúc Check-out
                    $time_passed_minutes = 0;
                    if ($checkout_log) {
                        $time_passed_minutes = (time() - strtotime($checkout_log['event_time'])) / 60;
                    }

                    if ($time_passed_minutes > 20) {
                        // ❌ QUÁ 20 PHÚT KIỂM TRA MÀ CHƯA XÁC NHẬN DỌN PHÒNG: BÁO ĐỘNG GIAN LẬN
                        $alert_details = "🚨 CẢNH BÁO: Cửa mở trái phép khi chưa xác nhận quét QR dọn phòng (Quá 20p kiểm tra)!";
                        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'BẤT THƯỜNG', '$alert_details', 0)");
                        
                        if (function_exists('sendTelegramNotification')) {
                            sendTelegramNotification("🚨 <b>CẢNH BÁO AN NINH GIAN LẬN:</b>\n🏨 Phòng <b>{$row['room_name']}</b> vừa MỞ CỬA!\n⚠️ Trạng thái: Chờ dọn dẹp đã quá 20 phút.\n❌ Nhân viên chưa quét mã QR xác nhận dọn phòng! Nghi ngờ có người vào ở lén.");
                        }
                    } else {
                        //  TRONG VÒNG 20 PHÚT ĐẦU: Lễ tân vào kiểm tra phòng -> Ghi nhận lưu ý thông thường
                        $notice_details = "⚠️ HỆ THỐNG: Cửa mở trong khung giờ 20 phút Lễ tân kiểm tra phòng.";
                        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'LƯU Ý', '$notice_details', 0)");
                    }
                } else {
                    //  ĐÃ QUÉT QR BẤM BẮT ĐẦU: Nhân viên đang dọn dẹp hợp lệ -> Chỉ ghi nhận nhật ký làm việc
                    $notice_details = "🧹 HỆ THỐNG: Cửa mở trong lúc nhân viên đang dọn dẹp vệ sinh hợp lệ.";
                    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($current_room_id, NOW(), 'LỄ TÂN', '$notice_details', 0)");
                }
            }
            // =========================================================================
        }

        // TÍNH TOÁN CẢNH BÁO QUÊN ĐÓNG CỬA > 5 PHÚT (Logic cũ giữ nguyên)
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

// LẤY 5 LOG GẦN NHẤT ĐỔ RA BẢNG TIN
$logs_query = mysqli_query($conn, "SELECT l.id, l.event_time, r.room_name, l.event_type, l.amount, l.details FROM room_logs l JOIN rooms r ON l.room_id = r.id ORDER BY l.id DESC LIMIT 5");
$logs = [];
while ($row_log = mysqli_fetch_assoc($logs_query)) {
    $logs[] = ["id" => (int)$row_log['id'], "event_time" => $row_log['event_time'], "room_name" => $row_log['room_name'], "event_type" => $row_log['event_type'], "amount" => (int)$row_log['amount'], "details" => $row_log['details']];
}

ob_clean();
echo json_encode(["rooms" => $rooms, "logs" => $logs], JSON_UNESCAPED_UNICODE);
exit();
?>
