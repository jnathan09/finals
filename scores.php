<?php
session_start();
require 'db.php';

// Authentication check
// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Get selected event ID
$event_id = $_GET['event_id'] ?? null;
$selected_event = null;

// Fetch all events
$stmt = $conn->prepare("SELECT * FROM events ORDER BY name");
$stmt->execute();
$events_result = $stmt->get_result();

// Fetch selected event details if event is selected
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $event_result = $stmt->get_result();
    $selected_event = $event_result->fetch_assoc();
    
    // Get the current stage for the selected event
    $current_stage = $selected_event['stage'] ?? '';
}

// Handle Export Action
if (isset($_POST['export_scores'])) {
    $stage_to_export = $_POST['stage'] ?? $current_stage;
    $_SESSION['success_message'] = "Scores for $stage_to_export stage exported successfully!";
    header("Location: scores.php" . ($event_id ? "?event_id=$event_id" : ""));
    exit();
}

// Handle Import Action
if (isset($_POST['import_scores'])) {
    $stage_to_import = $_POST['stage'] ?? $current_stage;
    $_SESSION['success_message'] = "Scores for $stage_to_import stage imported successfully!";
    header("Location: scores.php" . ($event_id ? "?event_id=$event_id" : ""));
    exit();
}

// Fetch all stages that have been used for this event
$stages = [];
if ($event_id) {
    // First add the current stage from the event
    if (!empty($current_stage)) {
        $stages[] = $current_stage;
    }
    
    // Then get any other stages that have scores defined
    $stmt = $conn->prepare("SELECT DISTINCT stage FROM scores WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $stages_result = $stmt->get_result();
    
    while ($stage_row = $stages_result->fetch_assoc()) {
        if (!in_array($stage_row['stage'], $stages)) {
            $stages[] = $stage_row['stage'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raw Scores</title>
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
        }
        .sidebar a {
            padding: 15px;
            text-decoration: none;
            font-size: 1rem;
            color: white;
            display: block;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        .main {
            margin-left: 170px;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            background: white;
        }
        .actions-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
           justify-content: flex-end;
        }
        .score-value {
            font-weight: bold;
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
    <div class="container">
        <h2 class="mb-4">Raw Scores Management</h2>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success_message'] ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error_message'] ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Event Selection Form with Refresh Button -->
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
        
        
        <?php if ($event_id && count($stages) > 0): ?>
            <?php foreach ($stages as $index => $stage): ?>
                <!-- Stage header -->
                <div class="stage-header">
                    <h4>📊 Scores for <?= htmlspecialchars($stage) ?> stage</h4>
                    <?php if ($event_id && $index === 0): ?>  <!-- Only show refresh button for the first stage -->
                        <a href="scores.php?event_id=<?= $event_id ?>" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Refresh Scores
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="card p-3">                   
                    <!-- Scores Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Contestant #</th>
                                    <th>Contestant Name</th>
                                    <th>Judge</th>
                                    <th>Criteria</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Query to get scores with contestant and judge names for this stage
                                $sql = "SELECT s.id, s.score, s.criteria_id, c.name AS criteria_name, 
                                        cont.name AS contestant_name, cont.contestant_number, 
                                        u.name AS judge_name, u.email AS judge_email
                                        FROM scores s
                                        JOIN contestants cont ON s.contestant_id = cont.id
                                        JOIN users u ON s.judge_id = u.id
                                        JOIN criteria c ON s.criteria_id = c.id
                                        WHERE s.event_id = ? AND s.stage = ?
                                        ORDER BY cont.contestant_number, u.name, c.name";
                                
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param('is', $event_id, $stage);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if ($result->num_rows > 0) {
                                    // Group scores by contestant and judge for better organization
                                    $groupedScores = [];
                                    
                                    while ($score = $result->fetch_assoc()) {
                                        $key = $score['contestant_number'] . '-' . $score['judge_email'];
                                        
                                        if (!isset($groupedScores[$key])) {
                                            $groupedScores[$key] = [
                                                'contestant_number' => $score['contestant_number'],
                                                'contestant_name' => $score['contestant_name'],
                                                'judge_name' => $score['judge_name'],
                                                'scores' => []
                                            ];
                                        }
                                        
                                        $groupedScores[$key]['scores'][] = [
                                            'criteria_name' => $score['criteria_name'],
                                            'score' => $score['score']
                                        ];
                                    }
                                    
                                    // Display grouped scores
                                    foreach ($groupedScores as $group) {
                                        // For each criteria, create a new row
                                        foreach ($group['scores'] as $index => $scoreItem) {
                                            echo '<tr>';
                                            
                                            // Only show contestant info and judge name in the first row of each group
                                            if ($index === 0) {
                                                echo '<td>' . htmlspecialchars($group['contestant_number']) . '</td>';
                                                echo '<td>' . htmlspecialchars($group['contestant_name']) . '</td>';
                                                echo '<td>' . htmlspecialchars($group['judge_name']) . '</td>';
                                            } else {
                                                echo '<td></td><td></td><td></td>';
                                            }
                                            
                                            echo '<td>' . htmlspecialchars($scoreItem['criteria_name']) . '</td>';
                                            echo '<td class="score-value">' . htmlspecialchars($scoreItem['score']) . '</td>';
                                            echo '</tr>';
                                        }
                                        
                                        // Add a separator row between contestants/judges
                                        echo '<tr><td colspan="5" style="height: 10px; background-color: #f8f9fa;"></td></tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="5" class="text-center">No scores found for the ' . htmlspecialchars($stage) . ' stage</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                        <!-- Export/Import Buttons for this stage -->
                    <div class="actions-container mb-3">
                        <form method="POST">
                            <input type="hidden" name="event_id" value="<?= $event_id ?>">
                            <input type="hidden" name="stage" value="<?= $stage ?>">
                            <button type="submit" name="export_scores" class="btn btn-success">
                                <i class="bi bi-download"></i> Export Scores
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="event_id" value="<?= $event_id ?>">
                            <input type="hidden" name="stage" value="<?= $stage ?>">
                            <button type="submit" name="import_scores" class="btn btn-info">
                                <i class="bi bi-upload"></i> Import Scores
                            </button>
                        </form>
                    </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
        <?php endif; ?>
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