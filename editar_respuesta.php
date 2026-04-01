<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['usuario']) || !isset($_GET['id'])) {
    header("Location: foro.php");
    exit();
}

$idResp = intval($_GET['id']);
$nombreUser = $_SESSION['usuario'];

// VERIFICAR DUEÑO
$sql = "SELECT r.* FROM foro_respuestas r 
        JOIN usuarios u ON r.usuario_id = u.id 
        WHERE r.id = $idResp AND u.nombre = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nombreUser);
$stmt->execute();
$resp = $stmt->get_result()->fetch_assoc();

if (!$resp) die("No tienes permiso.");

// PROCESAR EDICIÓN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mensaje = trim($_POST['mensaje']);
    if (!empty($mensaje)) {
        $sqlUp = "UPDATE foro_respuestas SET mensaje=?, fecha_edicion=NOW() WHERE id=?";
        $stmtUp = $conn->prepare($sqlUp);
        $stmtUp->bind_param("si", $mensaje, $idResp);
        
        if ($stmtUp->execute()) {
            header("Location: tema.php?id=" . $resp['tema_id']); // Volver al hilo
            exit();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3 class="mb-4">Editar Respuesta</h3>
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <textarea name="mensaje" class="form-control" rows="5" required><?php echo htmlspecialchars($resp['mensaje']); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="tema.php?id=<?php echo $resp['tema_id']; ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Actualizar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>