<h2>Мой QR-код</h2>
<div class="card">
  <p>Покажите QR бариста для быстрого поиска в системе.</p>
  <div class="qr-wrap">
    <img class="qr-img" alt="User QR" src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= rawurlencode($token) ?>">
  </div>
  <label>Токен (резервный вариант)</label>
  <textarea rows="4" readonly><?= htmlspecialchars($token) ?></textarea>
  <button class="btn" type="button" data-copy-target="textarea">Копировать токен</button>
</div>
