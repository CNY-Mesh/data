<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login - CNY Mesh Data</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="/assets/style.css" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">
                        <i class="fas fa-lock me-2"></i>
                        CNY Mesh Login
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($lockoutTime > 0): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-clock me-2"></i>
                            Account locked. Try again in <span id="countdown"><?= $lockoutTime ?></span> seconds.
                        </div>
                        <script>
                            let countdown = <?= $lockoutTime ?>;
                            const countdownEl = document.getElementById('countdown');
                            const timer = setInterval(() => {
                                countdown--;
                                countdownEl.textContent = countdown;
                                if (countdown <= 0) {
                                    clearInterval(timer);
                                    location.reload();
                                }
                            }, 1000);
                        </script>
                    <?php else: ?>
                        <form method="POST" action="/?r=login">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control" 
                                           id="username" 
                                           name="username" 
                                           required 
                                           autofocus
                                           autocomplete="username">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control" 
                                           id="password" 
                                           name="password" 
                                           required
                                           autocomplete="current-password">
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    Login
                                </button>
                            </div>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Default credentials: admin/password
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="mt-5">
    <div class="container text-center">
        <p class="text-muted">Meshtastic MQTT PHP · SQLite · Leaflet · Chart.js</p>
    </div>
</footer>

</body>
</html>
