<?php
session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

$trainerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($trainerId <= 0) {
    redirect_to('trainers.php');
}

$stmt = $db->prepare('
    SELECT u.UserID, u.Name, u.ProfilePhoto, tp.Bio, tp.Specializations, tp.Certifications
    FROM USER u
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    WHERE u.UserID = :id AND u.Role = "Trainer" AND u.IsActive = 1
');
$stmt->execute([':id' => $trainerId]);
$trainer = $stmt->fetch();

if (!$trainer) {
    redirect_to('trainers.php');
}

$classStmt = $db->prepare('
    SELECT fc.ClassID, fc.Name, fc.Type, fc.Day, fc.StartTime, fc.EndTime, fc.Room, fc.Capacity,
           COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END) AS EnrolledCount
    FROM FITNESS_CLASS fc
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    WHERE fc.TrainerID = :trainer AND fc.IsActive = 1
    GROUP BY fc.ClassID
    ORDER BY fc.Day, fc.StartTime
');
$classStmt->execute([':trainer' => $trainerId]);
$classes = $classStmt->fetchAll();

$photo = $trainer['ProfilePhoto'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($trainer['Name']) . '&background=f18c4f&color=fff';
$specs = array_filter(array_map('trim', explode(',', $trainer['Specializations'] ?? '')));
$certs = array_filter(array_map('trim', explode(',', $trainer['Certifications'] ?? '')));
$bioParagraphs = $trainer['Bio'] ? explode("\n", $trainer['Bio']) : ['This trainer has not added a biography yet.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trainer Profile - <?= e($trainer['Name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/reset.css">
  <link rel="stylesheet" href="css/navbar.css">
  <link rel="stylesheet" href="css/pages/profile.css">
</head>
<body>
  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <main class="perfil-container">
    <section class="seccao-introducao">
      <div class="coluna-foto">
        <img src="<?= e($photo) ?>" alt="Photo of <?= e($trainer['Name']) ?>" class="perfil-foto">
      </div>
      <div class="coluna-detalhes">
        <div class="cabecalho-treinador">
          <span class="badge-cargo">Personal Trainer</span>
          <h1 class="nome-treinador"><?= e($trainer['Name']) ?></h1>
          <p class="slogan-treinador">Transforming lives through movement</p>
        </div>
        <div class="biografia-treinador">
          <h2>Biography</h2>
          <div class="texto-biografia">
            <?php foreach ($bioParagraphs as $p): ?>
              <?php if (trim($p)): ?>
                <p><?= nl2br(e(trim($p))) ?></p>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="seccao-especializacoes">
      <h2 class="titulo-seccao">Specialisations</h2>
      <div class="grelha-especializacoes">
        <?php foreach ($specs as $spec): ?>
          <article class="cartao-especialidade"><h3><?= e($spec) ?></h3></article>
        <?php endforeach; ?>
        <?php if (!$specs): ?><p>No specialisations listed.</p><?php endif; ?>
      </div>
    </section>

    <section class="seccao-certificados">
      <h2 class="titulo-seccao">Certifications and Education</h2>
      <div class="lista-certificados">
        <?php foreach ($certs as $cert): ?>
          <article class="item-certificado">
            <div class="ano-certificado"><span>&check;</span></div>
            <div class="info-certificado"><h3><?= e($cert) ?></h3></div>
          </article>
        <?php endforeach; ?>
        <?php if (!$certs): ?><p>No certifications listed.</p><?php endif; ?>
      </div>
    </section>

    <section class="seccao-certificados">
      <h2 class="titulo-seccao">Classes taught</h2>
      <div class="lista-certificados">
        <?php foreach ($classes as $class): ?>
          <article class="item-certificado trainer-class-row">
            <div class="ano-certificado"><span><?= e($class['Type']) ?></span></div>
            <div class="info-certificado">
              <h3><?= e($class['Name']) ?></h3>
              <p><?= e($class['Day']) ?> · <?= e($class['StartTime']) ?>-<?= e($class['EndTime']) ?> · <?= e($class['Room']) ?> · <?= e($class['EnrolledCount']) ?>/<?= e($class['Capacity']) ?> members</p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <footer class="rodape-perfil">
    <a href="trainers.php" class="back-trainers-link">&larr; Back to Trainers List</a>
  </footer>

  <script src="js/navbar.js"></script>
</body>
</html>
