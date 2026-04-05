<?php
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Zenorganics - Bodega Digital</title>
    <link rel="icon" href="logo1.png" type="image/png">

    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="layout-wrapper">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="logo1.png" alt="Zenorganics" style="max-height: 45px;">
            </div>

            <nav class="sidebar-menu">
                <a href="dashboard.php"
                    class="menu-link <?php echo ($pagina_actual == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class='bx bx-home-smile'></i> <span>Dashboard</span>
                </a>

                <div class="menu-title">Operaciones</div>
                <a href="camara.php" class="menu-link <?php echo ($pagina_actual == 'camara.php') ? 'active' : ''; ?>">
                    <i class='bx bx-fridge'></i> <span>Cámara de Frío</span>
                </a>
                <a href="#" class="menu-link">
                    <i class='bx bx-buildings'></i> <span>Clientes</span>
                </a>

                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <div class="menu-title">Administración</div>
                    <a href="usuarios.php" class="menu-link">
                        <i class='bx bx-group'></i> <span>Usuarios</span>
                    </a>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="main-content">