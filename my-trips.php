<?php
/* =========================================================
   my-trips.php
   Shows all trips belonging to the logged-in user as cards.
   Each card has View / Edit / Delete buttons.
   ========================================================= */

session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message   = '';

/* ---------------------------------------------------------
   Handle delete request (POST).
   We check user_id in the WHERE clause too, so a user can
   only ever delete their OWN trip.
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_trip'])) {
    $trip_id = (int) $_POST['trip_id'];

    $stmt = $conn->prepare("DELETE FROM trips WHERE trip_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $trip_id, $user_id);

    if ($stmt->execute()) {
        $success_message = 'Trip deleted successfully.';
    } else {
        $error_message = 'Could not delete the trip. Please try again.';
    }
    $stmt->close();
}

/* ---------------------------------------------------------
   Fetch all trips for this user
   --------------------------------------------------------- */
$trips = [];
$stmt = $conn->prepare("SELECT trip_id, trip_name, start_date, end_date, description, cover_photo
                         FROM trips WHERE user_id = ? ORDER BY start_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $trips[] = $row;
}
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
                        <img src="<?php echo !empty($trip['cover_photo']) ? htmlspecialchars($trip['cover_photo']) : 'https://picsum.photos/seed/' . urlencode($trip['trip_name']) . '/400/300'; ?>"
                             class="card-img-top" alt="<?php echo htmlspecialchars($trip['trip_name']); ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($trip['trip_name']); ?></h5>
                            <p class="card-text text-muted small mb-2">
                                <i class="bi bi-calendar3"></i>
                                <?php echo date('d M Y', strtotime($trip['start_date'])); ?>
                                &mdash;
                                <?php echo date('d M Y', strtotime($trip['end_date'])); ?>
                            </p>
                            <?php if (!empty($trip['description'])): ?>
                                <p class="card-text small mb-3"><?php echo htmlspecialchars($trip['description']); ?></p>
                            <?php endif; ?>

                            <div class="mt-auto d-flex flex-wrap gap-2">
                                <a href="itinerary-builder.php?trip_id=<?php echo $trip['trip_id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <a href="edit-trip.php?trip_id=<?php echo $trip['trip_id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this trip? This cannot be undone.');">
                                    <input type="hidden" name="trip_id" value="<?php echo $trip['trip_id']; ?>">
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

