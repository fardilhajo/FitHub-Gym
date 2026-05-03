<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$trainerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$trainerId) {
    header('Location: trainers.php');
    exit;
}

$stmt = $db->prepare('
    SELECT u.Name, u.ProfilePhoto, tp.Bio, tp.Specializations, tp.Certifications
    FROM USER u
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    WHERE u.UserID = :id AND u.Role = "Trainer" AND u.IsActive = 1
');
$stmt->execute([':id' => $trainerId]);
$trainer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trainer) {
    header('Location: trainers.php');
    exit;
}

$isHttp = strpos($trainer['ProfilePhoto'], 'http') === 0;
$photo = ($trainer['ProfilePhoto'] && ($isHttp || file_exists(__DIR__ . '/' . $trainer['ProfilePhoto']))) 
    ? htmlspecialchars($trainer['ProfilePhoto']) 
    : 'https://ui-avatars.com/api/?name=' . urlencode($trainer['Name']) . '&background=f18c4f&color=fff';

$specs = $trainer['Specializations'] ? explode(',', $trainer['Specializations']) : [];
$certs = $trainer['Certifications'] ? explode(',', $trainer['Certifications']) : [];
$bioParagraphs = $trainer['Bio'] ? explode("\n", $trainer['Bio']) : ['This trainer hasn\'t added a biography yet.'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Trainer Profile - <?= htmlspecialchars($trainer['Name']) ?></title>
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
        <img src="<?= $photo ?>" alt="Photo of <?= htmlspecialchars($trainer['Name']) ?>" class="perfil-foto">
      </div>
      <div class="coluna-detalhes">
        <div class="cabecalho-treinador">
          <span class="badge-cargo">Personal Trainer</span>
          <h1 class="nome-treinador"><?= htmlspecialchars($trainer['Name']) ?></h1>
          <p class="slogan-treinador">Transforming lives through movement</p>
        </div>
        <div class="biografia-treinador">
          <h2>Biography</h2>
          <div class="texto-biografia">
            <?php foreach ($bioParagraphs as $p): ?>
                <?php if (trim($p)): ?>
                    <p><?= nl2br(htmlspecialchars(trim($p))) ?></p>
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
            <?php if (trim($spec)): ?>
                <article class="cartao-especialidade">
                    <h3><?= htmlspecialchars(trim($spec)) ?></h3>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($specs)): ?>
            <p>No specialisations listed.</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="seccao-certificados">
      <h2 class="titulo-seccao">Certifications and Education</h2>
      <div class="lista-certificados">
        <?php foreach ($certs as $cert): ?>
            <?php if (trim($cert)): ?>
                <article class="item-certificado">
                  <div class="ano-certificado"><span>&check;</span></div>
                  <div class="info-certificado">
                      <h3><?= htmlspecialchars(trim($cert)) ?></h3>
                  </div>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($certs)): ?>
            <p>No certifications listed.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="rodape-perfil" style="display:flex; justify-content:center; margin-top: 2rem;">
      <a href="trainers.php" style="color: var(--orange); text-decoration: none; font-weight: 600;">&larr; Back to Trainers List</a>
  </footer>

  <script src="js/navbar.js"></script>
</body>
</html>
