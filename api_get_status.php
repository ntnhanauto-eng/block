<?php
include 'config.php';
checkLogin(); // Đảm bảo an toàn bảo mật dữ liệu

header('Content-Type: application/json');

// 1. ĐÃ SỬA: Lấy danh sách phòng kèm theo sự kiện cửa gần đây nhất của phòng đó
$rooms_query = mysqli_query($conn, "
    SELECT r.id, r.room_name, r.status, 
    (SELECT l.details FROM room_logs l WHERE l.room_id = r.id ORDER BY l.id DESC LIMIT 1) as last_door_event 
    FROM rooms r
");

if (!$rooms_query) {
    echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
    exit();
}

$rooms = [];
while ($row = mysqli_fetch_assoc($rooms_query)) {
    // Mặc định ban đầu coi như cửa đang đóng nếu hệ thống chưa từng ghi nhận lịch sử nào
    $door_status = 'Đóng'; 
    
    // Nếu phòng này đã từng có lịch sử, tiến hành bóc tách chuỗi chữ
    if ($row['last_door_event']) {
        $event_details = mb_strtolower($row['last_door_event'], 'UTF-8');
        
        // Nếu trong chi tiết lịch sử có chứa chữ "mở" hoặc chữ "open"
        if (strpos($event_details, 'mở') !== false || strpos($event_details, 'open') !== false) {
            $door_status = 'Mở';
        }
    }
    
    // Đóng gói dữ liệu phòng gửi về cho JavaScript
    $rooms[] = [
        "id" => (int)$row['id'],
        "room_name" => $row['room_name'],
        "status" => $row['status'],
        "door" => $door_status // Biến door này sẽ truyền trạng thái "Mở" hoặc "Đóng" lên giao diện
    ];
}

// 2. Lấy 5 lịch sử đóng mở cửa gần nhất để hiển thị bảng nhật ký hành lang (Giữ nguyên cấu trúc của bạn)
$logs_query = mysqli_query($conn, "
    SELECT l.event_time, r.room_name, l.event_type, l.details 
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

// Nếu chưa có lịch sử nào, trả về thông báo trống mượt mà
if (empty($logs)) {
    $logs[] = [
        "event_time" => date('Y-m-d H:i:s'), // Đã sửa lỗi định dạng ngày 'Y-m-dr' bị thừa chữ r của bạn trước đó
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
