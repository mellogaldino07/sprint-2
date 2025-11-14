<?php
// clientes.php - Gerenciamento completo de clientes

require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// LISTAR CLIENTES (GET)
if ($metodo === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, nome_completo, cpf, telefone, data_nascimento, email FROM clientes ORDER BY nome_completo ASC");
        $clientes = $stmt->fetchAll();
        jsonResponse(true, 'Clientes carregados', $clientes);
    } catch(PDOException $e) {
        jsonResponse(false, 'Erro ao carregar clientes: ' . $e->getMessage());
    }
}

// CADASTRAR OU ATUALIZAR CLIENTE (POST)
if ($metodo === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        // ATUALIZAR (se o ID existir)
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $pdo->prepare(
                "UPDATE clientes SET nome_completo = :nome, cpf = :cpf, telefone = :telefone, data_nascimento = :nasc, email = :email WHERE id = :id"
            );
            $stmt->execute([
                'nome' => trim($data['nome_completo']),
                'cpf' => trim($data['cpf']),
                'telefone' => trim($data['telefone']),
                'nasc' => $data['data_nascimento'],
                'email' => trim($data['email']),
                'id' => intval($data['id'])
            ]);
            jsonResponse(true, 'Cliente atualizado com sucesso!');
        } else {
            // CADASTRAR (se não houver ID)
             jsonResponse(false, 'A criação de clientes deve ser feita pela tela de cadastro inicial.');
        }

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
             jsonResponse(false, 'Erro: CPF ou Email já cadastrado no sistema.');
        }
        jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
    }
}

// DELETAR CLIENTE (DELETE)
if ($metodo === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);

    if (!$id) {
        jsonResponse(false, 'ID do cliente não informado.');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = :id");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() > 0) {
            jsonResponse(true, 'Cliente excluído com sucesso!');
        } else {
            jsonResponse(false, 'Cliente não encontrado.');
        }

    } catch(PDOException $e) {
        // Verifica se há restrição de chave estrangeira (se o cliente tiver movimentações ou pedidos)
        if ($e->getCode() == '23000') {
            jsonResponse(false, 'Não é possível excluir o cliente. Existem registros (pedidos/movimentações) vinculados a ele.');
        }
        jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
    }
}
?>