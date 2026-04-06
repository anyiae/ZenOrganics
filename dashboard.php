<?php
session_start();
require 'conexion.php'; // Necesitamos la conexión para los gráficos
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// ==========================================
// 1. OBTENER DATOS PARA LOS GRÁFICOS (PHP)
// ==========================================

// A. Estadísticas Rápidas (Tarjetas superiores)
$stmt = $pdo->query("SELECT SUM(stock_actual) FROM camara_frio");
$total_stock = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(cantidad) FROM movimientos WHERE tipo='retiro' AND DATE(fecha) = CURDATE()");
$despachos_hoy = (int) $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(cantidad) FROM movimientos WHERE tipo='produccion' AND DATE(fecha) = CURDATE()");
$produccion_hoy = (int) $stmt->fetchColumn();

// B. Datos para el Gráfico de Dona (Distribución de Stock)
$stmtStock = $pdo->query("SELECT nombre_producto, stock_actual FROM camara_frio WHERE stock_actual > 0");
$nombres_stock = [];
$cantidades_stock = [];
foreach ($stmtStock->fetchAll() as $row) {
    $nombres_stock[] = $row['nombre_producto'];
    $cantidades_stock[] = (int) $row['stock_actual'];
}

// C. Datos para el Gráfico de Área (Últimos 7 días de ingresos y despachos)
$fechas_7dias = [];
$data_ingresos = [];
$data_retiros = [];

