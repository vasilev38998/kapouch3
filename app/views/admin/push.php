<h2>Push / уведомления</h2>
<section class="card fade-in">
  <div>Подписанных устройств: <strong><?= (int)$subscriptions ?></strong></div>
  <div class="muted" style="margin-top:6px">Аудитория:</div>
  <div class="row" style="margin-top:6px">
    <?php foreach(($audienceStats ?? []) as $s): ?>
      <div class="chip"><?= htmlspecialchars((string)$s['role']) ?>: <?= (int)$s['c'] ?></div>
    <?php endforeach; ?>
  </div>
  <p class="muted">Рассылка создаёт кампанию и доставляет уведомление всем пользователям в приложении (poll + Notification API).</p>
</section>
<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <label>Заголовок</label><input name="title" required>
  <label>Текст</label><textarea name="body" required></textarea>
  <label>URL перехода</label><input name="url" value="/profile">
  <label>Кому отправить</label>
  <select name="audience">
    <option value="all">Всем</option>
    <option value="user">Только user</option>
    <option value="barista">Только barista</option>
    <option value="manager">Только manager</option>
    <option value="admin">Только admin</option>
  </select>
  <button class="btn">Отправить</button>
</form>
<section class="card fade-in">
  <h3>Последние кампании</h3>
  <?php foreach($campaigns as $c): ?>
    <div>#<?= (int)$c['id'] ?> · <strong><?= htmlspecialchars($c['title']) ?></strong> · <?= htmlspecialchars($c['body']) ?> · <?= htmlspecialchars($c['created_at']) ?></div>
  <?php endforeach; ?>
</section>
