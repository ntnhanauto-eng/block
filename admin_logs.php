<?php
include 'config.php';
checkLogin();

if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền truy cập trang lịch sử của Admin!</h1>");
}

$rows_per_page = 15; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $rows_per_page;

$count_sql = "SELECT COUNT(*) as total FROM room_logs WHERE event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$count_query = mysqli_query($conn, $count_sql);
$count_data = mysqli_fetch_assoc($count_query);
$total_rows = $count_data['total'];
$total_pages = ceil($total_rows / $rows_per_page);

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $rows_per_page;
}

$sql = "SELECT l.*, r.room_name 
        FROM room_logs l 
        JOIN rooms r ON l.room_id = r.id 
        WHERE l.event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
        ORDER BY l.event_time DESC 
        LIMIT $offset, $rows_per_page";

$all_logs = mysqli_query($conn, $sql);

if (!$all_logs) {
    die("Lỗi truy vấn hệ thống: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hệ thống quản trị tối cao</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 10px; }
        th { background: #e6e6e6; }
        .alert-red { background-color: #ffcccc; color: red; font-weight: bold; }
        .back-link { font-size: 16px; text-decoration: none; color: #007bff; font-weight: bold; }
        .no-data { text-align: center; padding: 20px; font-style: italic; color: #666; }
        
        /* CSS Badge phân loại sự kiện trực quan cho Admin */
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge-letan { background: #d1ecf1; color: #0c5460; } /* Màu xanh dương nhạt */
        .badge-clean { background: #d4edda; color: #155724; } /* Màu xanh lá nhạt */
        .badge-danger { background: #f8d7da; color: #721c24; } /* Màu đỏ nhạt */
        .badge-normal { background: #e2e3e5; color: #383d41; } /* Màu xám mặc định */

        .pagination { display: flex; justify-content: center; align-items: center; margin-top: 25px; gap: 5px; }
        .pagination a, .pagination span { padding: 8px 14px; border: 1px solid #ddd; text-decoration: none; color: #007bff; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .pagination a:hover { background-color: #f1f1f1; }
        .pagination .active { background-color: #007bff; color: white; border: 1px solid #007bff; }
        .pagination .disabled { color: #ccc; border-color: #eee; pointer-events: none; }
        .pagination .dots { border: none; color: #666; }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h2>HỆ THỐNG KIỂM TOÁN VÀ AN NINH TOÀN DIỆN (CHỈ ADMIN)</h2>
    <p>Dưới đây là dữ liệu toàn bộ lịch sử hệ thống của 3 phòng được lưu vết trong <b>30 ngày gần nhất</b>.</p>

    <table>
        <thead>
            <tr>
                <th>Mã ID</th>
                <th>Thời Gian Ghi Nhận</th>
                <th>Tên Phòng Ngủ</th>
                <th>Phân Loại Sự Kiện</th>
                <th>Chi Tiết Bản Ghi Hệ Thống</th>
            </tr>
        </thead>
        <tbody>
            <?php if (mysqli_num_rows($all_logs) > 0): ?>
                <?php while($l = mysqli_fetch_assoc($all_logs)): ?>
                    <?php 
                        // Thuật toán định dạng class màu sắc động theo cột event_type
                        $row_class = ($l['event_type'] === 'BẤT THƯỜNG') ? 'alert-red' : '';
                        
                        $badge_class = 'badge-normal';
                        if ($l['event_type'] === 'LÊ TÂN') $badge_class = 'badge-letan';
                        elseif ($l['event_type'] === 'DỌN XONG' || $l['event_type'] === 'DỌN PHÒNG') $badge_class = 'badge-clean';
                        elseif ($l['event_type'] === 'BẤT THƯỜNG') $badge_class = 'badge-danger';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo $l['id']; ?></td>
                        <td><?php echo $l['event_time']; ?></td>
                        <td><?php echo htmlspecialchars($l['room_name']); ?></td>
                        <td>
                            <span class="badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($l['event_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($l['details']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="no-data">Hiện tại hệ thống chưa ghi nhận lịch sử nào trong 30 ngày qua.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a href="?page=1" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>">« Đầu</a>
            <a href="?page=<?php echo $current_page - 1; ?>" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>">‹</a>

            <?php 
            for ($i = 1; $i <= min(3, $total_pages); $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                echo "<a href='?page=$i' class='$active_class'>$i</a>";
            }

            if ($total_pages > 3) {
                if ($current_page > 3) {
                    echo "<span class='dots'>...</span>";
                    echo "<a href='?page=$current_page' class='active'>$current_page</a>";
                }
                if ($current_page < $total_pages) {
                    echo "<span class='dots'>...</span>";
                }
            }
            ?>

            <a href="?page=<?php echo $current_page + 1; ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>">›</a>
            <a href="?page=<?php echo $total_pages; ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>">Cuối »</a>
        </div>
    <?php endif; ?>

</body>
</html>
