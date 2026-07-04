<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h2 class="mb-0">
                        <i class="fas fa-key me-2"></i>
                        Password Hash Generator
                    </h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Security Tool:</strong> Use this tool to generate bcrypt password hashes for the 
                        <code>.env</code> file. Never store plaintext passwords in configuration files.
                    </div>
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?= $messageType ?>" role="alert">
                            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="/?r=password_hash">
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-1"></i>
                                Password to Hash
                            </label>
                            <div class="input-group">
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       value="<?= htmlspecialchars($password) ?>"
                                       placeholder="Enter your new password"
                                       required>
                                <button type="button" 
                                        class="btn btn-outline-secondary" 
                                        id="togglePassword">
                                    <i class="fas fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                Choose a strong password with at least 12 characters, including uppercase, lowercase, numbers, and symbols.
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-cog me-2"></i>
                                Generate Hash
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!empty($hash)): ?>
                        <div class="mt-4">
                            <h5 class="text-success">
                                <i class="fas fa-check-circle me-2"></i>
                                Generated Hash
                            </h5>
                            <div class="alert alert-light border">
                                <div class="mb-2">
                                    <strong>Password:</strong> <code><?= htmlspecialchars($password) ?></code>
                                </div>
                                <div class="mb-3">
                                    <strong>Hash:</strong>
                                </div>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control font-monospace" 
                                           id="hashOutput" 
                                           value="<?= htmlspecialchars($hash) ?>" 
                                           readonly>
                                    <button type="button" 
                                            class="btn btn-outline-primary" 
                                            id="copyHash">
                                        <i class="fas fa-copy me-1"></i>
                                        Copy
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-success" role="alert">
                                <h6><i class="fas fa-lightbulb me-2"></i>How to use this hash:</h6>
                                <ol class="mb-0">
                                    <li>Copy the hash above using the "Copy" button</li>
                                    <li>Open your <code>.env</code> file</li>
                                    <li>Update the appropriate password hash variable:
                                        <ul>
                                            <li><code>ADMIN_PASSWORD_HASH="paste_hash_here"</code></li>
                                            <li><code>MESH_PASSWORD_HASH="paste_hash_here"</code></li>
                                        </ul>
                                    </li>
                                    <li>Save the file and test the new password</li>
                                </ol>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <h5 class="text-primary">
                            <i class="fas fa-cog me-2"></i>
                            Environment Variables Reference
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Variable</th>
                                        <th>Purpose</th>
                                        <th>Default</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>ADMIN_USERNAME</code></td>
                                        <td>Administrator username</td>
                                        <td>admin</td>
                                    </tr>
                                    <tr>
                                        <td><code>ADMIN_PASSWORD_HASH</code></td>
                                        <td>Administrator password hash</td>
                                        <td>(default: "password")</td>
                                    </tr>
                                    <tr>
                                        <td><code>MESH_USERNAME</code></td>
                                        <td>Mesh user username</td>
                                        <td>mesh</td>
                                    </tr>
                                    <tr>
                                        <td><code>MESH_PASSWORD_HASH</code></td>
                                        <td>Mesh user password hash</td>
                                        <td>(default: "password")</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordField = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        eyeIcon.className = 'fas fa-eye-slash';
    } else {
        passwordField.type = 'password';
        eyeIcon.className = 'fas fa-eye';
    }
});

// Copy hash to clipboard
<?php if (!empty($hash)): ?>
document.getElementById('copyHash').addEventListener('click', function() {
    const hashField = document.getElementById('hashOutput');
    const copyButton = this;
    
    hashField.select();
    hashField.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(hashField.value).then(function() {
        const originalText = copyButton.innerHTML;
        copyButton.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        copyButton.classList.remove('btn-outline-primary');
        copyButton.classList.add('btn-success');
        
        setTimeout(function() {
            copyButton.innerHTML = originalText;
            copyButton.classList.remove('btn-success');
            copyButton.classList.add('btn-outline-primary');
        }, 2000);
    });
});
<?php endif; ?>
</script>
