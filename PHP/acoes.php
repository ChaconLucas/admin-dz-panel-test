<?php
session_start();
require 'conexao.php';

// Incluir sistema de logs automático
require_once '../src/php/auto_log.php';

    if (isset($_POST['create_usuario'])){
        $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
        $email = mysqli_real_escape_string($conexao, trim($_POST['email']));
        $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
        $senha = isset($_POST['senha']) ? mysqli_real_escape_string($conexao, password_hash(trim($_POST['senha']), PASSWORD_DEFAULT)) : '';

        $sql = "INSERT INTO teste_dz (nome, email, data_nascimento, senha) VALUES ('$nome', '$email', '$data_nascimento', '$senha')";

        mysqli_query($conexao, $sql);

        if (mysqli_affected_rows($conexao) > 0){
            // LOG: Criação de usuário
            $detalhes = "email: {$email}, data nascimento: {$data_nascimento}";
            registrar_log_acao($conexao, 'criar_usuario', $nome, $detalhes);
            
            $_SESSION['mensagem'] = "Usuário criado com sucesso!";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['mensagem'] = "Erro ao criar usuário.";
            header('Location: index.php');
            exit;
        }
    }

    if (isset($_POST['update_usuario'])){
        $usuario_id = mysqli_real_escape_string($conexao, trim($_POST['usuario_id']));

        $nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
        $email = mysqli_real_escape_string($conexao, trim($_POST['email']));
        $data_nascimento = mysqli_real_escape_string($conexao, trim($_POST['data_nascimento']));
        $senha = mysqli_real_escape_string($conexao, trim($_POST['senha']));

        // Buscar dados atuais antes de alterar
        $dados_antes = buscar_dados_atuais($conexao, 'teste_dz', $usuario_id, ['nome', 'email', 'data_nascimento']);
        
        $sql = "UPDATE teste_dz SET nome='$nome', email='$email', data_nascimento='$data_nascimento'";
        if (!empty($senha)) {
            $sql .= ", senha='" . password_hash($senha, PASSWORD_DEFAULT) . "'";
        }
        $sql .= " WHERE id='$usuario_id'";
        mysqli_query($conexao, $sql);

        if (mysqli_affected_rows($conexao) > 0){
            // LOGS: Registrar apenas campos que mudaram
            if (!empty($dados_antes)) {
                $alteracoes = [];
                
                if ($dados_antes['nome'] != $nome) {
                    registrar_log_alteracao($conexao, 'usuario_dados', $nome, 'nome', $dados_antes['nome'], $nome);
                }
                
                if ($dados_antes['email'] != $email) {
                    registrar_log_alteracao($conexao, 'usuario_dados', $nome, 'email', $dados_antes['email'], $email);
                }
                
                if ($dados_antes['data_nascimento'] != $data_nascimento) {
                    registrar_log_alteracao($conexao, 'usuario_dados', $nome, 'data_nascimento', $dados_antes['data_nascimento'], $data_nascimento);
                }
                
                if (!empty($senha)) {
                    registrar_log_acao($conexao, 'alterar_permissoes', $nome, 'senha alterada');
                }
            }
            
            $_SESSION['mensagem'] = "Usuário atualizado com sucesso!";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['mensagem'] = "Erro ao atualizar usuário.";
            header('Location: index.php');
            exit;
        }
    }

    if(isset($_POST['delete_usuario'])){
        $usuario_id = mysqli_real_escape_string($conexao, $_POST['delete_usuario']);
        
        // Buscar dados do usuário antes de excluir
        $dados_usuario = buscar_dados_atuais($conexao, 'teste_dz', $usuario_id, ['nome', 'email']);
        
        $sql = "DELETE FROM teste_dz WHERE id='$usuario_id'";
        mysqli_query($conexao, $sql);

        if (mysqli_affected_rows($conexao) > 0){
            // LOG: Exclusão de usuário
            if (!empty($dados_usuario)) {
                $nome = $dados_usuario['nome'];
                $detalhes = "email: " . $dados_usuario['email'];
                registrar_log_acao($conexao, 'excluir_usuario', $nome, $detalhes);
            }
            
            $_SESSION['mensagem'] = "Usuário excluído com sucesso!";
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['mensagem'] = "Erro ao excluir usuário.";
            header('Location: index.php');
            exit;
        }
    }
?>
