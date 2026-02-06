<h2>Мой QR-код</h2>
<div class="card">
  <p>Покажите QR бариста для быстрого поиска в системе.</p>
  <div class="qr-wrap">
    <img class="qr-img" alt="User QR" src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= rawurlencode((string)$shortCode) ?>">
  </div>

  <label>Короткий код (быстрый ввод)</label>
  <input readonly value="<?= htmlspecialchars((string)$shortCode) ?>">
  <button class="btn" type="button" data-copy-target="input">Копировать короткий код</button>

  <details style="margin-top:10px">
    <summary>Показать длинный токен (резервный вариант)</summary>
    <label>Токен</label>
    <textarea rows="4" readonly><?= htmlspecialchars((string)$token) ?></textarea>
    <button class="btn ghost" type="button" data-copy-target="textarea">Копировать токен</button>
  </details>
</div>
