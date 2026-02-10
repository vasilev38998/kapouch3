<h2>Начисление по коду клиента</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string)$idem) ?>">

  <label>Код клиента (User ID)</label>
  <input name="user_id" value="<?= htmlspecialchars((string)$user_id) ?>" required>

  <label>Сумма заказа (для расчёта кэшбэка)</label>
  <input name="total_amount" type="number" min="1" step="0.01" required>

  <label>Сколько начислить штампов</label>
  <input name="stamps" type="number" min="0" step="1" value="1" required>

  <button class="btn">Начислить</button>
</form>
