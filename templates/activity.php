<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t('activity.title'), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { --bg:#f8fafc; --panel:#fff; --text:#111827; --muted:#4b5563; --accent:#0f766e; --danger:#b91c1c; --ok:#166534; --border:#d1d5db; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:"IBM Plex Sans","Segoe UI",sans-serif; background:linear-gradient(150deg,#ecfeff 0%,#f8fafc 40%,#eff6ff 100%); color:var(--text); }
    .skip-link { position:absolute; left:-9999px; top:0; background:#111827; color:#fff; padding:8px; }
    .skip-link:focus { left:12px; top:12px; }
    header { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border); background:#fff; }
    h1 { margin:0; font-size:1.25rem; }
    .container { max-width:1200px; margin:0 auto; padding:22px; display:grid; gap:18px; }
    .panel { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:16px; }
    .flash { padding:10px 12px; border-radius:10px; margin-bottom:12px; }
    .flash.success { background:#dcfce7; color:var(--ok); }
    .flash.error { background:#fee2e2; color:var(--danger); }
    table { width:100%; border-collapse:collapse; }
    th, td { border-bottom:1px solid #e5e7eb; text-align:left; padding:10px; vertical-align:top; font-size:.9rem; }
    input, select, button { padding:10px 12px; border-radius:10px; border:1px solid var(--border); font:inherit; }
    button { cursor:pointer; background:var(--accent); color:#fff; border:none; font-weight:600; }
    .nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .nav a, .nav button { text-decoration:none; background:#fff; border:1px solid var(--border); padding:8px 10px; border-radius:10px; color:#111827; width:auto; }
    .pill { display:inline-block; padding:4px 8px; border-radius:999px; background:#eef2ff; font-size:.8rem; }
    .stats-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:18px; }
    .stat-card { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:16px; text-align:center; }
    .stat-card h3 { margin:0 0 4px; font-size:.8rem; color:var(--muted); }
    .stat-card .value { font-size:1.8rem; font-weight:700; color:var(--accent); }
    .filters { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-bottom:12px; }
    .filters label { font-size:.85rem; color:var(--muted); }
    @media (max-width: 720px) { .stats-grid { grid-template-columns:1fr 1fr; } .filters { flex-direction:column; } }
  </style>
</head>
<body>
<a class="skip-link" href="#main"><?= htmlspecialchars($t('a11y.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>
<header>
  <h1><?= htmlspecialchars($t('activity.title'), ENT_QUOTES, 'UTF-8') ?></h1>
  <nav class="nav">
    <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
    <form method="post" action="/logout" style="margin:0"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button type="submit" style="width:auto"><?= htmlspecialchars($t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></button></form>
  </nav>
</header>
<main id="main" class="container">
<?php
$stats = is_array($stats ?? null) ? $stats : ['total' => 0, 'today' => 0, 'top_users' => [], 'top_actions' => []];
$logs = is_array($logs ?? null) ? $logs : [];
$filters = is_array($filters ?? null) ? $filters : ['user_id' => '', 'action' => '', 'date_from' => '', 'date_to' => ''];
$flash = $flash ?? null;
?>
<?php if ($flash): ?><div class="flash <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<div class="stats-grid">
  <div class="stat-card"><h3><?= htmlspecialchars($t('activity.total'), ENT_QUOTES, 'UTF-8') ?></h3><div class="value"><?= (int)$stats['total'] ?></div></div>
  <div class="stat-card"><h3><?= htmlspecialchars($t('activity.today'), ENT_QUOTES, 'UTF-8') ?></h3><div class="value"><?= (int)$stats['today'] ?></div></div>
</div>

<div class="panel">
  <h2 style="margin:0 0 10px"><?= htmlspecialchars($t('activity.top_actions'), ENT_QUOTES, 'UTF-8') ?></h2>
  <?php foreach ($stats['top_actions'] ?? [] as $ta): ?>
    <span class="pill"><?= htmlspecialchars((string)$ta['action'], ENT_QUOTES, 'UTF-8') ?> (<?= (int)$ta['action_count'] ?>)</span>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2 style="margin:0 0 10px"><?= htmlspecialchars($t('activity.title'), ENT_QUOTES, 'UTF-8') ?></h2>

  <form method="get" action="/activity" class="filters">
    <div><label><?= htmlspecialchars($t('activity.action'), ENT_QUOTES, 'UTF-8') ?></label><br><input type="text" name="action" value="<?= htmlspecialchars($filters['action'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. auth.login"></div>
    <div><label><?= htmlspecialchars($t('activity.date'), ENT_QUOTES, 'UTF-8') ?> (from)</label><br><input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <div><label><?= htmlspecialchars($t('activity.date'), ENT_QUOTES, 'UTF-8') ?> (to)</label><br><input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>"></div>
    <div><button type="submit" style="width:auto;margin-top:18px"><?= htmlspecialchars($t('common.search'), ENT_QUOTES, 'UTF-8') ?></button></div>
  </form>

  <table>
    <thead><tr>
      <th><?= htmlspecialchars($t('activity.date'), ENT_QUOTES, 'UTF-8') ?></th>
      <th><?= htmlspecialchars($t('activity.user'), ENT_QUOTES, 'UTF-8') ?></th>
      <th><?= htmlspecialchars($t('activity.action'), ENT_QUOTES, 'UTF-8') ?></th>
      <th><?= htmlspecialchars($t('activity.ip'), ENT_QUOTES, 'UTF-8') ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= htmlspecialchars((string)($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($log['display_name'] ?? $log['user_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><span class="pill"><?= htmlspecialchars((string)($log['action'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></td>
        <td><?= htmlspecialchars((string)($log['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($logs === []): ?>
      <tr><td colspan="4" style="text-align:center;color:var(--muted)"><?= htmlspecialchars($t('scan.no_results'), ENT_QUOTES, 'UTF-8') ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
</main>
<footer style="text-align:center;padding:12px;color:var(--muted);font-size:.8rem"><?= htmlspecialchars($t('footer.powered'), ENT_QUOTES, 'UTF-8') ?></footer>
</body>
</html>
