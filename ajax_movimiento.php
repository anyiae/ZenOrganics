<?php
session_start();
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    $accion = $data['accion']; // 'ingreso' o 'retiro'
    $producto_id = $data['producto_id'];
    $cantidad_total = $data['cantidad_total']; // Ya viene calculada desde JS en Unidades
    $usuario_id = $_SESSION['user_id'];
    $cliente_id = isset($data['cliente_id']) ? $data['cliente_id'] : null;

    try {
        $pdo->beginTransaction();

        if ($accion === 'ingreso') {
            // Sumar al stock
            $stmt = $pdo->prepare("UPDATE camara_frio SET stock_actual = stock_actual + ? WHERE id = ?");
            $stmt->execute([$cantidad_total, $producto_id]);

            // Log del movimiento
            $stmtLog = $pdo->prepare("INSERT INTO movimientos (tipo, cantidad, producto_id, usuario_id) VALUES ('ingreso', ?, ?, ?)");
            $stmtLog->execute([$cantidad_total, $producto_id, $usuario_id]);

            $pdo->commit();
            echo json_encode(["success" => true, "mensaje" => "Stock agregado correctamente"]);

        } else if ($accion === 'retiro') {
            // Restar al stock (verificando que haya suficiente)
            $stmt = $pdo->prepare("UPDATE camara_frio SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?");
            $stmt->execute([$cantidad_total, $producto_id, $cantidad_total]);

            if ($stmt->rowCount() > 0) {
                // Log del movimiento
                $stmtLog = $pdo->prepare("INSERT INTO movimientos (tipo, cantidad, producto_id, cliente_id, usuario_id) VALUES ('retiro', ?, ?, ?, ?)");
                $stmtLog->execute([$cantidad_total, $producto_id, $cliente_id, $usuario_id]);

                $pdo->commit();
                echo json_encode(["success" => true, "mensaje" => "Stock retirado correctamente"]);
            } else {
                $pdo->rollBack();
                echo json_encode(["success" => false, "mensaje" => "No hay suficiente stock en la base de datos."]);
            }
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "mensaje" => "Error de base de datos: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "mensaje" => "Acceso denegado"]);
}
?>