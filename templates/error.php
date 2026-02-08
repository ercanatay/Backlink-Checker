<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error <?= (int) $status ?></title>
  <style>
    body { margin:0; font-family:"IBM Plex Sans","Segoe UI",sans-serif; display:grid; min-height:100vh; place-items:center; background:#f8fafc; color:#111827; }
    .card { border:1px solid #d1d5db; border-radius:14px; background:#fff; padding:26px; max-width:520px; }
    a { color:#1d4ed8; }
  </style>
</head>
<body>
  <main class="card">
    <h1>Error <?= (int) $status ?></h1>
    <p><?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') ?></p>
    <p><a href="/dashboard">Back to dashboard</a></p>
  </main>
</body>
</html>
