<?php
session_start();
require 'conexion.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Traer productos
$stmt = $pdo->query("SELECT * FROM camara_frio");
$productos = $stmt->fetchAll();

// Traer clientes
$stmtClientes = $pdo->query("SELECT * FROM clientes");
$opcionesClientes = "";
foreach ($stmtClientes->fetchAll() as $c) {
    $opcionesClientes .= "<option value='" . $c['id'] . "'>" . $c['nombre'] . "</option>";
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h2 style="font-size: 20px; font-weight: 600; margin: 0;">Stock: Cámara de Frío (Tofus)</h2>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Stock Actual</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td style="color: var(--text-muted);">#<?php echo $p['id']; ?></td>
                        <td style="font-weight: 500;"><?php echo $p['nombre_producto']; ?></td>
                        <td>
                            <span
                                style="background: var(--primary-light); color: var(--primary); padding: 5px 12px; border-radius: 6px; font-weight: 600;">
                                <?php echo $p['stock_actual']; ?> Unidades
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-primary"
                                style="background: #10b981; padding: 6px 12px; margin-right: 5px;"
                                onclick="abrirIngreso(<?php echo $p['id']; ?>, '<?php echo $p['nombre_producto']; ?>')">
                                <i class='bx bx-plus-circle'></i> Ingresar
                            </button>
                            <button class="btn btn-primary" style="background: #f39c12; padding: 6px 12px;"
                                onclick="abrirRetiro(<?php echo $p['id']; ?>, '<?php echo $p['nombre_producto']; ?>', <?php echo $p['stock_actual']; ?>)">
                                <i class='bx bx-minus-circle'></i> Despachar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Función auxiliar para generar el HTML del selector de empaque
    function getEmpaqueHTML(isRetiro = false) {
        return `
        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">TIPO DE EMPAQUE</label>
        <select id="tipo_empaque" class="form-control" style="margin-bottom: 15px; cursor:pointer;" onchange="toggleCajaInput()">
            <option value="pallet">Pallets Completos (50 unidades c/u)</option>
            <option value="caja">Cajas (Personalizado)</option>
            <option value="individual">Unidades Individuales</option>
        </select>

        <div id="div_unidades_caja" style="display: none;">
            <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">UNIDADES POR CAJA</label>
            <input type="number" id="unidades_caja" class="form-control" placeholder="Ej: 10" value="10" style="margin-bottom: 15px;">
        </div>

        <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">CANTIDAD DE <span id="lbl_cantidad">PALLETS</span></label>
        <input type="number" id="cantidad_ingresada" class="form-control" placeholder="Ej: 2" style="margin-bottom: 20px;">
    `;
    }

    // Función auxiliar para que el formulario cambie en vivo
    function toggleCajaInput() {
        const tipo = document.getElementById('tipo_empaque').value;
        const divCaja = document.getElementById('div_unidades_caja');
        const lblCantidad = document.getElementById('lbl_cantidad');

        if (tipo === 'caja') {
            divCaja.style.display = 'block';
            lblCantidad.innerText = 'CAJAS';
        } else if (tipo === 'pallet') {
            divCaja.style.display = 'none';
            lblCantidad.innerText = 'PALLETS';
        } else {
            divCaja.style.display = 'none';
            lblCantidad.innerText = 'UNIDADES';
        }
    }

    // Lógica para calcular el total antes de enviar a PHP
    function calcularTotalUnidades() {
        const tipo = document.getElementById('tipo_empaque').value;
        const cantidad = parseInt(document.getElementById('cantidad_ingresada').value) || 0;

        if (tipo === 'pallet') return cantidad * 50;
        if (tipo === 'caja') {
            const porCaja = parseInt(document.getElementById('unidades_caja').value) || 0;
            return cantidad * porCaja;
        }
        return cantidad; // Individual
    }

    // --- SWEETALERT PARA INGRESAR STOCK ---
    function abrirIngreso(idProducto, nombre) {
        Swal.fire({
            title: '<h3 style="margin:0; font-weight:600; color:var(--text-main);">Ingresar Tofu</h3>',
            html: `
            <div style="text-align: left; margin-top: 10px; color: var(--text-main);">
                <p style="margin: 0 0 15px 0; font-size: 14px;">Producto: <strong style="color: var(--primary);">${nombre}</strong></p>
                ${getEmpaqueHTML()}
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="bx bx-check-circle" style="font-size:18px;"></i> Guardar Ingreso',
            cancelButtonText: 'Cancelar',
            customClass: { popup: 'card', confirmButton: 'btn btn-primary', cancelButton: 'btn' },
            buttonsStyling: false,
            preConfirm: () => {
                const totalUnidades = calcularTotalUnidades();
                if (totalUnidades <= 0) {
                    Swal.showValidationMessage('Ingresa una cantidad válida');
                    return false;
                }
                return { total_unidades: totalUnidades }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('ajax_movimiento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion: 'ingreso',
                        producto_id: idProducto,
                        cantidad_total: result.value.total_unidades
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ title: '¡Ingreso Exitoso!', text: `Se sumaron ${result.value.total_unidades} unidades de tofu.`, icon: 'success', customClass: { confirmButton: 'btn btn-primary', popup: 'card' }, buttonsStyling: false }).then(() => location.reload());
                        } else {
                            Swal.fire({ title: 'Error', text: data.mensaje, icon: 'error', customClass: { confirmButton: 'btn btn-primary', popup: 'card' }, buttonsStyling: false });
                        }
                    });
            }
        });
    }

    // --- SWEETALERT PARA DESPACHAR STOCK ---
    function abrirRetiro(idProducto, nombre, stockActual) {
        Swal.fire({
            title: '<h3 style="margin:0; font-weight:600; color:var(--text-main);">Despachar Tofu</h3>',
            html: `
            <div style="text-align: left; margin-top: 10px; color: var(--text-main);">
                <div style="background: var(--bg-body); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin: 0 0 5px 0; font-size: 14px;">Producto: <strong style="color: var(--primary);">${nombre}</strong></p>
                    <p style="margin: 0; font-size: 14px;">Stock disponible: <strong>${stockActual} Unidades</strong></p>
                </div>
                ${getEmpaqueHTML(true)}
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-muted);">CLIENTE DESTINO</label>
                <select id="cliente" class="form-control" style="box-sizing: border-box; cursor: pointer;">
                    <option value="" disabled selected>-- Selecciona el cliente --</option>
                    <?php echo $opcionesClientes; ?>
                </select>
            </div>
        `,
            showCancelButton: true,
            confirmButtonText: '<i class="bx bx-check-circle" style="font-size:18px;"></i> Confirmar Salida',
            cancelButtonText: 'Cancelar',
            customClass: { popup: 'card', confirmButton: 'btn btn-primary', cancelButton: 'btn' },
            buttonsStyling: false,
            preConfirm: () => {
                const totalUnidades = calcularTotalUnidades();
                const cliente = document.getElementById('cliente').value;

                if (totalUnidades <= 0) {
                    Swal.showValidationMessage('Ingresa una cantidad válida'); return false;
                }
                if (totalUnidades > parseInt(stockActual)) {
                    Swal.showValidationMessage(`No puedes sacar ${totalUnidades} unidades. Solo hay ${stockActual}.`); return false;
                }
                if (!cliente) {
                    Swal.showValidationMessage('Por favor selecciona un cliente'); return false;
                }
                return { total_unidades: totalUnidades, cliente: cliente }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('ajax_movimiento.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        accion: 'retiro',
                        producto_id: idProducto,
                        cantidad_total: result.value.total_unidades,
                        cliente_id: result.value.cliente
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({ title: '¡Despacho Exitoso!', text: `Se descontaron ${result.value.total_unidades} unidades.`, icon: 'success', customClass: { confirmButton: 'btn btn-primary', popup: 'card' }, buttonsStyling: false }).then(() => location.reload());
                        } else {
                            Swal.fire({ title: 'Error', text: data.mensaje, icon: 'error', customClass: { confirmButton: 'btn btn-primary', popup: 'card' }, buttonsStyling: false });
                        }
                    });
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>