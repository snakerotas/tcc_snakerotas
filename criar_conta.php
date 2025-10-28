<?php
session_start();
require_once __DIR__ . '/config.php'; // $pdo já existe aqui

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm  = isset($_POST['confirm-password']) ? $_POST['confirm-password'] : '';

    $nome = $username; // simplificação

    // Validações
    if ($username === '' || strlen($username) < 3) $errors[] = "Nome de usuário deve ter pelo menos 3 caracteres.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email inválido.";
    if (strlen($password) < 8) $errors[] = "Senha deve ter pelo menos 8 caracteres.";
    if ($password !== $confirm) $errors[] = "As senhas não são iguais.";

    if (empty($errors)) {
        // Checar se já existe usuário/email
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE usuario = :u OR email = :e LIMIT 1");
        $stmt->execute([':u'=>$username, ':e'=>$email]);
        if ($stmt->fetch()) {
            $errors[] = "Usuário ou email já cadastrados.";
        } else {
            // Inserir usuário no banco
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO usuarios (usuario, nome, email, senha) VALUES (:u,:n,:e,:s)");
            $ins->execute([
                ':u' => $username,
                ':n' => $nome,
                ':e' => $email,
                ':s' => $hash
            ]);

            // Redireciona para index.php
            header("Location: index.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar Conta</title>
<style>
/* ===================== TEMA COBRA CORAL ===================== */
:root {
  --preto: #0d0d0d;
  --cinza: #1a1a1a;
  --vermelho: #e63946;
  --vermelho-dark: #a4161a;
  --amarelo: #ffd166;
  --amarelo-dark: #f4a261;
  --texto: #f8f9fa;
  --texto-sec: #adb5bd;
  --radius: 14px;
  --shadow: 0 8px 28px rgba(0,0,0,0.65);
  --blur-bg: rgba(20,20,20,0.6);
}

* { box-sizing: border-box; margin:0; padding:0; }
html, body { height:100%; font-family: 'Poppins','Segoe UI',Roboto,sans-serif; }

body {
  background: linear-gradient(135deg, var(--preto), #121212);
  color: var(--texto);
  display:flex;
  justify-content:center;
  align-items:center;
  min-height:100vh;
  padding:1.5rem;
}

.signup-container {
  max-width:500px;
  width:100%;
  padding:2.5rem;
  background:var(--blur-bg);
  backdrop-filter: blur(10px);
  border:2px solid var(--vermelho-dark);
  border-radius:var(--radius);
  box-shadow:var(--shadow);
  text-align:center;
  position:relative;
  overflow:hidden;
}

.signup-container::before {
  content:'';
  position:absolute;
  top:0;
  left:0;
  width:100%;
  height:8px;
  background: repeating-linear-gradient(
    90deg,
    var(--vermelho),
    var(--vermelho) 30px,
    var(--amarelo),
    var(--amarelo) 40px,
    var(--preto),
    var(--preto) 80px
  );
  z-index:10;
}

.signup-container h2 {
  font-weight:800;
  font-size:1.5rem;
  color:var(--amarelo);
  text-transform:uppercase;
  margin-bottom:0.5rem;
  position:relative;
  z-index:1;
}

.signup-container p {
  color:var(--texto-sec);
  margin-bottom:1.5rem;
  position:relative;
  z-index:1;
}

.form-group { margin-bottom:1.2rem; text-align:left; }

.form-group label { display:block; margin-bottom:0.4rem; color:var(--texto-sec); font-size:0.9rem; }

.form-group input {
  width:100%;
  padding:1rem 1.2rem;
  border-radius:var(--radius);
  border:2px solid transparent;
  background: rgba(25,25,25,0.9);
  color:var(--texto);
  font-size:0.95rem;
  transition: all 0.3s ease;
}

.form-group input:focus {
  border-color:var(--amarelo);
  box-shadow:0 0 10px var(--amarelo);
  outline:none;
}

.btn.signup-btn {
  width:100%;
  font-size:1rem;
  margin-top:1rem;
  background: linear-gradient(145deg, var(--amarelo), var(--amarelo-dark));
  color:var(--preto);
  box-shadow:0 4px 15px rgba(244,162,97,0.4);
}

.btn.signup-btn:hover {
  transform:translateY(-2px);
  box-shadow:0 6px 20px rgba(244,162,97,0.6);
}

.secondary-link {
  display:block;
  margin-top:1.5rem;
  color:var(--texto-sec);
  font-size:0.9rem;
  text-decoration:none;
  transition:color 0.3s ease;
}

.secondary-link:hover { color:var(--amarelo); }
</style>
</head>
<body>
<div class="signup-container">
  <h2>Criar Conta</h2>
  <p>Junte-se à nossa comunidade!</p>

  <?php if(!empty($errors)): ?>
    <ul style="color:#e63946; margin-bottom:1rem;">
      <?php foreach($errors as $err) echo "<li>$err</li>"; ?>
    </ul>
  <?php endif; ?>

  <form action="" method="POST">
    <div class="form-group">
      <label for="username">Nome de Usuário</label>
      <input type="text" id="username" name="username" placeholder="Seu nome" required>
    </div>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
    </div>
    <div class="form-group">
      <label for="password">Senha</label>
      <input type="password" id="password" name="password" placeholder="Mínimo 8 caracteres" required minlength="8">
    </div>
    <div class="form-group">
      <label for="confirm-password">Confirmar Senha</label>
      <input type="password" id="confirm-password" name="confirm-password" placeholder="Repita sua senha" required minlength="8">
    </div>
    <button type="submit" class="btn signup-btn primary">Criar Conta</button>
  </form>
  <a href="/snakerotas/logar.html" class="secondary-link">Já tem uma conta? Faça login</a>
</div>
</body>
</html>