// assets/time.js
(function () {
  const headerDateEl = document.getElementById("todayDate"); // use dashboard header IDs
  const headerTimeEl = document.getElementById("todayTime");

  if (!headerDateEl || !headerTimeEl) return;

  const WEEKDAYS = ["Sun", "Mon", "Tues", "Wed", "Thurs", "Fri", "Sat"];
  const MONTHS = [
    "Jan", "Feb", "Mar", "Apr", "May", "Jun",
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
  ];

  function updateHeaderDateTime() {
    const now = new Date();

    const weekday = WEEKDAYS[now.getDay()];
    const monthName = MONTHS[now.getMonth()];
    const day = now.getDate();
    const year = now.getFullYear();

    // Example: "Tues, Dec 9, 2025" (shortened date format)
    headerDateEl.textContent = `${weekday}, ${monthName} ${day}, ${year}`;

    // Example: "9:23 AM"
    headerTimeEl.textContent = now.toLocaleTimeString([], {
      hour: "numeric",
      minute: "2-digit",
      hour12: true
    });
  }

  updateHeaderDateTime();
  setInterval(updateHeaderDateTime, 1000);
})();
