<?php
// =====================================================
// dashboard.php
// Logged-in user nu home page - welcome msg, recent trips
// (with budget snapshot), "Plan New Trip" button, popular cities.
// Aa file HAVE REAL DATABASE TABLES vapare chhe (CITY, TRIP, ITINERARY)
// kem ke actual schema ma aa tables already banela chhe.
// =====================================================

session_start();
include 'includes/db-connect.php';

// Login check - session ma User_ID nathi to login page mokli do
if (!isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['User_ID'];

// -----------------------------------------------------
// STEP 1: User nu naam lavo (table column nu naam "Name" chhe, "full_name" nahi)
// -----------------------------------------------------
$sql_user = "SELECT Name FROM USERS WHERE User_ID = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_name = $user_data['Name'] ?? 'Traveler';
$stmt->close();

// -----------------------------------------------------
// STEP 2: User na last 3 trips lavo (TRIP table mathi, Budget field sathe)
// -----------------------------------------------------
$sql_trips = "SELECT Trip_ID, Trip_Name, Start_Date, End_Date, Cover_Photo, Budget
              FROM TRIP
              WHERE User_ID = ?
              ORDER BY Trip_ID DESC
              LIMIT 3";
$stmt = $conn->prepare($sql_trips);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------
// STEP 3: Har trip nu "spent so far" calculate karo (Budget highlight mate)
// -----------------------------------------------------
$sql_spent = "SELECT COALESCE(SUM(ITINERARY.Activity_Cost), 0) AS spent
              FROM ITINERARY
              JOIN TRIP_STOP ON ITINERARY.Stop_ID = TRIP_STOP.Stop_ID
              WHERE TRIP_STOP.Trip_ID = ?";
$stmt = $conn->prepare($sql_spent);
foreach ($recent_trips as $key => $trip) {
    $stmt->bind_param("i", $trip['Trip_ID']);
    $stmt->execute();
    $spent_row = $stmt->get_result()->fetch_assoc();
    $spent = (float) $spent_row['spent'];
    $budget = (float) $trip['Budget'];
    $recent_trips[$key]['spent'] = $spent;
    $recent_trips[$key]['percent_used'] = $budget > 0 ? min(100, round(($spent / $budget) * 100)) : 0;
    $recent_trips[$key]['is_over_budget'] = $budget > 0 && $spent > $budget;
}
$stmt->close();

// -----------------------------------------------------
// STEP 3.5: Quick stats - total trips, cities covered, total budget
// -----------------------------------------------------
$stmt = $conn->prepare("SELECT COUNT(*) AS trip_count, COALESCE(SUM(Budget), 0) AS total_budget FROM TRIP WHERE User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(DISTINCT TRIP_STOP.City_ID) AS city_count
                         FROM TRIP_STOP
                         JOIN TRIP ON TRIP_STOP.Trip_ID = TRIP.Trip_ID
                         WHERE TRIP.User_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$city_stat = $stmt->get_result()->fetch_assoc();
$stmt->close();

// -----------------------------------------------------
// STEP 4: Popular cities - sabse popular 6 cities lavo
// -----------------------------------------------------
$sql_popular = "SELECT CITY.City_ID, CITY.City_Name, CITY.Image, COUNTRY.Country_Name
                FROM CITY
                JOIN COUNTRY ON CITY.Country_ID = COUNTRY.Country_ID
                ORDER BY CITY.Popularity DESC
                LIMIT 6";
$popular_cities = $conn->query($sql_popular)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - GlobeTrotter</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Inter&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

<?php $active_page = 'dashboard'; include 'includes/navbar.php'; ?>

<div class="container my-4">

<!-- ===== WELCOME SECTION ===== -->
<div class="dashboard-hero mb-4">
<div class="row align-items-center">
<div class="col-md-8">
<h2 class="section-title mb-1">Welcome back, <?php echo htmlspecialchars($user_name); ?> <i class="bi bi-hand-thumbs-up"></i></h2>
<p class="text-muted mb-3">Ready to plan your next adventure?</p>
<a href="create-trip.php" class="btn btn-primary"><i class="bi bi-airplane"></i> Plan New Trip</a>
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
<h3 class="mb-0"><?php echo (int) $stats['trip_count']; ?></h3>
<p class="text-muted small mb-0">Trips Planned</p>
</div>
</div>
</div>
<div class="col-6 col-md-3">
<div class="card stat-card h-100 text-center">
<div class="card-body">
<i class="bi bi-geo-alt icon-circle mx-auto"></i>
<h3 class="mb-0"><?php echo (int) $city_stat['city_count']; ?></h3>
<p class="text-muted small mb-0">Cities Added</p>
</div>
</div>
</div>
<div class="col-6 col-md-3">
<div class="card stat-card h-100 text-center">
<div class="card-body">
<i class="bi bi-wallet2 icon-circle mx-auto"></i>
<h3 class="mb-0">₹<?php echo number_format($stats['total_budget']); ?></h3>
<p class="text-muted small mb-0">Total Budget</p>
</div>
</div>
</div>
<div class="col-6 col-md-3">
<div class="card stat-card h-100 text-center">
<div class="card-body">
<i class="bi bi-compass icon-circle mx-auto"></i>
<h3 class="mb-0"><?php echo count($popular_cities); ?>+</h3>
<p class="text-muted small mb-0">Places to Explore</p>
</div>
</div>
</div>
</div>

<!-- ===== RECENT TRIPS SECTION ===== -->
<h3 class="section-title">Your Recent Trips</h3>
<div class="row g-4 mb-5">
<?php if (count($recent_trips) === 0): ?>
<div class="col-12">
<div class="alert alert-info">
You haven't planned any trips yet. Click "Plan New Trip" to get started!
</div>
</div>
<?php else: ?>
<?php foreach ($recent_trips as $trip): ?>
<div class="col-md-4">
<div class="card h-100">
<?php
$photo = !empty($trip['Cover_Photo']) ? $trip['Cover_Photo'] : 'https://placehold.co/400x200?text=Trip';
?>
<img src="<?php echo htmlspecialchars($photo); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($trip['Trip_Name']); ?>">
<div class="card-body">
<h5 class="card-title"><?php echo htmlspecialchars($trip['Trip_Name']); ?></h5>
<p class="card-text mb-1">
<i class="bi bi-calendar3"></i>
<?php echo htmlspecialchars($trip['Start_Date']); ?> to <?php echo htmlspecialchars($trip['End_Date']); ?>
</p>

<!-- ===== BUDGET HIGHLIGHT ===== -->
<?php if ($trip['Budget'] > 0): ?>
<p class="card-text mb-1">
<i class="bi bi-wallet2"></i>
₹<?php echo number_format($trip['spent']); ?> / ₹<?php echo number_format($trip['Budget']); ?> spent
</p>
<div class="progress mb-2" style="height: 8px;">
<div class="progress-bar <?php echo $trip['is_over_budget'] ? 'bg-over-budget' : ''; ?>"
role="progressbar"
style="width: <?php echo $trip['percent_used']; ?>%;"
aria-valuenow="<?php echo $trip['percent_used']; ?>" aria-valuemin="0" aria-valuemax="100">
</div>
</div>
<?php if ($trip['is_over_budget']): ?>
<small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Over budget!</small>
<?php endif; ?>
<?php else: ?>
<p class="card-text mb-2"><i class="bi bi-wallet2"></i> No budget set for this trip</p>
<?php endif; ?>

<a href="itinerary-view.php?trip_id=<?php echo $trip['Trip_ID']; ?>" class="btn btn-outline-primary btn-sm">View Trip</a>
</div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

<!-- ===== POPULAR CITIES SECTION ===== -->
<h3 class="section-title">Popular Destinations</h3>
<div class="row g-4">
<?php if (count($popular_cities) === 0): ?>
<div class="col-12">
<div class="alert alert-info">No cities in the database yet.</div>
</div>
<?php else: ?>
<?php foreach ($popular_cities as $city): ?>
<div class="col-md-4 col-lg-2 col-6">
<div class="card h-100">
<?php $city_image = !empty($city['Image']) ? $city['Image'] : 'https://placehold.co/300x180?text=' . urlencode($city['City_Name']); ?>
<img src="<?php echo htmlspecialchars($city_image); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($city['City_Name']); ?>">
<div class="card-body">
<h6 class="card-title mb-1"><?php echo htmlspecialchars($city['City_Name']); ?></h6>
<p class="card-text"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($city['Country_Name']); ?></p>
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
