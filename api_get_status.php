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
        
        $rooms[] = [
            "id" => (int)$row['id'],
            "room_name" => $row['room_name'],
            "status" => $row['status'],
            "door" => $log['details'] ?? 'Đóng', // Mặc định Đóng nếu chưa có log
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

// 3. XUẤT MẢNG JSON ĐẦY ĐỦ
ob_clean(); // Xóa bộ đệm rác
echo json_encode([
    "rooms" => $rooms,
    "logs" => $logs
], JSON_UNESCAPED_UNICODE);
exit;
?>
