<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/notifications.php';

require_login();
$user = current_user($db);
if (!$user) {
    redirect_to('login.php');
}

$success = '';
$error   = '';

/* ── Handle form POST: submit a new report ─────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['form_action'] ?? '';

    if ($action === 'new_report') {
        $category    = trim($_POST['category'] ?? '');
        $subject     = trim($_POST['subject'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority    = trim($_POST['priority'] ?? 'Medium');
        $equipmentId = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $classId     = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;

        $validCategories = ['Equipment', 'Class', 'Billing', 'Staff', 'Other'];
        $validPriorities = ['Low', 'Medium', 'High'];

        if (!in_array($category, $validCategories, true)) {
            $error = 'Please select a valid category.';
        } elseif (strlen($subject) < 3) {
            $error = 'Subject must be at least 3 characters.';
        } elseif (strlen($description) < 10) {
            $error = 'Please provide a more detailed description (at least 10 characters).';
        } elseif (!in_array($priority, $validPriorities, true)) {
            $error = 'Invalid priority level.';
        } else {
            $stmt = $db->prepare('
                INSERT INTO REPORT (MemberID, Category, Subject, Description, Priority, EquipmentID, ClassID)
                VALUES (:member, :cat, :subj, :desc, :prio, :eq, :cl)
            ');
            $stmt->execute([
                ':member' => $user['UserID'],
                ':cat'    => $category,
                ':subj'   => $subject,
                ':desc'   => $description,
                ':prio'   => $priority,
                ':eq'     => $equipmentId,
                ':cl'     => $classId,
            ]);

            // Notify all admins
            $admins = $db->query("SELECT UserID FROM USER WHERE Role = 'Admin' AND IsActive = 1")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                create_notification($db, (int)$adminId, 'report_update',
                    'New report submitted',
                    $user['Name'] . ' submitted a ' . $priority . ' priority ' . $category . ' report: "' . substr($subject, 0, 80) . '"'
                );
            }

            $success = 'Your report has been submitted successfully. We\'ll get back to you soon.';
        }
    }

    if ($action === 'reply') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        $message  = trim($_POST['message'] ?? '');

        if (!$reportId || strlen($message) < 2) {
            $error = 'Please enter a message.';
        } else {
            // Verify this report belongs to the user
            $check = $db->prepare('SELECT ReportID FROM REPORT WHERE ReportID = :rid AND MemberID = :mid');
            $check->execute([':rid' => $reportId, ':mid' => $user['UserID']]);
            if ($check->fetch()) {
                $stmt = $db->prepare('INSERT INTO REPORT_RESPONSE (ReportID, UserID, Message) VALUES (:rid, :uid, :msg)');
                $stmt->execute([':rid' => $reportId, ':uid' => $user['UserID'], ':msg' => $message]);
                $db->prepare('UPDATE REPORT SET UpdatedAt = CURRENT_TIMESTAMP WHERE ReportID = :id')->execute([':id' => $reportId]);
                $success = 'Your reply has been added.';
            } else {
                $error = 'Report not found.';
            }
        }
    }
}

/* ── Fetch user's reports ──────────────────────────────────────────────── */
$reportsStmt = $db->prepare("
    SELECT r.*, e.Name AS EquipmentName, fc.Name AS ClassName
    FROM REPORT r
    LEFT JOIN EQUIPMENT e ON e.EquipmentID = r.EquipmentID
    LEFT JOIN FITNESS_CLASS fc ON fc.ClassID = r.ClassID
    WHERE r.MemberID = :mid
    ORDER BY r.CreatedAt DESC
");
$reportsStmt->execute([':mid' => $user['UserID']]);
$myReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch responses for all user's reports
$reportIds = array_column($myReports, 'ReportID');
$responsesByReport = [];
if ($reportIds) {
    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
    $respStmt = $db->prepare("
        SELECT rr.*, u.Name AS AuthorName, u.Role AS AuthorRole
        FROM REPORT_RESPONSE rr
        JOIN USER u ON u.UserID = rr.UserID
        WHERE rr.ReportID IN ($placeholders)
        ORDER BY rr.CreatedAt ASC
    ");
    $respStmt->execute($reportIds);
    foreach ($respStmt->fetchAll(PDO::FETCH_ASSOC) as $resp) {
        $responsesByReport[(int)$resp['ReportID']][] = $resp;
    }
}

/* ── Fetch equipment and classes for form selectors ────────────────────── */
$equipment = $db->query("SELECT EquipmentID, Name, Location FROM EQUIPMENT ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
$classes   = $db->query("SELECT ClassID, Name, Day, StartTime FROM FITNESS_CLASS WHERE IsActive = 1 ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);

$expandedReport = isset($_GET['report']) ? (int)$_GET['report'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym — Reports</title>
  <meta name="description" content="Submit and track reports about equipment issues, class problems, billing disputes and more.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/reports.css">
</head>
<body>

  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="reports-page">
    <header class="reports-hero">
      <div class="reports-hero-content">
        <h1>Reports &amp; Feedback</h1>
        <p>Something not right? Let us know. We track every report and respond as soon as possible.</p>
      </div>
    </header>

    <?php if ($success): ?>
      <div class="reports-alert reports-alert--success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <?= e($success) ?>
      </div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="reports-alert reports-alert--error">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?= e($error) ?>
      </div>
    <?php endif; ?>

    <div class="reports-layout">

      <!-- ── Submit a Report ──────────────────────────────────────────── -->
      <section class="reports-section reports-form-section" id="submit-report">
        <div class="section-header">
          <h2>Submit a Report</h2>
          <p>Describe the issue and we'll handle it from here.</p>
        </div>
        <form method="POST" class="report-form" id="report-form">
          <?= csrf_input() ?>
          <input type="hidden" name="form_action" value="new_report">

          <div class="form-row">
            <div class="form-group">
              <label for="report-category">Category</label>
              <select id="report-category" name="category" required>
                <option value="">Select a category…</option>
                <option value="Equipment">🔧 Equipment Issue</option>
                <option value="Class">📅 Class Problem</option>
                <option value="Billing">💳 Billing / Payment</option>
                <option value="Staff">👤 Staff Complaint</option>
                <option value="Other">📝 Other</option>
              </select>
            </div>
            <div class="form-group">
              <label for="report-priority">Priority</label>
              <select id="report-priority" name="priority" required>
                <option value="Low">Low — no rush</option>
                <option value="Medium" selected>Medium — please look into it</option>
                <option value="High">High — urgent / safety issue</option>
              </select>
            </div>
          </div>

          <div class="form-group form-group--conditional" id="equipment-select-group" style="display:none">
            <label for="report-equipment">Which equipment?</label>
            <select id="report-equipment" name="equipment_id">
              <option value="">Select equipment (optional)…</option>
              <?php foreach ($equipment as $eq): ?>
                <option value="<?= $eq['EquipmentID'] ?>"><?= e($eq['Name']) ?> — <?= e($eq['Location']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group form-group--conditional" id="class-select-group" style="display:none">
            <label for="report-class">Which class?</label>
            <select id="report-class" name="class_id">
              <option value="">Select class (optional)…</option>
              <?php foreach ($classes as $cl): ?>
                <option value="<?= $cl['ClassID'] ?>"><?= e($cl['Name']) ?> — <?= e($cl['Day']) ?> <?= e($cl['StartTime']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="report-subject">Subject</label>
            <input type="text" id="report-subject" name="subject" placeholder="Brief summary of the issue…" required minlength="3" maxlength="200">
          </div>

          <div class="form-group">
            <label for="report-description">Description</label>
            <textarea id="report-description" name="description" rows="4" placeholder="Tell us what happened, when, and any relevant details…" required minlength="10"></textarea>
          </div>

          <button type="submit" class="btn-submit-report" id="btn-submit-report">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Submit Report
          </button>
        </form>
      </section>

      <!-- ── My Reports ──────────────────────────────────────────────── -->
      <section class="reports-section" id="my-reports">
        <div class="section-header">
          <h2>My Reports</h2>
          <p><?= count($myReports) ?> report<?= count($myReports) !== 1 ? 's' : '' ?> submitted</p>
        </div>

        <?php if (empty($myReports)): ?>
          <div class="reports-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
            <h3>No reports yet</h3>
            <p>When you submit a report, it will appear here so you can track its status.</p>
          </div>
        <?php else: ?>
          <div class="reports-list">
            <?php foreach ($myReports as $report): ?>
              <?php
                $rid = (int)$report['ReportID'];
                $responses = $responsesByReport[$rid] ?? [];
                $isExpanded = $expandedReport === $rid;
              ?>
              <article class="report-card <?= $isExpanded ? 'is-expanded' : '' ?>" id="report-<?= $rid ?>">
                <div class="report-card-header" data-toggle-report="<?= $rid ?>">
                  <div class="report-card-info">
                    <div class="report-card-badges">
                      <span class="badge badge--status badge--<?= strtolower(str_replace(' ', '-', $report['Status'])) ?>"><?= e($report['Status']) ?></span>
                      <span class="badge badge--priority badge--priority-<?= strtolower($report['Priority']) ?>"><?= e($report['Priority']) ?></span>
                      <span class="badge badge--category"><?= e($report['Category']) ?></span>
                    </div>
                    <h3 class="report-card-subject"><?= e($report['Subject']) ?></h3>
                    <div class="report-card-meta">
                      <time><?= date('M j, Y · g:i A', strtotime($report['CreatedAt'])) ?></time>
                      <?php if ($report['EquipmentName']): ?>
                        <span>🔧 <?= e($report['EquipmentName']) ?></span>
                      <?php endif; ?>
                      <?php if ($report['ClassName']): ?>
                        <span>📅 <?= e($report['ClassName']) ?></span>
                      <?php endif; ?>
                      <?php if (count($responses)): ?>
                        <span class="response-count"><?= count($responses) ?> response<?= count($responses) !== 1 ? 's' : '' ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <span class="report-card-chevron">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </span>
                </div>

                <div class="report-card-body">
                  <div class="report-description">
                    <p><?= nl2br(e($report['Description'])) ?></p>
                  </div>

                  <?php if ($responses): ?>
                    <div class="report-thread">
                      <h4>Conversation</h4>
                      <?php foreach ($responses as $resp): ?>
                        <div class="thread-message <?= $resp['AuthorRole'] === 'Admin' ? 'thread-message--admin' : 'thread-message--member' ?>">
                          <div class="thread-message-header">
                            <strong><?= e($resp['AuthorName']) ?></strong>
                            <span class="thread-role-badge"><?= e($resp['AuthorRole']) ?></span>
                            <time><?= date('M j, Y · g:i A', strtotime($resp['CreatedAt'])) ?></time>
                          </div>
                          <p><?= nl2br(e($resp['Message'])) ?></p>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($report['Status'] !== 'Closed'): ?>
                    <form method="POST" class="reply-form">
                      <?= csrf_input() ?>
                      <input type="hidden" name="form_action" value="reply">
                      <input type="hidden" name="report_id" value="<?= $rid ?>">
                      <textarea name="message" rows="2" placeholder="Add a follow-up message…" required minlength="2"></textarea>
                      <button type="submit" class="btn-reply">Reply</button>
                    </form>
                  <?php else: ?>
                    <p class="report-closed-notice">This report has been closed. If you still need help, please submit a new report.</p>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </main>

  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>

  <script src="js/navbar.js"></script>
  <script>
    // Toggle conditional selectors based on category
    var catSelect = document.getElementById('report-category');
    var eqGroup   = document.getElementById('equipment-select-group');
    var clGroup   = document.getElementById('class-select-group');

    if (catSelect) {
      catSelect.addEventListener('change', function () {
        eqGroup.style.display = this.value === 'Equipment' ? '' : 'none';
        clGroup.style.display = this.value === 'Class'     ? '' : 'none';
      });
    }

    // Toggle report card expansion
    document.addEventListener('click', function (e) {
      var toggle = e.target.closest('[data-toggle-report]');
      if (!toggle) return;
      var card = toggle.closest('.report-card');
      if (card) card.classList.toggle('is-expanded');
    });
  </script>
</body>
</html>
