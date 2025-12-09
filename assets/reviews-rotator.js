let reviews = [];
let currentReviewIndex = 0;

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
    return;
  }

  const r = reviews[currentReviewIndex];
  textEl.textContent = r.text || "";
  authorEl.textContent = r.author_name || "";
  timeEl.textContent = r.relative_time_description || "";

  if (r._overall_rating) {
    const stars = Math.round(r._overall_rating);
    ratingEl.textContent = "★".repeat(stars);
  }
  if (r._user_ratings_total) {
    countEl.textContent = `${r._user_ratings_total} Google reviews`;
  }

  currentReviewIndex = (currentReviewIndex + 1) % reviews.length;
}

async function loadReviews() {
  try {
    const res = await fetch("reviews.php");
    if (!res.ok) throw new Error("HTTP " + res.status);
    const data = await res.json();
    reviews = data.reviews || [];
    if (reviews.length) {
      currentReviewIndex = 0;
      showCurrentReview();
    } else {
      document.getElementById("reviewText").textContent =
        "No reviews available.";
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
setInterval(showCurrentReview, 2 * 60 * 1000);   // rotate every 2 min
setInterval(loadReviews, 60 * 60 * 1000);        // refresh list hourly
