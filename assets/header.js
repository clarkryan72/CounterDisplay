// assets/time.js
(function () {
  const headerDateEl = document.getElementById("todayDate"); // use dashboard header IDs
  const headerTimeEl = document.getElementById("todayTime");

  if (!headerDateEl || !headerTimeEl) return;

  const TIME_ZONE = "America/New_York";

  function updateHeaderDateTime() {
    const now = new Date();

    // Example: "Tue, Dec 9, 2025" (shortened date format)
    headerDateEl.textContent = new Intl.DateTimeFormat("en-US", {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
      timeZone: TIME_ZONE
    }).format(now);

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
