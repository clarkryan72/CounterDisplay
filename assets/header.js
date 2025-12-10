// assets/time.js
(function () {
  const headerDateEl = document.getElementById("todayDate"); // use dashboard header IDs
  const headerTimeEl = document.getElementById("todayTime");

  if (!headerDateEl || !headerTimeEl) return;

  const TIME_ZONE = "America/New_York";

  const dateFormatter = new Intl.DateTimeFormat("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
    timeZone: TIME_ZONE
  });

  const timeFormatter = new Intl.DateTimeFormat("en-US", {
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
    timeZone: TIME_ZONE
  });

  function formatOrdinalDay(day) {
    const remainder = day % 100;
    if (remainder >= 11 && remainder <= 13) return `${day}th`;

    switch (remainder % 10) {
      case 1:
        return `${day}st`;
      case 2:
        return `${day}nd`;
      case 3:
        return `${day}rd`;
      default:
        return `${day}th`;
    }
  }

  function updateHeaderDateTime() {
    const now = new Date();
    const parts = dateFormatter.formatToParts(now);
    const partMap = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    const day = Number(partMap.day);
    const ordinalDay = Number.isFinite(day) ? formatOrdinalDay(day) : partMap.day;
    headerDateEl.textContent = `${partMap.weekday}, ${partMap.month} ${ordinalDay}, ${partMap.year}`;

    // Example: "9:23 AM"
    headerTimeEl.textContent = timeFormatter.format(now);
  }

  updateHeaderDateTime();
  setInterval(updateHeaderDateTime, 1000);
})();
