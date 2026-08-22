<?php
/*
    includes/navbar.php
    ---------------------------------------------
    Reusable navbar so every page looks the same.
    Before including this file, set a variable:
        $active_page = 'dashboard';  // or 'city-search', 'activity-search'
    That variable is used below to highlight the current link.
*/
?>
<nav class="navbar navbar-expand-lg navbar-gt shadow-sm sticky-top">
  <div class="container">
    <!-- Logo / Brand -->
    <a class="navbar-brand" href="dashboard.php">🌍 GlobeTrotter</a>

    <!-- Hamburger for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#gtNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="gtNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?>" href="dashboard.php">
            <i class="bi bi-house-door"></i> Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_page ?? '') === 'city-search' ? 'active' : ''; ?>" href="city-search.php">
            <i class="bi bi-geo-alt"></i> Search Cities
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_page ?? '') === 'activity-search' ? 'active' : ''; ?>" href="activity-search.php">
            <i class="bi bi-compass"></i> Search Activities
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?php echo ($active_page ?? '') === 'my-trips' ? 'active' : ''; ?>" href="my-trips.php">
            <i class="bi bi-suitcase-lg"></i> My Trips
          </a>
        </li>
      </ul>

      <div class="d-flex align-items-center gap-2">
        <a href="create-trip.php" class="btn btn-gt-primary btn-sm">
          <i class="bi bi-plus-circle"></i> Plan New Trip
        </a>
        <!-- Logged-in user name + logout -->
        <span class="text-white-50 small d-none d-md-inline">
          <i class="bi bi-person-circle"></i>
          <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Traveler'); ?>
        </span>
        <a href="logout.php" class="btn btn-gt-outline btn-sm" style="border-color:#fff;color:#fff;">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </div>
  </div>
</nav>
