<?php
include 'config.php';
checkLogin(); // Đảm bảo an toàn bảo mật dữ liệu

header('Content-Type: application/json');

// 1. Lấy danh sách trạng thái các phòng hiện tại
$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
$rooms = [];
while ($row = mysqli_fetch_assoc($rooms_query)) {
    $rooms[] = $row;
}

// 2. Lấy 5 lịch sử đóng mở cửa gần nhất
$logs_query = mysqli_query($conn, "
    SELECT l.event_time, r.room_name, l.event_type, l.details 
    FROM room_logs l 
    JOIN rooms r ON l.room_id = r.id 
    ORDER BY l.id DESC LIMIT 5
");
$logs = [];
while ($row = mysqli_fetch_assoc($logs_query)) {
    $logs[] = $row;
}

// Nếu chưa có lịch sử nào, trả về thông báo trống mượt mà
if (empty($logs)) {
    $logs[] = [
        "event_time" => date('Y-m-dr H:i:s'),
        "room_name" => "Hệ thống",
        "event_type" => "THÔNG BÁO",
        "details" => "Hiện chưa ghi nhận sự kiện đóng mở cửa nào."
    ];
}

// Xuất chuỗi JSON cho Javascript xử lý render giao diện không cần F5
echo json_encode([
    "rooms" => $rooms,
    "logs" => $logs
]);
exit();
?>
