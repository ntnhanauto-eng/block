<?php
include 'config.php';
checkLogin(); 

header('Content-Type: application/json');

// 1. Lấy danh sách phòng kèm trạng thái cửa gần nhất
$rooms_query = mysqli_query($conn, "
    SELECT r.id, r.room_name, r.status, 
    (SELECT l.details FROM room_logs l WHERE l.room_id = r.id ORDER BY l.id DESC LIMIT 1) as last_door_event 
    FROM rooms r
");

$rooms = [];
while ($row = mysqli_fetch_assoc($rooms_query)) {
    $door_status = 'Đóng'; 
    if ($row['last_door_event']) {
        $event_details = mb_strtolower($row['last_door_event'], 'UTF-8');
        if (strpos($event_details, 'mở') !== false || strpos($event_details, 'open') !== false) {
            $door_status = 'Mở';
        }
    }
    $rooms[] = [
        "id" => (int)$row['id'],
        "room_name" => $row['room_name'],
        "status" => $row['status'],
        "door" => $door_status
    ];
}

// 2. Lấy 5 lịch sử thực tế từ cảm biến Tuya (Không tự tạo log giả nữa)
$logs_query = mysqli_query($conn, "
    SELECT l.id, l.event_time, r.room_name, l.event_type, l.details 
    FROM room_logs l 
    JOIN rooms r ON l.room_id = r.id 
    ORDER BY l.id DESC LIMIT 5
");

$logs = [];
if ($logs_query) {
    while ($row = mysqli_fetch_assoc($logs_query)) {
        $logs[] = $row;
    }
}

// Xuất chuỗi JSON thuần khiết về cho index.php tự so sánh
echo json_encode([
    "rooms" => $rooms,
    "logs" => $logs
]);
exit();
?>
