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
    </style>
</head>
<body>

   

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
