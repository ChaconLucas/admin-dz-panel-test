<?php
/**
 * Session Tracker - Incluir em todas as páginas do dashboard
 * Mantém registro de sessões ativas dos administradores
 */

// Verificar se existe sessão ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir conexão se não estiver incluída
if (!isset($conexao)) {
    require_once __DIR__ . '/../../../PHP/conexao.php';
}

function trackActiveSession($conexao) {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_logado'])) {
        return false;
    }
    
    try {
        // Criar tabela se não existir
        $createTable = "CREATE TABLE IF NOT EXISTS admin_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            INDEX idx_user_activity (user_id, last_activity),
            INDEX idx_session (session_id),
            UNIQUE KEY unique_user_session (user_id, session_id)
        ) ENGINE=InnoDB";
        
        mysqli_query($conexao, $createTable);
        
        $userId = $_SESSION['usuario_id'];
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Inserir ou atualizar sessão ativa
        $query = "INSERT INTO admin_sessions (user_id, session_id, ip_address, user_agent, last_activity) 
                  VALUES (?, ?, ?, ?, NOW()) 
                  ON DUPLICATE KEY UPDATE 
                  last_activity = NOW(), 
                  ip_address = VALUES(ip_address),
                  user_agent = VALUES(user_agent)";
        
        $stmt = mysqli_prepare($conexao, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $userId, $sessionId, $ipAddress, $userAgent);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            // Limpar sessões expiradas
            mysqli_query($conexao, "DELETE FROM admin_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
            
            return true;
        }
        
    } catch (Exception $e) {
        error_log("Erro ao rastrear sessão: " . $e->getMessage());
    }
    
    return false;
}

// Executar tracking automaticamente
if (isset($conexao)) {
    trackActiveSession($conexao);
}
?>