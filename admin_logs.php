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

// Khởi tạo điều kiện SQL cơ bản: Giới hạn cứng trong vòng 30 ngày gần nhất
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

// 2. XỬ LÝ XUẤT FILE EXCEL (Nếu có yêu cầu xuất dữ liệu thì chạy luồng này rồi ngắt trang)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=NhatKy_HeThong_".date('YmdHis').".xls");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private", false);
    
    // Thêm thẻ BOM UTF-8 để Excel hiển thị đúng font Tiếng Việt không bị lỗi hiển thị
    echo "\xEF\xBB\xBF"; 
    
    $export_sql = "SELECT l.*, r.room_name FROM room_logs l JOIN rooms r ON l.room_id = r.id $where_clause ORDER BY l.event_time DESC";
    $export_query = mysqli_query($conn, $export_sql);
    
    echo "<table border='1'>";
    echo "<tr>
            <th style='background:#e6e6e6;'>Mã ID</th>
            <th style='background:#e6e6e6;'>Thời Gian Ghi Nhận</th>
            <th style='background:#e6e6e6;'>Tên Phòng Ngủ</th>
            <th style='background:#e6e6e6;'>Phân Loại Sự Kiện</th>
            <th style='background:#e6e6e6;'>Chi Tiết Bản Ghi Hệ Thống</th>
          </tr>";
    
    while ($row = mysqli_fetch_assoc($export_query)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['event_time']}</td>";
        echo "<td>" . htmlspecialchars($row['room_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['event_type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['details']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    exit();
}

// 3. THUẬT TOÁN PHÂN TRANG (Áp dụng bộ lọc tìm kiếm vào tổng số dòng)
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

// 4. TRUY VẤN DỮ LIỆU HIỂN THỊ LÊN BẢNG TRÌNH DUYỆT
$sql = "SELECT l.*, r.room_name 
        FROM room_logs l 
        JOIN rooms r ON l.room_id = r.id 
        $where_clause 
        ORDER BY l.event_time DESC 
        LIMIT $offset, $rows_per_page";

$all_logs = mysqli_query($conn, $sql);

if (!$all_logs) {
    die("Lỗi truy vấn hệ thống: " . mysqli_error($conn));
}

// Lấy danh sách tất cả các phòng để đổ vào Dropdown bộ lọc tìm kiếm
$rooms_list = mysqli_query($conn, "SELECT id, room_name FROM rooms ORDER BY room_name ASC");

// Giữ lại các tham số tìm kiếm trên URL để khi chuyển trang phân trang không bị mất bộ lọc
$search_params = "&filter_room=$filter_room&filter_from=$filter_from&filter_to=$filter_to";
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
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge-letan { background: #d1ecf1; color: #0c5460; } 
        .badge-clean { background: #d4edda; color: #155724; } 
        .badge-danger { background: #f8d7da; color: #721c24; } 
        .badge-normal { background: #e2e3e5; color: #383d41; } 

        .pagination { display: flex; justify-content: center; align-items: center; margin-top: 25px; gap: 5px; }
        .pagination a, .pagination span { padding: 8px 14px; border: 1px solid #ddd; text-decoration: none; color: #007bff; border-radius: 4px; font-weight: bold; font-size: 14px; }
        .pagination a:hover { background-color: #f1f1f1; }
        .pagination .active { background-color: #007bff; color: white; border: 1px solid #007bff; }
        .pagination .disabled { color: #ccc; border-color: #eee; pointer-events: none; }
        .pagination .dots { border: none; color: #666; }

        /* Khung giao diện thanh bộ lọc tìm kiếm */
        .filter-section { background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #ddd; margin-top: 15px; display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-weight: bold; font-size: 13px; color: #444; }
        .filter-group select, .filter-group input { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; min-width: 150px; }
        .btn-search { background: #007bff; color: white; border: none; padding: 9px 18px; font-size: 14px; font-weight: bold; border-radius: 4px; cursor: pointer; }
        .btn-search:hover { background: #0056b3; }
        .btn-excel { background: #28a745; color: white; border: none; padding: 9px 18px; font-size: 14px; font-weight: bold; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-excel:hover { background: #218838; }
        .btn-clear { background: #6c757d; color: white; text-decoration: none; padding: 9px 15px; font-size: 14px; font-weight: bold; border-radius: 4px; }
        .btn-clear:hover { background: #5a6268; }
    </style>
</head>
<body>
    <a href="index.php" class="back-link">← Quay lại Bảng điều khiển chung</a>
    <h2>HỆ THỐNG KIỂM TOÁN VÀ AN NINH TOÀN DIỆN (CHỈ ADMIN)</h2>
    <p>Dưới đây là dữ liệu toàn bộ lịch sử hệ thống của 3 phòng được lưu vết trong <b>30 ngày gần nhất</b>.</p>

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

        <div>
            <button type="submit" class="btn-search">🔍 Tìm kiếm</button>
            <a href="admin_logs.php" class="btn-clear">🔄 Reset</a>
            <a href="?export=excel<?php echo $search_params; ?>" class="btn-excel">📥 Xuất file Excel</a>
        </div>
    </form>

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
                    <td colspan="5" class="no-data">Không tìm thấy lịch sử phù hợp với điều kiện lọc đã chọn.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

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
