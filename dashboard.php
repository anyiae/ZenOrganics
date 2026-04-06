<?php
session_start();
require 'conexion.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. OBTENER DATOS PARA LOS GRÁFICOS (PHP)
// ==========================================

// --- A. Estadísticas Rápidas (KPIs) ---
$total_stock = (int) $pdo->query("SELECT SUM(stock_actual) FROM camara_frio")->fetchColumn();
$despachos_hoy = (int) $pdo->query("SELECT SUM(cantidad) FROM movimientos WHERE tipo='retiro' AND DATE(fecha) = CURDATE()")->fetchColumn();
$produccion_hoy = (int) $pdo->query("SELECT SUM(cantidad) FROM movimientos WHERE tipo='produccion' AND DATE(fecha) = CURDATE()")->fetchColumn();
$top_cliente_mes = $pdo->query("SELECT c.nombre FROM movimientos m JOIN clientes c ON m.cliente_id = c.id WHERE m.tipo='retiro' AND MONTH(m.fecha) = MONTH(CURDATE()) GROUP BY c.id ORDER BY SUM(m.cantidad) DESC LIMIT 1")->fetchColumn() ?: 'Sin datos';

// --- B. Gráfico 1: Dona (Distribución de Stock) ---
$stmtStock = $pdo->query("SELECT nombre_producto, stock_actual FROM camara_frio WHERE stock_actual > 0");
$nombres_stock = [];
$cantidades_stock = [];
foreach ($stmtStock->fetchAll() as $row) {
    $nombres_stock[] = $row['nombre_producto'];
    $cantidades_stock[] = (int) $row['stock_actual'];
}

// --- C. Gráfico 2: Área (Últimos 7 días Flujo Logístico) ---
$fechas_7dias = [];
$data_ingresos = [];
$data_retiros = [];
for ($i = 6; $i >= 0; $i--) {
    $fecha_db = date('Y-m-d', strtotime("-$i days"));
    $fechas_7dias[] = date('d M', strtotime("-$i days"));

    $stmtIn = $pdo->prepare("SELECT SUM(cantidad) FROM movimientos WHERE tipo='ingreso' AND DATE(fecha) = ?");
    $stmtIn->execute([$fecha_db]);
    $data_ingresos[] = (int) $stmtIn->fetchColumn();

    $stmtOut = $pdo->prepare("SELECT SUM(cantidad) FROM movimientos WHERE tipo='retiro' AND DATE(fecha) = ?");
    $stmtOut->execute([$fecha_db]);
    $data_retiros[] = (int) $stmtOut->fetchColumn();
}

// --- D. Gráfico 3: Barras (Top Clientes Histórico) ---
$stmtTopClientes = $pdo->query("SELECT c.nombre, SUM(m.cantidad) as total FROM movimientos m JOIN clientes c ON m.cliente_id = c.id WHERE m.tipo = 'retiro' GROUP BY c.id ORDER BY total DESC LIMIT 5");
$nombres_clientes = [];
$cantidades_clientes = [];
foreach ($stmtTopClientes->fetchAll() as $row) {
    $nombres_clientes[] = $row['nombre'];
    $cantidades_clientes[] = (int) $row['total'];
}

