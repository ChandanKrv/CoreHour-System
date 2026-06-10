<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'your_database_name';
$user = 'your_db_username';
$pass = 'your_db_password';


$charset = 'utf8mb4';

// SMTP Configuration (Used for From Headers)
define('SMTP_USER', 'contact@chandankrv.com');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Mail Helper Function (Using Native PHP Mail with strict headers)
function send_otp_email($to_email, $otp_token) {
    $subject = "CoreHour System - Password Reset OTP";
    
    // HTML Message Body
    $message = "
    <html>
    <head>
    <title>Password Reset</title>
    </head>
    <body style='font-family: monospace;'>
        <h3>Authentication Request</h3>
        <p>Your one-time recovery token is:</p>
        <h2 style='background: #212529; color: #fff; padding: 10px; display: inline-block;'>{$otp_token}</h2>
        <p>This token expires in 15 minutes.</p>
    </body>
    </html>
    ";

    // Standard headers required for successful native mail delivery
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CoreHour System <" . SMTP_USER . ">" . "\r\n";
    $headers .= "Reply-To: " . SMTP_USER . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Send the email
    return mail($to_email, $subject, $message, $headers);
}
?>