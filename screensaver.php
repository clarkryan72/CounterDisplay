<?php
// screensaver.php – plays a 1080x1920 vertical screensaver video for 23 sec, then returns to dashboard
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Norman Campers Screensaver</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <style>
    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      background: #000;
    }

    .video-container {
      width: 100%;
      height: 100%;
      overflow: hidden;
      background: #000;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    video {
      width: 100%;
      height: 100%;
      object-fit: cover; /* fills 1080x1920 while cropping if needed */
    }
  </style>
</head>

<body>
  <div class="video-container">
    <video id="screensaverVideo" autoplay muted playsinline>
      <!-- Replace this with your uploaded video -->
      <source src="assets/screensaver.mp4" type="video/mp4" />
    </video>
  </div>

  <script>
    // Return to dashboard after exactly 23 seconds
    const RETURN_SECONDS = 23;

    setTimeout(() => {
      window.location.href = "index.php";
    }, RETURN_SECONDS * 1000);
  </script>
</body>
</html>
