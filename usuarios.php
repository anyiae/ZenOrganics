<?php
session_start();
require 'conexion.php';

// ESCUDO DE SEGURIDAD: Solo Administradores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Traer usuarios
$stmt = $pdo->query("SELECT id, nombre, usuario, rol FROM usuarios");
$usuarios = $stmt->fetchAll();

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 20px; font-weight: 600; margin: 0;">Gestión de Usuarios</h2>
            <button class="btn btn-primary" onclick="crearUsuario()">
                <i class='bx bx-user-plus'></i> Nuevo Usuario
            </button>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario (Login)</th>
                    <th>Rol en el sistema</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td style="font-weight: 500;">
                            <?php echo $u['nombre']; ?>
                        </td>
                        <td style="color: var(--text-muted);">
                            <?php echo $u['usuario']; ?>
                        </td>
                        <td>
                            <?php if ($u['rol'] == 'admin'): ?>
                                <span
                                    style="background: #e0f2fe; color: #0284c7; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">ADMINISTRADOR</span>
                            <?php else: ?>
                                <span
                                    style="background: var(--border-color); color: var(--text-main); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">LECTOR</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn" style="background: #f1f1f1; color: #333; padding: 6px 12px;"
                                onclick="alert('Función de editar en construcción')">
                                <i class='bx bx-edit'></i> Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function crearUsuario() {
        Swal.fire({
            title: '<h3 style="margin:0; font-weight:600; color:var(--text-main);">Crear Usuario</h3>',
            html: `
            <div style="text-align: left; margin-top: 10px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">NOMBRE COMPLETO</label>
                <input type="text" id="nuevo_nombre" class="form-control" placeholder="Ej: Juan Pérez" style="margin-bottom: 15px;">
                
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">USUARIO DE LOGIN</label>
                <input type="text" id="nuevo_usuario" class="form-control" placeholder="Ej: juanp" style="margin-bottom: 15px;">
                
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">CONTRASEÑA</label>
                <input type="password" id="nueva_clave" class="form-control" placeholder="Mínimo 6 caracteres" style="margin-bottom: 15px;">

                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">ROL</label>
                <select id="nuevo_rol" class="form-control">
                    <option value="lector">Lector (Solo operaciones)</option>
                    <option value="admin">Administrador (Acceso total)</option>
                </select>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: 'Crear Usuario',
            customClass: { popup: 'card', confirmButton: 'btn btn-primary', cancelButton: 'btn' },
            buttonsStyling: false,
            preConfirm: () => {
                // Aquí enviarías los datos a un archivo PHP por Fetch como hicimos con la bodega
                Swal.showValidationMessage('Esta función la conectaremos a la base de datos en el próximo paso si lo deseas.');
                return false;
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>