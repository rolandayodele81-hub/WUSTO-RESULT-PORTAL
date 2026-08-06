<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

auth_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check Result — Wesley University Portal</title>
  <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/css/portal.css" rel="stylesheet">
  <style>
    .grade-A { color: #8ff0a4; } .grade-B { color: #f0c64e; } .grade-C { color: #ffc98a; }
    .grade-D, .grade-E { color: #f7c9d4; } .grade-F { color: #ff8fa3; }
  </style>
</head>
<body class="bg-dark text-white">
  <main class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h1 class="h3 mb-0">My results</h1>
          <?php if (is_logged_in()): ?>
            <a class="btn btn-outline-light btn-sm" href="../logout.php">Sign out</a>
          <?php else: ?>
            <a class="btn btn-warning btn-sm" href="../login.php">Sign in</a>
          <?php endif ?>
        </div>

        <?php if (!is_logged_in()): ?>
          <div class="card bg-secondary bg-opacity-10 border-0 shadow-lg mb-4">
            <div class="card-body p-4">
              <p class="text-muted mb-3">Not signed in? Do a quick guest check with your matric number and surname — you'll see your most recent semester only. <a href="../login.php" class="link-light">Sign in</a> instead for your full transcript and running CGPA.</p>
              <form id="guestForm" class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Matric number</label>
                  <input class="form-control form-control-lg" id="matric" placeholder="WU/2021/0143" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Surname</label>
                  <input class="form-control form-control-lg" id="lastName" placeholder="Okonkwo" required>
                </div>
                <div class="col-12">
                  <button class="btn btn-warning btn-lg w-100" type="submit">Check result</button>
                </div>
              </form>
            </div>
          </div>
        <?php endif ?>

        <div id="resultArea"></div>
      </div>
    </div>
  </main>

  <script>window.WU_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;</script>
  <script src="../assets/js/result-client.js"></script>
</body>
</html>
