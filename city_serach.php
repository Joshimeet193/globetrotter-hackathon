<?php
// =====================================================
// city-search.php
// Cities search + filter page. Cities static array ma
// hardcoded chhe (database table nathi banavvanu).
// "Add to Trip" click karta stops table ma row insert thay chhe.
// =====================================================

session_start();
include 'includes/db-connect.php';

// Login check - agar user login nathi to login page mokli do
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// -----------------------------------------------------
// STEP A: Static cities data (hardcoded array)
// Har city ma: name, country, region, cost_index, image
// -----------------------------------------------------
$all_cities = [
    ["name" => "Goa",          "country" => "India",        "region" => "Asia",   "cost_index" => "Medium", "image" => "https://placehold.co/300x180/0d6efd/fff?text=Goa"],
    ["name" => "Manali",       "country" => "India",        "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180/198754/fff?text=Manali"],
    ["name" => "Jaipur",       "country" => "India",        "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180/dc3545/fff?text=Jaipur"],
    ["name" => "Udaipur",      "country" => "India",        "region" => "Asia",   "cost_index" => "Medium", "image" => "https://placehold.co/300x180/6610f2/fff?text=Udaipur"],
    ["name" => "Kerala",       "country" => "India",        "region" => "Asia",   "cost_index" => "Medium", "image" => "https://placehold.co/300x180/20c997/fff?text=Kerala"],
    ["name" => "Paris",        "country" => "France",       "region" => "Europe", "cost_index" => "High",   "image" => "https://placehold.co/300x180/6f42c1/fff?text=Paris"],
    ["name" => "Rome",         "country" => "Italy",        "region" => "Europe", "cost_index" => "High",   "image" => "https://placehold.co/300x180/fd7e14/fff?text=Rome"],
    ["name" => "Barcelona",    "country" => "Spain",        "region" => "Europe", "cost_index" => "Medium", "image" => "https://placehold.co/300x180/0dcaf0/fff?text=Barcelona"],
    ["name" => "London",       "country" => "UK",           "region" => "Europe", "cost_index" => "High",   "image" => "https://placehold.co/300x180/343a40/fff?text=London"],
    ["name" => "Dubai",        "country" => "UAE",          "region" => "Middle East", "cost_index" => "High", "image" => "https://placehold.co/300x180/ffc107/000?text=Dubai"],
    ["name" => "Bali",         "country" => "Indonesia",    "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180/198754/fff?text=Bali"],
    ["name" => "Bangkok",      "country" => "Thailand",     "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180/dc3545/fff?text=Bangkok"],
    ["name" => "Singapore",    "country" => "Singapore",    "region" => "Asia",   "cost_index" => "High",   "image" => "https://placehold.co/300x180/0d6efd/fff?text=Singapore"],
    ["name" => "New York",     "country" => "USA",          "region" => "North America", "cost_index" => "High", "image" => "https://placehold.co/300x180/6f42c1/fff?text=New+York"],
    ["name" => "Los Angeles",  "country" => "USA",          "region" => "North America", "cost_index" => "High", "image" => "https://placehold.co/300x180/fd7e14/fff?text=LA"],
    ["name" => "Sydney",       "country" => "Australia",    "region" => "Oceania", "cost_index" => "High",   "image" => "https://placehold.co/300x180/20c997/fff?text=Sydney"],
    ["name" => "Cape Town",    "country" => "South Africa", "region" => "Africa",  "cost_index" => "Medium", "image" => "https://placehold.co/300x180/6610f2/fff?text=Cape+Town"],
    ["name" => "Cairo",        "country" => "Egypt",        "region" => "Africa",  "cost_index" => "Low",    "image" => "https://placehold.co/300x180/ffc107/000?text=Cairo"],
    ["name" => "Tokyo",        "country" => "Japan",        "region" => "Asia",   "cost_index" => "High",   "image" => "https://placehold.co/300x180/dc3545/fff?text=Tokyo"],
    ["name" => "Amsterdam",    "country" => "Netherlands",  "region" => "Europe", "cost_index" => "Medium", "image" => "https://placehold.co/300x180/0dcaf0/fff?text=Amsterdam"],
];

