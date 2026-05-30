<?php
include 'config.php';
checkLogin();

if (!isAdmin()) {
    die("<h1 style='color:red; text-align:center; margin-top:50px;'>BẢO MẬT: Bạn không có quyền truy cập trang lịch sử của Admin!</h1>");
}

// 1. TIẾP NHẬN DỮ LIỆU BỘ LỌC TÌM KIẾM
$filter_room = isset($_GET['filter_room']) ? (int)$_GET['filter_room'] : 0;
$filter_from = isset($_GET['filter_from']) ? mysqli_real_escape_string($conn, $_GET['filter_from']) : '';
$filter_to   = isset($_GET['filter_to']) ? mysqli_real_escape_string($conn, $_GET['filter_to']) : '';

$where_clause = " WHERE l.event_time >= DATE_SUB(NOW(), INTERVAL 30 DAY) ";

if ($filter_room > 0) {
    $where_clause .= " AND l.room_id = $filter_room ";
}
if (!empty($filter_from)) {
    $where_clause .= " AND l.event_time >= '{$filter_from} 00:00:00' ";
}
if (!empty($filter_to)) {
    $where_clause .= " AND l.event_time <= '{$filter_to} 23:59:59' ";
}

// 2. XỬ LÝ XUẤT FILE EXCEL ĐỌC TRỰC TIẾP TỪ CỘT AMOUNT NGUYÊN BẢN
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=BaoCao_DoanhThu_".date('YmdHis').".xls");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    
    echo "\xEF\xBB\xBF"; 
    
    $export_sql = "SELECT l.id, l.event_time, l.event_type, l.amount, l.details, r.room_name FROM room_logs l JOIN rooms r ON l.room_id = r.id $where_clause ORDER BY l.event_time DESC";
    $export_query = mysqli_query($conn, $export_sql);
    
    echo "<table border='1'>";
    echo "<tr>
            <th style='background:#e6e6e6;'>Mã ID</th>
            <th style='background:#e6e6e6;'>Thời Gian Ghi Nhận</th>
            <th style='background:#e6e6e6;'>Tên Phòng Ngủ</th>
            <th style='background:#e6e6e6;'>Phân Loại Sự Kiện</th>
            <th style='background:#e6e6e6;'>Số Tiền Thu Được (đ)</th>
            <th style='background:#e6e6e6;'>Chi Tiết Bản Ghi Hệ Thống</th>
          </tr>";
    
    $total_excel_revenue = 0;
    while ($row = mysqli_fetch_assoc($export_query)) {
        $money = (int)$row['amount'];
        $total_excel_revenue += $money;
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['event_time']}</td>";
        echo "<td>" . htmlspecialchars($row['room_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
        echo "<td style='text-align:right; font-weight:bold;'>{$money}</td>";
        echo "<td>" . htmlspecialchars($row['details']) . "</td>";
        echo "</tr>";
    }
    echo "<tr>
            <td colspan='4' style='text-align:right; font-weight:bold; background:#f1f5f9;'>TỔNG DOANH THU TOÀN PHẦN:</td>
            <td style='text-align:right; font-weight:bold; background:#fef08a; color:red;'>{$total_excel_revenue}</td>
            <td style='background:#f1f5f9;'></td>
          </tr>";
    echo "</table>";
    exit();
}

// 3. THUẬT TOÁN PHÂN TRANG
$rows_per_page = 15; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $rows_per_page;

$count_sql = "SELECT COUNT(*) as total FROM room_logs l $where_clause";
$count_query = mysqli_query($conn, $count_sql);
$count_data = mysqli_fetch_assoc($count_query);
$total_rows = $count_data['total'];
$total_pages = ceil($total_rows / $rows_per_page);

if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $rows_per_page;
}

// 4. TRUY VẤN DỮ LIỆU HIỂN THỊ LÊN GIAO DIỆN WEB (ĐÃ LÀM SẠCH KÝ TỰ ẨN)
$sql = "SELECT l.*, r.room_name FROM room_logs l JOIN rooms r ON l.room_id = r.id" . $where_clause . "ORDER BY l.event_time DESC LIMIT $offset, $rows_per_page";

$all_logs = mysqli_query($conn, $sql);

if (!$all_logs) {
    die("Lỗi truy vấn hệ thống: " . mysqli_error($conn));
}

// ==========================================
// 🔥 THÀNH PHẦN MỚI: TỰ ĐỘNG BẮN CẢNH BÁO TELEGRAM KHI CÓ SỰ KIỆN BẤT THƯỜNG MỚI PHÁT SINH
$check_alert_sql = "SELECT l.*, r.room_name FROM room_logs l JOIN rooms r ON l.room_id = r.id WHERE l.event_type = 'BẤT THƯỜNG' AND l.event_time >= DATE_SUB(NOW(), INTERVAL 10 SECOND) ORDER BY l.id DESC LIMIT 1";
$check_alert_query = mysqli_query($conn, $check_alert_sql);

