<?php
include 'config.php';
checkLogin(); // Bắt buộc đăng nhập

// XỬ LÝ CẬP NHẬT TRẠNG THÁI PHÒNG VÀ GHI NHẬT KÝ LỄ TÂN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_room') {
    $room_id = (int)$_POST['room_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $username_action = $_SESSION['username']; 

    $old_room_query = mysqli_query($conn, "SELECT room_name, status FROM rooms WHERE id = $room_id");
    $old_room_data = mysqli_fetch_assoc($old_room_query);
    
    if ($old_room_data) {
        $room_name = $old_room_data['room_name'];
        $old_status = $old_room_data['status'];

        $status_map = ['trong' => 'Phòng Trống', 'khach' => 'Có Khách Ở', 've_sinh' => 'Đang Vệ Sinh'];
        $old_status_vn = $status_map[$old_status] ?? $old_status;
        $new_status_vn = $status_map[$status] ?? $status;

        if ($old_status !== $status) {
            mysqli_query($conn, "UPDATE rooms SET status = '$status' WHERE id = $room_id");

            $time_now = date('Y-m-d H:i:s');
            $event_type = "LỄ TÂN"; 
            
            // Cập nhật cấu trúc chuỗi chi tiết tương thích cấu hình: Mặc định Theo giờ [gio], Giá [100000], Cọc [0] để file booking đọc không lỗi
            if ($status === 'khach') {
                $details = "Lễ tân [$username_action] Check-in khách: Vãng Lai (Chuyển trạng thái nhanh) - Số người: 1 - Hình thức: [gio] (Theo Giờ) - Giá phòng: [100000] (100.000đ/giờ) - Ứng trước: [0] (0đ)";
            } else {
                $details = "Nhân viên [$username_action] đã chuyển trạng thái từ ($old_status_vn) thành ($new_status_vn)";
            }
            
            mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', '$event_type', '$details')");
            
            if (function_exists('sendTelegramNotification')) {
                sendTelegramNotification("👤 <b>CẬP NHẬT TỪ LỄ TÂN:</b>\n🏨 <b>$room_name</b>\n✍️ Người thực hiện: <code>$username_action</code>\n🔄 Thay đổi: $old_status_vn ➡️ <b>$new_status_vn</b>");
            }
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ thống Quản lý Khách sạn Real-time</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 30px; background: #eef2f3; color: #333; transition: background 0.3s, color 0.3s; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header h2 { margin: 0; font-size: 20px; letter-spacing: 0.5px; }
        .header a { color: #ffc107; text-decoration: none; font-weight: bold; margin-left: 10px; }
        
        .btn-darkmode { background: #34495e; color: #f1c40f; border: 1px solid #f1c40f; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; margin-right: 15px; }
        
        .stats-container { display: flex; gap: 15px; margin-top: 20px; }
        .stat-card { flex: 1; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; transition: background 0.3s; }
        .stat-card .num { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .stat-card .label { font-size: 14px; color: #7f8c8d; font-weight: 600; }
        
        .grid-container { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
        .room-card { padding: 20px; border-radius: 8px; border-top: 5px solid #3498db; box-shadow: 0 4px 10px rgba(0,0,0,0.08); width: 230px; text-align: center; transition: all 0.3s ease; background: white; position: relative; }
        .room-card:hover { transform: translateY(-3px); box-shadow: 0 6px 15px rgba(0,0,0,0.12); }
        .room-card h3 { margin: 0 0 10px 0; font-size: 22px; color: #2c3e50; }
        .room-card select { width: 100%; padding: 8px; margin-top: 12px; border-radius: 4px; border: 1px solid #ccc; background: white; font-weight: 600; color: #444; cursor: pointer; }
        
        /* HIỆU ỨNG NHẤP NHÁY KHẨN CẤP KHI PHÒNG QUÊN ĐÓNG CỬA > 5 PHÚT */
        .room-card.door-warning { animation: emergencyBlink 1s infinite !important; border-top-color: #e74c3c !important; }
        @keyframes emergencyBlink {
            0% { box-shadow: 0 0 5px #e74c3c; background-color: #fce4d6; }
            50% { box-shadow: 0 0 20px #e74c3c; background-color: #f8d7da; }
            100% { box-shadow: 0 0 5px #e74c3c; background-color: #fce4d6; }
        }

        .log-section { background: white; padding: 20px; border-radius: 8px; margin-top: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: background 0.3s; }
        .log-section h3 { margin-top: 0; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; transition: border-color 0.3s; }
        th { background: #f8f9fa; color: #34495e; font-weight: bold; }
        .alert-red { background-color: #f8d7da !important; color: #721c24; font-weight: bold; }
        
        .nav-btn { display: inline-block; background: #3498db; color: white !important; padding: 10px 15px; border-radius: 4px; margin-top: 15px; text-decoration: none; font-weight: bold; margin-right: 10px; }
        .nav-btn.analytics { background: #1abc9c; } /* Màu xanh ngọc bích của nút Báo cáo */
        .nav-btn.danger { background: #e74c3c; }

        body.dark-mode { background: #1a1a1a; color: #e0e0e0; }
        body.dark-mode .stat-card, body.dark-mode .room-card, body.dark-mode .log-section { background: #2d2d2d; color: #e0e0e0; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        body.dark-mode .stat-card .num, body.dark-mode .room-card h3, body.dark-mode .log-section h3, body.dark-mode th { color: #ffffff; }
        body.dark-mode th { background: #3d3d3d; }
        body.dark-mode td, body.dark-mode th { border-color: #444; }
        body.dark-mode select { background: #3d3d3d; color: #fff; border-color: #555; }
    </style>
</head>
<body>

    <div class="header">
        <h2>SMART HOTEL DASHBOARD (Chế độ Real-time)</h2>
        <div style="display: flex; align-items: center;">
            <button class="btn-darkmode" onclick="toggleDarkMode()">🌙 Chế độ đêm</button>
            <div>Xin chào: <b><?php echo htmlspecialchars($_SESSION['username']); ?></b> (<span style="color: #ffc107;"><?php echo strtoupper($_SESSION['role']); ?></span>) | <a href="logout.php">Đăng xuất</a></div>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card" style="border-bottom: 4px solid #28a745;"><div class="label">🟢 PHÒNG TRỐNG</div><div class="num" id="count-trong">0</div></div>
        <div class="stat-card" style="border-bottom: 4px solid #dc3545;"><div class="label">🔴 CÓ KHÁCH Ở</div><div class="num" id="count-khach">0</div></div>
        <div class="stat-card" style="border-bottom: 4px solid #ffc107;"><div class="label">🟡 ĐANG VỆ SINH</div><div class="num" id="count-vesinh">0</div></div>
    </div>

    <div class="grid-container" id="rooms-display">Đang đồng bộ dữ liệu phòng...</div>

    <div class="log-section">
        <h3>Lịch sử đóng mở cửa, vệ sinh buồng phòng gần đây (Tự động cập nhật)</h3>
        <table>
            <thead>
                <tr><th>Thời gian</th><th>Tên Phòng</th><th>Đánh giá hệ thống</th><th>Chi tiết sự kiện</th></tr>
            </thead>
            <tbody id="logs-display">
                <tr><td colspan="4">Đang đồng bộ lịch sử hành lang...</td></tr>
            </tbody>
        </table>

        <a href="danhsach_buongphong.php" class="nav-btn">🧹 XEM TRANG ĐIỀU HÀNH BUỒNG PHÒNG</a>
        
        <?php if (isAdmin()): ?>
            <a href="admin_dashboard.php" class="nav-btn analytics">📊 XEM BÁO CÁO & PHÂN TÍCH HIỆU SUẤT</a>
            <a href="admin_logs.php" class="nav-btn danger">📋 XEM LỊCH SỬ KIỂM TOÁN CHUNG</a>
        <?php endif; ?>
    </div>

    <script>
    let lastLogId = 0;
    let lastRoomsState = "";
    let isWarningActive = false;

    function playEmergencySound() {
        let audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx) {
            let oscillator = audioCtx.createOscillator();
            let gainNode = audioCtx.createGain();
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.type = 'sawtooth'; 
            oscillator.frequency.setValueAtTime(988, audioCtx.currentTime); 
            gainNode.gain.setValueAtTime(0.15, audioCtx.currentTime);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.3); 
        }
    }

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        if(document.body.classList.contains('dark-mode')) {
            localStorage.setItem('hotel-theme', 'dark');
        } else {
            localStorage.setItem('hotel-theme', 'light');
        }
    }
    if(localStorage.getItem('hotel-theme') === 'dark') {
        document.body.classList.add('dark-mode');
    }

    function loadRealTimeData() {
        fetch('api_get_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data) return;

                let trong = 0, khach = 0, vesinh = 0;
                data.rooms.forEach(r => {
                    if (r.status === 'trong') trong++;
                    else if (r.status === 'khach') khach++;
                    else if (r.status === 've_sinh') vesinh++;
                });
                document.getElementById('count-trong').innerText = trong;
                document.getElementById('count-khach').innerText = khach;
                document.getElementById('count-vesinh').innerText = vesinh;

                let currentRoomsState = JSON.stringify(data.rooms);
                if (currentRoomsState !== lastRoomsState) {
                    lastRoomsState = currentRoomsState;

                    let roomHtml = '';
                    isWarningActive = false; 

                    data.rooms.forEach(room => {
                        const statusMap = {'trong': 'Phòng Trống', 'khach': 'Có Khách Ở', 've_sinh': 'Đang Vệ Sinh'};
                        let displayName = statusMap[room.status] || room.status.toUpperCase();

                        let cardBgColor = '#ffffff'; 
                        if (room.status === 'trong') cardBgColor = '#e2f0d9'; 
                        else if (room.status === 'khach') cardBgColor = '#fce4d6'; 
                        else if (room.status === 've_sinh') cardBgColor = '#fff2cc'; 

                        let doorColor = room.door === 'Mở' ? '#dc3545' : '#28a745';
                        let doorBadge = room.door === 'Mở' ? '🔓 CỬA ĐANG MỞ' : '🔒 Cửa Đóng';

                        let warningClass = room.is_forget_warning ? 'door-warning' : '';
                        if (room.is_forget_warning) {
                            isWarningActive = true; 
                        }

                        // ĐÃ SỬA: Tên tiêu đề phòng giờ đây tích hợp kèm icon bánh răng ⚙️ liên kết trực tiếp sang trang xử lý Booking đặt phòng siêu tốc
                        roomHtml += `
                            <div class="room-card ${warningClass}" style="background-color: ${cardBgColor};">
                                <h3><a href="booking.php?room_id=${room.id}" style="color: #2c3e50; text-decoration: none; border-bottom: 1px dashed #2c3e50;" title="Bấm vào để Check-in / Check-out">${room.room_name} ⚙️</a></h3>
                                <div style="background: ${doorColor}; color: white; padding: 6px; margin: 10px 0; border-radius: 4px; font-weight: bold; font-size: 13px;">
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

                let latestLog = data.logs[0];
                let latestLogId = latestLog ? latestLog.id : 0;

                if (latestLogId !== lastLogId) {
                    lastLogId = latestLogId;
                    let logHtml = '';
                    data.logs.forEach(log => {
                        let isDanger = log.event_type === 'BẤT THƯỜNG' ? 'class="alert-red"' : '';
                        logHtml += `<tr ${isDanger}><td>${log.event_time}</td><td>${log.room_name}</td><td>${log.event_type}</td><td>${log.details}</td></tr>`;
                    });
                    document.getElementById('logs-display').innerHTML = logHtml;
                }
            });
    }

    setInterval(() => {
        if (isWarningActive) {
            playEmergencySound();
        }
    }, 3000);

    function updateRoomStatus(roomId, newStatus) {
        let formData = new FormData();
        formData.append('action', 'update_room');
        formData.append('room_id', roomId);
        formData.append('status', newStatus);
        fetch('index.php', { method: 'POST', body: formData }).then(() => loadRealTimeData()); 
    }

    setInterval(loadRealTimeData, 3000);
    loadRealTimeData(); 
    </script>
</body>
</html>
