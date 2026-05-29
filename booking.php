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
    'stay_type' => 'gio', 
    'price_rate' => 100000,
    'deposit' => 0,
    'services' => []
];

// Lấy bản ghi Check-in gần nhất của phòng này (Dùng cho cả việc lấy giờ dọn dẹp hoặc giờ khách vào)
$log_checkin_q = mysqli_query($conn, "SELECT event_time, details FROM room_logs WHERE room_id = $room_id AND details LIKE '%Check-in%' ORDER BY id DESC LIMIT 1");
$log_checkin = mysqli_fetch_assoc($log_checkin_q);

if ($room['status'] === 'khach') {
    if ($log_checkin) {
        $guest_info['checkin_time'] = $log_checkin['event_time'];
        
        if (preg_match('/Check-in khách: (.*?) \((.*?)\) - Số người: (\d+)/', $log_checkin['details'], $matches)) {
            $guest_info['name'] = $matches[1];
            $guest_info['cccd'] = $matches[2];
            $guest_info['guests_count'] = (int)$matches[3];
        }
        if (preg_match('/Hình thức: \[(.*?)\]/', $log_checkin['details'], $type_matches)) {
            $guest_info['stay_type'] = $type_matches[1]; 
        }
        if (preg_match('/Giá phòng: \[(\d+)\]/', $log_checkin['details'], $price_matches)) {
            $guest_info['price_rate'] = (int)$price_matches[1];
        }
        if (preg_match('/Ứng trước: \[(\d+)\]/', $log_checkin['details'], $deposit_matches)) {
            $guest_info['deposit'] = (int)$deposit_matches[1];
        }
    }

    // ĐÃ SỬA: Nếu không tìm thấy checkin_time, ta lấy dịch vụ trong vòng 24h qua để bill KHÔNG BAO GIỜ BỊ ẨN
    $time_limit = ($guest_info['checkin_time'] !== 'Không rõ') ? $guest_info['checkin_time'] : date('Y-m-d H:i:s', strtotime('-1 day'));
    
    $services_q = mysqli_query($conn, "SELECT event_time, details, amount FROM room_logs WHERE room_id = $room_id AND event_type = 'DỊCH VỤ' AND event_time >= '$time_limit' ORDER BY id ASC");
    while ($srv = mysqli_fetch_assoc($services_q)) {
        $guest_info['services'][] = $srv;
    }
}

// 3. XỬ LÝ CHECK-IN (ĐÓN KHÁCH VÀO PHÒNG)
if (isset($_POST['check_in'])) {
    $guest_name   = mysqli_real_escape_string($conn, $_POST['guest_name']);
    $cccd         = mysqli_real_escape_string($conn, $_POST['cccd']);
    $guests_count = (int)$_POST['guests_count'];
    $stay_type    = mysqli_real_escape_string($conn, $_POST['stay_type']); 
    $price        = (int)str_replace('.', '', $_POST['price']);
    $deposit      = (int)str_replace('.', '', $_POST['deposit']);
    $time_now     = date('Y-m-d H:i:s');
    $user_staff   = $_SESSION['username'];

    mysqli_query($conn, "UPDATE rooms SET status = 'khach' WHERE id = $room_id");

    $stay_type_vn = ($stay_type === 'ngay') ? 'Theo Ngày' : 'Theo Giờ';
    $unit_vn = ($stay_type === 'ngay') ? 'ngày' : 'giờ';
    $formatted_price = number_format($price, 0, ',', '.');
    $formatted_deposit = number_format($deposit, 0, ',', '.');
    
    $details = "Lễ tân [$user_staff] Check-in khách: $guest_name ($cccd) - Số người: $guests_count - Hình thức: [{$stay_type}] ({$stay_type_vn}) - Giá phòng: [{$price}] ({$formatted_price}đ/{$unit_vn}) - Ứng trước: [{$deposit}] ({$formatted_deposit}đ)";
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', 0, '$details')");
    
    if (function_exists('sendTelegramNotification')) {
        sendTelegramNotification("🛎️ <b>THÔNG BÁO KHÁCH NHẬN PHÒNG</b>\n🏨 <b>{$room['room_name']}</b>\n👤 Lễ tân: <code>$user_staff</code>\n📝 Khách hàng: <b>$guest_name</b>\n🔄 Hình thức: <b>$stay_type_vn</b>\n💵 Tiền cọc ứng trước: <b>{$formatted_deposit}đ</b>");
    }
    header("Location: index.php");
    exit();
}

// XỬ LÝ HOÀN TẤT VỆ SINH BUỒNG PHÒNG (MỚI BỔ SUNG)
if (isset($_POST['finish_cleaning'])) {
    $time_now = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];
    mysqli_query($conn, "UPDATE rooms SET status = 'trong' WHERE id = $room_id");
    
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', 0, 'Nhân viên [$user_staff] hoàn tất dọn dẹp vệ sinh phòng')");
    
    if (function_exists('sendTelegramNotification')) {
        sendTelegramNotification("🧹 <b>VỆ SINH BUỒNG PHÒNG:</b>\n🏨 <b>{$room['room_name']}</b>\n🟢 Đã dọn dẹp xong. Phòng sẵn sàng đón khách mới!");
    }
    header("Location: index.php");
    exit();
}

