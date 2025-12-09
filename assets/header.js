// assets/time.js
(function () {
  const headerDateEl = document.getElementById("headerDate");
  const headerTimeEl = document.getElementById("headerTime");

  if (!headerDateEl || !headerTimeEl) return;

  const WEEKDAYS = ["Sun", "Mon", "Tues", "Wed", "Thurs", "Fri", "Sat"];
  const MONTHS = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
  ];

  function updateHeaderDateTime() {
    const now = new Date();

    const weekday = WEEKDAYS[now.getDay()];
    const monthName = MONTHS[now.getMonth()];
    const day = now.getDate();
    const year = now.getFullYear();

    // Example: "Tues December 9, 2025"
    headerDateEl.textContent = `${weekday} ${monthName} ${day}, ${year}`;

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
