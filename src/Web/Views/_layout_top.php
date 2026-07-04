<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $pageTitle ?? 'CNYmesh Data Dashboard' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/assets/style.css?v=<?= time() ?>" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/app.js?v=<?= time() ?>"></script>
</head>
<body>
<?php
// Check authentication status for nav display
$auth = new \App\Web\Auth();
$isAuthenticated = $auth->isAuthenticated();
$username = $auth->getUsername();
?>
<header>
  <div class="header-container">
    <!-- Logo and Title -->
    <div class="header-brand">
      <div class="logo-container me-3">
        <img src="/assets/logo.png" alt="CNYmesh Logo" style="height: 90px;">
      </div>
      <h1 class="mb-0 d-none d-md-block">CNYmesh Data Dashboard</h1>
      <h1 class="mb-0 d-md-none">CNYmesh</h1>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class="fas fa-bars"></i>
    </button>

    <!-- Navigation and User Info -->
    <div class="header-nav-wrapper d-none d-md-flex">
      <nav class="header-nav">
        <a href="/?r=our_nodes">Home</a>
        <a href="/?r=about">About</a>
        <a href="/?r=dashboard">Dashboard</a>
        <a href="/?r=nodes">Nodes</a>
        <a href="/?r=positions">Positions</a>
        <a href="/?r=text_messages">Text Messages</a>
        <a href="/?r=neighbors">Neighbors</a>
        <?php if ($isAuthenticated): ?>
          <a href="/?r=tools">Tools</a>
        <?php else: ?>
          <a href="/?r=login">Login</a>
        <?php endif; ?>
      </nav>
      
      <?php if ($isAuthenticated): ?>
        <div class="user-info ms-3">
          <span class="text-light me-3">
            <i class="fas fa-user me-1"></i>
            Welcome, <?= htmlspecialchars($username) ?>
          </span>
          <a href="/?r=login&action=logout" class="btn btn-outline-light btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i>
            Logout
          </a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mobile Navigation Menu -->
  <div class="collapse d-md-none" id="navbarNav">
    <nav class="mobile-nav">
      <a href="/?r=our_nodes">Home</a>
      <a href="/?r=about">About</a>
      <a href="/?r=dashboard">Dashboard</a>
      <a href="/?r=nodes">Nodes</a>
      <a href="/?r=positions">Positions</a>
      <a href="/?r=text_messages">Text Messages</a>
      <a href="/?r=neighbors">Neighbors</a>
      <?php if ($isAuthenticated): ?>
        <a href="/?r=tools">Tools</a>
        <div class="mobile-user-info">
          <span class="text-light">
            <i class="fas fa-user me-1"></i>
            Welcome, <?= htmlspecialchars($username) ?>
          </span>
          <a href="/?r=login&action=logout" class="btn btn-outline-light btn-sm mt-2">
            <i class="fas fa-sign-out-alt me-1"></i>
            Logout
          </a>
        </div>
      <?php else: ?>
        <a href="/?r=login">Login</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main>
