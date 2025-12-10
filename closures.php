<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$closuresPath = __DIR__ . '/closures.json';

function load_schedule_data(string $path): array
{
    $default = [
        'closures' => [],
        'rv_shows' => [],
    ];

    if (!file_exists($path)) {
        return $default;
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        return $default;
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        return $default;
    }

    return [
        'closures' => $decoded['ranges'] ?? [],
        'rv_shows' => $decoded['rv_show_ranges'] ?? [],
    ];
}

function save_schedule_data(string $path, array $closures, array $rvShows): bool
{
    $payload = [
        'updated_at'      => date('c'),
        'ranges'          => array_values($closures),
        'rv_show_ranges'  => array_values($rvShows),
    ];

    return file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

$errors = [];
$successMessage = '';
$scheduleData = load_schedule_data($closuresPath);
$currentRanges = $scheduleData['closures'];
$currentRvShows = $scheduleData['rv_shows'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedClosures = $_POST['closures'] ?? [];
    $submittedRvShows = $_POST['rvshows'] ?? [];

    $validateRanges = static function (array $ranges, string $context) use (&$errors): array {
        $cleaned = [];

        foreach ($ranges as $range) {
            $start = trim($range['start'] ?? '');
            $end   = trim($range['end'] ?? '');

            if ($start === '' && $end === '') {
                continue;
            }

            if ($end === '') {
                $end = $start; // single-day entries
            }

            $startDate = DateTime::createFromFormat('Y-m-d', $start);
            $endDate   = DateTime::createFromFormat('Y-m-d', $end);

            $startValid = $startDate && $startDate->format('Y-m-d') === $start;
            $endValid   = $endDate && $endDate->format('Y-m-d') === $end;

            if (!$startValid || !$endValid) {
                $errors[] = "{$context} dates must be in YYYY-MM-DD format.";
                continue;
            }

            if ($startDate > $endDate) {
                $errors[] = "{$context} start date cannot be after end date.";
                continue;
            }

            $cleaned[] = [
                'start' => $start,
                'end'   => $end,
            ];
        }

        return $cleaned;
    };

    $cleanClosures = $validateRanges($submittedClosures, 'Closure');
    $cleanRvShows = $validateRanges($submittedRvShows, 'RV show');

    if (empty($errors)) {
        if (save_schedule_data($closuresPath, $cleanClosures, $cleanRvShows)) {
            $successMessage = 'Schedule saved. Sundays are automatically marked closed unless they are RV Show dates.';
            $currentRanges = $cleanClosures;
            $currentRvShows = $cleanRvShows;
        } else {
            $errors[] = 'Unable to save closure days. Please check file permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Closure Days</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #0f172a;
      color: #e2e8f0;
      margin: 0;
      padding: 0;
    }

    .page-shell {
      max-width: 900px;
      margin: 0 auto;
      padding: 32px 20px 48px;
    }

    h1 {
      margin-top: 0;
      color: #38bdf8;
      letter-spacing: 0.02em;
    }

    p.description {
      color: #cbd5e1;
      line-height: 1.5;
    }

    .subheading {
      margin: 24px 0 8px;
      color: #7dd3fc;
      letter-spacing: 0.02em;
      font-size: 18px;
    }

    .helper-text {
      color: #94a3b8;
      font-size: 14px;
      margin-top: 6px;
      line-height: 1.4;
    }

    .card {
      background: #111827;
      border: 1px solid #1f2937;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
      margin-top: 18px;
    }

    .alert {
      padding: 12px 14px;
      border-radius: 10px;
      margin-bottom: 16px;
      font-weight: 600;
    }

    .alert.success {
      background: rgba(16, 185, 129, 0.1);
      border: 1px solid rgba(16, 185, 129, 0.4);
      color: #34d399;
    }

    .alert.error {
      background: rgba(248, 113, 113, 0.1);
      border: 1px solid rgba(248, 113, 113, 0.4);
      color: #fca5a5;
    }

    form {
      margin-top: 12px;
    }

    .closure-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 12px;
      align-items: end;
      margin-bottom: 12px;
      padding: 12px;
      border-radius: 10px;
      background: #0b1220;
      border: 1px solid #1f2937;
    }

    label {
      display: block;
      font-size: 14px;
      color: #cbd5e1;
      margin-bottom: 6px;
    }

    input[type="date"] {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #1f2937;
      background: #0f172a;
      color: #e2e8f0;
    }

    .row-actions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    button {
      cursor: pointer;
      border: none;
      border-radius: 8px;
      padding: 10px 14px;
      font-weight: 700;
      letter-spacing: 0.01em;
    }

    .btn-primary {
      background: linear-gradient(135deg, #22d3ee, #6366f1);
      color: #0b1220;
    }

    .btn-secondary {
      background: #1f2937;
      color: #e2e8f0;
      border: 1px solid #334155;
    }

    .btn-danger {
      background: #ef4444;
      color: #0b1220;
    }

    .footer-note {
      color: #94a3b8;
      margin-top: 16px;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="page-shell">
    <h1>Manage Closure Days</h1>
    <p class="description">Add date ranges for planned closures and the RV Show. All Sundays are automatically marked closed on the dashboard unless they overlap with an RV Show.</p>

    <div class="card">
      <?php if ($successMessage): ?>
        <div class="alert success"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <?php foreach ($errors as $error): ?>
          <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <form method="POST" id="closuresForm">
        <div>
          <div class="subheading">Closure dates</div>
          <p class="helper-text">Add one or more date ranges when the business is closed. Sundays are treated as closed automatically.</p>
        </div>
        <div id="closureList">
          <?php if (!empty($currentRanges)): ?>
            <?php foreach ($currentRanges as $idx => $range): ?>
              <div class="closure-row" data-row-index="<?= (int) $idx; ?>">
                <div>
                  <label for="start-<?= (int) $idx; ?>">Start date</label>
                  <input type="date" id="start-<?= (int) $idx; ?>" name="closures[<?= (int) $idx; ?>][start]" value="<?= htmlspecialchars($range['start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div>
                  <label for="end-<?= (int) $idx; ?>">End date</label>
                  <input type="date" id="end-<?= (int) $idx; ?>" name="closures[<?= (int) $idx; ?>][end]" value="<?= htmlspecialchars($range['end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="row-actions">
                  <button type="button" class="btn-danger remove-row">Remove</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="closure-row" data-row-index="0">
              <div>
                <label for="start-0">Start date</label>
                <input type="date" id="start-0" name="closures[0][start]" required />
              </div>
              <div>
                <label for="end-0">End date</label>
                <input type="date" id="end-0" name="closures[0][end]" />
              </div>
              <div class="row-actions">
                <button type="button" class="btn-danger remove-row">Remove</button>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-secondary" id="addClosureRow">Add another closure range</button>

        <div style="margin-top: 24px;">
          <div class="subheading">RV Show dates</div>
          <p class="helper-text">Enter up to two RV Show date ranges per year. RV Show days override the automatic Sunday closures and will appear on the dashboard calendar.</p>
        </div>

        <div id="rvShowList">
          <?php if (!empty($currentRvShows)): ?>
            <?php foreach ($currentRvShows as $idx => $range): ?>
              <div class="closure-row" data-row-index="<?= (int) $idx; ?>">
                <div>
                  <label for="rv-start-<?= (int) $idx; ?>">Start date</label>
                  <input type="date" id="rv-start-<?= (int) $idx; ?>" name="rvshows[<?= (int) $idx; ?>][start]" value="<?= htmlspecialchars($range['start'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                </div>
                <div>
                  <label for="rv-end-<?= (int) $idx; ?>">End date</label>
                  <input type="date" id="rv-end-<?= (int) $idx; ?>" name="rvshows[<?= (int) $idx; ?>][end]" value="<?= htmlspecialchars($range['end'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                <div class="row-actions">
                  <button type="button" class="btn-danger remove-row">Remove</button>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="closure-row" data-row-index="0">
              <div>
                <label for="rv-start-0">Start date</label>
                <input type="date" id="rv-start-0" name="rvshows[0][start]" required />
              </div>
              <div>
                <label for="rv-end-0">End date</label>
                <input type="date" id="rv-end-0" name="rvshows[0][end]" />
              </div>
              <div class="row-actions">
                <button type="button" class="btn-danger remove-row">Remove</button>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <button type="button" class="btn-secondary" id="addRvShowRow">Add another RV Show range</button>

        <div style="margin-top: 16px;">
          <button type="submit" class="btn-primary">Save schedule</button>
        </div>
      </form>

      <p class="footer-note">Leave the end date blank for single-day entries. Add as many date ranges as you need.</p>
    </div>
  </div>

  <script>
    (function () {
      const closureList = document.getElementById('closureList');
      const rvShowList = document.getElementById('rvShowList');
      const addClosureRowBtn = document.getElementById('addClosureRow');
      const addRvShowRowBtn = document.getElementById('addRvShowRow');

      function renumberRows(listElement, prefix) {
        const rows = Array.from(listElement.querySelectorAll('.closure-row'));
        rows.forEach((row, idx) => {
          row.dataset.rowIndex = idx;

          const startInput = row.querySelector('input[name$="[start]"]');
          const endInput = row.querySelector('input[name$="[end]"]');

          if (startInput) {
            startInput.name = `${prefix}[${idx}][start]`;
            startInput.id = `${prefix === 'closures' ? 'start' : 'rv-start'}-${idx}`;
            const label = row.querySelector(`label[for="${startInput.id}"]`);
            if (label) label.setAttribute('for', startInput.id);
          }

          if (endInput) {
            endInput.name = `${prefix}[${idx}][end]`;
            endInput.id = `${prefix === 'closures' ? 'end' : 'rv-end'}-${idx}`;
            const label = row.querySelector(`label[for="${endInput.id}"]`);
            if (label) label.setAttribute('for', endInput.id);
          }
        });
      }

      function addRow(listElement, prefix, startValue = '', endValue = '') {
        const idx = listElement.querySelectorAll('.closure-row').length;
        const isClosure = prefix === 'closures';
        const row = document.createElement('div');
        row.className = 'closure-row';
        row.dataset.rowIndex = idx;
        row.innerHTML = `
          <div>
            <label for="${isClosure ? 'start' : 'rv-start'}-${idx}">Start date</label>
            <input type="date" id="${isClosure ? 'start' : 'rv-start'}-${idx}" name="${prefix}[${idx}][start]" value="${startValue}" required />
          </div>
          <div>
            <label for="${isClosure ? 'end' : 'rv-end'}-${idx}">End date</label>
            <input type="date" id="${isClosure ? 'end' : 'rv-end'}-${idx}" name="${prefix}[${idx}][end]" value="${endValue}" />
          </div>
          <div class="row-actions">
            <button type="button" class="btn-danger remove-row">Remove</button>
          </div>
        `;

        listElement.appendChild(row);
      }

      addClosureRowBtn.addEventListener('click', function () {
        addRow(closureList, 'closures');
      });

      addRvShowRowBtn.addEventListener('click', function () {
        addRow(rvShowList, 'rvshows');
      });

      function handleRemoveClick(event, listElement, prefix) {
        if (event.target.classList.contains('remove-row')) {
          const row = event.target.closest('.closure-row');
          if (row) {
            row.remove();
            renumberRows(listElement, prefix);
          }
        }
      }

      closureList.addEventListener('click', function (event) {
        handleRemoveClick(event, closureList, 'closures');
      });

      rvShowList.addEventListener('click', function (event) {
        handleRemoveClick(event, rvShowList, 'rvshows');
      });

      if (closureList.querySelectorAll('.closure-row').length === 0) {
        addRow(closureList, 'closures');
      }

      if (rvShowList.querySelectorAll('.closure-row').length === 0) {
        addRow(rvShowList, 'rvshows');
      }
    })();
  </script>
</body>
</html>
