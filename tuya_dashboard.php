<?php
// tuya_dashboard.php
include 'header.php'; // Gọi thanh tiêu đề THÀNH NGHIÊM HOTEL (Tự động checkLogin và đồng bộ Dark Mode)
?>
<style>
    /* KHUNG GIAO DIỆN CHUNG */
    .dashboard-container { margin: 20px; }
    .section-title { font-size: 18px; color: #2c3e50; font-weight: bold; margin: 25px 0 15px 0; border-bottom: 2px solid #ccc; padding-bottom: 5px; display: flex; align-items: center; gap: 8px; }
    body.dark-mode .section-title { color: #ffffff; border-color: #444; }

    /* BỘ THỐNG KÊ MINI SENSOR & SWITCH */
    .tuya-stats { display: flex; gap: 12px; margin-bottom: 20px; }
    .tuya-stat-card { flex: 1; background: white; padding: 12px; border-radius: 8px; text-align: center; border: 1px solid #333; box-shadow: 0 4px 15px rgba(243, 114, 140, 0.2); }
    .tuya-stat-card .num { font-size: 22px; font-weight: bold; }
    .tuya-stat-card .label { font-size: 11px; font-weight: bold; color: #7f8c8d; margin-top: 4px; }
    body.dark-mode .tuya-stat-card { background: #2d2d2d; color: #fff; }

    /* LƯỚI DANH SÁCH THIẾT BỊ (Mặc định 2 cột trên điện thoại) */
    .devices-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }

    /* THÈ THIẾT BỊ ĐỒNG BỘ STYLE INDEX.PHP (VIỀN ĐEN + BÓNG HỒNG NHẠT) */
    .device-card { padding: 15px; border-radius: 8px; background: white; border: 1px solid #333 !important; box-shadow: 0 4px 15px rgba(243, 114, 140, 0.2) !important; display: flex; flex-direction: column; justify-content: space-between; align-items: center; text-align: center; box-sizing: border-box; transition: all 0.3s ease; }
    body.dark-mode .device-card { background: #2d2d2d; }
    
    .device-card h3 { margin: 0 0 8px 0; font-size: 15px; color: #2c3e50; }
    body.dark-mode .device-card h3 { color: #ffffff; }
    .device-loc { font-size: 11px; color: #7f8c8d; margin-bottom: 12px; font-style: italic; }
    
    /* BADGE TRẠNG THÁI CẢM BIẾN CỬA */
    .sensor-status { width: 100%; padding: 8px; border-radius: 6px; font-weight: bold; font-size: 13px; margin-top: 5px; box-sizing: border-box; }
    .status-closed { background: #e2f0d9; color: #28a745; border: 1px solid #28a745; }
    .status-open { background: #fce4d6; color: #dc3545; border: 2px solid #dc3545; animation: alarmBlink 1s infinite; }
    
    @keyframes alarmBlink {
        0% { box-shadow: 0 0 5px #dc3545; }
        50% { box-shadow: 0 0 15px #dc3545; background-color: #f8d7da; }
        100% { box-shadow: 0 0 5px #dc3545; }
    }

    /* NÚT GẠT CÔNG TẮC ĐIỆN (TOGGLE SWITCH CSS) */
    .switch-container { position: relative; display: inline-block; width: 55px; height: 28px; margin-top: 5px; }
    .switch-container input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; border: 1px solid #666; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
    input:checked + .slider { background-color: #28a745; }
    input:checked + .slider:before { transform: translateX(27px); }
    
    .switch-text { font-size: 12px; font-weight: bold; margin-top: 5px; color: #555; }
    body.dark-mode .switch-text { color: #bbb; }

    /* ĐIỀU CHỈNH RESPONSIVE TRÊN MÁY TÍNH */
    @media (min-width: 768px) {
        .devices-grid { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .device-card { padding: 20px; }
        .device-card h3 { font-size: 16px; }
        .tuya-stats { max-width: 600px; margin: 0 auto 20px auto; }
    }
</style>

<div class="dashboard-container">
    
    <div class="tuya-stats">
        <div class="tuya-stat-card" style="border-bottom: 4px solid #dc3545 !important;">
            <div class="num" id="total-open-doors" style="color: #dc3545;">0</div>
            <div class="label">🔓 CỬA ĐANG MỞ</div>
        </div>
        <div class="tuya-stat-card" style="border-bottom: 4px solid #28a745 !important;">
            <div class="num" id="total-on-switches" style="color: #28a745;">0</div>
            <div class="label">💡 CÔNG TẮC ĐANG BẬT</div>
        </div>
    </div>

    <div class="section-title">
        <i class="fa-solid fa-shield-halved" style="color: #e74c3c;"></i> HỆ THỐNG CẢM BIẾN CỬA AN NINH
    </div>
    <div class="devices-grid" id="sensors-display">
        <div style="grid-column: 1/-1; text-align: center; color: #888; padding: 20px;">Đang đồng bộ dữ liệu khách sạn...</div>
    </div>

    <div class="section-title">
        <i class="fa-solid fa-lightbulb" style="color: #f1c40f;"></i> HỆ THỐNG CÔNG TẮC ĐIỀU KHIỂN ĐIỆN
    </div>
    <div class="devices-grid" id="switches-display">
        <div style="grid-column: 1/-1; text-align: center; color: #888; padding: 20px;">Đang đồng bộ dữ liệu khách sạn...</div>
    </div>

</div>

<script>
// Hàm phát tiếng bíp ngầm cảnh báo quên đóng cửa
function playWarningBeep() {
    let audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx) {
        let oscillator = audioCtx.createOscillator();
        let gainNode = audioCtx.createGain();
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        oscillator.type = 'sine'; 
        oscillator.frequency.setValueAtTime(1200, audioCtx.currentTime); 
        gainNode.gain.setValueAtTime(0.15, audioCtx.currentTime);
        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.15); 
    }
}

// HÀM TẢI DỮ LIỆU ĐỒNG BỘ THẲNG TỪ API_GET_STATUS.PHP CỦA KHÁCH SẠN
function loadTuyaRealTimeData() {
    fetch('api_get_status.php')
        .then(res => res.json())
        .then(data => {
            if (!data || !data.rooms) return;

            let openDoorsCount = 0;
            let onSwitchesCount = 0;
            let sensorHtml = '';
            let switchHtml = '';

            // DUYỆT DANH SÁCH PHÒNG ĐỂ TRÍCH XUẤT TRẠNG THÁI CỬA VÀ ĐIỆN
            data.rooms.forEach(room => {
                
                // 1. TRÍCH XUẤT CẢM BIẾN CỬA (Thuộc tính room.door)
                let isOpen = room.door === 'Mở';
                if (isOpen) openDoorsCount++;

                let statusClass = isOpen ? 'status-open' : 'status-closed';
                let statusIcon = isOpen ? '🔓 CỬA ĐANG MỞ' : '🔒 Cửa Đóng';

                sensorHtml += `
                    <div class="device-card">
                        <h3>Cửa Phòng ${room.room_name}</h3>
                        <div class="device-loc"><i class="fa-solid fa-location-dot"></i> Thiết bị: Cảm biến Tuya</div>
                        <div class="sensor-status ${statusClass}">${statusIcon}</div>
                    </div>
                `;

                // 2. TRÍCH XUẤT CÔNG TẮC ĐIỆN (Liên kết tự động theo trạng thái phòng)
                // Nếu phòng có khách ('khach') hoặc đang dọn ('ve_sinh') -> Hệ thống điện BẬT
                let isOn = (room.status === 'khach' || room.status === 've_sinh');
                if (isOn) onSwitchesCount++;

                switchHtml += `
                    <div class="device-card">
                        <h3>Điện Phòng ${room.room_name}</h3>
                        <div class="device-loc"><i class="fa-solid fa-plug"></i> Công tắc thông minh</div>
                        <label class="switch-container">
                            <input type="checkbox" ${isOn ? 'checked' : ''} onchange="controlRoomSwitch(${room.id}, this.checked)"> 
                            <span class="slider"></span>
                        </label>
                        <span class="switch-text" style="color: ${isOn ? '#28a745' : '#7f8c8d'}">${isOn ? 'ĐANG BẬT' : 'Tắt'}</span>
                    </div>
                `;
            });

            // Đẩy số liệu lên bộ thống kê mini
            document.getElementById('total-open-doors').innerText = openDoorsCount;
            document.getElementById('total-on-switches').innerText = onSwitchesCount;

            // Đổ HTML ra màn hình điều khiển
            document.getElementById('sensors-display').innerHTML = sensorHtml;
            document.getElementById('switches-display').innerHTML = switchHtml;

            // Kích hoạt tiếng bíp cảnh báo quên đóng cửa định kỳ
            if (openDoorsCount > 0) {
                playWarningBeep();
            }
        }).catch(err => console.log("Lỗi đồng bộ dữ liệu từ api_get_status.php"));
}

// HÀM XỬ LÝ KHI NHÂN VIÊN GẠT CÔNG TẮC ĐIỆN TRÊN WEBSITE
function controlRoomSwitch(roomId, isChecked) {
    let targetStatus = isChecked ? 'khach' : 'trong'; // Bật điện quy ra trạng thái phòng tương ứng
    
    let formData = new FormData();
    formData.append('action', 'update_room');
    formData.append('room_id', roomId);
    formData.append('status', targetStatus);

    // Gửi lệnh POST đồng bộ thẳng về index.php để thay đổi trạng thái thiết bị/phòng ngoài đời thực
    fetch('index.php', { 
        method: 'POST', 
        body: formData 
    }).then(() => loadTuyaRealTimeData()); 
}

// Quét dữ liệu liên tục sau mỗi 3 giây giống hệt trang index.php
setInterval(loadTuyaRealTimeData, 3000);
window.addEventListener('DOMContentLoaded', loadTuyaRealTimeData);
</script>
</body>
</html>
