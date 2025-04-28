<?php
session_start();
require 'db.php';

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

// Handle adding new award
if (isset($_POST['add_award'])) {
    $event_id = $_POST['event_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $type = $_POST['award_type'];
    
    if ($type === 'criteria_based') {
        $criteria_ids = isset($_POST['criteria_ids']) ? implode(',', $_POST['criteria_ids']) : '';
        $contestant_id = null;
    } else { // defined_winner
        $criteria_ids = null;
        $contestant_id = $_POST['contestant_id'] ?? null;
    }
    
    $stmt = $conn->prepare("INSERT INTO awards (event_id, name, description, type, criteria_ids, contestant_id) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssi', $event_id, $name, $description, $type, $criteria_ids, $contestant_id);
    $stmt->execute();
    
    header("Location: awards.php?event_id=$event_id");
    exit();
}

// Handle updating award
if (isset($_POST['update_award'])) {
    $award_id = $_POST['award_id'];
    $event_id = $_POST['event_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $type = $_POST['award_type'];
    
    if ($type === 'criteria_based') {
        $criteria_ids = isset($_POST['criteria_ids']) ? implode(',', $_POST['criteria_ids']) : '';
        $contestant_id = null;
    } else { // defined_winner
        $criteria_ids = null;
        $contestant_id = $_POST['contestant_id'] ?? null;
    }
    
    $stmt = $conn->prepare("UPDATE awards SET name = ?, description = ?, type = ?, criteria_ids = ?, contestant_id = ? 
                           WHERE id = ?");
    $stmt->bind_param('ssssii', $name, $description, $type, $criteria_ids, $contestant_id, $award_id);
    $stmt->execute();
    
    header("Location: awards.php?event_id=$event_id");
    exit();
}

// Handle deleting award
if (isset($_POST['delete_award'])) {
    $award_id = $_POST['award_id'];
    $event_id = $_POST['event_id'];
    
    $stmt = $conn->prepare("DELETE FROM awards WHERE id = ?");
    $stmt->bind_param('i', $award_id);
    $stmt->execute();

    header("Location: awards.php?event_id=$event_id");
    exit();
}

// Fetch all awards for the selected event
$awards_result = null;
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM awards WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $awards_result = $stmt->get_result();
}

// Fetch criteria for the selected event (for dropdown in the add/edit modals)
$criteria_result = null;
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM criteria WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $criteria_result = $stmt->get_result();
    $all_criteria = [];
    while ($criteria = $criteria_result->fetch_assoc()) {
        $all_criteria[] = $criteria;
    }
}

// Fetch contestants for the selected event (for dropdown in the add/edit modals)
$contestants_result = null;
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM contestants WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $contestants_result = $stmt->get_result();
    $all_contestants = [];
    while ($contestant = $contestants_result->fetch_assoc()) {
        $all_contestants[] = $contestant;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Awards Management.</title>
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
        .description-cell {
            max-width: 200px;
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
        <h2 class="mb-4">Awards Management</h2>
        
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
                <h4>🎉 Awards for <?= htmlspecialchars($selected_event['name']) ?></h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAwardModal">
                    + Add Award
                </button>
            </div>
            
            <div class="card p-3">
                <!-- Awards Table -->
              <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Award Name</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($awards_result && $awards_result->num_rows > 0): ?>
                            <?php while ($award = $awards_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($award['name']) ?></td>
                                    <td class="description-cell" title="<?= htmlspecialchars($award['description']) ?>">
                                        <?= htmlspecialchars($award['description']) ?>
                                    </td>
                                    <td>
                                        <?php if ($award['type'] == 'criteria_based'): ?>
                                            Based on Criteria
                                        <?php else: ?>
                                            Defined Winner
                                        <?php endif; ?>
                                    </td>
                                    <td class="description-cell">
                                        <?php if ($award['type'] == 'criteria_based' && !empty($award['criteria_ids'])): ?> <i>criteria: </i>
                                            <?php 
                                                $criteria_array = explode(',', $award['criteria_ids']);
                                                $criteria_names = [];
                                                foreach ($all_criteria as $criteria) {
                                                    if (in_array($criteria['id'], $criteria_array)) {
                                                        $criteria_names[] = $criteria['name'];
                                                    }
                                                }
                                                echo htmlspecialchars(implode(', ', $criteria_names));
                                            ?>
                                        <?php elseif ($award['type'] == 'defined_winner' && !empty($award['contestant_id'])): ?> <i>winner: </i>
                                            <?php
                                                $found_contestant = null;
                                                foreach ($all_contestants as $contestant) {
                                                    if ($contestant['id'] == $award['contestant_id']) {
                                                        $found_contestant = $contestant;
                                                        break;
                                                    }
                                                }
                                                echo $found_contestant ? htmlspecialchars($found_contestant['name']) : 'Unknown contestant';
                                            ?>
                                        <?php else: ?>
                                            Not specified
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editAwardModal"
                                                data-id="<?= $award['id'] ?>"
                                                data-name="<?= htmlspecialchars($award['name']) ?>"
                                                data-description="<?= htmlspecialchars($award['description']) ?>"
                                                data-type="<?= htmlspecialchars($award['type']) ?>"
                                                data-criteria-ids="<?= htmlspecialchars($award['criteria_ids'] ?? '') ?>"
                                                data-contestant-id="<?= htmlspecialchars($award['contestant_id'] ?? '') ?>">
                                            Edit
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="award_id" value="<?= $award['id'] ?>">
                                            <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                            <button type="submit" name="delete_award" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to delete this award?');">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No awards defined for this event.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                        </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Award Modal -->
<div class="modal fade" id="addAwardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Award</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Award Type</label>
                        <select name="award_type" id="add_award_type" class="form-select" required onchange="toggleAwardFields('add')">
                            <option value="">-- Select Award Type --</option>
                            <option value="criteria_based">Award based on Criteria</option>
                            <option value="defined_winner">Award with Defined Winner</option>
                        </select>
                    </div>
                    
                    <div id="add_common_fields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Award Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div id="add_criteria_fields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Select Criteria</label>
                            <select name="criteria_ids[]" class="form-select" multiple size="5">
                                <?php foreach ($all_criteria as $criteria): ?>
                                    <option value="<?= $criteria['id'] ?>"><?= htmlspecialchars($criteria['name']) ?> (<?= htmlspecialchars($criteria['stage']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl (or Cmd) to select multiple criteria</small>
                        </div>
                    </div>
                    
                    <div id="add_contestant_fields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Select Winner</label>
                            <select name="contestant_id" class="form-select">
                                <option value="">-- Select Contestant --</option>
                                <?php foreach ($all_contestants as $contestant): ?>
                                    <option value="<?= $contestant['id'] ?>"><?= htmlspecialchars($contestant['name']) ?> (<?= htmlspecialchars($contestant['contestant_number']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_award" class="btn btn-primary">Add Award</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Award Modal -->
<div class="modal fade" id="editAwardModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Award</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="award_id" id="edit_award_id">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Award Type</label>
                        <select name="award_type" id="edit_award_type" class="form-select" required onchange="toggleAwardFields('edit')">
                            <option value="criteria_based">Award based on Criteria</option>
                            <option value="defined_winner">Award with Defined Winner</option>
                        </select>
                    </div>
                    
                    <div id="edit_common_fields">
                        <div class="mb-3">
                            <label class="form-label">Award Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div id="edit_criteria_fields">
                        <div class="mb-3">
                            <label class="form-label">Select Criteria</label>
                            <select name="criteria_ids[]" id="edit_criteria_ids" class="form-select" multiple size="5">
                                <?php foreach ($all_criteria as $criteria): ?>
                                    <option value="<?= $criteria['id'] ?>"><?= htmlspecialchars($criteria['name']) ?> (<?= htmlspecialchars($criteria['stage']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl (or Cmd) to select multiple criteria</small>
                        </div>
                    </div>
                    
                    <div id="edit_contestant_fields">
                        <div class="mb-3">
                            <label class="form-label">Select Winner</label>
                            <select name="contestant_id" id="edit_contestant_id" class="form-select">
                                <option value="">-- Select Contestant --</option>
                                <?php foreach ($all_contestants as $contestant): ?>
                                    <option value="<?= $contestant['id'] ?>"><?= htmlspecialchars($contestant['name']) ?> (<?= htmlspecialchars($contestant['contestant_number']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_award" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleAwardFields(mode) {
        const awardType = document.getElementById(mode + '_award_type').value;
        
        // Show common fields when a type is selected
        document.getElementById(mode + '_common_fields').style.display = awardType ? 'block' : 'none';
        
        // Toggle specific fields based on award type
        if (awardType === 'criteria_based') {
            document.getElementById(mode + '_criteria_fields').style.display = 'block';
            document.getElementById(mode + '_contestant_fields').style.display = 'none';
        } else if (awardType === 'defined_winner') {
            document.getElementById(mode + '_criteria_fields').style.display = 'none';
            document.getElementById(mode + '_contestant_fields').style.display = 'block';
        } else {
            document.getElementById(mode + '_criteria_fields').style.display = 'none';
            document.getElementById(mode + '_contestant_fields').style.display = 'none';
        }
    }
    
    // Handle edit modal data population
    document.addEventListener('DOMContentLoaded', function() {
        const editModal = document.getElementById('editAwardModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const description = button.getAttribute('data-description');
                const type = button.getAttribute('data-type');
                const criteriaIds = button.getAttribute('data-criteria-ids');
                const contestantId = button.getAttribute('data-contestant-id');
                
                document.getElementById('edit_award_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_award_type').value = type;
                
                // Set the award type and toggle appropriate fields
                toggleAwardFields('edit');
                
                // Set criteria values if it's a criteria-based award
                if (type === 'criteria_based' && criteriaIds) {
                    const criteriaArray = criteriaIds.split(',');
                    const selectElement = document.getElementById('edit_criteria_ids');
                    
                    for (let i = 0; i < selectElement.options.length; i++) {
                        selectElement.options[i].selected = criteriaArray.includes(selectElement.options[i].value);
                    }
                }
                
                // Set contestant value if it's an award with defined winner
                if (type === 'defined_winner' && contestantId) {
                    document.getElementById('edit_contestant_id').value = contestantId;
                }
            });
        }
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        });
        
</script>
</body>
</html> 