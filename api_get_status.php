<?php
// 1. Ép hệ thống hiển thị lỗi ra màn hình
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';
header('Content-Type: application/json; charset=utf-8');

// 2. Kiểm tra kết nối Database
if (!isset($conn)) {
    die("LỖI: Biến kết nối Database trong file config.php không phải tên là \$conn. Bạn hãy kiểm tra lại file config.php xem biến đó tên là gì (ví dụ: \$connect, \$db, \$link...) để sửa lại.");
}

$rooms_query = mysqli_query($conn, "SELECT id, room_name, status FROM rooms");
if (!$rooms_query) {
    die("LỖI SQL (rooms): " . mysqli_error($conn));
}

$rooms = [];
while ($row = mysqli_fetch_assoc($rooms_query)) {
    // Lấy dòng log cảm biến mới nhất
    $log_q = mysqli_query($conn, "SELECT details FROM room_logs WHERE room_id = {$row['id']} AND event_type = 'CẢM BIẾN' ORDER BY id DESC LIMIT 1");
    
    if (!$log_q) {
        die("LỖI SQL (room_logs): " . mysqli_error($conn) . ". -> Hãy kiểm tra xem tên bảng room_logs hoặc các tên cột đã gõ đúng chưa.");
    }
    
    $log = mysqli_fetch_assoc($log_q);
    
    $rooms[] = [
        "id" => $row['id'],
        "room_name" => $row['room_name'],
        "status" => $row['status'],
        "door" => ($log && isset($log['details'])) ? $log['details'] : 'Đóng'
    ];
}

ob_clean(); // Xóa bộ đệm rác
echo json_encode(["rooms" => $rooms], JSON_UNESCAPED_UNICODE);
exit;
?>
