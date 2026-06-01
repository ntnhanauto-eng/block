<?php
include 'config.php';
checkLogin(); // Bắt buộc đăng nhập để lấy thông tin tài khoản

$current_user = $_SESSION['username'] ?? 'Ẩn danh';
$message = "";

// ========================================================
// SỬA LỖI: ĐÃ FIX BIẾN $_POST CHUẨN XÁC ĐỂ LỆNH CHẠY NGAY
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_room_id = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    
    if (isset($_POST['start_cleaning']) && $action_room_id > 0) {
        $log_details = "BẮT ĐẦU DỌN PHÒNG - Nhân viên: $current_user";
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($action_room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
    }
    
    if (isset($_POST['finish_cleaning']) && $action_room_id > 0) {
        mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $action_room_id");
        $log_details = "Hoàn tất ca dọn dẹp vệ sinh phòng. - Nhân viên: $current_user";
        mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details, amount) VALUES ($action_room_id, NOW(), 'LỄ TÂN', '$log_details', 0)");
    }
    
    // Sau khi xử lý xong phải chuyển hướng tại chỗ để cập nhật giao diện ngay lập tức
    header("Location: danhsach_buongphong.php");
    exit();
}

// THUẬT TOÁN LỌC: Chỉ lấy các phòng đang ở trạng thái 've_sinh'
$rooms_q = mysqli_query($conn, "SELECT id, room_name, status FROM rooms WHERE status = 've_sinh' ORDER BY room_name ASC");
$total_waiting = mysqli_num_rows($rooms_q);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Quản Lý Buồng Phòng</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; box-sizing: border-box; }
        .container { width: 100%; max-width: 400px; } 
        
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        h1 { font-size: 16px; color: #1e293b; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; }
        .refresh-status { font-size: 11px; color: #64748b; text-align: right; margin-bottom: 15px; font-style: italic; }

        /* GIAO DIỆN THÈ PHÒNG ĐỒNG BỘ */
        .card { background: white; width: 100%; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; box-sizing: border-box; margin-bottom: 20px; border-left: 6px solid #ffc107; }
        .room-badge { background: #0288d1; color: white; font-size: 24px; font-weight: bold; padding: 10px 20px; border-radius: 8px; display: inline-block; margin-bottom: 12px; }
        .status-text { font-size: 14px; color: #64748b; margin-bottom: 15px; line-height: 1.6; }
        
        /* NÚT BẤM TO RÕ RÀNG */
        .btn { display: block; width: 100%; padding: 16px; font-size: 15px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 10px; text-decoration: none; box-sizing: border-box; }
        .btn-blue { background: #007bff; }
        .btn-green { background: #28a745; }
        
        /* NÚT XEM QR NHỎ */
        .btn-qr-toggle { background: #f1f5f9; color: #475569; padding: 6px 12px; font-size: 11px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer; margin-top: 10px; }

        /* KHUNG QR CO GIÃN */
        .qr-box { display: none; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 12px; justify-content: center; align-items: center; flex-direction: column; }
        .qr-box img { width: 130px; height: 130px; background: white; padding: 5px; border: 1px solid #e2e8f0; border-radius: 6px; }

        /* ĐỒNG HỒ ĐẾM GIỜ CHẠY NGẦM */
        .timer-box { background: #fff7ed; border: 1px solid #fed7aa; padding: 12px; border-radius: 8px; margin-bottom: 15px; color: #c2410c; }
        .timer-clock { font-size: 26px; font-weight: bold; font-family: monospace; margin: 3px 0; }
        .user-tag { background: #e2e8f0; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; }
        .empty-state { text-align: center; background: white; padding: 40px 20px; border-radius: 12px; color: #64748b; font-style: italic; box-shadow: 0 4px 12px rgba(0,0,0,0.02); width: 100%; }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header-box">
        <h1>🧹 QUẢN LÝ BUỒNG PHÒNG</h1>
        <a href="index.php" class="back-link">Trang Chủ</a>
    </div>

    <div class="refresh-status">🔄 Tự cập nhật sau <span id="countdown">10</span>s...</div>

    <?php if ($total_waiting > 0): ?>
        <?php while($r = mysqli_fetch_assoc($rooms_q)): ?>
            <?php 
                $room_id = $r['id'];
                $room_name = htmlspecialchars($r['room_name']);
                
                // Thuật toán quét chu kỳ để đồng bộ trạng thái đếm giờ
                $checkout_log_q = mysqli_query($conn, "SELECT event_time FROM room_logs WHERE room_id = $room_id AND (details LIKE '%vệ sinh%' OR details LIKE '%ve_sink%') AND details NOT LIKE '%hoàn tất%' ORDER BY id DESC LIMIT 1");
                $checkout_log = mysqli_fetch_assoc($checkout_log_q);
                $checkout_time = $checkout_log['event_time'] ?? '1970-01-01 00:00:00';

                $clean_log_q = mysqli_query($conn, "SELECT event_time, details FROM room_logs WHERE room_id = $room_id AND details LIKE 'BẮT ĐẦU DỌN PHÒNG%' AND event_time > '$checkout_time' ORDER BY id DESC LIMIT 1");
                $clean_log = mysqli_fetch_assoc($clean_log_q);

                $has_started = false;
                $seconds_elapsed = 0;
                $cleaner_name = $current_user;
                $start_time_string = "";

                if ($clean_log) {
                    $has_started = true;
                    $start_time_string = $clean_log['event_time'];
                    $seconds_elapsed = time() - strtotime($start_time_string);
                    if (preg_match('/Nhân viên: (.*)$/', $clean_log['details'], $matches)) {
                        $cleaner_name = $matches[1];
                    }
                }

                $action_url = "https://thanhnhan.site/qlks/cleaner_action.php?room_id=" . $room_id;
                $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($action_url);
            ?>
            
            <div class="card">
                <div class="room-badge"><?php echo $room_name; ?></div>
                
                <div class="status-text">
                    Trạng thái: <span style="color:#d97706; font-weight:bold;">⚠️ CHỜ VỆ SINH</span><br>
                    <span class="user-tag">👤 Tài khoản thực hiện: <?php echo htmlspecialchars($has_started ? $cleaner_name : $current_user); ?></span>
                </div>

                <form method="POST">
                    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                    
                    <?php if (!$has_started): ?>
                        <button type="submit" name="start_cleaning" class="btn btn-blue" onclick="return confirm('Xác nhận bắt đầu dọn phòng và mở khóa cảm biến cửa?')">▶️ BẤT ĐẦU DỌN PHÒNG</button>
                    <?php else: ?>
                        <div class="timer-box">
                            <div style="font-size: 12px; font-weight: bold;">⏱️ THỜI GIAN ĐANG DỌN PHÒNG</div>
                            <div class="timer-clock class-live-timer" data-seconds="<?php echo $seconds_elapsed; ?>">00:00:00</div>
                            <div style="font-size: 11px; color: #7c2d12;">Bắt đầu lúc: <?php echo date('H:i:s', strtotime($start_time_string)); ?></div>
                        </div>
                        <button type="submit" name="finish_cleaning" class="btn btn-green" onclick="return confirm('Xác nhận dọn dẹp hoàn tất, đưa phòng về trạng thái trống sạch?')">🚀 ĐÃ DỌN XONG (HOÀN TẤT)</button>
                    <?php endif; ?>
                </form>

                <button class="btn-qr-toggle" onclick="toggleQR(<?php echo $room_id; ?>)">📷 Xem mã QR phòng</button>

                <div class="qr-box" id="qr_box_<?php echo $room_id; ?>">
                    <img src="<?php echo $qr_api_url; ?>" alt="QR">
                    <div style="font-size:10px; color:#94a3b8; margin-top:5px; word-break:break-all;"><?php echo $action_url; ?></div>
                </div>
            </div>

        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">🎉 Không có phòng nào trong danh sách chờ vệ sinh!</div>
    <?php endif; ?>

</div>

<script>
// 1. CHẠY ĐỒNG HỒ ĐẾM THỜI GIAN REAL-TIME
document.addEventListener('DOMContentLoaded', () => {
    const timers = document.querySelectorAll('.class-live-timer');
    
    setInterval(() => {
        timers.forEach(timer => {
            let seconds = parseInt(timer.getAttribute('data-seconds')) + 1;
            timer.setAttribute('data-seconds', seconds);
            
            let hrs = Math.floor(seconds / 3600);
            let mins = Math.floor((seconds % 3600) / 60);
            let secs = seconds % 60;
            
            timer.textContent = 
                (hrs < 10 ? "0" + hrs : hrs) + ":" + 
                (mins < 10 ? "0" + mins : mins) + ":" + 
                (secs < 10 ? "0" + secs : secs);
        });
    }, 1000);
});

// 2. ẨN / HIỆN MÃ QR VÀ GIỮ TRẠNG THÁI KHI AUTO-RELOAD
function toggleQR(roomId) {
    const qrBox = document.getElementById('qr_box_' + roomId);
    if (qrBox.style.display === 'none' || qrBox.style.display === '') {
        qrBox.style.display = 'flex';
        localStorage.setItem('keep_qr_' + roomId, 'yes');
    } else {
        qrBox.style.display = 'none';
        localStorage.removeItem('keep_qr_' + roomId);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qr-box').forEach(box => {
        let id = box.id.replace('qr_box_', '');
        if (localStorage.getItem('keep_qr_' + id) === 'yes') {
            box.style.display = 'flex';
        }
    });
});

// 3. TỰ ĐỘNG LÀM MỚI (RELOAD) TRANG SAU MỖI 10 GIÂY
let timeLeft = 10;
const countdownElement = document.getElementById('countdown');
let refreshInterval = setInterval(() => {
    timeLeft--;
    if (countdownElement) countdownElement.textContent = timeLeft;
    if (timeLeft <= 0) {
        window.location.reload();
    }
}, 1000);

// Nếu người dùng đang tương tác bấm nút submit, tạm dừng bộ reload tránh xung đột dữ liệu
document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', () => {
        clearInterval(refreshInterval);
    });
});
</script>

</body>
</html>
