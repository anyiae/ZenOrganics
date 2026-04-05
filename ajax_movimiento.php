<?php
session_start();
require 'conexion.php';

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    
    $accion         = $data['accion']; // 'ingreso', 'retiro' o 'produccion'
    $producto_id    = !empty($data['producto_id']) ? $data['producto_id'] : null;
    $cantidad_total = (int)$data['cantidad_total'];
    $usuario_id     = $_SESSION['user_id'];
    
    $cliente_id     = !empty($data['cliente_id']) ? $data['cliente_id'] : null;
    $total_pedido   = !empty($data['total_pedido']) ? (int)$data['total_pedido'] : null;
    $temp_tofu      = !empty($data['temp_tofu']) ? $data['temp_tofu'] : null;
    $temp_camion    = !empty($data['temp_camion']) ? $data['temp_camion'] : null;
    $hora_llegada   = !empty($data['hora_llegada']) ? $data['hora_llegada'] : null;
    $hora_despacho  = !empty($data['hora_despacho']) ? $data['hora_despacho'] : null;
    $observaciones  = !empty($data['observaciones']) ? $data['observaciones'] : null;

    try {
        $pdo->beginTransaction();

        if ($accion === 'ingreso') {
            // Afecta inventario real
            $stmt = $pdo->prepare("UPDATE camara_frio SET stock_actual = stock_actual + ? WHERE id = ?");
            $stmt->execute([$cantidad_total, $producto_id]);
            $mensaje = "Stock real actualizado.";

        } else if ($accion === 'retiro') {
            // Afecta inventario real
            $stmt = $pdo->prepare("UPDATE camara_frio SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?");
            $stmt->execute([$cantidad_total, $producto_id, $cantidad_total]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Stock insuficiente en inventario real.");
            }
            $mensaje = "Despacho realizado.";

        } else if ($accion === 'produccion') {
            // NO toca la tabla camara_frio, solo se registra el movimiento
            $mensaje = "Estimación guardada fuera del inventario real.";
        }

        // INSERTAR EN HISTORIAL (El campo 'tipo' diferenciará dónde aparece cada registro)
        $sqlLog = "INSERT INTO movimientos (
            tipo, cantidad, producto_id, usuario_id, cliente_id, 
            total_pedido, temp_tofu, temp_camion, hora_llegada, 
            hora_despacho, observaciones, fecha
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmtLog = $pdo->prepare($sqlLog);
        $stmtLog->execute([
            $accion, // 'ingreso', 'retiro' o 'produccion'
            $cantidad_total, 
            $producto_id, 
            $usuario_id, 
            $cliente_id,
            $total_pedido, 
            $temp_tofu, 
            $temp_camion, 
            $hora_llegada, 
            $hora_despacho, 
            $observaciones
        ]);

        $pdo->commit();
        echo json_encode(["success" => true, "mensaje" => $mensaje]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "mensaje" => $e->getMessage()]);
    }
}