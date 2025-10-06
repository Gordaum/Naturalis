<?php
// Iniciar a sessão para acessar os dados do usuário
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.html');
    exit();
}

// Buscar dados completos do usuário no banco de dados
$usuario = [];
$erro = '';
$sucesso = '';

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            nome, 
            email, 
            telefone, 
            cidade, 
            estado, 
            cep,
            DATE_FORMAT(data_nascimento, '%Y-%m-%d') as data_nascimento
        FROM usuarios 
        WHERE id = ? AND ativo = 1
    ");
    
    $stmt->execute([$_SESSION['usuario_id']]);
    
    if ($stmt->rowCount() == 1) {
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // Usuário não encontrado, fazer logout
        session_destroy();
        header('Location: login.html?erro=4');
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Erro ao buscar perfil: " . $e->getMessage());
    $erro = "Erro ao carregar dados do perfil.";
}

// Processar formulário de atualização
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $cidade = trim($_POST['cidade'] ?? '');
    $estado = trim($_POST['estado'] ?? '');
    $cep = trim($_POST['cep'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    
    // Validações básicas
    if (empty($nome)) {
        $erro = "Nome é obrigatório.";
    } elseif (empty($email)) {
        $erro = "Email é obrigatório.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Email inválido.";
    } else {
        try {
            // Verificar se o email já existe para outro usuário
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ? AND ativo = 1");
            $stmt->execute([$email, $_SESSION['usuario_id']]);
            
            if ($stmt->rowCount() > 0) {
                $erro = "Este email já está sendo usado por outro usuário.";
            } else {
                // Atualizar dados do usuário
                $stmt = $conn->prepare("
                    UPDATE usuarios SET 
                        nome = ?, 
                        email = ?, 
                        telefone = ?, 
                        cidade = ?, 
                        estado = ?, 
                        cep = ?,
                        data_nascimento = ?
                    WHERE id = ? AND ativo = 1
                ");
                
                $stmt->execute([
                    $nome, 
                    $email, 
                    $telefone, 
                    $cidade, 
                    $estado, 
                    $cep,
                    $data_nascimento ?: null,
                    $_SESSION['usuario_id']
                ]);
                
                if ($stmt->rowCount() > 0) {
                    // Atualizar dados da sessão
                    $_SESSION['usuario'] = $nome;
                    $_SESSION['email'] = $email;
                    
                    // Redirecionar para perfil.php após salvar com sucesso
                    header('Location: perfil.php?sucesso=1');
                    exit();
                } else {
                    $erro = "Nenhuma alteração foi feita.";
                }
            }
        } catch (PDOException $e) {
            error_log("Erro ao atualizar perfil: " . $e->getMessage());
            $erro = "Erro ao atualizar perfil. Tente novamente.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - Naturallis</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
            margin: 2rem auto;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            background-color: #6aa84f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 3em;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6aa84f;
            box-shadow: 0 0 0 2px rgba(106, 168, 79, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 2rem;
        }

        button {
            background-color: #6aa84f;
            color: white;
            border: none;
            padding: 0.75rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #5a9042;
        }

        button.secondary {
            background-color: #fff;
            border: 2px solid #6aa84f;
            color: #6aa84f;
        }

        button.secondary:hover {
            background-color: #f4f8f4;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <div class="profile-header">
            <div class="profile-picture">
                <i class="fas fa-user-edit"></i>
            </div>
            <h2>Editar Meu Perfil</h2>
        </div>

        <?php if ($erro): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($sucesso): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($sucesso); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="nome">
                    <i class="fas fa-user"></i> Nome Completo *
                </label>
                <input type="text" id="nome" name="nome" required 
                       value="<?php echo htmlspecialchars($usuario['nome']); ?>">
            </div>

            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email *
                </label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($usuario['email']); ?>">
            </div>

            <div class="form-group">
                <label for="telefone">
                    <i class="fas fa-phone"></i> Telefone
                </label>
                <input type="tel" id="telefone" name="telefone" 
                       value="<?php echo htmlspecialchars($usuario['telefone'] ?? ''); ?>"
                       placeholder="(11) 99999-9999">
            </div>

            <div class="form-group">
                <label for="data_nascimento">
                    <i class="fas fa-calendar"></i> Data de Nascimento
                </label>
                <input type="date" id="data_nascimento" name="data_nascimento" 
                       value="<?php echo htmlspecialchars($usuario['data_nascimento'] ?? ''); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="cidade">
                        <i class="fas fa-city"></i> Cidade
                    </label>
                    <input type="text" id="cidade" name="cidade" 
                           value="<?php echo htmlspecialchars($usuario['cidade'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="estado">
                        <i class="fas fa-map-marker-alt"></i> Estado
                    </label>
                    <select id="estado" name="estado">
                        <option value="">Selecione...</option>
                        <?php 
                        $estados = [
                            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                            'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                            'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                            'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                            'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                        ];
                        foreach ($estados as $sigla => $nome) {
                            $selected = ($usuario['estado'] ?? '') == $sigla ? 'selected' : '';
                            echo "<option value=\"$sigla\" $selected>$nome</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="cep">
                    <i class="fas fa-mail-bulk"></i> CEP
                </label>
                <input type="text" id="cep" name="cep" 
                       value="<?php echo htmlspecialchars($usuario['cep'] ?? ''); ?>"
                       placeholder="12345-678" maxlength="9">
            </div>

            <div class="form-actions">
                <button type="button" onclick="window.location.href='perfil.php'" class="secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </button>
                <button type="submit">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </div>

    <script>
        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length <= 10) {
                    value = value.replace(/(\d{2})(\d{4})(\d)/, '($1) $2-$3');
                } else {
                    value = value.replace(/(\d{2})(\d{5})(\d)/, '($1) $2-$3');
                }
                e.target.value = value;
            }
        });
    </script>
</body>
</html>