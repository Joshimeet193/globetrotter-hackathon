<?php
// =====================================================
// dashboard.php
// Logged-in user nu home page - welcome msg, recent trips
// (with budget snapshot), "Plan New Trip" button, popular cities.
// =====================================================

session_start();
include 'includes/db-connect.php';


// =====================================================
// CHECK LOGIN
// =====================================================

if (!isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit();
}

$user_id = (int) $_SESSION['User_ID'];


// =====================================================
// SMALL HELPER - avoid repeating htmlspecialchars() everywhere
// =====================================================

function e($str) {
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}


// =====================================================
// GET LOGGED-IN USER
// =====================================================

$sql_user = "SELECT Name
             FROM USERS
             WHERE User_ID = ?";

$stmt = $conn->prepare($sql_user);

$user_name = 'Traveler';

if ($stmt !== false) {

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {

        $user_data = $stmt->get_result()->fetch_assoc();

        if ($user_data) {
            $user_name = $user_data['Name'] ?? 'Traveler';
        }
    }

    $stmt->close();
}


// =====================================================
// GET RECENT TRIPS
// =====================================================

$sql_trips = "SELECT Trip_ID,
                     Trip_Name,
                     Start_Date,
                     End_Date,
                     Cover_Photo,
                     COALESCE(Budget, 0) AS Budget
              FROM TRIP
              WHERE User_ID = ?
              ORDER BY Trip_ID DESC
              LIMIT 3";

$stmt = $conn->prepare($sql_trips);

$recent_trips = [];

if ($stmt !== false) {

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {

        $recent_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    $stmt->close();
}


// =====================================================
// CALCULATE ACTUAL SPENDING FOR RECENT TRIPS
// IMPORTANT:
// Actual spending comes from EXPENSE.Amount.
// ITINERARY.Activity_Cost is planned/estimated activity
// cost and should NOT be counted as actual spending.
//
// NOTE: this used to run one EXPENSE query per trip inside
// the loop (N+1). Since we already know all the Trip_IDs
// from $recent_trips, we fetch every trip's spend in a
// single grouped query instead.
// =====================================================

$spent_map = [];

if (count($recent_trips) > 0) {

    $trip_ids = array_map('intval', array_column($recent_trips, 'Trip_ID'));
    $placeholders = implode(',', array_fill(0, count($trip_ids), '?'));
    $types = str_repeat('i', count($trip_ids));

    $sql_spent = "SELECT Trip_ID, COALESCE(SUM(Amount), 0) AS spent
                  FROM EXPENSE
                  WHERE Trip_ID IN ($placeholders)
                  GROUP BY Trip_ID";

    $stmt = $conn->prepare($sql_spent);

    if ($stmt !== false) {

        $stmt->bind_param($types, ...$trip_ids);

        if ($stmt->execute()) {

            $spent_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($spent_rows as $row) {
                $spent_map[(int) $row['Trip_ID']] = (float) $row['spent'];
            }
        }

        $stmt->close();
    }
}

$today = date('Y-m-d');

foreach ($recent_trips as $key => $trip) {

    $trip_id = (int) $trip['Trip_ID'];
    $spent = $spent_map[$trip_id] ?? 0.0;
    $budget = (float) ($trip['Budget'] ?? 0);


    // Store actual spending
    $recent_trips[$key]['spent'] = $spent;


    // Calculate percentage of budget used
    $recent_trips[$key]['percent_used'] =
        $budget > 0
            ? min(100, round(($spent / $budget) * 100))
            : 0;


    // Check whether actual spending exceeds budget
    $recent_trips[$key]['is_over_budget'] =
        $budget > 0 && $spent > $budget;


    // =================================================
    // TRIP STATUS
    // Guard against missing/invalid dates so a bad row
    // doesn't throw a warning or mis-sort the trip.
    // =================================================

    $start = $trip['Start_Date'] ?? null;
    $end = $trip['End_Date'] ?? null;

    if (!$start || !$end) {

        $recent_trips[$key]['status'] = 'upcoming';

    } elseif ($end < $today) {

        $recent_trips[$key]['status'] = 'past';

    } elseif ($start <= $today && $end >= $today) {

        $recent_trips[$key]['status'] = 'ongoing';

    } else {

        $recent_trips[$key]['status'] = 'upcoming';
    }
}


// =====================================================
// QUICK STATS
// =====================================================

$stats = [
    'trip_count' => 0,
    'total_budget' => 0
];

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS trip_count,
        COALESCE(SUM(Budget), 0) AS total_budget
     FROM TRIP
     WHERE User_ID = ?"
);

