let reviews = [];
let currentReviewIndex = 0;
let reviewTotal = 0;
let overallRating = null;
let reviewVersion = null;
const REVIEW_PROGRESS_KEY = "nc_review_progress";

function loadStoredIndex(version, maxLength) {
  try {
    const raw = localStorage.getItem(REVIEW_PROGRESS_KEY);
    if (!raw) return 0;
    const parsed = JSON.parse(raw);
    if (!parsed || parsed.version !== version) return 0;

    const idx = Number.parseInt(parsed.index, 10);
    if (Number.isNaN(idx) || idx < 0) return 0;
    return maxLength > 0 ? idx % maxLength : 0;
  } catch (err) {
    console.warn("Unable to read review progress", err);
    return 0;
  }
}

function storeNextIndex(version, index) {
  try {
    localStorage.setItem(
      REVIEW_PROGRESS_KEY,
      JSON.stringify({ version, index })
    );
  } catch (err) {
    console.warn("Unable to persist review progress", err);
  }
}

function setupReviewQr() {
  const qrImg = document.getElementById("reviewQr");
  if (!qrImg || !GOOGLE_REVIEW_URL) return;
  qrImg.src =
    "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" +
    encodeURIComponent(GOOGLE_REVIEW_URL);
}

function showCurrentReview() {
  const textEl = document.getElementById("reviewText");
  const authorEl = document.getElementById("reviewAuthor");
  const timeEl = document.getElementById("reviewTime");
  const ratingEl = document.getElementById("reviewsOverallRating");
  const countEl = document.getElementById("reviewsCount");

  if (!reviews.length) {
    textEl.textContent = "No reviews available.";
    authorEl.textContent = "";
    timeEl.textContent = "";
    countEl.textContent = `${reviewTotal} Customer Reviews`;
    return;
  }

  const r = reviews[currentReviewIndex];
  textEl.textContent = r.text || "";
  authorEl.textContent = r.author_name || "";
  timeEl.textContent = r.relative_time_description || "";

  if (overallRating) {
    const stars = Math.round(overallRating);
    ratingEl.textContent = "★".repeat(stars);
  } else {
    ratingEl.textContent = "";
  }

  countEl.textContent = `${reviewTotal} Customer Reviews`;

  const nextIndex = (currentReviewIndex + 1) % reviews.length;
  if (reviewVersion) {
    storeNextIndex(reviewVersion, nextIndex);
  }
  currentReviewIndex = nextIndex;
}

async function loadReviews() {
  try {
    const res = await fetch("reviews.php");
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();

    reviews = data.reviews || [];
    reviewTotal = data.total ?? reviews.length;
    overallRating = data.rating ?? null;
    // Keep the version stable unless the list itself changes so we don't
    // reset progress every time new data is fetched. Use a simple signature
    // based on the review count plus the first/last dates (ordered newest →
    // oldest in reviews.php) instead of the generated_at timestamp.
    const newestDate = reviews[0]?.review_date ?? "";
    const oldestDate = reviews[reviews.length - 1]?.review_date ?? "";
    reviewVersion = `${reviews.length}-${newestDate}-${oldestDate}`;

    if (reviews.length) {
      currentReviewIndex = loadStoredIndex(reviewVersion, reviews.length);
      showCurrentReview();
    } else {
      document.getElementById("reviewText").textContent =
        "No reviews available.";
      document.getElementById("reviewsCount").textContent =
        `${reviewTotal} Customer Reviews`;
    }
  } catch (err) {
    console.error("Reviews error:", err);
    document.getElementById("reviewText").textContent =
      "Unable to load reviews.";
    document.getElementById("reviewAuthor").textContent = "";
    document.getElementById("reviewTime").textContent = "";
  }
}

setupReviewQr();
loadReviews();
setInterval(showCurrentReview, 2 * 60 * 1000); // rotate every 2 min
setInterval(loadReviews, 60 * 60 * 1000); // refresh list hourly
