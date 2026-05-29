<?php
include 'config.php';
checkLogin();

if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền cấu hình thiết bị!</h1>");
}

$message = "";

// LÝ XỬ LÝ CẬP NHẬT HÀNG LOẠT KHI ẤN NÚT LƯU TẤT CẢ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all_devices'])) {
    $device_data = isset($_POST['device_ids']) ? $_POST['device_ids'] : [];
    $success_count = 0;
    $error_count = 0;

    // Chạy vòng lặp duyệt qua từng phòng gửi lên
    foreach ($device_data as $room_id => $device_id) {
        $room_id = (int)$room_id;
        $device_id = mysqli_real_escape_string($conn, trim($device_id));
        
        // Nếu ô nhập trống thì gán NULL, ngược lại gán chuỗi ID phần cứng
        $sql_update = "UPDATE rooms SET device_id = " . ($device_id === '' ? "NULL" : "'$device_id'") . " WHERE id = $room_id";
        
        if (mysqli_query($conn, $sql_update)) {
            $success_count++;
        } else {
            $error_count++;
        }
    }

    if ($error_count === 0) {
        $message = "<div class='alert success'>🟢 Đã lưu cấu hình hàng loạt thành công cho $success_count phòng!</div>";
    } else {
        $message = "<div class='alert danger'>⚠️ Lưu thành công $success_count phòng, thất bại $error_count phòng. Lỗi: " . mysqli_error($conn) . "</div>";
    }
}

// LẤY DANH SÁCH TẤT CẢ PHÒNG ĐỂ ĐỔ RA FORM
$rooms_q = mysqli_query($conn, "SELECT id, room_name, status, device_id FROM rooms ORDER BY room_name ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cấu hình hàng loạt ID thiết bị</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 15px; background: #f4f7f6; color: #333; }
        h2 { color: #2c3e50; font-size: 18px; margin-top: 10px; }
        p { font-size: 13px; color: #64748b; margin-bottom: 15px; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; display: inline-block; margin-bottom: 15px; }
        
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; margin-bottom: 15px; }
        .alert.success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert.danger { background: #ffe4e6; color: #b91c1c; border: 1px solid #fecdd3; }

        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid #e2e8f0; background: white; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 500px; }
        th, td { padding: 12px; font-size: 14px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        th { background: #f8fafc; color: #475569; font-weight: bold; }
        
        /* Ô nhập liệu tinh gọn */
        .device-input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #f8fafc; font-family: monospace; }
        .device-input:focus { border-color: #007bff; background: white; outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.1); }

        /* Nút lưu tổng */
        .btn-submit-all { display: block; width: 100%; padding: 14px; background: #28a745; color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; box-shadow: 0 4px 6px rgba(40,167,69,0.2); transition: background 0.2s; }
        .btn-submit-all:hover { background: #218838; }

        @media (min-width: 768px) {
            body { margin: 30px; }
            h2 { font-size: 22px; }
            .btn-submit-all { width: auto; padding: 12px 30px; float: right; margin-top: 20px; }
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h2>⚙️ CẤU HÌNH HÀNG LOẠT ID THIẾT BỊ PHÒNG</h2>
    <p>Điền mã ID cảm biến Tuya của tất cả các phòng cùng lúc, sau đó kéo xuống dưới cùng bấm nút để lưu lại toàn bộ.</p>

    <?php echo $message; ?>

    <form method="POST">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 110px;">Tên Phòng</th>
                        <th style="width: 120px;">Trạng Thái</th>
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
                                    <input type="text" 
                                           name="device_ids[<?php echo $r['id']; ?>]" 
                                           class="device-input" 
                                           value="<?php echo htmlspecialchars($r['device_id'] ?? ''); ?>" 
                                           placeholder="Dán Device ID của con cảm biến tương ứng vào đây..." 
                                           autocomplete="off">
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center; color:#94a3b8; padding:20px;">Hệ thống chưa tạo phòng ngủ nào.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <button type="submit" name="save_all_devices" class="btn-submit-all">🚀 LƯU LẠI TOÀN BỘ CẤU HÌNH PHÒNG</button>
    </form>

</body>
</html>
