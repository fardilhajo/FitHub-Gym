<?php
require_once __DIR__ . '/_helpers.php';
public_api_cors(['GET']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    public_api_json_headers();
    public_api_error('method_not_allowed', 'Only GET requests are supported by the public API.', 405);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Public API</title>
  <style>
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #111827; color: #f9fafb; }
    main { max-width: 920px; margin: 0 auto; padding: 48px 20px; }
    h1 { font-size: clamp(2rem, 4vw, 3.5rem); margin-bottom: 0.25rem; }
    p { color: #d1d5db; line-height: 1.65; }
    section { background: #1f2937; border: 1px solid #374151; border-radius: 18px; padding: 24px; margin-top: 20px; }
    code, pre { background: #020617; border-radius: 10px; }
    code { padding: 0.15rem 0.35rem; }
    pre { padding: 16px; overflow-x: auto; color: #bfdbfe; }
    a { color: #fbbf24; }
    .tag { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 999px; background: #065f46; color: #d1fae5; font-size: 0.8rem; font-weight: 700; }
  </style>
</head>
<body>
  <main>
    <span class="tag">Read-only REST API</span>
    <h1>FitHub Public API</h1>
    <p>Third-party apps can query class schedules, trainer profiles, and equipment availability without logging in. The API only exposes public gym data, because publishing password hashes would be a bold new genre of disaster.</p>

    <section>
      <h2>Class schedules</h2>
      <p><code>GET /api/public/classes.php</code></p>
      <pre>GET /api/public/classes.php?day=Monday&type=Yoga
GET /api/public/classes.php?id=1
GET /api/public/classes.php?trainer=3&q=strength</pre>
    </section>

    <section>
      <h2>Trainer profiles</h2>
      <p><code>GET /api/public/trainers.php</code></p>
      <pre>GET /api/public/trainers.php
GET /api/public/trainers.php?id=3
GET /api/public/trainers.php?q=mobility</pre>
    </section>

    <section>
      <h2>Equipment availability</h2>
      <p><code>GET /api/public/equipment.php</code></p>
      <pre>GET /api/public/equipment.php
GET /api/public/equipment.php?category=Cardio
GET /api/public/equipment.php?id=5&amp;start=2026-05-30T10:00&amp;end=2026-05-30T11:00</pre>
    </section>
  </main>
</body>
</html>
