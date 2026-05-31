<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$user = current_user($db);

$classesStmt = $db->query('
    SELECT
        fc.ClassID,
        fc.Name,
        fc.Type,
        fc.Day,
        fc.StartTime,
        fc.EndTime,
        fc.Room,
        fc.Capacity,
        fc.Description,
        fc.ImageURL,
        u.Name AS TrainerName,
        COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END) AS EnrolledCount
    FROM FITNESS_CLASS fc
    JOIN USER u ON fc.TrainerID = u.UserID
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    WHERE fc.IsActive = 1
    GROUP BY fc.ClassID
    ORDER BY fc.Day, fc.StartTime
    LIMIT 4
');
$classes = $classesStmt->fetchAll();

$trainersStmt = $db->query('
    SELECT u.UserID, u.Name, u.ProfilePhoto, tp.Specializations
    FROM USER u
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    WHERE u.Role = "Trainer" AND u.IsActive = 1
    ORDER BY u.Name
    LIMIT 4
');
$trainers = $trainersStmt->fetchAll();

$equipmentStmt = $db->query('
    SELECT Category,
           SUM(CASE WHEN Status = "Available" THEN 1 ELSE 0 END) AS AvailableCount,
           COUNT(*) AS TotalCount,
           SUM(CASE WHEN Status IN ("In use", "Reserved") THEN 1 ELSE 0 END) AS BusyCount
    FROM EQUIPMENT
    GROUP BY Category
    ORDER BY Category
    LIMIT 4
');
$equipmentGroups = $equipmentStmt->fetchAll();

$nextClass = null;
if ($user) {
    $nextStmt = $db->prepare('
        SELECT fc.Name, fc.Day, fc.StartTime, fc.Room
        FROM CLASS_ENROLLMENT ce
        JOIN FITNESS_CLASS fc ON ce.ClassID = fc.ClassID
        WHERE ce.MemberID = :member AND ce.Status = "Enrolled" AND fc.IsActive = 1
        ORDER BY fc.Day, fc.StartTime
        LIMIT 1
    ');
    $nextStmt->execute([':member' => $user['UserID']]);
    $nextClass = $nextStmt->fetch();
}

$promotionsStmt = $db->query('
    SELECT p.*,
           c.Name AS ClassName, c.Description AS ClassDescription, c.ImageURL AS ClassImage, c.Type AS ClassType, c.Day AS ClassDay, c.StartTime AS ClassStartTime, c.EndTime AS ClassEndTime, c.Room AS ClassRoom, c.Capacity AS ClassCapacity,
           u.Name AS TrainerName, u.ProfilePhoto AS TrainerPhoto, tp.Specializations AS TrainerSpecializations,
           (SELECT COUNT(*) FROM CLASS_ENROLLMENT ce WHERE ce.ClassID = p.ClassID AND ce.Status = "Enrolled") AS ClassEnrolledCount
    FROM FEATURED_PROMOTION p
    LEFT JOIN FITNESS_CLASS c ON p.ClassID = c.ClassID
    LEFT JOIN USER u ON p.TrainerID = u.UserID
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    WHERE p.IsActive = 1 AND (c.ClassID IS NOT NULL AND c.IsActive = 1 OR u.UserID IS NOT NULL AND u.IsActive = 1)
');
$promotions = $promotionsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Home</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/components.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/home.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <section class="hero-home">
      <div class="hero-copy">
        <span class="hero-badge"><?= $user ? e($user['Role']) . ' Dashboard' : 'Gym management platform' ?></span>
        <h1><?= $user ? 'Welcome back, ' . e($user['Name']) : 'Train smarter with FitHub' ?></h1>
        <p>Browse classes, check equipment availability, meet trainers and manage bookings from one place.</p>
      </div>

      <article class="next-class-card">
        <span class="card-label">Next Scheduled Class</span>
        <?php if ($nextClass): ?>
          <h2><?= e($nextClass['Name']) ?></h2>
          <div class="class-info-row">
            <span>📅 <?= e($nextClass['Day']) ?></span>
            <span>🕕 <?= e($nextClass['StartTime']) ?></span>
            <span>📍 <?= e($nextClass['Room']) ?></span>
          </div>
          <a class="primary-link" href="book-class.php">Manage booking</a>
        <?php elseif ($user): ?>
          <h2>No booking yet</h2>
          <p>Find a class that does not make future-you hate present-you.</p>
          <a class="primary-link" href="book-class.php">Browse classes</a>
        <?php else: ?>
          <h2>Create an account</h2>
          <p>Members can enroll in classes and cancel bookings when life commits its usual sabotage.</p>
          <a class="primary-link" href="register.php">Join now</a>
        <?php endif; ?>
      </article>
    </section>

    <?php if (!empty($promotions)): ?>
    <section class="page-section">
      <div class="section-header">
        <div>
          <p class="section-kicker">Special Offers</p>
          <h2>Featured Promotions</h2>
        </div>
      </div>
      <div class="promotions-grid">
        <?php foreach ($promotions as $promo): ?>
          <?php if ($promo['Type'] === 'Class'): ?>
            <?php 
              $available = max(0, (int)$promo['ClassCapacity'] - (int)$promo['ClassEnrolledCount']);
              $image = $promo['ClassImage'] ?: 'https://images.unsplash.com/photo-1518611507436-f9221403cca2?auto=format&fit=crop&w=700&q=80';
            ?>
            <article class="promo-card promo-card--class" style="background-image: linear-gradient(to bottom, rgba(42, 27, 21, 0.25) 0%, rgba(42, 27, 21, 0.9) 100%), url('<?= e($image) ?>')">
              <div class="promo-card-content">
                <span class="promo-badge promo-badge--class">⭐ Featured Class</span>
                <h3 class="promo-title"><?= e($promo['PromoTitle']) ?></h3>
                <p class="promo-desc"><?= e($promo['PromoDescription'] ?: $promo['ClassDescription']) ?></p>
                <div class="promo-details">
                  <span class="promo-detail-item"><strong>Class:</strong> <?= e($promo['ClassName']) ?></span>
                  <span class="promo-detail-item"><strong>Type:</strong> <?= e($promo['ClassType']) ?></span>
                  <span class="promo-detail-item">📅 <?= e($promo['ClassDay']) ?> @ <?= e($promo['ClassStartTime']) ?>-<?= e($promo['ClassEndTime']) ?></span>
                  <span class="promo-detail-item">📍 <?= e($promo['ClassRoom']) ?></span>
                  <span class="promo-detail-item">👥 <?= $available ?>/<?= e($promo['ClassCapacity']) ?> spots</span>
                  <span class="promo-detail-item">💪 Trainer: <?= e($promo['TrainerName']) ?></span>
                </div>
                <a class="book-button promo-btn" href="book-class.php?q=<?= urlencode($promo['ClassName']) ?>">Book Class Now</a>
              </div>
            </article>
          <?php else: ?>
            <?php 
              $photo = $promo['TrainerPhoto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($promo['TrainerName']) . '&background=f18c4f&color=fff';
              $specs = array_filter(array_map('trim', explode(',', $promo['TrainerSpecializations'] ?? '')));
              $mainSpec = $specs[0] ?? 'Trainer';
            ?>
            <article class="promo-card promo-card--trainer" style="background-image: linear-gradient(to bottom, rgba(42, 27, 21, 0.25) 0%, rgba(42, 27, 21, 0.9) 100%), url('<?= e($photo) ?>')">
              <div class="promo-card-content">
                <span class="promo-badge promo-badge--trainer">⭐ Featured Trainer</span>
                <h3 class="promo-title"><?= e($promo['PromoTitle']) ?></h3>
                <p class="promo-desc"><?= e($promo['PromoDescription']) ?></p>
                <div class="promo-details">
                  <span class="promo-detail-item"><strong>Trainer:</strong> <?= e($promo['TrainerName']) ?></span>
                  <span class="promo-detail-item"><strong>Focus:</strong> <?= e($mainSpec) ?></span>
                  <?php if (!empty($specs)): ?>
                    <span class="promo-detail-item"><strong>Specialties:</strong> <?= implode(', ', array_slice($specs, 0, 3)) ?></span>
                  <?php endif; ?>
                </div>
                <a class="book-button promo-btn" href="trainer-profile.php?id=<?= e($promo['TrainerID']) ?>">View Trainer Profile</a>
              </div>
            </article>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <section class="page-section">
      <div class="section-header">
        <div>
          <p class="section-kicker">Classes</p>
          <h2>Class Schedule</h2>
        </div>
        <a class="section-link" href="book-class.php">View all classes</a>
      </div>

      <div class="search-panel">
        <form class="home-search-form" action="book-class.php" method="get">
          <div class="search-bar">
            <span class="search-icon">⌕</span>
            <input type="text" name="q" placeholder="Search classes, types or trainers...">
          </div>
          <button class="book-button" type="submit">Search</button>
        </form>
      </div>

      <div class="class-grid">
        <?php foreach ($classes as $class): ?>
          <?php $available = max(0, (int)$class['Capacity'] - (int)$class['EnrolledCount']); ?>
          <article class="class-card">
            <div class="class-card-top">
              <div>
                <h3><?= e($class['Name']) ?></h3>
                <p><?= e($class['Description']) ?></p>
              </div>
              <span class="class-tag"><?= e($class['Type']) ?></span>
            </div>
            <div class="trainer-inline">
              <div>
                <small>Trainer</small>
                <p><?= e($class['TrainerName']) ?></p>
              </div>
            </div>
            <div class="class-meta">
              <span>📅 <?= e($class['Day']) ?></span>
              <span>🕒 <?= e($class['StartTime']) ?>-<?= e($class['EndTime']) ?></span>
              <span>👥 <?= $available ?>/<?= e($class['Capacity']) ?> spots</span>
            </div>
            <a class="book-button" href="book-class.php?q=<?= urlencode($class['Name']) ?>">Book now</a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="page-section" id="equipment">
      <div class="section-header">
        <div>
          <p class="section-kicker">Gym floor</p>
          <h2>Equipment Status</h2>
        </div>
        <a class="section-link" href="equipment.php">View equipment</a>
      </div>

      <div class="equipment-grid">
        <?php foreach ($equipmentGroups as $group): ?>
          <?php $statusClass = ((int)$group['AvailableCount'] > 0) ? 'equipment-status--green' : 'equipment-status--red'; ?>
          <article class="equipment-card">
            <div class="equipment-status <?= e($statusClass) ?>"></div>
            <div>
              <h3><?= e($group['Category']) ?></h3>
              <p><?= e($group['AvailableCount']) ?> available / <?= e($group['TotalCount']) ?> total</p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="page-section page-section--last">
      <div class="section-header">
        <div>
          <p class="section-kicker">Coaching team</p>
          <h2>Our Trainers</h2>
        </div>
        <a class="section-link" href="trainers.php">See trainer page</a>
      </div>

      <div class="team-grid">
        <?php foreach ($trainers as $trainer): ?>
          <?php
            $photo = $trainer['ProfilePhoto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($trainer['Name']) . '&background=f18c4f&color=fff';
            $specs = array_filter(array_map('trim', explode(',', $trainer['Specializations'] ?? '')));
            $mainSpec = $specs[0] ?? 'Personal Trainer';
          ?>
          <a href="trainer-profile.php?id=<?= e($trainer['UserID']) ?>" class="team-card">
            <img src="<?= e($photo) ?>" alt="<?= e($trainer['Name']) ?>">
            <h3><?= e($trainer['Name']) ?></h3>
            <p><?= e($mainSpec) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <footer class="main-footer">
    <div class="footer-content">
      <p>&copy; 2026 FitHub Gym. Move your body, change your life.</p>
      <div class="social-links"><a href="#">Instagram</a><a href="#">Facebook</a></div>
    </div>
  </footer>
  <script src="js/navbar.js"></script>
</body>
</html>
