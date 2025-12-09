// JS version of your PHP icon map
const ICON_MAP = {
  0:  { day: "clear-day",                  night: "clear-night" },
  1:  { day: "partly-cloudy-day",          night: "partly-cloudy-night" },
  2:  { day: "partly-cloudy-day",          night: "partly-cloudy-night" },
  3:  { day: "overcast-day",               night: "overcast-night" },

  45: { day: "fog-day",                    night: "fog-night" },
  48: { day: "fog-day",                    night: "fog-night" },

  51: { day: "partly-cloudy-day-drizzle",  night: "partly-cloudy-night-drizzle" },
  53: { day: "overcast-day-drizzle",       night: "overcast-night-drizzle" },
  55: { day: "overcast-day-drizzle",       night: "overcast-night-drizzle" },
  56: { day: "overcast-day-sleet",         night: "overcast-night-sleet" },
  57: { day: "overcast-day-sleet",         night: "overcast-night-sleet" },

  61: { day: "partly-cloudy-day-rain",     night: "partly-cloudy-night-rain" },
  63: { day: "overcast-day-rain",          night: "overcast-night-rain" },
  65: { day: "overcast-day-rain",          night: "overcast-night-rain" },
  66: { day: "overcast-day-sleet",         night: "overcast-night-sleet" },
  67: { day: "overcast-day-sleet",         night: "overcast-night-sleet" },

  71: { day: "partly-cloudy-day-snow",     night: "partly-cloudy-night-snow" },
  73: { day: "overcast-day-snow",          night: "overcast-night-snow" },
  75: { day: "overcast-day-snow",          night: "overcast-night-snow" },
  77: { day: "overcast-day-snow",          night: "overcast-night-snow" },

  80: { day: "partly-cloudy-day-rain",     night: "partly-cloudy-night-rain" },
  81: { day: "overcast-day-rain",          night: "overcast-night-rain" },
  82: { day: "overcast-day-rain",          night: "overcast-night-rain" },

  85: { day: "partly-cloudy-day-snow",     night: "partly-cloudy-night-snow" },
  86: { day: "overcast-day-snow",          night: "overcast-night-snow" },

  95: { day: "thunderstorms-day",              night: "thunderstorms-night" },
  96: { day: "thunderstorms-day-rain",        night: "thunderstorms-night-rain" },
  99: { day: "thunderstorms-day-overcast-rain", night: "thunderstorms-night-overcast-rain" }
};

const ICON_FALLBACK = {
  day:   "not-available",
  night: "not-available"
};

function getIconName(code, isDay) {
  const dayNightKey = isDay ? "day" : "night";
  const map = ICON_MAP[code] || ICON_FALLBACK;
  return map[dayNightKey] || ICON_FALLBACK[dayNightKey];
}

function getWeatherDescription(code) {
  if (code === 0) return "Mostly sunny";
  if (code === 1) return "Mainly clear";
  if (code === 2) return "Partly cloudy";
  if (code === 3) return "Overcast";
  if (code === 45 || code === 48) return "Fog";
  if (code >= 51 && code <= 57) return "Drizzle";
  if (code >= 61 && code <= 67) return "Rain";
  if (code >= 71 && code <= 77) return "Snow";
  if (code >= 80 && code <= 82) return "Rain showers";
  if (code >= 85 && code <= 86) return "Snow showers";
  if (code >= 95 && code <= 99) return "Thunderstorms";
  return "Not available";
}

async function loadWeather() {
  const rowElem = document.getElementById("weatherDayRow");

  try {
    const response = await fetch(OPEN_METEO_URL);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();

    const current = data.current;
    const daily = data.daily;
    if (!current || !daily) throw new Error("Incomplete weather data");

    const days = daily.time;
    const maxTemps = daily.temperature_2m_max;
    const minTemps = daily.temperature_2m_min;
    const codes = daily.weathercode;

    rowElem.innerHTML = "";

    for (let i = 0; i < Math.min(days.length, 4); i++) {
      const date = new Date(days[i] + "T00:00:00");
      const isToday = i === 0;

      const label = isToday
        ? "Today"
        : date.toLocaleDateString(undefined, { weekday: "long" });

      const code = codes[i];
      const desc = getWeatherDescription(code);

      // For today we use actual is_day; forecast days assume "day"
      const isDay = isToday ? (current.is_day ?? 1) : 1;
      const iconName = getIconName(code, isDay);

      const maxT = Math.round(maxTemps[i]);
      const minT = Math.round(minTemps[i]);

      const cell = document.createElement("div");
      cell.className = "weather-day" + (isToday ? " today" : "");

      const iconClass = isToday ? "weather-icon-big" : "weather-icon-small";
      const hiLoClass = isToday
        ? "weather-hi-lo weather-hi-lo-large"
        : "weather-hi-lo weather-hi-lo-regular";

      // TV-style layout: label, big icon, big Hi/Lo, condition text
      cell.innerHTML = `
        <div class="weather-day-label">${label}</div>
        <img class="${iconClass}" src="${WEATHER_ICON_BASE}/${iconName}.svg" alt="${desc} icon" />
        <div class="${hiLoClass}">
          <span class="temp-hi">${maxT}°</span>
          <span class="slash">/</span>
          <span class="temp-lo">${minT}°</span>
        </div>
        <div class="weather-desc">${desc}</div>
      `;

      rowElem.appendChild(cell);
    }
  } catch (err) {
    console.error("Weather error:", err);
    rowElem.innerHTML = "";
    const fallback = document.createElement("div");
    fallback.className = "weather-day today";
    fallback.innerHTML =
      '<div class="weather-day-label">Today</div><div class="weather-desc">Unable to load weather</div>';
    rowElem.appendChild(fallback);
  }
}

loadWeather();
setInterval(loadWeather, 30 * 60 * 1000); // refresh every 30 minutes
