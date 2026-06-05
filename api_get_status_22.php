<?php
// api_get_status.php (Bản tối ưu tốc độ - CHỈ ĐỌC DATABASE)
include 'config.php';
checkLogin(); 

header('Content-Type: application/json');

// ========================================================
// BỎ HẲN MỤC 1, 2, 3 (CẤU HÌNH VÀ GỌI API TUYA)
// VÌ CÁC FILE NÀY ĐÃ ĐƯỢC CRONJOB CHẠY NGẦM XỬ LÝ RỒI
// ========================================================

// 4. DUYỆT DATABASE VÀ BỐC DỮ LIỆU CŨ RA (KHÔNG GỌI API NỮA)
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
$rooms = [];

while ($row = mysqli_fetch_assoc($rooms_query)) {
    $current_room_id = (int)$row['id'];
    
    // Lấy trạng thái cửa mới nhất mà CRONJOB ĐÃ GHI VÀO DB TRƯỚC ĐÓ
    $last_db_log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = $current_room_id AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
    $last_db_log = mysqli_fetch_assoc($last_db_log_q);
    
    // Nếu trong DB ghi là Mở thì hiển thị là Mở, ngược lại là Đóng
    $tuya_door_state = ($last_db_log && strpos($last_db_log['details'], 'Mở') !== false) ? 'Mở' : 'Đóng';
    $is_forget_warning = false;

    // Bỏ toàn bộ logic INSERT logs hay sendTelegram ở đây, vì Cronjob đã làm rồi!

    $rooms[] = [
        "id" => $current_room_id,
        "room_name" => $row['room_name'],
        "status" => $row['status'],
        "door" => $tuya_door_state, // Trạng thái này lấy từ DB ra cực nhanh
        "is_forget_warning" => $is_forget_warning
    ];
}

// Lấy 5 log cuối để hiển thị lên bảng tin (Giữ nguyên)
$logs_query = mysqli_query($conn, "SELECT l.id, l.event_time, r.room_name, l.event_type, l.amount, l.details FROM room_logs l JOIN rooms r ON l.room_id = r.id ORDER BY l.id DESC LIMIT 5");
$logs = [];
while ($row_log = mysqli_fetch_assoc($logs_query)) {
    $logs[] = $row_log;
}

// Xuất JSON trả về cho index.php vẽ giao diện
echo json_encode(["rooms" => $rooms, "logs" => $logs], JSON_UNESCAPED_UNICODE);
?>
