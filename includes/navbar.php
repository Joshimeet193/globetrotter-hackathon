<?php
/*
includes/navbar.php
---------------------------------------------
Reusable navbar so every page looks the same.
Before including this file, set a variable:
   $active_page = 'dashboard'; // or 'city-search', 'activity-search', 'my-trips'
That variable is used below to highlight the current link.
*/
?>
<nav class="navbar navbar-expand-lg shadow-sm sticky-top" id="gtMainNavbar">
<div class="container">

<a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
<span>🌍 GlobeTrotter</span>
<span class="d-none d-lg-inline" style="font-family:'Space Mono',monospace; font-size:0.65rem; letter-spacing:0.08em; color: var(--gold); border:1px solid rgba(255,255,255,0.25); padding:2px 8px; border-radius:20px;">GT&#8209;01</span>
</a>

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
<a href="create-trip.php" class="btn btn-primary btn-sm">
<i class="bi bi-plus-circle"></i> Plan New Trip
</a>
<a href="profile.php" class="navbar-profile-link small d-none d-md-inline <?php echo ($active_page ?? '') === 'profile' ? 'active' : ''; ?>">
<i class="bi bi-person-circle"></i>
<?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Traveler'); ?>
</a>
<a href="logout.php" class="btn btn-outline-primary btn-sm">
<i class="bi bi-box-arrow-right"></i> Logout
</a>
</div>
</div>
</div>
</nav>

<script>
// Adds the "navbar-scrolled" class (tighter padding + deeper shadow,
// already styled in css/style.css) once the page scrolls past 40px.
(function () {
  const nav = document.getElementById('gtMainNavbar');
  if (!nav) return;
  window.addEventListener('scroll', function () {
    nav.classList.toggle('navbar-scrolled', window.scrollY > 40);
  }, { passive: true });
})();
</script>
