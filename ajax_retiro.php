<?php
session_start();
require 'conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if ($data && isset($_SESSION['user_id'])) {
    $producto_id = $data['producto_id'];
    $cantidad = $data['cantidad'];
    $cliente_id = $data['cliente_id'];
    $usuario_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // 1. Descontar stock
        $stmt = $pdo->prepare("UPDATE camara_frio SET stock_actual = stock_actual - ? WHERE id = ? AND stock_actual >= ?");
        $stmt->execute([$cantidad, $producto_id, $cantidad]);

        if ($stmt->rowCount() > 0) {
            // 2. Registrar el movimiento (Log)
            $stmtLog = $pdo->prepare("INSERT INTO movimientos (tipo, cantidad, producto_id, cliente_id, usuario_id) VALUES ('retiro', ?, ?, ?, ?)");
            $stmtLog->execute([$cantidad, $producto_id, $cliente_id, $usuario_id]);

            $pdo->commit();
            echo json_encode(["success" => true, "mensaje" => "Se descontaron $cantidad cajas exitosamente."]);
        } else {
            $pdo->rollBack();
            echo json_encode(["success" => false, "mensaje" => "No hay suficiente stock."]);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["success" => false, "mensaje" => "Error en la base de datos."]);
    }
} else {
    echo json_encode(["success" => false, "mensaje" => "Petición no válida o sesión expirada."]);
}
?>