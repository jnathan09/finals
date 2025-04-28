<?php
session_start();
require 'db.php';

// Add this at the beginning of the file, right after the session_start() and require 'db.php';
// Handle AJAX visibility toggle
if (isset($_POST['toggle_visibility']) && isset($_POST['ajax'])) {
    // Clear any output that might have happened
    ob_clean();
    
    // Disable error reporting for clean JSON output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Prepare response
    $response = ['success' => false, 'message' => ''];
    
    try {
        $table_id = $_POST['table_id'] ?? null;
        $is_public = $_POST['is_public'] ? 0 : 1; // Toggle the value
        $table_type = $_POST['table_type'] ?? '';
        $award_id = $_POST['award_id'] ?? null;
        $event_id = $_GET['event_id'] ?? null;
        
        if (!$event_id) {
            throw new Exception("Event ID is required");
        }
        
        if ($table_type === 'overall') {
            // Check if record exists
            $check = $conn->prepare("SELECT * FROM ranking_visibility WHERE event_id = ? AND table_type = 'overall'");
            $check->bind_param('i', $event_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE ranking_visibility SET is_public = ? WHERE id = ?");
                $stmt->bind_param('ii', $is_public, $table_id);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO ranking_visibility (event_id, table_type, is_public) VALUES (?, 'overall', ?)");
                $stmt->bind_param('ii', $event_id, $is_public);
            }
        } else if ($table_type === 'award') {
            // Check if record exists
            $check = $conn->prepare("SELECT * FROM ranking_visibility WHERE event_id = ? AND table_type = 'award' AND award_id = ?");
            $check->bind_param('ii', $event_id, $award_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE ranking_visibility SET is_public = ? WHERE id = ?");
                $stmt->bind_param('ii', $is_public, $table_id);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO ranking_visibility (event_id, table_type, award_id, is_public) VALUES (?, 'award', ?, ?)");
                $stmt->bind_param('iii', $event_id, $award_id, $is_public);
            }
        } else if ($table_type === 'peoples_choice') {
            // Check if record exists
            $check = $conn->prepare("SELECT * FROM ranking_visibility WHERE event_id = ? AND table_type = 'peoples_choice'");
            $check->bind_param('i', $event_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE ranking_visibility SET is_public = ? WHERE id = ?");
                $stmt->bind_param('ii', $is_public, $table_id);
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO ranking_visibility (event_id, table_type, is_public) VALUES (?, 'peoples_choice', ?)");
                $stmt->bind_param('ii', $event_id, $is_public);
            }
        } else {
            throw new Exception("Invalid table type");
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }
        
        $response['success'] = true;
        $response['message'] = $is_public ? "Table is now public" : "Table is now hidden";
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Get selected event ID
$event_id = $_GET['event_id'] ?? null;
$selected_event = null;

// Fetch all events
$stmt = $conn->prepare("SELECT * FROM events");
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

// Handle visibility toggle
// if (isset($_POST['toggle_visibility'])) {
//     $table_id = $_POST['table_id'];
//     $is_public = $_POST['is_public'] ? 0 : 1; // Toggle the value
//     $table_type = $_POST['table_type'];
//     $award_id = $_POST['award_id'] ?? null;
//     $ajax = $_POST['ajax'] ?? null;
    
//     if ($table_type === 'overall') {
//         // Check if record exists
//         $check = $conn->prepare("SELECT * FROM ranking_visibility WHERE event_id = ? AND table_type = 'overall'");
//         $check->bind_param('i', $event_id);
//         $check->execute();
//         $result = $check->get_result();
        
//         if ($result->num_rows > 0) {
//             // Update existing record
//             $stmt = $conn->prepare("UPDATE ranking_visibility SET is_public = ? WHERE id = ?");
//             $stmt->bind_param('ii', $is_public, $table_id);
//         } else {
//             // Insert new record
//             $stmt = $conn->prepare("INSERT INTO ranking_visibility (event_id, table_type, is_public) VALUES (?, 'overall', ?)");
//             $stmt->bind_param('ii', $event_id, $is_public);
//         }
//     } else if ($table_type === 'award') {
//         // Check if record exists
//         $check = $conn->prepare("SELECT * FROM ranking_visibility WHERE event_id = ? AND table_type = 'award' AND award_id = ?");
//         $check->bind_param('ii', $event_id, $award_id);
//         $check->execute();
//         $result = $check->get_result();
        
//         if ($result->num_rows > 0) {
//             // Update existing record
//             $stmt = $conn->prepare("UPDATE ranking_visibility SET is_public = ? WHERE id = ?");
//             $stmt->bind_param('ii', $is_public, $table_id);
//         } else {
//             // Insert new record
//             $stmt = $conn->prepare("INSERT INTO ranking_visibility (event_id, table_type, award_id, is_public) VALUES (?, 'award', ?, ?)");
//             $stmt->bind_param('iii', $event_id, $award_id, $is_public);
//         }
//     } else if ($table_type === 'peoples_choice') {
//         // Check if record exists
//         $check = $conn->prepare("SELECT * FROM ranking_visibility WHERE event_id = ? AND table_type = 'peoples_choice'");
//         $check->bind_param('i', $event_id);
//         $check->execute();
//         $result = $check->get_result();
        
//         if ($result->num_rows > 0) {
//             // Update existing record
//             $stmt = $conn->prepare("UPDATE ranking_visibility SET is_public = ? WHERE id = ?");
//             $stmt->bind_param('ii', $is_public, $table_id);
//         } else {
//             // Insert new record
//             $stmt = $conn->prepare("INSERT INTO ranking_visibility (event_id, table_type, is_public) VALUES (?, 'peoples_choice', ?)");
//             $stmt->bind_param('ii', $event_id, $is_public);
//         }
//     }
    
//     $stmt->execute();
    
//     if ($ajax) {
//         header('Content-Type: application/json');
//         echo json_encode(['success' => true, 'message' => 'Visibility updated successfully!']);
//         exit();
//     } else {
//         header("Location: ranking.php?event_id=$event_id");
//         exit();
//     }
// }

// Function to calculate overall rankings
function calculateOverallRankings($conn, $event_id, $stage) {
    $rankings = [];
    
    // Get all contestants in the current stage
    $stmt = $conn->prepare("SELECT id, name, contestant_number FROM contestants WHERE event_id = ? AND stage = ?");
    $stmt->bind_param('is', $event_id, $stage);
    $stmt->execute();
    $contestants_result = $stmt->get_result();
    
    while ($contestant = $contestants_result->fetch_assoc()) {
        $contestant_id = $contestant['id'];
        $total_score = 0;
        $criteria_count = 0;
        
        // Get all criteria for this stage
        $criteria_stmt = $conn->prepare("SELECT id, percentage FROM criteria WHERE event_id = ? AND stage = ?");
        $criteria_stmt->bind_param('is', $event_id, $stage);
        $criteria_stmt->execute();
        $criteria_result = $criteria_stmt->get_result();
        
        while ($criterion = $criteria_result->fetch_assoc()) {
            $criteria_id = $criterion['id'];
            $percentage = $criterion['percentage'] / 100; // Convert to decimal
            
            // Get average score for this contestant and criterion
            $score_stmt = $conn->prepare("
                SELECT AVG(score) as avg_score 
                FROM scores 
                WHERE event_id = ? AND contestant_id = ? AND criteria_id = ? AND stage = ?
            ");
            $score_stmt->bind_param('iiis', $event_id, $contestant_id, $criteria_id, $stage);
            $score_stmt->execute();
            $score_result = $score_stmt->get_result();
            $score_row = $score_result->fetch_assoc();
            
            if ($score_row && $score_row['avg_score']) {
                $total_score += $score_row['avg_score'] * $percentage;
                $criteria_count++;
            }
        }
        
        // Only include contestants who have been scored
        if ($criteria_count > 0) {
            $rankings[] = [
                'contestant_id' => $contestant_id,
                'name' => $contestant['name'],
                'contestant_number' => $contestant['contestant_number'],
                'total_score' => $total_score
            ];
        }
    }
    
    // Sort by total score (descending)
    usort($rankings, function($a, $b) {
        return $b['total_score'] <=> $a['total_score'];
    });
    
    // Add rank
    $rank = 1;
    $prev_score = null;
    $prev_rank = 1;
    
    foreach ($rankings as &$ranking) {
        if ($prev_score !== null && $ranking['total_score'] < $prev_score) {
            $rank = $prev_rank + 1;
        }
        
        $ranking['rank'] = $rank;
        $prev_score = $ranking['total_score'];
        $prev_rank = $rank;
        $rank++;
    }
    
    return $rankings;
}

// Function to calculate award rankings based on criteria
function calculateAwardRankings($conn, $event_id, $stage, $award) {
    $rankings = [];
    
    // Get criteria IDs for this award
    $criteria_ids = explode(',', $award['criteria_ids']);
    
    // Get all contestants in the current stage
    $stmt = $conn->prepare("SELECT id, name, contestant_number FROM contestants WHERE event_id = ? AND stage = ?");
    $stmt->bind_param('is', $event_id, $stage);
    $stmt->execute();
    $contestants_result = $stmt->get_result();
    
    while ($contestant = $contestants_result->fetch_assoc()) {
        $contestant_id = $contestant['id'];
        $total_score = 0;
        $criteria_count = 0;
        
        foreach ($criteria_ids as $criteria_id) {
            // Get criterion percentage
            $criteria_stmt = $conn->prepare("SELECT percentage FROM criteria WHERE id = ?");
            $criteria_stmt->bind_param('i', $criteria_id);
            $criteria_stmt->execute();
            $criteria_result = $criteria_stmt->get_result();
            $criterion = $criteria_result->fetch_assoc();
            
            if ($criterion) {
                $percentage = $criterion['percentage'] / 100; // Convert to decimal
                
                // Get average score for this contestant and criterion
                $score_stmt = $conn->prepare("
                    SELECT AVG(score) as avg_score 
                    FROM scores 
                    WHERE event_id = ? AND contestant_id = ? AND criteria_id = ? AND stage = ?
                ");
                $score_stmt->bind_param('iiis', $event_id, $contestant_id, $criteria_id, $stage);
                $score_stmt->execute();
                $score_result = $score_stmt->get_result();
                $score_row = $score_result->fetch_assoc();
                
                if ($score_row && $score_row['avg_score']) {
                    $total_score += $score_row['avg_score'] * $percentage;
                    $criteria_count++;
                }
            }
        }
        
        // Only include contestants who have been scored for all criteria
        if ($criteria_count == count($criteria_ids)) {
            $rankings[] = [
                'contestant_id' => $contestant_id,
                'name' => $contestant['name'],
                'contestant_number' => $contestant['contestant_number'],
                'total_score' => $total_score
            ];
        }
    }
    
    // Sort by total score (descending)
    usort($rankings, function($a, $b) {
        return $b['total_score'] <=> $a['total_score'];
    });
    
    // Add rank
    $rank = 1;
    $prev_score = null;
    $prev_rank = 1;
    
    foreach ($rankings as &$ranking) {
        if ($prev_score !== null && $ranking['total_score'] < $prev_score) {
            $rank = $prev_rank + 1;
        }
        
        $ranking['rank'] = $rank;
        $prev_score = $ranking['total_score'];
        $prev_rank = $rank;
        $rank++;
    }
    
    return $rankings;
}

// Function to get People's Choice Award rankings
function getPeoplesChoiceRankings($conn, $event_id) {
    $rankings = [];
    
    // Get voting period for this event
    $stmt = $conn->prepare("
        SELECT * FROM peoples_choice_voting 
        WHERE event_id = ? 
        ORDER BY end_datetime DESC 
        LIMIT 1
    ");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $voting_result = $stmt->get_result();
    $voting = $voting_result->fetch_assoc();
    
    if (!$voting) {
        return $rankings;
    }
    
    $voting_id = $voting['id'];
    
    // Get all contestants with votes
    $stmt = $conn->prepare("
        SELECT c.id, c.name, c.contestant_number, COUNT(v.id) as vote_count
        FROM contestants c
        LEFT JOIN peoples_choice_votes v ON c.id = v.contestant_id AND v.voting_id = ?
        WHERE c.event_id = ?
        GROUP BY c.id
        HAVING vote_count > 0
        ORDER BY vote_count DESC
    ");
    $stmt->bind_param('ii', $voting_id, $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rank = 1;
    $prev_votes = null;
    $prev_rank = 1;
    
    while ($row = $result->fetch_assoc()) {
        if ($prev_votes !== null && $row['vote_count'] < $prev_votes) {
            $rank = $prev_rank + 1;
        }
        
        $rankings[] = [
            'contestant_id' => $row['id'],
            'name' => $row['name'],
            'contestant_number' => $row['contestant_number'],
            'vote_count' => $row['vote_count'],
            'rank' => $rank
        ];
        
        $prev_votes = $row['vote_count'];
        $prev_rank = $rank;
        $rank++;
    }
    
    return $rankings;
}

// Get visibility settings
function getVisibilitySettings($conn, $event_id) {
    $visibility = [
        'overall' => ['id' => null, 'is_public' => 0],
        'awards' => [],
        'peoples_choice' => ['id' => null, 'is_public' => 0]
    ];
    
    // Get overall visibility
    $stmt = $conn->prepare("SELECT id, is_public FROM ranking_visibility WHERE event_id = ? AND table_type = 'overall'");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $visibility['overall'] = $row;
    }
    
    // Get award visibilities
    $stmt = $conn->prepare("SELECT id, award_id, is_public FROM ranking_visibility WHERE event_id = ? AND table_type = 'award'");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $visibility['awards'][$row['award_id']] = $row;
    }
    
    // Get people's choice visibility
    $stmt = $conn->prepare("SELECT id, is_public FROM ranking_visibility WHERE event_id = ? AND table_type = 'peoples_choice'");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $visibility['peoples_choice'] = $row;
    }
    
    return $visibility;
}

// Variables to store rankings
$overall_rankings = [];
$award_rankings = [];
$peoples_choice_rankings = [];
$visibility_settings = [];

// Fetch data if event is selected
if ($event_id) {
    // Get visibility settings
    $visibility_settings = getVisibilitySettings($conn, $event_id);
    
    // Calculate overall rankings
    $overall_rankings = calculateOverallRankings($conn, $event_id, $current_stage);
    
    // Fetch all awards for this event
    $stmt = $conn->prepare("SELECT * FROM awards WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $awards_result = $stmt->get_result();
    
    // Process each award
    while ($award = $awards_result->fetch_assoc()) {
        if ($award['type'] == 'criteria_based') {
            // Calculate rankings based on criteria
            $award_rankings[$award['id']] = [
                'award' => $award,
                'rankings' => calculateAwardRankings($conn, $event_id, $current_stage, $award)
            ];
        } else if ($award['type'] == 'defined_winner') {
            // Get the defined winner
            $stmt = $conn->prepare("
                SELECT c.id, c.name, c.contestant_number 
                FROM contestants c 
                WHERE c.id = ? AND c.event_id = ?
            ");
            $stmt->bind_param('ii', $award['contestant_id'], $event_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $winner = $result->fetch_assoc();
            
            if ($winner) {
                $award_rankings[$award['id']] = [
                    'award' => $award,
                    'rankings' => [
                        [
                            'contestant_id' => $winner['id'],
                            'name' => $winner['name'],
                            'contestant_number' => $winner['contestant_number'],
                            'rank' => 1
                        ]
                    ]
                ];
            } else {
                $award_rankings[$award['id']] = [
                    'award' => $award,
                    'rankings' => []
                ];
            }
        }
    }
    
    // Get People's Choice Award rankings
    $peoples_choice_rankings = getPeoplesChoiceRankings($conn, $event_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rankings</title>
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
        .stage-header {
            margin-top: 30px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rank-tag {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: bold;
    margin-left: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.rank-tag-2 {
    background: linear-gradient(135deg, #e0e0e0, #c0c0c0);
    color: #000;
}
.rank-tag-3 {
    background: linear-gradient(135deg, #cd7f32, #a0522d);
    color: #fff;
}
.rank-tag-winner {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: #fff;
    padding: 3px 12px;
}
.visibility-badge {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    margin-left: 10px;
}
#visibility-message {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    display: none;
}
/* Update the rank-tag-1 style to match the winner tag color */
.rank-tag-1 {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: #fff;
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
        <h2 class="mb-4">Rankings</h2>
        
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
            <!-- Overall Rankings -->
            <div class="stage-header">
    <h4>
        🏆 Overall Rankings
        <?php if ($visibility_settings['overall']['is_public']): ?>
            <span class="badge bg-success visibility-badge">Public</span>
        <?php else: ?>
            <span class="badge bg-secondary visibility-badge">Hidden</span>
        <?php endif; ?>
    </h4>
    <button type="button" 
            class="btn <?= $visibility_settings['overall']['is_public'] ? 'btn-secondary' : 'btn-success' ?> toggle-visibility-btn"
            data-table-id="<?= $visibility_settings['overall']['id'] ?>"
            data-is-public="<?= $visibility_settings['overall']['is_public'] ?>"
            data-table-type="overall"
            data-award-id="">
        <?= $visibility_settings['overall']['is_public'] ? 'Hide' : 'Make Public' ?>
    </button>
</div>
                <div class="card p-3">
                    <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Contestant #</th>
                                <th>Name</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($overall_rankings) > 0): ?>
                                <?php foreach ($overall_rankings as $ranking): ?>
                                    <tr>
                                        <td><?= $ranking['rank'] ?></td>
                                        <td><?= htmlspecialchars($ranking['contestant_number']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($ranking['name']) ?>
                                            <?php if ($ranking['rank'] <= 3): ?>
                                                <span class="rank-tag rank-tag-<?= $ranking['rank'] ?>"><?= $ranking['rank'] == 1 ? '1st' : ($ranking['rank'] == 2 ? '2nd' : '3rd') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format($ranking['total_score'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No rankings available. Ensure contestants have been scored.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                            </div>
                </div>
            
            <!-- Award Rankings -->
            <?php foreach ($award_rankings as $award_id => $award_data): ?>
                <?php 
                $award = $award_data['award'];
                $rankings = $award_data['rankings'];
                $visibility = $visibility_settings['awards'][$award_id] ?? ['id' => null, 'is_public' => 0];
                ?>
                
                <div class="stage-header">
    <h4>
        🏅 <?= htmlspecialchars($award['name']) ?>
        <?php if ($visibility['is_public']): ?>
            <span class="badge bg-success visibility-badge">Public</span>
        <?php else: ?>
            <span class="badge bg-secondary visibility-badge">Hidden</span>
        <?php endif; ?>
    </h4>
    <button type="button" 
            class="btn <?= $visibility['is_public'] ? 'btn-secondary' : 'btn-success' ?> toggle-visibility-btn"
            data-table-id="<?= $visibility['id'] ?>"
            data-is-public="<?= $visibility['is_public'] ?>"
            data-table-type="award"
            data-award-id="<?= $award_id ?>">
        <?= $visibility['is_public'] ? 'Hide' : 'Make Public' ?>
    </button>
</div>
                
    <div class="card p-3">
    <p class="text-muted"><?= htmlspecialchars($award['description']) ?></p>
    
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Contestant #</th>
                <th>Name</th>
                <?php if ($award['type'] == 'criteria_based'): ?>
                    <th>Score</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
<?php if (count($rankings) > 0): ?>
    <?php 
    // Only display the winner (first in the rankings)
    $winner = $rankings[0];
    ?>
    <tr>
        <td><?= htmlspecialchars($winner['contestant_number']) ?></td>
        <td>
            <?= htmlspecialchars($winner['name']) ?>
            <span class="rank-tag rank-tag-winner">Winner</span>
        </td>
        <?php if ($award['type'] == 'criteria_based'): ?>
            <td><?= number_format($winner['total_score'], 2) ?></td>
        <?php endif; ?>
    </tr>
<?php else: ?>
    <tr>
        <td colspan="<?= $award['type'] == 'criteria_based' ? '3' : '2' ?>" class="text-center">A winner for this award has yet to be determined.</td>
    </tr>
<?php endif; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
            
            <!-- People's Choice Award -->
            <div class="stage-header">
    <h4>
        👥 People's Choice Award
        <?php if ($visibility_settings['peoples_choice']['is_public']): ?>
            <span class="badge bg-success visibility-badge">Public</span>
        <?php else: ?>
            <span class="badge bg-secondary visibility-badge">Hidden</span>
        <?php endif; ?>
    </h4>
    <button type="button" 
            class="btn <?= $visibility_settings['peoples_choice']['is_public'] ? 'btn-secondary' : 'btn-success' ?> toggle-visibility-btn"
            data-table-id="<?= $visibility_settings['peoples_choice']['id'] ?>"
            data-is-public="<?= $visibility_settings['peoples_choice']['is_public'] ?>"
            data-table-type="peoples_choice"
            data-award-id="">
        <?= $visibility_settings['peoples_choice']['is_public'] ? 'Hide' : 'Make Public' ?>
    </button>
</div>
            
            <div class="card p-3">
            <table class=" table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Contestant #</th>
                                <th>Name</th>
                                <th>Votes</th>
                            </tr>
                        </thead>
                        <?php if (count($peoples_choice_rankings) > 0): ?>
                        <tbody>
                            <?php 
// Only display the winner (first place)
if (count($peoples_choice_rankings) > 0):
    $winner = $peoples_choice_rankings[0];
?>
    <tr>

        <td><?= htmlspecialchars($winner['contestant_number']) ?></td>
        <td>
            <?= htmlspecialchars($winner['name']) ?>
            <span class="rank-tag rank-tag-winner">Winner</span>
        </td>
        <td><?= $winner['vote_count'] ?></td>
    </tr>
        <?php else: ?>
        <?php endif; ?>
                        </tbody>
                    </table>
        </div>
                <?php else: ?>
                    <tr>
                <td colspan="4" class="text-center">No People's Choice voting data available.</td>
            </tr>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="visibility-message" class="alert alert-success"></div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add notification div for messages
    const visibilityMessage = document.getElementById('visibility-message');
    
    // Find all toggle visibility buttons
    const toggleButtons = document.querySelectorAll('.toggle-visibility-btn');
    
    // Add event listener to each button
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get data attributes
            const tableId = this.getAttribute('data-table-id');
            const isPublic = this.getAttribute('data-is-public');
            const tableType = this.getAttribute('data-table-type');
            const awardId = this.getAttribute('data-award-id');
            
            // Create form data
            const formData = new FormData();
            formData.append('toggle_visibility', '1');
            formData.append('table_id', tableId);
            formData.append('is_public', isPublic);
            formData.append('table_type', tableType);
            formData.append('ajax', '1');
            
            if (awardId) {
                formData.append('award_id', awardId);
            }
            
            // Disable button during request
            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = 'Processing...';
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Server returned invalid response. Please try again.');
                }
                
                if (data.success) {
                    // Update button text and class
                    const newIsPublic = isPublic === '1' ? '0' : '1';
                    this.setAttribute('data-is-public', newIsPublic);
                    
                    if (newIsPublic === '1') {
                        this.textContent = 'Hide';
                        this.classList.remove('btn-success');
                        this.classList.add('btn-secondary');
                        
                        // Update badge
                        const header = this.closest('.stage-header').querySelector('h4');
                        const badge = header.querySelector('.visibility-badge');
                        if (badge) {
                            badge.textContent = 'Public';
                            badge.classList.remove('bg-secondary');
                            badge.classList.add('bg-success');
                        } else {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'badge bg-success visibility-badge';
                            newBadge.textContent = 'Public';
                            header.appendChild(newBadge);
                        }
                    } else {
                        this.textContent = 'Make Public';
                        this.classList.remove('btn-secondary');
                        this.classList.add('btn-success');
                        
                        // Update badge
                        const header = this.closest('.stage-header').querySelector('h4');
                        const badge = header.querySelector('.visibility-badge');
                        if (badge) {
                            badge.textContent = 'Hidden';
                            badge.classList.remove('bg-success');
                            badge.classList.add('bg-secondary');
                        }
                    }
                    
                    // Show success message
                    visibilityMessage.textContent = data.message || 'Visibility updated successfully!';
                    visibilityMessage.className = 'alert alert-success';
                    visibilityMessage.style.display = 'block';
                    
                    // Hide message after 3 seconds
                    setTimeout(() => {
                        visibilityMessage.style.display = 'none';
                    }, 3000);
                } else {
                    // Show error message
                    alert(data.message || 'An error occurred. Please try again.');
                    this.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message);
                this.textContent = originalText;
            })
            .finally(() => {
                // Re-enable button
                this.disabled = false;
            });
        });
    });
});
</script>
</body>
</html>
