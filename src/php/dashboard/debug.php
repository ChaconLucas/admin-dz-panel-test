<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Dashboard</title>
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(45deg, #ff00d4, #7380ec);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        .debug {
            background: white;
            padding: 20px;
            margin: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="debug">
        <h2>Debug Dashboard</h2>
        <p><strong>Sessão ativa:</strong> <?php echo isset($_SESSION['usuario_logado']) ? 'Sim' : 'Não'; ?></p>
        <p><strong>CSS carregado:</strong> Testando estilo...</p>
        <p><strong>Caminho CSS:</strong> ../../css/dashboard.css</p>
        <p><strong>Caminho Imagem:</strong> ../../../assets/images/Logodz.png</p>
    </div>

    <div class="container">
        <aside>
            <div class="top">
                <div class="logo">
                    <img src="../../../assets/images/Logodz.png" alt="Logo" style="width: 50px;">
                    <h2 class="danger">D&Z</h2>
                </div>
            </div>
            
            <div class="sidebar">
                <a href="#" class="active">
                    <span class="material-symbols-sharp">grid_view</span>
                    <h3>Painel</h3>
                </a>
                <a href="#">
                    <span class="material-symbols-sharp">group</span>
                    <h3>Clientes</h3>
                </a>
            </div>
        </aside>
        
        <main>
            <h1>Dashboard Funcional</h1>
            <div class="insights">
                <div class="sales">
                    <span class="material-symbols-sharp">bar_chart</span>
                    <div class="middle">
                        <div class="left">
                            <h3>Teste</h3>
                            <h1>R$ 1.000</h1>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>