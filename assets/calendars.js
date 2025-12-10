function ymd(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

// Appointments (currently unused but left for future)
function isAppointment(date) {
  return APPOINTMENTS.includes(ymd(date));
}

// Closed days
function isClosed(date) {
  const t = date.getTime();
  return CLOSED_RANGES.some((range) => {
    const start = new Date(range.start + "T00:00:00").getTime();
    const end = new Date(range.end + "T23:59:59").getTime();
    return t >= start && t <= end;
  });
}

// Atlanta Camping & RV Show days
function isCamperShow(date) {
  return CAMPER_SHOW_DATES.includes(ymd(date));
}

function isTimBirthday(date) {
  return date.getMonth() === 0 && date.getDate() === 2;
}

function renderCalendar(monthOffset, gridId, labelId) {
  const today = new Date();
  const firstDayDate = new Date(
    today.getFullYear(),
    today.getMonth() + monthOffset,
    1
  );

  const year = firstDayDate.getFullYear();
  const month = firstDayDate.getMonth();

  const monthLabel = firstDayDate.toLocaleDateString(undefined, {
    month: "long",
    year: "numeric",
  });

  const labelElem = document.getElementById(labelId);
  const gridElem = document.getElementById(gridId);
  if (!labelElem || !gridElem) return;

  labelElem.textContent = monthLabel;
  gridElem.innerHTML = "";

  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const firstWeekday = firstDayDate.getDay();

  // Previous month muted days
  const prevMonthDays = new Date(year, month, 0).getDate();
  for (let i = 0; i < firstWeekday; i++) {
    const cellDate = new Date(
      year,
      month - 1,
      prevMonthDays - firstWeekday + 1 + i
    );
    const cell = document.createElement("div");
    cell.className = "calendar-cell muted";
    cell.textContent = cellDate.getDate();
    gridElem.appendChild(cell);
  }

  // Current month days
  for (let day = 1; day <= daysInMonth; day++) {
    const cellDate = new Date(year, month, day);
    const cell = document.createElement("div");
    cell.className = "calendar-cell";

    const isBirthday = isTimBirthday(cellDate);
    const isToday =
      cellDate.getFullYear() === today.getFullYear() &&
      cellDate.getMonth() === today.getMonth() &&
      cellDate.getDate() === today.getDate();

    const weekday = cellDate.getDay();
    if (weekday === 0 || weekday === 6) cell.classList.add("weekend");
    if (isToday) cell.classList.add("today");

    if (isClosed(cellDate)) {
      cell.classList.add("closed");
    } else if (isCamperShow(cellDate)) {
      cell.classList.add("camper-show");
    } else if (isAppointment(cellDate)) {
      // currently unused, but here if you bring appointments back later
      cell.classList.add("appt");
    } else if (isBirthday) {
      cell.classList.add("birthday");
    }

    if (isBirthday) {
      // match the RV show styling: full-tile background with the provided GIF
      cell.textContent = day;
      cell.setAttribute("data-daynum", String(day));
    } else {
      // set date number and data attribute for ::before
      cell.textContent = day;
      cell.setAttribute("data-daynum", String(day));
    }

    gridElem.appendChild(cell);
  }

  // Next month muted days to fill grid to 6 weeks (42 cells)
  const totalCells = 42;
  let nextDayNumber = 1;
  while (gridElem.children.length < totalCells) {
    const cellDate = new Date(year, month + 1, nextDayNumber);
    const cell = document.createElement("div");
    cell.className = "calendar-cell muted";
    cell.textContent = cellDate.getDate();
    gridElem.appendChild(cell);
    nextDayNumber++;
  }
}

function renderCalendars() {
  renderCalendar(0, "calendarCurrent", "monthLabelCurrent");
  renderCalendar(1, "calendarNext", "monthLabelNext");
}

renderCalendars();

function scheduleMidnightRefresh() {
  const now = new Date();
  const millisTillMidnight =
    new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 5) -
    now;
  setTimeout(() => {
    renderCalendars();
    scheduleMidnightRefresh();
  }, millisTillMidnight);
}
scheduleMidnightRefresh();
