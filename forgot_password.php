<?php
require_once 'db.php';

// Force PHP to use your local timezone to prevent any future time-drift bugs
date_default_timezone_set('Asia/Kolkata'); 

$msg = ''; $msg_class = ''; $step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // STEP 1: Request OTP
    if (isset($_POST['request_otp'])) {
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$email, $otp, $expires]);
            
            if(send_otp_email($email, $otp)) {
                // Added the Spam folder notice here
                $msg = "OTP sent successfully! Please check your Inbox and Spam/Junk folder.";
                $msg_class = "alert-success";
                $_SESSION['reset_email'] = $email;
                $step = 2;
            } else {
                $msg = "Failed to send email. Check your server's mail configuration.";
                $msg_class = "alert-danger";
            }
        } else {
            $msg = "No account found with that email address.";
            $msg_class = "alert-danger";
        }
    } 
    
    // STEP 2: Verify OTP
    elseif (isset($_POST['verify_reset'])) {
        $otp = trim($_POST['otp']);
        $new_pass = $_POST['new_password'];
        $email = $_SESSION['reset_email'] ?? '';

        // FIX: Capture the exact PHP time right now
        $current_php_time = date('Y-m-d H:i:s');

        // FIX: Compare against the exact PHP time, NOT MySQL's NOW() function
        $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND token = ? AND expires_at > ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email, $otp, $current_php_time]);
        
        if ($stmt->fetch()) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?")->execute([$hash, $email]);
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);
            
            unset($_SESSION['reset_email']);
            header('Location: login.php?reset=success');
            exit;
        } else {
            $step = 2;
            $msg = "Invalid or expired OTP. Please request a new one.";
            $msg_class = "alert-danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recover Password - CoreHour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    
    <main class="flex-grow-1 d-flex align-items-center justify-content-center w-100 my-5">
        <div class="container" style="max-width: 450px;">
            <div class="card shadow p-4 border-0 rounded-4">
                
                <div class="text-center mb-4">
                    <h4 class="font-monospace fw-bold m-0">SYSTEM RECOVERY</h4>
                    <span class="small text-muted font-monospace">Reset your access credentials</span>
                </div>
                
                <?php if ($msg): ?>
                    <div class='alert <?= $msg_class ?> small text-center font-monospace'><?= $msg ?></div>
                <?php endif; ?>
                
                <?php if ($step === 1): ?>
                    <p class="small text-muted text-center mb-4">Enter your registered email address. We will send a secure 6-digit one-time passcode to verify your identity.</p>
                    <form method="POST">
                        <div class="form-floating mb-4">
                            <input type="email" name="email" class="form-control" id="floatingEmail" placeholder="name@example.com" required autofocus>
                            <label for="floatingEmail">Email address</label>
                        </div>
                        
                        <button type="submit" name="request_otp" class="btn btn-dark btn-lg w-100 font-monospace shadow-sm mb-3">REQUEST SECURE OTP</button>
                        
                        <div class="text-center">
                            <a href="login.php" class="small fw-bold text-dark text-decoration-none font-monospace border-bottom border-dark">Return to Login</a>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="small text-muted text-center mb-4">Enter the 6-digit code sent to your email and create your new password.</p>
                    <form method="POST">
                        <div class="form-floating mb-3">
                            <input type="text" name="otp" class="form-control text-center font-monospace fs-4 fw-bold" style="letter-spacing: 4px;" id="floatingOTP" placeholder="000000" maxlength="6" required autofocus>
                            <label for="floatingOTP" class="text-center w-100">6-Digit OTP</label>
                        </div>
                        <div class="form-floating mb-4">
                            <input type="password" name="new_password" class="form-control" id="floatingNewPass" placeholder="New Password" required>
                            <label for="floatingNewPass">New Password</label>
                        </div>
                        
                        <button type="submit" name="verify_reset" class="btn btn-success btn-lg w-100 font-monospace shadow-sm mb-3">SECURE & LOGIN</button>
                        
                        <div class="text-center">
                            <a href="forgot_password.php" class="small text-muted text-decoration-none font-monospace">Did not receive it? Request again</a>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php require_once 'footer.php'; ?>
</body>
</html>