// 4. XỬ LÝ GỌI THÊM ĐỒ (DỊCH VỤ)
if (isset($_POST['add_service'])) {
    $service_name  = mysqli_real_escape_string($conn, $_POST['service_name']);
    $service_price = (int)str_replace('.', '', $_POST['service_price']);
    $time_now      = date('Y-m-d H:i:s');
    $user_staff    = $_SESSION['username'];

    $formatted_srv_price = number_format($service_price, 0, ',', '.');
    $details_srv = "Khách gọi thêm: $service_name - Tiền dịch vụ: ({$formatted_srv_price}đ)";
    
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'DỊCH VỤ', $service_price, '$details_srv')");
    
    if (function_exists('sendTelegramNotification')) {
        sendTelegramNotification("🛒 <b>DỊCH VỤ PHÁT SINH:</b>\n🏨 <b>{$room['room_name']}</b>\n📦 Đồ gọi thêm: $service_name\n💰 Số tiền: {$formatted_srv_price}đ");
    }
    header("Location: booking.php?room_id=$room_id&open_panel=1");
    exit();
}

// 5. XỬ LÝ CHECK-OUT (TÍNH TỔNG HOÁ ĐƠN VÀ TRẢ PHÒNG)
if (isset($_POST['check_out'])) {
    $time_now = date('Y-m-d H:i:s');
    $user_staff = $_SESSION['username'];

    $units = 1;
    $price_rate = $guest_info['price_rate'];
    $stay_type = $guest_info['stay_type'];

    if ($guest_info['checkin_time'] !== 'Không rõ') {
        $checkin_time = strtotime($guest_info['checkin_time']);
        $checkout_time = time();
        $diff_seconds = $checkout_time - $checkin_time;
        if ($diff_seconds > 0) {
            $units = ($stay_type === 'ngay') ? ceil($diff_seconds / 86400) : ceil($diff_seconds / 3600);
        }
    }

    $room_bill = $units * $price_rate;
    $total_services_bill = 0;
    foreach ($guest_info['services'] as $srv) {
        $total_services_bill += (int)$srv['amount'];
    }

    $subtotal = $room_bill + $total_services_bill;
    $deposit = $guest_info['deposit'];
    $total_bill = $subtotal - $deposit;

    mysqli_query($conn, "UPDATE rooms SET status = 've_sinh' WHERE id = $room_id");

    $unit_label = ($stay_type === 'ngay') ? 'ngày' : 'giờ';
    $details_out = "Lễ tân [$user_staff] Check-out - Tổng: $units $unit_label (Tiền phòng: " . number_format($room_bill, 0, ',', '.') . "đ) - Tiền dịch vụ: " . number_format($total_services_bill, 0, ',', '.') . "đ - Đã ứng trước: " . number_format($deposit, 0, ',', '.') . "đ -> Tổng thu thêm: " . number_format($total_bill, 0, ',', '.') . "đ";
    
    mysqli_query($conn, "INSERT INTO room_logs (room_id, event_time, event_type, amount, details) VALUES ($room_id, '$time_now', 'LỄ TÂN', $total_bill, '$details_out')");

    if (function_exists('sendTelegramNotification')) {
        $stay_type_vn = ($stay_type === 'ngay') ? 'Theo Ngày' : 'Theo Giờ';
        sendTelegramNotification("💰 <b>THÔNG BÁO TRẢ PHÒNG (CHECK-OUT)</b>\n🏨 <b>{$room['room_name']}</b>\n👤 Lễ tân: <code>$user_staff</code>\n⏱️ Thời gian dùng: <b>$units $unit_label</b> ($stay_type_vn)\n💳 Tổng hóa đơn: " . number_format($subtotal, 0, ',', '.') . "đ\n📉 Đã trừ tiền cọc: -" . number_format($deposit, 0, ',', '.') . "đ\n---------------------------------\n🧮 <b>👉 TỔNG TIỀN THU THÊM: " . number_format($total_bill, 0, ',', '.') . "đ</b>");
    }

    $f_room_bill = number_format($room_bill, 0, ',', '.');
    $f_srv_bill  = number_format($total_services_bill, 0, ',', '.');
    $f_subtotal  = number_format($subtotal, 0, ',', '.');
    $f_deposit   = number_format($deposit, 0, ',', '.');
    $f_total     = number_format($total_bill, 0, ',', '.');

    echo "<script>
        alert('🧾 HOÁ ĐƠN THANH TOÁN TỔNG HỢP: {$room['room_name']}\\n-------------------------------------\\n⏱️ Tiền phòng ($units $unit_label): {$f_room_bill} đ\\n🛒 Tiền dịch vụ phát sinh: {$f_srv_bill} đ\\n-------------------------------------\\n💵 Tổng cộng hóa đơn: {$f_subtotal} đ\\n📉 Tiền khách đã ứng trước: -{$f_deposit} đ\\n-------------------------------------\\n👉 TỔNG TIỀN THU THÊM: {$f_total} đ\\n-------------------------------------\\nBấm OK để chuyển sang trạng thái ĐANG VỆ SINH!');
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
        .form-group input, .form-group select { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px; }
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
            <span style="color: <?php echo ($room['status'] === 'trong') ? '#28a745' : (($room['status'] === 'khach') ? '#dc3545' : '#ffc107'); ?>;">
                <?php 
                    if($room['status'] === 'trong') echo '🟢 PHÒNG TRỐNG';
                    elseif($room['status'] === 'khach') echo '🔴 ĐANG CÓ KHÁCH';
                    else echo '🟡 ĐANG VỆ SINH';
                ?>
            </span>
        </p>

        <?php if ($room['status'] === 'trong'): ?>
            <form method="POST" onsubmit="cleanAllPrices()">
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
                    <label>Hình thức thuê phòng:</label>
                    <select name="stay_type" id="stay_type" onchange="updatePriceLabel(this.value)">
                        <option value="gio">Ở theo giờ (đ/giờ)</option>
                        <option value="ngay">Ở theo ngày (đ/ngày)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label id="price_label">Giá phòng cấu hình (đ/giờ):</label>
                    <input type="text" id="price_input" name="price" value="100.000" oninput="formatCurrency(this)" style="font-weight: bold; color: #2c3e50;" required>
                </div>
                <div class="form-group">
                    <label style="color: #0056b3;">Tiền ứng trước / Tiền cọc (đ):</label>
                    <input type="text" id="deposit_input" name="deposit" value="0" oninput="formatCurrency(this)" style="font-weight: bold; color: #0056b3;" required>
                </div>
                <button type="submit" name="check_in" class="btn btn-in">🔑 XÁC NHẬN NHẬN PHÒNG</button>
            </form>

        <?php elseif ($room['status'] === 've_sinh'): ?>
            <div style="text-align: center; padding: 20px 0;">
                <p style="font-size: 16px; color: #555;">Phòng này vừa trả khách và đang được nhân viên làm vệ sinh buồng phòng.</p>
                <form method="POST">
                    <button type="submit" name="finish_cleaning" class="btn btn-in" style="background: #28a745;">✨ HOÀN TẤT VỆ SINH (CHUYỂN THÀNH PHÒNG TRỐNG)</button>
                </form>
            </div>

        <?php else: ?>
            <button class="btn-toggle" onclick="toggleSlidePanel()">📋 XEM THÔNG TIN LƯU TRÚ & ORDER</button>
            
            <div id="guestSlidePanel" class="slide-panel">
                <div class="info-row"><span>👤 Khách hàng:</span><span><?php echo htmlspecialchars($guest_info['name']); ?></span></div>
                <div class="info-row"><span>🪪 CCCD/Passport:</span><span><?php echo htmlspecialchars($guest_info['cccd']); ?></span></div>
                <div class="info-row"><span>👥 Số người ở:</span><span><?php echo $guest_info['guests_count']; ?> người</span></div>
                <div class="info-row"><span>🔄 Hình thức thuê:</span><span><?php echo ($guest_info['stay_type'] === 'ngay') ? '📅 Theo Ngày' : '⏱️ Theo Giờ'; ?></span></div>
                <div class="info-row"><span>⏰ Giờ nhận phòng:</span><span><?php echo date('d/m H:i', strtotime($guest_info['checkin_time'])); ?></span></div>
                <div class="info-row"><span>💰 Đơn giá thuê:</span><span><?php echo number_format($guest_info['price_rate'], 0, ',', '.'); ?> đ/<?php echo ($guest_info['stay_type'] === 'ngay') ? 'ngày' : 'h'; ?></span></div>
                <div class="info-row" style="background: #e2f0d9;"><span>💵 Đã ứng trước:</span><span style="color: #28a745;"><?php echo number_format($guest_info['deposit'], 0, ',', '.'); ?> đ</span></div>

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
                                $s_price = (int)$srv['amount'];
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

function cleanAllPrices() {
    cleanPriceBeforeSubmit('price_input');
    cleanPriceBeforeSubmit('deposit_input');
}

function updatePriceLabel(val) {
    const label = document.getElementById('price_label');
    if (val === 'ngay') {
        label.innerText = "Giá phòng cấu hình (đ/ngày):";
        if(document.getElementById('price_input').value === '100.000') {
            document.getElementById('price_input').value = '350.000';
        }
    } else {
        label.innerText = "Giá phòng cấu hình (đ/giờ):";
        if(document.getElementById('price_input').value === '350.000') {
            document.getElementById('price_input').value = '100.000';
        }
    }
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
