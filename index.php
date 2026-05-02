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
$db->exec("CREATE TABLE IF NOT EXISTS compras (id INTEGER PRIMARY KEY AUTOINCREMENT, item TEXT, criado_em DATETIME DEFAULT CURRENT_TIMESTAMP)");

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

// CRUD: DELETE ALL (Esvaziar Lista)
if ($action === 'clear_all') {
    $db->exec("DELETE FROM compras");
    header("Location: index.php");
    exit;
}

// CRUD: CREATE e UPDATE com Validação no Servidor (Requisito Obrigatório)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item = trim($_POST['item'] ?? '');

    if (empty($item) || strlen($item) < 3) {
        $msg_erro = "Erro no Servidor: O item deve ter pelo menos 3 caracteres.";
    } else {
        if (isset($_POST['update_id'])) {
            $stmt = $db->prepare("UPDATE compras SET item = ? WHERE id = ?");
            $stmt->execute([htmlspecialchars($item), $_POST['update_id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO compras (item) VALUES (?)");
            $stmt->execute([htmlspecialchars($item)]);
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