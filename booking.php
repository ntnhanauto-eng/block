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
    
    // Loại bỏ tất cả dấu chấm phân cách phần ngàn trước khi lưu vào Database dạng số nguyên
    $price_raw  = str_replace('.', '', $_POST['price']);
    $price      = (int)$price_raw;
    
    $time_now   = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];

    // Cập nhật trạng thái phòng sang Có Khách Ở
    mysqli_query($conn, "UPDATE rooms SET status = 'khach' WHERE id = $room_id");

    // Ghi nhật ký hệ thống kèm chi tiết thông tin khách thuê (Định dạng chuẩn để Check-out đọc)
    $details = "Lễ tân [$user_staff] Check-in khách: $guest_name ($cccd) - Giá phòng: [" . $price . "]đ/giờ";
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', '$details')");
    
    header("Location: index.php");
    exit();
}

// XỬ LÝ CHECK-OUT (TÍNH TIỀN TRẢ PHÒNG CHÍNH XÁC THEO GIÁ CHECK-IN)
if (isset($_POST['check_out'])) {
    $time_now = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];

    // Tìm lại mốc thời gian khách vào từ Log hệ thống gần nhất của phòng này để tính giờ ở
    $log_q = mysqli_query($conn, "SELECT event_time, details FROM room_logs WHERE room_id = $room_id AND details LIKE '%Check-in%' ORDER BY id DESC LIMIT 1");
    $last_log = mysqli_fetch_assoc($log_q);

    $hours = 1; // Mặc định tính tối thiểu 1 giờ
    $price_per_hour = 100000; // Giá phòng thủ hờ mặc định

    if ($last_log) {
        $checkin_time = strtotime($last_log['event_time']);
        $checkout_time = time();
        $diff_seconds = $checkout_time - $checkin_time;
        
        // Tính số giờ thực tế, tối thiểu là 1 giờ
        if ($diff_seconds > 0) {
            $hours = ceil($diff_seconds / 3600); 
        }
        
        // ĐÃ SỬA: Thuật toán bóc tách chính xác số tiền nằm trong dấu ngoặc vuông [ ] lúc Check-in
        if (preg_match('/\[(\d+)\]/', $last_log['details'], $matches)) {
            $price_per_hour = (int)$matches[1]; // Lấy đúng giá phòng lễ tân đã nhập lúc vào
        }
    }

    $total_bill = $hours * $price_per_hour;

    // Chuyển phòng sang trạng thái Đang Vệ Sinh
    mysqli_query($conn, "UPDATE rooms SET status = 've_sinh' WHERE id = $room_id");

    // Ghi vết vào Log hệ thống
    $details_out = "Lễ tân [$user_staff] Check-out - Tổng thời gian: $hours giờ - Tổng tiền thu: " . number_format($total_bill, 0, ',', '.') . "đ";
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', '$details_out')");

    // Định dạng tiền tệ có dấu chấm phân cách phần ngàn hiển thị lên hóa đơn thông báo
    $formatted_price = number_format($price_per_hour, 0, ',', '.');
    $formatted_total = number_format($total_bill, 0, ',', '.');

    echo "<script>
        alert('🧾 HOÁ ĐƠN THANH TOÁN PHÒNG {$room['room_name']}\\n-------------------------------\\nSố giờ sử dụng: $hours giờ\\nĐơn giá gốc: {$formatted_price} đ/giờ\\n👉 TỔNG TIỀN: {$formatted_total} đ\\n-------------------------------\\nBấm OK để chuyển trạng thái phòng sang ĐANG VỆ SINH!');
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
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .booking-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        h2 { text-align: center; color: #2c3e50; margin-top: 0; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 15px; }
        .form-group label { font-weight: bold; color: #444; }
        .form-group input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; color: white; transition: background 0.2s; }
        .btn-in { background: #28a745; }
        .btn-in:hover { background: #218838; }
        .btn-out { background: #dc3545; }
        .btn-out:hover { background: #c82333; }
        .back { text-align: center; margin-top: 15px; display: block; color: #007bff; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="booking-box">
    <h2>🏨 QUẦY PHÒNG: <?php echo htmlspecialchars($room['room_name']); ?></h2>
    <p style="text-align: center; font-weight: bold; font-size: 15px;">Trạng thái hiện tại: 
        <span style="color: <?php echo $room['status'] === 'trong' ? '#28a745' : '#dc3545'; ?>;">
            <?php echo $room['status'] === 'trong' ? '🟢 PHÒNG TRỐNG' : '🔴 ĐANG CÓ KHÁCH'; ?>
        </span>
    </p>

    <?php if ($room['status'] === 'trong'): ?>
        <form method="POST" onsubmit="cleanPriceBeforeSubmit()">
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
                <input type="text" id="price_input" name="price" value="100.000" oninput="formatCurrency(this)" style="font-weight: bold; color: #2c3e50; font-size: 16px;" required>
            </div>
            <button type="submit" name="check_in" class="btn btn-in">🔑 XÁC NHẬN NHẬN PHÒNG</button>
        </form>
    <?php else: ?>
        <form method="POST">
            <p style="color: #666; font-style: italic; text-align: center; line-height: 1.4;">Hệ thống sẽ tự động đối soát chính xác số tiền nhập lúc đầu và số giờ ở thực tế để tính hóa đơn thanh toán.</p>
            <button type="submit" name="check_out" class="btn btn-out">💸 TÍNH TIỀN & TRẢ PHÒNG</button>
        </form>
    <?php endif; ?>
    
    <a href="index.php" class="back">← Hủy bỏ và quay lại</a>
</div>

<script>
// Hàm JavaScript tự động thêm dấu chấm phân cách phần ngàn khi lễ tân gõ tiền
function formatCurrency(input) {
    // Xóa tất cả các ký tự không phải là số
    let value = input.value.replace(/\D/g, '');
    
    // Định dạng lại chuỗi có dấu chấm phân cách phần ngàn
    if (value !== "") {
        value = Number(value).toLocaleString('vi-VN');
    }
    
    input.value = value;
}

// Hàm dọn sạch dấu chấm trước khi form gửi lên Server, tránh lỗi dữ liệu
function cleanPriceBeforeSubmit() {
    let priceInput = document.getElementById('price_input');
    if (priceInput) {
        priceInput.value = priceInput.value.replace(/\./g, '');
    }
}
</script>
</body>
</html>
