<?php
include 'config.php';
checkLogin();

$message = "";
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

// 1. Lấy thông tin cơ bản của phòng hiện tại
$room_q = mysqli_query($conn, "SELECT * FROM rooms WHERE id = $room_id");
$room = mysqli_fetch_assoc($room_q);

if (!$room) {
    die("Phòng không tồn tại!");
}

// 2. TRUY VẤN THÔNG TIN KHÁCH ĐANG LƯU TRÚ (NẾU PHÒNG CÓ KHÁCH)
$guest_info = [
    'name' => 'Không rõ',
    'cccd' => 'Không rõ',
    'guests_count' => 1,
    'checkin_time' => 'Không rõ',
    'price_per_hour' => 100000,
    'services' => []
];

if ($room['status'] === 'khach') {
    // Tìm lại bản ghi Check-in gần nhất của phòng này
    $log_checkin_q = mysqli_query($conn, "SELECT event_time, details FROM room_logs WHERE room_id = $room_id AND details LIKE '%Check-in%' ORDER BY id DESC LIMIT 1");
    $log_checkin = mysqli_fetch_assoc($log_checkin_q);

    if ($log_checkin) {
        $guest_info['checkin_time'] = $log_checkin['event_time'];
        
        if (preg_match('/Check-in khách: (.*?) \((.*?)\) - Số người: (\d+)/', $log_checkin['details'], $matches)) {
            $guest_info['name'] = $matches[1];
            $guest_info['cccd'] = $matches[2];
            $guest_info['guests_count'] = (int)$matches[3];
        }
        if (preg_match('/\[(\d+)\]/', $log_checkin['details'], $price_matches)) {
            $guest_info['price_per_hour'] = (int)$price_matches[1];
        }
    }

    // Tìm tất cả các dịch vụ phát sinh đã gọi thêm trong ca này (Lấy trực tiếp từ cột amount mới)
    $services_q = mysqli_query($conn, "SELECT event_time, details, amount FROM room_logs WHERE room_id = $room_id AND event_type = 'DỊCH VỤ' AND event_time >= '{$guest_info['checkin_time']}' ORDER BY id ASC");
    while ($srv = mysqli_fetch_assoc($services_q)) {
        $guest_info['services'][] = $srv;
    }
}

// 3. XỬ LÝ CHECK-IN (ĐÓN KHÁCH VÀO PHÒNG)
if (isset($_POST['check_in'])) {
    $guest_name   = mysqli_real_escape_string($conn, $_POST['guest_name']);
    $cccd         = mysqli_real_escape_string($conn, $_POST['cccd']);
    $guests_count = (int)$_POST['guests_count'];
    $price        = (int)str_replace('.', '', $_POST['price']);
    $time_now     = date('Y-m-d H:i:s');
    $user_staff   = $_SESSION['username'];

    mysqli_query($conn, "UPDATE rooms SET status = 'khach' WHERE id = $room_id");

    $formatted_price_log = number_format($price, 0, ',', '.');
    $details = "Lễ tân [$user_staff] Check-in khách: $guest_name ($cccd) - Số người: $guests_count - Giá phòng: [{$price}] ({$formatted_price_log}đ/giờ)";
    
    // Check-in chưa thu tiền tổng nên amount để mặc định là 0
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', 0, '$details')");
    
    header("Location: index.php");
    exit();
}

// 4. XỬ LÝ GỌI THÊM ĐỒ (BẮT ĐẦU ĐẨY TIỀN THẲNG VÀO CỘT AMOUNT)
if (isset($_POST['add_service'])) {
    $service_name  = mysqli_real_escape_string($conn, $_POST['service_name']);
    $service_price = (int)str_replace('.', '', $_POST['service_price']);
    $time_now      = date('Y-m-d H:i:s');
    $user_staff    = $_SESSION['username'];

    $formatted_srv_price = number_format($service_price, 0, ',', '.');
    $details_srv = "Khách gọi thêm: $service_name - Tiền dịch vụ: ({$formatted_srv_price}đ)";
    
    // ĐÃ NÂNG CẤP: Lưu chính xác số tiền vào cột amount mới
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'DỊCH VỤ', $service_price, '$details_srv')");
    
    if (function_exists('sendTelegramNotification')) {
        sendTelegramNotification("🛒 <b>DỊCH VỤ PHÁT SINH:</b>\n🏨 <b>{$room['room_name']}</b>\n📦 Đồ gọi thêm: $service_name\n💰 Số tiền: {$formatted_srv_price}đ");
    }

    header("Location: booking.php?room_id=$room_id&open_panel=1");
    exit();
}

