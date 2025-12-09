// assets/time.js
(function () {
  const headerDateEl = document.getElementById("todayDate"); // use dashboard header IDs
  const headerTimeEl = document.getElementById("todayTime");

  if (!headerDateEl || !headerTimeEl) return;

  const TIME_ZONE = "America/New_York";
  const STATIC_DATE_TEXT = "Tuesday, December 9th, 2025";

  function updateHeaderDateTime() {
    const now = new Date();

    // Fixed display date requested by design
    headerDateEl.textContent = STATIC_DATE_TEXT;

    // Example: "9:23 AM"
    headerTimeEl.textContent = new Intl.DateTimeFormat("en-US", {
      hour: "numeric",
      minute: "2-digit",
      hour12: true,
      timeZone: TIME_ZONE
    }).format(now);
  }

  updateHeaderDateTime();
  setInterval(updateHeaderDateTime, 1000);
})();
