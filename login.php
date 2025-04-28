<?php
session_start();
include 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // This loads PHPMailer via Composer


$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Login process
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (md5($password) == $user['password']) {
                $_SESSION['user'] = $user;
                
                // Redirect based on role
                if ($user['role'] == 'admin') {
                    header("Location: admin_dashboard.php");
                    exit(); // Ensure no further code is executed after the redirect
                } else if ($user['role'] == 'judge') {
                    header("Location: judge_dashboard.php");
                    exit(); // Ensure no further code is executed after the redirect
                }
            } else {
                $message = "Incorrect password!";
            }
        } else {
            $message = "User not found!";
        }
    }

    // Forgot password process
    if (isset($_POST['forgot_password'])) {
        $email = $_POST['email_forgot'];

        // Check if email exists in the database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Generate a unique password reset token
            $token = bin2hex(random_bytes(50)); // Generate a secure token

            // Store the token in the database with an expiration time
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
            $stmt->bind_param("ss", $token, $email);
            $stmt->execute();

            // Send password reset email using PHPMailer
            require 'vendor/autoload.php'; // Load PHPMailer
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'systemtabulation30@gmail.com'; 
                $mail->Password = 'tijv rcow ebag slam';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('systemtabulation30@gmail.com', 'Crown Tabulation System');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "Click <a href='http://localhost/crown/reset_password.php?token=$token'>here</a> to reset your password.";

                $mail->send();
                $message = "Password reset link has been sent to your email.";
            } catch (Exception $e) {
                $message = "Mailer Error: " . $mail->ErrorInfo;
            }
        } else {
            $message = "No user found with that email address.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Crowning Score</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <style>
          body {
            background: linear-gradient(to right, #e0c3fc, #8ec5fc);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .background-video { 
            position: fixed; /* Stay fixed in the viewport */
            top: 0;
            left: 0;
            width: 100vw; /* 100% of the viewport width */
            height: 100vh; /* 100% of the viewport height */
            object-fit: cover; /* Scale the video to cover, potentially cropping */
            z-index: -1;
        
          }
        .login-card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            background-color: rgba(255, 255, 255, 0.7);
            border: 3px solid #ccc;
        }
         

    </style>
</head>
<body>

        <video autoplay loop muted class="background-video">
            <source src="assets/bg1.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>

<div class="card login-card p-4" style="width: 100%; max-width: 400px;">
    <h3 class="text-center mb-3"><i class="bi bi-gem text-primary"></i> Crowning Score</h3>
    <p class="text-center text-muted mb-4">Login to manage your event</p>

    <?php if ($message): ?>
        <div class="alert alert-info"><?= $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="text-center mt-3">
        <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot Password?</a>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forgotPasswordModalLabel">Forgot Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Enter Your Email Address</label>
                        <input type="email" name="email_forgot" class="form-control" required>
                    </div>
                    <button type="submit" name="forgot_password" class="btn btn-primary w-100">Send Reset Link</button>
                </form>
            </div>
        </div>
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
