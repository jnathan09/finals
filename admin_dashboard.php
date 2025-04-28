<?php
session_start();
require 'db.php';

// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Handle adding new event
if (isset($_POST['add_event'])) {
    $name = $_POST['name'];
    $status = $_POST['status'];
    $stage = $_POST['stage'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $category = $_POST['category'];

    $stmt = $conn->prepare("INSERT INTO events (name, status, stage, start_date, end_date, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssss', $name, $status, $stage, $start_date, $end_date, $category);
    $stmt->execute();
    header("Location: admin_dashboard.php");
    exit();
}

// Handle event update
if (isset($_POST['update_event'])) {
    $event_id = $_POST['event_id'];
    $status = $_POST['status'];
    $new_stage = $_POST['stage'];

    // Get current stage from DB
    $stmt = $conn->prepare("SELECT stage FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $current_stage = $event['stage'];

    //UPDATE
    $stmt = $conn->prepare("UPDATE events SET status = ?, stage = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ssi', $status, $new_stage, $event_id);
    $stmt->execute();

    // TO SHOW ADVANCING MODAL
    if ($new_stage !== $current_stage) {
        // Set modal flag + pass event id to session
        $_SESSION['show_advancing_modal'] = true;
        $_SESSION['advancing_event_id'] = $event_id;
        $_SESSION['new_stage'] = $new_stage;
        $_SESSION['last_stage'] = $current_stage;
    }

    header("Location: admin_dashboard.php");
    exit();
}

// Handle event deletion
if (isset($_POST['delete_event'])) {
    $event_id = $_POST['event_id'];

    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();

    // Reset stage of all contestants in this event
    $stmt = $conn->prepare("UPDATE contestants SET stage = NULL WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();

    header("Location: admin_dashboard.php");
    exit();
}

// Fetch events
$stmt = $conn->prepare("SELECT * FROM events");
$stmt->execute();
$events_result = $stmt->get_result();

// Handle advancing contestants save
if (isset($_POST['save_advancing']) && isset($_POST['advance_event_id'])) {
    $event_id = $_POST['advance_event_id'];
    $new_stage = $_POST['new_stage'];
    $advancing_ids = isset($_POST['advancing_ids']) ? $_POST['advancing_ids'] : [];

    // Set selected contestants' stage to the new stage
    if (!empty($advancing_ids)) {
        foreach ($advancing_ids as $cid) {
            $stmt = $conn->prepare("UPDATE contestants SET stage = ? WHERE id = ? AND event_id = ?");
            $stmt->bind_param('sii', $new_stage, $cid, $event_id);
            $stmt->execute();
        }
    }

    header("Location: admin_dashboard.php");
    exit();
}

// Show advancing contestants modal if needed
$showAdvancingModal = false;
$advancingEventId = null;
$newStage = null;
$contestantsForAdvancing = [];
$last_stage = null;
if (isset($_SESSION['last_stage'])) {
    $last_stage = $_SESSION['last_stage'];
    unset($_SESSION['last_stage']); // Clean up after using it
}

if (isset($_SESSION['show_advancing_modal']) && $_SESSION['show_advancing_modal'] === true) {
    unset($_SESSION['show_advancing_modal']);
    $showAdvancingModal = true;

    // Get last updated event (or store event ID in session if needed)
    if (isset($_SESSION['advancing_event_id'])) {
        $advancingEventId = $_SESSION['advancing_event_id'];
        $newStage = $_SESSION['new_stage'];
        unset($_SESSION['advancing_event_id']);
        unset($_SESSION['new_stage']);
        
        $stmt = $conn->prepare("SELECT * FROM contestants WHERE event_id = ? and stage = ?");
        $stmt->bind_param('is', $advancingEventId, $last_stage);
        $stmt->execute();
        $contestantsForAdvancing = $stmt->get_result();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
    <a href="logout.php"onclick="return confirm('Confirm logout?');">🚪 Logout</a>
</div>

<div class="main">
    <div class="container">
        <h2 class="mb-4">Event Management</h2>
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addEventModal">+ Add Event</button>

        <div class="card p-3">
          <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Stage</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Category</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($events_result->num_rows > 0): ?>
                    <?php while ($event = $events_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($event['name']) ?></td>
                            <td><?= htmlspecialchars($event['status']) ?></td>
                            <td><?= htmlspecialchars($event['stage']) ?></td>
                            <td><?= htmlspecialchars($event['start_date']) ?></td>
                            <td><?= htmlspecialchars($event['end_date']) ?></td>
                            <td><?= htmlspecialchars($event['category']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#updateEventModal"
                                    onclick="populateUpdateModal(
                                        <?= $event['id'] ?>,
                                        '<?= htmlspecialchars($event['status'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($event['stage'], ENT_QUOTES) ?>'
                                    )">
                                    Update
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    <button type="submit" name="delete_event" onclick="return confirm('Are you sure you want to delete this event?');" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7">No events available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
                </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
          <div class="modal-header">
              <h5 class="modal-title">Add New Event</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <div class="mb-3">
                  <label class="form-label">Event Name</label>
                  <input type="text" name="name" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-select" required>
                      <option value="Upcoming">Upcoming</option>
                      <option value="Ongoing">Ongoing</option>
                      <option value="Closed">Closed</option>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label">Stage</label>
                  <input type="text" name="stage" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Start Date</label>
                  <input type="date" name="start_date" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">End Date</label>
                  <input type="date" name="end_date" class="form-control" required>
              </div>
              <div class="mb-3">
                  <label class="form-label">Category</label>
                  <select name="category" class="form-select" required>
                      <option value="School">School</option>
                      <option value="Public">Public</option>
                  </select>
              </div>
          </div>
          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="add_event" class="btn btn-primary">Add Event</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Update Event Modal -->
<div class="modal fade" id="updateEventModal" tabindex="-1" aria-labelledby="updateEventLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="update_event_id">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" id="update_status" required>
                            <option value="Upcoming">Upcoming</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Closed">Closed</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stage</label>
                        <input type="text" name="stage" class="form-control" id="update_stage" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_event" class="btn btn-warning">Update Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Advancing Contestants Modal -->
<div class="modal fade" id="advancingModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <input type="hidden" name="advance_event_id" value="<?= $advancingEventId ?>">
            <input type="hidden" name="new_stage" value="<?= $newStage ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Contestants for <?= htmlspecialchars($newStage) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($contestantsForAdvancing->num_rows > 0): ?>
                        <?php while ($row = $contestantsForAdvancing->fetch_assoc()): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="advancing_ids[]" value="<?= $row['id'] ?>" id="c<?= $row['id'] ?>">
                                <label class="form-check-label" for="c<?= $row['id'] ?>">
                                    <?= htmlspecialchars($row['name']) ?>
                                </label>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No contestants available.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_advancing" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>


<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
function populateUpdateModal(eventId, status, stage) {
    document.getElementById('update_event_id').value = eventId;
    document.getElementById('update_status').value = status;
    document.getElementById('update_stage').value = stage;
}

<?php if ($showAdvancingModal): ?>
    window.addEventListener('DOMContentLoaded', () => {
        const modal = new bootstrap.Modal(document.getElementById('advancingModal'));
        modal.show();
    });
<?php endif; ?>

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