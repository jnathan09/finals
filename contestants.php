<?php
session_start();
require 'db.php';

// if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
//     header("Location: login.php");
//     exit();
// }

// Fetch events
$stmt = $conn->prepare("SELECT * FROM events");
$stmt->execute();
$events_result = $stmt->get_result();

// Get selected event ID
$event_id = $_GET['event_id'] ?? null;
$selected_event = null;
$category = '';
$current_stage = '';

// Fetch selected event details
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $event_result = $stmt->get_result();
    $selected_event = $event_result->fetch_assoc();
    $category = $selected_event['category'] ?? '';
    $current_stage = $selected_event['stage'] ?? '';
}

// Handle adding new contestant
if (isset($_POST['add_contestant'])) {
    $event_id = $_POST['event_id'];
    $category = $_POST['category']; // coming from hidden input
    $stage = $_POST['stage']; // Get stage from hidden input (current event stage)
    $contestant_number = $_POST['contestant_number']; // New contestant number field

    if ($category == 'School') {
        $student_id = $_POST['student_id'];

        // First check if this student is already registered for this event
        $check = $conn->prepare("SELECT c.id FROM contestants c JOIN aeris_students s ON c.name = s.name 
                                WHERE s.student_id = ? AND c.event_id = ?");
        $check->bind_param('si', $student_id, $event_id);
        $check->execute();
        $exists = $check->get_result();
        
        if ($exists->num_rows > 0) {
            $_SESSION['error_message'] = "This student is already registered for this event.";
            header("Location: contestants.php?event_id=$event_id");
            exit();
        }
        
        // Prepare and execute the lookup
        $stmt = $conn->prepare("SELECT name, age, gender, course, address FROM aeris_students WHERE student_id = ?");
        $stmt->bind_param('s', $student_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if student exists
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();

            // Insert student data into contestants
            $insert = $conn->prepare("INSERT INTO contestants (name, age, gender, course, address, contestant_number, event_id, stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param(
                'sissssss',
                $student['name'],
                $student['age'],
                $student['gender'],
                $student['course'],
                $student['address'],
                $contestant_number,
                $event_id,
                $stage
            );
            $insert->execute();
            $insert->close();
        } else {
            $_SESSION['error_message'] = "This student ID does not exist.";
            header("Location: contestants.php?event_id=$event_id");
            exit();
        }

        $stmt->close();
    }

    if ($category == 'Public') {
        $name = $_POST['name'];
        $age = $_POST['age'];
        $gender = $_POST['gender'];
        $course = $_POST['course'];
        $address = $_POST['address'];

        $check = $conn->prepare("SELECT id FROM contestants WHERE name = ? AND event_id = ?");
        $check->bind_param('si', $name, $event_id);
        $check->execute();
        $exists = $check->get_result();
        
        if ($exists->num_rows > 0) {
            $_SESSION['error_message'] = "A contestant with this name is already registered for this event.";
            header("Location: contestants.php?event_id=$event_id");
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO contestants (name, age, gender, course, address, contestant_number, event_id, stage) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssssss', $name, $age, $gender, $course, $address, $contestant_number, $event_id, $stage);
        $stmt->execute();
    }

    header("Location: contestants.php?event_id=$event_id");
    exit();
}

// Handle update contestant
if (isset($_POST['update_contestant'])) {
    $id = $_POST['contestant_id'];
    $name = $_POST['name'];
    $age = $_POST['age'];
    $gender = $_POST['gender'];
    $course = $_POST['course'];
    $address = $_POST['address'];
    $contestant_number = $_POST['contestant_number'];

    $stmt = $conn->prepare("UPDATE contestants SET name=?, age=?, gender=?, course=?, address=?, contestant_number=? WHERE id=?");
    $stmt->bind_param('ssssssi', $name, $age, $gender, $course, $address, $contestant_number, $id);
    $stmt->execute();

    header("Location: contestants.php?event_id=" . $_POST['event_id']);
    exit();
}

// Handle delete contestant
if (isset($_POST['delete_contestant'])) {
    $id = $_POST['contestant_id'];

    $stmt = $conn->prepare("DELETE FROM contestants WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    header("Location: contestants.php?event_id=" . $_POST['event_id']);
    exit();
}

// Fetch all contestants for the event
$contestants_result = null;
if ($event_id) {
    $stmt = $conn->prepare("SELECT * FROM contestants WHERE event_id = ?");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $contestants_result = $stmt->get_result();
}

// NEW CODE: Fetch all stages that have contestants for this event
$stages = [];
if ($event_id) {
    // First add the current stage from the event if it's not empty
    if (!empty($current_stage)) {
        $stages[] = $current_stage;
    }
    
    // Then get any other stages that have contestants assigned
    $stmt = $conn->prepare("SELECT DISTINCT stage FROM contestants WHERE event_id = ? AND stage IS NOT NULL");
    $stmt->bind_param('i', $event_id);
    $stmt->execute();
    $stages_result = $stmt->get_result();
    
    while ($stage_row = $stages_result->fetch_assoc()) {
        if (!empty($stage_row['stage']) && !in_array($stage_row['stage'], $stages)) {
            $stages[] = $stage_row['stage'];
        }
    }
}

// Handle remove from current stage
if (isset($_POST['remove_from_stage'])) {
    $id = $_POST['contestant_id'];

    $stmt = $conn->prepare("UPDATE contestants SET stage = NULL WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    header("Location: contestants.php?event_id=" . $_POST['event_id']);
    exit();
}

// Handle assign to stage
if (isset($_POST['assign_stage'])) {
    $contestant_id = $_POST['contestant_id'];
    $event_id = $_POST['event_id'];
    $stage = $_POST['assign_stage'];

    $stmt = $conn->prepare("UPDATE contestants SET stage = ? WHERE id = ? AND event_id = ?");
    $stmt->bind_param('sii', $stage, $contestant_id, $event_id);
    $stmt->execute();

    header("Location: contestants.php?event_id=" . $event_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contestants Management</title>
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
        <h2 class="mb-4">Contestants Management</h2>

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
                    <h4>👑 All Registered Contestants</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addContestantModal">+ Add Contestant</button>
            </div>
            <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Contestant #</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Course</th>
                            <th>Address</th>
                            <th>Stage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($contestants_result && $contestants_result->num_rows > 0): ?>
                            <?php while ($contestant = $contestants_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($contestant['contestant_number']) ?></td>
                                    <td><?= htmlspecialchars($contestant['name']) ?></td>
                                    <td><?= htmlspecialchars($contestant['age']) ?></td>
                                    <td><?= htmlspecialchars($contestant['gender']) ?></td>
                                    <td><?= htmlspecialchars($contestant['course']) ?></td>
                                    <td><?= htmlspecialchars($contestant['address']) ?></td>
                                    <td><?= htmlspecialchars($contestant['stage'] ?: 'Not assigned') ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editContestantModal"
                                            data-id="<?= $contestant['id'] ?>"
                                            data-name="<?= htmlspecialchars($contestant['name']) ?>"
                                            data-age="<?= htmlspecialchars($contestant['age']) ?>"
                                            data-gender="<?= htmlspecialchars($contestant['gender']) ?>"
                                            data-course="<?= htmlspecialchars($contestant['course']) ?>"
                                            data-address="<?= htmlspecialchars($contestant['address']) ?>"
                                            data-contestant-number="<?= htmlspecialchars($contestant['contestant_number']) ?>"
                                        >Edit</button>
                                        
                                        <?php if (!$contestant['stage']): ?>
                                        <button class="btn btn-success btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#assignStageModal"
                                            data-id="<?= $contestant['id'] ?>"
                                        >Assign Stage</button>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="contestant_id" value="<?= $contestant['id'] ?>">
                                            <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                            <button type="submit" name="delete_contestant" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this contestant?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8">No contestants found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                        </div>
            </div>

            <?php foreach ($stages as $stage): ?>
            <div class="stage-header">
                <h4>🤹 Contestants in <?= htmlspecialchars($stage) ?> stage</h4>
            </div>
            <div class="card p-3">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Contestant #</th>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Course</th>
                            <th>Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $conn->prepare("SELECT * FROM contestants WHERE event_id = ? AND stage = ?");
                        $stmt->bind_param('is', $event_id, $stage);
                        $stmt->execute();
                        $stage_contestants = $stmt->get_result();
                        
                        if ($stage_contestants && $stage_contestants->num_rows > 0):
                            while ($cont = $stage_contestants->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($cont['contestant_number']) ?></td>
                                <td><?= htmlspecialchars($cont['name']) ?></td>
                                <td><?= htmlspecialchars($cont['age']) ?></td>
                                <td><?= htmlspecialchars($cont['gender']) ?></td>
                                <td><?= htmlspecialchars($cont['course']) ?></td>
                                <td><?= htmlspecialchars($cont['address']) ?></td>
                                <td>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="contestant_id" value="<?= $cont['id'] ?>">
                                        <input type="hidden" name="event_id" value="<?= $event_id ?>">
                                        <button type="submit" name="remove_from_stage" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to remove this contestant from the current stage?');">
                                            Remove from Stage
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr><td colspan="7">No contestants in this stage.</td></tr>
                        <?php
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>           
        <?php endif; ?>
    </div>
</div>

<!-- Add Contestant Modal -->
<div class="modal fade" id="addContestantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Contestant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
                    <input type="hidden" name="stage" value="<?= htmlspecialchars($current_stage) ?>">

                    <div class="mb-3">
                        <label class="form-label">Contestant Number</label>
                        <input type="text" name="contestant_number" class="form-control" required>
                    </div>

                    <?php if ($category == 'School'): ?>
                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" class="form-control" required>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Age</label>
                            <input type="number" name="age" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_contestant" class="btn btn-primary">Add Contestant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Contestant Modal -->
<div class="modal fade" id="editContestantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered ">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Contestant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="contestant_id" id="edit_contestant_id">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Contestant Number</label>
                        <input type="text" name="contestant_number" id="edit_contestant_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" id="edit_age" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="edit_gender" class="form-select" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <input type="text" name="course" id="edit_course" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="edit_address" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_contestant" class="btn btn-warning">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Stage Modal -->
<div class="modal fade" id="assignStageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Assign to Stage</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="contestant_id" id="assign_contestant_id">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Stage</label>
                        <input type="text" name="assign_stage" class="form-control" value="<?= htmlspecialchars($current_stage) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_stage" class="btn btn-success">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="bootstrap-5.3.3-dist/js/bootstrap.bundle.min.js"></script>
<script>
    // For the edit contestant modal
    document.querySelectorAll('[data-bs-target="#editContestantModal"]').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_contestant_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_age').value = this.getAttribute('data-age');
            document.getElementById('edit_gender').value = this.getAttribute('data-gender');
            document.getElementById('edit_course').value = this.getAttribute('data-course');
            document.getElementById('edit_address').value = this.getAttribute('data-address');
            document.getElementById('edit_contestant_number').value = this.getAttribute('data-contestant-number');
        });
    });

    // For the assign stage modal
    document.querySelectorAll('[data-bs-target="#assignStageModal"]').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('assign_contestant_id').value = this.getAttribute('data-id');
        });
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