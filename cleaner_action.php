<?php
include 'config.php';
checkLogin(); // Đảm bảo bắt buộc phải đăng nhập để lấy thông tin cá nhân nhân viên

$message = "";
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$current_user = $_SESSION['username'] ?? 'Ẩn danh'; // Lấy tên tài khoản đang đăng nhập

// Lấy thông tin phòng hiện tại
$room_q = mysqli_query($conn, "SELECT room_name, status FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_q);

if (!$room) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ Lỗi: Không tìm thấy số phòng này!</h2>");
}

// XỬ LÝ KHI NHÂN VIÊN TÁC ĐỘNG BẤM NÚT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_cleaning'])) {
        // Ghi nhận mốc BẮT ĐẦU DỌN và lưu kèm tên nhân viên thực hiện
        $log_details = "BẮT ĐẦU DỌN PHÒNG - Nhân viên: $current_user";
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
        $message = "<div class='alert success'>🔓 ĐÃ XÁC NHẬN! Hệ thống tạm tắt báo động cửa. Bạn có thể mở cửa làm việc.</div>";
    }
    
    if (isset($_POST['finish_cleaning'])) {
        // Cập nhật trạng thái phòng về lại 'trong' (Sạch sẵn sàng đón khách)
        mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
        
        // Ghi nhận mốc HOÀN TẤT và lưu kèm tên nhân viên thực hiện
        $log_details = "Hoàn tất ca dọn dẹp vệ sinh phòng. - Nhân viên: $current_user";
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
        $message = "<div class='alert success'>🔒 ĐÃ HOÀN TẤT VỆ SINH! Phòng đã chuyển sang trạng thái TRỐNG SẠCH SẼ.</div>";
        
        // Cập nhật lại biến hiển thị giao diện nhanh
        $room['status'] = 'trong';
    }
}

// KHỐI LOGIC QUÉT THỜI GIAN: Tìm xem phòng này đã bấm "BẮT ĐẦU DỌN PHÒNG" gần nhất lúc nào
$has_started_cleaning = false;
$start_time_string = "";
$seconds_elapsed = 0;

