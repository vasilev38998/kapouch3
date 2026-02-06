<h2>Создать заказ</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string)$idem) ?>">
  <label>User ID</label><input name="user_id" value="<?= htmlspecialchars((string)$user_id) ?>" required>
  <label>Сумма</label><input name="total_amount" type="number" min="1" step="0.01" required>
  <label>Списать cashback</label><input name="cashback_spend" type="number" min="0" step="0.01" value="0">
  <label>Локация ID</label><input name="location_id" type="number">
  <label>Категория</label><input name="category">
  <label>Промокод</label><input name="promocode">
  <label>Заметка</label><input name="note">
  <button class="btn">Провести заказ</button>
</form>
