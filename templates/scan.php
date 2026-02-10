<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t('scan.details'), ENT_QUOTES, 'UTF-8') ?> #<?= (int) $scan['id'] ?></title>
  <style>
    :root { --bg:#f8fafc; --panel:#fff; --text:#111827; --muted:#4b5563; --accent:#7c3aed; --border:#d1d5db; --ok:#166534; --danger:#b91c1c; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:"IBM Plex Sans","Segoe UI",sans-serif; background:conic-gradient(from 200deg at top right,#e0f2fe,#f8fafc,#ede9fe); color:var(--text); }
    header { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:16px 22px; border-bottom:1px solid var(--border); background:#fff; flex-wrap:wrap; }
    .container { max-width:1300px; margin:0 auto; padding:20px; display:grid; gap:14px; }
    .panel { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px; }
    .meta { display:grid; grid-template-columns:repeat(5,minmax(120px,1fr)); gap:10px; }
    .meta div { background:#f8fafc; border:1px solid #e5e7eb; border-radius:10px; padding:10px; }
    form.filters { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; align-items:end; }
    input, select, button { width:100%; padding:9px 10px; border-radius:10px; border:1px solid var(--border); font:inherit; }
    button { cursor:pointer; background:var(--accent); color:#fff; border:none; font-weight:600; }
    table { width:100%; border-collapse:collapse; font-size:.92rem; }
    th, td { border-bottom:1px solid #e5e7eb; text-align:left; padding:8px; vertical-align:top; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .ok { color:var(--ok); }
    .err { color:var(--danger); }
    .tools { display:flex; gap:8px; flex-wrap:wrap; }
    .tools a, .tools button { width:auto; text-decoration:none; border:1px solid var(--border); padding:8px 10px; border-radius:10px; color:#111827; background:#fff; }
    .skip-link { position:absolute; left:-9999px; }
    .skip-link:focus { left:12px; top:12px; background:#111827; color:#fff; padding:8px; border-radius:6px; z-index:10; }
    @media (max-width: 1080px) { form.filters { grid-template-columns:1fr 1fr; } .meta { grid-template-columns:1fr 1fr; } }
  </style>
</head>
<body>
<a href="#main" class="skip-link"><?= htmlspecialchars($t('a11y.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>
<header>
  <div>
    <strong><?= htmlspecialchars($t('scan.details'), ENT_QUOTES, 'UTF-8') ?> #<?= (int) $scan['id'] ?></strong>
    <div class="mono"><?= htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8') ?> / <?= htmlspecialchars($scan['root_domain'], ENT_QUOTES, 'UTF-8') ?></div>
  </div>
  <div class="tools">
    <a href="/projects/<?= (int) $project['id'] ?>"><?= htmlspecialchars($t('nav.projects'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/scans/<?= (int) $scan['id'] ?>/export?format=csv"><?= htmlspecialchars($t('export.csv'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/scans/<?= (int) $scan['id'] ?>/export?format=txt"><?= htmlspecialchars($t('export.txt'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/scans/<?= (int) $scan['id'] ?>/export?format=xlsx"><?= htmlspecialchars($t('export.xlsx'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="/scans/<?= (int) $scan['id'] ?>/export?format=json"><?= htmlspecialchars($t('export.json'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php if (!in_array($scan['status'], ['completed','failed','cancelled'], true)): ?>
      <form method="post" action="/scans/<?= (int) $scan['id'] ?>/cancel">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit"><?= htmlspecialchars($t('scan.cancel'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    <?php endif; ?>
  </div>
</header>
<main id="main" class="container">
  <?php if (!empty($flash)): ?><div class="flash <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
  <section class="panel meta">
    <div><strong><?= htmlspecialchars($t('scan.status'), ENT_QUOTES, 'UTF-8') ?></strong><br><?= htmlspecialchars($scan['status'], ENT_QUOTES, 'UTF-8') ?></div>
    <div><strong><?= htmlspecialchars($t('scan.total_targets'), ENT_QUOTES, 'UTF-8') ?></strong><br><?= (int) $scan['total_targets'] ?></div>
    <div><strong><?= htmlspecialchars($t('scan.processed_targets'), ENT_QUOTES, 'UTF-8') ?></strong><br><?= (int) $scan['processed_targets'] ?></div>
    <div><strong><?= htmlspecialchars($t('scan.created_at'), ENT_QUOTES, 'UTF-8') ?></strong><br><?= htmlspecialchars((string) $scan['created_at'], ENT_QUOTES, 'UTF-8') ?></div>
    <div><strong><?= htmlspecialchars($t('scan.finished_at'), ENT_QUOTES, 'UTF-8') ?></strong><br><?= htmlspecialchars((string) ($scan['finished_at'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
  </section>

  <?php if (!empty($trend['has_previous'])): ?>
    <section class="panel">
      <strong><?= htmlspecialchars($t('scan.trend'), ENT_QUOTES, 'UTF-8') ?></strong>
      <div>Previous scan: #<?= (int) $trend['previous_scan_id'] ?></div>
      <div>Backlink delta: <?= (int) $trend['delta_backlinks'] ?></div>
      <div>Average DA delta: <?= htmlspecialchars((string) $trend['delta_avg_da'], ENT_QUOTES, 'UTF-8') ?></div>
    </section>
  <?php endif; ?>

  <section class="panel">
    <form method="get" class="filters">
      <div><label for="filter-status"><?= htmlspecialchars($t('scan.filter_status'), ENT_QUOTES, 'UTF-8') ?></label><input id="filter-status" name="status" value="<?= htmlspecialchars($filters['status'], ENT_QUOTES, 'UTF-8') ?>"></div>
      <div><label for="filter-link-type"><?= htmlspecialchars($t('scan.filter_link_type'), ENT_QUOTES, 'UTF-8') ?></label><input id="filter-link-type" name="link_type" value="<?= htmlspecialchars($filters['link_type'], ENT_QUOTES, 'UTF-8') ?>"></div>
      <div><label for="filter-search"><?= htmlspecialchars($t('scan.filter_search'), ENT_QUOTES, 'UTF-8') ?></label><input id="filter-search" name="search" value="<?= htmlspecialchars($filters['search'], ENT_QUOTES, 'UTF-8') ?>"></div>
      <div><label for="filter-sort"><?= htmlspecialchars($t('scan.sort'), ENT_QUOTES, 'UTF-8') ?></label>
        <select id="filter-sort" name="sort">
          <option value="id_desc" <?= $filters['sort'] === 'id_desc' ? 'selected' : '' ?>>Newest</option>
          <option value="da_desc" <?= $filters['sort'] === 'da_desc' ? 'selected' : '' ?>>DA desc</option>
          <option value="da_asc" <?= $filters['sort'] === 'da_asc' ? 'selected' : '' ?>>DA asc</option>
          <option value="status_asc" <?= $filters['sort'] === 'status_asc' ? 'selected' : '' ?>>Status</option>
        </select>
      </div>
      <button type="submit"><?= htmlspecialchars($t('common.search'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>
  </section>

  <section class="panel">
    <?php if (empty($results)): ?>
      <p><?= htmlspecialchars($t('scan.no_results'), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <div style="overflow:auto;">
        <table>
          <thead>
            <tr>
              <th><?= htmlspecialchars($t('result.url'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.final_url'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.http_status'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.noindex'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.x_robots'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.backlink'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.link_type'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.anchor'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.da'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.pa'), ENT_QUOTES, 'UTF-8') ?></th>
              <th><?= htmlspecialchars($t('result.error'), ENT_QUOTES, 'UTF-8') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($results as $row): ?>
            <tr>
              <td class="mono"><a href="<?= htmlspecialchars($row['source_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($row['source_domain'], ENT_QUOTES, 'UTF-8') ?></a></td>
              <td class="mono"><?= htmlspecialchars((string) $row['final_url'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) $row['http_status'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= ((int) $row['robots_noindex']) === 1 ? htmlspecialchars($t('common.yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($t('common.no'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= ((int) $row['x_robots_noindex']) === 1 ? htmlspecialchars($t('common.yes'), ENT_QUOTES, 'UTF-8') : htmlspecialchars($t('common.no'), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= ((int) $row['backlink_found']) === 1 ? '<span class="ok">' . htmlspecialchars($t('common.yes'), ENT_QUOTES, 'UTF-8') . '</span>' : '<span class="err">' . htmlspecialchars($t('common.no'), ENT_QUOTES, 'UTF-8') . '</span>' ?></td>
              <td><?= htmlspecialchars((string) $row['best_link_type'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($row['anchor_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($row['domain_authority'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($row['page_authority'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars((string) ($row['error_message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/partials/form_submit_feedback.php'; ?>
</body>
</html>
