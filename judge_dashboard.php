<?php
// Start output buffering right away to capture any unexpected output
ob_start();

// Start session
session_start();
require 'db.php';

// Handle AJAX requests separately with clean output
if (isset($_POST['submit_scores']) && isset($_POST['ajax'])) {
    // Clear any output that might have happened
    ob_clean();
    
    // Disable error reporting for clean JSON output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Prepare response
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate inputs
        if (!isset($_POST['event_id']) || !isset($_POST['contestant_id']) || !isset($_POST['stage'])) {
            throw new Exception("Missing required fields");
        }
        
        $judge_id = $_SESSION['user']['id'] ?? null;
        if (!$judge_id) {
            throw new Exception("Judge ID not found. Please login again.");
        }
        
        $event_id = intval($_POST['event_id']);
        $contestant_id = intval($_POST['contestant_id']);
        $stage = $_POST['stage'];
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Check for existing scores
        $check = $conn->prepare("
            SELECT * FROM scores  
            WHERE event_id = ? AND contestant_id = ? AND judge_id = ? AND stage = ?
        ");
        $check->bind_param('iiis', $event_id, $contestant_id, $judge_id, $stage);
        if (!$check->execute()) {
            throw new Exception("Database check error: " . $check->error);
        }
        $existing_scores = $check->get_result();
        
        if ($existing_scores->num_rows > 0) {
            throw new Exception("You have already submitted scores for this contestant in this stage.");
        }
        
        // Get criteria IDs from the form
        $criteria_found = false;
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'criteria_') === 0) {
                $criteria_found = true;
                $criteria_id = intval(substr($key, 9)); // Extract and validate criteria ID
                $score = floatval($value);
                
                // Validate score
                if ($score < 1 || $score > 100) {
                    throw new Exception("Invalid score: must be between 1 and 100");
                }
                
                // Insert score
                $stmt = $conn->prepare("
                    INSERT INTO scores (event_id, contestant_id, judge_id, criteria_id, score, stage)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('iiiids', $event_id, $contestant_id, $judge_id, $criteria_id, $score, $stage);
                
                if (!$stmt->execute()) {
                    throw new Exception("Database insert error: " . $stmt->error);
                }
            }
        }
        
        if (!$criteria_found) {
            throw new Exception("No criteria scores found in submission.");
        }
        
        // Commit transaction
        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Scores submitted successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollback();
        }
        $response['message'] = $e->getMessage();
    }
    
    // Clear all output and send clean JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// For regular page requests, continue with the page rendering
// Check if user is logged in and is a judge
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'judge') {
    header("Location: login.php");
    exit();
}

$judge_id = $_SESSION['user']['id'];
$judge_email = $_SESSION['user']['email'];

