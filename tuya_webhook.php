<?php
include 'config.php';

// Đọc luồng dữ liệu JSON gửi trực tiếp từ máy chủ đám mây Tuya
$raw_content = file_get_contents('php://input');
$data = json_decode($raw_content, true);

if ($data) {
    // Tuya mã hóa chuỗi sự kiện bằng định dạng Base64 để bảo mật, ta phải giải mã ra
    $payload = json_decode(base64_decode($data['data']), true);
    $devId = $payload['devId']; // Lấy mã ID thiết bị đang gửi tín hiệu về
    
    // Tìm phòng đang gắn mã thiết bị cảm biến cửa này trong Database
    $query = mysqli_query($conn, "SELECT * FROM rooms WHERE device_id = '$devId'");
    $room = mysqli_fetch_assoc($query);

    if ($room) {
        $statusArr = $payload['status'];
        foreach ($statusArr as $s) {
            // Nhận dạng đúng mã trạng thái mở cửa của Tuya (doorcontact_state hoặc switch)
            if (($s['code'] === 'doorcontact_state' || $s['code'] === 'switch') && ($s['value'] === true || $s['value'] === 'open')) {
                
                $room_id = $room['id'];
                $current_room_status = $room['status'];
                $event_type = 'Bình thường';
                $details = "Cửa mở khi trạng thái phòng đang gán là: " . $current_room_status;

                // THỨC HIỆN LOGIC CẢNH BÁO NẾU PHÒNG ĐANG TRỐNG
                if ($current_room_status === 'trong') {
                    $event_type = 'BẤT THƯỜNG';
                    
                    // Kích hoạt chuông báo động gửi tin nhắn đẩy về điện thoại qua Telegram Bot
                    $alert_msg = "🚨 CẢNH BÁO AN NINH 🚨\n" . $room['room_name'] . " vừa bị mở cửa bất thường khi trạng thái đang cài đặt là TRỐNG! Xin kiểm tra ngay lập tức.";
                    sendTelegramAlert($alert_msg);
                }

                // Ghi nhận lịch sử chi tiết vào Database để phục vụ Real-time hiển thị lên giao diện web
                mysqli_query($conn, "INSERT INTO door_logs (room_id, event_type, details) VALUES ($room_id, '$event_type', '$details')");
            }
        }
    }
}

// Báo cáo mã trạng thái 200 cho máy chủ Tuya biết mạng kết nối Việt Nam đã xử lý thành công gói tin
http_response_code(200);
?>
