<?php
include 'config.php';
checkLogin();

if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền truy cập!</h1>");
}

// 1. Thống kê tỷ lệ phần trăm các loại trạng thái phòng hiện tại
$total_rooms_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM rooms");
$total_rooms = mysqli_fetch_assoc($total_rooms_q)['total'];

$status_q = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM rooms GROUP BY status");
$stats = ['trong' => 0, 'khach' => 0, 've_sinh' => 0, 've_sink' => 0];

while ($r = mysqli_fetch_assoc($status_q)) {
    $stats[$r['status']] = (int)$r['count'];
}

// Gộp chung hai cách đặt tên trạng thái vệ sinh nếu có (ve_sinh và ve_sink)
$total_cleaning = $stats['ve_sinh'] + $stats['ve_sink'];

$percent_khach = $total_rooms > 0 ? round(($stats['khach'] / $total_rooms) * 100) : 0;

// Tính toán phần trăm an toàn cho thanh tiến trình (Progress Bar), tránh lỗi chia cho 0
$p_trong = $total_rooms > 0 ? ($stats['trong'] / $total_rooms) * 100 : 0;
$p_khach = $total_rooms > 0 ? ($stats['khach'] / $total_rooms) * 100 : 0;
$p_cleaning = $total_rooms > 0 ? ($total_cleaning / $total_rooms) * 100 : 0;


// 2. THUẬT TOÁN MỚI: Tối ưu đối soát mốc thời gian dọn buồng phòng bằng Subquery
// Tìm các log "hoàn tất dọn dẹp", sau đó tìm log "vệ sinh" gần nhất trước đó của phòng đó để tính khoảng cách phút.
$perf_sql = "
    SELECT 
        r.room_name,
        l_start.event_time as start_time,
        l_end.event_time as end_time,
        TIMESTAMPDIFF(MINUTE, l_start.event_time, l_end.event_time) as duration
    FROM room_logs l_end
    JOIN rooms r ON l_end.room_id = r.id
    JOIN room_logs l_start ON l_start.id = (
        SELECT MAX(id) 
        FROM room_logs 
        WHERE room_id = l_end.room_id 
          AND id < l_end.id
          AND (details LIKE '%vệ sinh%' OR details LIKE '%ve_sinh%')
          AND details NOT LIKE '%hoàn tất%'
    )
    WHERE l_end.details LIKE '%hoàn tất%vệ sinh%'
      AND l_end.event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY l_end.id DESC
    LIMIT 10
";

$perf_query = mysqli_query($conn, $perf_sql);

if (!$perf_query) {
    die("Lỗi hệ thống đối soát dữ liệu: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Trung Tâm Phân Tích Dữ Liệu Khách Sạn</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 15px; background: #f4f7f6; color: #334155; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; display: inline-block; margin-bottom: 10px; }
        .dashboard-grid { display: flex; gap: 20px; margin-top: 15px; flex-direction: column; }
        .dash-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; }
        h1 { font-size: 20px; margin-bottom: 5px; }
        h2 { color: #2c3e50; font-size: 16px; margin-top: 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-weight: bold; }
        p { font-size: 13px; margin: 8px 0; }
        
        .progress-bar { background: #e2e8f0; border-radius: 6px; height: 25px; width: 100%; overflow: hidden; margin-top: 15px; display: flex; }
        .progress-chunk { height: 100%; text-align: center; color: white; font-weight: bold; font-size: 11px; line-height: 25px; transition: width 0.3s ease; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 15px; border: 1px solid #e2e8f0; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; min-width: 550px; background: white; }
        th, td { padding: 10px; text-align: left; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; color: #475569; font-weight: bold; }
        .badge-time { background: #e0f2fe; color: #0369a1; font-weight: bold; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: inline-block; }
        
        @media (min-width: 768px) {
            body { margin: 30px; }
            h1 { font-size: 24px; }
            h2 { font-size: 18px; }
            p { font-size: 14px; }
            .dashboard-grid { flex-direction: row; flex-wrap: wrap; }
            .dash-card { flex: 1; min-width: 350px; padding: 25px; }
            th, td { font-size: 14px; padding: 12px; }
        }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h1 style="color: #2c3e50;">📊 TRUNG TÂM PHÂN TÍCH HIỆU SUẤT VÀ CÔNG SUẤT PHÒNG</h1>
    
    <div class="dashboard-grid">
        <div class="dash-card">
            <h2>📈 Tỷ lệ công suất sử dụng phòng</h2>
            <p>Tổng số phòng đang quản lý: <b><?php echo $total_rooms; ?> phòng</b></p>
            <div class="progress-bar">
                <?php if($p_trong > 0): ?>
                    <div class="progress-chunk" style="background: #28a745; width: <?php echo $p_trong; ?>%">Trống (<?php echo $stats['trong']; ?>)</div>
                <?php endif; ?>
                <?php if($p_khach > 0): ?>
                    <div class="progress-chunk" style="background: #dc3545; width: <?php echo $p_khach; ?>%">Có khách (<?php echo $stats['khach']; ?>)</div>
                <?php endif; ?>
                <?php if($p_cleaning > 0): ?>
                    <div class="progress-chunk" style="background: #ffc107; width: <?php echo $p_cleaning; ?>%; color:#333;">Dọn dẹp (<?php echo $total_cleaning; ?>)</div>
                <?php endif; ?>
            </div>
            <p style="margin-top: 15px; font-style: italic; color: #64748b;">Hiện tại Khách sạn đang vận hành với hiệu suất sử dụng đạt: <b><?php echo $percent_khach; ?>%</b> công suất phòng ngủ.</p>
        </div>

        <div class="dash-card">
            <h2>⏱️ Hiệu suất và Tốc độ dọn phòng (30 ngày qua)</h2>
            <p>Báo cáo ghi nhận chi tiết thời gian hoàn tất ca dọn dẹp buồng phòng:</p>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Tên Phòng</th>
                            <th>Thời Điểm Bắt Đầu</th>
                            <th>Thời Điểm Xong</th>
                            <th>Thời gian dọn dẹp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($perf_query) > 0): ?>
                            <?php while ($p = mysqli_fetch_assoc($perf_query)): ?>
                                <tr>
                                    <td><b><?php echo htmlspecialchars($p['room_name']); ?></b></td>
                                    <td><?php echo $p['start_time']; ?></td>
                                    <td><?php echo $p['end_time']; ?></td>
                                    <td><span class="badge-time"><?php echo $p['duration']; ?> phút</span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:#94a3b8; font-style: italic; padding: 20px;">
                                    Hệ thống chưa đủ dữ liệu đối soát chu kỳ dọn phòng.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
