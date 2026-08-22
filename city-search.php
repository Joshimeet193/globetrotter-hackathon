<?php
// =====================================================
// city-search.php
// Cities search + filter page.
// HAVE REAL CITY + COUNTRY tables chhe database ma, etle
// hardcoded array nathi vaparyu - direct DB thi data lavo chhiye.
// "Add to Trip" click karta TRIP_STOP table ma row insert thay chhe.
// =====================================================

session_start();
include 'includes/db-connect.php';

if (!isset($_SESSION['User_ID'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['User_ID'];

// -----------------------------------------------------
// Cost_Index ek numeric decimal chhe (Low/Medium/High jevu enum nathi).
// Etle apane j ek assumption ni scale banavi chhe cost ne "Low/Medium/High"
// batavva mate.
// -----------------------------------------------------
define('COST_LOW_MAX', 40); // Cost_Index <= 40 => Low
define('COST_MEDIUM_MAX', 80); // Cost_Index 41-80 => Medium, 80+ => High

function cost_label($cost_index) {
    if ($cost_index <= COST_LOW_MAX) return ['label' => 'Low', 'class' => 'bg-success'];
    if ($cost_index <= COST_MEDIUM_MAX) return ['label' => 'Medium', 'class' => 'bg-warning text-dark'];
    return ['label' => 'High', 'class' => 'bg-danger'];
}

// -----------------------------------------------------
// STEP A: Handle "Add to Trip" form submission (POST)
// -----------------------------------------------------
$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_trip'])) {
    $trip_id = (int) $_POST['trip_id'];
    $city_id = (int) $_POST['city_id'];
    $arrival_date = $_POST['arrival_date'];
    $departure_date = $_POST['departure_date'];

    if (empty($trip_id) || empty($city_id)) {
        $error_message = "Please select a trip first!";
    } else {
        // Security check: e trip khareki aaj logged-in user ni j chhe ke nai
        $sql_check = "SELECT Trip_ID FROM TRIP WHERE Trip_ID = ? AND User_ID = ?";
        $stmt = $conn->prepare($sql_check);
        $stmt->bind_param("ii", $trip_id, $user_id);
        $stmt->execute();
        $owns_trip = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$owns_trip) {
            $error_message = "Invalid trip selected.";
        } else {
            // Stop_Order calculate karo
            $sql_order = "SELECT COALESCE(MAX(Stop_Order), 0) + 1 AS next_order FROM TRIP_STOP WHERE Trip_ID = ?";
            $stmt = $conn->prepare($sql_order);
            $stmt->bind_param("i", $trip_id);
            $stmt->execute();
            $next_order = $stmt->get_result()->fetch_assoc()['next_order'];
            $stmt->close();

            // Have TRIP_STOP table ma naya stop insert karo
            $sql_insert = "INSERT INTO TRIP_STOP (Trip_ID, City_ID, Stop_Order, Arrival_Date, Departure_Date)
                            VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iiiss", $trip_id, $city_id, $next_order, $arrival_date, $departure_date);
            if ($stmt->execute()) {
                $success_message = "City added to your trip successfully!";
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
            $stmt->close();
        }
    }
}

// -----------------------------------------------------
// STEP B: User ni trips list lavo (dropdown mate)
// -----------------------------------------------------
$sql_trips = "SELECT Trip_ID, Trip_Name FROM TRIP WHERE User_ID = ? ORDER BY Trip_ID DESC";
$stmt = $conn->prepare($sql_trips);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// -----------------------------------------------------
// STEP C: Badhi cities DB mathi lavo (CITY + COUNTRY joined)
// -----------------------------------------------------
$sql_cities = "SELECT CITY.City_ID, CITY.City_Name, CITY.Cost_Index, CITY.Image,
                      COUNTRY.Country_Name, COUNTRY.Region
               FROM CITY
               JOIN COUNTRY ON CITY.Country_ID = COUNTRY.Country_ID
               ORDER BY CITY.Popularity DESC";
$all_cities = $conn->query($sql_cities)->fetch_all(MYSQLI_ASSOC);

// GET params thi filters lai lo
$search_term = $_GET['search'] ?? '';
$filter_region = $_GET['region'] ?? '';
$filter_country = $_GET['country'] ?? '';

// PHP ma j filter karo (array_filter)
$filtered_cities = array_filter($all_cities, function ($city) use ($search_term, $filter_region, $filter_country) {
    $matches_search = empty($search_term) || stripos($city['City_Name'], $search_term) !== false;
    $matches_region = empty($filter_region) || $city['Region'] === $filter_region;
    $matches_country = empty($filter_country) || $city['Country_Name'] === $filter_country;
    return $matches_search && $matches_region && $matches_country;
});

// Dropdown mate unique regions/countries kadhi lo
$all_regions = array_unique(array_column($all_cities, 'Region'));
$all_countries = array_unique(array_column($all_cities, 'Country_Name'));
sort($all_regions);
sort($all_countries);
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

<?php $active_page = 'city-search'; include 'includes/navbar.php'; ?>

<div class="container my-4">

<h2 class="section-title"><i class="bi bi-geo-alt"></i> Search Cities</h2>

<?php if ($success_message): ?>
<div class="alert alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- ===== SEARCH + FILTER FORM (GET method) ===== -->
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
<?php foreach ($filtered_cities as $city): ?>
<?php $cost = cost_label($city['Cost_Index']); ?>
<div class="col-md-4 col-lg-3">
<div class="card h-100">
<?php $city_image = !empty($city['Image']) ? $city['Image'] : 'https://placehold.co/300x180?text=' . urlencode($city['City_Name']); ?>
<img src="<?php echo htmlspecialchars($city_image); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($city['City_Name']); ?>">
<div class="card-body">
<h5 class="card-title"><?php echo htmlspecialchars($city['City_Name']); ?></h5>
<p class="card-text mb-1"><i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($city['Country_Name']); ?> · <?php echo htmlspecialchars($city['Region']); ?></p>
<span class="badge <?php echo $cost['class']; ?>"><i class="bi bi-wallet2"></i> <?php echo $cost['label']; ?> Cost</span>

<div class="mt-3">
<?php if (count($user_trips) > 0): ?>
<button type="button" class="btn btn-primary btn-sm w-100"
data-bs-toggle="modal" data-bs-target="#addModal<?php echo $city['City_ID']; ?>">
<i class="bi bi-plus-circle"></i> Add to Trip
</button>
<?php else: ?>
<small><i class="bi bi-info-circle"></i> Create a trip first to add cities.</small>
<?php endif; ?>
</div>
</div>
</div>
</div>

<!-- ===== MODAL for this city ===== -->
<div class="modal fade" id="addModal<?php echo $city['City_ID']; ?>" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST">
<div class="modal-header">
<h5 class="modal-title">Add <?php echo htmlspecialchars($city['City_Name']); ?> to Trip</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<input type="hidden" name="city_id" value="<?php echo $city['City_ID']; ?>">
<div class="mb-3">
<label class="form-label">Select Trip</label>
<select name="trip_id" class="form-select" required>
<option value="">-- Choose a trip --</option>
<?php foreach ($user_trips as $trip): ?>
<option value="<?php echo $trip['Trip_ID']; ?>">
<?php echo htmlspecialchars($trip['Trip_Name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-calendar3"></i> Arrival Date</label>
<input type="date" name="arrival_date" class="form-control" required>
</div>
<div class="mb-3">
<label class="form-label"><i class="bi bi-calendar3"></i> Departure Date</label>
<input type="date" name="departure_date" class="form-control" required>
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

<script src="js/script.js"></script>
</body>

</html>
