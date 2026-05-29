<?php
// cron_tuya.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Nhúng file cấu hình (Đã có kết nối DB và Token/ChatID Telegram)
include 'config.php';

// ==========================================
// 1. CẤU HÌNH THÔNG TIN TUYA API
// ==========================================
$client_id   = "qap98nweqkmufpdp5d3r";     // Điền Access ID của bạn vào đây
$secret      = "cb7684adc56045bdb5f77c1d7a541d48";            // Điền Access Secret của bạn vào đây
$easyTuyaUrl = "https://openapi.tuyaus.com";

// Cấu hình danh sách thiết bị cảm biến gắn với ID phòng trong Database
$devices = [
    1 => "eb9530c1bda34fc126kdqn",
    2 => "eb27e8cde676d1752cnznu",
    3 => "eb4b84fb4b534fbe2ahss6"
];

$reversed_rooms = []; 

// ==========================================
// 2. HÀM TỰ ĐỘNG GỬI TIN NHẮN TELEGRAM
// ==========================================
function thong_bao_telegram($message) {
    global $telegram_token, $telegram_chat_id; 
    
    $tok = defined('TELEGRAM_TOKEN') ? TELEGRAM_TOKEN : ($telegram_token ?? '');
    $chat = defined('TELEGRAM_CHAT_ID') ? TELEGRAM_CHAT_ID : ($telegram_chat_id ?? '');

    if (empty($tok) || empty($chat)) {
        return false; 
    }
    
    $url = "https://api.telegram.org/bot" . $tok . "/sendMessage";
    $data = [
        'chat_id' => $chat,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
}

// ==========================================
// 3. CÁC HÀM XỬ LÝ KẾT NỐI API TUYA CLOUD
// ==========================================
function getTuyaAccessToken($url, $client_id, $secret) {
    // A. LẤY THỜI GIAN CHUẨN TỪ API ĐỘC LẬP CỦA TUYA (KHÔNG CẦN CHỮ KÝ)
    $ch_time = curl_init("$url/v1.0/utils/time");
    curl_setopt($ch_time, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_time, CURLOPT_TIMEOUT, 5);
    $res_time = curl_exec($ch_time);
    curl_close($ch_time);
    
    $json_time = json_decode($res_time, true);
    
    // Nếu API Tuya trả về thời gian chuẩn thành công thì dùng, nếu kẹt mới dùng giờ của Host
    $t = (isset($json_time['success']) && $json_time['success']) ? $json_time['result']['t'] : (time() * 1000);
    
    // B. TẠO CHỮ KÝ (SIGN) DỰA TRÊN GIỜ CHUẨN CỦA TUYA
    $sign = strtoupper(hash_hmac('sha256', $client_id . $t, $secret));
    
    // C. TIẾN HÀNH XIN ACCESS TOKEN CHÍNH THỨC
    $ch = curl_init("$url/v1.0/token?grant_type=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 7);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "client_id: $client_id",
        "sign: $sign",
        "t: $t",
        "sign_method: HMAC-SHA256"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($res, true);
    
    if (!isset($json['result']['access_token'])) {
        global $tuya_raw_error;
        $tuya_raw_error = $res . " (Giờ sử dụng: " . $t . ")";
        return null;
    }
    
    return $json['result']['access_token'];
}

function getTuyaDeviceStatus($url, $client_id, $access_token, $secret, $device_id) {
    // Đọc lại thời gian chuẩn cho mỗi lượt gửi trạng thái thiết bị
    $ch_time = curl_init("$url/v1.0/utils/time");
    curl_setopt($ch_time, CURLOPT_RETURNTRANSFER, true);
    $res_time = curl_exec($ch_time);
    curl_close($ch_time);
    $json_time = json_decode($res_time, true);
    $t = (isset($json_time['success']) && $json_time['success']) ? $json_time['result']['t'] : (time() * 1000);

    $string_to_sign = $client_id . $access_token . $t;
    $sign = strtoupper(hash_hmac('sha256', $string_to_sign, $secret));
    
    $ch = curl_init("$url/v1.0/devices/$device_id/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "client_id: $client_id",
        "access_token: $access_token",
        "sign: $sign",
        "t: $t",
        "sign_method: HMAC-SHA256"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($res, true);
}

// ==========================================
// 4. CHU TRÌNH CHẠY NGẦM QUÉT 4 LẦN TRONG 1 PHÚT
// ==========================================
$tuya_raw_error = "";
$access_token = getTuyaAccessToken($easyTuyaUrl, $client_id, $secret);

if (!$access_token) {
    die("Lỗi: Không thể kết nối lấy Access Token từ Tuya Cloud.<br><b>Phản hồi gốc từ Tuya:</b> <pre>" . htmlspecialchars($tuya_raw_error) . "</pre>");
}

for ($cycle = 0; $cycle < 4; $cycle++) {
    $current_time_log = date('H:i:s');
    echo "--- Lần quét $cycle ($current_time_log) ---<br>";

    foreach ($devices as $room_id => $device_id) {
        $data = getTuyaDeviceStatus($easyTuyaUrl, $client_id, $access_token, $secret, $device_id);
        
        if (isset($data['success']) && $data['success']) {
            $is_open = false;
            
            foreach ($data['result'] as $status_item) {
                if (in_array($status_item['code'], ['doorcontact_state', 'ismoving', 'switch'])) {
                    $is_open = ($status_item['value'] === true || $status_item['value'] === 'on' || $status_item['value'] === 'open');
                    break;
                }
            }
            
            if (in_array($room_id, $reversed_rooms)) {
                $is_open = !$is_open;
            }
            
            $current_state = $is_open ? 'Mở' : 'Đóng';
            
            $room_q = mysqli_query($conn, "SELECT room_name, status, alert_enabled FROM rooms WHERE id = $room_id LIMIT 1");
            $room_data = mysqli_fetch_assoc($room_q);
            $room_status   = isset($room_data['status']) ? strtolower(trim($room_data['status'])) : 'trong'; 
            $room_name     = $room_data['room_name'] ?? "Phòng $room_id";
            $alert_enabled = $room_data['alert_enabled'] ?? 1; 
            
            $last_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
            $last = mysqli_fetch_assoc($last_q);
            $last_door_state = $last ? $last['details'] : 'Đóng';
            
            $trigger_alert = false;
            $alert_message = "";
            
            if ($last_door_state !== $current_state) {
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, NOW(), 'CẢM BIẾN', '$current_state')");
                echo "$room_name: Thay đổi trạng thái cửa thành $current_state. Đã ghi DB.<br>";
                
                if ($alert_enabled == 1 && $room_status === 'trong' && $current_state === 'Mở') {
                    $trigger_alert = true;
                    $alert_message = "⚠️ CẢNH BÁO ĐỘT NHẬP: {$room_name} đang TRỐNG nhưng CỬA VỪA MỞ! Vui lòng kiểm tra gấp!";
                }
            } 
            else {
                echo "$room_name: Không có thay đổi cửa ($current_state).<br>";
                
                if ($alert_enabled == 1 && $room_status === 'trong' && $current_state === 'Mở') {
                    $check_last_log_q = mysqli_query($conn, "SELECT event_type FROM room_logs WHERE room_id = $room_id ORDER BY id DESC LIMIT 1");
                    $last_log_data = mysqli_fetch_assoc($check_last_log_q);
                    $last_log_type = $last_log_data['event_type'] ?? '';

                    $should_alert = false;
                    
                    if ($last_log_type !== 'CẢNH BÁO') {
                        $should_alert = true;
                    } else {
                        $check_time_q = mysqli_query($conn, "SELECT id FROM room_logs WHERE room_id = $room_id AND event_type = 'CẢNH BÁO' AND event_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
                        if (mysqli_num_rows($check_time_q) == 0) {
                            $should_alert = true;
                        }
                    }

                    if ($should_alert) {
                        $trigger_alert = true;
                        $alert_message = "⚠️ CẢNH BÁO AN TOÀN: {$room_name} đang trạng thái TRỐNG nhưng CỬA VỪA MỞ! Vui lòng khép lại!";
                    }
                }
            }
            
            if ($trigger_alert) {
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, NOW(), 'CẢNH BÁO', '$alert_message')");
                echo "<strong>[ALERT]</strong> " . $alert_message . "<br>";
                thong_bao_telegram($alert_message);
            }
        } else {
            echo "Phòng $room_id: Lỗi không lấy được dữ liệu từ API Tuya.<br>";
        }
    }
    
    echo "<br>";
    if ($cycle < 3) {
        sleep(15);
        mysqli_ping($conn); 
    }
}

echo "--- Hoàn thành chu kỳ quét ---";
exit;
?>
