<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t('dashboard.title'), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { --bg:#f8fafc; --panel:#fff; --text:#111827; --muted:#4b5563; --accent:#0f766e; --danger:#b91c1c; --ok:#166534; --border:#d1d5db; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:"IBM Plex Sans","Segoe UI",sans-serif; background:linear-gradient(150deg,#ecfeff 0%,#f8fafc 40%,#eff6ff 100%); color:var(--text); }
    .skip-link { position:absolute; left:-9999px; top:0; background:#111827; color:#fff; padding:8px; }
    .skip-link:focus { left:12px; top:12px; }
    header { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border); background:#fff; }
    h1 { margin:0; font-size:1.25rem; }
    .container { max-width:1200px; margin:0 auto; padding:22px; display:grid; gap:18px; }
    .grid { display:grid; grid-template-columns:1.2fr .8fr; gap:18px; }
    .panel { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:16px; }
    .flash { padding:10px 12px; border-radius:10px; margin-bottom:12px; }
    .flash.success { background:#dcfce7; color:var(--ok); }
    .flash.error { background:#fee2e2; color:var(--danger); }
    table { width:100%; border-collapse:collapse; }
    th, td { border-bottom:1px solid #e5e7eb; text-align:left; padding:10px; vertical-align:top; }
    input, textarea, select, button { width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); font:inherit; }
    textarea { min-height:110px; }
    button { cursor:pointer; background:var(--accent); color:#fff; border:none; font-weight:600; }
    input:focus, select:focus, button:focus { outline:3px solid rgba(15,118,110,.6); outline-offset:1px; }
    .nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    .nav a, .nav button { text-decoration:none; background:#fff; border:1px solid var(--border); padding:8px 10px; border-radius:10px; color:#111827; width:auto; }
    .pill { display:inline-block; padding:4px 8px; border-radius:999px; background:#eef2ff; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .inline { display:inline; width:auto; }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width: 920px) { .grid { grid-template-columns:1fr; } }
  </style>
</head>
<body>
<?php
$settingsForm = is_array($settingsForm ?? null) ? $settingsForm : ['retention_days' => 90, 'telemetry_enabled' => false];
$updaterConfig = is_array($updaterConfig ?? null) ? $updaterConfig : ['enabled' => true, 'interval_minutes' => 60];
$updaterState = is_array($updaterState ?? null) ? $updaterState : [
  'current_version' => '',
  'current_commit' => '',
  'current_branch' => '',
  'latest_version' => '',
  'latest_url' => '',
  'update_available' => false,
  'last_checked_at' => '',
  'last_check_status' => '',
  'last_check_error' => null,
  'last_apply_at' => '',
  'last_apply_status' => '',
  'last_apply_error' => null,
  'rollback_performed' => false,
  'restart_required' => false,
];
$roles = $user['roles'] ?? [];
$isAdmin = is_array($roles) && in_array('admin', $roles, true);
?>
<a href="#main" class="skip-link"><?= htmlspecialchars($t('a11y.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>
<header>
  <div>
    <h1><?= htmlspecialchars($t('dashboard.title'), ENT_QUOTES, 'UTF-8') ?></h1>
    <small><?= htmlspecialchars($t('dashboard.welcome', ['name' => $user['display_name'] ?? '']), ENT_QUOTES, 'UTF-8') ?></small>
  </div>
  <nav class="nav" aria-label="Main navigation">
    <a href="/dashboard"><?= htmlspecialchars($t('nav.dashboard'), ENT_QUOTES, 'UTF-8') ?></a>
    <form class="inline" method="post" action="/logout">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit"><?= htmlspecialchars($t('nav.logout'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </nav>
</header>
<main id="main" class="container">
  <?php if (!empty($flash)): ?>
    <div class="flash <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status">
      <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($newToken)): ?>
    <div class="panel" role="alert">
      <strong><?= htmlspecialchars($t('api.token_created'), ENT_QUOTES, 'UTF-8') ?></strong>
      <div class="mono" style="margin-top:8px;"><?= htmlspecialchars($newToken, ENT_QUOTES, 'UTF-8') ?></div>
      <?php unset($_SESSION['new_token']); ?>
    </div>
  <?php endif; ?>

  <section class="grid">
    <article class="panel">
      <h2><?= htmlspecialchars($t('nav.projects'), ENT_QUOTES, 'UTF-8') ?></h2>
      <?php if (empty($projects)): ?>
        <p><?= htmlspecialchars($t('project.no_projects'), ENT_QUOTES, 'UTF-8') ?></p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th scope="col"><?= htmlspecialchars($t('project.name'), ENT_QUOTES, 'UTF-8') ?></th>
              <th scope="col"><?= htmlspecialchars($t('project.root_domain'), ENT_QUOTES, 'UTF-8') ?></th>
              <th scope="col"><?= htmlspecialchars($t('scan.status'), ENT_QUOTES, 'UTF-8') ?></th>
              <th scope="col"><?= htmlspecialchars($t('common.actions'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($projects as $project): ?>
              <tr>
                <td><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="mono"><?= htmlspecialchars($project['root_domain'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="pill"><?= htmlspecialchars($project['membership_role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td><a href="/projects/<?= (int) $project['id'] ?>"><?= htmlspecialchars($t('project.open'), ENT_QUOTES, 'UTF-8') ?></a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </article>

    <aside class="panel">
      <h2><?= htmlspecialchars($t('dashboard.create_project'), ENT_QUOTES, 'UTF-8') ?></h2>
      <?php if (!empty($error)): ?><p style="color:#b91c1c"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
      <form method="post" action="/projects">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <label for="project-name"><?= htmlspecialchars($t('project.name'), ENT_QUOTES, 'UTF-8') ?></label>
        <input id="project-name" type="text" name="name" required>

        <label for="project-root-domain"><?= htmlspecialchars($t('project.root_domain'), ENT_QUOTES, 'UTF-8') ?></label>
        <input id="project-root-domain" type="text" name="root_domain" placeholder="example.com" required>

        <label for="project-description"><?= htmlspecialchars($t('project.description'), ENT_QUOTES, 'UTF-8') ?></label>
        <textarea id="project-description" name="description"></textarea>

        <button type="submit"><?= htmlspecialchars($t('project.create'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </aside>
  </section>

  <section class="panel">
    <h2><?= htmlspecialchars($t('dashboard.latest_scans'), ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (empty($latestScans)): ?>
      <p><?= htmlspecialchars($t('dashboard.no_scans'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th scope="col">ID</th>
            <th scope="col"><?= htmlspecialchars($t('scan.status'), ENT_QUOTES, 'UTF-8') ?></th>
            <th scope="col"><?= htmlspecialchars($t('scan.total_targets'), ENT_QUOTES, 'UTF-8') ?></th>
            <th scope="col"><?= htmlspecialchars($t('scan.processed_targets'), ENT_QUOTES, 'UTF-8') ?></th>
            <th scope="col"><?= htmlspecialchars($t('scan.created_at'), ENT_QUOTES, 'UTF-8') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($latestScans as $scan): ?>
            <tr>
              <td><a href="/scans/<?= (int) $scan['id'] ?>">#<?= (int) $scan['id'] ?></a></td>
              <td><?= htmlspecialchars($scan['status'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= (int) $scan['total_targets'] ?></td>
              <td><?= (int) $scan['processed_targets'] ?></td>
              <td><?= htmlspecialchars((string) $scan['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2><?= htmlspecialchars($t('nav.settings'), ENT_QUOTES, 'UTF-8') ?></h2>
    <form method="post" action="/settings">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="row">
        <div>
          <label for="settings-locale"><?= htmlspecialchars($t('settings.locale'), ENT_QUOTES, 'UTF-8') ?></label>
          <select id="settings-locale" name="locale">
            <?php foreach ($supportedLocales as $supportedLocale): ?>
              <option value="<?= htmlspecialchars($supportedLocale, ENT_QUOTES, 'UTF-8') ?>" <?= $supportedLocale === $locale ? 'selected' : '' ?>>
                <?= htmlspecialchars($supportedLocale, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="settings-retention"><?= htmlspecialchars($t('settings.retention_days'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="settings-retention" type="number" min="1" max="3650" name="retention_days" value="<?= (int) ($settingsForm['retention_days'] ?? 90) ?>">
        </div>
      </div>
      <label><input class="inline" type="checkbox" name="telemetry" value="1" <?= !empty($settingsForm['telemetry_enabled']) ? 'checked' : '' ?>> <?= htmlspecialchars($t('settings.telemetry'), ENT_QUOTES, 'UTF-8') ?></label>
      <button type="submit"><?= htmlspecialchars($t('settings.save'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </section>

  <section class="panel">
    <h2><?= htmlspecialchars($t('updater.title'), ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="row">
      <div>
        <strong><?= htmlspecialchars($t('updater.current_version'), ENT_QUOTES, 'UTF-8') ?>:</strong>
        <span class="mono"><?= htmlspecialchars((string) ($updaterState['current_version'] ?: 'n/a'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <div>
        <strong><?= htmlspecialchars($t('updater.latest_version'), ENT_QUOTES, 'UTF-8') ?>:</strong>
        <span class="mono"><?= htmlspecialchars((string) ($updaterState['latest_version'] ?: 'n/a'), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
    </div>
    <p>
      <strong><?= htmlspecialchars($t('updater.update_available'), ENT_QUOTES, 'UTF-8') ?>:</strong>
      <?= !empty($updaterState['update_available']) ? htmlspecialchars($t('common.yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($t('common.no'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <p>
      <strong><?= htmlspecialchars($t('updater.branch_commit'), ENT_QUOTES, 'UTF-8') ?>:</strong>
      <?php $shortCommit = $updaterState['current_commit'] !== '' ? substr((string) $updaterState['current_commit'], 0, 12) : '-'; ?>
      <span class="mono"><?= htmlspecialchars((string) (($updaterState['current_branch'] ?: '-') . ' @ ' . $shortCommit), ENT_QUOTES, 'UTF-8') ?></span>
    </p>
    <p>
      <strong><?= htmlspecialchars($t('updater.last_check'), ENT_QUOTES, 'UTF-8') ?>:</strong>
      <span class="mono"><?= htmlspecialchars((string) ($updaterState['last_checked_at'] ?: 'n/a'), ENT_QUOTES, 'UTF-8') ?></span>
      (<?= htmlspecialchars((string) ($updaterState['last_check_status'] ?: 'n/a'), ENT_QUOTES, 'UTF-8') ?>)
    </p>
    <p>
      <strong><?= htmlspecialchars($t('updater.last_apply'), ENT_QUOTES, 'UTF-8') ?>:</strong>
      <span class="mono"><?= htmlspecialchars((string) ($updaterState['last_apply_at'] ?: 'n/a'), ENT_QUOTES, 'UTF-8') ?></span>
      (<?= htmlspecialchars((string) ($updaterState['last_apply_status'] ?: 'n/a'), ENT_QUOTES, 'UTF-8') ?>)
    </p>
    <p>
      <strong><?= htmlspecialchars($t('updater.interval_minutes'), ENT_QUOTES, 'UTF-8') ?>:</strong>
      <?= (int) ($updaterConfig['interval_minutes'] ?? 60) ?>
      | <strong><?= htmlspecialchars($t('updater.enabled'), ENT_QUOTES, 'UTF-8') ?>:</strong>
      <?= !empty($updaterConfig['enabled']) ? htmlspecialchars($t('common.yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($t('common.no'), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php if (!empty($updaterState['latest_url'])): ?>
      <p><a href="<?= htmlspecialchars((string) $updaterState['latest_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($t('updater.latest_release_link'), ENT_QUOTES, 'UTF-8') ?></a></p>
    <?php endif; ?>
    <?php if (!empty($updaterState['restart_required'])): ?>
      <p style="color:#b91c1c"><strong><?= htmlspecialchars($t('updater.restart_required'), ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php endif; ?>
    <?php if (!empty($updaterState['last_check_error'])): ?>
      <p style="color:#b91c1c"><strong><?= htmlspecialchars($t('updater.last_check_error'), ENT_QUOTES, 'UTF-8') ?>:</strong> <span class="mono"><?= htmlspecialchars((string) $updaterState['last_check_error'], ENT_QUOTES, 'UTF-8') ?></span></p>
    <?php endif; ?>
    <?php if (!empty($updaterState['last_apply_error'])): ?>
      <p style="color:#b91c1c"><strong><?= htmlspecialchars($t('updater.last_apply_error'), ENT_QUOTES, 'UTF-8') ?>:</strong> <span class="mono"><?= htmlspecialchars((string) $updaterState['last_apply_error'], ENT_QUOTES, 'UTF-8') ?></span></p>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
      <div class="row">
        <form method="post" action="/settings/updater/check">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit"><?= htmlspecialchars($t('updater.check_now'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <form method="post" action="/settings/updater/apply">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <button type="submit"><?= htmlspecialchars($t('updater.apply_now'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
      </div>
    <?php else: ?>
      <p><?= htmlspecialchars($t('updater.admin_only'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2><?= htmlspecialchars($t('nav.api_tokens'), ENT_QUOTES, 'UTF-8') ?></h2>
    <form method="post" action="/api-tokens">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <div class="row">
        <div>
          <label for="api-token-name"><?= htmlspecialchars($t('api.name'), ENT_QUOTES, 'UTF-8') ?></label>
          <input id="api-token-name" type="text" name="name" required>
        </div>
        <div>
          <label for="api-token-scopes"><?= htmlspecialchars($t('api.scopes'), ENT_QUOTES, 'UTF-8') ?> (comma separated)</label>
          <input id="api-token-scopes" type="text" name="scopes" value="scans:read,scans:write,projects:write,exports:read,schedules:write,webhooks:test">
        </div>
      </div>
      <button type="submit"><?= htmlspecialchars($t('api.create'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/form_submit_feedback.php'; ?>
<script>
  document.addEventListener('keydown', (event) => {
    if (event.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
      const firstInput = document.querySelector('input, textarea');
      if (firstInput) {
        event.preventDefault();
        firstInput.focus();
      }
    }
  });
</script>
</body>
</html>
