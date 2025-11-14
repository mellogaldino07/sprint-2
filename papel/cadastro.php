<?php
// cadastro.php - Cadastro de novos clientes e fornecedores

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['tipo']) || !isset($data['email']) || !isset($data['senha'])) {
    jsonResponse(false, 'Dados incompletos');
}

$tipo = trim($data['tipo']);
// SEGURANÇA: Criptografa a senha antes de salvar
$senhaHash = password_hash($data['senha'], PASSWORD_DEFAULT);

try {
    if ($tipo === 'cliente') {
        $stmt = $pdo->prepare(
            "INSERT INTO clientes (nome_completo, cpf, telefone, data_nascimento, email, senha)
             VALUES (:nome_completo, :cpf, :telefone, :data_nascimento, :email, :senha)"
        );
        $stmt->execute([
            'nome_completo' => trim($data['nome']),
            'cpf' => trim($data['cpf']),
            'telefone' => trim($data['telefone']),
            'data_nascimento' => $data['data_nascimento'],
            'email' => trim($data['email']),
            'senha' => $senhaHash // Salva a senha criptografada
        ]);
    } elseif ($tipo === 'fornecedor') {
        $stmt = $pdo->prepare(
            "INSERT INTO fornecedores (razao_social, inscricao_estadual, cnpj, nome_responsavel, data_nascimento_responsavel, cpf_responsavel, telefone, email_corporativo, senha)
             VALUES (:razao_social, :inscricao_estadual, :cnpj, :nome_responsavel, :data_nascimento_responsavel, :cpf_responsavel, :telefone, :email_corporativo, :senha)"
        );
        $stmt->execute([
            'razao_social' => trim($data['razao_social']),
            'inscricao_estadual' => trim($data['inscricao_estadual']),
            'cnpj' => trim($data['cnpj']),
            'nome_responsavel' => trim($data['nome_responsavel']),
            'data_nascimento_responsavel' => $data['data_nascimento_responsavel'],
            'cpf_responsavel' => trim($data['cpf_responsavel']),
            'telefone' => trim($data['telefone']),
            'email_corporativo' => trim($data['email']),
            'senha' => $senhaHash // Salva a senha criptografada
        ]);
    } else {
        jsonResponse(false, 'Tipo de usuário inválido');
    }

    jsonResponse(true, 'Cadastro realizado com sucesso! Você já pode fazer login.');

} catch(PDOException $e) {
    // MELHORIA: Verifica se o erro é de entrada duplicada (email, cpf ou cnpj já existem)
    if ($e->getCode() == '23000') {
        if (strpos($e->getMessage(), 'cpf')) {
             jsonResponse(false, 'Erro: CPF já cadastrado no sistema.');
        } elseif (strpos($e->getMessage(), 'cnpj')) {
             jsonResponse(false, 'Erro: CNPJ já cadastrado no sistema.');
        } elseif (strpos($e->getMessage(), 'email')) {
             jsonResponse(false, 'Erro: Email já cadastrado no sistema.');
        } else {
             jsonResponse(false, 'Erro de duplicidade de dados.');
        }
    }
    jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
}
?>