// 5. XỬ LÝ CHECK-OUT (TÍNH TỔNG HOÁ ĐƠN - ĐẨY TIỀN TỔNG THU VÀO CỘT AMOUNT)
if (isset($_POST['check_out'])) {
    $time_now = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];

    $hours = 1;
    $price_per_hour = $guest_info['price_per_hour'];

    if ($guest_info['checkin_time'] !== 'Không rõ') {
        $checkin_time = strtotime($guest_info['checkin_time']);
        $checkout_time = time();
        $diff_seconds = $checkout_time - $checkin_time;
        if ($diff_seconds > 0) {
            $hours = ceil($diff_seconds / 3600); 
        }
    }

    $room_bill = $hours * $price_per_hour;

    // Tính tổng tiền dịch vụ siêu nhanh bằng việc cộng dồn cột amount
    $total_services_bill = 0;
    foreach ($guest_info['services'] as $srv) {
        $total_services_bill += (int)$srv['amount'];
    }

    $total_bill = $room_bill + $total_services_bill;

    mysqli_query($conn, "UPDATE rooms SET status = 've_sinh' WHERE id = $room_id");

    $details_out = "Lễ tân [$user_staff] Check-out - Tổng: $hours giờ (Tiền phòng: " . number_format($room_bill, 0, ',', '.') . "đ) - Tiền dịch vụ thêm: " . number_format($total_services_bill, 0, ',', '.') . "đ -> Tổng thu: " . number_format($total_bill, 0, ',', '.') . "đ";
    
    // ĐÃ NÂNG CẤP: Khóa chặt TỔNG TIỀN CUỐI CÙNG thu được của khách vào cột amount của bản ghi Check-out
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', $total_bill, '$details_out')");

    $f_room_bill = number_format($room_bill, 0, ',', '.');
    $f_srv_bill  = number_format($total_services_bill, 0, ',', '.');
    $f_total     = number_format($total_bill, 0, ',', '.');

    echo "<script>
        alert('🧾 HOÁ ĐƠN THANH TOÁN TỔNG HỢP: {$room['room_name']}\\n-------------------------------------\\n⏱️ Tiền phòng ($hours giờ): {$f_room_bill} đ\\n🛒 Tiền dịch vụ phát sinh: {$f_srv_bill} đ\\n-------------------------------------\\n👉 TỔNG TIỀN PHẢI THU: {$f_total} đ\\n-------------------------------------\\nBấm OK để chuyển trạng thái phòng sang ĐANG VỆ SINH!');
        window.location.href = 'index.php';
    </script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nghiệp Vụ Phòng Khách Sạn</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f4f7f6; display: flex; justify-content: center; align-items: flex-start; }
        .container { width: 100%; max-width: 450px; display: flex; flex-direction: column; gap: 20px; }
        .box { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); box-sizing: border-box; }
        h2 { text-align: center; color: #2c3e50; margin-top: 0; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        .form-group label { font-weight: bold; color: #444; font-size: 14px; }
        .form-group input { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 4px; font-size: 16px; font-weight: bold; cursor: pointer; color: white; transition: background 0.2s; margin-top: 5px; }
        .btn-in { background: #28a745; }
        .btn-srv { background: #e67e22; }
        .btn-out { background: #dc3545; }
        .back { text-align: center; margin-top: 15px; display: block; color: #007bff; text-decoration: none; font-weight: bold; }
        .slide-panel { display: none; overflow: hidden; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 15px; margin-top: 15px; }
        .btn-toggle { background: #007bff; color: white; border: none; width: 100%; padding: 10px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; margin-bottom: 10px; }
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px dashed #e2e8f0; font-size: 14px; }
        .info-row span:first-child { color: #64748b; font-weight: 500; }
        .info-row span:last-child { color: #1e293b; font-weight: bold; }
        .srv-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; background: white; }
        .srv-table th, .srv-table td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        .srv-table th { background: #f1f5f9; color: #475569; }
        .total-row { background: #fef08a !important; font-weight: bold; font-size: 14px; color: #b45309; text-align: right !important; }
    </style>
</head>
<body>
<div class="container">

    <div class="box">
        <h2>🏨 QUẦY PHÒNG: <?php echo htmlspecialchars($room['room_name']); ?></h2>
        <p style="text-align: center; font-weight: bold; font-size: 15px;">Trạng thái: 
            <span style="color: <?php echo $room['status'] === 'trong' ? '#28a745' : '#dc3545'; ?>;">
                <?php echo $room['status'] === 'trong' ? '🟢 PHÒNG TRỐNG' : '🔴 ĐANG CÓ KHÁCH'; ?>
            </span>
        </p>

        <?php if ($room['status'] === 'trong'): ?>
            <form method="POST" onsubmit="cleanPriceBeforeSubmit('price_input')">
                <div class="form-group">
                    <label>Tên khách hàng:</label>
                    <input type="text" name="guest_name" placeholder="Nguyễn Văn A" required>
                </div>
                <div class="form-group">
                    <label>Số CCCD / Passport:</label>
                    <input type="text" name="cccd" placeholder="0400XXXXXXXX" required>
                </div>
                <div class="form-group">
                    <label>Số người lưu trú:</label>
                    <input type="number" name="guests_count" value="1" min="1" max="10" required>
                </div>
                <div class="form-group">
                    <label>Giá phòng cấu hình (đ/giờ):</label>
                    <input type="text" id="price_input" name="price" value="100.000" oninput="formatCurrency(this)" style="font-weight: bold; color: #2c3e50;" required>
                </div>
                <button type="submit" name="check_in" class="btn btn-in">🔑 XÁC NHẬN NHẬN PHÒNG</button>
            </form>
        <?php else: ?>
            <button class="btn-toggle" onclick="toggleSlidePanel()">📋 XEM THÔNG TIN LƯU TRÚ & ORDER</button>
            
            <div id="guestSlidePanel" class="slide-panel">
                <div class="info-row"><span>👤 Khách hàng:</span><span><?php echo htmlspecialchars($guest_info['name']); ?></span></div>
                <div class="info-row"><span>🪪 CCCD/Passport:</span><span><?php echo htmlspecialchars($guest_info['cccd']); ?></span></div>
                <div class="info-row"><span>👥 Số người ở:</span><span><?php echo $guest_info['guests_count']; ?> người</span></div>
                <div class="info-row"><span>⏰ Giờ nhận phòng:</span><span><?php echo date('d/m H:i', strtotime($guest_info['checkin_time'])); ?></span></div>
                <div class="info-row"><span>💰 Đơn giá phòng:</span><span><?php echo number_format($guest_info['price_per_hour'], 0, ',', '.'); ?> đ/h</span></div>

                <h4 style="margin: 15px 0 5px 0; color: #e67e22; border-bottom: 2px solid #e67e22; padding-bottom:3px;">🛒 CÁC MÓN ĐÃ ORDER THÊM:</h4>
                <table class="srv-table">
                    <thead>
                        <tr><th>Tên Món Ăn/Dịch Vụ</th><th style="text-align: right;">Đơn Giá</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sum_services_price = 0;
                        if (count($guest_info['services']) > 0): 
                            foreach ($guest_info['services'] as $srv):
                                $s_name = "Đồ gọi thêm";
                                if (preg_match('/Khách gọi thêm: (.*?) - Tiền dịch vụ:/', $srv['details'], $n_matches)) { $s_name = $n_matches[1]; }
                                $s_price = (int)$srv['amount']; // Đọc trực tiếp từ cột số nguyên amount
                                $sum_services_price += $s_price;
                        ?>
                            <tr>
                                <td>🟢 <?php echo htmlspecialchars($s_name); ?></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format($s_price, 0, ',', '.'); ?> đ</td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="2" style="text-align: center; color:#94a3b8; font-style:italic;">Khách chưa gọi thêm đồ.</td></tr>
                        <?php endif; ?>
                        
                        <tr>
                            <td class="total-row">💵 TỔNG TIỀN DỊCH VỤ:</td>
                            <td class="total-row" style="text-align: right;"><?php echo number_format($sum_services_price, 0, ',', '.'); ?> đ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <fieldset style="border: 1px solid #e67e22; border-radius: 6px; padding: 12px; margin-top: 15px;">
                <legend style="color: #e67e22; font-weight:bold; padding: 0 6px;">🛒 THÊM MÓN / DỊCH VỤ</legend>
                <form method="POST" onsubmit="cleanPriceBeforeSubmit('srv_price_input')">
                    <div class="form-group">
                        <input type="text" name="service_name" placeholder="Ví dụ: 2 chai nước suối" required>
                    </div>
                    <div class="form-group">
                        <input type="text" id="srv_price_input" name="service_price" placeholder="Đơn giá tiền (đ)" oninput="formatCurrency(this)" style="font-weight: bold; color: #e67e22;" required>
                    </div>
                    <button type="submit" name="add_service" class="btn btn-srv">➕ THÊM MÓN</button>
                </form>
            </fieldset>

            <form method="POST" style="margin-top: 15px;">
                <button type="submit" name="check_out" class="btn btn-out">💸 TÍNH TIỀN & TRẢ PHÒNG</button>
            </form>
        <?php endif; ?>
        
        <a href="index.php" class="back">← Quay lại trang chủ</a>
    </div>

</div>

<script>
function formatCurrency(input) {
    let value = input.value.replace(/\D/g, '');
    if (value !== "") { value = Number(value).toLocaleString('vi-VN'); }
    input.value = value;
}

function cleanPriceBeforeSubmit(id) {
    let priceInput = document.getElementById(id);
    if (priceInput) { priceInput.value = priceInput.value.replace(/\./g, ''); }
}

function toggleSlidePanel() {
    let panel = document.getElementById("guestSlidePanel");
    panel.style.display = (panel.style.display === "block") ? "none" : "block";
}

const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('open_panel') === '1') {
    document.getElementById("guestSlidePanel").style.display = "block";
}
</script>
</body>
</html>
