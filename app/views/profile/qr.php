<h2>Код для начисления штампов</h2>
<div class="card">
  <p>Покажите бариста короткий код. Код состоит из 5 цифр и автоматически обновляется каждые 5–7 минут.</p>

  <label>Ваш короткий код</label>
  <input readonly value="<?= htmlspecialchars((string)$shortCode) ?>" style="font-size:32px;font-weight:800;letter-spacing:6px;text-align:center">
  <button class="btn" type="button" data-copy-target="input">Копировать код</button>

  <p class="muted" style="margin-top:8px">Если код устарел, просто обновите страницу — будет выдан новый актуальный код.</p>
</div>
