<?php
include 'config.php'; // Kết nối DB để cập nhật dữ liệu

// ĐỊNH NGHĨA MÃ PIN BẢO MẬT (Bạn có thể đổi số '1234' này thành số khác tùy ý)
define('CLEANING_PIN', '1234');

$message = "";
$room_name = "";
$room_id = 0;
$current_status = "";

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
    $user_pin = trim($_POST['clean_pin']); // Lấy mã PIN nhân viên nhập vào

    // KIỂM TRA 1: Xem căn phòng có đang ở trạng thái cần dọn dẹp không
    if ($current_status !== 've_sinh') {
        $message = "<div class='error'>🔒 Lỗi: Phòng này hiện không ở trạng thái cần dọn dẹp!</div>";
    }
    // KIỂM TRA 2: Xác thực mã PIN nhập vào có khớp với mã hệ thống không
    elseif ($user_pin !== CLEANING_PIN) {
        $message = "<div class='error'>❌ Sai mã PIN bảo mật! Vui lòng kiểm tra và nhập lại.</div>";
    } 
    // NẾU TẤT CẢ ĐỀU ĐÚNG -> TIẾN HÀNH ĐỔI TRẠNG THÁI PHÒNG
    else {
        // 1. Cập nhật trạng thái phòng về 'trong' (Phòng Trống - Hóa xanh trên web lễ tân)
        $update = mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
        
        if ($update) {
            // 2. Ghi lịch sử vào bảng room_logs
            $time_now = date('Y-m-d H:i:s');
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'DỌN XONG', 'Nhân viên xác nhận hoàn thành từ danh sách riêng lẻ')");
            
            // 3. Bắn Telegram báo cho cả hệ thống biết
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("🧹 ✨ <b>THÔNG BÁO BUỒNG PHÒNG:</b>\n🏨 <b>$room_name</b> đã được dọn dẹp sạch sẽ và sẵn sàng đón khách mới!");
            }
            
            $message = "<div class='success'>🎉 Đã cập nhật thành công! Hệ thống lễ tân đã nhận được tín hiệu phòng trống màu xanh.</div>";
            $current_status = 'trong'; // Đổi lại biến để giao diện khóa nút bấm ngay lập tức
        } else {
            $message = "<div class='error'>Có lỗi xảy ra kết nối dữ liệu, vui lòng thử lại!</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Buồng Phòng Bảo Mật</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .clean-box { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 90%; max-width: 400px; text-align: center; }
        h1 { color: #333; font-size: 22px; margin-bottom: 5px; font-weight: bold; }
        .room-badge { font-size: 32px; font-weight: bold; color: #007bff; margin: 10px 0; }
        
        /* CSS cho ô nhập mã PIN */
        .pin-input { width: 80%; padding: 12px; font-size: 20px; text-align: center; border-radius: 6px; border: 2px solid #ccc; margin-bottom: 15px; letter-spacing: 5px; font-weight: bold; }
        .pin-input:focus { border-color: #28a745; outline: none; }
        
        .btn-clean { width: 100%; padding: 15px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; box-shadow: 0 4px 10px rgba(40,167,69,0.3); transition: all 0.2s; }
        .btn-clean:active { transform: scale(0.98); }
        .btn-clean:disabled { background: #6c757d; box-shadow: none; cursor: not-allowed; }
        
        .success { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; font-size: 14px; text-align: left; }
        .error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; font-size: 14px; text-align: left; }
        .status-info { margin-top: 15px; color: #666; font-style: italic; font-size: 13px; }
    </style>
</head>
<body>
    <div class="clean-box">
        <h1>BÁO CÁO BUỒNG PHÒNG</h1>
        
        <?php if ($room_id > 0 && !empty($room_name)): ?>
            <div class="room-badge"><?php echo $room_name; ?></div>
            
            <?php echo $message; ?>

            <?php if ($current_status === 've_sinh'): ?>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                    
                    <p style="margin: 0 0 8px 0; color: #555; font-weight: bold;">Nhập mã PIN xác thực:</p>
                    <input type="password" name="clean_pin" class="pin-input" placeholder="••••" maxlength="6" inputmode="numeric" required>
                    
                    <button type="submit" name="complete_cleaning" class="btn-clean">✨ XÁC NHẬN ĐÃ DỌN XONG</button>
                </form>
            <?php else: ?>
                <button class="btn-clean" disabled>🔒 HỆ THỐNG ĐANG KHÓA</button>
                <div class="status-info">Phòng hiện tại đang hoạt động bình thường, không trong ca dọn dẹp vệ sinh.</div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="error">⚠️ Đường dẫn (Mã ID phòng) không hợp lệ hoặc không tồn tại trong hệ thống khách sạn!</div>
        <?php endif; ?>
    </div>
</body>
</html>
