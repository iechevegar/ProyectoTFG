<?php
// =========================================================================================
// LIBRERÍA DE FUNCIONES GLOBALES (CORE UTILITIES)
// =========================================================================================

/**
 * Generador de Slugs. Transforma texto natural en identificadores URL-friendly.
 */
function limpiarURL($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// =========================================================================================
// PROTECCIÓN CSRF
// =========================================================================================

/** Genera (o recupera) el token CSRF de la sesión. Llamar tras session_start(). */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Devuelve un campo hidden con el token CSRF para insertar en formularios. */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

/**
 * Verifica el token CSRF del POST. Si falla, redirige y aborta.
 * @param string $redirect URL de redirección en caso de fallo.
 */
function csrf_verify($redirect = '/') {
    if (empty($_POST['csrf_token']) || !hash_equals(csrf_token(), $_POST['csrf_token'])) {
        header("Location: $redirect");
        exit();
    }
}

// =========================================================================================
// RATE LIMITING EN LOGIN (ANTI FUERZA BRUTA)
// =========================================================================================

/**
 * Comprueba si el usuario está bloqueado por demasiados intentos fallidos.
 * @param int $max_intentos  Intentos antes del bloqueo (defecto: 5).
 * @param int $ventana_seg   Duración del bloqueo en segundos (defecto: 5 min).
 * @return bool true = bloqueado.
 */
function rate_limit_login($max_intentos = 5, $ventana_seg = 300) {
    $ahora = time();
    if (isset($_SESSION['login_time']) && ($ahora - $_SESSION['login_time']) > $ventana_seg) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = $ahora;
    }
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_time'] = $ahora;
    }
    return $_SESSION['login_attempts'] >= $max_intentos;
}

/** Incrementa el contador de intentos fallidos. */
function rate_limit_fail() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if (!isset($_SESSION['login_time'])) $_SESSION['login_time'] = time();
}

/** Resetea el contador tras login exitoso. */
function rate_limit_reset() {
    $_SESSION['login_attempts'] = 0;
    unset($_SESSION['login_time']);
}

/** Devuelve minutos restantes de bloqueo. */
function rate_limit_minutos_restantes($ventana_seg = 300) {
    if (!isset($_SESSION['login_time'])) return 0;
    $restantes = $ventana_seg - (time() - $_SESSION['login_time']);
    return $restantes > 0 ? ceil($restantes / 60) : 0;
}

// =========================================================================================
// HELPERS DE SESIÓN / USUARIO
// =========================================================================================

/**
 * Devuelve el estado del usuario logueado: ID y si está suspendido.
 * Usa prepared statement internamente.
 * @param mysqli $conn
 * @return array ['suspendido'=>bool, 'hasta'=>string|null, 'id'=>int|null]
 */
function get_estado_usuario($conn) {
    $resultado = ['suspendido' => false, 'hasta' => null, 'id' => null];
    if (!isset($_SESSION['usuario'])) return $resultado;
    $stmt = $conn->prepare("SELECT id, fecha_desbloqueo FROM usuarios WHERE nombre = ?");
    $stmt->bind_param("s", $_SESSION['usuario']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) return $resultado;
    $row = $res->fetch_assoc();
    $resultado['id'] = (int) $row['id'];
    if (!empty($row['fecha_desbloqueo']) && strtotime($row['fecha_desbloqueo']) > time()) {
        $resultado['suspendido'] = true;
        $resultado['hasta'] = date('d/m/Y H:i', strtotime($row['fecha_desbloqueo']));
    }
    return $resultado;
}

/** Escapa string para salida HTML segura. */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/** Valida formato de email. */
function validar_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
