<?php
// =====================================================
// dashboard.php
// Logged-in user nu home page - welcome msg, recent trips
// (with budget snapshot), "Plan New Trip" button, popular cities.
// =====================================================

session_start();
include 'includes/db-connect.php';

// Login check
if (!isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['User_ID'];

// -----------------------------------------------------
// STEP 1: User nu naam lavo
// -----------------------------------------------------
$sql_user = "SELECT Name FROM USERS WHERE User_ID = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$user_name = $user_data['Name'] ?? 'Traveler';
$stmt->close();

// -----------------------------------------------------
// STEP 2: User na last 3 trips lavo
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
// STEP 3: Har trip nu "spent so far" calculate karo
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
// STEP 4: Popular cities
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

<nav class="navbar navbar-expand-lg">
<div class="container">
<a class="navbar-brand" href="dashboard.php">🌍 GlobeTrotter</a>
<div class="d-flex">
<a href="city-search.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-search"></i> City Search</a>
<a href="activity-search.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-search"></i> Activity Search</a>
<a href="my-trips.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-suitcase"></i> My Trips</a>
<a href="logout.php" class="btn btn-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>
</div>
</nav>

<div class="container my-4">

<div class="row mb-4">
<div class="col-12">
<h2 class="section-title">Welcome back, <?php echo htmlspecialchars($user_name); ?> <i class="bi bi-hand-thumbs-up"></i></h2>
<p>Ready to plan your next adventure?</p>
<a href="create-trip.php" class="btn btn-primary"><i class="bi bi-airplane"></i> Plan New Trip</a>
</div>
</div>

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
<?php $photo = !empty($trip['Cover_Photo']) ? $trip['Cover_Photo'] : 'https://placehold.co/400x200?text=Trip'; ?>
<img src="<?php echo htmlspecialchars($photo); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($trip['Trip_Name']); ?>">
<div class="card-body">
<h5 class="card-title"><?php echo htmlspecialchars($trip['Trip_Name']); ?></h5>
<p class="card-text mb-1">
<i class="bi bi-calendar3"></i>
<?php echo htmlspecialchars($trip['Start_Date']); ?> to <?php echo htmlspecialchars($trip['End_Date']); ?>
</p>
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

</body>
</html>
