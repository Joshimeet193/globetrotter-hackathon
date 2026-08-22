<?php
// =====================================================
// activity-search.php
// Activities search + filter page.
// =====================================================

session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['User_ID'];

$type_icons = [
    'sightseeing' => 'bi-binoculars',
    'food' => 'bi-cup-hot',
    'adventure' => 'bi-lightning-charge',
];

function type_icon($type) {
    global $type_icons;
    return $type_icons[strtolower($type)] ?? 'bi-stars';
}

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_activity'])) {
    $stop_id = (int) $_POST['stop_id'];
    $activity_id = (int) $_POST['activity_id'];
    $activity_date = $_POST['activity_date'];
    $activity_cost = $_POST['activity_cost'];

    if (empty($stop_id)) {
        $error_message = "Please select a trip stop first!";
    } else {
        $sql_check = "SELECT TRIP_STOP.Stop_ID
                      FROM TRIP_STOP
                      JOIN TRIP ON TRIP_STOP.Trip_ID = TRIP.Trip_ID
                      WHERE TRIP_STOP.Stop_ID = ? AND TRIP.User_ID = ?";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("ii", $stop_id, $user_id);
        $stmt->execute();
        $owns_stop = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$owns_stop) {
            $error_message = "Invalid trip stop selected.";
        } else {
            $sql_insert = "INSERT INTO ITINERARY (Stop_ID, Activity_ID, Activity_Date, Activity_Cost)
                            VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iisd", $stop_id, $activity_id, $activity_date, $activity_cost);
            if ($stmt->execute()) {
                $success_message = "Activity added to your itinerary!";
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
}

$sql_activities = "SELECT ACTIVITY.Activity_ID, ACTIVITY.Activity_Name, ACTIVITY.Activity_Type,
                          ACTIVITY.Description, ACTIVITY.Duration, ACTIVITY.Estimated_Cost, ACTIVITY.Image,
                          CITY.City_ID, CITY.City_Name
                   FROM ACTIVITY
                   JOIN CITY ON ACTIVITY.City_ID = CITY.City_ID
                   ORDER BY ACTIVITY.Activity_Name";
$all_activities = $conn->query($sql_activities)->fetch_all(MYSQLI_ASSOC);

$sql_stops = "SELECT TRIP_STOP.Stop_ID, TRIP_STOP.City_ID, TRIP.Trip_Name, CITY.City_Name
              FROM TRIP_STOP
              JOIN TRIP ON TRIP_STOP.Trip_ID = TRIP.Trip_ID
              JOIN CITY ON TRIP_STOP.City_ID = CITY.City_ID
              WHERE TRIP.User_ID = ?
              ORDER BY TRIP_STOP.Stop_ID DESC";
$stmt = $conn->prepare($sql_stops);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_stops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$filter_category = $_GET['category'] ?? '';
$min_cost = isset($_GET['min_cost']) && $_GET['min_cost'] !== '' ? (float) $_GET['min_cost'] : null;
$max_cost = isset($_GET['max_cost']) && $_GET['max_cost'] !== '' ? (float) $_GET['max_cost'] : null;
$filter_duration = $_GET['duration'] ?? '';

$filtered_activities = array_filter($all_activities, function ($activity) use ($filter_category, $min_cost, $max_cost, $filter_duration) {
    $matches_category = empty($filter_category) || strtolower($activity['Activity_Type']) === strtolower($filter_category);
    $matches_min = is_null($min_cost) || $activity['Estimated_Cost'] >= $min_cost;
    $matches_max = is_null($max_cost) || $activity['Estimated_Cost'] <= $max_cost;
    $matches_duration = empty($filter_duration) || (float) $activity['Duration'] == (float) $filter_duration;
    return $matches_category && $matches_min && $matches_max && $matches_duration;
});

$all_types = array_unique(array_column($all_activities, 'Activity_Type'));
sort($all_types);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Search - GlobeTrotter</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<?php $active_page = 'activity-search'; include 'includes/navbar.php'; ?>

<div class="container my-4">
<div class="dashboard-hero mb-4">
<h2 class="section-title mb-1"><i class="bi bi-compass"></i> Search Activities</h2>
<p class="text-muted mb-0">Fill your itinerary with things to do at each stop.</p>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-4">
<div class="col-md-3">
<label class="form-label">Category</label>
<select name="category" class="form-select">
<option value="">All Categories</option>
<?php foreach ($all_types as $type): ?>
<option value="<?php echo htmlspecialchars($type); ?>" <?php echo (strtolower($filter_category) === strtolower($type)) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars(ucfirst($type)); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<label class="form-label">Min Cost (₹)</label>
<input type="number" name="min_cost" class="form-control" value="<?php echo htmlspecialchars($min_cost ?? ''); ?>" placeholder="0">
</div>
<div class="col-md-2">
<label class="form-label">Max Cost (₹)</label>
<input type="number" name="max_cost" class="form-control" value="<?php echo htmlspecialchars($max_cost ?? ''); ?>" placeholder="5000">
</div>
<div class="col-md-3">
<label class="form-label">Duration (hours)</label>
<select name="duration" class="form-select">
<option value="">Any Duration</option>
<option value="1" <?php echo $filter_duration == '1' ? 'selected' : ''; ?>>1 hour</option>
<option value="2" <?php echo $filter_duration == '2' ? 'selected' : ''; ?>>2 hours</option>
<option value="3" <?php echo $filter_duration == '3' ? 'selected' : ''; ?>>3 hours</option>
<option value="4" <?php echo $filter_duration == '4' ? 'selected' : ''; ?>>4 hours</option>
</select>
</div>
<div class="col-md-2 d-flex align-items-end">
<button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Apply</button>
</div>
</form>

<div class="row g-4">
<?php if (count($filtered_activities) === 0): ?>
<div class="col-12">
<div class="alert alert-warning">No activities match your filters. Try adjusting them.</div>
</div>
<?php else: ?>
<?php foreach ($filtered_activities as $activity): ?>
<?php
$matching_stops = array_filter($user_stops, function ($stop) use ($activity) {
    return $stop['City_ID'] == $activity['City_ID'];
});
?>
<div class="col-md-4 col-lg-3">
<div class="card h-100">
<?php if (!empty($activity['Image'])): ?>
<img src="<?php echo htmlspecialchars($activity['Image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($activity['Activity_Name']); ?>">
<?php endif; ?>
<div class="card-body">
<span class="badge bg-secondary mb-2">
<i class="bi <?php echo type_icon($activity['Activity_Type']); ?>"></i>
<?php echo htmlspecialchars(ucfirst($activity['Activity_Type'])); ?>
</span>
<h5 class="card-title"><?php echo htmlspecialchars($activity['Activity_Name']); ?></h5>
<p class="card-text mb-1"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($activity['City_Name']); ?></p>
<p class="card-text mb-1"><i class="bi bi-wallet2"></i> ₹<?php echo number_format($activity['Estimated_Cost']); ?></p>
<p class="card-text mb-3"><i class="bi bi-clock"></i> <?php echo rtrim(rtrim($activity['Duration'], '0'), '.'); ?> hr(s)</p>
<div class="d-flex gap-2">
<button type="button" class="btn btn-outline-primary btn-sm flex-fill"
data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $activity['Activity_ID']; ?>">
<i class="bi bi-eye"></i> View
</button>
<?php if (count($matching_stops) > 0): ?>
<button type="button" class="btn btn-primary btn-sm flex-fill"
data-bs-toggle="modal" data-bs-target="#addModal<?php echo $activity['Activity_ID']; ?>">
<i class="bi bi-plus-circle"></i> Add
</button>
<?php endif; ?>
</div>
<?php if (count($matching_stops) === 0): ?>
<small class="d-block mt-2"><i class="bi bi-info-circle"></i> Add <?php echo htmlspecialchars($activity['City_Name']); ?> to a trip first.</small>
<?php endif; ?>
</div>
</div>
</div>

<div class="modal fade" id="viewModal<?php echo $activity['Activity_ID']; ?>" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title"><?php echo htmlspecialchars($activity['Activity_Name']); ?></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<?php if (!empty($activity['Image'])): ?>
<img src="<?php echo htmlspecialchars($activity['Image']); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($activity['Activity_Name']); ?>">
<?php endif; ?>
<p><?php echo htmlspecialchars($activity['Description']); ?></p>
<hr>
<p class="mb-1"><i class="bi <?php echo type_icon($activity['Activity_Type']); ?>"></i> <strong>Category:</strong> <?php echo htmlspecialchars(ucfirst($activity['Activity_Type'])); ?></p>
<p class="mb-1"><i class="bi bi-geo-alt"></i> <strong>City:</strong> <?php echo htmlspecialchars($activity['City_Name']); ?></p>
<p class="mb-1"><i class="bi bi-wallet2"></i> <strong>Cost:</strong> ₹<?php echo number_format($activity['Estimated_Cost']); ?></p>
<p class="mb-0"><i class="bi bi-clock"></i> <strong>Duration:</strong> <?php echo rtrim(rtrim($activity['Duration'], '0'), '.'); ?> hour(s)</p>
</div>
</div>
</div>
</div>

<div class="modal fade" id="addModal<?php echo $activity['Activity_ID']; ?>" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">
<div class="modal-header">
<h5 class="modal-title">Add "<?php echo htmlspecialchars($activity['Activity_Name']); ?>" to Trip</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<input type="hidden" name="activity_id" value="<?php echo $activity['Activity_ID']; ?>">
<input type="hidden" name="activity_cost" value="<?php echo $activity['Estimated_Cost']; ?>">
<div class="mb-3">
<label class="form-label">Select Trip Stop (in <?php echo htmlspecialchars($activity['City_Name']); ?>)</label>
<select name="stop_id" class="form-select" required>
<option value="">-- Choose a stop --</option>
<?php foreach ($matching_stops as $stop): ?>
<option value="<?php echo $stop['Stop_ID']; ?>">
<?php echo htmlspecialchars($stop['Trip_Name']); ?> - <?php echo htmlspecialchars($stop['City_Name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-calendar3"></i> Activity Date</label>
<input type="date" name="activity_date" class="form-control" required>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" name="add_activity" class="btn btn-primary">Add Activity</button>
</div>
</form>
</div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
<script src="js/script.js"></script>
</body>
</html>
