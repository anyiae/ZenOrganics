<?php
// Asegurarnos de que si alguien entra aquí, su sesión se cierre por seguridad
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Login - Zenorganics</title>
    <link rel="icon" href="logo1.png" type="image/png">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="login-bg">
    <div class="login-card">
        <img src="logo1.png" alt="Zenorganics" style="max-height: 60px; margin-bottom: 20px;">
        <h2 style="margin-bottom: 5px; color: var(--text-main);">¡Bienvenido! 👋</h2>
        <p style="color: var(--text-muted); margin-bottom: 25px; font-size: 14px;">Ingresa a tu bodega digital</p>

        <form action="login.php" method="POST">
            <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
            <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
            <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
        </form>
    </div>
</body>

</html>