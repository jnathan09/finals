<?php
session_start();
require 'db.php';

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

// Function to get visibility settings
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
    
    // Get overall rankings if public
    if ($visibility_settings['overall']['is_public']) {
        // Query to get overall rankings
        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.contestant_number, r.rank, r.score
            FROM contestants c
            JOIN (
                SELECT contestant_id, @rank := @rank + 1 as rank, total_score as score
                FROM (
                    SELECT s.contestant_id, SUM(s.score * cr.percentage / 100) as total_score
                    FROM scores s
                    JOIN criteria cr ON s.criteria_id = cr.id
                    WHERE s.event_id = ? AND s.stage = ?
                    GROUP BY s.contestant_id
                    ORDER BY total_score DESC
                ) ranked_scores, (SELECT @rank := 0) r
            ) r ON c.id = r.contestant_id
            WHERE c.event_id = ?
            ORDER BY r.rank
        ");
        $stmt->bind_param('isi', $event_id, $current_stage, $event_id);
        $stmt->execute();
        $overall_rankings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get award rankings if public
    $stmt = $conn->prepare("SELECT * FROM awards WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $awards_result = $stmt->get_result();
    
    while ($award = $awards_result->fetch_assoc()) {
        // Check if this award's rankings are public
        $is_public = isset($visibility_settings['awards'][$award['id']]) && 
                    $visibility_settings['awards'][$award['id']]['is_public'];
        
        if ($is_public) {
            if ($award['type'] == 'criteria_based') {
                // Get criteria-based rankings
                $criteria_ids = explode(',', $award['criteria_ids']);
                $placeholders = str_repeat('?,', count($criteria_ids) - 1) . '?';
                
                $sql = "
                    SELECT c.id, c.name, c.contestant_number, r.rank, r.score
                    FROM contestants c
                    JOIN (
                        SELECT contestant_id, @rank := @rank + 1 as rank, total_score as score
                        FROM (
                            SELECT s.contestant_id, SUM(s.score * cr.percentage / 100) as total_score
                            FROM scores s
                            JOIN criteria cr ON s.criteria_id = cr.id
                            WHERE s.event_id = ? AND s.stage = ? AND cr.id IN ($placeholders)
                            GROUP BY s.contestant_id
                            ORDER BY total_score DESC
                        ) ranked_scores, (SELECT @rank := 0) r
                    ) r ON c.id = r.contestant_id
                    WHERE c.event_id = ?
                    ORDER BY r.rank
                ";
                
                $stmt = $conn->prepare($sql);
                $params = array_merge([$event_id, $current_stage], $criteria_ids, [$event_id]);
                $types = 'is' . str_repeat('i', count($criteria_ids)) . 'i';
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $rankings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $award_rankings[$award['id']] = [
                    'award' => $award,
                    'rankings' => $rankings
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
                $winner = $stmt->get_result()->fetch_assoc();
                
                if ($winner) {
                    $winner['rank'] = 1;
                    $award_rankings[$award['id']] = [
                        'award' => $award,
                        'rankings' => [$winner]
                    ];
                }
            }
        }
    }
    
    // Get People's Choice Award rankings if public
    if ($visibility_settings['peoples_choice']['is_public']) {
        $stmt = $conn->prepare("
            SELECT c.id, c.name, c.contestant_number, COUNT(v.id) as vote_count,
                   @rank := IF(@prev = COUNT(v.id), @rank, @rownum) as rank,
                   @rownum := @rownum + 1,
                   @prev := COUNT(v.id)
            FROM contestants c
            JOIN peoples_choice_votes v ON c.id = v.contestant_id
            JOIN peoples_choice_voting pcv ON v.voting_id = pcv.id
            CROSS JOIN (SELECT @rownum := 0, @rank := 0, @prev := NULL) r
            WHERE c.event_id = ? AND pcv.event_id = ?
            GROUP BY c.id
            ORDER BY vote_count DESC, c.name
        ");
        $stmt->bind_param('ii', $event_id, $event_id);
        $stmt->execute();
        $peoples_choice_rankings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Rankings</title>
    <link rel="stylesheet" href="bootstrap-5.3.3-dist/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(to right, #e0c3fc, #8ec5fc);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            background: white;
            margin-bottom: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .rank-1 {
            background-color: #ffd700 !important;
            font-weight: bold;
        }
        .rank-2 {
            background-color: #c0c0c0 !important;
            font-weight: bold;
        }
        .rank-3 {
            background-color: #cd7f32 !important;
            font-weight: bold;
        }
        .section-header {
            margin-top: 30px;
            margin-bottom: 15px;
        }
        /* Update the CSS for rank tags to make them more beautiful */
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
        /* Update the rank-tag-1 style to match the winner tag color */
        .rank-tag-1 {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: #fff;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Event Rankings</h1>
            <p class="lead">View the latest rankings for our events</p>
        </div>
        
        <!-- Event Selection Form -->
        <form method="GET" class="mb-4">
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
            <h2 class="text-center mb-4"><?= htmlspecialchars($selected_event['name']) ?></h2>
            
            <!-- Overall Rankings -->
            <?php if ($visibility_settings['overall']['is_public'] && count($overall_rankings) >= 0): ?>
                <h3 class="section-header">🏆 Overall Rankings</h3>
                <div class="card p-3">
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
                                        <td><?= number_format($ranking['score'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No rankings are currently available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Award Rankings -->
            <?php foreach ($award_rankings as $award_id => $award_data): ?>
                <?php 
                $award = $award_data['award'];
                $rankings = $award_data['rankings'];
                ?>
                
                <h3 class="section-header">🏅 <?= htmlspecialchars($award['name']) ?></h3>
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
        <td colspan="<?= $award['type'] == 'criteria_based' ? '3' : '2' ?>" class="text-center">The recipient of this award is yet to be revealed.</td>
    </tr>
<?php endif; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
            
            <!-- People's Choice Award -->
            <?php if ($visibility_settings['peoples_choice']['is_public'] && count($peoples_choice_rankings) > 0): ?>
                <h3 class="section-header">👥 People's Choice Award</h3>
                <div class="card p-3">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Contestant #</th>
                                <th>Name</th>
                                <th>Votes</th>
                            </tr>
                        </thead>
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
                                <tr>
                                    <td colspan="4" class="text-center">No People's Choice voting data available.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if (!$visibility_settings['overall']['is_public'] && 
                     count($award_rankings) == 0 && 
                     !$visibility_settings['peoples_choice']['is_public']): ?>
                <div class="alert alert-info text-center">
                    <h4>No rankings have been made public for this event yet.</h4>
                    <p>Please check back later for updates.</p>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-info text-center">
                <h4>Please select an event to view rankings</h4>
            </div>
        <?php endif; ?>
    </div>

    <script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
