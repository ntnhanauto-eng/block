<?php
include 'config.php'; // Kết nối DB để cập nhật dữ liệu

$message = "";
$room_name = "";

// Lấy ID phòng từ link truyền vào
if (isset($_GET['room_id'])) {
    $room_id = (int)$_GET['room_id'];
    
    // Lấy tên phòng hiển thị cho nhân viên biết
    $room_query = mysqli_query($conn, "SELECT room_name, status FROM rooms WHERE id = $room_id");
    $room_data = mysqli_fetch_assoc($room_query);
    if ($room_data) {
        $room_name = $room_data['room_name'];
        $current_status = $room_data['status'];
    }
}

// Xử lý khi nhân viên bấm nút "Xác nhận dọn xong"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_cleaning'])) {
    $room_id = (int)$_POST['room_id'];
    
    // 1. Cập nhật trạng thái phòng về 'trong' (Phòng Trống - Sẽ hóa xanh trên web lễ tân)
    $update = mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
    
    if ($update) {
        // 2. Ghi lịch sử vào bảng room_logs
        $time_now = date('Y-m-d H:i:s');
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'DỌN XONG', 'Nhân viên buồng phòng xác nhận đã dọn dẹp sạch sẽ')");
        
        // 3. Bắn Telegram báo cho cả hệ thống biết
        if (function_exists('sendTelegramNotification')) {
            sendTelegramNotification("🧹 ✨ <b>THÔNG BÁO BUỒNG PHÒNG:</b>\n🏨 <b>$room_name</b> đã được dọn dẹp sạch sẽ và sẵn sàng đón khách mới!");
        }
        
        $message = "<div class='success'>Đã cập nhật thành công! Hệ thống lễ tân đã nhận được tín hiệu. Bạn có thể rời đi.</div>";
        // Cập nhật lại biến để giao diện thay đổi theo
        $current_status = 'trong';
    } else {
        $message = "<div class='error'>Có lỗi xảy ra, vui lòng thử lại!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Buồng Phòng</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .clean-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 90%; max-width: 400px; text-align: center; }
        h1 { color: #333; font-size: 24px; margin-bottom: 5px; }
        .room-badge { font-size: 28px; font-weight: bold; color: #007bff; margin: 15px 0; }
        .btn-clean { width: 100%; padding: 18px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 18px; font-weight: bold; box-shadow: 0 4px 10px rgba(40,167,69,0.3); }
        .btn-clean:disabled { background: #6c757d; box-shadow: none; cursor: not-allowed; }
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; }
        .status-info { margin: 15px 0; color: #666; font-style: italic; }
    </style>
</head>
<body>
    <div class="clean-box">
        <h1>CẬP NHẬT TIẾN ĐỘ</h1>
        <?php if (!empty($room_name)): ?>
            <div class="room-badge"><?php echo $room_name; ?></div>
            
            <?php echo $message; ?>

            <?php if ($current_status === 've_sinh'): ?>
                <form method="POST">
                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                    <button type="submit" name="complete_cleaning" class="btn-clean">✨ XÁC NHẬN ĐÃ DỌN XONG</button>
                </form>
            <?php else: ?>
                <button class="btn-clean" disabled>🔒 PHÒNG KHÔNG Ở TRẠNG THÁI DỌN ĐẸP</button>
                <div class="status-info">Phòng này hiện đang trống hoặc đang có khách ở, không cần dọn vệ sinh.</div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="error">Mã phòng không hợp lệ hoặc không tồn tại!</div>
        <?php endif; ?>
    </div>
</body>
</html>
