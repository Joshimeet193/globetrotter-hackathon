
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
// (Aa file already banavelu chhe teacher/team lead e - jema $conn variable available hase)
include 'includes/db-connect.php';

// Step 3: Check karo ke user login chhe ke nai
// Jo session ma 'user_id' nathi, to login page par pacho mokli do
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit(); // exit() bahu important chhe - header pachi code chalvanu band thai jaay
}

// Step 4: Session mathi current user nu ID lai lo
$user_id = $_SESSION['user_id'];

// Step 5: Database mathi is user nu full_name lavo
// Prepared statement vaparyu chhe (SQL Injection thi bachva mate - safe coding practice)
$sql_user = "SELECT full_name FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id); // "i" means integer parameter
$stmt->execute();
$result_user = $stmt->get_result();
$user_data = $result_user->fetch_assoc(); // ek row associative array ma malse
$user_name = $user_data['full_name'] ?? 'Traveler'; // agar naam na male to default "Traveler"
$stmt->close();

// Step 6: User na last 3 trips lavo (sabse recent trips upar dekhaay)
// ORDER BY trip_id DESC = sabse nava trips pehla, LIMIT 3 = sirf 3 j lavo
$sql_trips = "SELECT trip_id, trip_name, start_date, end_date, cover_photo 
              FROM trips 
              WHERE user_id = ? 
              ORDER BY trip_id DESC 
              LIMIT 3";
$stmt = $conn->prepare($sql_trips);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_trips = $stmt->get_result();

// Trips ne ek array ma store kari lo, jethi HTML ma loop chalavi shakay
$recent_trips = [];
while ($row = $result_trips->fetch_assoc()) {
    $recent_trips[] = $row;
}
$stmt->close();

// Step 7: Popular/recommended cities - HARDCODED array (database ma nathi)
// Har city nu naam, country, ane image URL rakhyu chhe
$popular_cities = [
    ["name" => "Goa",      "country" => "India",         "image" => "https://placehold.co/400x250/0d6efd/ffffff?text=Goa"],
    ["name" => "Manali",   "country" => "India",         "image" => "https://placehold.co/400x250/198754/ffffff?text=Manali"],
    ["name" => "Jaipur",   "country" => "India",         "image" => "https://placehold.co/400x250/dc3545/ffffff?text=Jaipur"],
    ["name" => "Paris",    "country" => "France",        "image" => "https://placehold.co/400x250/6f42c1/ffffff?text=Paris"],
    ["name" => "Dubai",    "country" => "UAE",           "image" => "https://placehold.co/400x250/fd7e14/ffffff?text=Dubai"],
    ["name" => "Bali",     "country" => "Indonesia",     "image" => "https://placehold.co/400x250/20c997/ffffff?text=Bali"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - GlobeTrotter</title>
    <!-- Bootstrap 5 CSS CDN se le rahya chhe -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Chhoti si custom styling - card hover effect */
        .trip-card:hover, .city-card:hover {
            transform: translateY(-5px);
            transition: 0.3s;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .hero-section {
            background: linear-gradient(135deg, #0d6efd, #6610f2);
            color: white;
            padding: 40px 0;
            border-radius: 0 0 20px 20px;
        }
    </style>
</head>
<body class="bg-light">

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">🌍 GlobeTrotter</a>
        <div class="d-flex">
            <a href="city-search.php" class="btn btn-outline-light btn-sm me-2">City Search</a>
            <a href="activity-search.php" class="btn btn-outline-light btn-sm me-2">Activity Search</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>
</nav>

<!-- ===== WELCOME / HERO SECTION ===== -->
<div class="hero-section text-center">
    <div class="container">
        <!-- htmlspecialchars() vaparyu chhe security mate - XSS attack thi bachva -->
        <h1 class="fw-bold">Welcome back, <?php echo htmlspecialchars($user_name); ?> 👋</h1>
        <p class="lead">Ready to plan your next adventure?</p>
        <!-- Plan New Trip button - create-trip.php par redirect thase -->
        <a href="create-trip.php" class="btn btn-light btn-lg fw-bold mt-2">+ Plan New Trip</a>
    </div>
</div>

<div class="container my-5">

    <!-- ===== RECENT TRIPS SECTION ===== -->
    <h3 class="mb-3">Your Recent Trips</h3>
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
                    <div class="card trip-card h-100 shadow-sm">
                        <?php
                        // Agar cover_photo database ma set chhe to eno use karo,
                        // nahi to ek default placeholder image dekhado
                        $photo = !empty($trip['cover_photo']) ? $trip['cover_photo'] : 'https://placehold.co/400x200?text=Trip';
                        ?>
                        <img src="<?php echo htmlspecialchars($photo); ?>" class="card-img-top" style="height:180px; object-fit:cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($trip['trip_name']); ?></h5>
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars($trip['start_date']); ?> to <?php echo htmlspecialchars($trip['end_date']); ?>
                            </p>
                            <a href="trip-details.php?id=<?php echo $trip['trip_id']; ?>" class="btn btn-outline-primary btn-sm">View Trip</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ===== POPULAR CITIES SECTION (Static Data) ===== -->
    <h3 class="mb-3">Popular Destinations</h3>
    <div class="row g-4">
        <?php foreach ($popular_cities as $city): ?>
            <div class="col-md-4 col-lg-2 col-6">
                <div class="card city-card h-100 shadow-sm">
                    <img src="<?php echo $city['image']; ?>" class="card-img-top" style="height:130px; object-fit:cover;">
                    <div class="card-body p-2 text-center">
                        <h6 class="mb-0"><?php echo htmlspecialchars($city['name']); ?></h6>
                        <small class="text-muted"><?php echo htmlspecialchars($city['country']); ?></small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Bootstrap 5 JS Bundle (needed for dropdowns, modals etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