// Fetch events assigned to this judge
$stmt = $conn->prepare("
    SELECT e.* 
    FROM events e
    JOIN event_judges ej ON e.id = ej.event_id
    WHERE ej.user_id = ?
");
$stmt->bind_param('i', $judge_id);
$stmt->execute();
$events_result = $stmt->get_result();

// Get selected event ID
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : null;
$selected_event = null;
$current_stage = '';

// Fetch selected event details
if ($event_id) {
    // Fetch event details
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $event_result = $stmt->get_result();
    $selected_event = $event_result->fetch_assoc();
    $current_stage = $selected_event['stage'] ?? '';
}

// Fetch criteria for the current stage
$criteria = [];
$total_percentage = 0;
if ($event_id && !empty($current_stage)) {
    $stmt = $conn->prepare("
        SELECT * FROM criteria 
        WHERE event_id = ? AND stage = ?
    ");
    $stmt->bind_param('is', $event_id, $current_stage);
    $stmt->execute();
    $criteria_result = $stmt->get_result();
    
    while ($criterion = $criteria_result->fetch_assoc()) {
        $criteria[] = $criterion;
        $total_percentage += $criterion['percentage'];
    }
}

// Fetch contestants in the current stage
$contestants = [];
if ($event_id && !empty($current_stage)) {
    $stmt = $conn->prepare("
        SELECT * FROM contestants 
        WHERE event_id = ? AND stage = ?
        ORDER BY contestant_number
    ");
    $stmt->bind_param('is', $event_id, $current_stage);
    $stmt->execute();
    $contestants_result = $stmt->get_result();
    
    while ($contestant = $contestants_result->fetch_assoc()) {
        // Check if this judge has already scored this contestant
        $check = $conn->prepare("
            SELECT * FROM scores 
            WHERE event_id = ? AND contestant_id = ? AND judge_id = ? AND stage = ?
        ");
        $check->bind_param('iiis', $event_id, $contestant['id'], $judge_id, $current_stage);
        $check->execute();
        $scores_result = $check->get_result();
        
        $contestant['already_scored'] = ($scores_result->num_rows > 0);
        $contestants[] = $contestant;
    }
}

// End output buffering for the regular page
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Judge Dashboard</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(to right, #e0c3fc, #8ec5fc);
            min-height: 100vh;
            margin: 0;
            padding-bottom: 40px;
        }
        .sidebar {
            width: 220px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.7);
            padding-top: 60px;
        }
        .sidebar a {
            padding: 15px;
            text-decoration: none;
            font-size: 18px;
            color: white;
            display: block;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .main {
            margin-left: 220px;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            background: white;
            margin-bottom: 20px;
        }
        .stage-header {
            margin-top: 30px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .score-input {
            width: 80px;
        }
        .score-form {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            background-color: #f8f9fa;
        }
        .score-form h5 {
            margin-bottom: 15px;
        }
        .submit-btn {
            margin-top: 15px;
        }
        .criteria-table {
            margin-bottom: 20px;
        }
        .percentage-total {
            font-weight: bold;
            color: #0d6efd;
        }
        .percentage-warning {
            color: #dc3545;
        }
        .description-cell {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .description-cell:hover {
            white-space: normal;
            overflow: visible;
        }
    </style>
</head>
<body>
<div class="sidebar">
    <a href="judge_dashboard.php">🏠 Dashboard</a>
    <a href="logout.php" onclick="return confirm('Confirm logout?');">🚪 Logout</a>
</div>

<div class="main">
    <div class="container">
        <h2 class="mb-4">Judge Dashboard</h2>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
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
        
    <?php if ($event_id): ?>           
            <?php if (!empty($current_stage)): ?>
                        <h4>📜 Criteria for <?= htmlspecialchars($current_stage) ?> Stage</h4>
                        <div class="card p-3 mb-4">
                            <table class="table table-striped criteria-table">
                                <thead>
                                    <tr>
                                        <th>Criteria Name</th>
                                        <th>Description</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($criteria) > 0): ?>
                                        <?php foreach ($criteria as $criterion): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($criterion['name']) ?></td>
                                                <td class="description-cell" title="<?= htmlspecialchars($criterion['description'] ?? '') ?>">
                                                    <?= htmlspecialchars($criterion['description'] ?? '') ?>
                                                </td>
                                                <td><?= htmlspecialchars($criterion['percentage']) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No criteria defined for this stage.</td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <!-- Total row -->
                                    <tr>
                                        <td class="fw-bold">Total</td>
                                        <td></td>
                                        <td class="<?= ($total_percentage != 100) ? 'percentage-warning' : 'percentage-total' ?>">
                                            <?= $total_percentage ?>% <?= ($total_percentage != 100) ? '(Should be 100%)' : '' ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        
                        <?php if (count($contestants) > 0 && $total_percentage == 100): ?>
                    <h4>👑 Contestants in <?= htmlspecialchars($current_stage) ?> Stage</h4>

                    <?php foreach ($contestants as $contestant): ?>
                        <div class="card p-3 mb-4">
                            <h5>Contestant #<?= htmlspecialchars($contestant['contestant_number']) ?>: 
                                <?= htmlspecialchars($contestant['name']) ?> </h5>
                            <?php if ($contestant['already_scored']): ?>
                                <div class="alert alert-info">
                                    You have already submitted scores for this contestant.
                                </div>
                            <?php else: ?>
                                <form method="POST" class="score-form">
                                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                    <input type="hidden" name="contestant_id" value="<?= $contestant['id'] ?>">
                                    <input type="hidden" name="stage" value="<?= htmlspecialchars($current_stage) ?>">

                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Criteria</th>
                                                <th>Percentage</th>
                                                <th>Score (1-100)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($criteria as $criterion): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($criterion['name']) ?></td>
                                                    <td><?= htmlspecialchars($criterion['percentage']) ?>%</td>
                                                    <td>
                                                        <input type="number"
                                                               name="criteria_<?= $criterion['id'] ?>"
                                                               class="form-control score-input"
                                                               min="1"
                                                               max="100"
                                                               required>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="text-end">
                                        <button type="submit" name="submit_scores" class="btn btn-primary submit-btn">
                                            Submit Scores
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                <?php elseif (count($contestants) > 0): ?>
                    <div class="alert alert-warning">
                        Scoring is disabled until criteria percentages total 100%.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No contestants assigned to the current stage.
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?> 
    <?php endif; ?>
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Find all score forms
    const scoreForms = document.querySelectorAll('.score-form');
    
    // Add event listener to each form
    scoreForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent normal form submission
            
            const formData = new FormData(this);
            formData.append('ajax', '1'); // Flag for AJAX request
            formData.append('submit_scores', '1'); // Ensure this flag is set
            
            // Create a loading indicator
            const submitBtn = this.querySelector('.submit-btn');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Submitting...';
            submitBtn.disabled = true;
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.text();
            })
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Server returned invalid JSON. Please try again or contact support.');
                }
                
                if (data.success) {
                    // Replace the form with success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-info';
                    alertDiv.textContent = 'You have already submitted scores for this contestant.';
                    
                    this.replaceWith(alertDiv);
                    
                    // Show a temporary success notification
                    const notification = document.createElement('div');
                    notification.className = 'alert alert-success';
                    notification.textContent = data.message;
                    notification.style.position = 'fixed';
                    notification.style.top = '20px';
                    notification.style.right = '20px';
                    notification.style.zIndex = '1000';
                    document.body.appendChild(notification);
                    
                    // Remove notification after 3 seconds
                    setTimeout(() => {
                        notification.remove();
                    }, 3000);
                } else {
                    // Show error message
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
                alert('An error occurred while submitting scores: ' + error.message);
            });
        });
    });
});

</script>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>