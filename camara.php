<?php
session_start();
require 'conexion.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// 1. Stock Actual (Solo para la pestaña Inventario)
$stmt = $pdo->query("SELECT * FROM camara_frio");
$productos = $stmt->fetchAll();

// 2. Historial General (SOLO inventario real: ingresos y retiros)
$sqlHistorial = "SELECT m.*, p.nombre_producto, u.nombre as nombre_usuario, c.nombre as nombre_cliente 
                 FROM movimientos m
                 LEFT JOIN camara_frio p ON m.producto_id = p.id
                 LEFT JOIN usuarios u ON m.usuario_id = u.id
                 LEFT JOIN clientes c ON m.cliente_id = c.id
                 WHERE m.tipo IN ('ingreso', 'retiro') 
                 ORDER BY m.fecha DESC";
$historial = $pdo->query($sqlHistorial)->fetchAll();

// 3. Historial Específico de Canastas (SOLO registros de estimación/producción)
$sqlCanastas = "SELECT * FROM movimientos 
                WHERE tipo = 'produccion' 
                ORDER BY fecha DESC LIMIT 20";
$historialCanastas = $pdo->query($sqlCanastas)->fetchAll();

// 4. Clientes
$stmtClientes = $pdo->query("SELECT * FROM clientes");
$opcionesClientes = "";
foreach ($stmtClientes->fetchAll() as $c) {
    $opcionesClientes .= "<option value='" . $c['id'] . "'>" . $c['nombre'] . "</option>";
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>

<style>
    .nav-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    .tab-btn { padding: 10px 20px; border: none; background: #f8f9fa; color: #666; cursor: pointer; border-radius: 8px; font-weight: 600; transition: 0.3s; }
    .tab-btn.active { background: var(--primary); color: white; }
    .tab-content { display: none; animation: fadeIn 0.4s; }
    .tab-content.active { display: block; }
    .label-sm { font-size: 11px; font-weight: 700; color: #888; display: block; margin-top: 12px; text-transform: uppercase; }
    .estimacion-box { background: #fff; border: 1px solid #ddd; padding: 15px; margin-top: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .selector-metodo { display: flex; gap: 10px; margin: 15px 0; background: #eee; padding: 5px; border-radius: 6px; }
    .metodo-btn { flex: 1; border: none; padding: 8px; cursor: pointer; border-radius: 4px; font-size: 11px; font-weight: bold; }
    .metodo-btn.active { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="content-wrapper">
    <div class="nav-tabs">
        <button class="tab-btn active" onclick="openTab(event, 'stock')">📦 Inventario Real</button>
        <button class="tab-btn" onclick="openTab(event, 'canastas')">🧺 Producción y Estimación</button>
        <button class="tab-btn" onclick="openTab(event, 'historial')">📜 Historial Logístico</button>
    </div>

    <div id="stock" class="tab-content active">
        <div class="card">
            <div class="card-header"><h2>Control de Cajas en Stock</h2></div>
            <table class="table">
                <thead><tr><th>Producto</th><th>Stock Actual</th><th>Acciones</th></tr></thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><strong><?php echo $p['nombre_producto']; ?></strong></td>
                        <td><span class="badge" style="background:var(--primary-light); color:var(--primary);"><?php echo $p['stock_actual']; ?> Cajas</span></td>
                        <td>
                            <button class="btn" style="background:#10b981; color:white;" onclick="abrirMovimiento('ingreso', <?php echo $p['id']; ?>, '<?php echo $p['nombre_producto']; ?>')">Ingresar</button>
                            <button class="btn" style="background:#f39c12; color:white;" onclick="abrirMovimiento('retiro', <?php echo $p['id']; ?>, '<?php echo $p['nombre_producto']; ?>', <?php echo $p['stock_actual']; ?>)">Despachar</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="canastas" class="tab-content">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px;">
            <div class="card">
                <div class="card-header"><h2>Calculadora de Cajas</h2></div>
                <div class="card-body">
                    <div class="selector-metodo">
                        <button id="btn-peso" class="metodo-btn active" onclick="setMetodo('peso')">POR PESO</button>
                        <button id="btn-unidad" class="metodo-btn" onclick="setMetodo('unidad')">POR UNIDADES</button>
                    </div>

                    <div id="div-peso">
                        <label class="label-sm">Total Kilogramos</label>
                        <input type="number" step="0.1" id="calc_peso" class="form-control" oninput="calcularCajas()">
                    </div>
                    <div id="div-unidad" style="display:none;">
                        <label class="label-sm">Total Tofus</label>
                        <input type="number" id="calc_tofus" class="form-control" oninput="calcularCajas()">
                    </div>

                    <div id="resultado_calc" class="estimacion-box" style="display:none;">
                        <p style="margin:0; font-size:12px;">Cajas Proyectadas:</p>
                        <h2 id="res_promedio" style="margin:5px 0; color:#2c3e50;">0</h2>
                        <small id="res_detalle" style="color:#666;"></small>
                        <hr>
                        <button class="btn" style="background:#34495e; color:white; width:100%" onclick="registrarProduccionInformativa()">Guardar en Registro Diario</button>
                        <p style="font-size:10px; color:red; margin-top:8px; text-align:center;">* Este registro no suma cajas al inventario real.</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Planificación / Producción Guardada</h2></div>
                <table class="table" style="font-size: 12px;">
                    <thead><tr><th>Fecha/Hora</th><th>Cajas Est.</th><th>Detalle de Cálculo</th></tr></thead>
                    <tbody>
                        <?php if(empty($historialCanastas)): ?>
                            <tr><td colspan="3" style="text-align:center; color:#999;">No hay registros de producción hoy</td></tr>
                        <?php endif; ?>
                        <?php foreach ($historialCanastas as $hc): ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($hc['fecha'])); ?></td>
                            <td><strong style="color:var(--primary);"><?php echo $hc['cantidad']; ?></strong></td>
                            <td><small><?php echo $hc['observaciones']; ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="historial" class="tab-content">
        <div class="card">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h2>Logística y Movimientos</h2>
                <button class="btn" style="background:#27ae60; color:white;" onclick="exportarExcel()">📗 Exportar Todo</button>
            </div>
            <table class="table" id="tabla-movimientos" style="font-size:12px;">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Producto</th><th>Cant.</th><th>T°</th><th>Cliente</th><th>Obs.</th></tr></thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?php echo date('d/m/y H:i', strtotime($h['fecha'])); ?></td>
                        <td>
                            <span class="badge" style="background:<?php echo $h['tipo']=='ingreso'?'#d1fae5':'#ffedd5';?>; color:<?php echo $h['tipo']=='ingreso'?'#065f46':'#9a3412';?>">
                                <?php echo strtoupper($h['tipo']); ?>
                            </span>
                        </td>
                        <td><?php echo $h['nombre_producto'] ?: 'N/A (Producción)'; ?></td>
                        <td><?php echo $h['cantidad']; ?></td>
                        <td><?php echo $h['temp_tofu'] ? $h['temp_tofu']."°/".$h['temp_camion']."°" : '-'; ?></td>
                        <td><?php echo $h['nombre_cliente'] ?: '-'; ?></td>
                        <td><small><?php echo $h['observaciones']; ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    let metodoActual = 'peso';

    function openTab(evt, tabName) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabName).classList.add('active');
        evt.currentTarget.classList.add('active');
    }

    function setMetodo(m) {
        metodoActual = m;
        document.getElementById('btn-peso').classList.toggle('active', m === 'peso');
        document.getElementById('btn-unidad').classList.toggle('active', m === 'unidad');
        document.getElementById('div-peso').style.display = m === 'peso' ? 'block' : 'none';
        document.getElementById('div-unidad').style.display = m === 'unidad' ? 'block' : 'none';
        document.getElementById('resultado_calc').style.display = 'none';
    }

    function calcularCajas() {
        const resH2 = document.getElementById('res_promedio');
        const resDet = document.getElementById('res_detalle');
        const divRes = document.getElementById('resultado_calc');

        if (metodoActual === 'peso') {
            const peso = parseFloat(document.getElementById('calc_peso').value);
            if (peso > 0) {
                const prom = Math.round(peso / 4.25);
                resH2.innerText = prom + " Cajas";
                resDet.innerText = `Estimado 4.25kg por caja`;
                divRes.style.display = 'block';
            }
        } else {
            const tofus = parseInt(document.getElementById('calc_tofus').value);
            if (tofus > 0) {
                const cajas = Math.floor(tofus / 13);
                resH2.innerText = cajas + " Cajas";
                resDet.innerText = `13 unidades por caja (Resto: ${tofus % 13})`;
                divRes.style.display = 'block';
            }
        }
    }

    // REGISTRO SIN AFECTAR STOCK
    function registrarProduccionInformativa() {
        const cant = document.getElementById('res_promedio').innerText.split(' ')[0];
        const obs = metodoActual === 'peso' 
            ? `SOLO REGISTRO: Producción de ${document.getElementById('calc_peso').value}kg.` 
            : `SOLO REGISTRO: Producción de ${document.getElementById('calc_tofus').value} tofus.`;

        // Enviamos 'produccion' como tipo para que el PHP sepa NO hacer UPDATE a stock
        fetch('ajax_movimiento.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                accion: 'produccion', // <-- Cambiado de 'ingreso' a 'produccion'
                producto_id: null, 
                cantidad_total: cant, 
                observaciones: obs 
            })
        }).then(res => res.json()).then(data => { if(data.success) location.reload(); });
    }

    // MOVIMIENTOS QUE SÍ AFECTAN STOCK
    function abrirMovimiento(tipo, id, nombre, stock = 0) {
        let logisticaHTML = '';
        if (tipo === 'retiro') {
            logisticaHTML = `
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <div><label class="label-sm">T° Tofu</label><input type="number" step="0.1" id="t_tofu" class="form-control"></div>
                    <div><label class="label-sm">T° Camión</label><input type="number" step="0.1" id="t_cam" class="form-control"></div>
                    <div><label class="label-sm">H. Llegada</label><input type="time" id="h_lleg" class="form-control"></div>
                    <div><label class="label-sm">H. Salida</label><input type="time" id="h_sal" class="form-control"></div>
                </div>
                <label class="label-sm">Cliente Destino</label>
                <select id="cli_id" class="form-control"><?php echo $opcionesClientes; ?></select>
            `;
        }

        Swal.fire({
            title: tipo === 'ingreso' ? 'Ingreso Stock Físico' : 'Despacho a Cliente',
            html: `<div style="text-align:left;">
                <label class="label-sm">Cantidad de Cajas (${nombre})</label>
                <input type="number" id="cant_c" class="form-control">
                ${logisticaHTML}
                <label class="label-sm">Notas</label>
                <textarea id="not" class="form-control"></textarea>
            </div>`,
            preConfirm: () => {
                const c = document.getElementById('cant_c').value;
                if (!c || c <= 0) return Swal.showValidationMessage('Cantidad requerida');
                if (tipo === 'retiro' && parseInt(c) > stock) return Swal.showValidationMessage('Stock insuficiente');
                
                const d = { cantidad_total: c, observaciones: document.getElementById('not').value };
                if (tipo === 'retiro') {
                    d.cliente_id = document.getElementById('cli_id').value;
                    d.temp_tofu = document.getElementById('t_tofu').value;
                    d.temp_camion = document.getElementById('t_cam').value;
                    d.hora_llegada = document.getElementById('h_lleg').value;
                    d.hora_despacho = document.getElementById('h_sal').value;
                }
                return d;
            }
        }).then(r => { if(r.isConfirmed) enviarFetch(tipo, id, r.value.cantidad_total, r.value); });
    }

    function enviarFetch(t, id, c, extra) {
        fetch('ajax_movimiento.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ accion: t, producto_id: id, cantidad_total: c, ...extra })
        }).then(res => res.json()).then(data => { if(data.success) location.reload(); });
    }

    function exportarExcel() {
        const table = document.getElementById("tabla-movimientos");
        const wb = XLSX.utils.table_to_book(table, { sheet: "Historial" });
        XLSX.writeFile(wb, "Reporte_Camara.xlsx");
    }
</script>

<?php include 'includes/footer.php'; ?>