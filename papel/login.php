<?php
// login.php - Sistema de autenticação (VERSÃO CORRIGIDA - Retorna dados completos)

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['email']) || !isset($data['senha']) || !isset($data['tipo'])) {
    jsonResponse(false, 'Dados incompletos');
}

$email = trim($data['email']);
$senha = trim($data['senha']);
$tipo = trim($data['tipo']); 

if (!in_array($tipo, ['cliente', 'fornecedor'])) {
    jsonResponse(false, 'Tipo de usuário inválido');
}

$tabela = $tipo === 'cliente' ? 'clientes' : 'fornecedores';
$email_column = $tipo === 'fornecedor' ? 'email_corporativo' : 'email';

try {
    $stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE {$email_column} = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    // Verificação de senha
    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
        jsonResponse(false, 'Email ou senha incorretos');
    }

    // Se chegou até aqui, o login foi um sucesso.
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario_tipo'] = $tipo;

    // *** CORREÇÃO PRINCIPAL: Retorna TODOS os dados do usuário ***
    // Remove a senha antes de enviar para o frontend
    unset($usuario['senha']);
    
    // Adiciona o tipo ao objeto de resposta
    $usuario['tipo'] = $tipo;

    jsonResponse(true, 'Login bem-sucedido!', $usuario);

} catch(PDOException $e) {
    jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
}
?>