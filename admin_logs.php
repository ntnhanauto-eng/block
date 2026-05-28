<?php
include 'config.php';
checkLogin();

// 1. Chặn đứng nếu người dùng đăng nhập không phải là Admin
if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền truy cập trang lịch sử của Admin!</h1>");
}

// 2. Cấu hình phân trang
$rows_per_page = 15; // Mỗi trang hiển thị 15 dòng
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $rows_per_page;

// 3. Tính tổng số dòng THỰC TẾ TRONG 30 NGÀY GẦN NHẤT để biết có bao nhiêu trang
$count_sql = "SELECT COUNT(*) as total FROM room_logs WHERE event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$count_query = mysqli_query($conn, $count_sql);
$count_data = mysqli_fetch_assoc($count_query);
$total_rows = $count_data['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Nếu trang hiện tại vượt quá tổng số trang (đề phòng bấm bậy) thì ép về trang cuối
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $rows_per_page;
}

// 4. Truy vấn lấy dữ liệu phân trang và GIỚI HẠN 30 NGÀY GẦN NHẤT
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
        
        /* CSS cho Thanh Phân Trang */
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
    <p>Dưới đây là dữ liệu toàn bộ lịch sử đóng mở cửa của 3 phòng được lưu vết trong <b>30 ngày gần nhất</b>.</p>

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
                    <tr class="<?php echo ($l['event_type'] === 'BẤT THƯỜNG') ? 'alert-red' : ''; ?>">
                        <td><?php echo $l['id']; ?></td>
                        <td><?php echo $l['event_time']; ?></td>
                        <td><?php echo htmlspecialchars($l['room_name']); ?></td>
                        <td><?php echo htmlspecialchars($l['event_type']); ?></td>
                        <td><?php echo htmlspecialchars($l['details']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="no-data">Hiện tại hệ thống chưa ghi nhận lịch sử đóng mở cửa nào từ cảm biến Tuya trong 30 ngày qua.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            
            <a href="?page=1" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>" title="Trang đầu">« Đầu</a>
            
            <a href="?page=<?php echo $current_page - 1; ?>" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>" title="Trang trước">‹</a>

            <?php 
            // Luôn hiển thị các trang 1, 2, 3
            for ($i = 1; $i <= min(3, $total_pages); $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                echo "<a href='?page=$i' class='$active_class'>$i</a>";
            }

            // Nếu tổng số trang lớn hơn 3, thực hiện ẩn các trang sau vào dấu ba chấm (...) và hiện mũi tên hướng đến trang CUỐI
            if ($total_pages > 3) {
                // Nếu người dùng đang bấm ở các trang từ 4 trở đi, tạo một nhãn số động để họ biết mình đang ở đâu
                if ($current_page > 3) {
                    echo "<span class='dots'>...</span>";
                    echo "<a href='?page=$current_page' class='active'>$current_page</a>";
                }
                
                // Nếu trang hiện tại chưa phải là trang cuối cùng, hiện dấu ... trước khi đến nút cuối
                if ($current_page < $total_pages) {
                    echo "<span class='dots'>...</span>";
                }
            }
            ?>

            <a href="?page=<?php echo $current_page + 1; ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>" title="Trang sau">›</a>
            
            <a href="?page=<?php echo $total_pages; ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>" title="Trang cuối">Cuối »</a>

        </div>
    <?php endif; ?>

</body>
</html>
