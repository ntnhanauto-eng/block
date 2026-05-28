<?php
include 'config.php';
checkLogin(); 

header('Content-Type: application/json');

// 1. Lấy danh sách trạng thái các phòng hiện tại kèm sự kiện và mốc thời gian cửa gần nhất
$rooms_query = mysqli_query($conn, "
    SELECT r.id, r.room_name, r.status, 
    (SELECT l.details FROM room_logs l WHERE l.room_id = r.id ORDER BY l.id DESC LIMIT 1) as last_door_event,
    (SELECT l.event_time FROM room_logs l WHERE l.room_id = r.id ORDER BY l.id DESC LIMIT 1) as last_event_time
    FROM rooms r
");

if (!$rooms_query) {
    echo json_encode(["success" => false, "error" => mysqli_error($conn)]);
    exit();
}

$rooms = [];
while ($row = mysqli_fetch_assoc($rooms_query)) {
    $door_status = 'Đóng'; 
    $is_forget_warning = false;

    if ($row['last_door_event']) {
        $event_details = mb_strtolower($row['last_door_event'], 'UTF-8');
        if (strpos($event_details, 'mở') !== false || strpos($event_details, 'open') !== false) {
            $door_status = 'Mở';
            
            // TÍNH NĂNG 1: Đo khoảng cách thời gian từ lúc mở cửa đến hiện tại
            $opened_time = strtotime($row['last_event_time']);
            $current_time = time();
            $time_passed = $current_time - $opened_time;

            if ($time_passed > 300) { // Nếu mở cửa quá 5 phút (300 giây)
                $is_forget_warning = true;
                
                // Cơ chế tự động bắn Telegram nhắc nhở định kỳ (chỉ bắn 1 lần mỗi phút để tránh ngập tin nhắn)
                if ($time_passed % 60 < 4 && function_exists('sendTelegramNotification')) {
                    sendTelegramNotification("⚠️ <b>CẢNH BÁO QUÊN ĐÓNG CỬA:</b>\n🏨 Phòng <b>{$row['room_name']}</b> đã mở cửa liên tục hơn 5 phút!\nVui lòng nhắc nhở kiểm tra thiết bị điện và an toàn.");
                }
            }
        }
    }
    
    $rooms[] = [
        "id" => (int)$row['id'],
        "room_name" => $row['room_name'],
        "status" => $row['status'],
        "door" => $door_status,
        "is_forget_warning" => $is_forget_warning // Trả về true nếu phòng quên đóng cửa
    ];
}

// 2. Lấy 5 lịch sử thực tế từ database
$logs_query = mysqli_query($conn, "
    SELECT l.id, l.event_time, r.room_name, l.event_type, l.details 
    FROM room_logs l 
    JOIN rooms r ON l.room_id = r.id 
    ORDER BY l.id DESC LIMIT 5
");

$logs = [];
if ($logs_query) {
    while ($row = mysqli_fetch_assoc($logs_query)) {
        $logs[] = [
            "id" => (int)$row['id'],
            "event_time" => $row['event_time'],
            "room_name" => $row['room_name'],
            "event_type" => $row['event_type'],
            "details" => $row['details']
        ];
    }
}

echo json_encode(["rooms" => $rooms, "logs" => $logs]);
exit();
?>
