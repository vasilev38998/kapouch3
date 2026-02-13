<h2>Дата рождения</h2>
<form method="post" class="card">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="date" name="birthday" value="<?= htmlspecialchars((string)($user['birthday'] ?? '')) ?>">
  <button class="btn">Сохранить</button>
</form>
