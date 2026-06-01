<?php
include 'config.php';
checkLogin(); // Đảm bảo nhân viên dọn phòng cũng có tài khoản để đăng nhập hệ thống

$message = "";
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

// Lấy thông tin phòng hiện tại
$room_q = mysqli_query($conn, "SELECT room_name, status FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_q);

if (!$room) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>X Lỗi: Không tìm thấy số phòng này!</h2>");
}

// XỬ LÝ KHI NHÂN VIÊN BẤM NÚT XÁC NHẬN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_cleaning'])) {
        // Ghi nhận mốc bắt đầu dọn phòng vào hệ thống để mở khóa cảm biến cửa
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', 'BẮT ĐẦU DỌN PHÒNG', 0)");
        $message = "<div class='alert success'>🔓 ĐÃ XÁC NHẬN BẮT ĐẦU! Hệ thống tạm tắt báo động cửa phòng này. Bạn có thể mở cửa làm việc.</div>";
    }
    
    if (isset($_POST['finish_cleaning'])) {
        // Cập nhật trạng thái phòng về lại 'trong' (Phòng Trống chuẩn)
        mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
        // Ghi log hoàn tất ca dọn dẹp
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', 'Hoàn tất ca dọn dẹp vệ sinh phòng.', 0)");
        $message = "<div class='alert success'>🔒 ĐÃ HOÀN TẤT VỆ SINH! Phòng đã chuyển sang trạng thái TRỐNG SẠCH SẼ.</div>";
        // Cập nhật lại biến hiển thị
        $room['status'] = 'trong';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xác nhận dọn buồng phòng</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box; }
        .card { background: white; width: 100%; max-width: 400px; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        h2 { color: #1e293b; margin-top: 0; font-size: 22px; }
        .room-badge { background: #0288d1; color: white; font-size: 24px; font-weight: bold; padding: 10px 20px; border-radius: 8px; display: inline-block; margin-bottom: 15px; }
        .status-text { font-size: 14px; color: #64748b; margin-bottom: 25px; }
        
        .btn { display: block; width: 100%; padding: 16px; font-size: 16px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-decoration: none; }
        .btn-blue { background: #007bff; }
        .btn-green { background: #28a745; }
        .btn-secondary { background: #64748b; font-size: 14px; padding: 10px; margin-top: 15px; }
        
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 20px; text-align: left; }
        .alert.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<div class="card">
    <h2>🧹 HỆ THỐNG BUỒNG PHÒNG</h2>
    <div class="room-badge"><?php echo htmlspecialchars($room['room_name']); ?></div>
    
    <div class="status-text">
        Trạng thái hiện tại: 
        <b>
        <?php 
            if($room['status'] === 've_sinh') echo '<span style="color:#ffc107;">⚠️ Đang chờ / Đang dọn dẹp</span>';
            elseif($room['status'] === 'trong') echo '<span style="color:#28a745;">🟢 Phòng trống sẵn sàng</span>';
            else echo '<span style="color:#dc3545;">🔴 Có khách ở</span>';
        ?>
        </b>
    </div>

    <?php echo $message; ?>

    <form method="POST">
        <?php if ($room['status'] === 've_sinh'): ?>
            <button type="submit" name="start_cleaning" class="btn btn-blue">🔓 BẤT ĐẦU DỌN (TẮT BÁO ĐỘNG)</button>
            
            <button type="submit" name="finish_cleaning" class="btn btn-green" style="margin-top: 20px;">🚀 ĐÃ DỌN XONG (HOÀN TẤT)</button>
        <?php else: ?>
            <p style="color: #64748b; font-size: 14px; font-style: italic;">Phòng này hiện tại không ở chế độ vệ sinh nên không cần tác vụ.</p>
        <?php endif; ?>
    </form>

    <a href="index.php" class="btn btn-secondary">Quay lại Tổng quan</a>
</div>

</body>
</html>
