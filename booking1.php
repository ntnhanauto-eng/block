<?php
include 'config.php';
checkLogin();

$message = "";
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

$room_q = mysqli_query($conn, "SELECT * FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_q);

if (!$room) {
    die("Phòng không tồn tại!");
}

// XỬ LÝ CHECK-IN (ĐÓN KHÁCH VÀO PHÒNG)
if (isset($_POST['check_in'])) {
    $guest_name = mysqli_real_escape_string($conn, $_POST['guest_name']);
    $cccd       = mysqli_real_escape_string($conn, $_POST['cccd']);
    $price      = (int)$_POST['price'];
    $time_now   = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];

    // Cập nhật trạng thái phòng sang Có Khách Ở
    mysqli_query($conn, "UPDATE rooms SET status = 'khach' WHERE id = $room_id");

    // Ghi nhật ký hệ thống kèm chi tiết thông tin khách thuê
    $details = "Lễ tân [$user_staff] Check-in khách: $guest_name ($cccd) - Giá phòng: ".number_format($price)."đ/giờ";
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', '$details')");
    
    header("Location: index.php");
    exit();
}

// XỬ LÝ CHECK-OUT (TÍNH TIỀN TRẢ PHÒNG & ĐẨY SANG CHẾ ĐỘ VỆ SINH)
if (isset($_POST['check_out'])) {
    $time_now = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];

    // Tìm lại mốc thời gian khách vào từ Log hệ thống gần nhất của phòng này để tính giờ ở
    $log_q = mysqli_query($conn, "SELECT event_time, details FROM room_logs WHERE room_id = $room_id AND details LIKE '%Check-in%' ORDER BY id DESC LIMIT 1");
    $last_log = mysqli_fetch_assoc($log_q);

    $hours = 1; // Mặc định tính tối thiểu 1 giờ
    $price_per_hour = 100000; // Giá mặc định nếu không tìm thấy log cũ

    if ($last_log) {
        $checkin_time = strtotime($last_log['event_time']);
        $checkout_time = time();
        $diff_seconds = $checkout_time - $checkin_time;
        $hours = ceil($diff_seconds / 3600); // Làm tròn lên số giờ sử dụng
        
        // Bóc tách lấy số giá phòng từ chuỗi text lưu trong log cũ
        preg_match('/Giá phòng: (.*?)đ/', $last_log['details'], $matches);
        if(isset($matches[1])) {
            $price_per_hour = (int)str_replace('.', '', $matches[1]);
        }
    }

    $total_bill = $hours * $price_per_hour;

    // Chuyển phòng sang trạng thái Đang Vệ Sinh
    mysqli_query($conn, "UPDATE rooms SET status = 've_sinh' WHERE id = $room_id");

    // Ghi vết vào Log
    $details_out = "Lễ tân [$user_staff] Check-out - Tổng thời gian: $hours giờ - Tổng tiền thu: ".number_format($total_bill)."đ";
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', '$details_out')");

    // In hóa đơn nhanh ra màn hình trước khi đẩy về trang chủ
    echo "<script>
        alert('🧾 HOÁ ĐƠN THANH TOÁN PHÒNG {$room['room_name']}\\n-------------------------------\\nSố giờ sử dụng: $hours giờ\\nĐơn giá: ".number_format($price_per_hour)."đ/giờ\\n👉 TỔNG TIỀN: ".number_format($total_bill)."đ\\n-------------------------------\\nBấm OK để chuyển trạng thái phòng sang ĐANG VỆ SINH!');
        window.location.href = 'index.php';
    </script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Nghiệp Vụ Phòng Khách Sạn</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 5px; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .booking-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #2c3e50; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 15px; }
        .form-group label { font-weight: bold; }
        .form-group input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; color: white; }
        .btn-in { background: #e67e22; }
        .btn-out { background: #e74c3c; }
        .back { text-align: center; margin-top: 15px; display: block; color: #666; }
    </style>
</head>
<body>
<div class="booking-box">
    <h2>🏨 QUẦY PHÒNG: <?php echo htmlspecialchars($room['room_name']); ?></h2>
    <p style="text-align: center; font-weight: bold;">Trạng thái hiện tại: 
        <span style="color: #e67e22;"><?php echo $room['status'] === 'trong' ? 'PHÒNG TRỐNG' : 'ĐANG CÓ KHÁCH'; ?></span>
    </p>

    <?php if ($room['status'] === 'trong'): ?>
        <form method="POST">
            <div class="form-group">
                <label>Tên khách hàng:</label>
                <input type="text" name="guest_name" placeholder="Nguyễn Văn A" required>
            </div>
            <div class="form-group">
                <label>Số CCCD / Passport:</label>
                <input type="text" name="cccd" placeholder="0400XXXXXXXX" required>
            </div>
            <div class="form-group">
                <label>Giá phòng cấu hình (đ/giờ):</label>
                <input type="number" name="price" value="100000" required>
            </div>
            <button type="submit" name="check_in" class="btn btn-in">🔑 XÁC NHẬN NHẬN PHÒNG</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <p style="color: #666; font-style: italic; text-align: center;">Hệ thống sẽ tự động đối soát thời gian check-in trước đó để tính tổng tiền cho lễ tân.</p>
            <button type="submit" name="check_out" class="btn btn-out">💸 TÍNH TIỀN & TRẢ PHÒNG</button>
        </form>
    <?php endif; ?>
    
    <a href="index.php" class="back">← Hủy bỏ và quay lại</a>
</div>
</body>
</html>
