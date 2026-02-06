<h2>Меню кофейни</h2>
<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="action" value="create">
  <label>Название</label><input name="name" required>
  <label>Цена (₽)</label><input name="price" type="number" min="0" step="0.01" required>
  <label>Описание (опционально)</label><textarea name="description"></textarea>
  <label>Ссылка на картинку (опционально)</label><input name="image_url" placeholder="https://...">
  <label>Порядок сортировки</label><input name="sort_order" type="number" value="100">
  <label><input type="checkbox" name="is_active" checked style="width:auto"> Активна</label>
  <button class="btn">Добавить позицию</button>
</form>

<section class="card fade-in" style="overflow:auto">
  <h3>Текущие позиции</h3>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead>
      <tr>
        <th style="text-align:left;border-bottom:1px solid #e4be5f;padding:6px">ID</th>
        <th style="text-align:left;border-bottom:1px solid #e4be5f;padding:6px">Название</th>
        <th style="text-align:left;border-bottom:1px solid #e4be5f;padding:6px">Цена</th>
        <th style="text-align:left;border-bottom:1px solid #e4be5f;padding:6px">Статус</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($items ?? []) as $i): ?>
        <tr>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= (int)$i['id'] ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= htmlspecialchars((string)$i['name']) ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= number_format((float)$i['price'], 2, '.', ' ') ?> ₽</td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= (int)$i['is_active'] ? 'активна' : 'выключена' ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f;white-space:nowrap">
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
              <button class="btn ghost">Вкл/Выкл</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
              <button class="btn ghost" onclick="return confirm('Удалить позицию?')">Удалить</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
