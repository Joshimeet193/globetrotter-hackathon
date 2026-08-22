<?php
/* =========================================================
   itinerary-builder.php
   For one specific trip: add stops (cities), and add
   activities to each stop. Uses ?trip_id=... in the URL.
   ========================================================= */

session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// We need to know WHICH trip we're building an itinerary for
$trip_id = (int) ($_GET['trip_id'] ?? 0);

if ($trip_id <= 0) {
    header('Location: my-trips.php');
    exit;
}

/* ---------------------------------------------------------
   Fetch the trip itself, making sure it belongs to this user
   --------------------------------------------------------- */
$stmt = $conn->prepare("SELECT trip_id, trip_name, start_date, end_date FROM trips WHERE trip_id = ? AND user_id = ?");
$stmt->bind_param("ii", $trip_id, $user_id);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

// If no trip found (wrong id, or belongs to someone else), send user back
if (!$trip) {
    header('Location: my-trips.php');
    exit;
}

$success_message = '';
$error_message   = '';

/* ---------------------------------------------------------
   Handle "Add Stop" form submission (POST)
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stop'])) {
    $city_name  = trim($_POST['city_name']);
    $country    = trim($_POST['country']);
    $start_date = $_POST['stop_start_date'];
    $end_date   = $_POST['stop_end_date'];

    if ($city_name === '' || $start_date === '' || $end_date === '') {
        $error_message = 'Please fill in city name, start date and end date.';
    } else {
        $stmt = $conn->prepare("INSERT INTO stops (trip_id, city_name, country, start_date, end_date)
                                 VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $trip_id, $city_name, $country, $start_date, $end_date);

        if ($stmt->execute()) {
            $success_message = $city_name . ' was added to your itinerary!';
        } else {
            $error_message = 'Something went wrong while adding the stop.';
        }
        $stmt->close();
    }
}

/* ---------------------------------------------------------
   Handle "Add Activity" form submission (POST)
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $stop_id       = (int) $_POST['stop_id'];
    $activity_name = trim($_POST['activity_name']);
    $category      = $_POST['category'];
    $cost          = (float) $_POST['cost'];
    $duration      = (float) $_POST['duration'];

    if ($stop_id <= 0 || $activity_name === '') {
        $error_message = 'Please fill in the activity name.';
    } else {
        $stmt = $conn->prepare("INSERT INTO activities (stop_id, activity_name, category, cost, duration)
                                 VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issdd", $stop_id, $activity_name, $category, $cost, $duration);

        if ($stmt->execute()) {
            $success_message = $activity_name . ' was added!';
        } else {
            $error_message = 'Something went wrong while adding the activity.';
        }
        $stmt->close();
    }
}

/* ---------------------------------------------------------
   Handle "Delete Stop" (also removes its activities first,
   since activities.stop_id points to stops.stop_id)
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stop'])) {
    $stop_id = (int) $_POST['stop_id'];

    // Delete the activities under this stop first
    $del_act = $conn->prepare("DELETE FROM activities WHERE stop_id = ?");
    $del_act->bind_param("i", $stop_id);
    $del_act->execute();
    $del_act->close();

    // Then delete the stop itself (only if it belongs to this trip)
    $del_stop = $conn->prepare("DELETE FROM stops WHERE stop_id = ? AND trip_id = ?");
    $del_stop->bind_param("ii", $stop_id, $trip_id);
    $del_stop->execute();
    $del_stop->close();

    $success_message = 'Stop removed from itinerary.';
}

/* ---------------------------------------------------------
   Handle "Delete Activity"
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    $activity_id = (int) $_POST['activity_id'];

    // Join through stops to make sure this activity belongs to THIS trip
    $stmt = $conn->prepare("DELETE activities FROM activities
                             INNER JOIN stops ON activities.stop_id = stops.stop_id
                             WHERE activities.activity_id = ? AND stops.trip_id = ?");
    $stmt->bind_param("ii", $activity_id, $trip_id);
    $stmt->execute();
    $stmt->close();

    $success_message = 'Activity removed.';
}

/* ---------------------------------------------------------
   Fetch all stops for this trip, and their activities
   --------------------------------------------------------- */
$stops = [];
$stmt = $conn->prepare("SELECT stop_id, city_name, country, start_date, end_date
                         FROM stops WHERE trip_id = ? ORDER BY start_date ASC");
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['activities'] = []; // we'll fill this in below
    $stops[$row['stop_id']] = $row;
}
$stmt->close();

