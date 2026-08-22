<?php
/* =========================================================
   itinerary-builder.php
   "Manage trip" page: shows stops (cities) + their activities
   for one trip, lets you delete a stop or a single activity.
   Fixed: old version used tables "trips/stops/activities"
   which don't exist. Now uses the real schema:
   TRIP -> TRIP_STOP (+CITY/COUNTRY) -> ITINERARY (+ACTIVITY).
   Adding new stops/activities is done on city-search.php and
   activity-search.php (already write to TRIP_STOP / ITINERARY
   correctly) - linked from here instead of duplicating insert
   logic in a second place.
   ========================================================= */
session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$trip_id = (int) ($_GET['trip_id'] ?? 0);
if ($trip_id <= 0) {
    header('Location: my-trips.php');
    exit;
}

// Fetch the trip, making sure it belongs to this user
$stmt = $conn->prepare("SELECT Trip_ID, Trip_Name, Start_Date, End_Date FROM TRIP WHERE Trip_ID = ? AND User_ID = ?");
$stmt->bind_param("ii", $trip_id, $user_id);
$stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trip) {
    header('Location: my-trips.php');
    exit;
}

$success_message = '';
$error_message = '';

// ----- Delete a stop (and its itinerary entries) -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stop'])) {
    $stop_id = (int) $_POST['stop_id'];

    // ownership check via join
    $check = $conn->prepare("SELECT Stop_ID FROM TRIP_STOP WHERE Stop_ID = ? AND Trip_ID = ?");
    $check->bind_param("ii", $stop_id, $trip_id);
    $check->execute();
    $owns_stop = $check->get_result()->num_rows > 0;
    $check->close();

    if ($owns_stop) {
        $del_it = $conn->prepare("DELETE FROM ITINERARY WHERE Stop_ID = ?");
        $del_it->bind_param("i", $stop_id);
        $del_it->execute();
        $del_it->close();

        $del_stop = $conn->prepare("DELETE FROM TRIP_STOP WHERE Stop_ID = ? AND Trip_ID = ?");
        $del_stop->bind_param("ii", $stop_id, $trip_id);
        $del_stop->execute();
        $del_stop->close();

        $success_message = 'Stop removed from itinerary.';
    } else {
        $error_message = 'Invalid stop.';
    }
}

// ----- Delete a single itinerary (activity) entry -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity'])) {
    $itinerary_id = (int) $_POST['itinerary_id'];

    $stmt = $conn->prepare("DELETE ITINERARY FROM ITINERARY
                             INNER JOIN TRIP_STOP ON ITINERARY.Stop_ID = TRIP_STOP.Stop_ID
                             WHERE ITINERARY.Itinerary_ID = ? AND TRIP_STOP.Trip_ID = ?");
    $stmt->bind_param("ii", $itinerary_id, $trip_id);
    $stmt->execute();
    $stmt->close();
    $success_message = 'Activity removed.';
}

// ----- Fetch stops for this trip (joined with CITY/COUNTRY) -----
$sql_stops = "SELECT TRIP_STOP.Stop_ID, TRIP_STOP.Stop_Order, TRIP_STOP.Arrival_Date, TRIP_STOP.Departure_Date,
                     CITY.City_Name, COUNTRY.Country_Name
              FROM TRIP_STOP
              JOIN CITY ON TRIP_STOP.City_ID = CITY.City_ID
              JOIN COUNTRY ON CITY.Country_ID = COUNTRY.Country_ID
              WHERE TRIP_STOP.Trip_ID = ?
              ORDER BY TRIP_STOP.Stop_Order ASC";
$stmt = $conn->prepare($sql_stops);
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();
$stops = [];
while ($row = $result->fetch_assoc()) {
    $row['activities'] = [];
    $stops[$row['Stop_ID']] = $row;
}
$stmt->close();

