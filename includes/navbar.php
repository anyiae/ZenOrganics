<header class="navbar">
    <div style="display: flex; align-items: center;">
        <div style="text-align: right; line-height: 1.2;">
            <div style="font-weight: 600; color: var(--text-main);">
                <?php echo isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario'; ?>
            </div>
            <span class="badge-rol"><?php echo isset($_SESSION['rol']) ? strtoupper($_SESSION['rol']) : ''; ?></span>
        </div>
        <a href="index.php"
            style="margin-left: 20px; color: #EB3D63; display: flex; align-items: center; text-decoration: none; padding: 8px; background: #fff0f3; border-radius: 50%; transition: 0.2s;"
            title="Cerrar Sesión">
            <i class='bx bx-power-off' style="font-size: 22px;"></i>
        </a>
    </div>
</header>