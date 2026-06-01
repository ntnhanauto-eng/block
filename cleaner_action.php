<?php
include 'config.php';
checkLogin(); 

$message = "";
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$current_user = $_SESSION['username'] ?? 'Ẩn danh';

// Lấy thông tin trạng thái phòng hiện tại
$room_q = mysqli_query($conn, "SELECT room_name, status FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_q);

if (!$room) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>❌ Lỗi: Không tìm thấy số phòng này!</h2>");
}

// Tìm mốc checkout chu kỳ gần nhất
$checkout_log_q = mysqli_query($conn, "SELECT event_time FROM room_logs WHERE room_id = $room_id AND (details LIKE '%vệ sinh%' OR details LIKE '%ve_sinh%') AND details NOT LIKE '%hoàn tất%' ORDER BY id DESC LIMIT 1");
$checkout_log = mysqli_fetch_assoc($checkout_log_q);
$checkout_time = $checkout_log['event_time'] ?? '1970-01-01 00:00:00';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['start_cleaning'])) {
        $check_exist = mysqli_query($conn, "SELECT id FROM room_logs WHERE room_id = $room_id AND details LIKE 'BẮT ĐẦU DỌN PHÒNG%' AND event_time > '$checkout_time' LIMIT 1");
        if (mysqli_num_rows($check_exist) == 0) {
            $log_details = "BẮT ĐẦU DỌN PHÒNG - Nhân viên: $current_user";
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
        }
        $room['status'] = 've_sinh'; 
    }
    
    if (isset($_POST['finish_cleaning'])) {
        mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
        $log_details = "Hoàn tất ca dọn dẹp vệ sinh phòng. - Nhân viên: $current_user";
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
        $message = "<div class='alert success'>🔒 ĐÃ HOÀN TẤT VỆ SINH! Phòng đã chuyển sang trạng thái TRỐNG SẠCH SẼ.</div>";
        $room['status'] = 'trong';
    }
}

// ĐỒNG BỘ TRẠNG THÁI TIẾN ĐỘ VÀ ĐỒNG HỒ ĐẾM GIỜ
$has_started_cleaning = false;
$start_time_string = "";
$seconds_elapsed = 0;
$cleaner_name = $current_user;

if ($room['status'] === 've_sinh') {
    $clean_log_q = mysqli_query($conn, "
        SELECT event_time, details,
        (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(event_time)) as seconds_passed
        FROM room_logs 
        WHERE room_id = $room_id 
          AND details LIKE 'BẮT ĐẦU DỌN PHÒNG%' 
          AND event_time > '$checkout_time' 
        ORDER BY id DESC LIMIT 1
    ");
    $clean_log = mysqli_fetch_assoc($clean_log_q);

    if ($clean_log && (int)$clean_log['seconds_passed'] >= 0) {
        $has_started_cleaning = true;
        $start_time_string = $clean_log['event_time'];
        $seconds_elapsed = (int)$clean_log['seconds_passed'];
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
        .card { background: white; width: 100%; max-width: 400px; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; position: relative; }
        h2 { color: #1e293b; margin-top: 0; font-size: 20px; text-transform: uppercase; }
        .room-badge { background: #0288d1; color: white; font-size: 26px; font-weight: bold; padding: 12px 24px; border-radius: 8px; display: inline-block; margin-bottom: 12px; }
        .status-text { font-size: 14px; color: #64748b; margin-bottom: 20px; }
        .btn { display: block; width: 100%; padding: 16px; font-size: 16px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-decoration: none; box-sizing: border-box; }
        .btn-blue { background: #007bff; }
        .btn-green { background: #28a745; }
        .btn-secondary { background: #64748b; font-size: 14px; padding: 10px; margin-top: 15px; display: block; border-radius: 8px; color: white; text-decoration: none; }
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 20px; text-align: left; }
        .alert.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .timer-box { background: #fff7ed; border: 1px solid #fed7aa; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #c2410c; }
        .timer-clock { font-size: 28px; font-weight: bold; font-family: monospace; margin: 5px 0; }
        .user-tag { background: #e2e8f0; color: #475569; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; margin-top: 5px; }
        .sync-text { font-size: 11px; color: #94a3b8; text-align: right; margin-bottom: 10px; font-style: italic; }
    </style>
</head>
<body>

<div class="card">
    <h2>🧹 QUẢN LÝ BUỒNG PHÒNG</h2>
    <div class="room-badge"><?php echo htmlspecialchars($room['room_name']); ?></div>
    
    <div class="sync-text">🔄 Đồng bộ hệ thống sau <span id="sync_countdown">10</span>s...</div>

    <div class="status-text">
        Trạng thái: 
        <b>
        <?php 
            if($room['status'] === 've_sinh') {
                echo $has_started_cleaning ? '<span style="color:#0288d1;">⏳ ĐANG TIẾN HÀNH DỌN</span>' : '<span style="color:#ffc107;">⚠️ CHỜ VỆ SINH</span>';
            } else {
                echo '<span style="color:#28a745;">🟢 PHÒNG TRỐNG SẴN SÀNG</span>';
            }
        ?>
        </b>
        <br>
        <span class="user-tag">👤 Tài khoản thực hiện: <?php echo htmlspecialchars($cleaner_name); ?></span>
    </div>

    <?php echo $message; ?>

    <form method="POST">
        <?php if ($room['status'] === 've_sinh'): ?>
            <?php if (!$has_started_cleaning): ?>
                <button type="submit" name="start_cleaning" class="btn btn-blue">▶️ BẤT ĐẦU DỌN PHÒNG</button>
            <?php else: ?>
                <div class="timer-box">
                    <div style="font-size: 13px; font-weight: bold;">⏱️ THỜI GIAN ĐANG DỌN PHÒNG</div>
                    <div class="timer-clock" id="liveTimer">00:00:00</div>
                    <div style="font-size: 12px; color: #7c2d12;">Bắt đầu lúc: <?php echo date('H:i:s', strtotime($start_time_string)); ?> <br>Bởi: <b><?php echo htmlspecialchars($cleaner_name); ?></b></div>
                </div>
                <button type="submit" name="finish_cleaning" class="btn btn-green">🚀 ĐÃ DỌN XONG (HOÀN TẤT)</button>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #15803d; font-size: 15px; font-weight: bold; background: #dcfce7; padding: 15px; border-radius: 8px;">🎉 Phòng này đã được xác nhận dọn dẹp hoàn tất sạch sẽ!</p>
        <?php endif; ?>
    </form>

    <a href="danhsach_buongphong.php" class="btn btn-secondary">Xem tất cả phòng chờ</a>
</div>

<script>
// 1. ĐỒNG HỒ ĐẾM TIẾN ĐỘ THỜI GIAN THỰC (CHẠY LIÊN TỤC 1s)
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

// 2. 🔥 BỘ TỰ ĐỘNG LÀM MỚI (RELOAD) MỖI 10 GIÂY ĐỂ ĐỒNG BỘ TRẠNG THÁI LẬP TỨC
let timeLeft = 10;
const syncCountdown = document.getElementById('sync_countdown');
let refreshInterval = setInterval(() => {
    timeLeft--;
    if (syncCountdown) syncCountdown.textContent = timeLeft;
    if (timeLeft <= 0) {
        window.location.reload(); // Reload để đồng bộ với Database xem Lễ tân đã bấm Hoàn tất chưa
    }
}, 1000);

// Nếu nhân viên đang nhấn submit gửi form dọn phòng, tạm hoãn reload tránh lỗi trùng dữ liệu
document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', () => {
        clearInterval(refreshInterval);
    });
});
</script>
</body>
</html>
