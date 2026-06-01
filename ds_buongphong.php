<?php
include 'config.php';
checkLogin(); 

// LẤY DANH SÁCH CÁC PHÒNG: Ưu tiên sắp xếp phòng Vệ Sinh lên đầu, sau đó đến phòng Trống và Có Khách
$rooms_q = mysqli_query($conn, "SELECT id, room_name, status FROM rooms ORDER BY FIELD(status, 've_sinh', 'trong', 'khach'), room_name ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Danh Sách Theo Dõi Buồng Phòng</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 15px; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; }
        
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { font-size: 18px; color: #1e293b; margin: 0; text-transform: uppercase; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; }

        /* THÔNG BÁO ĐANG TỰ ĐỘNG CẬP NHẬT */
        .refresh-status { font-size: 12px; color: #64748b; text-align: right; margin-bottom: 10px; font-style: italic; }

        /* DANH SÁCH PHÒNG THEO DẠNG THẺ DỌC */
        .room-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); text-align: center; }
        
        .room-card.status-ve_sinh { border-left: 6px solid #ffc107; }
        .room-card.status-trong { border-left: 6px solid #28a745; }
        .room-card.status-khach { border-left: 6px solid #dc3545; }

        .room-title { font-size: 22px; font-weight: bold; color: #1e293b; margin-bottom: 5px; }
        .status-text { font-size: 13px; color: #64748b; margin-bottom: 15px; }
        
        /* CÁC NÚT BẤM THAO TÁC */
        .btn { display: inline-block; width: 100%; padding: 12px; font-size: 14px; font-weight: bold; color: white; border: none; border-radius: 8px; cursor: pointer; margin-bottom: 8px; text-decoration: none; box-sizing: border-box; }
        .btn-orange { background: #ea580c; } 
        .btn-blue { background: #007bff; }   
        
        /* KHUNG CHỨA MÃ QR ẨN / HIỆN */
        .qr-container { display: none; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 10px; justify-content: center; align-items: center; flex-direction: column; }
        .qr-container.show { display: flex; } /* Class hỗ trợ giữ trạng thái hiển thị sau khi reload */
        .qr-container img { width: 140px; height: 140px; background: white; padding: 5px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .qr-url { font-size: 11px; color: #94a3b8; margin-top: 8px; word-break: break-all; }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header-box">
        <h1>🧹 Danh Sách Buồng Phòng</h1>
        <a href="index.php" class="back-link">← Trang Chủ</a>
    </div>

    <div class="refresh-status">🔄 Hệ thống tự động cập nhật sau <span id="countdown">10</span>s...</div>

    <?php if (mysqli_num_rows($rooms_q) > 0): ?>
        <?php while($r = mysqli_fetch_assoc($rooms_q)): ?>
            <?php 
                $room_id = $r['id'];
                $room_name = htmlspecialchars($r['room_name']);
                $status = $r['status'];
                
                $action_url = "https://thanhnhan.site/qlks/cleaner_action.php?room_id=" . $room_id;
                $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($action_url);
            ?>
            
            <div class="room-card status-<?php echo $status; ?>">
                <div class="room-title"><?php echo $room_name; ?></div>
                
                <div class="status-text">
                    Trạng thái: 
                    <b>
                    <?php 
                        if($status === 've_sinh') echo '<span style="color:#d97706;">⚠️ CHỜ VỆ SINH</span>';
                        elseif($status === 'trong') echo '<span style="color:#28a745;">🟢 PHÒNG TRỐNG SẠCH SẼ</span>';
                        else echo '<span style="color:#dc3545;">🔴 CÓ KHÁCH Ở</span>';
                    ?>
                    </b>
                </div>

                <button class="btn btn-orange toggle-qr-btn" data-room-id="<?php echo $room_id; ?>" onclick="toggleQR(<?php echo $room_id; ?>)">
                     HIỂN THỊ MÃ QR PHÒNG
                </button>

                <div class="qr-container" id="qr_box_<?php echo $room_id; ?>">
                    <img src="<?php echo $qr_api_url; ?>" alt="QR <?php echo $room_name; ?>">
                    <div class="qr-url">Link quét: <?php echo $action_url; ?></div>
                </div>

                <a href="<?php echo $action_url; ?>" class="btn btn-blue" style="margin-top: 8px;">👉 Vào Trang Tiến Độ Dọn Phòng</a>
            </div>

        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center; color:#94a3b8; font-style:italic;">Hệ thống chưa cấu hình phòng ngủ nào.</p>
    <?php endif; ?>

</div>

<script>
// ========================================================
// 1. XỬ LÝ ẨN/HIỆN MÃ QR VÀ LƯU TRẠNG THÁI VÀO LOCALSTORAGE
// ========================================================
function toggleQR(roomId) {
    const qrBox = document.getElementById('qr_box_' + roomId);
    
    if (qrBox.style.display === 'none' || qrBox.style.display === '') {
        qrBox.style.display = 'flex';
        localStorage.setItem('qr_status_' + roomId, 'open'); // Lưu lại trạng thái ĐANG MỞ
    } else {
        qrBox.style.display = 'none';
        localStorage.removeItem('qr_status_' + roomId); // Xóa bộ nhớ nếu đóng lại
    }
}

// Khi trang vừa làm mới xong, quét kiểm tra xem trước đó Admin đang mở mã QR nào để khôi phục lại
window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.qr-container').forEach(box => {
        let id = box.id.replace('qr_box_', '');
        if (localStorage.getItem('qr_status_' + id) === 'open') {
            box.style.display = 'flex';
        }
    });
});

// ========================================================
// 2. BỘ ĐẾM THỜI GIAN ĐẾM NGƯỢC 10 GIÂY VÀ TỰ ĐỘNG TẢI LẠI TRANG
// ========================================================
let timeLeft = 10;
const countdownElement = document.getElementById('countdown');

setInterval(() => {
    timeLeft--;
    if (countdownElement) {
        countdownElement.textContent = timeLeft;
    }
    
    if (timeLeft <= 0) {
        window.location.reload(); // Đủ 10 giây tự động tải lại dữ liệu mới nhất
    }
}, 1000);
</script>

</body>
</html>
