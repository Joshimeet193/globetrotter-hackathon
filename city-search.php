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
    ["name" => "Goa",          "country" => "India",        "region" => "Asia",   "cost_index" => "Medium", "image" => "https://placehold.co/300x180?text=Goa"],
    ["name" => "Manali",       "country" => "India",        "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180?text=Manali"],
    ["name" => "Jaipur",       "country" => "India",        "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180?text=Jaipur"],
    ["name" => "Udaipur",      "country" => "India",        "region" => "Asia",   "cost_index" => "Medium", "image" => "https://placehold.co/300x180?text=Udaipur"],
    ["name" => "Kerala",       "country" => "India",        "region" => "Asia",   "cost_index" => "Medium", "image" => "https://placehold.co/300x180?text=Kerala"],
    ["name" => "Paris",        "country" => "France",       "region" => "Europe", "cost_index" => "High",   "image" => "https://placehold.co/300x180?text=Paris"],
    ["name" => "Rome",         "country" => "Italy",        "region" => "Europe", "cost_index" => "High",   "image" => "https://placehold.co/300x180?text=Rome"],
    ["name" => "Barcelona",    "country" => "Spain",        "region" => "Europe", "cost_index" => "Medium", "image" => "https://placehold.co/300x180?text=Barcelona"],
    ["name" => "London",       "country" => "UK",           "region" => "Europe", "cost_index" => "High",   "image" => "https://placehold.co/300x180?text=London"],
    ["name" => "Dubai",        "country" => "UAE",          "region" => "Middle East", "cost_index" => "High", "image" => "https://placehold.co/300x180?text=Dubai"],
    ["name" => "Bali",         "country" => "Indonesia",    "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180?text=Bali"],
    ["name" => "Bangkok",      "country" => "Thailand",     "region" => "Asia",   "cost_index" => "Low",    "image" => "https://placehold.co/300x180?text=Bangkok"],
    ["name" => "Singapore",    "country" => "Singapore",    "region" => "Asia",   "cost_index" => "High",   "image" => "https://placehold.co/300x180?text=Singapore"],
    ["name" => "New York",     "country" => "USA",          "region" => "North America", "cost_index" => "High", "image" => "https://placehold.co/300x180?text=New+York"],
    ["name" => "Los Angeles",  "country" => "USA",          "region" => "North America", "cost_index" => "High", "image" => "https://placehold.co/300x180?text=LA"],
    ["name" => "Sydney",       "country" => "Australia",    "region" => "Oceania", "cost_index" => "High",   "image" => "https://placehold.co/300x180?text=Sydney"],
    ["name" => "Cape Town",    "country" => "South Africa", "region" => "Africa",  "cost_index" => "Medium", "image" => "https://placehold.co/300x180?text=Cape+Town"],
    ["name" => "Cairo",        "country" => "Egypt",        "region" => "Africa",  "cost_index" => "Low",    "image" => "https://placehold.co/300x180?text=Cairo"],
    ["name" => "Tokyo",        "country" => "Japan",        "region" => "Asia",   "cost_index" => "High",   "image" => "https://placehold.co/300x180?text=Tokyo"],
    ["name" => "Amsterdam",    "country" => "Netherlands",  "region" => "Europe", "cost_index" => "Medium", "image" => "https://placehold.co/300x180?text=Amsterdam"],
];

// -----------------------------------------------------
// STEP B: Handle "Add to Trip" form submission (POST request)
// -----------------------------------------------------
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_trip'])) {
    $trip_id    = $_POST['trip_id'];
    $city_name  = $_POST['city_name'];
    $country    = $_POST['country'];
    $stop_start = $_POST['stop_start_date'];
    $stop_end   = $_POST['stop_end_date'];

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
$search_term    = $_GET['search'] ?? '';
$filter_region  = $_GET['region'] ?? '';
$filter_country = $_GET['country'] ?? '';

// Filter karo cities array ne PHP ma j (database query nathi karvani, static data chhe)
$filtered_cities = array_filter($all_cities, function ($city) use ($search_term, $filter_region, $filter_country) {
    $matches_search  = empty($search_term) || stripos($city['name'], $search_term) !== false;
    $matches_region  = empty($filter_region) || $city['region'] === $filter_region;
    $matches_country = empty($filter_country) || $city['country'] === $filter_country;
    return $matches_search && $matches_region && $matches_country;
});

// Dropdown mate unique regions ane countries kadhi lo static array mathi
$all_regions   = array_unique(array_column($all_cities, 'region'));
$all_countries = array_unique(array_column($all_cities, 'country'));
sort($all_regions);
sort($all_countries);

// cost_index mujab kayo Bootstrap contextual color vaparvo (standard bootstrap badges, custom color nathi)
$cost_badge_class = [
    "Low"    => "bg-success",
    "Medium" => "bg-warning text-dark",
    "High"   => "bg-danger",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>City Search - GlobeTrotter</title>
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
            <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-2"><i class="bi bi-house"></i> Dashboard</a>
            <a href="activity-search.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-search"></i> Activity Search</a>
        </div>
    </div>
</nav>

<div class="container my-4">
    <h2 class="section-title"><i class="bi bi-geo-alt"></i> Search Cities</h2>

    <!-- Success / Error messages dekhado agar hoy to -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- ===== SEARCH + FILTER FORM (GET method - URL ma dekhaay) ===== -->
    <form method="GET" class="row g-2 mb-4">
        <div class="col-md-5">
            <label class="form-label"><i class="bi bi-search"></i> City Name</label>
            <input type="text" name="search" class="form-control" placeholder="Search city name..."
                   value="<?php echo htmlspecialchars($search_term); ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Region</label>
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
            <label class="form-label">Country</label>
            <select name="country" class="form-select">
                <option value="">All Countries</option>
                <?php foreach ($all_countries as $country): ?>
                    <option value="<?php echo htmlspecialchars($country); ?>" <?php echo ($filter_country === $country) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($country); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
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
                    <div class="card h-100">
                        <img src="<?php echo $city['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($city['name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($city['name']); ?></h5>
                            <p class="card-text mb-1"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($city['country']); ?> · <?php echo htmlspecialchars($city['region']); ?></p>
                            <span class="badge <?php echo $cost_badge_class[$city['cost_index']]; ?>"><i class="bi bi-wallet2"></i> <?php echo $city['cost_index']; ?> Cost</span>

                            <!-- Button opens a Bootstrap Modal to add this city to a trip -->
                            <div class="mt-3">
                                <?php if (count($user_trips) > 0): ?>
                                    <button type="button" class="btn btn-primary btn-sm w-100"
                                            data-bs-toggle="modal" data-bs-target="#addModal<?php echo $index; ?>">
                                        <i class="bi bi-plus-circle"></i> Add to Trip
                                    </button>
                                <?php else: ?>
                                    <small><i class="bi bi-info-circle"></i> Create a trip first to add cities.</small>
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
                                        <label class="form-label"><i class="bi bi-calendar3"></i> Stop Start Date</label>
                                        <input type="date" name="stop_start_date" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><i class="bi bi-calendar3"></i> Stop End Date</label>
                                        <input type="date" name="stop_end_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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

<footer><p>Made with <span>❤️</span> for GlobeTrotter Hackathon</p></footer>
</body>
</html>
