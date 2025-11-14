<?php
// fornecedores.php - Gerenciamento (VERSÃO CORRIGIDA E SEGURA)

require_once 'config.php';

// INÍCIO DO GUARDIÃO DE SEGURANÇA
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    jsonResponse(false, 'Acesso negado. Faça login novamente.');
    exit;
}
if ($_SESSION['usuario_tipo'] !== 'fornecedor') {
     jsonResponse(false, 'Acesso não autorizado para este tipo de conta.');
     exit;
}
$fornecedor_logado_id = $_SESSION['usuario_id'];
// FIM DO GUARDIÃO

$metodo = $_SERVER['REQUEST_METHOD'];

// LISTAR FORNECEDORES (Filtrado - Mostra SÓ o logado)
if ($metodo === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, razao_social, inscricao_estadual, cnpj, nome_responsavel, data_nascimento_responsavel, cpf_responsavel, telefone, email_corporativo
            FROM fornecedores
            WHERE id = :id
        ");
        $stmt->execute(['id' => $fornecedor_logado_id]);
        $fornecedores = $stmt->fetchAll(); // Retorna array com 1 item
        jsonResponse(true, 'Fornecedores carregados', $fornecedores);
    } catch(PDOException $e) {
        jsonResponse(false, 'Erro ao carregar dados do fornecedor: ' . $e->getMessage());
    }
}

// ATUALIZAR FORNECEDOR (POST)
if ($metodo === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // ATUALIZAR (o POST só deve ser usado para atualizar o próprio registro)
        if (isset($data['id']) && intval($data['id']) === $fornecedor_logado_id) {
            
            // Verifica se o ID do POST corresponde ao ID do usuário logado
            if (intval($data['id']) !== $fornecedor_logado_id) {
                jsonResponse(false, 'Você não tem permissão para editar este fornecedor.');
                exit;
            }
            $stmt = $pdo->prepare("
                UPDATE fornecedores SET
                    razao_social = :razao_social,
                    inscricao_estadual = :inscricao_estadual,
                    telefone = :telefone,
                    email_corporativo = :email_corporativo
                WHERE id = :id
            ");
            $stmt->execute([
                'razao_social' => trim($data['razao_social']),
                'inscricao_estadual' => trim($data['inscricao_estadual'] ?? ''),
                'telefone' => trim($data['telefone'] ?? ''),
                'email_corporativo' => trim($data['email_corporativo']),
                'id' => $fornecedor_logado_id
            ]);
            jsonResponse(true, 'Seus dados foram atualizados com sucesso!');
        } else {
            // BLOQUEIA CRIAÇÃO
            jsonResponse(false, 'Fornecedores não podem criar novas contas por este painel.');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
             jsonResponse(false, 'Erro: Email já cadastrado no sistema.');
        }
        jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
    }
}

// DELETAR (Bloqueado)
if ($metodo === 'DELETE') {
    jsonResponse(false, 'Exclusão de conta não permitida por este painel. Entre em contato com o suporte.');
}
?>