if ($stmt !== false) {

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {

        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            $stats['trip_count'] = (int) ($result['trip_count'] ?? 0);
            $stats['total_budget'] = (float) ($result['total_budget'] ?? 0);
        }
    }

    $stmt->close();
}


// =====================================================
// COUNT UNIQUE CITIES ADDED TO USER'S TRIPS
// =====================================================

$city_stat = [
    'city_count' => 0
];

$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT TRIP_STOP.City_ID) AS city_count
     FROM TRIP_STOP
     JOIN TRIP
       ON TRIP_STOP.Trip_ID = TRIP.Trip_ID
     WHERE TRIP.User_ID = ?"
);

if ($stmt !== false) {

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {

        $result = $stmt->get_result()->fetch_assoc();

        if ($result) {
            $city_stat['city_count'] =
                (int) ($result['city_count'] ?? 0);
        }
    }

    $stmt->close();
}


// =====================================================
// POPULAR CITIES
// =====================================================

$sql_popular = "SELECT CITY.City_ID,
                       CITY.City_Name,
                       CITY.Image,
                       COUNTRY.Country_Name
                FROM CITY
                JOIN COUNTRY
                  ON CITY.Country_ID = COUNTRY.Country_ID
                ORDER BY CITY.Popularity DESC
                LIMIT 6";

$popular_cities = [];

$result = $conn->query($sql_popular);

