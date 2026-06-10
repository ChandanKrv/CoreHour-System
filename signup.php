<?php
require_once 'db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($name && $email && $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);
            $new_user_id = $pdo->lastInsertId();

            // Initialize Settings
            $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$new_user_id]);
            
            // Initialize Default Categories
            $pdo->prepare("INSERT INTO project_categories (user_id, category_name) VALUES (?, 'Core Platform'), (?, 'Side Business'), (?, 'General Operations')")->execute([$new_user_id, $new_user_id, $new_user_id]);

            // Clone default tracking fields for new user
            $pdo->prepare("INSERT INTO activity_fields (user_id, field_name, group_heading, weight, chart_color) SELECT ?, field_name, group_heading, weight, chart_color FROM default_activity_fields")->execute([$new_user_id]);

            header('Location: login.php?msg=registered');
            exit;
        } catch (PDOException $e) {
            $error = "Email already exists or invalid data provided.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Register - CoreHour</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .hero-section { background: linear-gradient(135deg, #212529 0%, #343a40 100%); color: white; border-radius: 12px; padding: 3rem; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 align-items-center justify-content-center">
    <div class="container my-5">
        <div class="row align-items-center g-5 max-w-1000 mx-auto">
            
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-section h-100 shadow">
                    <h3 class="font-monospace fw-bold mb-4">JOIN COREHOUR</h3>
                    <p class="mb-4 text-white-50" style="line-height: 1.6;">Build the discipline required to scale your personal projects while managing a full-time career.</p>
                    
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 d-flex"><i class="bi bi-check2-circle text-success me-2"></i> <span class="small text-white-50">Track Deep Work vs Distraction</span></li>
                        <li class="mb-3 d-flex"><i class="bi bi-check2-circle text-success me-2"></i> <span class="small text-white-50">Manage Weekly Engineering Objectives</span></li>
                        <li class="mb-3 d-flex"><i class="bi bi-check2-circle text-success me-2"></i> <span class="small text-white-50">Analyze Visual Time Distributions</span></li>
                        <li class="d-flex"><i class="bi bi-check2-circle text-success me-2"></i> <span class="small text-white-50">Completely customizable scoring engine</span></li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow p-4 border-0 rounded-4">
                    <div class="text-center mb-4">
                        <h4 class="font-monospace fw-bold m-0">INITIALIZE ACCOUNT</h4>
                        <span class="small text-muted font-monospace">Deploy your workspace</span>
                    </div>

                    <?php if($error) echo "<div class='alert alert-danger small text-center font-monospace'>$error</div>"; ?>
                    
                    <form method="POST">
                        <div class="form-floating mb-3">
                            <input type="text" name="full_name" class="form-control" id="floatingName" placeholder="John Doe" required autofocus>
                            <label for="floatingName">Full Name</label>
                        </div>
                        <div class="form-floating mb-3">
                            <input type="email" name="email" class="form-control" id="floatingEmail" placeholder="name@example.com" required>
                            <label for="floatingEmail">Email address</label>
                        </div>
                        <div class="form-floating mb-4">
                            <input type="password" name="password" class="form-control" id="floatingPassword" placeholder="Password" required>
                            <label for="floatingPassword">Create Password</label>
                        </div>
                        
                        <button type="submit" class="btn btn-dark btn-lg w-100 font-monospace shadow-sm">Register</button>
                        
                        <div class="text-center mt-4">
                            <span class="small text-muted">Already initialized?</span> <a href="login.php" class="small fw-bold text-dark text-decoration-none font-monospace border-bottom border-dark">Log In Here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once 'footer.php'; ?>
</body>
</html>