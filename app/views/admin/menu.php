<h2>Меню кофейни</h2>
<form method="post" class="card fade-in">
  <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
  <input type="hidden" name="action" value="create">
  <label>Название</label><input name="name" required>
  <label>Категория</label><input name="category" value="Напитки" required>
  <label>Цена (₽)</label><input name="price" type="number" min="0" step="0.01" required>
  <label>Описание (опционально)</label><textarea name="description"></textarea>
  <label>Ссылка на картинку (опционально)</label><input name="image_url" placeholder="https://...">
  <label>Порядок сортировки</label><input name="sort_order" type="number" value="100">
  <label><input type="checkbox" name="is_active" checked style="width:auto"> Активна</label>
  <label><input type="checkbox" name="is_sold_out" style="width:auto"> В стоп-листе</label>
  <button class="btn">Добавить позицию</button>
</form>

<section class="card fade-in" style="overflow:auto">
  <h3>Текущие позиции</h3>
  <table style="width:100%;border-collapse:collapse;font-size:14px">
    <thead><tr><th>ID</th><th>Категория</th><th>Название</th><th>Цена</th><th>Статус</th><th></th></tr></thead>
    <tbody>
      <?php foreach (($items ?? []) as $i): ?>
        <tr>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= (int)$i['id'] ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= htmlspecialchars((string)$i['category']) ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= htmlspecialchars((string)$i['name']) ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= number_format((float)$i['price'], 2, '.', ' ') ?> ₽</td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f"><?= (int)$i['is_active'] ? 'активна' : 'выключена' ?><?php if((int)$i['is_sold_out']===1): ?> · стоп-лист<?php endif; ?></td>
          <td style="padding:6px;border-bottom:1px dashed #f0d56f;white-space:nowrap">
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><button class="btn ghost" type="submit">On/Off</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="sold_out"><input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>"><button class="btn ghost" type="submit">Стоп</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section class="card fade-in">
  <h3>Группы модификаторов</h3>
  <form method="post" class="grid-2">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <input type="hidden" name="action" value="mod_group_create">
    <label>Товар
      <select name="menu_item_id" required>
        <?php foreach (($items ?? []) as $i): ?><option value="<?= (int)$i['id'] ?>">#<?= (int)$i['id'] ?> · <?= htmlspecialchars((string)$i['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Название группы<input name="group_name" placeholder="Сироп" required></label>
    <label>Тип выбора
      <select name="selection_mode">
        <option value="single">Один вариант</option>
        <option value="multi">Несколько вариантов</option>
      </select>
    </label>
    <label>Сортировка<input name="sort_order" type="number" value="100"></label>
    <label><input type="checkbox" name="is_required" style="width:auto"> Обязательный выбор</label>
    <div><button class="btn">Добавить группу</button></div>
  </form>

  <div style="margin-top:10px">
    <?php foreach (($groups ?? []) as $g): ?>
      <div class="favorites-summary" style="margin-bottom:8px">
        <strong>#<?= (int)$g['id'] ?> · <?= htmlspecialchars((string)$g['name']) ?></strong>
        <div class="muted">Товар #<?= (int)$g['menu_item_id'] ?> · <?= htmlspecialchars((string)$g['selection_mode']) ?><?= (int)$g['is_required']===1?' · обязателен':'' ?><?= (int)$g['is_active']===1?' · активен':' · выключен' ?></div>
        <form method="post" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="mod_group_toggle"><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>"><button class="btn ghost" type="submit">On/Off</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Удалить группу и её модификаторы?')">
          <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="mod_group_delete"><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>"><button class="btn ghost" type="submit">Удалить</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="card fade-in">
  <h3>Модификаторы</h3>
  <form method="post" class="grid-2">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <input type="hidden" name="action" value="mod_create">
    <label>Группа
      <select name="group_id" required>
        <?php foreach (($groups ?? []) as $g): ?><option value="<?= (int)$g['id'] ?>">#<?= (int)$g['id'] ?> · <?= htmlspecialchars((string)$g['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Название<input name="modifier_name" placeholder="Ваниль" required></label>
    <label>Цена (+ ₽)<input name="price_delta" type="number" min="0" step="0.01" value="0"></label>
    <label>Сортировка<input name="sort_order" type="number" value="100"></label>
    <div><button class="btn">Добавить модификатор</button></div>
  </form>

  <div style="margin-top:10px">
    <?php foreach (($modifiers ?? []) as $m): ?>
      <div class="favorites-summary" style="margin-bottom:8px">
        <strong>#<?= (int)$m['id'] ?> · <?= htmlspecialchars((string)$m['name']) ?></strong>
        <div class="muted">Группа #<?= (int)$m['group_id'] ?> · +<?= number_format((float)$m['price_delta'],2,'.',' ') ?> ₽<?= (int)$m['is_sold_out']===1?' · стоп-лист':'' ?><?= (int)$m['is_active']===1?' · активен':' · выключен' ?></div>
        <form method="post" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="mod_toggle"><input type="hidden" name="modifier_id" value="<?= (int)$m['id'] ?>"><button class="btn ghost" type="submit">On/Off</button>
        </form>
        <form method="post" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="mod_sold_out"><input type="hidden" name="modifier_id" value="<?= (int)$m['id'] ?>"><button class="btn ghost" type="submit">Стоп</button>
        </form>
        <form method="post" style="display:inline" onsubmit="return confirm('Удалить модификатор?')">
          <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>"><input type="hidden" name="action" value="mod_delete"><input type="hidden" name="modifier_id" value="<?= (int)$m['id'] ?>"><button class="btn ghost" type="submit">Удалить</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</section>
