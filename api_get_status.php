<?php
include 'config.php';
checkLogin(); // Chỉ những ai đăng nhập mới được lấy dữ liệu từ API này

$data = ['rooms' => [], 'logs' => []];

// 1. Lấy dữ liệu 3 phòng
$res_rooms = mysqli_query($conn, "SELECT * FROM rooms ORDER BY room_name ASC");
while ($r = mysqli_fetch_assoc($res_rooms)) {
    $data['rooms'][] = $r;
}

// 2. Lấy 15 lịch sử đóng mở cửa mới nhất
$res_logs = mysqli_query($conn, "SELECT l.*, r.room_name FROM door_logs l JOIN rooms r ON l.room_id = r.id ORDER BY l.event_time DESC LIMIT 15");
while ($l = mysqli_fetch_assoc($res_logs)) {
    $data['logs'][] = $l;
}

header('Content-Type: application/json');
echo json_encode($data);
?>
