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
        FROM TRIP WHERE User_ID = ? ORDER BY Start_Date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
<div class="d-flex justify-content-between align-items-center mb-4">
<h1 class="section-title mb-0"><i class="bi bi-suitcase-lg"></i> My Trips</h1>
<a href="create-trip.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Plan New Trip</a>
</div>

<?php if ($success_message): ?>
<div class="alert alert-success"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<div class="row g-4">
<?php if (count($trips) === 0): ?>
<div class="col-12">
<p class="text-muted text-center py-5">
<i class="bi bi-map" style="font-size: 2.5rem;"></i><br>
You haven't planned any trips yet. Click "Plan New Trip" to get started!
</p>
</div>
<?php else: ?>
<?php foreach ($trips as $trip): ?>
<div class="col-md-6 col-lg-4">
<div class="card h-100">
<img src="<?php echo !empty($trip['Cover_Photo']) ? htmlspecialchars($trip['Cover_Photo']) : 'https://placehold.co/400x300?text=' . urlencode($trip['Trip_Name']); ?>"
     class="card-img-top" alt="<?php echo htmlspecialchars($trip['Trip_Name']); ?>">
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
</body>
</html>
