<?php

function send_gym_email(string $to, string $toName, string $subject, string $bodyText): bool
{
    $from = 'noreply@fithubgym.com';
    $fromName = 'FitHub Gym';

    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
    $headers .= 'From: ' . $fromName . ' <' . $from . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $from . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    $escaped = nl2br(htmlspecialchars($bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Inter,sans-serif;background:#fff7f1;margin:0;padding:24px;">
  <div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08);">
    <div style="background:#f18c4f;padding:20px 28px;">
      <h1 style="color:#fff;font-size:1.4rem;margin:0;">FitHub Gym</h1>
    </div>
    <div style="padding:28px;">
      <p style="color:#111;font-size:1rem;margin-bottom:12px;">Hi {$toName},</p>
      <p style="color:#444;font-size:0.95rem;line-height:1.6;">{$escaped}</p>
      <p style="color:#999;font-size:0.8rem;margin-top:24px;">— The FitHub Gym Team</p>
    </div>
  </div>
</body>
</html>
HTML;

    return mail($to, $subject, $html, $headers);
}
