<?php
session_start();
require_once 'config/database.php';

// Verificar se o usuário está logado
$usuario_logado = isset($_SESSION['usuario_id']);
$nome_usuario = $usuario_logado ? $_SESSION['usuario'] : null;
$is_admin = $usuario_logado && isAdmin();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naturallis</title>
    <link rel="stylesheet" href="style.css">
    <!-- Adicionando Font Awesome para o ícone de login -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Estilo para os ícones de navegação */
        .login-icon {
            position: fixed;
            top: 20px;
            background-color: #6aa84f;
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1em;
            z-index: 1000;
        }

        /* Estilo específico para os botões (z-index para ordem correta) */
        .login-icon.loja-button {
            z-index: 1001;
        }
        
        .login-icon.admin-button {
            z-index: 1002;
            min-width: 100px;
            max-width: 120px;
            justify-content: center;
        }

        .login-icon:hover {
            background-color: #38761d;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .login-icon i {
            font-size: 1.2em;
        }

        /* Estilo quando usuário está logado */
        .login-icon.user-logged {
            background-color: #28a745;
            min-width: 150px;
            max-width: 250px;
            justify-content: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .login-icon.user-logged:hover {
            background-color: #218838;
        }

        @media (max-width: 768px) {
            .login-icon {
                padding: 10px;
                font-size: 1em;
                min-width: auto;
                position: relative !important;
                margin: 5px;
                display: inline-flex;
            }
            .login-icon span {
                display: inline;
            }
            .login-icon.user-logged {
                min-width: auto;
                max-width: 200px;
            }
        }

        @media (max-width: 480px) {
            .login-icon span {
                display: none;
            }
        }

        /* Estilos específicos para a página principal */
        .sustainability-container {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            margin: 2rem auto;
        }

        .sustainability-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .sustainability-content {
            text-align: left;
            color: #333;
            line-height: 1.6;
        }

        .sustainability-section {
            margin-bottom: 2rem;
        }

        .sustainability-section h3 {
            color: #38761d;
            margin-bottom: 1rem;
        }

        .eco-initiatives {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .initiative-card {
            background-color: #f4f8f4;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #6aa84f;
        }
    </style>
</head>
<body>
    <!-- Mensagem de sucesso do cadastro -->
    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] === 'cadastro'): ?>
        <div id="welcome-message" style="position: fixed; top: 10px; left: 50%; transform: translateX(-50%); background-color: #d4edda; color: #155724; padding: 15px 25px; border-radius: 8px; border: 1px solid #c3e6cb; box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 1000; animation: slideDown 0.5s ease;">
            <i class="fas fa-check-circle"></i> 
            <strong>Bem-vindo(a), <?php echo htmlspecialchars($nome_usuario); ?>!</strong> Seu cadastro foi realizado com sucesso!
            <button onclick="document.getElementById('welcome-message').style.display='none'" style="background: none; border: none; color: #155724; font-size: 18px; float: right; cursor: pointer; margin-left: 15px;">&times;</button>
        </div>
        <style>
            @keyframes slideDown {
                from { top: -100px; opacity: 0; }
                to { top: 10px; opacity: 1; }
            }
        </style>
        <script>
            // Auto-ocultar mensagem após 5 segundos
            setTimeout(() => {
                const message = document.getElementById('welcome-message');
                if (message) {
                    message.style.transition = 'opacity 0.5s ease';
                    message.style.opacity = '0';
                    setTimeout(() => message.style.display = 'none', 500);
                }
            }, 5000);
        </script>
    <?php endif; ?>

    <!-- Ícones de navegação -->
    <a href="produtos.php" class="login-icon loja-button" style="right: 330px;">
        <i class="fas fa-store"></i>
        <span>Loja</span>
    </a>
    
    <?php if ($usuario_logado): ?>
        <?php if ($is_admin): ?>
            <!-- Botão Admin -->
            <a href="admin/dashboard.php" class="login-icon admin-button" style="right: 200px;" title="Painel Administrativo">
                <i class="fas fa-cogs"></i>
                <span>Admin</span>
            </a>
        <?php endif; ?>
        
        <!-- Perfil do usuário -->
        <a href="perfil.php" class="login-icon user-logged" style="right: 20px;" title="Ir para o perfil">
            <i class="fas fa-user-check"></i>
            <span><?php echo htmlspecialchars($nome_usuario); ?></span>
        </a>
    <?php else: ?>
        <a href="login.html" class="login-icon" style="right: 20px;">
            <i class="fas fa-user"></i>
            <span>Login</span>
        </a>
    <?php endif; ?>

    <div class="sustainability-container">
        <div class="sustainability-header">
            <img src="naturallis.jpeg" alt="Naturallis Logo" class="logo-img">
            <h2>Nossa Compromisso com a Sustentabilidade</h2>
        </div>

        <div class="sustainability-content">
            <div class="sustainability-section">
                <h3>Missão Ambiental</h3>
                <p>Na Naturallis, acreditamos que a sustentabilidade é fundamental para um futuro melhor. Nosso compromisso vai além de simplesmente oferecer produtos naturais - buscamos ativamente reduzir nosso impacto ambiental em todas as operações.</p>
            </div>

            <div class="sustainability-section">
                <h3>Nossas Iniciativas Eco-friendly</h3>
                <div class="eco-initiatives">
                    <div class="initiative-card">
                        <h4>Embalagens Sustentáveis</h4>
                        <p>Utilizamos materiais biodegradáveis e recicláveis em todas as nossas embalagens.</p>
                    </div>
                    <div class="initiative-card">
                        <h4>Energia Renovável</h4>
                        <p>Nossas instalações são alimentadas por energia solar e outras fontes renováveis.</p>
                    </div>
                    <div class="initiative-card">
                        <h4>Resíduo Zero</h4>
                        <p>Programa de reciclagem e compostagem que visa eliminar o desperdício.</p>
                    </div>
                </div>
            </div>

            <div class="sustainability-section">
                <h3>Compromissos para 2026</h3>
                <ul>
                    <li>Reduzir em 50% nossa pegada de carbono</li>
                    <li>Alcançar 100% de embalagens recicláveis</li>
                    <li>Implementar programa de refil em todas as lojas</li>
                    <li>Expandir nosso programa de compostagem</li>
                </ul>
            </div>

            <div class="sustainability-section">
                <h3>Participe Conosco</h3>
                <p>Junte-se a nós nesta jornada por um mundo mais sustentável. Saiba mais sobre nossas iniciativas e como você pode contribuir visitando uma de nossas lojas ou entrando em contato conosco.</p>
                <button onclick="window.location.href='index.php'">Voltar para Home</button>
            </div>
        </div>
    </div>
</body>
</html>