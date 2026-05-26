<?php
include 'config.php';
checkLogin();

// Chặn đứng nếu người dùng đăng nhập không phải là Admin
if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền truy cập trang lịch sử của Admin!</h1>");
}

// Truy vấn toàn bộ lịch sử không giới hạn từ database
$all_logs = mysqli_query($conn, "SELECT l.*, r.room_name FROM door_logs l JOIN rooms r ON l.room_id = r.id ORDER BY l.event_time DESC");
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
    </style>
</head>
<body>
    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h2>HỆ THỐNG KIỂM TOÁN VÀ AN NINH TOÀN DIỆN (CHỈ ADMIN)</h2>
    <p>Dưới đây là dữ liệu toàn bộ lịch sử đóng mở cửa của 3 phòng được lưu vết vĩnh viễn.</p>

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
            <?php while($l = mysqli_fetch_assoc($all_logs)): ?>
                <tr class="<?php echo ($l['event_type'] === 'BẤT THƯỜNG') ? 'alert-red' : ''; ?>">
                    <td><?php echo $l['id']; ?></td>
                    <td><?php echo $l['event_time']; ?></td>
                    <td><?php echo $l['room_name']; ?></td>
                    <td><?php echo $l['event_type']; ?></td>
                    <td><?php echo $l['details']; ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
