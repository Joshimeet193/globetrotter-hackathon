<?php
// =====================================================
// dashboard.php
// Logged-in user nu home page - welcome msg, recent trips,
// "Plan New Trip" button, ane popular cities (hardcoded)
// =====================================================

// Step 1: Session start karvi padse har protected page ma
// Jethi apane khabar pade ke user login chhe ke nai
session_start();

// Step 2: Database connection file include karo
include 'includes/db-connect.php';

// Step 3: Login check - agar session ma 'user_id' nathi to login page par mokli do
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit(); // exit() bahu important chhe - header pachi code chalvanu band thai jaay
}

// Step 4: Session mathi current user nu ID lai lo
$user_id = $_SESSION['user_id'];

// Step 5: Database mathi is user nu full_name lavo (prepared statement - SQL injection safe)
$sql_user = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id); // "i" means integer parameter
$stmt->execute();
$result_user = $stmt->get_result();
$user_data = $result_user->fetch_assoc();
$user_name = $user_data['full_name'] ?? 'Traveler';
$stmt->close();

// Step 6: User na last 3 trips lavo (sabse recent trips upar dekhaay)
$sql_trips = "SELECT trip_id, trip_name, start_date, end_date, cover_photo 
              FROM trips 
              WHERE user_id = ? 
              ORDER BY trip_id DESC 
              LIMIT 3";
$stmt = $conn->prepare($sql_trips);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_trips = $stmt->get_result();

$recent_trips = [];
while ($row = $result_trips->fetch_assoc()) {
    $recent_trips[] = $row;
}
$stmt->close();

// Step 6.5: Har trip nu estimated cost calculate karo (budget highlight mate)
// activities.cost ne stops thi trip sathe JOIN karine SUM lai lo
// (Trip level par koi budget "target" nathi database ma, etle just total spend batavi rahya chhe)
$sql_cost = "SELECT COALESCE(SUM(activities.cost), 0) AS total_cost 
             FROM activities 
             JOIN stops ON activities.stop_id = stops.stop_id 
             WHERE stops.trip_id = ?";
$stmt = $conn->prepare($sql_cost);
foreach ($recent_trips as $key => $trip) {
    $stmt->bind_param("i", $trip['trip_id']);
    $stmt->execute();
    $cost_result = $stmt->get_result()->fetch_assoc();
    // Har trip array ma ek naya key 'total_cost' add kari didhu
    $recent_trips[$key]['total_cost'] = $cost_result['total_cost'];
}
$stmt->close();

// Step 7: Popular/recommended cities - HARDCODED array (database ma nathi)
$popular_cities = [
    ["name" => "Goa",      "country" => "India",  "image" => "https://placehold.co/400x250?text=Goa"],
    ["name" => "Manali",   "country" => "India",  "image" => "https://placehold.co/400x250?text=Manali"],
    ["name" => "Jaipur",   "country" => "India",  "image" => "https://placehold.co/400x250?text=Jaipur"],
    ["name" => "Paris",    "country" => "France", "image" => "https://placehold.co/400x250?text=Paris"],
    ["name" => "Dubai",    "country" => "UAE",    "image" => "https://placehold.co/400x250?text=Dubai"],
    ["name" => "Bali",     "country" => "Indonesia", "image" => "https://placehold.co/400x250?text=Bali"],
];
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

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">🌍 GlobeTrotter</a>
        <div class="d-flex">
            <a href="city-search.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-search"></i> City Search</a>
            <a href="activity-search.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-search"></i> Activity Search</a>
            <a href="logout.php" class="btn btn-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</nav>

<div class="container my-4">

    <!-- ===== WELCOME SECTION ===== -->
    <div class="row mb-4">
        <div class="col-12">
            <!-- htmlspecialchars() vaparyu chhe security mate - XSS attack thi bachva -->
            <h2 class="section-title">Welcome back, <?php echo htmlspecialchars($user_name); ?> <i class="bi bi-hand-thumbs-up"></i></h2>
            <p>Ready to plan your next adventure?</p>
            <a href="create-trip.php" class="btn btn-primary"><i class="bi bi-airplane"></i> Plan New Trip</a>
        </div>
    </div>

    <!-- ===== RECENT TRIPS SECTION ===== -->
    <h3 class="section-title">Your Recent Trips</h3>
    <div class="row g-4 mb-5">
        <?php if (count($recent_trips) === 0): ?>
            <!-- Agar koi trip nathi to friendly message batao -->
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
                        // Agar cover_photo database ma set chhe to eno use karo, nahi to default placeholder
                        $photo = !empty($trip['cover_photo']) ? $trip['cover_photo'] : 'https://placehold.co/400x200?text=Trip';
                        ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($trip['trip_name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($trip['trip_name']); ?></h5>
                            <p class="card-text mb-1">
                                <i class="bi bi-calendar3"></i>
                                <?php echo htmlspecialchars($trip['start_date']); ?> to <?php echo htmlspecialchars($trip['end_date']); ?>
                            </p>
                            <!-- Budget highlight - estimated cost so far based on added activities -->
                            <p class="card-text mb-2">
                                <i class="bi bi-wallet2"></i> Estimated cost: ₹<?php echo number_format($trip['total_cost']); ?>
                            </p>
                            <a href="trip-details.php?id=<?php echo $trip['trip_id']; ?>" class="btn btn-outline-primary btn-sm">View Trip</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ===== POPULAR CITIES SECTION (Static Data) ===== -->
    <h3 class="section-title">Popular Destinations</h3>
    <div class="row g-4">
        <?php foreach ($popular_cities as $city): ?>
            <div class="col-md-4 col-lg-2 col-6">
                <div class="card h-100">
                    <img src="<?php echo $city['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($city['name']); ?>">
                    <div class="card-body">
                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($city['name']); ?></h6>
                        <p class="card-text"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($city['country']); ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
</body>
</html>
