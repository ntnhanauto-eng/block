<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Cấu hình Database của bạn trên 123HOST
$db_host = "localhost";
$db_user = "nacwxjyg_qlks";      // Thay bằng User DB của bạn
$db_pass = "mDNshduHEJwB2REtKeWU";      // Thay bằng Mật khẩu DB của bạn
$db_name = "nacwxjyg_qlks";     // Thay bằng Tên DB của bạn

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$conn) {
    die("Kết nối cơ sở dữ liệu thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// 2. Cấu hình API Tuya IoT (Dùng cho script lấy dữ liệu ngầm)
define('TUYA_CLIENT_ID', 'qap98nweqkmufpdp5d3r');
define('TUYA_SECRET', 'cb7684adc56045bdb5f77c1d7a541d48');
define('TUYA_API_URL', 'https://openapi.tuyaus.com'); // Thay đổi vùng tùy tài khoản Tuya của bạn (us/eu/cn)

// 3. Các hàm kiểm tra quyền truy cập của hệ thống
function checkLogin() {
    if (!isset($_SESSION['username'])) {
        // Nếu là yêu cầu gọi API ngầm (AJAX/Fetch)
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || strpos($_SERVER['REQUEST_URI'], 'api_') !== false) {
            header('HTTP/1.1 401 Unauthorized');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'msg' => 'Chưa đăng nhập']);
            exit();
        } else {
            // Nếu người dùng vào trực tiếp bằng trình duyệt (như vào index.php) thì mới chuyển hướng
            header("Location: login.php");
            exit();
        }
    }
}

function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}

// 4. Cấu hình Telegram Bot để thông báo về điện thoại
define('TELEGRAM_BOT_TOKEN', '8920696041:AAHpQL-f3vPb3Ddrimnso1pmTSGuIvRingM');
define('TELEGRAM_CHAT_ID', '1733868980');

// Hàm dùng chung để gửi tin nhắn về Telegram ở bất kỳ đâu trong code
function sendTelegramNotification($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML' // Cho phép viết chữ đậm, nghiêng cho đẹp
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}
?>
