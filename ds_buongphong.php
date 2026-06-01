<?php
include 'config.php';
checkLogin(); 

// THUẬT TOÁN LỌC: Chỉ lấy các phòng đang ở trạng thái 've_sinh'
$rooms_q = mysqli_query($conn, "SELECT id, room_name, status FROM rooms WHERE status = 've_sinh' ORDER BY room_name ASC");
$total_waiting = mysqli_num_rows($rooms_q);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Danh Sách Phòng Chờ Vệ Sinh</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; box-sizing: border-box; }
        .container { width: 100%; max-width: 400px; } /* Căn giữa một cột chuẩn Mobile giống cleaner_action */
        
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        h1 { font-size: 16px; color: #1e293b; margin: 0; text-transform: uppercase; letter-spacing: 0.5px; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; }

        .refresh-status { font-size: 11px; color: #64748b; text-align: right; margin-bottom: 15px; font-style: italic; }

        /* GIAO DIỆN THẺ PHÒNG GIỐNG 100% CLEANER_ACTION */
        .card { background: white; width: 100%; padding: 25px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; box-sizing: border-box; margin-bottom: 20px; border-left: 6px solid #ffc107; }
        .room-badge { background: #0288d1; color: white; font-size: 24px; font-weight: bold; padding: 10px 20px; border-radius: 8px; display: inline-block; margin-bottom: 12px; }
        .status-text { font-size: 14px; color: #d97706; font-weight: bold; margin-bottom: 20px; }
        
        /* CÁC NÚT BẤM THAO TÁC TRỰC QUAN */
        .btn { display: block; width: 100%; padding: 14px; font-size: 15px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 10px; text-decoration: none; box-sizing: border-box; }
        .btn-blue { background: #007bff; }
        .btn-blue:hover { background: #0056b3; }
        
        /* NÚT BẬT QR NHỎ GỌN PHÍA DƯỚI */
        .btn-qr-toggle { background: #e2e8f0; color: #475569; padding: 8px 12px; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; margin-top: 5px; }
        .btn-qr-toggle:hover { background: #cbd5e1; }

        /* KHUNG CHỨA MÃ QR (MẶC ĐỊNH ẨN) */
        .qr-box { display: none; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 15px; justify-content: center; align-items: center; flex-direction: column; }
        .qr-box img { width: 140px; height: 140px; background: white; padding: 5px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .qr-url { font-size: 11px; color: #94a3b8; margin-top: 8px; word-break: break-all; }

        .empty-state { text-align: center; background: white; padding: 40px 20px; border-radius: 12px; color: #64748b; font-style: italic; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header-box">
        <h1>🧹 Danh Sách Chờ Dọn (<?php echo $total_waiting; ?>)</h1>
        <a href="index.php" class="back-link">← Quay lại</a>
    </div>

    <div class="refresh-status">🔄 Tự cập nhật phòng mới sau <span id="countdown">10</span>s...</div>

    <?php if ($total_waiting > 0): ?>
        <?php while($r = mysqli_fetch_assoc($rooms_q)): ?>
            <?php 
                $room_id = $r['id'];
                $room_name = htmlspecialchars($r['room_name']);
                
                // Tạo link điều khiển và gọi API QR Code
                $action_url = "https://thanhnhan.site/qlks/cleaner_action.php?room_id=" . $room_id;
                $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($action_url);
            ?>
            
            <div class="card">
                <div class="room-badge"><?php echo $room_name; ?></div>
                <div class="status-text">⚠️ TRẠNG THÁI: CHỜ VỆ SINH</div>

                <a href="<?php echo $action_url; ?>" class="btn btn-blue">👉 VÀO TRANG TIẾN ĐỘ DỌN</a>
                
                <button class="btn-qr-toggle" onclick="toggleQR(<?php echo $room_id; ?>)">
                    📷 Hiện mã QR phòng
                </button>

                <div class="qr-box" id="qr_box_<?php echo $room_id; ?>">
                    <img src="<?php echo $qr_api_url; ?>" alt="QR <?php echo $room_name; ?>">
                    <div class="qr-url">Link quét: <?php echo $action_url; ?></div>
                </div>
            </div>

        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            🎉 Hiện tại tất cả các phòng đều sạch sẽ. Không có phòng nào chờ dọn dẹp!
        </div>
    <?php endif; ?>

</div>

<script>
// 1. XỬ LÝ ẨN/HIỆN MÃ QR VÀ LƯU TRẠNG THÁI TRÁNH BỊ ĐÓNG KHI AUTO-RELOAD
function toggleQR(roomId) {
    const qrBox = document.getElementById('qr_box_' + roomId);
    if (qrBox.style.display === 'none' || qrBox.style.display === '') {
        qrBox.style.display = 'flex';
        localStorage.setItem('clean_qr_' + roomId, 'active');
    } else {
        qrBox.style.display = 'none';
        localStorage.removeItem('clean_qr_' + roomId);
    }
}

// Khôi phục lại hộp QR đang mở sau khi trang Reload
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qr-box').forEach(box => {
        let id = box.id.replace('qr_box_', '');
        if (localStorage.getItem('clean_qr_' + id) === 'active') {
            box.style.display = 'flex';
        }
    });
});

// 2. BỘ ĐẾM TỰ ĐỘNG LÀM MỚI (REFRESH) MỖI 10 GIÂY
let timeLeft = 10;
const countdownElement = document.getElementById('countdown');

setInterval(() => {
    timeLeft--;
    if (countdownElement) countdownElement.textContent = timeLeft;
    if (timeLeft <= 0) {
        window.location.reload();
    }
}, 1000);
</script>

</body>
</html>
