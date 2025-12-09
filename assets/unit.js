async function loadUnitOfDay() {
  const titleEl       = document.getElementById("uotdTitle");
  const stockEl       = document.getElementById("uotdStock");
  const img1El        = document.getElementById("uotdImage1");
  const img2El        = document.getElementById("uotdImage2");
  const priceEl       = document.getElementById("uotdPrice");
  const paymentLineEl = document.getElementById("uotdPaymentLine");
  const financeMetaEl = document.getElementById("uotdFinanceMeta");
  const specsEl       = document.getElementById("uotdSpecs");
  const qrWrapEl      = document.getElementById("uotdQr");
  const qrImgEl       = document.getElementById("uotdQrImage");

  function setError(msg) {
    if (titleEl)       titleEl.textContent       = "Unit of the Day";
    if (priceEl)       priceEl.textContent       = "--";
    if (paymentLineEl) paymentLineEl.textContent = msg;
    if (qrWrapEl)      qrWrapEl.style.display    = "none";
  }

  try {
    const resp = await fetch("unit-of-day.php?cb=" + Date.now());
    if (!resp.ok) throw new Error("HTTP " + resp.status);

    const data = await resp.json();
    if (data.error) {
      console.error("Unit-of-day error:", data.message);
      setError("Unable to load unit.");
      return;
    }

    const {
      title,
      stock,
      price,
      price_formatted,
      image1,
      image2,
      year,
      make,
      model,
      length,      // we won't use this pill now
      slides,
      sleeps,
      detail_url,
    } = data;

    if (titleEl) titleEl.textContent = title || "Unit of the Day";
    if (stockEl) stockEl.textContent = stock ? `Stock #${stock}` : "";

    // ---- Images (show only tech drawing / second image) ----
    if (img1El && img1El.parentElement) {
      img1El.parentElement.style.display = "none"; // hide extra image slot
    }

    if (img2El && img2El.parentElement) {
      const techDrawingImage = image2 || image1; // show only tech_drawing image (image #2 preferred)
      if (techDrawingImage) {
        img2El.src = techDrawingImage;
        img2El.alt = "Unit technical drawing";
        img2El.parentElement.style.display = "";
      } else {
        img2El.parentElement.style.display = "none";
      }
    }

    // ---- Price number ----
    const sale =
      typeof price === "number"
        ? price
        : parseFloat(String(price || "").replace(/[^0-9.]/g, "")) || 0;

    if (priceEl) {
      if (sale > 0) {
        priceEl.textContent =
          "$" + sale.toLocaleString("en-US", { maximumFractionDigits: 0 });
      } else if (price_formatted) {
        priceEl.textContent = price_formatted;
      } else {
        priceEl.textContent = "See dealer for price";
      }
    }

    // ---- Finance + payment ----
    if (sale > 0 && paymentLineEl && financeMetaEl) {
      const calc = calculatePayment(sale);

      paymentLineEl.innerHTML = `
        Payments as low as
        <span class="uotd-payment-amount">
          ${formatMoney(calc.payment)}/mo
        </span>
      `;

      financeMetaEl.textContent =
        `Estimated with 10% down on a ${calc.years}-year term at 7.99% APR, ` +
        `including approx. 6% tax.`;
    } else if (paymentLineEl) {
      paymentLineEl.textContent =
        "Financing available — see dealer for details.";
    }

    // ---- Specs pills (no Length pill) ----
    if (specsEl) {
      const pills = [];
      const nameBits = [];
      if (year)  nameBits.push(year);
      if (make)  nameBits.push(make);
      if (model) nameBits.push(model);
      if (nameBits.length) pills.push(nameBits.join(" "));

      // no length pill
      if (slides) pills.push(`Slides: ${slides}`);
      if (sleeps) pills.push(`Sleeps: ${sleeps}`);

      specsEl.innerHTML = pills
        .map((text) => `<span class="uotd-spec-pill">${text}</span>`)
        .join("");
    }

    // ---- QR code for detail_url ----
    if (qrWrapEl && qrImgEl) {
      if (detail_url) {
        const base = "https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=";
        qrImgEl.src = base + encodeURIComponent(detail_url);
        qrWrapEl.style.display = "";
      } else {
        qrWrapEl.style.display = "none";
      }
    }
  } catch (err) {
    console.error("Unit-of-day fetch error:", err);
    setError("Unable to load unit.");
  }
}

function formatMoney(amount) {
  return (
    "$" +
    amount.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

// Finance rules:
// Tax = 6%
// Subtotal = sale + tax
// Down = 10% of subtotal
// Financed = subtotal - down
// <= 25k -> 7.99% for 12 years, else 15 years
function calculatePayment(salePrice) {
  const taxRate = 0.06;
  const downPct = 0.10;

  const subtotal    = salePrice * (1 + taxRate);
  const downPayment = subtotal * downPct;
  const financed    = subtotal - downPayment;

  const years = financed <= 25000 ? 12 : 15;
  const apr   = 0.0799;
  const r     = apr / 12;
  const n     = years * 12;

  const payment = financed * r / (1 - Math.pow(1 + r, -n));

  return { subtotal, downPayment, financed, years, payment };
}

loadUnitOfDay();
// refresh once per 24 hours
setInterval(loadUnitOfDay, 24 * 60 * 60 * 1000);
