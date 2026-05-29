<?php
include 'config.php';
checkLogin();

if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền cấu hình thiết bị!</h1>");
}

$message = "";

// LÝ XỬ LÝ CẬP NHẬT ID THIẾT BỊ KHI ẤN LƯU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_device'])) {
    $room_id = (int)$_POST['room_id'];
    $device_id = mysqli_real_escape_string($conn, trim($_POST['device_id']));
    
    $sql_update = "UPDATE rooms SET device_id = " . ($device_id === '' ? "NULL" : "'$device_id'") . " WHERE id = $room_id";
    
    if (mysqli_query($conn, $sql_update)) {
        $message = "<div class='alert success'>🟢 Cập nhật ID thiết bị thành công!</div>";
    } else {
        $message = "<div class='alert danger'>❌ Lỗi hệ thống: " . mysqli_error($conn) . "</div>";
    }
}

// LẤY DANH SÁCH PHÒNG ĐỂ ĐỔ RA BẢNG
$rooms_q = mysqli_query($conn, "SELECT id, room_name, status, device_id FROM rooms ORDER BY room_name ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Quản lý cấu hình ID thiết bị phòng</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 15px; background: #f4f7f6; color: #333; }
        h2 { color: #2c3e50; font-size: 18px; margin-top: 10px; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; display: inline-block; margin-bottom: 15px; }
        
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 15px; }
        .alert.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert.danger { background: #ffe4e6; color: #b91c1c; border: 1px solid #fecdd3; }

        /* KHUNG DANH SÁCH PHÒNG RESPONSIVE MOBILE */
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { padding: 12px; font-size: 14px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; color: #475569; font-weight: bold; }
        
        /* Form nhập ID thiết bị ngay tại dòng */
        .inline-form { display: flex; gap: 6px; width: 100%; }
        .inline-form input { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; flex: 1; min-width: 140px; background: #f8fafc; font-family: monospace; }
        .inline-form input:focus { border-color: #007bff; background: white; outline: none; }
        .btn-save { background: #007bff; color: white; border: none; padding: 8px 12px; font-size: 13px; font-weight: bold; border-radius: 6px; cursor: pointer; white-space: nowrap; }
        .btn-save:hover { background: #0056b3; }

        @media (min-width: 768px) {
            body { margin: 30px; }
            h2 { font-size: 22px; }
            .inline-form input { font-size: 14px; }
            .btn-save { font-size: 14px; padding: 8px 16px; }
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h2>⚙️ CẤU HÌNH GÁN ID THIẾT BỊ CHO TỪNG PHÒNG</h2>
    <p style="font-size: 13px; color: #64748b; margin-bottom: 15px;">Điền chính xác chuỗi mã ID phần cứng (Device ID) của cảm biến cửa vào ô tương ứng của từng phòng.</p>

    <?php echo $message; ?>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th style="width: 100px;">Tên Phòng</th>
                    <th style="width: 120px;">Trạng Thái Phòng</th>
                    <th>Mã ID Thiết Bị (Cảm Biến Cửa Tuya)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($rooms_q) > 0): ?>
                    <?php while($r = mysqli_fetch_assoc($rooms_q)): ?>
                        <tr>
                            <td><b><?php echo htmlspecialchars($r['room_name']); ?></b></td>
                            <td>
                                <?php 
                                    if($r['status'] === 'trong') echo '<span style="color:#28a745;font-weight:bold;">🟢 Trống</span>';
                                    elseif($r['status'] === 'khach') echo '<span style="color:#dc3545;font-weight:bold;">🔴 Có Khách</span>';
                                    else echo '<span style="color:#ffc107;font-weight:bold;">🟡 Vệ Sinh</span>';
                                ?>
                            </td>
                            <td>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="room_id" value="<?php echo $r['id']; ?>">
                                    <input type="text" name="device_id" value="<?php echo htmlspecialchars($r['device_id'] ?? ''); ?>" placeholder="Ví dụ: eb8f416a247ec..." autocomplete="off">
                                    <button type="submit" name="update_device" class="btn-save">💾 Lưu lại</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; color:#94a3b8; padding:20px;">Hệ thống chưa tạo phòng ngủ nào trong database.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>
