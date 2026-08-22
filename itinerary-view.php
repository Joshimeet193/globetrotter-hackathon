<?php
// itinerary-view.php
// Purpose: Show the full itinerary of a trip - stops grouped with their activities
session_start();
include 'includes/db-connect.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get trip_id from URL, e.g. itinerary-view.php?trip_id=3
if (!isset($_GET['trip_id'])) {
    die("No trip selected.");
}
$trip_id = intval($_GET['trip_id']);

// Fetch trip details (and confirm it belongs to this user)
$tripQuery = $conn->prepare("SELECT * FROM trips WHERE trip_id = ? AND user_id = ?");
$tripQuery->bind_param("ii", $trip_id, $user_id);
$tripQuery->execute();
$tripResult = $tripQuery->get_result();

if ($tripResult->num_rows === 0) {
    die("Trip not found or you don't have access to it.");
}
$trip = $tripResult->fetch_assoc();

// Fetch all stops (cities) for this trip, ordered by start_date
$stopsQuery = $conn->prepare("SELECT * FROM stops WHERE trip_id = ? ORDER BY start_date ASC");
$stopsQuery->bind_param("i", $trip_id);
$stopsQuery->execute();
$stopsResult = $stopsQuery->get_result();

$stops = [];
while ($row = $stopsResult->fetch_assoc()) {
    $stops[] = $row;
}

// For each stop, fetch its activities
foreach ($stops as $index => $stop) {
    $actQuery = $conn->prepare("SELECT * FROM activities WHERE stop_id = ?");
    $actQuery->bind_param("i", $stop['stop_id']);
    $actQuery->execute();
    $actResult = $actQuery->get_result();

    $activities = [];
    while ($a = $actResult->fetch_assoc()) {
        $activities[] = $a;
    }
    $stops[$index]['activities'] = $activities;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Itinerary - <?php echo htmlspecialchars($trip['trip_name']); ?> | GlobeTrotter</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">🌍 GlobeTrotter</a>
        <div class="ms-auto">
            <a href="dashboard.php" class="nav-link d-inline"><i class="bi bi-house"></i> Dashboard</a>
            <a href="my-trips.php" class="nav-link d-inline"><i class="bi bi-suitcase"></i> My Trips</a>
            <a href="logout.php" class="nav-link d-inline"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container py-section">

    <!-- Trip Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-map text-primary-custom"></i> <?php echo htmlspecialchars($trip['trip_name']); ?></h2>
            <p class="text-muted">
                <i class="bi bi-calendar3"></i>
                <?php echo date("d M Y", strtotime($trip['start_date'])); ?>
                &nbsp;→&nbsp;
                <?php echo date("d M Y", strtotime($trip['end_date'])); ?>
            </p>
        </div>
        <div>
            <a href="itinerary-builder.php?trip_id=<?php echo $trip_id; ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil-square"></i> Edit Itinerary
            </a>
            <a href="budget.php?trip_id=<?php echo $trip_id; ?>" class="btn btn-primary">
                <i class="bi bi-wallet2"></i> View Budget
            </a>
        </div>
    </div>

    <?php if ($trip['description']): ?>
        <p class="mb-4"><?php echo htmlspecialchars($trip['description']); ?></p>
    <?php endif; ?>

    <!-- Itinerary Timeline -->
    <h4 class="section-title"><i class="bi bi-signpost-2"></i> Trip Timeline</h4>

    <?php if (count($stops) === 0): ?>
        <div class="budget-alert">
            <i class="bi bi-info-circle"></i> No stops added yet.
            <a href="itinerary-builder.php?trip_id=<?php echo $trip_id; ?>">Add your first stop</a>.
        </div>
    <?php else: ?>

        <?php foreach ($stops as $stop): ?>
            <div class="timeline-day">
                <h5>
                    <i class="bi bi-geo-alt-fill text-primary-custom"></i>
                    <?php echo htmlspecialchars($stop['city_name']); ?>,
                    <?php echo htmlspecialchars($stop['country']); ?>
                </h5>
                <p class="text-muted mb-2">
                    <i class="bi bi-calendar-event"></i>
                    <?php echo date("d M", strtotime($stop['start_date'])); ?>
                    -
                    <?php echo date("d M", strtotime($stop['end_date'])); ?>
                </p>

                <?php if (count($stop['activities']) === 0): ?>
                    <p class="text-muted"><i class="bi bi-dash-circle"></i> No activities added for this stop.</p>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($stop['activities'] as $activity): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <span class="badge-custom">
                                            <?php echo htmlspecialchars($activity['category']); ?>
                                        </span>
                                        <h6 class="card-title mt-2">
                                            <i class="bi bi-stars"></i>
                                            <?php echo htmlspecialchars($activity['activity_name']); ?>
                                        </h6>
                                        <p class="card-text mb-1">
                                            <i class="bi bi-clock"></i> <?php echo htmlspecialchars($activity['duration']); ?>
                                        </p>
                                        <p class="card-text">
                                            <i class="bi bi-cash-coin"></i> ₹<?php echo number_format($activity['cost'], 2); ?>
                                        </p>
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

<footer>
    <p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