// --- E. Gráfico 4: Líneas (Control de Temperatura Últimos 7 Despachos) ---
$stmtTemp = $pdo->query("SELECT DATE_FORMAT(fecha, '%d/%m %H:%i') as dia_hora, temp_tofu, temp_camion FROM movimientos WHERE tipo = 'retiro' AND temp_tofu > 0 ORDER BY fecha DESC LIMIT 7");
$temp_fechas = [];
$temp_tofu_arr = [];
$temp_camion_arr = [];
$temps = array_reverse($stmtTemp->fetchAll()); // Invertir para que el más viejo salga a la izquierda
foreach ($temps as $row) {
    $temp_fechas[] = $row['dia_hora'];
    $temp_tofu_arr[] = (float) $row['temp_tofu'];
    $temp_camion_arr[] = (float) $row['temp_camion'];
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .kpi-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 4px 24px rgba(34, 41, 47, 0.05);
        display: flex;
        align-items: center;
        border: 1px solid var(--border-color);
    }

    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-right: 15px;
    }

    .dashboard-grid-main {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .dashboard-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .btn-acceso {
        background: white;
        border-radius: 10px;
        padding: 15px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: 0.3s;
        box-shadow: 0 4px 24px rgba(34, 41, 47, 0.05);
        border: 1px solid var(--border-color);
        text-decoration: none;
        color: inherit;
        margin-bottom: 12px;
    }

    .btn-acceso:hover {
        transform: translateY(-3px);
        border-color: var(--primary);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
    }

    .card-header-chart {
        padding: 20px 20px 0 20px;
        border-bottom: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chart-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-main);
        margin: 0;
    }

    .chart-subtitle {
        font-size: 13px;
        color: var(--text-muted);
        margin: 0;
    }

    @media (max-width: 1200px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .dashboard-grid-main,
        .dashboard-grid-3 {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <div style="margin-bottom: 25px;">
        <h2 style="font-weight: 600; color: var(--text-main); margin-bottom: 5px;">Dashboard Operativo</h2>
        <p style="color: var(--text-muted); margin:0;">Análisis en tiempo real de inventario y logística de Zenorganics.
        </p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #e0f2fe; color: #0284c7;"><i class='bx bx-cube-alt'></i></div>
            <div>
                <h3 style="margin: 0; font-size: 22px; font-weight: 700;"><?php echo number_format($total_stock); ?>
                </h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Stock Total (Cajas)</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #ffedd5; color: #ea580c;"><i class='bx bx-trending-up'></i></div>
            <div>
                <h3 style="margin: 0; font-size: 22px; font-weight: 700;"><?php echo number_format($despachos_hoy); ?>
                </h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Despachadas Hoy</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #dcfce7; color: #059669;"><i class='bx bx-check-shield'></i></div>
            <div>
                <h3 style="margin: 0; font-size: 22px; font-weight: 700;"><?php echo number_format($produccion_hoy); ?>
                </h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Producción Est. Hoy</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #f3e8ff; color: #9333ea;"><i class='bx bx-star'></i></div>
            <div>
                <h3
                    style="margin: 0; font-size: 16px; font-weight: 700; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;">
                    <?php echo $top_cliente_mes; ?>
                </h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Top Cliente del Mes</p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid-main">
        <div class="card" style="margin: 0; padding: 0;">
            <div class="card-header-chart">
                <div>
                    <h3 class="chart-title">Flujo Logístico</h3>
                    <p class="chart-subtitle">Ingresos vs Despachos (Últimos 7 días)</p>
                </div>
                <span
                    style="background: var(--primary-light); color: var(--primary); padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">Actualizado</span>
            </div>
            <div class="card-body" style="padding-top: 0;">
                <div id="chartArea"></div>
            </div>
        </div>

        <div>
            <h3
                style="font-size: 14px; font-weight: 600; color: var(--text-muted); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px;">
                Accesos Rápidos</h3>
            <a href="camara.php" class="btn-acceso">
                <div
                    style="background: var(--primary-light); color: var(--primary); padding: 12px; border-radius: 8px; margin-right: 15px;">
                    <i class='bx bx-fridge' style="font-size: 24px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 15px;">Cámara de Frío</h4>
                    <p style="margin: 0; color: var(--text-muted); font-size: 12px;">Gestión de Tofus</p>
                </div>
                <i class='bx bx-chevron-right' style="margin-left: auto; color: #ccc; font-size: 20px;"></i>
            </a>
            <a href="#" class="btn-acceso">
                <div
                    style="background: #e0f2fe; color: #0284c7; padding: 12px; border-radius: 8px; margin-right: 15px;">
                    <i class='bx bx-buildings' style="font-size: 24px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 15px;">Clientes Destino</h4>
                    <p style="margin: 0; color: var(--text-muted); font-size: 12px;">Directorio comercial</p>
                </div>
                <i class='bx bx-chevron-right' style="margin-left: auto; color: #ccc; font-size: 20px;"></i>
            </a>
            <?php if ($_SESSION['rol'] === 'admin'): ?>
                <a href="usuarios.php" class="btn-acceso">
                    <div
                        style="background: #f1f5f9; color: #475569; padding: 12px; border-radius: 8px; margin-right: 15px;">
                        <i class='bx bx-group' style="font-size: 24px;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 15px;">Control de Usuarios</h4>
                        <p style="margin: 0; color: var(--text-muted); font-size: 12px;">Accesos del sistema</p>
                    </div>
                    <i class='bx bx-chevron-right' style="margin-left: auto; color: #ccc; font-size: 20px;"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="dashboard-grid-3">
        <div class="card" style="margin: 0; padding: 0;">
            <div class="card-header-chart">
                <div>
                    <h3 class="chart-title">Top Clientes</h3>
                    <p class="chart-subtitle">Despachos históricos por empresa</p>
                </div>
            </div>
            <div class="card-body">
                <div id="chartBarras"></div>
            </div>
        </div>

        <div class="card" style="margin: 0; padding: 0;">
            <div class="card-header-chart">
                <div>
                    <h3 class="chart-title">Control de Temperatura</h3>
                    <p class="chart-subtitle">T° en los últimos 7 despachos</p>
                </div>
            </div>
            <div class="card-body">
                <div id="chartLineas"></div>
            </div>
        </div>

        <div class="card" style="margin: 0; padding: 0;">
            <div class="card-header-chart">
                <div>
                    <h3 class="chart-title">Inventario Actual</h3>
                    <p class="chart-subtitle">Distribución por tipo de Tofu</p>
                </div>
            </div>
            <div class="card-body" style="display:flex; justify-content:center; align-items:center;">
                <div id="chartDona"></div>
            </div>
        </div>
    </div>
</div>

<script>
    const fontFam = 'Public Sans, sans-serif';

    // 1. GRÁFICO ÁREA (FLUJO LOGÍSTICO)
    new ApexCharts(document.querySelector("#chartArea"), {
        series: [
            { name: 'Ingresos', data: <?php echo json_encode($data_ingresos); ?> },
            { name: 'Despachos', data: <?php echo json_encode($data_retiros); ?> }
        ],
        chart: { height: 300, type: 'area', fontFamily: fontFam, toolbar: { show: false }, zoom: { enabled: false } },
        colors: ['#10b981', '#ea580c'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05, stops: [0, 90, 100] } },
        xaxis: { categories: <?php echo json_encode($fechas_7dias); ?>, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { colors: '#82868b' } } },
        grid: { borderColor: '#ebebeb', strokeDashArray: 4 },
        legend: { position: 'top', horizontalAlign: 'left' }
    }).render();

    // 2. GRÁFICO BARRAS HORIZONTALES (TOP CLIENTES)
    new ApexCharts(document.querySelector("#chartBarras"), {
        series: [{ name: 'Cajas Despachadas', data: <?php echo json_encode($cantidades_clientes); ?> }],
        chart: { type: 'bar', height: 280, fontFamily: fontFam, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, distributed: true, dataLabels: { position: 'bottom' } } },
        colors: ['#0ea5e9', '#38bdf8', '#7dd3fc', '#bae6fd', '#e0f2fe'], // Gradiente azul
        dataLabels: { enabled: true, textAnchor: 'start', style: { colors: ['#fff'] }, offsetX: 10 },
        xaxis: { categories: <?php echo json_encode($nombres_clientes); ?>, labels: { style: { colors: '#82868b' } } },
        yaxis: { labels: { style: { colors: '#4b4b4b', fontWeight: 500 } } },
        grid: { show: false }
    }).render();

    // 3. GRÁFICO LÍNEAS (TEMPERATURAS)
    new ApexCharts(document.querySelector("#chartLineas"), {
        series: [
            { name: 'T° Tofu', data: <?php echo json_encode($temp_tofu_arr); ?> },
            { name: 'T° Camión', data: <?php echo json_encode($temp_camion_arr); ?> }
        ],
        chart: { height: 280, type: 'line', fontFamily: fontFam, toolbar: { show: false }, dropShadow: { enabled: true, top: 5, left: 0, blur: 4, opacity: 0.1 } },
        colors: ['#f59e0b', '#3b82f6'],
        stroke: { curve: 'smooth', width: 4 },
        markers: { size: 5, hover: { size: 7 } },
        dataLabels: { enabled: false },
        xaxis: { categories: <?php echo json_encode($temp_fechas); ?>, tooltip: { enabled: false }, labels: { style: { fontSize: '10px' } } },
        yaxis: { title: { text: 'Grados Celcius (°C)' } },
        grid: { borderColor: '#ebebeb', strokeDashArray: 4 },
        legend: { position: 'top' }
    }).render();

    // 4. GRÁFICO DONA (INVENTARIO)
    new ApexCharts(document.querySelector("#chartDona"), {
        series: <?php echo empty($cantidades_stock) ? '[0]' : json_encode($cantidades_stock); ?>,
        labels: <?php echo empty($nombres_stock) ? '["Sin Stock"]' : json_encode($nombres_stock); ?>,
        chart: { type: 'donut', height: 290, fontFamily: fontFam },
        colors: ['#10b981', '#8b5cf6', '#0ea5e9'],
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: { fontSize: '12px', color: '#82868b' },
                        value: { fontSize: '24px', fontWeight: 600, color: '#4b4b4b', formatter: function (val) { return val + " u." } },
                        total: { show: true, label: 'Stock Total', color: '#82868b', formatter: function (w) { return w.globals.seriesTotals.reduce((a, b) => { return a + b }, 0) } }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 3, colors: ['#ffffff'] },
        legend: { position: 'bottom' }
    }).render();
</script>

<?php include 'includes/footer.php'; ?>