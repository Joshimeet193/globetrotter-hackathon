<?php
/* =========================================================
   my-trips.php
   Shows all trips belonging to the logged-in user as cards.
   Fixed: was querying a "trips" table that doesn't exist.
   Now uses TRIP (Trip_ID, Trip_Name, Start_Date, End_Date,
   Description, Cover_Photo, User_ID) from the real schema.
   Delete now also cleans up child rows (TRIP_STOP, ITINERARY,
   EXPENSE, TRIP_SHARE) so it doesn't fail on foreign keys.
   ========================================================= */
session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    $trip_id = (int) $_POST['trip_id'];

    // Confirm this trip belongs to the logged-in user before touching anything
    $check = $conn->prepare("SELECT Trip_ID FROM TRIP WHERE Trip_ID = ? AND User_ID = ?");
    $check->bind_param("ii", $trip_id, $user_id);
    $check->execute();
    $owns_trip = $check->get_result()->num_rows > 0;
    $check->close();

    if (!$owns_trip) {
        $error_message = 'Invalid trip.';
    } else {
        // Delete ITINERARY rows for every stop of this trip
        $del1 = $conn->prepare("DELETE ITINERARY FROM ITINERARY
                                 INNER JOIN TRIP_STOP ON ITINERARY.Stop_ID = TRIP_STOP.Stop_ID
                                 WHERE TRIP_STOP.Trip_ID = ?");
        $del1->bind_param("i", $trip_id);
        $del1->execute();
        $del1->close();

        // Delete stops, expenses, shares, then the trip itself
        foreach (['TRIP_STOP', 'EXPENSE', 'TRIP_SHARE'] as $table) {
            $del = $conn->prepare("DELETE FROM $table WHERE Trip_ID = ?");
            $del->bind_param("i", $trip_id);
            $del->execute();
            $del->close();
        }

        $stmt = $conn->prepare("DELETE FROM TRIP WHERE Trip_ID = ? AND User_ID = ?");
        $stmt->bind_param("ii", $trip_id, $user_id);
        if ($stmt->execute()) {
            $success_message = 'Trip deleted successfully.';
        } else {
            $error_message = 'Could not delete the trip. Please try again.';
        }
        $stmt->close();
    }
}

$sql = "SELECT Trip_ID, Trip_Name, Start_Date, End_Date, Description, Cover_Photo
        FROM TRIP WHERE User_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ----- Classify each trip as Upcoming / Ongoing / Past -----
$today = date('Y-m-d');
foreach ($trips as $key => $trip) {
    if ($trip['End_Date'] < $today) {
        $trips[$key]['status'] = 'past';
    } elseif ($trip['Start_Date'] <= $today && $trip['End_Date'] >= $today) {
        $trips[$key]['status'] = 'ongoing';
    } else {
        $trips[$key]['status'] = 'upcoming';
    }
}

// ----- Search / filter / sort (GET params, no page it needs to hit DB again for) -----
$search_term   = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? '';
$sort_by       = $_GET['sort'] ?? 'newest';

$trips = array_filter($trips, function ($trip) use ($search_term, $filter_status) {
    $matches_search = $search_term === '' || stripos($trip['Trip_Name'], $search_term) !== false;
    $matches_status = $filter_status === '' || $trip['status'] === $filter_status;
    return $matches_search && $matches_status;
});

usort($trips, function ($a, $b) use ($sort_by) {
    switch ($sort_by) {
        case 'oldest':
            return strcmp($a['Start_Date'], $b['Start_Date']);
        case 'name':
            return strcasecmp($a['Trip_Name'], $b['Trip_Name']);
        case 'newest':
        default:
            return strcmp($b['Start_Date'], $a['Start_Date']);
    }
});

if (isset($_GET['error']) && $_GET['error'] === 'trip_not_found') {
    $error_message = "That trip wasn't found, or you don't have access to it.";
}

