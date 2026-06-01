<?php
include 'config.php';
checkLogin(); // Bắt buộc đăng nhập

date_default_timezone_set('Asia/Ho_Chi_Minh');

$message = "";
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$current_user = $_SESSION['username'] ?? 'Ẩn danh';

// 1. Lấy thông tin trạng thái phòng hiện tại
$room_q = mysqli_query($conn, "SELECT room_name, status FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_q);

if (!$room) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ Lỗi: Không tìm thấy số phòng này!</h2>");
}

// 2. THUẬT TOÁN KIỂM TRA: Xem log gần nhất của phòng này là BẮT ĐẦU hay HOÀN TẤT
$last_action_q = mysqli_query($conn, "
    SELECT details, event_time,
           (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(event_time)) as seconds_passed
    FROM room_logs 
    WHERE room_id = $room_id 
      AND (details LIKE 'BẮT ĐẦU DỌN PHÒNG%' OR details LIKE 'Hoàn tất ca dọn dẹp%')
    ORDER BY id DESC LIMIT 1
");
$last_action = mysqli_fetch_assoc($last_action_q);

$has_started_cleaning = false;
$seconds_elapsed = 0;
$start_time_string = "";
$cleaner_name = $current_user;

// Nếu log gần nhất là "BẮT ĐẦU DỌN PHÒNG" -> Chứng tỏ phòng này đang trong tiến trình dọn dẹp
if ($last_action && strpos($last_action['details'], 'BẮT ĐẦU DỌN PHÒNG') !== false && $room['status'] === 've_sinh') {
    $has_started_cleaning = true;
    $start_time_string = $last_action['event_time'];
    $seconds_elapsed = (int)$last_action['seconds_passed'] < 0 ? 0 : (int)$last_action['seconds_passed'];
    
    if (preg_match('/Nhân viên: (.*)$/', $last_action['details'], $matches)) {
        $cleaner_name = $matches[1];
    }
}

// 3. Xử lý khi nhân viên nhấn nút gửi Form gửi lên
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_cleaning'])) {
        if (!$has_started_cleaning) {
            $log_details = "BẮT ĐẦU DỌN PHÒNG - Nhân viên: $current_user";
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
        }
        header("Location: cleaner_action.php?room_id=" . $room_id);
        exit();
    }
    
    if (isset($_POST['finish_cleaning'])) {
        mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
        $log_details = "Hoàn tất ca dọn dẹp vệ sinh phòng. - Nhân viên: $current_user";
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
        
        header("Location: cleaner_action.php?room_id=" . $room_id);
        exit();
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
        .card { background: white; width: 100%; max-width: 400px; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; position: relative; }
        h2 { color: #1e293b; margin-top: 0; font-size: 20px; text-transform: uppercase; }
        .room-badge { background: #0288d1; color: white; font-size: 26px; font-weight: bold; padding: 12px 24px; border-radius: 8px; display: inline-block; margin-bottom: 12px; }
        .status-text { font-size: 14px; color: #64748b; margin-bottom: 20px; }
        .btn { display: block; width: 100%; padding: 16px; font-size: 16px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-decoration: none; box-sizing: border-box; }
        .btn-blue { background: #007bff; }
        .btn-green { background: #28a745; }
        .btn-secondary { background: #64748b; font-size: 14px; padding: 10px; margin-top: 15px; display: block; border-radius: 8px; color: white; text-decoration: none; }
        .timer-box { background: #fff7ed; border: 1px solid #fed7aa; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #c2410c; }
        .timer-clock { font-size: 28px; font-weight: bold; font-family: monospace; margin: 5px 0; }
        .user-tag { background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-top: 5px; }
        .sync-text { font-size: 11px; color: #94a3b8; text-align: right; margin-bottom: 10px; font-style: italic; }
        
        /* Style thông báo động theo trạng thái */
        .alert-box { font-size: 15px; font-weight: bold; padding: 18px; border-radius: 8px; margin: 15px 0; line-height: 1.5; }
        .alert-khach { color: #c2410c; background: #fff7ed; border: 1px solid #fdba74; }
        .alert-trong { color: #15803d; background: #dcfce7; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>

<div class="card">
    <h2>🧹 QUẢN LÝ BUỒNG PHÒNG</h2>
    <div class="room-badge"><?php echo htmlspecialchars($room['room_name']); ?></div>
    
    <div class="sync-text">🔄 Đồng bộ hệ thống sau <span id="sync_countdown">10</span>s...</div>

    <div class="status-text">
        Trạng thái thực tế: 
        <b>
        <?php 
            if ($room['status'] === 've_sinh') {
                echo $has_started_cleaning ? '<span style="color:#0288d1;">⏳ ĐANG TIẾN HÀNH DỌN</span>' : '<span style="color:#ffc107;">⚠️ CHỜ VỆ SINH</span>';
            } else if ($room['status'] === 'khach') {
                echo '<span style="color:#dc3545;">🔴 PHÒNG ĐANG CÓ KHÁCH Ở</span>';
            } else {
                echo '<span style="color:#28a745;">🟢 PHÒNG TRỐNG SẴN SÀNG</span>';
            }
        ?>
        </b>
        <br>
        <span class="user-tag">👤 Tài khoản kiểm tra: <?php echo htmlspecialchars($current_user); ?></span>
    </div>

    <form method="POST">
        <?php if ($room['status'] === 've_sinh'): ?>
            <?php if (!$has_started_cleaning): ?>
                <button type="submit" name="start_cleaning" class="btn btn-blue" onclick="return confirm('Xác nhận bắt đầu dọn phòng này?')">▶️ BẤT ĐẦU DỌN PHÒNG</button>
            <?php else: ?>
                <div class="timer-box">
                    <div style="font-size: 13px; font-weight: bold;">⏱️ THỜI GIAN ĐANG DỌN PHÒNG</div>
                    <div class="timer-clock" id="liveTimer">00:00:00</div>
                    <div style="font-size: 12px; color: #7c2d12;">Bắt đầu lúc: <?php echo date('H:i:s', strtotime($start_time_string)); ?> <br>Bởi: <b><?php echo htmlspecialchars($cleaner_name); ?></b></div>
                </div>
                <button type="submit" name="finish_cleaning" class="btn btn-green" onclick="return confirm('Xác nhận đã dọn dẹp xong hoàn tất?')">🚀 ĐÃ DỌN XONG (HOÀN TẤT)</button>
            <?php endif; ?>

        <?php elseif ($room['status'] === 'khach'): ?>
            <div class="alert-box alert-khach">
                🛑 Không thể can thiệp nghiệp vụ!<br>
                Hiện tại phòng này đang có khách lưu trú sinh hoạt. Khóa chức năng dọn dẹp hệ thống.
            </div>

        <?php else: ?>
            <div class="alert-box alert-trong">
                🎉 Phòng này đã được xác nhận dọn dẹp hoàn tất sạch sẽ, sẵn sàng đón lượt khách mới!
            </div>
        <?php endif; ?>
    </form>

    <a href="danhsach_buongphong.php" class="btn btn-secondary">Xem tất cả phòng chờ</a>
</div>

<script>
<?php if ($has_started_cleaning): ?>
    let totalSeconds = <?php echo $seconds_elapsed; ?>;
    const timerElement = document.getElementById('liveTimer');
    function updateTimer() {
        totalSeconds++;
        let hours = Math.floor(totalSeconds / 3600);
        let minutes = Math.floor((totalSeconds % 3600) / 60);
        let seconds = totalSeconds % 60;
        if(timerElement) {
            timerElement.textContent = (hours < 10 ? "0" + hours : hours) + ":" + (minutes < 10 ? "0" + minutes : minutes) + ":" + (seconds < 10 ? "0" + seconds : seconds);
        }
    }
    setInterval(updateTimer, 1000);
    updateTimer();
<?php endif; ?>

let timeLeft = 10;
const syncCountdown = document.getElementById('sync_countdown');
let refreshInterval = setInterval(() => {
    timeLeft--;
    if (syncCountdown) syncCountdown.textContent = timeLeft;
    if (timeLeft <= 0) {
        window.location.reload(); 
    }
}, 1000);

document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', () => {
        clearInterval(refreshInterval);
    });
});
</script>
</body>
</html>
