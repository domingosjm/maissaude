<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout | Integrada Mais Saúde</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh;">
    <div class="card shadow p-4 text-center" style="max-width: 370px; width:100%; border-radius: 1.2rem;">
        <h2 class="mb-3" style="color:#43a047;">Você saiu do sistema</h2>
        <p class="mb-4">Sessão encerrada com sucesso.</p>
        <a href="index.php" class="btn btn-success w-100">Voltar ao Login</a>
    </div>
</body>
</html>
