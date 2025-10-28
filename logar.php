<?php
session_start();
require_once __DIR__ . '/config.php'; // contém o $pdo

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $senha = isset($_POST['password']) ? $_POST['password'] : '';

    // Consulta o usuário pelo email
    $stmt = $pdo->prepare("SELECT id_usuario, usuario, nome, email, senha FROM usuarios WHERE email = :e LIMIT 1");
    $stmt->execute([':e' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha'])) {
        // Login OK → cria sessão
        $_SESSION['usuario_id'] = $user['id_usuario'];
        $_SESSION['usuario_nome'] = $user['nome'] ?: $user['usuario'];
        $_SESSION['usuario_email'] = $user['email'];

        // Redireciona para o index
        header("Location: index.php");
        exit;
    } else {
        $erro = "Email ou senha incorretos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <style>
    /* Mantém exatamente seu estilo Cobra Coral Premium */
    <?php include 'style_coral.css'; ?> /* Se você tiver um CSS separado */
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Login</h2>
    <p>Entre com sua conta.</p>

    <?php if ($erro): ?>
      <p style="color:#e63946; font-weight:bold;"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
      </div>
      <div class="form-group">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" placeholder="Sua senha" required>
      </div>
      <div class="forgot-password">
        <a href="/recuperar-senha">Esqueceu a senha?</a>
      </div>
      <button type="submit" class="btn login-btn">Entrar</button>
    </form>

    <a href="criar_conta.php" class="secondary-link">Não tem uma conta? Crie agora</a>
  </div>
</body>
</html>
