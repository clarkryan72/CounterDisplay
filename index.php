<?php
// /counter_dashboard/index.php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Norman Campers – Lobby Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="assets/dashboard.css">
</head>
<body>
  <div class="dashboard">
    <?php include __DIR__ . '/partials/header.php'; ?>
    <?php include __DIR__ . '/partials/weather.php'; ?>

    <section class="mid-section">
      <?php include __DIR__ . '/partials/unit-card.php'; ?>
      <?php include __DIR__ . '/partials/reviews-card.php'; ?>
    </section>

    <?php include __DIR__ . '/partials/calendars.php'; ?>
  </div>

  <!-- JS config + modules -->
  <script src="assets/config.js"></script>
  <script src="assets/header.js"></script>
  <script src="assets/weather.js"></script>
  <script src="assets/calendars.js"></script>
  <script src="assets/unit.js"></script>
  <script src="assets/reviews-rotator.js"></script>
  <script>
  (function () {
    // How long to wait before showing the screensaver (in minutes)
    const SCREENSAVER_DELAY_MINUTES = 30;
    const SCREENSAVER_URL = "screensaver.php";
    const STORAGE_KEY = "ncScreensaverLastPlay";

    // Convert minutes → milliseconds
    const delayMs = SCREENSAVER_DELAY_MINUTES * 60 * 1000;

    // Determine remaining delay based on last playback time
    const lastPlay = parseInt(localStorage.getItem(STORAGE_KEY), 10) || 0;
    const elapsed = Date.now() - lastPlay;
    const remaining = Math.max(delayMs - elapsed, 0);

    // Start one-shot timer
    setTimeout(function () {
      localStorage.setItem(STORAGE_KEY, Date.now().toString());
      window.location.href = SCREENSAVER_URL;
    }, remaining || delayMs);
  })();
</script>
</body>
</html>
