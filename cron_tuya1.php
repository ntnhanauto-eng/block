<?php
// cron_tuya.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

// --- CẤU HÌNH THÔNG TIN TUYA API ---
$client_id   = "qap98nweqkmufpdp5d3r";     // Điền Access ID của bạn vào đây
$secret      = "cb7684adc56045bdb5f77c1d7a541d48";            // Điền Access Secret của bạn vào đây
$easyTuyaUrl = "https://openapi.tuyaus.com";    // Endpoint Tuya (Ví dụ khu vực Châu Mỹ/Châu Á)

// Cấu hình danh sách thiết bị cảm biến gắn với ID phòng trong Database
// Định dạng: ID_PHÒNG => "MÃ_DEVICE_ID_TUYA"
$devices = [
    1 => "eb9530c1bda34fc126kdqn",
    2 => "eb27e8cde676d1752cnznu",
    3 => "eb4b84fb4b534fbe2ahss6"
];

$reversed_rooms = []; // Để trống nếu không có cảm biến nào bị ngược logic

// --- HÀM TỰ ĐỘNG LẤY ACCESS TOKEN TỪ TUYA ---
function getTuyaAccessToken($url, $client_id, $secret) {
    $t = time() * 1000;
    $sign = strtoupper(hash_hmac('sha256', $client_id . $t, $secret));
    
    $ch = curl_init("$url/v1.0/token?grant_type=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "client_id: $client_id",
        "sign: $sign",
        "t: $t",
        "sign_method: HMAC-SHA256"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($res, true);
    return $json['result']['access_token'] ?? null;
}

// --- HÀM LẤY TRẠNG THÁI THIẾT BỊ TỪ TUYA ---
function getTuyaDeviceStatus($url, $client_id, $access_token, $secret, $device_id) {
    $t = time() * 1000;
    $string_to_sign = $client_id . $access_token . $t;
    $sign = strtoupper(hash_hmac('sha256', $string_to_sign, $secret));
    
    $ch = curl_init("$url/v1.0/devices/$device_id/status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

// --- CHU TRÌNH CHẠY NGẦM QUÉT 4 LẦN TRONG 1 PHÚT ---
$access_token = getTuyaAccessToken($easyTuyaUrl, $client_id, $secret);

if (!$access_token) {
    die("Lỗi: Không thể kết nối lấy Access Token từ Tuya Cloud.");
}

for ($cycle = 0; $cycle < 4; $cycle++) {
    $current_time_log = date('H:i:s');
    echo "--- Lần quét $cycle ($current_time_log) ---<br>";

    foreach ($devices as $room_id => $device_id) {
        $data = getTuyaDeviceStatus($easyTuyaUrl, $client_id, $access_token, $secret, $device_id);
        
        if (isset($data['success']) && $data['success']) {
            $is_open = false;
            
            // Tìm thuộc tính trạng thái cửa (thường là 'doorcontact_state' hoặc 'ismoving')
            foreach ($data['result'] as $status_item) {
                if (in_array($status_item['code'], ['doorcontact_state', 'ismoving', 'switch'])) {
                    $is_open = ($status_item['value'] === true || $status_item['value'] === 'on' || $status_item['value'] === 'open');
                    break;
                }
            }
            
            // Xử lý đảo ngược logic nếu phòng nằm trong danh sách ngược
            if (in_array($room_id, $reversed_rooms)) {
                $is_open = !$is_open;
            }
            
            $current_state = $is_open ? 'Mở' : 'Đóng';
            
            // 1. Đọc trạng thái phòng hiện tại và cấu hình Bật/Tắt từ DB
            $room_q = mysqli_query($conn, "SELECT room_name, status, alert_enabled FROM rooms WHERE id = $room_id LIMIT 1");
            $room_data = mysqli_fetch_assoc($room_q);
            $room_status   = $room_data['status'] ?? 'trong'; 
            $room_name     = $room_data['room_name'] ?? "Phòng $room_id";
            $alert_enabled = $room_data['alert_enabled'] ?? 1; 
            
            // 2. Lấy log trạng thái cửa gần nhất từ DB để so sánh thay đổi
            $last_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
            $last = mysqli_fetch_assoc($last_q);
            $last_door_state = $last ? $last['details'] : 'Đóng';
            
            $trigger_alert = false;
            $alert_message = "";
            
            // TÌNH HUỐNG A: Cửa thực tế vừa có hành động Đóng/Mở
            if ($last_door_state !== $current_state) {
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, NOW(), 'CẢM BIẾN', '$current_state')");
                echo "$room_name: Thay đổi trạng thái thành $current_state. Đã ghi DB.<br>";
                
                if ($alert_enabled == 1 && $room_status === 'trong' && $current_state === 'Mở') {
                    $trigger_alert = true;
                    $alert_message = "⚠️ CẢNH BÁO ĐỘT NHẬP: {$room_name} đang TRỐNG nhưng CỬA VỪA MỞ! Vui lòng kiểm tra gấp!";
                }
            } 
            // TÌNH HUỐNG B: Cửa không đổi, nhưng phòng ở trạng thái TRỐNG mà cửa VẪN ĐANG MỞ
            else {
                echo "$room_name: Không có thay đổi ($current_state).<br>";
                
                if ($alert_enabled == 1 && $room_status === 'trong' && $current_state === 'Mở') {
                    // Kiểm tra chống spam cảnh báo liên tục (chỉ cho phép ghi log cảnh báo 1 phút/lần)
                    $check_alert_q = mysqli_query($conn, "SELECT id FROM room_logs WHERE room_id = $room_id AND event_type = 'CẢNH BÁO' AND event_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
                    if (mysqli_num_rows($check_alert_q) == 0) {
                        $trigger_alert = true;
                        $alert_message = "⚠️ CẢNH BÁO AN TOÀN: {$room_name} đang trạng thái TRỐNG nhưng CỬA VẪN ĐANG MỞ! Vui lòng khép lại!";
                    }
                }
            }
            
            // 3. Nếu có báo động và Admin đang bật cấu hình, thực hiện ghi Log cảnh báo
            if ($trigger_alert) {
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, NOW(), 'CẢNH BÁO', '$alert_message')");
                echo "<strong>[ALERT]</strong> Đã kích hoạt cảnh báo cho $room_name.<br>";
                
                // Bạn có thể chèn hàm gửi Telegram tại đây nếu cần:
                // thong_bao_telegram($alert_message);
            }
        } else {
            echo "Phòng $room_id: Lỗi không lấy được dữ liệu từ API Tuya.<br>";
        }
    }
    
    echo "<br>";
    // Nghỉ 15 giây trước khi bước sang lần quét tiếp theo (Bỏ lần nghỉ cuối cùng ở giây thứ 45)
    if ($cycle < 3) {
        sleep(15);
        // Làm mới kết nối MySQL tránh bị mất kết nối (gói hosting yếu dễ bị ngắt giữa chừng)
        mysqli_ping($conn);
    }
}

echo "--- Hoàn thành chu kỳ quét ---";
exit;
?>