if ($room['status'] === 've_sinh') {
    // Tìm log chuyển sang vệ sinh gần nhất để làm mốc giới hạn
    $checkout_log_q = mysqli_query($conn, "SELECT event_time FROM room_logs WHERE room_id = $room_id AND (details LIKE '%vệ sinh%' OR details LIKE '%ve_sinh%') AND details NOT LIKE '%hoàn tất%' ORDER BY id DESC LIMIT 1");
    $checkout_log = mysqli_fetch_assoc($checkout_log_q);
    $checkout_time = $checkout_log['event_time'] ?? '1970-01-01 00:00:00';

    // Tìm xem sau mốc checkout đó, nhân viên nào đã bấm "BẮT ĐẦU DỌN PHÒNG" chưa
    $clean_log_q = mysqli_query($conn, "SELECT event_time, details FROM room_logs WHERE room_id = $room_id AND details LIKE 'BẮT ĐẦU DỌN PHÒNG%' AND event_time > '$checkout_time' ORDER BY id DESC LIMIT 1");
    $clean_log = mysqli_fetch_assoc($clean_log_q);

    if ($clean_log) {
        $has_started_cleaning = true;
        $start_time_string = $clean_log['event_time'];
        // Tính toán số giây đã trôi qua kể từ lúc bấm nút (Dành cho đồng hồ chạy mượt real-time)
        $seconds_elapsed = time() - strtotime($start_time_string);
        
        // Trích xuất tên nhân viên từ log cũ để hiển thị lại nếu họ có F5 hoặc thoát ra vào lại
        if (preg_match('/Nhân viên: (.*)$/', $clean_log['details'], $matches)) {
            $cleaner_name = $matches[1];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Xác nhận tác vụ buồng phòng</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; box-sizing: border-box; }
        .card { background: white; width: 100%; max-width: 400px; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        h2 { color: #1e293b; margin-top: 0; font-size: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
        .room-badge { background: #0288d1; color: white; font-size: 26px; font-weight: bold; padding: 12px 24px; border-radius: 8px; display: inline-block; margin-bottom: 12px; }
        .status-text { font-size: 14px; color: #64748b; margin-bottom: 20px; }
        
        .btn { display: block; width: 100%; padding: 16px; font-size: 16px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-decoration: none; box-sizing: border-box; }
        .btn-blue { background: #007bff; }
        .btn-blue:hover { background: #0056b3; }
        .btn-green { background: #28a745; }
        .btn-green:hover { background: #218838; }
        .btn-secondary { background: #64748b; font-size: 14px; padding: 10px; margin-top: 15px; display: block; border-radius: 8px; color: white; text-decoration: none; }
        
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 20px; text-align: left; }
        .alert.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        /* KHU VỰC THỜI GIAN ĐẾM NGƯỢC CHUYÊN NGHIỆP */
        .timer-box { background: #fff7ed; border: 1px solid #fed7aa; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #c2410c; }
        .timer-clock { font-size: 28px; font-weight: bold; font-family: monospace; margin: 5px 0; }
        .user-tag { background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-top: 5px; }
    </style>
</head>
<body>

<div class="card">
    <h2>🧹 QUẢN LÝ BUỒNG PHÒNG</h2>
    <div class="room-badge"><?php echo htmlspecialchars($room['room_name']); ?></div>
    
    <div class="status-text">
        Trạng thái: 
        <b>
        <?php 
            if($room['status'] === 've_sinh') {
                echo $has_started_cleaning ? '<span style="color:#0288d1;">⏳ ĐANG TIẾN HÀNH DỌN</span>' : '<span style="color:#ffc107;">⚠️ CHỜ VỆ SINH (CỬA KHÓA)</span>';
            } elseif($room['status'] === 'trong') {
                echo '<span style="color:#28a745;">🟢 PHÒNG TRỐNG SẴN SÀNG</span>';
            } else {
                echo '<span style="color:#dc3545;">🔴 CÓ KHÁCH ĐANG Ở</span>';
            }
        ?>
        </b>
        <br>
        <span class="user-tag">👤 Tài khoản thực hiện: <?php echo htmlspecialchars($current_user); ?></span>
    </div>

    <?php echo $message; ?>

    <form method="POST" id="actionForm">
        <?php if ($room['status'] === 've_sinh'): ?>
            
            <?php if (!$has_started_cleaning): ?>
                <button type="submit" name="start_cleaning" class="btn btn-blue" onclick="return confirm('Xác nhận mở khóa cảm biến và bắt đầu tính giờ dọn dẹp phòng?')">▶️ BẤT ĐẦU DỌN PHÒNG</button>
            <?php else: ?>
                <div class="timer-box">
                    <div style="font-size: 13px; font-weight: bold;">⏱️ THỜI GIAN ĐANG DỌN PHÒNG</div>
                    <div class="timer-clock" id="liveTimer">00:00:00</div>
                    <div style="font-size: 12px; color: #7c2d12;">Bắt đầu lúc: <?php echo date('H:i:s', strtotime($start_time_string)); ?> <br>Bởi: <b><?php echo htmlspecialchars($cleaner_name ?? $current_user); ?></b></div>
                </div>

                <button type="submit" name="finish_cleaning" class="btn btn-green" onclick="return confirm('Xác nhận đã dọn xong hoàn toàn, đóng cửa và chuyển phòng về trạng thái TRỐNG?')">🚀 ĐÃ DỌN XONG (HOÀN TẤT)</button>
            <?php endif; ?>

        <?php else: ?>
            <p style="color: #64748b; font-size: 14px; font-style: italic; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px dashed #cbd5e1;">
                Phòng này đã xử lý xong hoặc đang có khách thuê, không cần thao tác dọn dẹp vệ sinh.
            </p>
        <?php endif; ?>
    </form>

    <a href="index.php" class="btn btn-secondary">Quay lại Trang Chủ</a>
</div>

<script>
<?php if ($has_started_cleaning): ?>
    // Lấy số giây chênh lệch từ PHP nạp vào JavaScript
    let totalSeconds = <?php echo $seconds_elapsed; ?>;
    const timerElement = document.getElementById('liveTimer');

    function updateTimer() {
        totalSeconds++;
        
        let hours = Math.floor(totalSeconds / 3600);
        let minutes = Math.floor((totalSeconds % 3600) / 60);
        let seconds = totalSeconds % 60;

        // Định dạng chuỗi hiển thị 00:00:00
        let displayTime = 
            (hours < 10 ? "0" + hours : hours) + ":" + 
            (minutes < 10 ? "0" + minutes : minutes) + ":" + 
            (seconds < 10 ? "0" + seconds : seconds);

        timerElement.textContent = displayTime;
    }

    // Chạy đồng hồ lặp lại sau mỗi 1 giây
    setInterval(updateTimer, 1000);
    updateTimer(); // Chạy ngay lập tức lần đầu tiên tránh bị trễ
<?php endif; ?>
</script>

</body>
</html>
