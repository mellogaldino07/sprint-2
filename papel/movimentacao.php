<?php
// movimentacao.php - Gerenciamento completo de movimentações de estoque

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
// LISTAR HISTÓRICO DE MOVIMENTAÇÕES (GET)
// ============================================
if ($metodo === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.produto_id,
                m.tipo_movimentacao,
                m.quantidade,
                m.data_movimentacao,
                m.observacao,
                p.nome AS produto_nome
            FROM movimentacao m
            LEFT JOIN produtos p ON m.produto_id = p.id
            WHERE m.fornecedor_id = :fornecedor_id
            ORDER BY m.data_movimentacao DESC
        ");
        
        $stmt->execute(['fornecedor_id' => $fornecedor_id]);
        $movimentacoes = $stmt->fetchAll();
        
        jsonResponse(true, 'Histórico carregado', $movimentacoes);
        
    } catch(PDOException $e) {
        jsonResponse(false, 'Erro ao carregar histórico: ' . $e->getMessage());
    }
}

// ============================================
// REGISTRAR NOVA MOVIMENTAÇÃO (POST)
// ============================================
if ($metodo === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (
        !$data || 
        !isset($data['produto_id']) || 
        !isset($data['tipo']) || 
        !isset($data['quantidade'])
    ) {
        jsonResponse(false, 'Dados incompletos para a movimentação.');
    }
    
    $produto_id = intval($data['produto_id']);
    $tipo = trim($data['tipo']);
    $quantidade = intval($data['quantidade']);
    $observacao = trim($data['observacao'] ?? '');
    
    if ($quantidade <= 0) {
        jsonResponse(false, 'Quantidade deve ser um número positivo.');
    }
    if (!in_array($tipo, ['entrada', 'saida'])) {
        jsonResponse(false, 'Tipo de movimentação inválido.');
    }

    try {
        // Inicia Transação para garantir atomicidade
        $pdo->beginTransaction();
        
        // 1. Verifica o estoque atual e se o produto pertence ao fornecedor
        $stmt = $pdo->prepare("SELECT estoque_atual FROM produtos WHERE id = :id AND fornecedor_id = :fornecedor_id LIMIT 1");
        $stmt->execute(['id' => $produto_id, 'fornecedor_id' => $fornecedor_id]);
        $produto = $stmt->fetch();
        
        if (!$produto) {
            $pdo->rollBack();
            jsonResponse(false, 'Produto não encontrado ou você não tem permissão para movimentá-lo.');
        }
        
        $estoqueAtual = intval($produto['estoque_atual']);
        $novoEstoque = $estoqueAtual;

        if ($tipo === 'entrada') {
            $novoEstoque += $quantidade;
        } elseif ($tipo === 'saida') {
            if ($quantidade > $estoqueAtual) {
                $pdo->rollBack();
                jsonResponse(false, "Erro: Quantidade de saída ({$quantidade}) é maior que o estoque atual ({$estoqueAtual}).");
            }
            $novoEstoque -= $quantidade;
        }

        // 2. Registra a movimentação na tabela `movimentacao`
        $stmt = $pdo->prepare("
            INSERT INTO movimentacao (produto_id, fornecedor_id, tipo_movimentacao, quantidade, observacao)
            VALUES (:produto_id, :fornecedor_id, :tipo, :quantidade, :observacao)
        ");
        $stmt->execute([
            'produto_id' => $produto_id,
            'fornecedor_id' => $fornecedor_id,
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'observacao' => $observacao
        ]);
        
        // 3. Atualiza o estoque na tabela `produtos`
        $stmt = $pdo->prepare("
            UPDATE produtos 
            SET estoque_atual = :novo_estoque 
            WHERE id = :id
        ");
        
        $stmt->execute([
            'novo_estoque' => $novoEstoque,
            'id' => $produto_id
        ]);
        
        // Confirma todas as operações
        $pdo->commit();
        
        $tipoTexto = $tipo === 'entrada' ? 'ENTRADA' : 'SAÍDA';
        jsonResponse(true, "✓ {$tipoTexto} de {$quantidade} unidade(s) registrada com sucesso! Novo estoque: {$novoEstoque}");
        
    } catch(PDOException $e) {
        // Em caso de erro, desfaz todas as operações
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, 'Erro ao registrar movimentação: ' . $e->getMessage());
    }
}

// ============================================
// EXCLUIR TODO O HISTÓRICO (DELETE SEM ID)
// ============================================
if ($metodo === 'DELETE') {
    try {
        // Inicia Transação
        $pdo->beginTransaction();
        
        // 1. Exclui todo o histórico de movimentação
        $stmt = $pdo->prepare("DELETE FROM movimentacao WHERE fornecedor_id = :fornecedor_id");
        $stmt->execute(['fornecedor_id' => $fornecedor_id]);
        
        // Confirma todas as operações
        $pdo->commit();
        
        $total = $stmt->rowCount();
        
        if ($total > 0) {
            jsonResponse(true, "✓ {$total} registro(s) de histórico excluído(s) com sucesso. O estoque atual dos produtos foi mantido.");
        } else {
            jsonResponse(true, 'Nenhum registro encontrado para excluir.');
        }
        
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, 'Erro ao excluir histórico: ' . $e->getMessage());
    }
}