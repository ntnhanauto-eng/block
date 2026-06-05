<?php
include 'config.php';
// --- 1. BẢO MẬT: CHỈ ADMIN MỚI ĐƯỢC VÀO ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giám Sát Trạng Thái Cửa Real-time</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 15px; background: #eef2f3; color: #333; }
        
        /* LƯỚI PHÒNG */
        .grid-container { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 15px; }
        
        /* Đã bỏ phần border đỏ khẩn cấp, ép cứng 1 loại viền và đổ bóng cho mọi phòng */
        .room-card { padding: 15px 10px; border-radius: 8px; text-align: center; transition: all 0.3s ease; background: white; box-sizing: border-box; border: 1px solid #333 !important; box-shadow: 0 4px 15px rgba(243, 114, 140, 0.2) !important; }
        .room-card h3 { margin: 0 0 10px 0; font-size: 18px; color: #2c3e50; }
        .room-badge-door { color: white; padding: 8px; border-radius: 4px; font-weight: bold; font-size: 13px; letter-spacing: 0.3px; }

        /* PHẦN LỊCH SỬ */
        .log-section { background: white; padding: 15px; border-radius: 8px; margin-top: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .log-section h3 { margin-top: 0; font-size: 16px; color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 8px; }
        
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 4px; border: 1px solid #ddd; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { border: none; border-bottom: 1px solid #eee; padding: 10px 12px; text-align: left; font-size: 13px; white-space: nowrap; }
        th { background: #f8f9fa; color: #34495e; font-weight: bold; }
        
        .alert-red { background-color: #f8d7da !important; color: #721c24; font-weight: bold; }
        
        /* PHÂN TRANG */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 15px; align-items: center; }
        .pagination button { background: #3498db; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
        .pagination button:disabled { background: #bdc3c7; cursor: not-allowed; }
        .pagination span { font-size: 13px; font-weight: 600; color: #555; }

        @media (min-width: 768px) {
            .grid-container { grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
            table { min-width: 100%; } 
        }
    </style>
</head>
<body>

    <div class="grid-container" id="rooms-display">Đang đồng bộ dữ liệu phòng...</div>

    <div class="log-section">
        <h3>Nhật ký hoạt động cửa phòng</h3>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">STT</th>
                        <th>THỜI GIAN</th>
                        <th>PHÒNG</th>
                        <th>TRẠNG THÁI CỬA</th>
                    </tr>
                </thead>
                <tbody id="logs-display">
                    <tr><td colspan="4" style="text-align:center;">Đang đồng bộ dữ liệu nhật ký...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button id="btn-prev" onclick="changePage(-1)" disabled>◀ Trước</button>
            <span id="page-info">Trang 1 / 1</span>
            <button id="btn-next" onclick="changePage(1)" disabled>Sau ▶</button>
        </div>
    </div>

    <script>
    let lastRoomsState = "";
    let allLogs = []; 
    let currentPage = 1;
    const rowsPerPage = 10; // Giới hạn đúng 10 dòng trên một trang

    function loadRealTimeData(forceRender = false) {
        fetch('api_get_status.php')
            .then(res => res.json())
            .then(data => {
                if (!data) return;

                // 1. XỬ LÝ ĐỒNG BỘ Ô PHÒNG (Đã gỡ bỏ hiệu ứng cảnh báo viền đỏ)
                let currentRoomsState = JSON.stringify(data.rooms);
                if (forceRender || currentRoomsState !== lastRoomsState) {
                    lastRoomsState = currentRoomsState;
                    let roomHtml = '';

                    data.rooms.forEach(room => {
                        let doorColor = room.door === 'Mở' ? '#dc3545' : '#28a745';
                        let doorBadge = room.door === 'Mở' ? '🔓 ĐANG MỞ' : '🔒 CỬA ĐÓNG';

                        roomHtml += `
                            <div class="room-card">
                                <h3>${room.room_name}</h3>
                                <div class="room-badge-door" style="background: ${doorColor};">
                                    ${doorBadge}
                                </div>
                            </div>
                        `;
                    });
                    document.getElementById('rooms-display').innerHTML = roomHtml;
                }

                // 2. CẬP NHẬT DỮ LIỆU LOGS VÀ RENDER PHÂN TRANG
                if (data.logs) {
                    allLogs = data.logs;
                    renderLogsTable();
                }
            });
    }

    // Hàm render bảng theo đúng cấu trúc cột mới
    function renderLogsTable() {
        const totalPages = Math.ceil(allLogs.length / rowsPerPage) || 1;
        
        if (currentPage > totalPages) currentPage = totalPages;

        const startIndex = (currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const paginatedLogs = allLogs.slice(startIndex, endIndex);

        let logHtml = '';
        if (paginatedLogs.length === 0) {
            logHtml = `<tr><td colspan="4" style="text-align:center;">Không có dữ liệu nhật ký.</td></tr>`;
        } else {
            paginatedLogs.forEach((log, index) => {
                let pSTT = startIndex + index + 1;
                let isDanger = log.event_type === 'BẤT THƯỜNG' ? 'class="alert-red"' : '';
                
                // Tách icon dựa vào chi tiết log từ hệ thống
                let doorStatusText = log.details.includes('Mở') || log.details.includes('mở') 
                    ? '<span style="color: #dc3545; font-weight: bold;">🔓 ĐANG MỞ</span>' 
                    : '<span style="color: #28a745; font-weight: bold;">🔒 CỬA ĐÓNG</span>';
                
                // Hiển thị theo thứ tự cột yêu cầu: STT | THỜI GIAN | PHÒNG | TRẠNG THÁI CỬA
                logHtml += `
                    <tr ${isDanger}>
                        <td><b>${pSTT}</b></td>
                        <td>${log.event_time}</td>
                        <td><b>${log.room_name}</b></td>
                        <td>${doorStatusText} <small style="color:#777; margin-left: 8px;">(${log.details})</small></td>
                    </tr>
                `;
            });
        }

        document.getElementById('logs-display').innerHTML = logHtml;

        // Cập nhật giao diện thanh phân trang
        document.getElementById('page-info').innerText = `Trang ${currentPage} / ${totalPages}`;
        document.getElementById('btn-prev').disabled = (currentPage === 1);
        document.getElementById('btn-next').disabled = (currentPage === totalPages);
    }

    // Hàm chuyển trang
    function changePage(direction) {
        currentPage += direction;
        renderLogsTable();
    }

    // Đồng bộ Real-time mỗi 3 giây
    setInterval(() => loadRealTimeData(false), 3000); 
    
    // Tải dữ liệu lập tức khi load trang
    loadRealTimeData(true); 
    </script>
</body>
</html>