// -----------------------------------------------------
// STEP B: Handle "Add to Trip" form submission (POST request)
// Jyare user ek city select kari ne trip ma add kare
// -----------------------------------------------------
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_trip'])) {
    // Form mathi data lai lo
    $trip_id    = $_POST['trip_id'];
    $city_name  = $_POST['city_name'];
    $country    = $_POST['country'];
    $stop_start = $_POST['stop_start_date'];
    $stop_end   = $_POST['stop_end_date'];

    // Basic validation - trip select karyu chhe ke nai check karo
    if (empty($trip_id)) {
        $error_message = "Please select a trip first!";
    } else {
        // Prepared statement vaparine stops table ma insert karo (SQL injection safe)
        $sql_insert = "INSERT INTO stops (trip_id, city_name, country, start_date, end_date) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("issss", $trip_id, $city_name, $country, $stop_start, $stop_end);

        if ($stmt->execute()) {
            $success_message = htmlspecialchars($city_name) . " added to your trip successfully!";
        } else {
            $error_message = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

// -----------------------------------------------------
// STEP C: User ni trips list lavo (dropdown ma "Add to Trip" mate joise)
// -----------------------------------------------------
$sql_trips = "SELECT trip_id, trip_name FROM trips WHERE user_id = ? ORDER BY trip_id DESC";
$stmt = $conn->prepare($sql_trips);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------
// STEP D: Search + Filter logic (GET request thi)
// -----------------------------------------------------
$search_term    = $_GET['search'] ?? '';       // search box nu text
$filter_region  = $_GET['region'] ?? '';        // region dropdown filter
$filter_country = $_GET['country'] ?? '';       // country dropdown filter

// Filter karo cities array ne PHP ma j (database query nathi karvani, static data chhe)
$filtered_cities = array_filter($all_cities, function ($city) use ($search_term, $filter_region, $filter_country) {
    // Search term city name ma match thai chhe ke nai (case-insensitive)
    $matches_search = empty($search_term) || stripos($city['name'], $search_term) !== false;
    // Region filter
    $matches_region = empty($filter_region) || $city['region'] === $filter_region;
    // Country filter
    $matches_country = empty($filter_country) || $city['country'] === $filter_country;

    // Trane condition true hovi joie tabhi j city dekhaay
    return $matches_search && $matches_region && $matches_country;
});

// Dropdown mate unique regions ane countries kadhi lo static array mathi
$all_regions   = array_unique(array_column($all_cities, 'region'));
$all_countries = array_unique(array_column($all_cities, 'country'));
sort($all_regions);
sort($all_countries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>City Search - GlobeTrotter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .city-card img { height: 160px; object-fit: cover; }
        .cost-badge-Low { background-color: #198754; }
        .cost-badge-Medium { background-color: #fd7e14; }
        .cost-badge-High { background-color: #dc3545; }
    </style>
</head>
<body class="bg-light">

<!-- ===== NAVBAR ===== -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">🌍 GlobeTrotter</a>
        <div class="d-flex">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm me-2">Dashboard</a>
            <a href="activity-search.php" class="btn btn-outline-light btn-sm">Activity Search</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <h2 class="mb-4">🔍 Search Cities</h2>

    <!-- Success / Error messages dekhado agar hoy to -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- ===== SEARCH + FILTER FORM (GET method - URL ma dekhaay) ===== -->
    <form method="GET" class="row g-2 mb-4 bg-white p-3 rounded shadow-sm">
        <div class="col-md-5">
            <input type="text" name="search" class="form-control" placeholder="Search city name..."
                   value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
        <div class="col-md-3">
            <select name="region" class="form-select">
                <option value="">All Regions</option>
                <?php foreach ($all_regions as $region): ?>
                    <option value="<?php echo htmlspecialchars($region); ?>" <?php echo ($filter_region === $region) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="country" class="form-select">
                <option value="">All Countries</option>
                <?php foreach ($all_countries as $country): ?>
                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo ($filter_country === $country) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($country); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100">Go</button>
        </div>
    </form>

    <!-- ===== CITY RESULTS ===== -->
    <div class="row g-4">
        <?php if (count($filtered_cities) === 0): ?>
            <div class="col-12">
                <div class="alert alert-warning">No cities found. Try a different search or filter.</div>
            </div>
        <?php else: ?>
            <?php foreach ($filtered_cities as $index => $city): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card city-card h-100 shadow-sm">
                        <img src="<?php echo $city['image']; ?>" class="card-img-top">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($city['name']); ?></h5>
                            <p class="card-text text-muted mb-1"><?php echo htmlspecialchars($city['country']); ?> · <?php echo htmlspecialchars($city['region']); ?></p>
                            <span class="badge cost-badge-<?php echo $city['cost_index']; ?>">Cost: <?php echo $city['cost_index']; ?></span>

                            <!-- Button opens a Bootstrap Modal to add this city to a trip -->
                            <div class="mt-3">
                                <?php if (count($user_trips) > 0): ?>
                                    <button type="button" class="btn btn-outline-primary btn-sm w-100"
                                            data-bs-toggle="modal" data-bs-target="#addModal<?php echo $index; ?>">
                                        + Add to Trip
                                    </button>
                                <?php else: ?>
                                    <small class="text-muted">Create a trip first to add cities.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===== MODAL for this city (one modal per city, unique ID) ===== -->
                <div class="modal fade" id="addModal<?php echo $index; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <!-- Form POST karse aaj page par (self-submit) -->
                            <form method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add <?php echo htmlspecialchars($city['name']); ?> to Trip</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Hidden fields - city nu data form sathe submit thase -->
                                    <input type="hidden" name="city_name" value="<?php echo htmlspecialchars($city['name']); ?>">
                                    <input type="hidden" name="country" value="<?php echo htmlspecialchars($city['country']); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Select Trip</label>
                                        <select name="trip_id" class="form-select" required>
                                            <option value="">-- Choose a trip --</option>
                                            <?php foreach ($user_trips as $trip): ?>
                                                <option value="<?php echo $trip['trip_id']; ?>">
                                                    <?php echo htmlspecialchars($trip['trip_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Stop Start Date</label>
                                        <input type="date" name="stop_start_date" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Stop End Date</label>
                                        <input type="date" name="stop_end_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="add_to_trip" class="btn btn-primary">Add City</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
