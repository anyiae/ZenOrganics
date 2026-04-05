<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="content-wrapper">
    <div style="margin-bottom: 30px;">
        <h2>Resumen Operativo</h2>
        <p style="color: var(--text-muted);">Bienvenido al panel de control de Zenorganics.</p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">

        <div class="card"
            style="display: flex; flex-direction: row; align-items: center; padding: 20px; cursor: pointer; transition: 0.2s;"
            onclick="window.location.href='camara.php'">
            <div
                style="background: var(--primary-light); color: var(--primary); padding: 18px; border-radius: 12px; margin-right: 15px;">
                <i class='bx bx-fridge' style="font-size: 32px;"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 20px;">Cámara de Frío</h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Controlar ingresos y despachos</p>
            </div>
        </div>

        <div class="card" style="display: flex; flex-direction: row; align-items: center; padding: 20px;">
            <div style="background: #e0f2fe; color: #0284c7; padding: 18px; border-radius: 12px; margin-right: 15px;">
                <i class='bx bx-buildings' style="font-size: 32px;"></i>
            </div>
            <div>
                <h3 style="margin: 0; font-size: 20px;">Clientes</h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Gestión de empresas destino</p>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>