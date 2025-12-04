<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-md">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-users me-2"></i>Sistema CRUD D&Z
        </a>
        
        <div class="navbar-nav ms-auto">
            <?php if (isset($_SESSION['usuario_nome'])): ?>
                <span class="navbar-text me-3">
                    Olá, <strong><?= $_SESSION['usuario_nome']; ?></strong>
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Sair
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>
