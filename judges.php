<?php
session_start();
require 'db.php';

// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Fetch all events
$stmt = $conn->prepare("SELECT * FROM events");
$stmt->execute();
$events_result = $stmt->get_result();

// Get selected event ID
$event_id = $_GET['event_id'] ?? null;
$selected_event = null;
$current_stage = '';

// Fetch selected event details
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $event_result = $stmt->get_result();
    $selected_event = $event_result->fetch_assoc();
    $current_stage = $selected_event['stage'] ?? '';
}

// Handle adding new judge assignment
if (isset($_POST['add_judge'])) {
    $email = $_POST['email'];
    $event_id = $_POST['event_id'];
    
    // First check if the email exists in the users table
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        
        //Check if the role is 'judge'
        if ($user['role'] !== 'judge') {
            $_SESSION['error_message'] = "This user's role is not judge.";
            header("Location: judges.php?event_id=$event_id");
            exit();
        }
        
        // Check if this judge is already assigned to this event
        $check = $conn->prepare("SELECT * FROM event_judges WHERE user_id = ? AND event_id = ?");
        $check->bind_param('ii', $user_id, $event_id);
        $check->execute();
        $check_result = $check->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error_message'] = "This judge is already assigned to this event.";
        } else {
            // Assign the judge to the event
            $insert = $conn->prepare("INSERT INTO event_judges (user_id, event_id, email) VALUES (?, ?, ?)");
            $insert->bind_param('iis', $user_id, $event_id, $email);
            $insert->execute();
        }
    } else {
        $_SESSION['error_message'] = "Email not found in the system. The judge must have a user account first.";
    }
    
    header("Location: judges.php?event_id=$event_id");
    exit();
}

// Handle removing judge from event
if (isset($_POST['remove_judge'])) {
    $assignment_id = $_POST['assignment_id'];
    $event_id = $_POST['event_id'];
    
    $stmt = $conn->prepare("DELETE FROM event_judges WHERE id = ?");
    $stmt->bind_param('i', $assignment_id);
    $stmt->execute();
    
    header("Location: judges.php?event_id=$event_id");
    exit();
}

// Fetch judges assigned to the selected event
$assigned_judges = [];
if ($event_id) {
    $stmt = $conn->prepare("
        SELECT ej.id as assignment_id, u.id as user_id, u.name, u.email 
        FROM event_judges ej
        JOIN users u ON ej.user_id = u.id
        WHERE ej.event_id = ?
    ");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $assigned_judges_result = $stmt->get_result();
    
    while ($judge = $assigned_judges_result->fetch_assoc()) {
        $assigned_judges[] = $judge;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judges Management</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <style>
        body {
        background: #EFEEEA;
        height: 100vh;
        margin: 0;
    }
    .sidebar {
        width: 170px;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background-color: #06202B;
        padding-top: 35px;
        transition: width 0.3s; /* Smooth transition */
        overflow-x: hidden;
    }
    .sidebar.collapsed {
        width: 60px;
    }
    .sidebar a {
        padding: 15px;
        text-decoration: none;
        font-size: 1rem;
        color: white;
        display: block;
        white-space: nowrap;
    }
    .sidebar.collapsed a {
        text-align: center;
        font-size: 0.9rem;
    }
    .sidebar .logo {
        text-align: center;
        transition: all 0.3s;
    }
    .sidebar.collapsed .logo img {
        width: 40px; /* smaller logo when collapsed */
    }

    .main {
        margin-left: 170px;
        padding: 20px;
        transition: margin-left 0.3s; /* Smooth transition */
    }
    .main.collapsed {
        margin-left: 60px;
    }
    .card {
        border-radius: 10px;
        box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        background: white;
    }
    .stage-header {
        margin-top: 30px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
        

    </style>
</head>
<body>

<div class="sidebar">

    <div class="logo d-flex justify-content-center align-items-center mb-4">
    <img src="assets/logo.png" alt="Logo" class="img-fluid" style="max-width: 80px; height: auto;">
    </div>

    <a href="admin_dashboard.php">🏠 Dashboard</a>
    <a href="contestants.php">👗 Contestants</a>
    <a href="judges.php">🧑‍⚖️ Judges</a>
    <a href="criteria.php">📚 Criteria</a>
    <a href="awards.php">🥇 Awards</a>
    <a href="scores.php">📊 Raw Scores</a>
    <a href="rankings.php">🏆 Rankings</a>
    <a href="logout.php" onclick="return confirm('Confirm logout?');">🚪 Logout</a>
</div>

<div class="main">
<button id="toggleSidebar" class="btn btn-white mb-3" style="margin-top: 10px;">☰</button>  
    <div class="container">
        <h2 class="mb-4">Judges Management</h2>
        
        <!-- Event Selection Form -->
        <form method="GET" class="mb-3">
            <label class="form-label">Select Event</label>
            <select name="event_id" class="form-select" onchange="this.form.submit()">
                <option value="">-- Select Event --</option>
                <?php while ($event = $events_result->fetch_assoc()): ?>
                    <option value="<?= $event['id'] ?>" <?= $event['id'] == $event_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($event['name']) ?> (<?= htmlspecialchars($event['category']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if ($event_id): ?>
            <div class="stage-header">
                    <h4>🏛️ List of Assigned Judges</h4>
                     <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJudgeModal">
                    + Add Judge
                </button>
            </div>
            <div class="card p-3">  
            <div class="table-responsive">              
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <?php if (count($assigned_judges) > 0): ?>
                    <tbody>
                        <?php foreach ($assigned_judges as $judge): ?>
                        <tr>
                            <td><?= htmlspecialchars($judge['name']) ?></td>
                            <td><?= htmlspecialchars($judge['email']) ?></td>
                            <td>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="assignment_id" value="<?= $judge['assignment_id'] ?>">
                                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                    <button type="submit" name="remove_judge" class="btn btn-danger btn-sm" 
                                            onclick="return confirm('Are you sure you want to remove this judge from the event?');">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                        </table>
                <?php else: ?>
                            <tr><td colspan="3">No judges yet.</td></tr>
                    <?php endif; ?>
            </div>
        <?php else: ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Judge Modal -->
<div class="modal fade" id="addJudgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Judge to Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Judge Email</label>
                        <input type="email" name="email" class="form-control" required>
                        <small class="text-muted">Enter the email address of the judge. They must have an account in the system.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_judge" class="btn btn-primary">Add Judge</button>
                </div>
            </form>
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

        const toggleButton = document.getElementById('toggleSidebar');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main');

    toggleButton.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    });

</script>       

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>