<?php
include 'config.php';
checkLogin(); // Bắt buộc đăng nhập

// Xử lý cập nhật trạng thái phòng bằng AJAX gửi lên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = (int)$_POST['room_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    mysqli_query($conn, "UPDATE rooms SET status = '$status' WHERE id = $room_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ thống Quản lý Khách sạn Real-time</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 30px; background: #eef2f3; }
        .header { background: #333; color: white; padding: 15px 20px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: #ffc107; text-decoration: none; font-weight: bold; }
        .grid-container { display: flex; gap: 20px; margin-top: 20px; }
        .room-card { background: white; padding: 20px; border-radius: 8px; border-top: 5px solid #007bff; box-shadow: 0 2px 8px rgba(0,0,0,0.05); width: 250px; text-align: center; }
        .room-card select { width: 100%; padding: 8px; margin-top: 10px; border-radius: 4px; }
        .log-section { background: white; padding: 20px; border-radius: 8px; margin-top: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f8f9fa; }
        .alert-red { background-color: #f8d7da !important; color: #721c24; font-weight: bold; }
        .nav-btn { display: inline-block; background: red; color: white !important; padding: 10px 15px; border-radius: 4px; margin-top: 15px; }
    </style>
</head>
<body>

    <div class="header">
        <h2>SMART HOTEL DASHBOARD (Chế độ Real-time)</h2>
        <div>Xin chào: <b><?php echo $_SESSION['username']; ?></b> (<?php echo strtoupper($_SESSION['role']); ?>) | <a href="logout.php">Đăng xuất</a></div>
    </div>

    <div class="grid-container" id="rooms-display">Đang đồng bộ dữ liệu phòng...</div>

    <div class="log-section">
        <h3>Lịch sử mở cửa gần đây (Tự động cập nhật không cần F5)</h3>
        <table>
            <thead>
                <tr>
                    <th>Thời gian</th>
                    <th>Tên Phòng</th>
                    <th>Đánh giá hệ thống</th>
                    <th>Chi tiết sự kiện</th>
                </tr>
            </thead>
            <tbody id="logs-display">
                <tr><td colspan="4">Đang đồng bộ lịch sử hành lang...</td></tr>
            </tbody>
        </table>

        <?php if (isAdmin()): ?>
            <a href="admin_logs.php" class="nav-btn">⚠️ TRANG QUẢN TRỊ CAO CẤP (XEM LỊCH SỬ ĐẦY ĐỦ)</a>
        <?php endif; ?>
    </div>

    <script>
    // Hàm thực hiện lấy dữ liệu Real-time liên tục từ Server
    function loadRealTimeData() {
        fetch('api_get_status.php')
            .then(res => res.json())
            .then(data => {
                // 1. Vẽ giao diện 3 phòng ngủ
                let roomHtml = '';
                data.rooms.forEach(room => {
                    roomHtml += `
                        <div class="room-card">
                            <h3>${room.room_name}</h3>
                            <p>Cấu hình: <span style="color:green; font-weight:bold;">${room.status.toUpperCase()}</span></p>
                            <select onchange="updateRoomStatus(${room.id}, this.value)">
                                <option value="trong" ${room.status=='trong'?'selected':''}>Phòng Trống</option>
                                <option value="khach" ${room.status=='khach'?'selected':''}>Có Khách Ở</option>
                                <option value="ve_sinh" ${room.status=='ve_sinh'?'selected':''}>Đang Vệ Sinh</option>
                            </select>
                        </div>
                    `;
                });
                document.getElementById('rooms-display').innerHTML = roomHtml;

                // 2. Vẽ bảng lịch sử mở cửa nhanh
                let logHtml = '';
                data.logs.forEach(log => {
                    let isDanger = log.event_type === 'BẤT THƯỜNG' ? 'class="alert-red"' : '';
                    logHtml += `
                        <tr ${isDanger}>
                            <td>${log.event_time}</td>
                            <td>${log.room_name}</td>
                            <td>${log.event_type}</td>
                            <td>${log.details}</td>
                        </tr>
                    `;
                });
                document.getElementById('logs-display').innerHTML = logHtml;
            })
            .catch(err => console.log("Lỗi đồng bộ dữ liệu: ", err));
    }

    // Hàm đổi trạng thái phòng lập tức không bị giật trang
    function updateRoomStatus(roomId, newStatus) {
        let formData = new FormData();
        formData.append('action', 'update_room');
        formData.append('room_id', roomId);
        formData.append('status', newStatus);

        fetch('index.php', { method: 'POST', body: formData })
            .then(() => loadRealTimeData()); // Gọi nạp lại dữ liệu ngay lập tức
    }

    // Thiết lập vòng lặp chạy ngầm tự động gọi sau mỗi 3 giây
    setInterval(loadRealTimeData, 3000);
    loadRealTimeData(); // Gọi chạy ngay lần đầu tiên mở trình duyệt
    </script>
</body>
</html>
