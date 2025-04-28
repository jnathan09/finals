<?php
session_start();
include 'db.php';

$message = '';
$token = $_GET['token'] ?? '';

// Check if token is valid
$stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // Handle new password submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $new_password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Check if passwords match
        if ($new_password === $confirm_password) {
            // Hash the new password
            $new_password_hashed = md5($new_password);

            // Update password in the database
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
            $stmt->bind_param("ss", $new_password_hashed, $token);
            $stmt->execute();

            echo "<script>
                alert('Password Reset Successfully');
                window.location.href = 'login.php';
            </script>";
            exit();
        } else {
            $message = "Passwords do not match. Please try again.";
        }
    }
} else {  
    echo "<script>
        alert('Invalid or Expired Token');
        window.location.href = 'login.php';
    </script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Crowning Score</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
</head>
<style>
          body {
            background: linear-gradient(to right, #e0c3fc, #8ec5fc);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
    </style>
<body>

<div class="container mt-5 d-flex justify-content-center">
    <div class="card p-4" style="max-width: 400px; width: 100%;">
        <h3 class="text-center mb-4">Reset Password</h3>
        <?php if ($message): ?>
            <div class="alert alert-info"><?= $message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
    </div>
</div>

<script>
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        });

</script> 

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
