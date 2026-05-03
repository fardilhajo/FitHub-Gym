<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FitHub Gym - Classes</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/booking.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="content">
    <header class="page-header"><h1>Class Schedule</h1></header>

    <section class="filter-section">
      <div class="filter-title"><h2>Filters</h2></div>
      <form class="filter-controls">
        <input type="text" class="input-search" placeholder="Search classes..." />
        <select class="select-field">
          <option>All Types</option><option>Yoga</option><option>Cardio</option>
          <option>Pilates</option><option>CrossFit</option><option>Boxing</option><option>Recovery</option>
        </select>
        <select class="select-field">
          <option>All Days</option><option>Monday</option><option>Tuesday</option><option>Wednesday</option>
          <option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option>
        </select>
        <select class="select-field">
          <option>All Trainers</option><option>Trainer 1</option><option>Trainer 2</option>
          <option>Trainer 3</option><option>Trainer 4</option>
        </select>
        <button type="reset" class="btn-clear">Clear</button>
      </form>
    </section>

    <section class="classes-container">
      <p class="results-count">Showing 4 available classes</p>
      <div class="classes-grid">
        <article class="class-card">
          <div class="card-image">
            <img src="https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=400&q=80" alt="Yoga Class" />
            <span class="tag-type">Yoga</span>
          </div>
          <div class="card-body">
            <h3>Morning Zen Flow</h3>
            <div class="class-info">
              <span><strong>Trainer:</strong> Ana Costas</span>
              <span>🕒 09:00 - 10:00</span><span>📍 Studio B</span>
            </div>
            <div class="class-footer">
              <span class="capacity">8/15 spots left</span>
              <button class="btn-book">Book Now</button>
            </div>
          </div>
        </article>
        <article class="class-card">
          <div class="card-image">
            <img src="https://images.unsplash.com/photo-1517963879433-6ad2b056d712?w=400&q=80" alt="HIIT Class" />
            <span class="tag-type">HIIT</span>
          </div>
          <div class="card-body">
            <h3>High-energy interval training</h3>
            <div class="class-info">
              <span><strong>Trainer:</strong> Ricardo Silva</span>
              <span>🕒 18:00 - 19:00</span><span>📍 Studio A</span>
            </div>
            <div class="class-footer">
              <span class="capacity">12/20 spots left</span>
              <button class="btn-book">Book Now</button>
            </div>
          </div>
        </article>
        <article class="class-card">
          <div class="card-image">
            <img src="https://images.unsplash.com/photo-1518611507436-f9221403cca2?w=400&q=80" alt="Pilates Class" />
            <span class="tag-type">Pilates</span>
          </div>
          <div class="card-body">
            <h3>Pilates Core Strength</h3>
            <div class="class-info">
              <span><strong>Trainer:</strong> Sofia Martins</span>
              <span>🕒 10:00 - 11:00</span><span>📍 Studio C</span>
            </div>
            <div class="class-footer">
              <span class="capacity">6/12 spots left</span>
              <button class="btn-book">Book Now</button>
            </div>
          </div>
        </article>
        <article class="class-card">
          <div class="card-image">
            <img src="https://images.unsplash.com/photo-1538805060514-97d9cc17730c?w=400&q=80" alt="Spinning Class" />
            <span class="tag-type">Spinning</span>
          </div>
          <div class="card-body">
            <h3>Cardio ride with high intensity blocks</h3>
            <div class="class-info">
              <span><strong>Trainer:</strong> David Santos</span>
              <span>🕒 19:00 - 20:00</span><span>📍 Studio A</span>
            </div>
            <div class="class-footer">
              <span class="capacity">15/20 spots left</span>
              <button class="btn-book">Book Now</button>
            </div>
          </div>
        </article>
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
