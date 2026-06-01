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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống Quản lý Khách sạn Real-time</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 15px; background: #eef2f3; color: #333; transition: background 0.3s, color 0.3s; }
        
        /* HEADER RESPONSIVE */
        .header { background: #2c3e50; color: white; padding: 12px 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); flex-wrap: wrap; gap: 10px; }
        .header h2 { margin: 0; font-size: 16px; letter-spacing: 0.5px; }
        .header a { color: #ffc107; text-decoration: none; font-weight: bold; margin-left: 5px; }
        .header-right { display: flex; align-items: center; justify-content: space-between; width: 100%; }
        .user-info { font-size: 13px; text-align: right; }
        
        .btn-darkmode { background: #34495e; color: #f1c40f; border: 1px solid #f1c40f; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
        
        /* THỐNG KÊ MINI */
        .stats-container { display: flex; gap: 8px; margin-top: 15px; }
        .stat-card { flex: 1; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: center; align-items: center; transition: background 0.3s; text-align: center; }
        .stat-card .num { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .stat-card .label { font-size: 10px; color: #7f8c8d; font-weight: bold; margin-top: 2px; }
        
        /* LƯỚI PHÒNG: MAC ĐỊNH TRÊN MOBILE LÀ 2 PHÒNG MỘT HÀNG */
        .grid-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 15px; }
        
        /* CẤU HÌNH Ô PHÒNG CHUNG */
        .room-card { padding: 12px 10px; border-radius: 8px; text-align: center; transition: all 0.3s ease; background: white; position: relative; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; }
        .room-card h3 { margin: 0 0 6px 0; font-size: 16px; color: #2c3e50; }
        .room-badge-door { color: white; padding: 4px; margin: 4px 0 8px 0; border-radius: 4px; font-weight: bold; font-size: 11px; letter-spacing: 0.3px; }
        .room-card p { margin: 0 0 6px 0; font-size: 12px; color: #555; }
        .room-card select { width: 100%; padding: 6px; margin-top: 4px; border-radius: 4px; border: 1px solid #ccc; background: white; font-weight: 600; color: #444; cursor: pointer; font-size: 12px; }
        
        /* Viền đỏ khẩn cấp tĩnh khi quên đóng cửa */
        .room-card.door-warning { box-shadow: 0 0 12px #e74c3c !important; border: 2px solid #e74c3c !important; }

        /* LỊCH SỬ HÀNH LANG & CUỘN NGANG KHÔNG ÉP DÒNG */
        .log-section { background: white; padding: 15px; border-radius: 8px; margin-top: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: background 0.3s; }
        .log-section h3 { margin-top: 0; font-size: 15px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 8px; }
        
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 4px; border: 1px solid #ddd; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { border: none; border-bottom: 1px solid #eee; padding: 10px 12px; text-align: left; font-size: 13px; white-space: nowrap; }
        th { background: #f8f9fa; color: #34495e; font-weight: bold; }
        
        th:nth-child(1), td:nth-child(1) { min-width: 120px; } 
        th:nth-child(2), td:nth-child(2) { min-width: 90px; }  
        th:nth-child(3), td:nth-child(3) { min-width: 90px; }  
        th:nth-child(4), td:nth-child(4) { white-space: normal; min-width: 250px; } 
        
        .alert-red { background-color: #f8d7da !important; color: #721c24; font-weight: bold; }
        
        .btn-group-vertical { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; }
        .nav-btn { display: block; width: 100%; text-align: center; background: #3498db; color: white !important; padding: 12px; border-radius: 6px; box-sizing: border-box; text-decoration: none; font-weight: bold; font-size: 14px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .nav-btn.analytics { background: #1abc9c; } 
        .nav-btn.danger { background: #e74c3c; }

        body.dark-mode { background: #1a1a1a; color: #e0e0e0; }
        body.dark-mode .stat-card, body.dark-mode .room-card, body.dark-mode .log-section { background: #2d2d2d; color: #e0e0e0; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        body.dark-mode .stat-card .num, body.dark-mode .room-card h3, body.dark-mode .log-section h3, body.dark-mode th { color: #ffffff; }
        body.dark-mode th { background: #3d3d3d; }
        body.dark-mode td, body.dark-mode th { border-color: #444; border-bottom: 1px solid #444; }
        body.dark-mode select { background: #3d3d3d; color: #fff; border-color: #555; }
        body.dark-mode .table-responsive { border-color: #444; }

        @media (min-width: 768px) {
            .header h2 { font-size: 20px; }
            .header-right { width: auto; justify-content: flex-end; }
            .btn-darkmode { margin-right: 15px; }
            .stat-card { padding: 15px; }
            .stat-card .num { font-size: 24px; }
            .stat-card .label { font-size: 13px; }
            
            .grid-container { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
            .room-card { padding: 20px; }
            .room-card h3 { font-size: 20px; }
            .room-badge-door { font-size: 13px; }
            .room-card p { font-size: 13px; }
            .room-card select { font-size: 13px; }
            
            .log-section h3 { font-size: 18px; }
            table { min-width: 100%; } 
            
            .btn-group-vertical { flex-direction: row; }
            .nav-btn { width: auto; display: inline-block; }
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>SMART HOTEL DASHBOARD</h2>
        <div class="header-right">
            <button class="btn-darkmode" onclick="toggleDarkMode()">🌙 Chế độ đêm</button>
            <div class="user-info">Chào: <b><?php echo htmlspecialchars($_SESSION['username']); ?></b> (<span style="color: #ffc107;"><?php echo strtoupper($_SESSION['role']); ?></span>) | <a href="logout.php">Thoát</a></div>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card" style="border-bottom: 4px solid #28a745;"><div class="num" id="count-trong">0</div><div class="label">🟢 TRỐNG</div></div>
        <div class="stat-card" style="border-bottom: 4px solid #fce4d6;"><div class="num" id="count-khach">0</div><div class="label">🔴 CÓ KHÁCH</div></div>
        <div class="stat-card" style="border-bottom: 4px solid #ffc107;"><div class="num" id="count-vesinh">0</div><div class="label">🟡 VỆ SINH</div></div>
    </div>

    <div class="grid-container" id="rooms-display">Đang đồng bộ dữ liệu phòng...</div>

    <div class="log-section">
        <h3>Nhật ký hoạt động buồng phòng</h3>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr><th>Thời gian</th><th>Tên Phòng</th><th>Đánh giá</th><th>Chi tiết sự kiện</th></tr>
                </thead>
                <tbody id="logs-display">
                    <tr><td colspan="4" style="text-align:center;">Đang đồng bộ dữ liệu hành lang...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="btn-group-vertical">
            <a href="danhsach_buongphong.php" class="nav-btn">🧹 XEM TRANG ĐIỀU HÀNH BUỒNG PHÒNG</a>
            
            <?php if (function_exists('isAdmin') && isAdmin()): ?>
                <a href="admin_dashboard.php" class="nav-btn analytics">📊 XEM BÁO CÁO & PHÂN TÍCH HIỆU SUẤT</a>
                <a href="admin_logs.php" class="nav-btn danger">📋 XEM LỊCH SỬ KIỂM TOÁN CHUNG</a>
            <?php endif; ?>
        </div>
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

    function loadRealTimeData(forceRender = false) {
        fetch('api_get_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data) return;

                let trong = 0, khach = 0, vesinh = 0;
                data.rooms.forEach(r => {
                    if (r.status === 'trong') trong++;
                    else if (r.status === 'khach') khach++;
                    else if (r.status === 've_sinh' || r.status === 've_sink') vesinh++;
                });
                document.getElementById('count-trong').innerText = trong;
                document.getElementById('count-khach').innerText = khach;
                document.getElementById('count-vesinh').innerText = vesinh;

                let currentRoomsState = JSON.stringify(data.rooms);
                
                if (forceRender || currentRoomsState !== lastRoomsState) {
                    lastRoomsState = currentRoomsState;

                    let roomHtml = '';
                    isWarningActive = false; 

                    data.rooms.forEach(room => {
                        let displayName = '';
                        let cardBgColor = '#ffffff'; 
                        
                        if (room.status === 'trong') {
                            displayName = 'Phòng Trống';
                            cardBgColor = '#e2f0d9'; 
                        } else if (room.status === 'khach') {
                            displayName = 'Có Khách Ở';
                            cardBgColor = '#fce4d6'; 
                        } else if (room.status === 've_sinh' || room.status === 've_sink') {
                            cardBgColor = '#fff2cc'; // 🔥 GIỮ NGUYÊN: Màu nền vàng ấm cho cả 2 trạng thái buồng phòng theo ý bạn

                            // 🔥 THUẬT TOÁN KIỂM TRA ĐỘNG TRẠNG THÁI CHỜ DỌN / ĐANG DỌN QUA MẢNG NHẬT KÝ ĐÃ CÓ SẴN
                            let isCleaningStarted = false;
                            if (data.logs && data.logs.length > 0) {
                                // Tìm log mới nhất liên quan đến phòng này để đối soát
                                let roomLog = data.logs.find(l => l.room_name === room.room_name);
                                if (roomLog && roomLog.details.includes('BẮT ĐẦU DỌN PHÒNG')) {
                                    isCleaningStarted = true;
                                }
                            }

                            // Cập nhật chữ trạng thái tương ứng
                            if (isCleaningStarted) {
                                displayName = '⏳ Đang Dọn Vệ Sinh';
                            } else {
                                displayName = '⚠️ Chờ Dọn Vệ Sinh';
                            }
                        } else {
                            displayName = room.status.toUpperCase();
                        }

                        let doorColor = room.door === 'Mở' ? '#dc3545' : '#28a745';
                        let doorBadge = room.door === 'Mở' ? '🔓 ĐANG MỞ' : '🔒 CỬA ĐÓNG';

                        let warningClass = room.is_forget_warning ? 'door-warning' : '';
                        if (room.is_forget_warning) {
                            isWarningActive = true; 
                        }

                        let customStyle = 'border: 1px solid #333 !important; box-shadow: 0 4px 15px rgba(243, 114, 140, 0.2) !important;';

                        roomHtml += `
                            <div class="room-card ${warningClass}" style="background-color: ${cardBgColor}; ${customStyle}">
                                <h3><a href="booking.php?room_id=${room.id}" style="color: #2c3e50; text-decoration: none; border-bottom: 1px dashed #2c3e50;" title="Bấm vào để Check-in / Check-out">${room.room_name} ⚙️</a></h3>
                                <div class="room-badge-door" style="background: ${doorColor};">
                                    ${doorBadge}
                                </div>
                                <p>Cấu hình: <b>${displayName}</b></p>
                                <select onchange="updateRoomStatus(${room.id}, this.value)">
                                    <option value="trong" ${room.status=='trong'?'selected':''}>Phòng Trống</option>
                                    <option value="khach" ${room.status=='khach'?'selected':''}>Có Khách Ở</option>
                                    <option value="ve_sinh" ${(room.status=='ve_sinh' || room.status=='ve_sink')?'selected':''}>Đang Vệ Sinh</option>
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
            // playEmergencySound();
        }
    }, 3000);

    function updateRoomStatus(roomId, newStatus) {
        let formData = new FormData();
        formData.append('action', 'update_room');
        formData.append('room_id', roomId);
        formData.append('status', newStatus);
        
        fetch('index.php', { method: 'POST', body: formData }).then(() => loadRealTimeData(true)); 
    }

    setInterval(() => loadRealTimeData(false), 3000); 
    loadRealTimeData(true); 
    </script>
</body>
</html>
