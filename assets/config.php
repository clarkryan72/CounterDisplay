<?php
header('Content-Type: application/javascript');
header('Cache-Control: no-cache, no-store, must-revalidate');

$closuresPath = __DIR__ . '/../closures.json';
$closures = [];
if (file_exists($closuresPath)) {
    $contents = file_get_contents($closuresPath);
    if ($contents !== false) {
        $decoded = json_decode($contents, true);
        if (is_array($decoded) && isset($decoded['ranges']) && is_array($decoded['ranges'])) {
            $validRanges = [];
            foreach ($decoded['ranges'] as $range) {
                $start = isset($range['start']) ? (string) $range['start'] : '';
                $end   = isset($range['end']) ? (string) $range['end'] : '';
                if ($start !== '' && $end !== '') {
                    $validRanges[] = ['start' => $start, 'end' => $end];
                }
            }
            $closures = $validRanges;
        }
    }
}
?>
// Shared config for the dashboard

// Norman Campers coordinates (approx for Marietta)
const LATITUDE = 33.93;
const LONGITUDE = -84.56;
const USE_FAHRENHEIT = true;

// Meteocons SVG location
const WEATHER_ICON_BASE = "icons/meteocons-filled";

// Google review URL (your real review link)
const GOOGLE_REVIEW_URL = "https://g.page/r/CcX-PYUrciKPEAE/review";

// No normal appointments right now
const APPOINTMENTS = [];

// Closed dates
const CLOSED_RANGES = <?php echo json_encode($closures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

// Atlanta Camping & RV Show (Jan 22–25, 2026)
const CAMPER_SHOW_DATES = [
  "2026-01-22",
  "2026-01-23",
  "2026-01-24",
  "2026-01-25"
];

// Open-Meteo URL
const OPEN_METEO_URL =
  "https://api.open-meteo.com/v1/forecast" +
  `?latitude=${LATITUDE}` +
  `&longitude=${LONGITUDE}` +
  "&current=temperature_2m,weathercode,wind_speed_10m,is_day" +
  "&daily=weathercode,temperature_2m_max,temperature_2m_min" +
  "&forecast_days=4" +
  "&timezone=America%2FNew_York" +
  (USE_FAHRENHEIT ? "&temperature_unit=fahrenheit" : "");
