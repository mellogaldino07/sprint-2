<?php
// produtos.php - Gerenciamento completo de produtos

require_once 'config.php';

// GUARDIÃO DE SEGURANÇA
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_tipo'])) {
    jsonResponse(false, 'Acesso negado. Faça login novamente.');
    exit;
}

if ($_SESSION['usuario_tipo'] !== 'fornecedor') {
    jsonResponse(false, 'Acesso não autorizado para este tipo de conta.');
    exit;
}

$fornecedor_id = $_SESSION['usuario_id'];
$metodo = $_SERVER['REQUEST_METHOD'];

// ============================================
// LISTAR PRODUTOS (GET)
// ============================================
if ($metodo === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nome,
                descricao,
                material,
                tamanho,
                peso,
                fornecedor_id,
                estoque_atual,
                data_cadastro
            FROM produtos
            WHERE fornecedor_id = :fornecedor_id
            ORDER BY nome ASC
        ");
        
        $stmt->execute(['fornecedor_id' => $fornecedor_id]);
        $produtos = $stmt->fetchAll();
        
        jsonResponse(true, 'Produtos carregados', $produtos);
        
    } catch(PDOException $e) {
        jsonResponse(false, 'Erro ao carregar produtos: ' . $e->getMessage());
    }
}

// ============================================
// CADASTRAR OU ATUALIZAR PRODUTO (POST)
// ============================================
if ($metodo === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['nome']) || !isset($data['material'])) {
        jsonResponse(false, 'Nome e Material são obrigatórios.');
    }
    
    $nome = trim($data['nome']);
    $descricao = trim($data['descricao'] ?? '');
    $material = trim($data['material']);
    $tamanho = trim($data['tamanho'] ?? '');
    $peso = intval($data['peso'] ?? 0);
    $estoque_atual = intval($data['estoque_atual'] ?? 0);

    try {
        // ATUALIZAR (se o ID existir)
        if (isset($data['id']) && !empty($data['id'])) {
            $produto_id = intval($data['id']);

            // Garante que o fornecedor só pode editar seus próprios produtos
            $stmt_check = $pdo->prepare("SELECT id FROM produtos WHERE id = :id AND fornecedor_id = :fornecedor_id");
            $stmt_check->execute(['id' => $produto_id, 'fornecedor_id' => $fornecedor_id]);

            if (!$stmt_check->fetch()) {
                jsonResponse(false, 'Produto não encontrado ou você não tem permissão para editá-lo.');
            }

            // A coluna estoque_atual NÃO é atualizada aqui, é feita via `movimentacao.php`
            $stmt = $pdo->prepare("
                UPDATE produtos SET 
                    nome = :nome, 
                    descricao = :descricao, 
                    material = :material, 
                    tamanho = :tamanho, 
                    peso = :peso 
                WHERE id = :id
            ");
            $stmt->execute([
                'nome' => $nome,
                'descricao' => $descricao,
                'material' => $material,
                'tamanho' => $tamanho,
                'peso' => $peso,
                'id' => $produto_id
            ]);
            
            jsonResponse(true, '✓ Produto atualizado com sucesso!');
            
        } else {
            // CADASTRAR NOVO PRODUTO
            
            // Inicia Transação
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO produtos (nome, descricao, material, tamanho, peso, fornecedor_id, estoque_atual)
                VALUES (:nome, :descricao, :material, :tamanho, :peso, :fornecedor_id, :estoque)
            ");
            $stmt->execute([
                'nome' => $nome,
                'descricao' => $descricao,
                'material' => $material,
                'tamanho' => $tamanho,
                'peso' => $peso,
                'fornecedor_id' => $fornecedor_id,
                'estoque' => $estoque_atual
            ]);
            
            $novo_produto_id = $pdo->lastInsertId();
            
            // Se houver estoque inicial, registra uma "Entrada" na movimentação
            if ($estoque_atual > 0) {
                $stmt_mov = $pdo->prepare("
                    INSERT INTO movimentacao (produto_id, fornecedor_id, tipo_movimentacao, quantidade, observacao)
                    VALUES (:produto_id, :fornecedor_id, 'entrada', :quantidade, 'Estoque inicial no cadastro.')
                ");
                $stmt_mov->execute([
                    'produto_id' => $novo_produto_id,
                    'fornecedor_id' => $fornecedor_id,
                    'quantidade' => $estoque_atual
                ]);
            }
            
            $pdo->commit();
            
            jsonResponse(true, '✓ Produto cadastrado com sucesso!');
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() == '23000') {
             jsonResponse(false, 'Erro: Já existe um produto com o mesmo nome para este fornecedor.');
        }
        jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
    }
}

// ============================================
// EXCLUIR PRODUTO (DELETE)
// ============================================
if ($metodo === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        jsonResponse(false, 'ID do produto não informado.');
    }
    
    try {
        // Inicia Transação
        $pdo->beginTransaction();
        
        // 1. Verifica se o produto pertence ao fornecedor logado
        $stmt = $pdo->prepare("SELECT id FROM produtos WHERE id = :id AND fornecedor_id = :fornecedor_id");
        $stmt->execute([
            'id' => $id,
            'fornecedor_id' => $fornecedor_id
        ]);
        
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            jsonResponse(false, 'Produto não encontrado ou você não tem permissão para excluí-lo.');
        }
        
        // 2. Exclui as movimentações de estoque associadas a este produto
        $stmt = $pdo->prepare("DELETE FROM movimentacao WHERE produto_id = :id AND fornecedor_id = :fornecedor_id");
        $stmt->execute([
            'id' => $id,
            'fornecedor_id' => $fornecedor_id
        ]);
        
        // 3. Exclui o produto
        $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = :id AND fornecedor_id = :fornecedor_id");
        $stmt->execute([
            'id' => $id,
            'fornecedor_id' => $fornecedor_id
        ]);
        
        // Confirma todas as operações
        $pdo->commit();
        
        if ($stmt->rowCount() > 0) {
            jsonResponse(true, '✓ Produto excluído com sucesso!');
        } else {
            jsonResponse(false, 'Erro ao excluir produto.');
        }
        
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Verifica se há restrição de chave estrangeira (produto tem movimentações)
        if ($e->getCode() == '23000') {
            jsonResponse(false, 'Não é possível excluir o produto devido a registros de movimentação pendentes. Tente novamente mais tarde.');
        }
        jsonResponse(false, 'Erro no servidor: ' . $e->getMessage());
    }
}
?>