// ----- Fetch activities (ITINERARY + ACTIVITY) for all stops in one query -----
if (count($stops) > 0) {
    $stop_ids = implode(',', array_map('intval', array_keys($stops)));
    $sql_act = "SELECT ITINERARY.Itinerary_ID, ITINERARY.Stop_ID, ITINERARY.Activity_Date, ITINERARY.Activity_Cost,
                       ACTIVITY.Activity_Name, ACTIVITY.Activity_Type, ACTIVITY.Duration
                FROM ITINERARY
                JOIN ACTIVITY ON ITINERARY.Activity_ID = ACTIVITY.Activity_ID
                WHERE ITINERARY.Stop_ID IN ($stop_ids)
                ORDER BY ITINERARY.Activity_Date ASC";
    $act_result = $conn->query($sql_act);
    while ($act = $act_result->fetch_assoc()) {
        $stops[$act['Stop_ID']]['activities'][] = $act;
    }
}

$active_page = 'my-trips';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($trip['Trip_Name']); ?> - Manage Itinerary - GlobeTrotter</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container py-5">
<div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
<h1 class="section-title mb-0"><i class="bi bi-map"></i> <?php echo htmlspecialchars($trip['Trip_Name']); ?></h1>
<div class="d-flex gap-2">
<a href="city-search.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Add City</a>
<a href="activity-search.php" class="btn btn-secondary btn-sm"><i class="bi bi-plus-circle"></i> Add Activity</a>
</div>
</div>

<p class="text-muted mb-4">
<i class="bi bi-calendar3"></i>
<?php echo date('d M Y', strtotime($trip['Start_Date'])); ?>
&mdash;
<?php echo date('d M Y', strtotime($trip['End_Date'])); ?>
</p>

<?php if ($success_message): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<?php if (count($stops) === 0): ?>
<p class="text-muted text-center py-5">
<i class="bi bi-geo-alt" style="font-size: 2.5rem;"></i><br>
No stops added yet. Click "Add City" to start building your itinerary.
</p>
<?php else: ?>
<?php foreach ($stops as $stop): ?>
<div class="timeline-day mb-4">
<div class="d-flex justify-content-between align-items-start flex-wrap">
<div>
<h4><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($stop['City_Name']); ?>
<small class="text-muted">, <?php echo htmlspecialchars($stop['Country_Name']); ?></small>
</h4>
<p class="text-muted small mb-2">
<i class="bi bi-calendar3"></i>
<?php echo date('d M Y', strtotime($stop['Arrival_Date'])); ?>
&mdash;
<?php echo date('d M Y', strtotime($stop['Departure_Date'])); ?>
</p>
</div>
<form method="POST" onsubmit="return confirm('Remove this stop and all its activities?');">
<input type="hidden" name="stop_id" value="<?php echo $stop['Stop_ID']; ?>">
<button type="submit" name="delete_stop" class="btn btn-outline-primary btn-sm">
<i class="bi bi-trash"></i> Remove Stop
</button>
</form>
</div>

<?php if (count($stop['activities']) === 0): ?>
<p class="text-muted small fst-italic">No activities added yet for this stop.</p>
<?php else: ?>
<div class="row g-3">
<?php foreach ($stop['activities'] as $act): ?>
<div class="col-md-6 col-lg-4">
<div class="card">
<div class="card-body">
<h6 class="card-title mb-1"><?php echo htmlspecialchars($act['Activity_Name']); ?></h6>
<p class="card-text small text-muted mb-2">
<i class="bi bi-tag"></i> <?php echo htmlspecialchars(ucfirst($act['Activity_Type'])); ?>
&middot; <?php echo date('d M Y', strtotime($act['Activity_Date'])); ?>
</p>
<p class="card-text small mb-2">
<i class="bi bi-wallet2"></i>
<?php echo $act['Activity_Cost'] > 0 ? '₹' . number_format($act['Activity_Cost']) : 'Free'; ?>
&middot;
<i class="bi bi-clock"></i> <?php echo rtrim(rtrim($act['Duration'], '0'), '.'); ?> hrs
</p>
<form method="POST" onsubmit="return confirm('Remove this activity?');">
<input type="hidden" name="itinerary_id" value="<?php echo $act['Itinerary_ID']; ?>">
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

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
