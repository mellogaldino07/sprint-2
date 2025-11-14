<?php
// logout.php - Finaliza a sessão do usuário no servidor

require_once 'config.php';

// Destrói todas as variáveis de sessão
$_SESSION = [];

// Se é preciso matar a sessão, também apague o cookie de sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destrói a sessão
session_destroy();

jsonResponse(true, 'Logout realizado com sucesso.');

?>