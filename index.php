<?php
// === config supabase REST ===
$projectUrl = 'https://ulqqyjjroaddfbtcajcl.supabase.co';
$anonKey    = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InVscXF5ampyb2FkZGZidGNhamNsIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyMjQ0NTEsImV4cCI6MjA2MjgwMDQ1MX0.DLRqKe_ZIsmp8WAz6SyQuMY5Nz4erC-UdvGJ_tY2Pik';
$table      = 'entries';

function sb_request($method, $path, $body = null) {
    global $projectUrl, $anonKey;
    $url = rtrim($projectUrl, '/') . '/rest/v1/' . $path;
    $ch  = curl_init($url);
    $hdr = [
        "apikey: {$anonKey}",
        "Authorization: Bearer {$anonKey}",
        "Content-Type: application/json",
        "Accept: application/json"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $hdr);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        die("Curl error: " . curl_error($ch));
    }
    curl_close($ch);
    if ($status>=200 && $status<300) {
        return json_decode($resp, true);
    }
    die("Supabase REST error ($status): $resp");
}

// === lógica de CRUD ===
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
  $now = gmdate('c');
  if ($_POST['action']==='create' && !empty($_POST['nome'])) {
    sb_request('POST', "{$table}", [
      'full_name' => trim($_POST['nome']),
      'updated_at' => $now
    ]);
  }
  if ($_POST['action']==='update' && !empty($_POST['id']) && !empty($_POST['nome'])) {
    $id = intval($_POST['id']);
    sb_request('PATCH', "{$table}?id=eq.{$id}", [
      'full_name' => trim($_POST['nome']),
      'updated_at' => $now
    ]);
  }
  if ($_POST['action']==='delete' && !empty($_POST['id'])) {
    $id = intval($_POST['id']);
    sb_request('DELETE', "{$table}?id=eq.{$id}");
  }
  header('Location: '.$_SERVER['PHP_SELF']);
  exit;
}

$items = sb_request('GET', "{$table}?select=*");

$editItem = null;
if (isset($_GET['edit'])) {
  $eid = intval($_GET['edit']);
  $res = sb_request('GET', "{$table}?select=*&id=eq.{$eid}");
  if (count($res)) $editItem = $res[0];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CRUD Supabase REST</title>
  <!-- Bootstrap CSS corrigido -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding-top: 4.5rem; }
    .container { max-width: 800px; }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container">
      <a class="navbar-brand" href="#">Supabase CRUD</a>
    </div>
  </nav>

  <main class="container">

    <h1 class="mb-4 text-center">Gerenciar Entradas</h1>

    <!-- Form de criação ou edição -->
    <div class="card mb-5 shadow-sm">
      <div class="card-body">
        <form method="post" class="row g-3 align-items-end">
          <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create' ?>">
          <?php if($editItem): ?>
            <input type="hidden" name="id" value="<?= $editItem['id'] ?>">
          <?php endif ?>

          <div class="col-12 col-md">
            <label class="form-label"><?= $editItem ? 'Editar nome completo' : 'Novo nome completo' ?></label>
            <input
              type="text"
              name="nome"
              class="form-control"
              value="<?= $editItem ? htmlspecialchars($editItem['full_name']) : '' ?>"
              required
              placeholder="Digite nome e sobrenome"
              pattern="^\S+\s+\S+.*$"
              title="Por favor, digite nome e sobrenome (ex: João Silva)."
            />
            <div class="form-text">Deve conter nome e sobrenome.</div>
          </div>

          <div class="col-auto">
            <button class="btn btn-success"><?= $editItem ? 'Atualizar' : 'Cadastrar' ?></button>
            <?php if($editItem): ?>
              <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif ?>
          </div>
        </form>
      </div>
    </div>

    <!-- Tabela de registros -->
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Nome completo</th>
            <th>Última atualização</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($items as $it): ?>
          <tr>
            <td><?= $it['id'] ?></td>
            <td><?= htmlspecialchars($it['full_name']) ?></td>
            <td><?= (new DateTime($it['updated_at']))->format('d/m/Y H:i') ?></td>
            <td class="text-end">
              <a href="?edit=<?= $it['id'] ?>" class="btn btn-sm btn-outline-primary me-1">✏️</a>
              <form method="post" class="d-inline" onsubmit="return confirm('Excluir esse registro?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $it['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">🗑️</button>
              </form>
            </td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>

  </main>

  <!-- Bootstrap Bundle JS corrigido -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