for ($i = 6; $i >= 0; $i--) {
    $fecha_db = date('Y-m-d', strtotime("-$i days"));
    $fechas_7dias[] = date('d M', strtotime("-$i days")); // Ej: "05 Abr"

    // Sumar ingresos del día
    $stmtIn = $pdo->prepare("SELECT SUM(cantidad) FROM movimientos WHERE tipo='ingreso' AND DATE(fecha) = ?");
    $stmtIn->execute([$fecha_db]);
    $data_ingresos[] = (int) $stmtIn->fetchColumn();

    // Sumar retiros del día
    $stmtOut = $pdo->prepare("SELECT SUM(cantidad) FROM movimientos WHERE tipo='retiro' AND DATE(fecha) = ?");
    $stmtOut->execute([$fecha_db]);
    $data_retiros[] = (int) $stmtOut->fetchColumn();
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<style>
    /* Estilos específicos para estructurar el dashboard */
    .dashboard-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
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

    .btn-acceso {
        background: white;
        border-radius: 10px;
        padding: 20px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: 0.3s;
        box-shadow: 0 4px 24px rgba(34, 41, 47, 0.05);
        border: 1px solid transparent;
        text-decoration: none;
        color: inherit;
        margin-bottom: 15px;
    }

    .btn-acceso:hover {
        transform: translateY(-3px);
        border-color: var(--primary);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
    }

    @media (max-width: 992px) {

        .dashboard-grid,
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <div style="margin-bottom: 25px;">
        <h2 style="font-weight: 600; color: var(--text-main); margin-bottom: 5px;">Dashboard Operativo</h2>
        <p style="color: var(--text-muted); margin:0;">Resumen general de logística e inventario de Zenorganics.</p>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #e0f2fe; color: #0284c7;"><i class='bx bx-cube-alt'></i></div>
            <div>
                <h3 style="margin: 0; font-size: 24px; font-weight: 700;"><?php echo $total_stock; ?></h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Tofus en Stock Total</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #ffedd5; color: #ea580c;"><i class='bx bx-trending-up'></i></div>
            <div>
                <h3 style="margin: 0; font-size: 24px; font-weight: 700;"><?php echo $despachos_hoy; ?></h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Unidades Despachadas Hoy</p>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon" style="background: #dcfce7; color: #059669;"><i class='bx bx-check-shield'></i></div>
            <div>
                <h3 style="margin: 0; font-size: 24px; font-weight: 700;"><?php echo $produccion_hoy; ?></h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 13px;">Producción Proyectada Hoy</p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">

        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="border-bottom: none; padding-bottom: 0;">
                <h3 style="font-size: 18px; font-weight: 600; margin:0;">Flujo de Logística (Últimos 7 días)</h3>
                <span class="badge"
                    style="background: var(--primary-light); color: var(--primary); padding: 5px 10px; border-radius: 6px;">Actualizado</span>
            </div>
            <div class="card-body">
                <div id="graficoArea"></div>
            </div>
        </div>

        <div>
            <h3
                style="font-size: 16px; font-weight: 600; color: var(--text-muted); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;">
                Accesos Rápidos</h3>

            <a href="camara.php" class="btn-acceso">
                <div
                    style="background: var(--primary-light); color: var(--primary); padding: 15px; border-radius: 10px; margin-right: 15px;">
                    <i class='bx bx-fridge' style="font-size: 28px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 16px;">Cámara de Frío</h4>
                    <p style="margin: 0; color: var(--text-muted); font-size: 12px;">Ingresos y despachos</p>
                </div>
                <i class='bx bx-chevron-right' style="margin-left: auto; color: #ccc; font-size: 24px;"></i>
            </a>

            <a href="#" class="btn-acceso">
                <div
                    style="background: #f3e8ff; color: #7e22ce; padding: 15px; border-radius: 10px; margin-right: 15px;">
                    <i class='bx bx-buildings' style="font-size: 28px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 16px;">Clientes</h4>
                    <p style="margin: 0; color: var(--text-muted); font-size: 12px;">Directorio de empresas</p>
                </div>
                <i class='bx bx-chevron-right' style="margin-left: auto; color: #ccc; font-size: 24px;"></i>
            </a>

            <?php if ($_SESSION['rol'] === 'admin'): ?>
                <a href="usuarios.php" class="btn-acceso">
                    <div
                        style="background: #f1f5f9; color: #475569; padding: 15px; border-radius: 10px; margin-right: 15px;">
                        <i class='bx bx-group' style="font-size: 28px;"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 16px;">Usuarios</h4>
                        <p style="margin: 0; color: var(--text-muted); font-size: 12px;">Gestión de personal</p>
                    </div>
                    <i class='bx bx-chevron-right' style="margin-left: auto; color: #ccc; font-size: 24px;"></i>
                </a>
            <?php endif; ?>
        </div>

    </div>

    <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
        <div class="card">
            <div class="card-header" style="border-bottom: none;">
                <h3 style="font-size: 18px; font-weight: 600; margin:0;">Distribución de Inventario</h3>
            </div>
            <div class="card-body" style="display: flex; justify-content: center;">
                <div id="graficoDona"></div>
            </div>
        </div>

        <div class="card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
            <div class="card-body"
                style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; height: 100%;">
                <i class='bx bx-leaf' style="font-size: 60px; margin-bottom: 15px; opacity: 0.9;"></i>
                <h2 style="color: white; font-weight: 600; margin-bottom: 10px;">Zenorganics System</h2>
                <p style="opacity: 0.8; font-size: 14px; max-width: 80%;">El sistema está monitoreando la cámara de frío
                    y registrando la trazabilidad en tiempo real.</p>
            </div>
        </div>
    </div>
</div>

<script>
    // 1. CONFIGURACIÓN GRÁFICO DE ÁREA (Flujo Logístico)
    var optionsArea = {
        series: [{
            name: 'Ingresos (Unidades)',
            data: <?php echo json_encode($data_ingresos); ?>
        }, {
            name: 'Despachos (Unidades)',
            data: <?php echo json_encode($data_retiros); ?>
        }],
        chart: {
            height: 320,
            type: 'area',
            fontFamily: 'Public Sans, sans-serif',
            toolbar: { show: false }, // Oculta el menú hamburguesa del gráfico
            zoom: { enabled: false }
        },
        colors: ['#10b981', '#f39c12'], // Verde (Ingreso) y Naranja (Despacho)
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 90, 100]
            }
        },
        xaxis: {
            categories: <?php echo json_encode($fechas_7dias); ?>,
            axisBorder: { show: false },
            axisTicks: { show: false }
        },
        yaxis: {
            labels: { style: { colors: '#82868b' } }
        },
        grid: {
            borderColor: '#ebebeb',
            strokeDashArray: 4, // Líneas punteadas tipo Vuexy
            yaxis: { lines: { show: true } }
        },
        legend: { position: 'top', horizontalAlign: 'left' }
    };

    var chartArea = new ApexCharts(document.querySelector("#graficoArea"), optionsArea);
    chartArea.render();


    // 2. CONFIGURACIÓN GRÁFICO DE DONA (Inventario)
    var optionsDona = {
        series: <?php echo json_encode($cantidades_stock); ?>,
        labels: <?php echo json_encode($nombres_stock); ?>,
        chart: {
            type: 'donut',
            height: 300,
            fontFamily: 'Public Sans, sans-serif'
        },
        colors: ['#10b981', '#0ea5e9', '#8b5cf6', '#f59e0b', '#ec4899'], // Paleta moderna
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: { fontSize: '14px', color: '#82868b' },
                        value: {
                            fontSize: '24px',
                            fontWeight: 600,
                            color: '#4b4b4b',
                            formatter: function (val) { return val + " u." }
                        },
                        total: {
                            show: true,
                            label: 'Total Stock',
                            color: '#82868b',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => { return a + b }, 0)
                            }
                        }
                    }
                }
            }
        },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 3, colors: ['#ffffff'] },
        legend: { position: 'bottom' }
    };

    var chartDona = new ApexCharts(document.querySelector("#graficoDona"), optionsDona);
    chartDona.render();
</script>

<?php include 'includes/footer.php'; ?>