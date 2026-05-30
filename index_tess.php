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
<?php 
include 'header.php'; 
?>
<style>
        /* GIỮ NGUYÊN CSS GIAO DIỆN PHÒNG VÀ LOGS */
        .stats-container { display: flex; gap: 8px; margin-top: 15px; }
        .stat-card { flex: 1; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); display: flex; flex-direction: column; justify-content: center; align-items: center; transition: background 0.3s; text-align: center; }
        .stat-card .num { font-size: 18px; font-weight: bold; color: #2c3e50; }
        .stat-card .label { font-size: 10px; color: #7f8c8d; font-weight: bold; margin-top: 2px; }
        
        .grid-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 15px; }
        
        .room-card { padding: 12px 10px; border-radius: 8px; border-top: 4px solid #3498db; box-shadow: 0 2px 6px rgba(0,0,0,0.06); text-align: center; transition: all 0.3s ease; background: white; position: relative; display: flex; flex-direction: column; justify-content: space-between; box-sizing: border-box; }
        .room-card h3 { margin: 0 0 6px 0; font-size: 16px; color: #2c3e50; }
        .room-badge-door { color: white; padding: 4px; margin: 4px 0 8px 0; border-radius: 4px; font-weight: bold; font-size: 11px; letter-spacing: 0.3px; }
        .room-card p { margin: 0 0 6px 0; font-size: 12px; color: #555; }
        .room-card select { width: 100%; padding: 6px; margin-top: 4px; border-radius: 4px; border: 1px solid #ccc; background: white; font-weight: 600; color: #444; cursor: pointer; font-size: 12px; }
        
        .room-card.door-warning { animation: emergencyBlink 1s infinite !important; border-top-color: #e74c3c !important; }
        @keyframes emergencyBlink {
            0% { box-shadow: 0 0 5px #e74c3c; background-color: #fce4d6; }
            50% { box-shadow: 0 0 15px #e74c3c; background-color: #f8d7da; }
            100% { box-shadow: 0 0 5px #e74c3c; background-color: #fce4d6; }
        }

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

        @media (min-width: 768px) {
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

    <div class="stats-container">
        <div class="stat-card" style="border-bottom: 4px solid #28a745;"><div class="num" id="count-trong">0</div><div class="label">🟢 TRỐNG</div></div>
        <div class="stat-card" style="border-bottom: 4px solid #dc3545;"><div class="num" id="count-khach">0</div><div class="label">🔴 CÓ KHÁCH</div></div>
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
            
            <?php if (isAdmin()): ?>
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
                        const statusMap = {'trong': 'Phòng Trống', 'khach': 'Có Khách Ở', 've_sinh': 'Đang Vệ Sinh', 've_sink': 'Đang Vệ Sinh'};
                        let displayName = statusMap[room.status] || room.status.toUpperCase();

                        let cardBgColor = '#ffffff'; 
                        if (room.status === 'trong') cardBgColor = '#e2f0d9'; 
                        else if (room.status === 'khach') cardBgColor = '#fce4d6'; 
                        else if (room.status === 've_sinh' || room.status === 've_sink') cardBgColor = '#fff2cc'; 

                        let doorColor = room.door === 'Mở' ? '#dc3545' : '#28a745';
                        let doorBadge = room.door === 'Mở' ? '🔓 ĐANG MỞ' : '🔒 Cửa Đóng';

                        let warningClass = room.is_forget_warning ? 'door-warning' : '';
                        if (room.is_forget_warning) {
                            isWarningActive = true; 
                        }

                        roomHtml += `
                            <div class="room-card ${warningClass}" style="background-color: ${cardBgColor};">
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
        fetch('index.php', { method: 'POST', body: formData }).then(() => loadRealTimeData()); 
    }

    setInterval(loadRealTimeData, 3000);
    loadRealTimeData(); 
    </script>
</body>
</html>
