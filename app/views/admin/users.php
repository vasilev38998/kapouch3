<h2>Пользователи и роли</h2>
<div class="card">
<?php foreach($users as $u): ?>
  <form method="post" style="margin:8px 0">
    <input type="hidden" name="_csrf" value="<?= \App\Lib\Csrf::token() ?>">
    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
    <?= (int)$u['id'] ?> · <?= htmlspecialchars($u['phone']) ?>
    <select name="role">
      <?php foreach(['user','barista','manager','admin'] as $r): ?><option <?= $u['role']===$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?>
    </select>
    <button>OK</button>
  </form>
<?php endforeach; ?>
</div>
