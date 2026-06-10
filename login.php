<?php
require_once 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Login - CoreHour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .hero-section { background: linear-gradient(135deg, #212529 0%, #343a40 100%); color: white; border-radius: 12px; padding: 3rem; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 align-items-center justify-content-center">
    <main class="flex-grow-1 d-flex align-items-center justify-content-center w-100 my-5">
        <div class="container">
            <div class="row align-items-center g-5 max-w-1000 mx-auto">
                
                <div class="col-lg-7 d-none d-lg-block">
                    <div class="hero-section h-100 shadow">
                        <h1 class="font-monospace fw-bold mb-4">COREHOUR</h1>
                        <h4 class="mb-4 text-white-50" style="line-height: 1.5;">The ultimate execution ledger for engineers balancing MNC careers and side-hustle empires.</h4>
                        
                        <div class="d-flex align-items-start mb-3">
                            <i class="bi bi-bar-chart-line fs-4 text-info me-3"></i>
                            <div>
                                <h6 class="fw-bold font-monospace mb-1">Dynamic Score Engine</h6>
                                <p class="small text-white-50">Track your momentum. Our algorithm rewards deep work and penalizes distractions to give you a true daily performance score out of 10.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-start mb-3">
                            <i class="bi bi-crosshair fs-4 text-success me-3"></i>
                            <div>
                                <h6 class="fw-bold font-monospace mb-1">Weekly Objectives</h6>
                                <p class="small text-white-50">Stop drifting. Plan your week, categorize your targets, and mark them complete as you crush them.</p>
                            </div>
                        </div>

                        <div class="d-flex align-items-start">
                            <i class="bi bi-shield-check fs-4 text-warning me-3"></i>
                            <div>
                                <h6 class="fw-bold font-monospace mb-1">Data Sovereignty</h6>
                                <p class="small text-white-50">Your data belongs to you. Export your entire 24-hour execution ledger to CSV at any time for your own Pandas analytics.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card shadow p-4 border-0 rounded-4">
                        <div class="text-center mb-4">
                            <h4 class="font-monospace fw-bold m-0">SECURE LOGIN</h4>
                            <span class="small text-muted font-monospace">Access your workspace</span>
                        </div>
                        
                        <div class="alert alert-info border-info border-start border-4 py-3 px-3 mb-4 font-monospace small shadow-sm">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><i class="bi bi-info-circle me-1"></i> Demo Access Available</strong><br>
                                    Email: <strong id="demo-email">demo@corehour.com</strong><br>
                                    Pass: <strong id="demo-pass">demo123</strong>
                                </div>
                                <button type="button" class="btn btn-sm btn-info fw-bold" onclick="fillCredentials()">
                                    <i class="bi bi-pencil-square me-1"></i> Auto-Fill
                                </button>
                            </div>
                        </div>
                        
                        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'registered'): ?>
                            <div class='alert alert-success small text-center font-monospace'>Account created successfully! Please log in.</div>
                        <?php endif; ?>
                        
                        <?php if(isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                            <div class='alert alert-success small text-center font-monospace'>Password reset successfully! Please log in.</div>
                        <?php endif; ?>

                        <?php if($error) echo "<div class='alert alert-danger small text-center font-monospace'>$error</div>"; ?>
                        
                        <form method="POST" id="loginForm">
                            <div class="form-floating mb-3">
                                <input type="email" name="email" class="form-control" id="floatingInput" placeholder="name@example.com" required autofocus>
                                <label for="floatingInput">Email address</label>
                            </div>
                            <div class="form-floating mb-3">
                                <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                                <label for="floatingPassword">Password</label>
                            </div>
                            
                            <div class="d-flex justify-content-end mb-4">
                                <a href="forgot_password.php" class="small text-muted text-decoration-none font-monospace">Forgot Password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-dark btn-lg w-100 font-monospace shadow-sm">Login</button>
                            
                            <div class="text-center mt-4">
                                <span class="small text-muted">No account?</span> <a href="signup.php" class="small fw-bold text-dark text-decoration-none font-monospace border-bottom border-dark">Create Account</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php require_once 'footer.php'; ?>

    <script>
        function fillCredentials() {
            // Fill the inputs only, allowing the user to click login manually
            document.getElementById('floatingInput').value = 'demo@corehour.com';
            document.getElementById('floatingPassword').value = 'demo123';
        }
    </script>
</body>
</html>