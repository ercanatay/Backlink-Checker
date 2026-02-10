<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root { --bg:#f5f7fb; --card:#fff; --text:#1f2937; --muted:#6b7280; --accent:#0f766e; --danger:#b91c1c; --border:#d1d5db; }
    * { box-sizing:border-box; }
    body { margin:0; font-family: "IBM Plex Sans", "Segoe UI", sans-serif; color:var(--text); background:radial-gradient(circle at top right,#dbeafe,#f8fafc 50%,#f3f4f6); }
    a { color:var(--accent); }
    .skip-link { position:absolute; left:-9999px; top:0; background:#111827; color:#fff; padding:8px; }
    .skip-link:focus { left:12px; top:12px; z-index:10; }
    main { min-height:100vh; display:grid; place-items:center; padding:24px; }
    .card { width:min(460px,100%); background:var(--card); border:1px solid var(--border); border-radius:14px; box-shadow:0 8px 40px rgba(15,23,42,.08); padding:24px; }
    h1 { margin:0 0 8px; font-size:1.5rem; }
    p { color:var(--muted); margin:0 0 18px; }
    label { display:block; font-weight:600; margin:10px 0 4px; }
    input, select, button { width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); font:inherit; }
    input:focus, select:focus, button:focus { outline:3px solid rgba(15,118,110,.3); outline-offset:1px; }
    button { background:var(--accent); color:#fff; border:none; margin-top:16px; cursor:pointer; font-weight:600; }
    .error { background:#fee2e2; color:var(--danger); border:1px solid #fca5a5; border-radius:10px; padding:10px; margin-bottom:12px; }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .meta { margin-top:14px; font-size:.85rem; color:var(--muted); }
  </style>
</head>
<body>
<a href="#main" class="skip-link"><?= htmlspecialchars($t('a11y.skip_to_content'), ENT_QUOTES, 'UTF-8') ?></a>
<main id="main">
  <section class="card" aria-label="Login form">
    <h1><?= htmlspecialchars($t('auth.login_title'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= htmlspecialchars($t('app.name'), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if (!empty($error)): ?>
      <div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/login">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <label for="email"><?= htmlspecialchars($t('auth.email'), ENT_QUOTES, 'UTF-8') ?></label>
      <input id="email" type="email" name="email" autocomplete="username" required autofocus>

      <label for="password"><?= htmlspecialchars($t('auth.password'), ENT_QUOTES, 'UTF-8') ?></label>
      <input id="password" type="password" name="password" autocomplete="current-password" required>

      <button type="submit"><?= htmlspecialchars($t('auth.submit'), ENT_QUOTES, 'UTF-8') ?></button>
    </form>

    <p class="meta">Default bootstrap admin comes from <code>.env</code>. Change it before production use.</p>
  </section>
</main>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
      form.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
          if (form.dataset.submitting) {
            e.preventDefault();
            return;
          }
          form.dataset.submitting = 'true';
          btn.style.opacity = '0.7';
          btn.style.cursor = 'not-allowed';
          btn.textContent += '...';
        }
      });
    });
  });
</script>
</body>
</html>
