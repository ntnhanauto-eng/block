<?php
// api_get_status.php
include 'config.php';
header('Content-Type: application/json; charset=utf-8');

// 1. LẤY DANH SÁCH PHÒNG (Bỏ cột phụ để tránh lỗi mất phòng)
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
$rooms = [];

if ($rooms_query) {
    while ($row = mysqli_fetch_assoc($rooms_query)) {
        // Lấy dòng CẢM BIẾN mới nhất từ DB do file cron ghi vào
        $log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = {$row['id']} AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
        $log = mysqli_fetch_assoc($log_q);
        
        $door_status = $log['details'] ?? 'Đóng'; // Mặc định Đóng nếu chưa có log
        $room_id = (int)$row['id'];
        $room_name = $row['room_name'];
        $room_status = $row['status'];

        // =========================================================================
        // 🔥 KHỐI LOGIC MỚI: TỰ ĐỘNG PHÁT HIỆN CỬA MỞ BẤT THƯỜNG TẠI PHÒNG TRỐNG
        // =========================================================================
        if ($room_status === 'trong' && $door_status === 'Mở') {
            $time_now = date('Y-m-d H:i:s');
            
            // Kiểm tra chống trùng: Trong vòng 1 phút qua đã tạo log cảnh báo cho phòng này chưa?
            $check_duplicate = mysqli_query($conn, "SELECT id FROM room_logs WHERE room_id = $room_id AND event_type = 'BẤT THƯỜNG' AND event_time >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
            
            if (mysqli_num_rows($check_duplicate) == 0) {
                $alert_details = "🚨 HỆ THỐNG: Phát hiện cửa mở bất thường tại phòng trống!";
                
                // 1. Ghi lệnh tạo chữ BẤT THƯỜNG vào DB để admin_logs.php nhận diện bôi đỏ
                mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'BẤT THƯỜNG', 0, '$alert_details')");
                
                // 2. Kích hoạt bắn thông báo Telegram khẩn cấp về điện thoại
                if (function_exists('sendTelegramNotification')) {
                    $telegram_msg = "🚨 <b>HỆ THỐNG AN NINH KHÁCH SẠN</b>\n";
                    $telegram_msg .= "🏨 <b>Vị trí:</b> $room_name\n";
                    $telegram_msg .= "⚠️ <b>SỰ CỐ:</b> CỬA MỞ BẤT THƯỜNG!\n";
                    $telegram_msg .= "📌 <b>Trạng thái phòng:</b> <code style='color:red;'>PHÒNG TRỐNG</code>\n";
                    $telegram_msg .= "⏰ <b>Thời gian:</b> $time_now\n";
                    $telegram_msg .= "❗ <i>Vui lòng rà soát camera hành lang hoặc cử nhân viên lên kiểm tra ngay!</i>";
                    
                    sendTelegramNotification($telegram_msg);
                }
            }
        }
        // =========================================================================
        
        $rooms[] = [
            "id" => $room_id,
            "room_name" => $room_name,
            "status" => $room_status,
            "door" => $door_status,
            "is_forget_warning" => false // Để mặc định là false để không bị lỗi giao diện
        ];
    }
}

// 2. LẤY DANH SÁCH 5 LỊCH SỬ (LOGS) MỚI NHẤT
$logs_query = mysqli_query($conn, "SELECT rl.id, rl.event_time, r.room_name, rl.event_type, rl.amount, rl.details 
                                   FROM room_logs rl 
                                   JOIN rooms r ON rl.room_id = r.id 
                                   ORDER BY rl.id DESC LIMIT 5");
$logs = [];

if ($logs_query) {
    while ($log_row = mysqli_fetch_assoc($logs_query)) {
        $logs[] = [
            "id" => (int)$log_row['id'],
            "event_time" => $log_row['event_time'],
            "room_name" => $log_row['room_name'],
            "event_type" => $log_row['event_type'],
            "amount" => (int)$log_row['amount'],
            "details" => $log_row['details']
        ];
    }
}

// 3. XỬ LÝ XUẤT MẢNG JSON ĐẦY ĐỦ
ob_clean(); // Xóa bộ đệm rác
echo json_encode([
    "rooms" => $rooms,
    "logs" => $logs
], JSON_UNESCAPED_UNICODE);
exit;
?>
