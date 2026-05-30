<?php
include 'config.php'; // Kết nối Database và kiểm tra session nếu cần

// ĐỊNH NGHĨA MÃ PIN BẢO MẬT
define('CLEANING_PIN', '1234');

// Sử dụng Session tạm thời để truyền thông báo sau khi chuyển hướng trang
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
    
    // Chuyển hướng trang về chính nó để xóa sạch dữ liệu form cũ
    header("Location: danhsach_buongphong.php");
    exit();
}

// TRUY VẤN TẤT CẢ CÁC PHÒNG ĐANG Ở TRẠNG THÁI 've_sinh'
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
        
        .action-form { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; align-items: center; }
        .pin-box { flex: 1; min-width: 100px; padding: 10px; font-size: 16px; text-align: center; border-radius: 6px; border: 2px solid #ccc; font-weight: bold; box-sizing: border-box; }
        .pin-box:focus { border-color: #28a745; outline: none; }
        
        .btn-done { background: #28a745; color: white; border: none; padding: 10px 15px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .btn-done:active { transform: scale(0.98); }
        
        /* Nút xem mã QR */
        .btn-qr { background: #007bff; color: white; border: none; padding: 10px 15px; font-size: 15px; font-weight: bold; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px; transition: background 0.2s; }
        .btn-qr:hover { background: #0056b3; }
        
        .empty-state { background: white; padding: 40px; text-align: center; border-radius: 8px; color: #666; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .empty-icon { font-size: 48px; margin-bottom: 10px; }
        
        .refresh-notice { text-align: center; font-size: 12px; color: #888; margin-top: 20px; font-style: italic; }

        /* CSS CẤU HÌNH HỘP THOẠI POPUP MODAL QR CODE */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal-content { background-color: #fff; padding: 25px; border-radius: 12px; text-align: center; box-shadow: 0 5px 25px rgba(0,0,0,0.3); max-width: 320px; width: 100%; position: relative; animation: zoomIn 0.3s ease; }
        @keyframes zoomIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .modal-close { position: absolute; top: 10px; right: 15px; font-size: 24px; font-weight: bold; color: #aaa; cursor: pointer; }
        .modal-close:hover { color: #333; }
        .modal h3 { margin-top: 0; color: #2c3e50; font-size: 18px; margin-bottom: 15px; }
        #qrcode { display: inline-block; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
        .qr-url { font-size: 11px; color: #666; word-break: break-all; margin-top: 8px; background: #f8f9fa; padding: 6px; border-radius: 4px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        // Tự động reload danh sách phòng sau mỗi 10 giây nếu không gõ mã PIN
        let autoRefresh = setInterval(function(){
            if(document.activeElement.tagName !== 'INPUT') {
                window.location.reload();
            }
        }, 10000);

        // Hàm xử lý mở và tạo mã QR động
        function showQRCode(roomId, roomName) {
            // Tạm dừng auto reload để nhân viên kịp quét mã không bị mất hình
            clearInterval(autoRefresh);

            let qrContainer = document.getElementById("qrcode");
            qrContainer.innerHTML = ""; // Xóa mã cũ trước đó

            let targetUrl = "https://thanhnhan.site/qlks/nhanvien_clean.php?room_id=" + roomId;
            document.getElementById("modal-room-title").innerText = "MÃ QR " + roomName;
            document.getElementById("modal-qr-link").innerText = targetUrl;

            // Khởi tạo QR Code vào container
            new QRCode(qrContainer, {
                text: targetUrl,
                width: 200,
                height: 200,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });

            // Hiển thị khung popup
            document.getElementById("qrModal").style.display = "flex";
        }

        // Hàm đóng hộp thoại QR
        function closeModal() {
            document.getElementById("qrModal").style.display = "none";
            // Kích hoạt lại bộ đếm thời gian reload tự động
            autoRefresh = setInterval(function(){
                if(document.activeElement.tagName !== 'INPUT') {
                    window.location.reload();
                }
            }, 10000);
        }

        // Đóng modal khi bấm ra ngoài vùng trắng nền đen
        window.onclick = function(event) {
            let modal = document.getElementById("qrModal");
            if (event.target == modal) {
                closeModal();
            }
        }
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
                        <span>🏨 <?php echo htmlspecialchars($room['room_name']); ?></span>
                        <span class="status-badge">ĐANG CHỜ DỌN</span>
                    </div>
                    
                    <form method="POST" class="action-form" autocomplete="off">
                        <input type="hidden" name="action" value="finish_clean">
                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                        <input type="hidden" name="room_name" value="<?php echo htmlspecialchars($room['room_name']); ?>">
                        
                        <button type="button" class="btn-qr" onclick="showQRCode(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['room_name']); ?>')">
                            📷 Xem QR
                        </button>

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

<div id="qrModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="closeModal()">&times;</span>
        <h3 id="modal-room-title">MÃ QR PHÒNG</h3>
        <div id="qrcode"></div>
        <div class="qr-url" id="modal-qr-link"></div>
        <p style="font-size: 12px; margin: 10px 0 0 0; color: #e74c3c; font-weight: bold;">Quét mã để truy cập trang báo cáo dọn dẹp</p>
    </div>
</div>

</body>
</html>
