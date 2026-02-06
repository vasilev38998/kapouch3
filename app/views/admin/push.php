<h2>Push / уведомления</h2>
<section class="card fade-in">
  <div>Подписанных устройств: <strong><?= (int)$subscriptions ?></strong></div>
  <div class="muted" style="margin-top:6px">Аудитория:</div>
  <div class="row" style="margin-top:6px">
    <?php foreach(($audienceStats ?? []) as $s): ?>
      <div class="chip"><?= htmlspecialchars((string)$s['role']) ?>: <?= (int)$s['c'] ?></div>
    <?php endforeach; ?>
  </div>
  <p class="muted">Поддерживаются мгновенные и отложенные кампании, плюс шаблоны.</p>
  <div class="chip">Web Push backend: <?= !empty($webPushAvailable) ? 'готов' : 'не настроен' ?></div>
  <div class="row" style="margin-top:6px">
    <div class="chip">Активные за 15 мин: <?= (int)($subscriptionsActive15m ?? 0) ?></div>
    <div class="chip">Разрешили уведомления: <?= (int)($subscriptionsGranted ?? 0) ?></div>
    <div class="chip">Запретили уведомления: <?= (int)($subscriptionsDenied ?? 0) ?></div>
  </div>
  <p class="muted">Важно: текущая реализация — web notifications + polling (не настоящий Web Push). Для доставки пользователь должен быть в приложении/вкладке. Для фоновых push (когда сайт закрыт) нужен Web Push (VAPID + push event в service worker).</p>

</section>

<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="action" value="send">

  <label>Шаблон (необязательно)</label>
  <select id="pushTemplateSelect">
    <option value="">— без шаблона —</option>
    <?php foreach(($templates ?? []) as $t): ?>
      <option value="<?= (int)$t['id'] ?>" data-title="<?= htmlspecialchars((string)$t['title'], ENT_QUOTES) ?>" data-body="<?= htmlspecialchars((string)$t['body'], ENT_QUOTES) ?>" data-url="<?= htmlspecialchars((string)$t['url'], ENT_QUOTES) ?>">
        <?= htmlspecialchars((string)$t['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label>Заголовок</label><input id="pushTitle" name="title" required>
  <label>Текст</label><textarea id="pushBody" name="body" required></textarea>
  <label>URL перехода</label><input id="pushUrl" name="url" value="/profile">
  <label>Кому отправить</label>
  <select name="audience">
    <option value="all">Всем</option>
    <option value="user">Только user</option>
    <option value="barista">Только barista</option>
    <option value="manager">Только manager</option>
    <option value="admin">Только admin</option>
  </select>
  <label>Отложить до (опционально)</label>
  <input name="schedule_at" type="datetime-local">

  <button class="btn">Создать кампанию</button>
</form>

<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="action" value="template_create">
  <h3>Новый шаблон</h3>
  <label>Название шаблона</label><input name="template_name" required>
  <label>Заголовок</label><input name="template_title" required>
  <label>Текст</label><textarea name="template_body" required></textarea>
  <label>URL</label><input name="template_url" value="/profile">
  <button class="btn ghost">Сохранить шаблон</button>
</form>

<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="action" value="dispatch_due">
  <button class="btn">Отправить все запланированные сейчас</button>
</form>

<section class="card fade-in">
  <h3>Последние кампании</h3>
  <?php foreach($campaigns as $c): ?>
    <div>#<?= (int)$c['id'] ?> · <strong><?= htmlspecialchars($c['title']) ?></strong> · <?= htmlspecialchars($c['body']) ?> · аудитория: <?= htmlspecialchars((string)($c['target_role'] ?? 'all')) ?> · статус: <?= htmlspecialchars((string)($c['status'] ?? 'sent')) ?> · отправлено: <?= (int)($c['sent_count'] ?? $c['recipients_count'] ?? 0) ?> · прочитано: <?= (int)($c['read_count'] ?? 0) ?> · клики: <?= (int)($c['clicks_count'] ?? 0) ?> · <?= htmlspecialchars((string)($c['sent_at'] ?? $c['created_at'])) ?></div>
  <?php endforeach; ?>
</section>

<script>
  (() => {
    const select = document.getElementById('pushTemplateSelect');
    const title = document.getElementById('pushTitle');
    const body = document.getElementById('pushBody');
    const url = document.getElementById('pushUrl');
    if (!select || !title || !body || !url) return;
    select.addEventListener('change', () => {
      const o = select.options[select.selectedIndex];
      if (!o || !o.value) return;
      title.value = o.dataset.title || '';
      body.value = o.dataset.body || '';
      url.value = o.dataset.url || '/profile';
    });
  })();
</script>
