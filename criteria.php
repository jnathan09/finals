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

// Handle adding new criteria
if (isset($_POST['add_criteria'])) {
    $event_id = $_POST['event_id'];
    $stage = $_POST['stage'];
    $name = $_POST['name'];
    $description = $_POST['description']; // Added description field
    $percentage = $_POST['percentage'];
    
    // First check if adding this percentage would exceed 100%
    $stmt = $conn->prepare("SELECT SUM(percentage) as total FROM criteria WHERE event_id = ? AND stage = ?");
    $stmt->bind_param('is', $event_id, $stage);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_total = $result->fetch_assoc()['total'] ?? 0;
    
    if (($current_total + $percentage) > 100) {
        $_SESSION['error_message'] = "Total percentage cannot exceed 100%. Current total is {$current_total}%.";
    } else {
        $stmt = $conn->prepare("INSERT INTO criteria (event_id, stage, name, description, percentage) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('isssi', $event_id, $stage, $name, $description, $percentage);
        $stmt->execute();
    }
    
    header("Location: criteria.php?event_id=$event_id");
    exit();
}

// Handle updating criteria
if (isset($_POST['update_criteria'])) {
    $criteria_id = $_POST['criteria_id'];
    $event_id = $_POST['event_id'];
    $name = $_POST['name'];
    $description = $_POST['description']; // Added description field
    $percentage = $_POST['percentage'];
    $stage = $_POST['stage'];
    
    // Check if the new percentage would exceed 100%
    $stmt = $conn->prepare("SELECT SUM(percentage) as total FROM criteria WHERE event_id = ? AND stage = ? AND id != ?");
    $stmt->bind_param('isi', $event_id, $stage, $criteria_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $other_criteria_total = $result->fetch_assoc()['total'] ?? 0;
    
    if (($other_criteria_total + $percentage) > 100) {
        $_SESSION['error_message'] = "Total percentage cannot exceed 100%. Other criteria total is {$other_criteria_total}%.";
    } else {
        $stmt = $conn->prepare("UPDATE criteria SET name = ?, description = ?, percentage = ? WHERE id = ?");
        $stmt->bind_param('ssii', $name, $description, $percentage, $criteria_id);
        $stmt->execute();
    }
    
    header("Location: criteria.php?event_id=$event_id");
    exit();
}

// Handle deleting criteria
if (isset($_POST['delete_criteria'])) {
    $criteria_id = $_POST['criteria_id'];
    $event_id = $_POST['event_id'];
    
    $stmt = $conn->prepare("DELETE FROM criteria WHERE id = ?");
    $stmt->bind_param('i', $criteria_id);
    $stmt->execute();

    header("Location: criteria.php?event_id=$event_id");
    exit();
}

// Fetch all stages that have been used for this event in the criteria table
// This gets both the current stage and any previous stages that have criteria
$stages = [];
if ($event_id) {
    // First add the current stage from the event
    if (!empty($current_stage)) {
        $stages[] = $current_stage;
    }
    
    // Then get any other stages that have criteria defined
    $stmt = $conn->prepare("SELECT DISTINCT stage FROM criteria WHERE event_id = ?");
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
    <title>Criteria Management</title>
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
        <h2 class="mb-4">Criteria Management</h2>
        
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
        
        <?php if ($event_id && count($stages) > 0): ?>
            <?php foreach ($stages as $stage): ?>
                <!-- Stage header with Add Criteria button only for current stage -->
                <div class="stage-header">
                    <h4>📜 Criteria for <?= htmlspecialchars($stage) ?> stage</h4>
                    <?php if ($stage === $current_stage): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCriteriaModal" 
                                onclick="setupAddCriteriaModal('<?= htmlspecialchars($stage) ?>')">
                            + Add Criteria
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="card p-3">
                    <!-- Criteria Table -->
                     <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Criteria</th>
                                <th>Description</th>
                                <th>Percentage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $conn->prepare("SELECT * FROM criteria WHERE event_id = ? AND stage = ?");
                            $stmt->bind_param('is', $event_id, $stage);
                            $stmt->execute();
                            $criteria_result = $stmt->get_result();
                            $total_percentage = 0;
                            
                            if ($criteria_result->num_rows > 0):
                                while ($criteria = $criteria_result->fetch_assoc()):
                                    $total_percentage += $criteria['percentage'];
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($criteria['name']) ?></td>
                                    <td class="description-cell" title="<?= htmlspecialchars($criteria['description'] ?? '') ?>">
                                        <?= htmlspecialchars($criteria['description'] ?? '') ?>
                                    </td>
                                    <td><?= htmlspecialchars($criteria['percentage']) ?>%</td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" 
                                                data-bs-target="#editCriteriaModal"
                                                data-id="<?= $criteria['id'] ?>"
                                                data-name="<?= htmlspecialchars($criteria['name']) ?>"
                                                data-description="<?= htmlspecialchars($criteria['description'] ?? '') ?>"
                                                data-percentage="<?= htmlspecialchars($criteria['percentage']) ?>"
                                                data-stage="<?= htmlspecialchars($stage) ?>">
                                            Edit
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="criteria_id" value="<?= $criteria['id'] ?>">
                                            <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                            <button type="submit" name="delete_criteria" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to delete this criteria?');">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <tr>
                                    <td colspan="4" class="text-center">No criteria defined for this stage.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <!-- Total row -->
                            <tr>
                                <td class="fw-bold">Total</td>
                                <td></td>
                                <td class="<?= ($total_percentage != 100) ? 'percentage-warning' : 'percentage-total' ?>">
                                    <?= $total_percentage ?>% <?= ($total_percentage != 100) ? '(Should be 100%)' : '' ?>
                                </td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                            </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Criteria Modal -->
<div class="modal fade" id="addCriteriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Criteria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <input type="hidden" name="stage" id="add_stage">
                    
                    <div class="mb-3">
                        <label class="form-label">Criteria Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional description of this criteria"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Percentage (%)</label>
                        <input type="number" name="percentage" class="form-control" min="1" max="100" required>
                        <small class="text-muted">Enter a value between 1 and 100. Total percentage for all criteria should be 100%.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_criteria" class="btn btn-primary">Add Criteria</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Criteria Modal -->
<div class="modal fade" id="editCriteriaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Criteria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="criteria_id" id="edit_id">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <input type="hidden" name="stage" id="edit_stage">
                    
                    <div class="mb-3">
                        <label class="form-label">Criteria Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="Optional description of this criteria"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Percentage (%)</label>
                        <input type="number" name="percentage" id="edit_percentage" class="form-control" min="1" max="100" required>
                        <small class="text-muted">Enter a value between 1 and 100. Total percentage for all criteria should be 100%.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_criteria" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
    function setupAddCriteriaModal(stage) {
        document.getElementById('add_stage').value = stage;
    }
    
    // Handle edit modal data population
    var editModal = document.getElementById('editCriteriaModal');
    editModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('edit_id').value = button.getAttribute('data-id');
        document.getElementById('edit_name').value = button.getAttribute('data-name');
        document.getElementById('edit_description').value = button.getAttribute('data-description');
        document.getElementById('edit_percentage').value = button.getAttribute('data-percentage');
        document.getElementById('edit_stage').value = button.getAttribute('data-stage');
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