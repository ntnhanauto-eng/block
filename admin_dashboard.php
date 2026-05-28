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
$stats = ['trong' => 0, 'khach' => 0, 've_sinh' => 0];
while ($r = mysqli_fetch_assoc($status_q)) {
    $stats[$r['status']] = $r['count'];
}

$percent_trong = $total_rooms > 0 ? round(($stats['trong'] / $total_rooms) * 180) : 0; // Đổi sang góc xoay trực quan
$percent_khach = $total_rooms > 0 ? round(($stats['khach'] / $total_rooms) * 100) : 0;

// 2. TÍNH NĂNG THÔNG MINH: Tính thời gian dọn phòng trung bình của nhân viên trong tháng qua
// Thuật toán: Tìm khoảng thời gian chênh lệch giữa mốc Lễ tân bấm 've_sinh' và mốc Lao công nhập mã PIN 'DỌN XONG'
$perf_sql = "
    SELECT r.room_name, l1.event_time as start_time, l2.event_time as end_time,
    TIMESTAMPDIFF(MINUTE, l1.event_time, l2.event_time) as duration
    FROM room_logs l1
    JOIN room_logs l2 ON l1.room_id = l2.room_id AND l2.id > l1.id
    JOIN rooms r ON l1.room_id = r.id
    WHERE l1.details LIKE '%đang vệ sinh%' AND l2.event_type = 'DỌN XONG'
    AND l1.event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY l2.id ORDER BY l2.id DESC LIMIT 10
";
$perf_query = mysqli_query($conn, $perf_sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Trung Tâm Phân Tích Dữ Liệu Khách Sạn</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 30px; background: #f4f7f6; }
        .back-link { font-size: 16px; text-decoration: none; color: #007bff; font-weight: bold; }
        .dashboard-grid { display: flex; gap: 25px; margin-top: 20px; flex-wrap: wrap; }
        .dash-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); flex: 1; min-width: 300px; }
        h2 { color: #2c3e50; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        
        .progress-bar { background: #eee; border-radius: 10px; height: 25px; width: 100%; overflow: hidden; margin-top: 15px; display: flex; }
        .progress-chunk { height: 100%; text-align: center; color: white; font-weight: bold; font-size: 12px; line-height: 25px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f8f9fa; }
        .badge-time { background: #e1f5fe; color: #0288d1; font-weight: bold; padding: 4px 8px; border-radius: 4px; }
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
                <div class="progress-chunk" style="background: #28a745; width: <?php echo ($stats['trong']/$total_rooms)*100; ?>%">Trống (<?php echo $stats['trong']; ?>)</div>
                <div class="progress-chunk" style="background: #dc3545; width: <?php echo ($stats['khach']/$total_rooms)*100; ?>%">Có khách (<?php echo $stats['khach']; ?>)</div>
                <div class="progress-chunk" style="background: #ffc107; width: <?php echo ($stats['ve_sinh']/$total_rooms)*100; ?>%; color:#333;">Dọn dẹp (<?php echo $stats['ve_sinh']; ?>)</div>
            </div>
            <p style="margin-top: 15px; font-style: italic; color: #666;">Hiện tại Khách sạn đang vận hành với hiệu suất sử dụng đạt: <b><?php echo $percent_khach; ?>%</b> công suất phòng ngủ.</p>
        </div>

        <div class="dash-card">
            <h2>⏱️ Hiệu suất và Tốc độ dọn phòng (30 ngày qua)</h2>
            <p>Báo cáo ghi nhận chi tiết thời gian hoàn tất ca dọn dẹp buồng phòng:</p>
            <table>
                <thead>
                    <tr><th>Tên Phòng</th><th>Thời Điểm Bắt Đầu</th><th>Thời Điểm Xong</th><th>Thời gian dọn dẹp</th></tr>
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
                        <tr><td colspan="4" style="text-align:center; color:#999;">Hệ thống chưa đủ dữ liệu đối soát chu kỳ dọn phòng.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
