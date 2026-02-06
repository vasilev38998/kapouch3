<h2>Мой QR-токен</h2>
<div class="card">
  <p>Токен для сканирования:</p>
  <div class="qr-mock" aria-label="QR token visual"><?= htmlspecialchars(substr(hash('sha256', $token), 0, 36)) ?></div>
  <textarea rows="4" readonly><?= htmlspecialchars($token) ?></textarea>
  <p class="muted">В staff-сканере поддержан camera decode через BarcodeDetector (если доступно), иначе вставка токена вручную.</p>
</div>
