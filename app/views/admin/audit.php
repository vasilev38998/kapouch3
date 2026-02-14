<h2>Аудит</h2>
<div class="card" style="overflow:auto;max-height:70vh">
  <?php foreach($rows as $r): ?><div><?= htmlspecialchars($r['created_at']) ?> · <?= htmlspecialchars($r['action']) ?> · <?= htmlspecialchars($r['status']) ?> · <?= htmlspecialchars((string)$r['message']) ?></div><?php endforeach; ?>
</div>