$active_page = 'my-trips';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Trips - GlobeTrotter</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container py-5">
<div class="dashboard-hero mb-4">
<div class="row align-items-center">
<div class="col-md-8">
<h1 class="section-title mb-1"><i class="bi bi-suitcase-lg"></i> My Trips</h1>
<p class="text-muted mb-0">All your adventures, planned and past, in one place.</p>
</div>
<div class="col-md-4 text-md-end mt-3 mt-md-0">
<a href="create-trip.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Plan New Trip</a>
</div>
</div>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<!-- ===== SEARCH + FILTER + SORT ===== -->
<form method="GET" class="row g-2 mb-4">
<div class="col-md-5">
<label class="form-label"><i class="bi bi-search"></i> Search Trips</label>
<input type="text" name="search" class="form-control" placeholder="Search by trip name..."
value="<?php echo htmlspecialchars($search_term); ?>">
</div>
<div class="col-md-3">
<label class="form-label">Status</label>
<select name="status" class="form-select">
<option value="">All Trips</option>
<option value="upcoming" <?php echo $filter_status === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
<option value="ongoing" <?php echo $filter_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
<option value="past" <?php echo $filter_status === 'past' ? 'selected' : ''; ?>>Past</option>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Sort By</label>
<select name="sort" class="form-select">
<option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Start Date (Newest)</option>
<option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Start Date (Oldest)</option>
<option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name (A-Z)</option>
</select>
</div>
<div class="col-md-1 d-flex align-items-end">
<button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
</div>
</form>

<div class="row g-4">
<?php if (count($trips) === 0): ?>
<div class="col-12">
<p class="text-muted text-center py-5">
<i class="bi bi-map" style="font-size: 2.5rem;"></i><br>
<?php echo ($search_term !== '' || $filter_status !== '') ? 'No trips match your search or filter.' : 'You haven\'t planned any trips yet. Click "Plan New Trip" to get started!'; ?>
</p>
</div>
<?php else: ?>
<?php foreach ($trips as $trip): ?>
<?php
$status_badge = [
    'upcoming' => ['label' => 'Upcoming', 'class' => 'status-upcoming'],
    'ongoing'  => ['label' => 'Ongoing',  'class' => 'status-ongoing'],
    'past'     => ['label' => 'Past',     'class' => 'status-past'],
][$trip['status']];
?>
<div class="col-md-6 col-lg-4">
<div class="card h-100">
<div class="position-relative">
<img src="<?php echo !empty($trip['Cover_Photo']) ? htmlspecialchars($trip['Cover_Photo']) : 'https://placehold.co/400x300?text=' . urlencode($trip['Trip_Name']); ?>"
     class="card-img-top" alt="<?php echo htmlspecialchars($trip['Trip_Name']); ?>">
<span class="badge-custom <?php echo $status_badge['class']; ?> position-absolute top-0 end-0 m-2">
<?php echo $status_badge['label']; ?>
</span>
</div>
<div class="card-body d-flex flex-column">
<h5 class="card-title"><?php echo htmlspecialchars($trip['Trip_Name']); ?></h5>
<p class="card-text text-muted small mb-2">
<i class="bi bi-calendar3"></i>
<?php echo date('d M Y', strtotime($trip['Start_Date'])); ?>
&mdash;
<?php echo date('d M Y', strtotime($trip['End_Date'])); ?>
</p>
<?php if (!empty($trip['Description'])): ?>
<p class="card-text small mb-3"><?php echo htmlspecialchars($trip['Description']); ?></p>
<?php endif; ?>
<div class="mt-auto d-flex flex-wrap gap-2">
<a href="itinerary-view.php?trip_id=<?php echo $trip['Trip_ID']; ?>" class="btn btn-primary btn-sm">
<i class="bi bi-eye"></i> View
</a>
<a href="itinerary-builder.php?trip_id=<?php echo $trip['Trip_ID']; ?>" class="btn btn-secondary btn-sm">
<i class="bi bi-pencil"></i> Manage
</a>
<form method="POST" class="d-inline" onsubmit="return confirm('Delete this trip? This cannot be undone.');">
<input type="hidden" name="trip_id" value="<?php echo $trip['Trip_ID']; ?>">
<button type="submit" name="delete_trip" class="btn btn-outline-primary btn-sm">
<i class="bi bi-trash"></i> Delete
</button>
</form>
</div>
</div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/script.js"></script>
</body>
</html>