if (mysqli_num_rows($check_alert_query) > 0) {
    $alert_data = mysqli_fetch_assoc($check_alert_query);
    
    if (function_exists('sendTelegramNotification')) {
        $msg = "🚨 <b>HỆ THỐNG AN NINH KHÁCH SẠN</b>\n";
        $msg .= "🏨 <b>Vị trí:</b> " . $alert_data['room_name'] . "\n";
        $msg .= "⚠️ <b>LOẠI SỰ CỐ:</b> CỬA MỞ BẤT THƯỜNG!\n";
        $msg .= "📝 <b>Chi tiết:</b> " . $alert_data['details'] . "\n";
        $msg .= "⏰ <b>Thời gian:</b> " . $alert_data['event_time'] . "\n";
        $msg .= "❗ <i>Vui lòng rà soát camera hoặc cử nhân viên kiểm tra trực tiếp vị trí phòng trống này ngay!</i>";
        
        sendTelegramNotification($msg);
    }
}
// ==========================================

$rooms_list = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name ASC");
$search_params = "&filter_room=$filter_room&filter_from=$filter_from&filter_to=$filter_to";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hệ thống quản trị tối cao</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 15px; background: #f8fafc; color: #334155; }
        h2 { color: #1e293b; margin-top: 10px; font-size: 18px; font-weight: bold; }
        p { font-size: 13px; color: #64748b; margin-bottom: 15px; }
        .back-link { font-size: 14px; text-decoration: none; color: #007bff; font-weight: bold; display: inline-block; margin-bottom: 10px; }
        
        .filter-section { background: white; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; gap: 5px; width: 100%; }
        .filter-group label { font-weight: bold; font-size: 12px; color: #475569; }
        .filter-group select, .filter-group input { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 15px; width: 100%; box-sizing: border-box; background-color: #f8fafc; }
        
        .btn-container { display: flex; flex-direction: column; gap: 8px; margin-top: 5px; width: 100%; }
        .btn-search { background: #007bff; color: white; border: none; padding: 12px; font-size: 14px; font-weight: bold; border-radius: 6px; cursor: pointer; text-align: center; }
        .btn-excel { background: #28a745; color: white; border: none; padding: 12px; font-size: 14px; font-weight: bold; border-radius: 6px; cursor: pointer; text-decoration: none; display: block; text-align: center; }
        .btn-clear { background: #6c757d; color: white; text-decoration: none; padding: 12px; font-size: 14px; font-weight: bold; border-radius: 6px; text-align: center; display: block; }
        
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white; }
        table { width: 100%; border-collapse: collapse; min-width: 750px; }
        th, td { padding: 12px; font-size: 13px; text-align: left; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
        th { background: #f8fafc; color: #475569; font-weight: bold; }
        
        th:nth-child(1), td:nth-child(1) { min-width: 60px; }  
        th:nth-child(2), td:nth-child(2) { min-width: 140px; } 
        th:nth-child(3), td:nth-child(3) { min-width: 90px; }  
        th:nth-child(4), td:nth-child(4) { min-width: 100px; } 
        th:nth-child(5), td:nth-child(5) { min-width: 110px; } 
        th:nth-child(6), td:nth-child(6) { white-space: normal; min-width: 250px; } 

        .alert-red td { background-color: #ffe4e6 !important; color: #b91c1c !important; font-weight: bold !important; }
        .alert-yellow td { background-color: #fef9c3 !important; color: #a16207 !important; font-weight: bold !important; }
        
        .no-data { text-align: center; padding: 30px; font-style: italic; color: #94a3b8; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block; }
        .badge-letan { background: #e0f2fe; color: #0369a1; } 
        .badge-clean { background: #dcfce7; color: #15803d; } 
        .badge-danger { background: #b91c1c !important; color: #ffffff !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1); } 
        .badge-warning { background: #fef08a !important; color: #854d0e !important; }
        .badge-normal { background: #f1f5f9; color: #475569; } 

        .pagination { display: flex; justify-content: center; align-items: center; margin: 20px 0; gap: 4px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #e2e8f0; text-decoration: none; color: #007bff; border-radius: 6px; font-weight: bold; font-size: 13px; background: white; }
        .pagination a:hover { background-color: #f8fafc; }
        .pagination .active { background-color: #007bff; color: white; border: 1px solid #007bff; }
        .pagination .disabled { color: #cbd5e1; border-color: #f1f5f9; pointer-events: none; background: #f8fafc; }
        .pagination .dots { border: none; color: #94a3b8; background: transparent; padding: 8px 6px; }

        @media (min-width: 768px) {
            body { margin: 30px; }
            h2 { font-size: 22px; }
            p { font-size: 14px; }
            .filter-section { flex-direction: row; align-items: flex-end; gap: 15px; }
            .filter-group { width: auto; }
            .filter-group select, .filter-group input { min-width: 160px; font-size: 14px; padding: 8px 12px; }
            .btn-container { flex-direction: row; width: auto; gap: 10px; }
            .btn-search, .btn-excel, .btn-clear { padding: 9px 18px; width: auto; display: inline-block; font-size: 14px; border-radius: 4px; }
            table { min-width: 100%; }
            th, td { font-size: 14px; }
        }
    </style>
</head>
<body>

    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h2>HỆ THỐNG KIỂM TOÁN VÀ AN NINH TOÀN DIỆN (CHỈ ADMIN)</h2>
    <p>Dưới đây là dữ liệu lịch sử hệ thống được lưu vết trong <b>30 ngày gần nhất</b>.</p>

    <form method="GET" action="admin_logs.php" class="filter-section">
        <div class="filter-group">
            <label>Chọn Phòng Ngủ:</label>
            <select name="filter_room">
                <option value="0">-- Tất cả các phòng --</option>
                <?php while($rm = mysqli_fetch_assoc($rooms_list)): ?>
                    <option value="<?php echo $rm['id']; ?>" <?php echo ($filter_room == $rm['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($rm['room_name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Từ ngày:</label>
            <input type="date" name="filter_from" value="<?php echo htmlspecialchars($filter_from); ?>">
        </div>
        <div class="filter-group">
            <label>Đến ngày:</label>
            <input type="date" name="filter_to" value="<?php echo htmlspecialchars($filter_to); ?>">
        </div>
        <div class="btn-container">
            <button type="submit" class="btn-search">🔍 Tìm kiếm</button>
            <a href="admin_logs.php" class="btn-clear">🔄 Reset</a>
            <a href="?export=excel<?php echo $search_params; ?>" class="btn-excel">📥 Xuất Excel</a>
        </div>
    </form>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Mã ID</th>
                    <th>Thời Gian Ghi Nhận</th>
                    <th>Tên Phòng Ngủ</th>
                    <th>Phân Loại Sự Kiện</th>
                    <th style="color: #28a745; text-align: right;">Số Tiền Thu (đ)</th>
                    <th>Chi Tiết Bản Ghi Hệ Thống</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($all_logs) > 0): ?>
                    <?php while($l = mysqli_fetch_assoc($all_logs)): ?>
                        <?php 
                            $row_class = '';
                            if ($l['event_type'] === 'BẤT THƯỜNG') {
                                $row_class = 'alert-red';
                            } elseif ($l['event_type'] === 'LƯU Ý') {
                                $row_class = 'alert-yellow';
                            }
                            
                            $badge_class = 'badge-normal';
                            if ($l['event_type'] === 'LỄ TÂN') $badge_class = 'badge-letan';
                            elseif ($l['event_type'] === 'DỌN XONG' || $l['event_type'] === 'DỌN PHÒNG') $badge_class = 'badge-clean';
                            elseif ($l['event_type'] === 'BẤT THƯỜNG') $badge_class = 'badge-danger';
                            elseif ($l['event_type'] === 'LƯU Ý') $badge_class = 'badge-warning';

                            $money_display = (int)$l['amount'];
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td><?php echo $l['id']; ?></td>
                            <td><?php echo $l['event_time']; ?></td>
                            <td><b><?php echo htmlspecialchars($l['room_name']); ?></b></td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($l['event_type']); ?>
                                </span>
                            </td>
                            <td style="font-weight: bold; text-align: right;">
                                <?php echo $money_display > 0 ? number_format($money_display, 0, ',', '.') . ' đ' : '-'; ?>
                            </td>
                            <td><?php echo htmlspecialchars($l['details']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="no-data">Không tìm thấy lịch sử phù hợp với điều kiện lọc đã chọn.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a href="?page=1<?php echo $search_params; ?>" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>">« Đầu</a>
            <a href="?page=<?php echo $current_page - 1; ?><?php echo $search_params; ?>" class="<?php echo ($current_page == 1) ? 'disabled' : ''; ?>">‹</a>
            <?php 
            for ($i = 1; $i <= min(3, $total_pages); $i++) {
                $active_class = ($i == $current_page) ? 'active' : '';
                echo "<a href='?page=$i$search_params' class='$active_class'>$i</a>";
            }
            if ($total_pages > 3) {
                if ($current_page > 3) {
                    echo "<span class='dots'>...</span>";
                    echo "<a href='?page=$current_page$search_params' class='active'>$current_page</a>";
                }
                if ($current_page < $total_pages) {
                    echo "<span class='dots'>...</span>";
                }
            }
            ?>
            <a href="?page=<?php echo $current_page + 1; ?><?php echo $search_params; ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>">›</a>
            <a href="?page=<?php echo $total_pages; ?><?php echo $search_params; ?>" class="<?php echo ($current_page == $total_pages) ? 'disabled' : ''; ?>">Cuối »</a>
        </div>
    <?php endif; ?>

</body>
</html>
