<?php 
session_start();
require_once __DIR__ . '/includes/db.php';

// Fetch top 4 active trainers for the homepage
$trainersStmt = $db->prepare('
    SELECT u.UserID, u.Name, u.ProfilePhoto, tp.Specializations 
    FROM USER u 
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID 
    WHERE u.Role = "Trainer" AND u.IsActive = 1
    LIMIT 4
');
$trainersStmt->execute();
$trainers = $trainersStmt->fetchAll(PDO::FETCH_ASSOC);
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
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/home.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <section class="hero-home">
      <div class="hero-copy">
        <span class="hero-badge">Member Dashboard</span>
        <h1>Welcome back, João</h1>
        <p>Track your next class, check equipment availability and find the right trainer for your next session.</p>
      </div>

      <article class="next-class-card">
        <span class="card-label">Next Scheduled Class</span>
        <h2>HIIT Intenso</h2>
        <div class="class-info-row">
          <span>📅 8 April</span>
          <span>🕕 18:00</span>
          <span>📍 Studio A</span>
        </div>
        <a class="primary-link" href="book-class.php">Manage booking</a>
      </article>
    </section>

    <section class="page-section">
      <div class="section-header">
        <div>
          <p class="section-kicker">Classes</p>
          <h2>Class Schedule</h2>
        </div>
        <a class="section-link" href="book-class.php">View all classes</a>
      </div>

      <div class="search-panel">
        <div class="search-bar">
          <span class="search-icon">⌕</span>
          <input type="text" placeholder="Search classes...">
        </div>

        <div class="filter-row">
          <button class="filter-chip filter-chip--active" type="button">All</button>
          <button class="filter-chip" type="button">HIIT</button>
          <button class="filter-chip" type="button">Yoga</button>
          <button class="filter-chip" type="button">Spinning</button>
          <button class="filter-chip" type="button">Strength</button>
          <button class="filter-chip" type="button">Pilates</button>
        </div>
      </div>

      <div class="class-grid">
        <article class="class-card">
          <div class="class-card-top">
            <div>
              <h3>HIIT Intenso</h3>
              <p>High-energy interval training</p>
            </div>
            <span class="class-tag">HIIT</span>
          </div>
          <div class="trainer-inline">
            <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=80&q=80" alt="Ricardo Silva">
            <div>
              <small>Trainer</small>
              <p>Ricardo Silva</p>
            </div>
          </div>
          <div class="class-meta">
            <span>🕒 18:00</span>
            <span>👥 12/20</span>
          </div>
          <a class="book-button" href="book-class.php">Book now</a>
        </article>

        <article class="class-card">
          <div class="class-card-top">
            <div>
              <h3>Yoga Flow</h3>
              <p>Balance, breathing and mobility</p>
            </div>
            <span class="class-tag">Yoga</span>
          </div>
          <div class="trainer-inline">
            <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=80&q=80" alt="Ana Costa">
            <div>
              <small>Trainer</small>
              <p>Ana Costa</p>
            </div>
          </div>
          <div class="class-meta">
            <span>🕘 09:00</span>
            <span>👥 8/15</span>
          </div>
          <a class="book-button" href="book-class.php">Book now</a>
        </article>

        <article class="class-card">
          <div class="class-card-top">
            <div>
              <h3>Spinning Power</h3>
              <p>Cardio ride with high intensity blocks</p>
            </div>
            <span class="class-tag">Spinning</span>
          </div>
          <div class="trainer-inline">
            <img src="https://images.unsplash.com/photo-1507591064344-4c6ce005b128?auto=format&fit=crop&w=80&q=80" alt="Miguel Sousa">
            <div>
              <small>Trainer</small>
              <p>Miguel Sousa</p>
            </div>
          </div>
          <div class="class-meta">
            <span>🕖 19:00</span>
            <span>👥 15/20</span>
          </div>
          <a class="book-button" href="book-class.php">Book now</a>
        </article>

        <article class="class-card">
          <div class="class-card-top">
            <div>
              <h3>Pilates Essential</h3>
              <p>Core strength and body control</p>
            </div>
            <span class="class-tag">Pilates</span>
          </div>
          <div class="trainer-inline">
            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=80&q=80" alt="Sofia Martins">
            <div>
              <small>Trainer</small>
              <p>Sofia Martins</p>
            </div>
          </div>
          <div class="class-meta">
            <span>🕙 10:00</span>
            <span>👥 6/12</span>
          </div>
          <a class="book-button" href="book-class.php">Book now</a>
        </article>
      </div>
    </section>

    <section class="page-section" id="equipment">
      <div class="section-header">
        <div>
          <p class="section-kicker">Gym floor</p>
          <h2>Equipment Status</h2>
        </div>
      </div>

      <div class="equipment-grid">
        <article class="equipment-card">
          <div class="equipment-status equipment-status--green"></div>
          <div>
            <h3>Treadmills</h3>
            <p>4 available</p>
          </div>
        </article>

        <article class="equipment-card">
          <div class="equipment-status equipment-status--red"></div>
          <div>
            <h3>Free Weights</h3>
            <p>Very busy</p>
          </div>
        </article>

        <article class="equipment-card">
          <div class="equipment-status equipment-status--green"></div>
          <div>
            <h3>Bikes</h3>
            <p>2 available</p>
          </div>
        </article>

        <article class="equipment-card">
          <div class="equipment-status equipment-status--green"></div>
          <div>
            <h3>Machines</h3>
            <p>8 available</p>
          </div>
        </article>
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
            $isHttp = strpos($trainer['ProfilePhoto'], 'http') === 0;
            $photo = ($trainer['ProfilePhoto'] && ($isHttp || file_exists(__DIR__ . '/' . $trainer['ProfilePhoto']))) 
                ? htmlspecialchars($trainer['ProfilePhoto']) 
                : 'https://ui-avatars.com/api/?name=' . urlencode($trainer['Name']) . '&background=f18c4f&color=fff';
            
            $specs = explode(',', $trainer['Specializations']);
            $mainSpec = isset($specs[0]) ? trim($specs[0]) : 'Personal Trainer';
          ?>
          <a href="trainer-profile.php?id=<?= $trainer['UserID'] ?>" class="team-card" style="text-decoration:none; color:inherit; cursor:pointer; transition:transform 0.2s ease;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <img src="<?= $photo ?>" alt="<?= htmlspecialchars($trainer['Name']) ?>">
            <h3><?= htmlspecialchars($trainer['Name']) ?></h3>
            <p><?= htmlspecialchars($mainSpec) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <script src="js/navbar.js"></script>
</body>
</html>
