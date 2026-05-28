<?php
include 'config.php'; // Kết nối Database và kiểm tra session nếu cần

// ĐỊNH NGHĨA MÃ PIN BẢO MẬT (Bạn có thể đổi số này tùy ý)
define('CLEANING_PIN', '1234');

// BẮT ĐẦU SỬA: Sử dụng Session tạm thời để truyền thông báo sau khi chuyển hướng trang, tránh mất chữ khi Redirect
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$message = isset($_SESSION['clean_msg']) ? $_SESSION['clean_msg'] : "";
unset($_SESSION['clean_msg']); // Xóa ngay sau khi đã lấy ra hiển thị

// XỬ LÝ KHI NHÂN VIÊN BẤM XÁC NHẬN DỌN XONG TRÊN DANH SÁCH
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finish_clean') {
    $room_id = (int)$_POST['room_id'];
    $user_pin = trim($_POST['clean_pin']);
    $room_name = mysqli_real_escape_string($conn, $_POST['room_name']);

    // Kiểm tra mã PIN
    if ($user_pin !== CLEANING_PIN) {
        $_SESSION['clean_msg'] = "<div class='alert error'>❌ Sai mã PIN bảo mật cho $room_name! Vui lòng thử lại.</div>";
    } else {
        // Cập nhật trạng thái phòng về 'trong' (Phòng Trống)
        $update = mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
        
        if ($update) {
            // Ghi log hệ thống
            $time_now = date('Y-m-d H:i:s');
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'DỌN XONG', 'Nhân viên xác nhận hoàn thành từ danh sách tổng hợp')");
            
            // Bắn Telegram thông báo cho toàn khách sạn
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("🧹 ✨ <b>THÔNG BÁO BUỒNG PHÒNG:</b>\n🏨 <b>$room_name</b> đã dọn dẹp xong từ danh sách tổng hợp!");
            }
            
            $_SESSION['clean_msg'] = "<div class='alert success'>🎉 Đã cập nhật $room_name thành phòng trống thành công!</div>";
        } else {
            $_SESSION['clean_msg'] = "<div class='alert error'>Có lỗi kết nối cơ sở dữ liệu!</div>";
        }
    }
    
    // ĐÃ SỬA: Chuyển hướng trang (Redirect) về chính nó bằng phương thức GET để xóa sạch dữ liệu form cũ, chống trùng lặp log
    header("Location: danhsach_buongphong.php");
    exit();
}

// TRUY VẤN TẤT CẢ CÁC PHÒNG ĐANG Ở TRẠNG THÁI 've_sinh' (Lễ tân đẩy sang sẽ tự hiện ra tại đây)
$cleaning_rooms = mysqli_query($conn, "SELECT id, room_name FROM rooms WHERE status = 've_sinh' ORDER BY room_name ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trung Tâm Điều Hành Buồng Phòng</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; background: #f4f7f6; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { text-align: center; color: #333; font-size: 24px; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-weight: bold; font-size: 14px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Giao diện thẻ danh sách phòng cần dọn */
        .room-item { background: #fff2cc; border-left: 6px solid #ffc107; padding: 18px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .room-title { font-size: 22px; font-weight: bold; color: #333; margin: 0 0 10px 0; display: flex; justify-content: space-between; align-items: center; }
        .status-badge { background: #ffc107; color: #333; font-size: 12px; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        
        .action-form { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .pin-box { flex: 1; min-width: 120px; padding: 10px; font-size: 16px; text-align: center; border-radius: 6px; border: 2px solid #ccc; font-weight: bold; }
        .pin-box:focus { border-color: #28a745; outline: none; }
        
        .btn-done { background: #28a745; color: white; border: none; padding: 10px 20px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .btn-done:active { transform: scale(0.98); }
        
        .empty-state { background: white; padding: 40px; text-align: center; border-radius: 8px; color: #666; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .empty-icon { font-size: 48px; margin-bottom: 10px; }
        
        .refresh-notice { text-align: center; font-size: 12px; color: #888; margin-top: 20px; font-style: italic; }
    </style>
    <script>
        setInterval(function(){
            // Chỉ reload nếu người dùng không đang gõ mã PIN (tránh mất dữ liệu đang gõ)
            if(document.activeElement.tagName !== 'INPUT') {
                window.location.reload();
            }
        }, 10000);
    </script>
</head>
<body>
<div class="container">
    <h1>🧹 DANH SÁCH PHÒNG CẦN DỌN VỆ SINH</h1>
    
    <?php echo $message; ?>

    <div class="list-wrapper">
        <?php if (mysqli_num_rows($cleaning_rooms) > 0): ?>
            <?php while($room = mysqli_fetch_assoc($cleaning_rooms)): ?>
                <div class="room-item">
                    <div class="room-title">
                        <span>🏨 <?php echo $room['room_name']; ?></span>
                        <span class="status-badge">ĐANG CHỜ DỌN</span>
                    </div>
                    
                    <form method="POST" class="action-form" autocomplete="off">
                        <input type="hidden" name="action" value="finish_clean">
                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                        <input type="hidden" name="room_name" value="<?php echo $room['room_name']; ?>">
                        
                        <input type="password" name="clean_pin" class="pin-box" placeholder="Mã PIN" maxlength="6" inputmode="numeric" required>
                        <button type="submit" class="btn-done">✨ XÁC NHẬN XONG</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">✨🌸🍃</div>
                <h3>Tuyệt vời! Không có phòng nào cần dọn</h3>
                <p>Tất cả các phòng hiện đã sạch sẽ hoặc đang có khách ở.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="refresh-notice">🔄 Hệ thống tự động đồng bộ danh sách sau mỗi 10 giây...</div>
</div>
</body>
</html>
