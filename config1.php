<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kết nối cơ sở dữ liệu MySQL
$conn = mysqli_connect("localhost", "root", "", "hotel_db");
if (!$conn) {
    die("Kết nối database thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

// Thông số cấu hình Telegram Bot của bạn
define('TELE_TOKEN', 'ĐIỀN_TOKEN_BOT_TELEGRAM_VÀO_ĐÂY');
define('TELE_CHAT_ID', 'ĐIỀN_ID_CHAT_CỦA_BẠN_VÀO_ĐÂY');

// Hàm xử lý gửi tin nhắn tự động về điện thoại
function sendTelegramAlert($message) {
    $url = "https://api.telegram.org/bot" . TELE_TOKEN . "/sendMessage?chat_id=" . TELE_CHAT_ID . "&text=" . urlencode($message);
    // Sử dụng hàm an toàn để gọi URL ngầm
    @file_get_contents($url);
}

// Hàm kiểm tra xem người dùng đã đăng nhập chưa
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Hàm kiểm tra nhanh xem tài khoản có phải quyền Admin hay không
function isAdmin() {
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
}
?>