// Fetch activities for all stops in one query, then group them by stop_id
if (count($stops) > 0) {
    $stop_ids = implode(',', array_map('intval', array_keys($stops)));
    $act_result = $conn->query("SELECT activity_id, stop_id, activity_name, category, cost, duration
                                 FROM activities WHERE stop_id IN ($stop_ids) ORDER BY activity_id ASC");
    while ($act = $act_result->fetch_assoc()) {
        $stops[$act['stop_id']]['activities'][] = $act;
    }
}

$active_page = 'my-trips';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($trip['trip_name']); ?> - Itinerary - GlobeTrotter</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="container py-5">

    <!-- Trip header -->
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h1 class="section-title mb-0"><i class="bi bi-map"></i> <?php echo htmlspecialchars($trip['trip_name']); ?></h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStopModal">
            <i class="bi bi-plus-circle"></i> Add Stop
        </button>
    </div>
    <p class="text-muted mb-4">
        <i class="bi bi-calendar3"></i>
        <?php echo date('d M Y', strtotime($trip['start_date'])); ?>
        &mdash;
        <?php echo date('d M Y', strtotime($trip['end_date'])); ?>
    </p>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- ===== Itinerary timeline: one "timeline-day" block per stop ===== -->
    <?php if (count($stops) === 0): ?>
        <p class="text-muted text-center py-5">
            <i class="bi bi-geo-alt" style="font-size: 2.5rem;"></i><br>
            No stops added yet. Click "Add Stop" to start building your itinerary.
        </p>
    <?php else: ?>
        <?php foreach ($stops as $stop): ?>
            <div class="timeline-day mb-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <div>
                        <h4><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($stop['city_name']); ?>
                            <?php if (!empty($stop['country'])): ?>
                                <small class="text-muted">, <?php echo htmlspecialchars($stop['country']); ?></small>
                            <?php endif; ?>
                        </h4>
                        <p class="text-muted small mb-2">
                            <i class="bi bi-calendar3"></i>
                            <?php echo date('d M Y', strtotime($stop['start_date'])); ?>
                            &mdash;
                            <?php echo date('d M Y', strtotime($stop['end_date'])); ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-bs-toggle="modal" data-bs-target="#addActivityModal"
                                data-stop-id="<?php echo $stop['stop_id']; ?>"
                                data-stop-name="<?php echo htmlspecialchars($stop['city_name']); ?>">
                            <i class="bi bi-plus-circle"></i> Add Activity
                        </button>
                        <form method="POST" onsubmit="return confirm('Remove this stop and all its activities?');">
                            <input type="hidden" name="stop_id" value="<?php echo $stop['stop_id']; ?>">
                            <button type="submit" name="delete_stop" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Activities for this stop -->
                <?php if (count($stop['activities']) === 0): ?>
                    <p class="text-muted small fst-italic">No activities added yet for this stop.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($stop['activities'] as $act): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($act['activity_name']); ?></h6>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars(ucfirst($act['category'])); ?>
                                        </p>
                                        <p class="card-text small mb-2">
                                            <i class="bi bi-wallet2"></i>
                                            <?php echo $act['cost'] > 0 ? '₹' . number_format($act['cost']) : 'Free'; ?>
                                            &middot;
                                            <i class="bi bi-clock"></i> <?php echo $act['duration']; ?> hrs
                                        </p>
                                        <form method="POST" onsubmit="return confirm('Remove this activity?');">
                                            <input type="hidden" name="activity_id" value="<?php echo $act['activity_id']; ?>">
                                            <button type="submit" name="delete_activity" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-trash"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- =========================================================
     Add Stop modal
     ========================================================= -->
<div class="modal fade" id="addStopModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-geo-alt"></i> Add a Stop</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <div class="form-floating mb-3">
            <input type="text" name="city_name" class="form-control" placeholder="City Name" required>
            <label><i class="bi bi-geo-alt"></i> City Name</label>
        </div>

        <div class="form-floating mb-3">
            <input type="text" name="country" class="form-control" placeholder="Country">
            <label><i class="bi bi-flag"></i> Country</label>
        </div>

        <div class="row">
            <div class="col-6">
                <div class="form-floating mb-3">
                    <input type="date" name="stop_start_date" class="form-control" required>
                    <label>Arrival Date</label>
                </div>
            </div>
            <div class="col-6">
                <div class="form-floating mb-3">
                    <input type="date" name="stop_end_date" class="form-control" required>
                    <label>Departure Date</label>
                </div>
            </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_stop" class="btn btn-primary"><i class="bi bi-check-circle"></i> Add Stop</button>
      </div>
    </form>
  </div>
</div>

<!-- =========================================================
     Add Activity modal (shared - JS fills in which stop it's for)
     ========================================================= -->
<div class="modal fade" id="addActivityModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-star"></i> Add Activity to <span id="modalStopName">this stop</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">

        <input type="hidden" name="stop_id" id="modalStopIdInput">

        <div class="form-floating mb-3">
            <input type="text" name="activity_name" class="form-control" placeholder="Activity Name" required>
            <label><i class="bi bi-star"></i> Activity Name</label>
        </div>

        <div class="form-floating mb-3">
            <select name="category" class="form-select">
                <option value="sightseeing">Sightseeing</option>
                <option value="food">Food</option>
                <option value="adventure">Adventure</option>
            </select>
            <label>Category</label>
        </div>

        <div class="row">
            <div class="col-6">
                <div class="form-floating mb-3">
                    <input type="number" name="cost" class="form-control" placeholder="Cost" min="0" step="1" value="0">
                    <label><i class="bi bi-wallet2"></i> Cost (₹)</label>
                </div>
            </div>
            <div class="col-6">
                <div class="form-floating mb-3">
                    <input type="number" name="duration" class="form-control" placeholder="Duration" min="0" step="0.5" value="1">
                    <label><i class="bi bi-clock"></i> Duration (hrs)</label>
                </div>
            </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" name="add_activity" class="btn btn-primary"><i class="bi bi-check-circle"></i> Add Activity</button>
      </div>
    </form>
  </div>
</div>

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fill in which stop the "Add Activity" modal is for, based on which
// stop's "Add Activity" button was clicked.
const addActivityModal = document.getElementById('addActivityModal');
if (addActivityModal) {
    addActivityModal.addEventListener('show.bs.modal', function (event) {
        const button  = event.relatedTarget;
        const stopId   = button.getAttribute('data-stop-id');
        const stopName = button.getAttribute('data-stop-name');

        document.getElementById('modalStopIdInput').value = stopId;
        document.getElementById('modalStopName').textContent = stopName;
    });
}
</script>
</body>
</html>
