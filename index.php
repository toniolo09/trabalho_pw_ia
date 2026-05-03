<?php
require_once 'vendor/autoload.php';
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Carbon\Carbon;

$loader = new FilesystemLoader('templates');
$twig = new Environment($loader);
Carbon::setLocale('pt_BR');

// Persistência: Banco de Dados SQLite (Requisito Obrigatório)
$db = new PDO('sqlite:database.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Cria a tabela se não existir
$db->exec("CREATE TABLE IF NOT EXISTS compras (id INTEGER PRIMARY KEY AUTOINCREMENT, item TEXT, quantidade INTEGER DEFAULT 1, criado_em DATETIME DEFAULT CURRENT_TIMESTAMP)");

// Migração: Verifica se a coluna 'quantidade' existe (para casos onde a tabela foi criada antes da atualização)
$colunas = $db->query("PRAGMA table_info(compras)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('quantidade', $colunas)) {
    $db->exec("ALTER TABLE compras ADD COLUMN quantidade INTEGER DEFAULT 1");
}

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$msg_erro = null;

// CRUD: DELETE (Excluir)
if ($action === 'delete' && $id) {
    $stmt = $db->prepare("DELETE FROM compras WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: index.php");
    exit;
}

// CRUD: UPDATE QUANTITY (via botões +/- na lista)
if ($action === 'update_qty' && $id) {
    $change = (int)($_GET['change'] ?? 0);
    $stmt = $db->prepare("UPDATE compras SET quantidade = MAX(1, quantidade + ?) WHERE id = ?");
    $stmt->execute([$change, $id]);
    header("Location: index.php");
    exit;
}

// CRUD: DELETE ALL (Esvaziar Lista)
if ($action === 'clear_all') {
    $db->exec("DELETE FROM compras");
    header("Location: index.php");
    exit;
}

// CRUD: CREATE e UPDATE com Validação no Servidor (Requisito Obrigatório)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Caso seja apenas atualização rápida de quantidade via input na lista
    if (isset($_POST['update_qty_id'])) {
        $qty = (int)$_POST['quantidade'];
        if ($qty < 1) $qty = 1;
        $stmt = $db->prepare("UPDATE compras SET quantidade = ? WHERE id = ?");
        $stmt->execute([$qty, $_POST['update_qty_id']]);
        header("Location: index.php");
        exit;
    }

    $item = trim($_POST['item'] ?? '');
    $quantidade = (int)($_POST['quantidade'] ?? 1);
    if ($quantidade < 1) $quantidade = 1;

    if (empty($item) || strlen($item) < 1) {
        $msg_erro = "Erro no Servidor: O item deve ter pelo menos 1 caractere.";
    } else {
        if (isset($_POST['update_id'])) {
            $stmt = $db->prepare("UPDATE compras SET item = ?, quantidade = ? WHERE id = ?");
            $stmt->execute([htmlspecialchars($item), $quantidade, $_POST['update_id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO compras (item, quantidade) VALUES (?, ?)");
            $stmt->execute([htmlspecialchars($item), $quantidade]);
        }
        header("Location: index.php");
        exit;
    }
}

// CRUD: READ (Listar)
$query = $db->query("SELECT * FROM compras ORDER BY id DESC");
$itensRaw = $query->fetchAll(PDO::FETCH_ASSOC);

$itensFormatados = [];
foreach ($itensRaw as $row) {
    $itensFormatados[] = [
        'id' => $row['id'],
        'nome' => $row['item'],
        'quantidade' => $row['quantidade'] ?? 1,
        'data' => Carbon::parse($row['criado_em'], 'UTC')->setTimezone('America/Sao_Paulo')->diffForHumans()
    ];
}

$itemParaEditar = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM compras WHERE id = ?");
    $stmt->execute([$id]);
    $itemParaEditar = $stmt->fetch(PDO::FETCH_ASSOC);
}

echo $twig->render('index.html', [
    'lista' => $itensFormatados,
    'editando' => $itemParaEditar,
    'erro' => $msg_erro
]);