if ($result !== false) {

    $popular_cities = $result->fetch_all(MYSQLI_ASSOC);
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard - GlobeTrotter</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link rel="stylesheet" href="css/style.css">

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<style>

/* status chip on recent trip cards */
.trip-status-chip {
  position: absolute; top: 12px; right: 12px;
  font-family: 'Space Mono', monospace;
  font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.06em;
  color: #fff; padding: 4px 10px; border-radius: 20px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}


/* dashed divider echoing a ticket stub inside each trip card */
.trip-card-divider {
  border-top: 2px dashed var(--line);
  margin: 10px 0 12px;
}

</style>

</head>


<body>


<?php
$active_page = 'dashboard';
include 'includes/navbar.php';
?>


<div class="container my-4">


<!-- ===== WELCOME SECTION ===== -->

<div class="dashboard-hero mb-4">

<div class="row align-items-center">

<div class="col-md-8">

<p
    class="mb-1"
    style="font-family:'Space Mono',monospace; font-size:0.75rem; letter-spacing:0.1em; text-transform:uppercase; color: var(--teal);"
>
    Passenger
</p>

<h2 class="section-title mb-1">
    Welcome back,
    <?php echo e($user_name); ?>
    <i class="bi bi-hand-thumbs-up"></i>
</h2>

<p class="text-muted mb-3">
    Ready to plan your next adventure?
</p>

<a href="create-trip.php" class="btn btn-primary">
    <i class="bi bi-airplane"></i>
    Plan New Trip
</a>

</div>


<div class="col-md-4 text-md-end mt-3 mt-md-0">

<i class="bi bi-globe-americas dashboard-hero-icon"></i>

</div>

</div>

</div>


<!-- ===== QUICK STATS ===== -->

<div class="row g-3 mb-5">


<div class="col-6 col-md-3">

<div class="card stat-card h-100 text-center">

<div class="card-body">

<i class="bi bi-suitcase-lg icon-circle mx-auto"></i>

<h3 class="mb-0">
    <?php echo (int) $stats['trip_count']; ?>
</h3>

<p class="text-muted small mb-0">
    Trips Planned
</p>

</div>

</div>

</div>


<div class="col-6 col-md-3">

<div class="card stat-card h-100 text-center">

<div class="card-body">

<i class="bi bi-geo-alt icon-circle mx-auto"></i>

<h3 class="mb-0">
    <?php echo (int) $city_stat['city_count']; ?>
</h3>

<p class="text-muted small mb-0">
    Cities Added
</p>

</div>

</div>

</div>


<div class="col-6 col-md-3">

<div class="card stat-card h-100 text-center">

<div class="card-body">

<i class="bi bi-wallet2 icon-circle mx-auto"></i>

<h3 class="mb-0">
    ₹<?php echo number_format($stats['total_budget']); ?>
</h3>

<p class="text-muted small mb-0">
    Total Budget
</p>

</div>

</div>

</div>


<div class="col-6 col-md-3">

<div class="card stat-card h-100 text-center">

<div class="card-body">

<i class="bi bi-compass icon-circle mx-auto"></i>

<h3 class="mb-0">
    <?php echo count($popular_cities); ?>+
</h3>

<p class="text-muted small mb-0">
    Places to Explore
</p>

</div>

</div>

</div>


</div>


<!-- ===== RECENT TRIPS SECTION ===== -->

<h3 class="section-title">
    Your Recent Trips
</h3>


<div class="row g-4 mb-5">


<?php if (count($recent_trips) === 0): ?>

<div class="col-12">

<div class="alert alert-info">

<i class="bi bi-info-circle"></i>

You haven't planned any trips yet.
Click "Plan New Trip" to get started!

</div>

</div>


<?php else: ?>


<?php foreach ($recent_trips as $trip): ?>

<div class="col-md-4">

<div class="card h-100" style="position:relative;">


<?php

$status = $trip['status'];

$chip_bg =
    $status === 'ongoing'
        ? 'var(--stamp)'
        : (
            $status === 'past'
                ? 'var(--ink-soft)'
                : 'var(--teal)'
        );

?>

<span
    class="trip-status-chip"
    style="background-color: <?php echo $chip_bg; ?>;"
>
    <?php echo ucfirst($status); ?>
</span>


<?php

$photo = !empty($trip['Cover_Photo'])
    ? $trip['Cover_Photo']
    : 'https://placehold.co/400x200?text=Trip';

?>

<img
    src="<?php echo e($photo); ?>"
    class="card-img-top"
    alt="<?php echo e($trip['Trip_Name']); ?>"
    loading="lazy"
>


<div class="card-body">


<h5 class="card-title">
    <?php echo e($trip['Trip_Name']); ?>
</h5>


<p class="card-text mb-0">

<i class="bi bi-calendar3"></i>

<?php

if (!empty($trip['Start_Date']) && !empty($trip['End_Date'])) {
    echo date('d M', strtotime($trip['Start_Date']));
    echo ' &mdash; ';
    echo date('d M Y', strtotime($trip['End_Date']));
} else {
    echo 'Dates not set';
}

?>

</p>


<div class="trip-card-divider"></div>


<?php if ($trip['Budget'] > 0): ?>


<p class="card-text mb-1">

<i class="bi bi-wallet2"></i>

₹<?php echo number_format($trip['spent']); ?>

/

₹<?php echo number_format($trip['Budget']); ?>

spent

</p>


<div class="progress mb-2" style="height: 8px;">

<div
    class="progress-bar <?php echo $trip['is_over_budget'] ? 'bg-over-budget' : ''; ?>"
    role="progressbar"
    style="width: <?php echo $trip['percent_used']; ?>%;"
    aria-valuenow="<?php echo $trip['percent_used']; ?>"
    aria-valuemin="0"
    aria-valuemax="100"
>
</div>

</div>


<?php if ($trip['is_over_budget']): ?>

<small class="text-danger d-block mb-2">

<i class="bi bi-exclamation-triangle"></i>

Over budget!

</small>

<?php endif; ?>


<?php else: ?>


<p class="card-text mb-2">

<i class="bi bi-wallet2"></i>

No budget set for this trip

</p>


<?php endif; ?>


<a
    href="itinerary-view.php?trip_id=<?php echo (int) $trip['Trip_ID']; ?>"
    class="btn btn-outline-primary btn-sm"
>
    View Trip
</a>


</div>

</div>

</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


<!-- ===== POPULAR CITIES SECTION ===== -->

<h3 class="section-title">
    Popular Destinations
</h3>


<div class="row g-4">


<?php if (count($popular_cities) === 0): ?>


<div class="col-12">

<div class="alert alert-info">
    No cities in the database yet.
</div>

</div>


<?php else: ?>


<?php foreach ($popular_cities as $city): ?>


<div class="col-md-4 col-lg-2 col-6">

<div class="card h-100">


<?php

$city_image = !empty($city['Image'])
    ? $city['Image']
    : 'https://placehold.co/300x180?text=' . urlencode($city['City_Name']);

?>


<img
    src="<?php echo e($city_image); ?>"
    class="card-img-top"
    alt="<?php echo e($city['City_Name']); ?>"
    loading="lazy"
>


<div class="card-body">

<h6 class="card-title mb-1">
    <?php echo e($city['City_Name']); ?>
</h6>


<p class="card-text">

<i class="bi bi-geo-alt"></i>

<?php echo e($city['Country_Name']); ?>

</p>

</div>

</div>

</div>


<?php endforeach; ?>


<?php endif; ?>


</div>


</div>


<footer>

<p>
    Made with <span>❤️</span> for GlobeTrotter Hackathon
</p>

</footer>


<script src="js/script.js"></script>

</body>

</html>
