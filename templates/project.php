<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { --bg:#f8fafc; --panel:#fff; --text:#111827; --muted:#4b5563; --accent:#1d4ed8; --border:#d1d5db; --ok:#166534; --danger:#b91c1c; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:"IBM Plex Sans","Segoe UI",sans-serif; color:var(--text); background:linear-gradient(160deg,#eff6ff 0%,#f8fafc 70%); }
    .skip-link { position:absolute; left:-9999px; }
    .skip-link:focus { left:12px; top:12px; background:#111827; color:#fff; padding:8px; }
    header { padding:18px 22px; border-bottom:1px solid var(--border); background:#fff; display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap; }
    main { max-width:1200px; margin:0 auto; padding:20px; display:grid; gap:16px; }
    .panel { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:14px; }
    .grid { display:grid; grid-template-columns:1.2fr .8fr; gap:16px; }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    table { width:100%; border-collapse:collapse; }
    th,td { border-bottom:1px solid #e5e7eb; text-align:left; padding:9px; }
    input, textarea, select, button { width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); font:inherit; }
    textarea { min-height:120px; }
    button { border:none; cursor:pointer; background:var(--accent); color:#fff; font-weight:600; }
    .flash.success { color:var(--ok); }
    .flash.error { color:var(--danger); }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .tabs { display:flex; gap:8px; flex-wrap:wrap; }
    .tabs a { text-decoration:none; border:1px solid var(--border); padding:6px 10px; border-radius:999px; color:#111827; }
    @media (max-width: 920px) { .grid { grid-template-columns:1fr; } .row { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<a href="#main" class="skip-link"><?= htmlspecialchars($t('a11y.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>
<header>
  <div>
    <strong><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?></strong>
    <div class="mono"><?= htmlspecialchars($project['root_domain'], ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <nav class="tabs" aria-label="Breadcrumb">
    <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/projects/<?= (int) $project['id'] ?>"><?= htmlspecialchars($t('nav.projects'), ENT_QUOTES, 'UTF-8') ?></a>
  </nav>
</header>
<main id="main">
  <?php if (!empty($flash)): ?>
    <div class="flash <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <section class="grid">
    <article class="panel">
      <h2><?= htmlspecialchars($t('scan.new_title'), ENT_QUOTES, 'UTF-8') ?></h2>
      <form method="post" action="/projects/<?= (int) $project['id'] ?>/scans">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="row">
          <div>
            <label><?= htmlspecialchars($t('project.root_domain'), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" name="root_domain" value="<?= htmlspecialchars($project['root_domain'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div>
            <label><?= htmlspecialchars($t('scan.provider'), ENT_QUOTES, 'UTF-8') ?></label>
            <select name="provider"><option value="moz">Moz</option></select>
          </div>
        </div>
        <label><?= htmlspecialchars($t('scan.urls'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea name="urls" required></textarea>
        <button type="submit"><?= htmlspecialchars($t('scan.submit'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </article>

    <aside class="panel">
      <h2><?= htmlspecialchars($t('project.members'), ENT_QUOTES, 'UTF-8') ?></h2>
      <table>
        <thead><tr><th>Email</th><th>Role</th></tr></thead>
        <tbody>
          <?php foreach ($members as $member): ?>
            <tr><td><?= htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($member['role'], ENT_QUOTES, 'UTF-8') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="/projects/<?= (int) $project['id'] ?>/members">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Role</label>
        <select name="role"><option value="viewer">viewer</option><option value="editor">editor</option><option value="admin">admin</option></select>
        <button type="submit"><?= htmlspecialchars($t('common.submit'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </aside>
  </section>

  <section class="grid">
    <article class="panel">
      <h2><?= htmlspecialchars($t('nav.schedules'), ENT_QUOTES, 'UTF-8') ?></h2>
      <table>
        <thead><tr><th><?= htmlspecialchars($t('schedule.name'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('schedule.rrule'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('schedule.next_run'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
        <tbody>
          <?php foreach ($schedules as $schedule): ?>
            <tr>
              <td><?= htmlspecialchars($schedule['name'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="mono"><?= htmlspecialchars($schedule['rrule'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $schedule['next_run_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="/projects/<?= (int) $project['id'] ?>/schedules">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="row">
          <div><label><?= htmlspecialchars($t('schedule.name'), ENT_QUOTES, 'UTF-8') ?></label><input type="text" name="name" required></div>
          <div><label><?= htmlspecialchars($t('schedule.timezone'), ENT_QUOTES, 'UTF-8') ?></label><input type="text" name="timezone" value="UTC"></div>
        </div>
        <label><?= htmlspecialchars($t('schedule.rrule'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="rrule" value="FREQ=HOURLY;INTERVAL=24" required>
        <label><?= htmlspecialchars($t('project.root_domain'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="root_domain" value="<?= htmlspecialchars($project['root_domain'], ENT_QUOTES, 'UTF-8') ?>" required>
        <label><?= htmlspecialchars($t('scan.urls'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea name="targets" required></textarea>
        <button type="submit"><?= htmlspecialchars($t('schedule.create'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </article>

    <aside class="panel">
      <h2><?= htmlspecialchars($t('project.notifications'), ENT_QUOTES, 'UTF-8') ?></h2>
      <table>
        <thead><tr><th><?= htmlspecialchars($t('notification.channel'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('notification.destination'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
        <tbody>
          <?php foreach ($notifications as $notification): ?>
            <tr><td><?= htmlspecialchars($notification['channel'], ENT_QUOTES, 'UTF-8') ?></td><td class="mono"><?= htmlspecialchars($notification['destination'], ENT_QUOTES, 'UTF-8') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <form method="post" action="/projects/<?= (int) $project['id'] ?>/notifications">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label><?= htmlspecialchars($t('notification.channel'), ENT_QUOTES, 'UTF-8') ?></label>
        <select name="channel"><option value="email">email</option><option value="slack">slack</option><option value="webhook">webhook</option></select>
        <label><?= htmlspecialchars($t('notification.destination'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="text" name="destination" required>
        <label>Secret (optional)</label>
        <input type="text" name="secret">
        <button type="submit"><?= htmlspecialchars($t('notification.add'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </aside>
  </section>

  <section class="panel">
    <h2><?= htmlspecialchars($t('project.saved_views'), ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (empty($savedViews)): ?>
      <p>No saved views yet.</p>
    <?php else: ?>
      <table>
        <thead><tr><th>Name</th><th>Filters</th></tr></thead>
        <tbody>
          <?php foreach ($savedViews as $view): ?>
            <tr><td><?= htmlspecialchars($view['name'], ENT_QUOTES, 'UTF-8') ?></td><td class="mono"><?= htmlspecialchars($view['filters_json'], ENT_QUOTES, 'UTF-8') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <form method="post" action="/projects/<?= (int) $project['id'] ?>/saved-views">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="row">
        <div><label>Name</label><input type="text" name="name" required></div>
        <div><label><?= htmlspecialchars($t('scan.sort'), ENT_QUOTES, 'UTF-8') ?></label><select name="sort"><option value="id_desc">Newest</option><option value="da_desc">DA desc</option></select></div>
      </div>
      <div class="row">
        <div><label><?= htmlspecialchars($t('scan.filter_status'), ENT_QUOTES, 'UTF-8') ?></label><input type="text" name="status"></div>
        <div><label><?= htmlspecialchars($t('scan.filter_link_type'), ENT_QUOTES, 'UTF-8') ?></label><input type="text" name="link_type"></div>
      </div>
      <label><?= htmlspecialchars($t('scan.filter_search'), ENT_QUOTES, 'UTF-8') ?></label>
      <input type="text" name="search">
      <button type="submit"><?= htmlspecialchars($t('common.submit'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </section>

  <section class="panel">
    <h2><?= htmlspecialchars($t('dashboard.latest_scans'), ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (empty($scans)): ?>
      <p><?= htmlspecialchars($t('dashboard.no_scans'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <table>
        <thead><tr><th>ID</th><th><?= htmlspecialchars($t('scan.status'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('scan.total_targets'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('scan.processed_targets'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars($t('common.actions'), ENT_QUOTES, 'UTF-8') ?></th></tr></thead>
        <tbody>
          <?php foreach ($scans as $scan): ?>
            <tr>
              <td>#<?= (int) $scan['id'] ?></td>
              <td><?= htmlspecialchars($scan['status'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $scan['total_targets'] ?></td>
              <td><?= (int) $scan['processed_targets'] ?></td>
              <td><a href="/scans/<?= (int) $scan['id'] ?>"><?= htmlspecialchars($t('scan.details'), ENT_QUOTES, 'UTF-8') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
