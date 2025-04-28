<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crowning Score | Pageant Tabulation System</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
        .background-video { 
            position: fixed; /* Stay fixed in the viewport */
            top: 0;
            left: 0;
            width: 100vw; /* 100% of the viewport width */
            height: 100vh; /* 100% of the viewport height */
            object-fit: cover; /* Scale the video to cover, potentially cropping */
            z-index: -1;
        
          }
    </style>
</head>
<body class=" d-flex align-items-center justify-content-center vh-100">

<video autoplay loop muted class="background-video">
            <source src="assets/bg2.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>    

<div class="text-center text-white">
    <h1 class="mb-4">
        <i class="bi bi-gem text-primary"></i> Crowning Score
    </h1>
    <p class="lead">A modern and accurate tabulation system for pageants.</p>
    <p>Precision scoring and contestant ranking made simple — for pageants that deserve the best.</p>
    
    <a href="login.php" class="btn btn-primary mt-3">
        <i class="bi bi-box-arrow-in-right"></i> Login
    </a>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
