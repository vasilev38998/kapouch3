<h2>Data Manager (все таблицы)</h2>
<section class="card fade-in">
  <form method="get" class="row" style="align-items:end">
    <label style="flex:1">Таблица
      <select name="table">
        <?php foreach($tables as $t): ?><option value="<?= htmlspecialchars($t) ?>" <?= $table===$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label style="flex:1">Поиск
      <input name="q" value="<?= htmlspecialchars((string)($search ?? '')) ?>" placeholder="часть текста">
    </label>
    <label>Лимит
      <input name="limit" type="number" min="20" max="500" value="<?= (int)($limit ?? 200) ?>" style="width:110px">
    </label>
    <button class="btn">Открыть</button>
    <a class="btn ghost" href="/admin/data?table=<?= urlencode($table) ?>&q=<?= urlencode((string)($search ?? '')) ?>&limit=<?= (int)($limit ?? 200) ?>&export=csv">CSV</a>
  </form>
  <?php if(!($supportsSearch ?? false)): ?><p class="muted">Для этой таблицы поиск по тексту не доступен.</p><?php endif; ?>
</section>

<section class="card fade-in">
  <h3>Добавить запись</h3>
  <form method="post" action="/admin/data/save">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
    <input type="hidden" name="action" value="upsert">
    <?php foreach($columns as $c): ?>
      <label><?= htmlspecialchars($c['Field']) ?> <small>(<?= htmlspecialchars($c['Type']) ?>)</small></label>
      <input name="<?= htmlspecialchars($c['Field']) ?>" <?= (($c['Null'] ?? '')==='NO' && ($c['Extra'] ?? '')!=='auto_increment')?'required':'' ?>>
    <?php endforeach; ?>
    <button class="btn">Сохранить</button>
  </form>
</section>

<section class="card fade-in" style="overflow:auto">
  <h3>Записи: <?= htmlspecialchars($table) ?> <small class="muted">(<?= count($rows) ?>)</small></h3>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr><?php foreach($columns as $c): ?><th style="text-align:left;border-bottom:1px solid #e4be5f;padding:6px"><?= htmlspecialchars($c['Field']) ?></th><?php endforeach; ?><th></th></tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <?php foreach($columns as $c): ?><td style="border-bottom:1px dashed #f0d56f;padding:6px"><?= htmlspecialchars((string)($r[$c['Field']] ?? '')) ?></td><?php endforeach; ?>
          <td style="white-space:nowrap;padding:6px">
            <?php if($pk): ?>
            <form method="post" action="/admin/data/save" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
              <input type="hidden" name="table" value="<?= htmlspecialchars($table) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="<?= htmlspecialchars($pk) ?>" value="<?= htmlspecialchars((string)$r[$pk]) ?>">
              <button class="btn ghost" onclick="return confirm('Удалить запись?')">Удалить</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
