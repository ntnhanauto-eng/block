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
        
        /* Đã sửa flex-wrap để khi thêm nhiều phòng (102, 103...) các card tự động hạ dòng gọn gàng */
        .grid-container { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
        
        /* CSS gốc cho card phòng - bỏ thuộc tính background tĩnh để nhận màu động từ JS */
        .room-card { padding: 20px; border-radius: 8px; border-top: 5px solid #007bff; box-shadow: 0 4px 10px rgba(0,0,0,0.08); width: 250px; text-align: center; transition: all 0.3s ease; }
        .room-card select { width: 100%; padding: 8px; margin-top: 10px; border-radius: 4px; border: 1px solid #ccc; background: white; }
        
        .log-section { background: white; padding: 20px; border-radius: 8px; margin-top: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f8f9fa; }
        .alert-red { background-color: #f8d7da !important; color: #721c24; font-weight: bold; }
        .nav-btn { display: inline-block; background: red; color: white !important; padding: 10px 15px; border-radius: 4px; margin-top: 15px; text-decoration: none; font-weight: bold; }
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
    // Biến toàn cục đóng vai trò bộ nhớ đệm để kiểm tra thay đổi dữ liệu
    let lastLogId = 0;
    let lastRoomsState = "";

    // Hàm thực hiện lấy dữ liệu Real-time liên tục từ Server
    function loadRealTimeData() {
        fetch('api_get_status.php')
            .then(res => {
                if (res.status === 401) {
                    window.location.href = 'login.php';
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data) return;

                // KIỂM TRA THAY ĐỔI 1: Chỉ cập nhật giao diện phòng khi có sự thay đổi trạng thái
                let currentRoomsState = JSON.stringify(data.rooms);
                if (currentRoomsState !== lastRoomsState) {
                    lastRoomsState = currentRoomsState;

                    // 1. Vẽ giao diện danh sách phòng ngủ kèm màu nền động theo yêu cầu
                    let roomHtml = '';
                    data.rooms.forEach(room => {
                        const statusMap = {
                            'trong': 'Phòng Trống',
                            'khach': 'Có Khách Ở',
                            've_sinh': 'Đang Vệ Sinh'
                        };
                        let displayName = statusMap[room.status] || room.status.toUpperCase();

                        // THÊM LOGIC ĐỔI MÀU NỀN THEO TRẠNG THÁI PHÒNG
                        let cardBgColor = '#ffffff'; // Mặc định là màu trắng
                        if (room.status === 'trong') {
                            cardBgColor = '#e2f0d9'; // Xanh lá nhạt dịu mắt
                        } else if (room.status === 'khach') {
                            cardBgColor = '#fce4d6'; // Hồng/Cam nhạt ấm cúng
                        } else if (room.status === 've_sinh') {
                            cardBgColor = '#fff2cc'; // Vàng nhạt dễ chú ý
                        }

                        // Cấu hình màu sắc cho trạng thái cửa (Mở = Đỏ nguy hiểm, Đóng = Xanh an toàn)
                        let doorColor = room.door === 'Mở' ? '#dc3545' : '#28a745';
                        let doorBadge = room.door === 'Mở' ? '🔓 CỬA ĐANG MỞ' : '🔒 Cửa Đóng';

                        roomHtml += `
                            <div class="room-card" style="background-color: ${cardBgColor};">
                                <h3>${room.room_name}</h3>
                                
                                <div style="background: ${doorColor}; color: white; padding: 6px; margin: 10px 0; border-radius: 4px; font-weight: bold; font-size: 13px; letter-spacing: 0.5px;">
                                    ${doorBadge}
                                </div>

                                <p style="margin-bottom: 5px;">Cấu hình: <span style="color:#333; font-weight:bold;">${displayName}</span></p>
                                <select onchange="updateRoomStatus(${room.id}, this.value)">
                                    <option value="trong" ${room.status=='trong'?'selected':''}>Phòng Trống</option>
                                    <option value="khach" ${room.status=='khach'?'selected':''}>Có Khách Ở</option>
                                    <option value="ve_sinh" ${room.status=='ve_sinh'?'selected':''}>Đang Vệ Sinh</option>
                                </select>
                            </div>
                        `;
                    });
                    document.getElementById('rooms-display').innerHTML = roomHtml;
                }

                // KIỂM TRA THAY ĐỔI 2: Chỉ vẽ lại bảng lịch sử nếu xuất hiện bản ghi thực tế mới
                let latestLog = data.logs[0];
                let latestLogId = latestLog ? latestLog.id : 0;

                if (latestLogId !== lastLogId) {
                    lastLogId = latestLogId;

                    // 2. Vẽ bảng lịch sử mở cửa nhanh
                    let logHtml = '';
                    if (data.logs.length > 0) {
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
                    } else {
                        logHtml = '<tr><td colspan="4" style="text-align:center; color:#888;">Hiện chưa có sự kiện nào được ghi nhận.</td></tr>';
                    }
                    document.getElementById('logs-display').innerHTML = logHtml;
                }
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
