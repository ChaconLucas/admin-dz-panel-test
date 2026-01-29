<?php
session_start();
// Verificar se está logado
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: /../src/html/chat-cliente.html');
    exit();
}

// Incluir sistema de chat (apenas classes, sem API endpoints)
try {
    require_once '../sistema.php';
    
    if (!isset($chat_manager)) {
        throw new Exception('ChatManager não foi inicializado');
    }
    
    $stats = $chat_manager->obterEstatisticas();
    $conversas = $chat_manager->obterConversas();
} catch (Exception $e) {
    die("Erro no sistema de chat: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine());
}

// Incluir contador de mensagens não lidas
require_once 'helper-contador.php';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/dashboard.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="../../css/modern-chat.css?v=<?php echo time(); ?>" />
    <link
      href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp"
      rel="stylesheet"
    />
    <title>Mensagens - Dashboard</title>
  </head>

  <body>
    <div class="container">
      <aside>
        <div class="top">
          <div class="logo">
            <img src="../../../assets/images/Logodz.png" />
            <a href="index.php"><h2 class="danger">D&Z</h2></a>
          </div>

          <div class="close" id="close-btn">
            <span class="material-symbols-sharp">close</span>
          </div>
        </div>

        <div class="sidebar">
          <a href="index.php" class="panel">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Painel</h3>
          </a>

          <a href="customers.php">
            <span class="material-symbols-sharp">group</span>
            <h3>Clientes</h3>
          </a>

          <a href="orders.php">
            <span class="material-symbols-sharp">Orders</span>
            <h3>Pedidos</h3>
          </a>



          <a href="analytics.php">
            <span class="material-symbols-sharp">Insights</span>
            <h3>Gráficos</h3>
          </a>

          <a href="menssage.php" class="active">
            <span class="material-symbols-sharp">Mail</span>
            <h3>Mensagens</h3>
            <span class="message-count"><?php echo $nao_lidas; ?></span>
          </a>

          <a href="products.php">
            <span class="material-symbols-sharp">Inventory</span>
            <h3>Produtos</h3>
          </a>



          <div class="menu-item-container">
            <a href="geral.php" class="menu-item-with-submenu">
              <span class="material-symbols-sharp">Settings</span>
              <h3>Configurações</h3>
            </a>
            
            <div class="submenu">
              <a href="geral.php">
                <span class="material-symbols-sharp">tune</span>
                <h3>Geral</h3>
              </a>
              <a href="pagamentos.php">
                <span class="material-symbols-sharp">payments</span>
                <h3>Pagamentos</h3>
              </a>
              <a href="frete.php">
                <span class="material-symbols-sharp">local_shipping</span>
                <h3>Frete</h3>
              </a>
              <a href="automacao.php">
                <span class="material-symbols-sharp">automation</span>
                <h3>Automação</h3>
              </a>
              <a href="metricas.php">
                <span class="material-symbols-sharp">analytics</span>
                <h3>Métricas</h3>
              </a>
              <a href="settings.php">
                <span class="material-symbols-sharp">group</span>
                <h3>Usuários</h3>
              </a>
            </div>
          </div>

          <a href="revendedores.php">
            <span class="material-symbols-sharp">handshake</span>
            <h3>Revendedores</h3>
          </a>

          <a href="../../../PHP/logout.php">
            <span class="material-symbols-sharp">Logout</span>
            <h3>Sair</h3>
          </a>
        </div>
      </aside>

      <!----------FINAL ASIDE------------>
      <main>
        <h1>Central de Mensagens</h1>

        <div class="insights modern-chat-panel">
          <!-- Modern Stats Cards -->
          <div class="chat-stats">
            <div class="stat-card messages-today">
              <div class="stat-icon">
                <span class="material-symbols-sharp">mail</span>
              </div>
              <div class="stat-content">
                <h3><?php echo $stats['mensagens_hoje']; ?></h3>
                <p>Mensagens Hoje</p>
              </div>
            </div>
                        
            <div class="stat-card unread-messages">
              <div class="stat-icon">
                <span class="material-symbols-sharp">person_raised_hand</span>
              </div>
              <div class="stat-content">
                <h3><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano')); ?></h3>
                <p>Mensagens Escaladas</p>
              </div>
            </div>

 <div class="stat-card active-chats">
              <div class="stat-icon">
                <span class="material-symbols-sharp">check_circle</span>
              </div>
              <div class="stat-content">
                <h3><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'resolvida')); ?></h3>
                <p>Conversas Resolvidas</p>
              </div>
            </div>

          </div>
        </div>

          <!-- Modern Chat Interface -->
          <div class="chat-interface">
            <!-- Conversations Sidebar -->
            <div class="conversations-sidebar">
              <div class="sidebar-header">
                <h3>Conversas 
                  <?php if($stats['nao_lidas'] > 0): ?>
                    <span class="count"><?php echo $stats['nao_lidas']; ?></span>
                  <?php endif; ?>
                </h3>
                <div class="conversation-filters">
                  <button class="filter-tab active" onclick="filtrarConversas('todas')">
                    <span class="material-symbols-sharp">forum</span>
                    Todas
                    <span class="count"><?php echo count($conversas); ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('nao_lidas')">
                    <span class="material-symbols-sharp">mark_chat_unread</span>
                    Não Lidas
                    <span class="count"><?php echo array_sum(array_column($conversas, 'nao_lidas')); ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('ativa')">
                    <span class="material-symbols-sharp">circle</span>
                    Ativas
                    <span class="count"><?php echo $stats['conversas_ativas']; ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('aguardando_humano')">
                    <span class="material-symbols-sharp">person_raised_hand</span>
                    Escalado
                    <span class="count"><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano')); ?></span>
                  </button>
                  <button class="filter-tab" onclick="filtrarConversas('resolvida')">
                    <span class="material-symbols-sharp">check_circle</span>
                    Resolvidas
                    <span class="count"><?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'resolvida')); ?></span>
                  </button>
                </div>
              </div>
              
              <div class="conversations-list">
                <?php if(!empty($conversas)): ?>
                  <?php foreach($conversas as $conversa): ?>
                    <div class="conversation-item" 
                         data-id="<?php echo $conversa['id']; ?>"
                         data-status="<?php echo $conversa['status']; ?>"
                         data-nao-lidas="<?php echo $conversa['nao_lidas']; ?>"
                         onclick="selecionarConversa(<?php echo $conversa['id']; ?>, '<?php echo htmlspecialchars($conversa['usuario_nome'] ?: 'Cliente #' . $conversa['id']); ?>')">
                      
                      <div class="conversation-avatar">
                        <div class="avatar-circle">
                          <?php echo strtoupper(substr($conversa['usuario_nome'] ?: 'Cliente', 0, 1)); ?>
                        </div>
                        <?php if($conversa['nao_lidas'] > 0): ?>
                          <div class="unread-indicator"></div>
                        <?php endif; ?>
                      </div>
                      
                      <div class="conversation-content">
                        <div class="conversation-main">
                          <h4><?php echo htmlspecialchars($conversa['usuario_nome'] ?: 'Cliente #' . $conversa['id']); ?></h4>
                          <p class="conversation-preview">
                            <?php echo htmlspecialchars(substr($conversa['ultima_mensagem'] ?? 'Sem mensagens', 0, 40)); ?>...
                          </p>
                        </div>
                        
                        <div class="conversation-meta">
                          <span class="conversation-time"><?php echo date('H:i', strtotime($conversa['updated_at'] ?? 'now')); ?></span>
                          <span class="status-badge status-<?php echo $conversa['status']; ?>">
                            <?php 
                            switch($conversa['status']) {
                              case 'ativa': echo 'ATIVA'; break;
                              case 'aguardando_humano': echo 'PENDENTE'; break;
                              case 'resolvida': echo 'RESOLVIDA'; break;
                            }
                            ?>
                          </span>
                          <?php if($conversa['nao_lidas'] > 0): ?>
                            <span class="unread-count"><?php echo $conversa['nao_lidas']; ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      
                      <div class="conversation-actions">
                        <button onclick="event.stopPropagation(); marcarComoNaoLida(<?php echo $conversa['id']; ?>)" 
                                class="action-btn" title="Marcar como não lida">
                          <span class="material-symbols-sharp">mark_email_unread</span>
                        </button>
                        <button onclick="event.stopPropagation(); deletarConversa(<?php echo $conversa['id']; ?>)" 
                                class="action-btn delete" title="Deletar">
                          <span class="material-symbols-sharp">delete</span>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="empty-state">
                    <span class="material-symbols-sharp">forum</span>
                    <h4>Nenhuma conversa</h4>
                    <p>As conversas aparecerão aqui quando os clientes iniciarem o chat</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            
            <!-- Chat Main Area -->
            <div class="chat-main">
              <div class="chat-placeholder">
                <div class="placeholder-content">
                  <span class="material-symbols-sharp">chat</span>
                  <h3>Selecione uma conversa</h3>
                  <p>Clique em uma conversa para visualizar e responder mensagens</p>
                </div>
              </div>
              
              <div id="conversa-ativa" class="active-conversation" style="display: none;">
                <div class="chat-header">
                  <div id="chat-header-content">
                    <!-- Conteúdo será preenchido via JavaScript -->
                  </div>
                </div>
                
                <div class="messages-container" id="mensagens-container">
                  <!-- Mensagens serão carregadas aqui -->
                </div>
                
                <div class="message-input">
                  <div class="quick-actions">
                    <button onclick="escalarParaHumano()" class="quick-btn escalate">
                      <span class="material-symbols-sharp">person_add</span>
                      Escalar para Humano
                    </button>
                    <button onclick="resolverConversa()" class="quick-btn resolve">
                      <span class="material-symbols-sharp">check_circle</span>
                      Resolver Conversa
                    </button>
                  </div>
                  
                  <div class="input-area">
                    <input type="text" 
                           id="admin-mensagem" 
                           placeholder="Digite sua mensagem..." 
                           onkeypress="if(event.key==='Enter') enviarMensagemAdmin()">
                    <button onclick="enviarMensagemAdmin()" class="send-btn">
                      <span class="material-symbols-sharp">send</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
      </main>

      <!----------FINAL MAIN---------->
      <div class="right">
        <div class="top">
          <button id="menu-btn">
            <span class="material-symbols-sharp"> menu </span>
          </button>
          <div class="theme-toggler">
            <span class="material-symbols-sharp active"> wb_sunny </span
            ><span class="material-symbols-sharp"> bedtime </span>
          </div>
          <div class="profile">
            <div class="info">
              <p>Olá, <b><?= isset($_SESSION['usuario_nome']) ? $_SESSION['usuario_nome'] : 'Usuário'; ?></b></p>
              <small class="text-muted">Admin</small>
            </div>
            <div class="profile-photo">
              <img src="../../../assets/images/logo.png" alt="" />
            </div>
          </div>
        </div>

        <!----------FINAL DO TOP RIGHT---------->
        <div class="recent-updates">
          <h2>Atualizações Recentes</h2>
          <div class="updates">
            <div class="update">
              <div class="profile-photo">
                <img src="../../../assets/images/profile-2.jpg" />
              </div>
              <div class="message">
                <p><b>Sistema</b> Chat removido com sucesso</p>
                <small class="text-muted">Há 2 minutos</small>
              </div>
            </div>
            <div class="update">
              <div class="profile-photo">
                <img src="../../../assets/images/profile-3.jpg" />
              </div>
              <div class="message">
                <p><b>Admin</b> Sistema pronto para nova implementação</p>
                <small class="text-muted">Há 5 minutos</small>
              </div>
            </div>
          </div>
        </div>

        <!----------FINAL ATUALIZACOES---------->
        <div class="sales-analytics">
          <h2>Próximos Passos</h2>
          <div class="item online">
            <div class="icon">
              <span class="material-symbols-sharp">api</span>
            </div>
            <div class="right">
              <div class="info">
                <h3>Escolher API de IA</h3>
                <small class="text-muted">Selecione uma nova API</small>
              </div>
              <h5 class="success">Pendente</h5>
            </div>
          </div>
          <div class="item offline">
            <div class="icon">
              <span class="material-symbols-sharp">integration_instructions</span>
            </div>
            <div class="right">
              <div class="info">
                <h3>Configurar Sistema</h3>
                <small class="text-muted">Implementar nova solução</small>
              </div>
              <h5 class="danger">Aguardando</h5>
            </div>
          </div>
          <div class="item customers">
            <div class="icon">
              <span class="material-symbols-sharp">chat</span>
            </div>
            <div class="right">
              <div class="info">
                <h3>Interface do Cliente</h3>
                <small class="text-muted">Criar nova interface</small>
              </div>
              <h5 class="success">Pronto</h5>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="../../js/dashboard.js?v=<?php echo time(); ?>"></script>
    
    <script>
      let conversaAtiva = null;
      let nomeClienteAtivo = null;
      const nomeAdminLogado = '<?php echo addslashes($_SESSION['usuario_nome'] ?? 'Admin'); ?>';
      
      // Dados PHP para JavaScript
      const mensagensEscaladasPHP = <?php echo count(array_filter($conversas, fn($c) => $c['status'] == 'aguardando_humano')); ?>;
      console.log('🐘 Mensagens escaladas do PHP:', mensagensEscaladasPHP);
      
      // Filtrar conversas (função corrigida para não quebrar event listeners)
      function filtrarConversas(status) {
        // Atualizar botões - procurar pelo filtro correto
        document.querySelectorAll('.filter-tab').forEach(btn => {
          btn.classList.remove('active');
        });
        
        // Encontrar e ativar o botão correto baseado no onclick
        document.querySelectorAll('.filter-tab').forEach(btn => {
          const onclick = btn.getAttribute('onclick');
          if (onclick && onclick.includes(`'${status}'`)) {
            btn.classList.add('active');
          }
        });
        
        // Filtrar itens com animação suave
        document.querySelectorAll('.conversation-item').forEach(item => {
          const itemStatus = item.getAttribute('data-status');
          const naoLidas = parseInt(item.getAttribute('data-nao-lidas')) || 0;
          
          let mostrar = false;
          switch(status) {
            case 'todas':
              mostrar = true;
              break;
            case 'nao_lidas':
              mostrar = naoLidas > 0;
              break;
            case 'ativa':
              mostrar = itemStatus === 'ativa';
              break;
            case 'resolvida':
              mostrar = itemStatus === 'resolvida';
              break;
            case 'aguardando_humano':
              mostrar = itemStatus === 'aguardando_humano';
              break;
            default:
              mostrar = itemStatus === status;
          }
          
          // Aplicar filtro usando classes CSS para manter o layout
          if (mostrar) {
            // Remover todas as classes de filtro
            item.classList.remove('filtered-hidden', 'animate-out', 'hide');
            // Limpar estilos inline que possam interferir
            item.style.display = '';
            item.style.opacity = '';
            item.style.transform = '';
            
            // Re-adicionar event listeners se necessário
            if (!item.hasAttribute('data-listeners-added')) {
              item.setAttribute('data-listeners-added', 'true');
              
              // Restaurar hover effects
              item.addEventListener('mouseenter', function() {
                if (!this.classList.contains('selected') && !this.classList.contains('filtered-hidden')) {
                  this.style.transform = 'translateX(4px)';
                }
              });
              
              item.addEventListener('mouseleave', function() {
                if (!this.classList.contains('selected') && !this.classList.contains('filtered-hidden')) {
                  this.style.transform = 'translateX(0)';
                }
              });
            }
          } else {
            // Usar classes CSS para esconder mantendo o flexbox
            item.classList.add('filtered-hidden');
            setTimeout(() => {
              if (item.classList.contains('filtered-hidden')) {
                item.classList.add('animate-out');
                setTimeout(() => {
                  if (item.classList.contains('animate-out')) {
                    item.classList.add('hide');
                  }
                }, 200);
              }
            }, 50);
          }
        });
        
        console.log(`Filtro aplicado: ${status}`);
      }
      
      // Selecionar conversa (função otimizada com marcação imediata)
      function selecionarConversa(id, nome) {
        // Garantir que sempre temos um nome válido
        nome = nome || `Cliente #${id}`;
        console.log('🎯 Selecionando conversa:', id, nome);
        
        // Evitar reprocessamento desnecessário
        if (conversaAtiva === parseInt(id)) {
          console.log('⚠️ Conversa já está ativa, ignorando');
          return;
        }
        
        conversaAtiva = parseInt(id);
        nomeClienteAtivo = nome; // Armazenar o nome do cliente ativo
        
        // PRIMEIRO: Marcar como lida IMEDIATAMENTE (antes mesmo de carregar mensagens)
        const conversaItem = document.querySelector(`[data-id="${id}"]`);
        if (conversaItem && parseInt(conversaItem.getAttribute('data-nao-lidas')) > 0) {
          console.log('🔄 Marcando conversa como lida imediatamente');
          
          // Remover indicadores visuais imediatamente
          const unreadIndicator = conversaItem.querySelector('.unread-indicator');
          const unreadCount = conversaItem.querySelector('.unread-count');
          
          if (unreadIndicator) unreadIndicator.remove();
          if (unreadCount) unreadCount.remove();
          
          conversaItem.setAttribute('data-nao-lidas', '0');
          
          // Feedback visual
          conversaItem.style.backgroundColor = '#d4edda';
          conversaItem.style.borderColor = '#28a745';
          setTimeout(() => {
            conversaItem.style.backgroundColor = '';
            conversaItem.style.borderColor = '';
          }, 1000);
        }
        
        // Destacar conversa selecionada
        document.querySelectorAll('.conversation-item').forEach(item => {
          if (parseInt(item.getAttribute('data-id')) === conversaAtiva) {
            item.classList.add('selected');
          } else {
            item.classList.remove('selected');
          }
        });
        
        // Mostrar chat
        const placeholder = document.querySelector('.chat-placeholder');
        const chatAtivo = document.getElementById('conversa-ativa');
        
        if (placeholder) placeholder.style.display = 'none';
        if (chatAtivo) {
          chatAtivo.style.display = 'flex';
        }
        
        // Atualizar header
        const headerContent = document.getElementById('chat-header-content');
        if (headerContent) {
          headerContent.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <div>
                <h3 style="margin: 0; color: var(--color-dark);">${nome}</h3>
                <small style="color: var(--color-dark-variant);">Conversa #${id}</small>
              </div>
              <div style="display: flex; gap: 0.5rem;">
                <button onclick="escalarParaHumano()" class="mini-btn" title="Escalar para humano">
                  <span class="material-symbols-sharp">person_add</span>
                </button>
                <button onclick="resolverConversa()" class="mini-btn" title="Resolver conversa">
                  <span class="material-symbols-sharp">check_circle</span>
                </button>
              </div>
            </div>
          `;
        }
        
        // Carregar mensagens (que também tentará marcar como lida na API)
        carregarMensagens(id);
        
        // Atualizar contadores
        setTimeout(() => {
          atualizarContadoresFiltros();
          if (window.atualizarContadorMensagens) {
            window.atualizarContadorMensagens();
          }
        }, 200);
      }
      
      // Carregar mensagens
      async function carregarMensagens(conversaId) {
        try {
          const url = `../sistema.php?api=1&endpoint=admin&action=get_messages&conversa_id=${conversaId}`;
          const response = await fetch(url);
          
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
          
          const mensagens = await response.json();
          console.log('Mensagens recebidas:', mensagens);
          
          // Verificar se é um array de mensagens
          if (!Array.isArray(mensagens)) {
            console.error('Resposta não é um array:', mensagens);
            throw new Error('Formato de resposta inválido');
          }
          
          const container = document.getElementById('mensagens-container');
          container.innerHTML = '';
          
          mensagens.forEach(msg => {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message-bubble ${msg.remetente === 'usuario' ? 'client' : (msg.remetente === 'admin' ? 'admin' : 'ia')}`;
            
            // Usar nome salvo no banco se disponível, senão fallback
            const remetente = (msg.nome_remetente && msg.nome_remetente !== 'null') ? msg.nome_remetente : 
                             (msg.remetente === 'usuario' ? (nomeClienteAtivo || 'Cliente') : 
                              msg.remetente === 'admin' ? nomeAdminLogado : 'DAIze');
            
            // Avatar especial para DAIze (logo da marca) ou letra normal
            let avatarContent;
            if (remetente === 'DAIze' || msg.remetente === 'ia') {
              // Usar logo da marca D&Z (borboleta)
              avatarContent = `
                <img src="../../../assets/images/logodz.png" alt="DAIze" 
                     style="width: 85%; height: 85%; object-fit: contain;" 
                     onerror="this.parentNode.innerHTML='🦋'; this.parentNode.style.fontSize='1.4rem'">
              `;
            } else {
              avatarContent = remetente.charAt(0).toUpperCase();
            }
            const timestamp = new Date(msg.timestamp).toLocaleString('pt-BR', {
              hour: '2-digit', 
              minute: '2-digit',
              day: '2-digit',
              month: '2-digit'
            });
            
            // Estrutura diferente para admin/IA (avatar à direita) vs cliente (avatar à esquerda)
            if (msg.remetente === 'admin' || msg.remetente === 'ia') {
              messageDiv.innerHTML = `
                <div class="message-wrapper">
                  <div class="message-header">
                    <span class="message-sender">${remetente}</span>
                    <span class="message-time">${timestamp}</span>
                  </div>
                  <div class="message-content">
                    <div class="message-text">${msg.conteudo}</div>
                  </div>
                </div>
                <div class="message-avatar">${avatarContent}</div>
              `;
            } else {
              messageDiv.innerHTML = `
                <div class="message-avatar">${avatarContent}</div>
                <div class="message-wrapper">
                  <div class="message-header">
                    <span class="message-sender">${remetente}</span>
                    <span class="message-time">${timestamp}</span>
                  </div>
                  <div class="message-content">
                    <div class="message-text">${msg.conteudo}</div>
                  </div>
                </div>
              `;
            }
            
            container.appendChild(messageDiv);
          });
          
          container.scrollTop = container.scrollHeight;
          
          // Marcar mensagens como lidas
          await marcarMensagensLidas(conversaId);
          
        } catch (error) {
          console.error('Erro ao carregar mensagens:', error);
          const container = document.getElementById('mensagens-container');
          if (container) {
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: #ff0066;">❌ Erro ao carregar mensagens. Verifique o console.</div>';
          }
        }
      }
      
      // Marcar mensagens como lidas (função corrigida e fortalecida)
      async function marcarMensagensLidas(conversaId) {
        console.log('🔄 Tentando marcar mensagens como lidas para conversa:', conversaId);
        
        // SEMPRE atualizar interface visual primeiro (para feedback imediato)
        const conversaItem = document.querySelector(`[data-id="${conversaId}"]`);
        if (conversaItem) {
          console.log('📝 Atualizando interface visual para conversa:', conversaId);
          
          // Remover indicador de não lida
          const unreadIndicator = conversaItem.querySelector('.unread-indicator');
          if (unreadIndicator) {
            console.log('🔴 Removendo indicador de não lida');
            unreadIndicator.remove();
          }
          
          // Remover contador de não lidas
          const unreadCount = conversaItem.querySelector('.unread-count');
          if (unreadCount) {
            console.log('🔢 Removendo contador de não lidas:', unreadCount.textContent);
            unreadCount.remove();
          }
          
          // Atualizar atributo data
          conversaItem.setAttribute('data-nao-lidas', '0');
          console.log('✅ Atributo data-nao-lidas definido como 0');
          
          // Feedback visual imediato
          conversaItem.style.transition = 'all 0.3s ease';
          conversaItem.style.backgroundColor = '#d4edda';
          conversaItem.style.borderColor = '#28a745';
          
          setTimeout(() => {
            conversaItem.style.backgroundColor = '';
            conversaItem.style.borderColor = '';
          }, 1500);
        }
        
        // Tentar atualizar no backend (sem bloquear a UI)
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=mark_messages_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversa_id: conversaId })
          });
          
          if (!response.ok) {
            console.warn('⚠️ Resposta HTTP não ok:', response.status);
            return;
          }
          
          const result = await response.json();
          console.log('📊 Resposta da API:', result);
          
          if (result.success) {
            console.log('✅ API confirmou: mensagens marcadas como lidas');
          } else {
            console.warn('❌ API retornou erro:', result.error || 'Erro desconhecido');
            // Não reverter a interface - manter como lida visualmente
          }
          
        } catch (error) {
          console.error('🚨 Erro de conexão com API:', error);
          // Não reverter a interface - manter como lida visualmente
        }
        
        // SEMPRE atualizar contadores (independente da API)
        setTimeout(() => {
          atualizarContadoresFiltros();
          if (window.atualizarContadorMensagens) {
            window.atualizarContadorMensagens();
          }
        }, 100);
      }
      
      // Enviar mensagem admin
      async function enviarMensagemAdmin() {
        console.log('🚀 Tentando enviar mensagem, conversa ativa:', conversaAtiva);
        
        if (!conversaAtiva) {
          console.error('❌ Nenhuma conversa ativa');
          return;
        }
        
        const input = document.getElementById('admin-mensagem');
        const mensagem = input.value.trim();
        
        console.log('📝 Mensagem a enviar:', mensagem);
        
        if (!mensagem) {
          console.warn('⚠️ Mensagem vazia');
          return;
        }
        
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=send_admin_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              conversa_id: conversaAtiva,
              mensagem: mensagem
            })
          });
          
          const result = await response.json();
          console.log('📨 Resposta da API:', result);
          
          if (result.success) {
            console.log('✅ Mensagem enviada com sucesso');
            input.value = '';
            carregarMensagens(conversaAtiva);
          } else {
            console.error('❌ Erro da API:', result.error);
            alert('Erro ao enviar mensagem: ' + result.error);
          }
        } catch (error) {
          console.error('❌ Erro ao enviar mensagem:', error);
          alert('Erro de conexão: ' + error.message);
        }
      }
      
      // Escalar para humano
      async function escalarParaHumano() {
        if (!conversaAtiva) return;
        
        if (confirm('Deseja escalar esta conversa para atendimento humano?')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=escalar_humano', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                conversa_id: conversaAtiva
              })
            });
            
            const result = await response.json();
            
            if (result.success) {
              alert('Conversa escalada com sucesso!');
              location.reload();
            } else {
              alert('Erro: ' + result.error);
            }
          } catch (error) {
            console.error('Erro:', error);
            alert('Erro de conexão');
          }
        }
      }
      
      // Resolver conversa
      async function resolverConversa() {
        if (!conversaAtiva) return;
        
        if (confirm('Deseja marcar esta conversa como resolvida?')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=resolver_conversa', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                conversa_id: conversaAtiva
              })
            });
            
            const result = await response.json();
            
            if (result.success) {
              alert('Conversa resolvida com sucesso!');
              location.reload();
            } else {
              alert('Erro: ' + result.error);
            }
          } catch (error) {
            console.error('Erro:', error);
            alert('Erro de conexão');
          }
        }
      }
      
      // Marcar como não lida
      async function marcarComoNaoLida(conversaId) {
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=marcar_nao_lida', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ conversa_id: conversaId })
          });
          
          const result = await response.json();
          
          if (result.success) {
            const item = document.querySelector(`[data-id="${conversaId}"]`);
            if (item) {
              // Atualizar atributo
              item.setAttribute('data-nao-lidas', '1');
              
              // Adicionar indicador visual se não existir
              const avatar = item.querySelector('.conversation-avatar');
              let indicator = avatar.querySelector('.unread-indicator');
              if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'unread-indicator';
                avatar.appendChild(indicator);
              }
              
              // Adicionar contador se não existir
              const footer = item.querySelector('.conversation-footer');
              let unreadCount = footer.querySelector('.unread-count');
              if (!unreadCount) {
                unreadCount = document.createElement('span');
                unreadCount.className = 'unread-count';
                unreadCount.textContent = '1';
                footer.appendChild(unreadCount);
              } else {
                unreadCount.textContent = '1';
              }
              
              // Feedback visual
              item.style.transition = 'all 0.3s ease';
              item.style.backgroundColor = 'var(--color-warning)';
              item.style.transform = 'scale(1.02)';
              
              setTimeout(() => {
                item.style.backgroundColor = '';
                item.style.transform = '';
              }, 800);
            }
            
            mostrarToast('Marcado como não lida!');
            
            // Atualizar contadores
            atualizarContadoresFiltros();
            if (window.atualizarContadorMensagens) {
              setTimeout(window.atualizarContadorMensagens, 500);
            }
          }
        } catch (error) {
          console.error('Erro:', error);
        }
      }
      
      // Função arquivarConversa removida - não é mais necessária
      
      // Deletar conversa
      async function deletarConversa(conversaId) {
        if (confirm('⚠️ ATENÇÃO: Deseja realmente DELETAR esta conversa? Esta ação não pode ser desfeita!')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=deletar_conversa', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ conversa_id: conversaId })
            });
            
            const result = await response.json();
            
            if (result.success) {
              const item = document.querySelector(`[data-id="${conversaId}"]`);
              if (item) {
                item.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => {
                  item.remove();
                  if (conversaAtiva === conversaId) {
                    document.querySelector('.chat-placeholder').style.display = 'flex';
                    document.getElementById('conversa-ativa').style.display = 'none';
                    conversaAtiva = null;
                    nomeClienteAtivo = null;
                  }
                }, 300);
              }
              mostrarToast('Conversa deletada!', 'danger');
              if (window.atualizarContadorMensagens) {
                setTimeout(window.atualizarContadorMensagens, 500);
              }
            }
          } catch (error) {
            console.error('Erro:', error);
          }
        }
      }
      
      // Mostrar toast
      function mostrarToast(mensagem, tipo = 'success') {
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
          <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span class="material-symbols-sharp">${tipo === 'success' ? 'check_circle' : 'error'}</span>
            ${mensagem}
          </div>
        `;
        
        if (tipo === 'danger') {
          toast.style.background = 'linear-gradient(45deg, var(--color-danger), #ff6b9d)';
        }
        
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
      }
      
      // Testar API Groq
      async function testarGroqAPI() {
        try {
          const response = await fetch('../sistema.php?api=1&endpoint=admin&action=get_stats');
          const result = await response.json();
          
          if (result.success) {
            alert('✅ API Groq funcionando!\n\nResposta: ' + result.message);
          } else {
            alert('❌ Erro na API Groq:\n\n' + result.message);
          }
        } catch (error) {
          alert('❌ Erro de conexão: ' + error.message);
        }
      }
      
      // Auto-atualizar a cada 30 segundos
      setInterval(() => {
        if (conversaAtiva) {
          carregarMensagens(conversaAtiva);
        }
      }, 30000);
      
      // Função para gerenciar indicador de scroll
      function setupScrollIndicator() {
        const conversationsList = document.querySelector('.conversations-list');
        const sidebar = document.querySelector('.conversations-sidebar');
        
        if (!conversationsList || !sidebar) return;
        
        function checkScroll() {
          const hasScroll = conversationsList.scrollHeight > conversationsList.clientHeight;
          sidebar.classList.toggle('has-scroll', hasScroll);
        }
        
        // Verificar inicial
        checkScroll();
        
        // Verificar quando há mudanças no conteúdo
        const observer = new MutationObserver(checkScroll);
        observer.observe(conversationsList, { childList: true, subtree: true });
        
        // Verificar no redimensionamento
        window.addEventListener('resize', checkScroll);
        
        // Adicionar evento de scroll suave
        conversationsList.addEventListener('scroll', () => {
          // Opcional: lógica adicional quando o usuário faz scroll
        });
      }

      // Inicialização e configuração de event listeners
      function inicializarEventListeners() {
        // Setup do indicador de scroll
        setupScrollIndicator();
        
        // Hover effects para conversa items
        document.querySelectorAll('.conversation-item').forEach(item => {
          // Remover listeners existentes primeiro
          item.removeEventListener('mouseenter', hoverEnterHandler);
          item.removeEventListener('mouseleave', hoverLeaveHandler);
          
          // Adicionar novos listeners
          item.addEventListener('mouseenter', hoverEnterHandler);
          item.addEventListener('mouseleave', hoverLeaveHandler);
          
          // Marcar como inicializado
          item.setAttribute('data-listeners-added', 'true');
        });
      }
      
      // Handlers de hover separados para facilitar remoção
      function hoverEnterHandler() {
        if (!this.classList.contains('selected')) {
          this.style.transform = 'translateX(4px)';
        }
      }
      
      function hoverLeaveHandler() {
        if (!this.classList.contains('selected')) {
          this.style.transform = 'translateX(0)';
        }
      }
      
      // Adicionar CSS slideOut e estilos para indicadores
      const slideOutStyle = document.createElement('style');
      slideOutStyle.textContent = `
        @keyframes slideOut {
          to { transform: translateX(-100%); opacity: 0; height: 0; margin: 0; padding: 0; }
        }
        
        /* Garantir que indicadores removidos desapareçam */
        .unread-indicator.removing,
        .unread-count.removing {
          opacity: 0 !important;
          transform: scale(0) !important;
          transition: all 0.3s ease !important;
        }
        
        /* Estilo para conversa lida */
        .conversation-item[data-nao-lidas="0"] .unread-indicator,
        .conversation-item[data-nao-lidas="0"] .unread-count {
          display: none !important;
        }
        
        /* Debug - mostrar conversas não lidas em vermelho */
        .conversation-item[data-nao-lidas]:not([data-nao-lidas="0"]) {
          border-left: 3px solid var(--color-danger) !important;
        }
        
        .conversation-item[data-nao-lidas="0"] {
          border-left: 3px solid transparent !important;
        }
      `;
      document.head.appendChild(slideOutStyle);
      
      // Inicializar quando a página carregar
      document.addEventListener('DOMContentLoaded', inicializarEventListeners);
      

      

    </script>

    <style>
      /* CSS melhorado para interface de mensagens */
      .filter-tabs {
        background: white;
        border-radius: 12px;
        padding: 0.3rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      }
      
      .filter-btn {
        padding: 0.5rem 1rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        color: var(--color-dark-variant);
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }
      
      .filter-btn.active {
        background: var(--color-danger);
        color: white;
        border: 1px solid var(--color-danger);
        box-shadow: 0 2px 8px rgba(255, 0, 212, 0.3);
      }
      
      .filter-btn:hover:not(.active) {
        background: var(--color-baby-pink);
        color: var(--color-danger);
      }
      
      .badge {
        background: rgba(255,255,255,0.3);
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: bold;
      }
      
      .filter-btn.active .badge {
        background: rgba(255,255,255,0.3);
      }
      
      .filter-btn:not(.active) .badge {
        background: var(--color-danger);
        color: white;
      }
      
      .action-btn {
        padding: 0.6rem;
        border: 1px solid var(--color-light);
        background: white;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: var(--color-dark-variant);
      }
      
      .action-btn:hover {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 0, 212, 0.3);
      }
      
      .conversa-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 0, 212, 0.2);
        border-color: var(--color-danger);
      }
      
      .conversa-item:hover .action-buttons {
        opacity: 1 !important;
      }
      
      .mini-btn {
        padding: 0.4rem;
        border: 1px solid var(--color-light);
        background: white;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--color-dark-variant);
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      
      .mini-btn:hover {
        transform: scale(1.1);
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
      }
      
      .mini-btn.delete:hover {
        background: var(--color-danger);
        border-color: var(--color-danger);
      }
      
      /* Animação removida para simplificar */
      
      /* Melhorar área de chat */
      #mensagens-container::-webkit-scrollbar {
        width: 6px;
      }
      
      #mensagens-container::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
      }
      
      #mensagens-container::-webkit-scrollbar-thumb {
        background: var(--color-primary);
        border-radius: 3px;
      }
      
      .mensagem-item {
        margin-bottom: 1rem;
        animation: slideIn 0.3s ease;
      }
      
      @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: linear-gradient(45deg, var(--color-success), #7dd87d);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 1000;
        animation: slideInRight 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
      }
      
      @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      
      @keyframes fadeOut {
        to { transform: translateX(100%); opacity: 0; }
      }
    </style>

    <script>
      // Atualizar contadores dos filtros
      function atualizarContadoresFiltros() {
        const conversas = document.querySelectorAll('.conversation-item');
        let totalConversas = conversas.length;
        let conversasNaoLidas = 0;
        let conversasAtivas = 0;
        let conversasEscaladas = 0;
        let conversasResolvidas = 0;
        
        conversas.forEach(item => {
          const status = item.getAttribute('data-status');
          const naoLidas = parseInt(item.getAttribute('data-nao-lidas')) || 0;
          
          if (naoLidas > 0) conversasNaoLidas++;
          if (status === 'ativa') conversasAtivas++;
          if (status === 'aguardando_humano') conversasEscaladas++;
          if (status === 'resolvida') conversasResolvidas++;
        });
        
        // Atualizar contadores nos botões
        const btnTodas = document.querySelector('.filter-tab[onclick*="todas"] .count');
        const btnNaoLidas = document.querySelector('.filter-tab[onclick*="nao_lidas"] .count');
        const btnAtivas = document.querySelector('.filter-tab[onclick*="ativa"] .count');
        const btnEscaladas = document.querySelector('.filter-tab[onclick*="aguardando_humano"] .count');
        const btnResolvidas = document.querySelector('.filter-tab[onclick*="resolvida"] .count');
        
        if (btnTodas) btnTodas.textContent = totalConversas;
        if (btnNaoLidas) btnNaoLidas.textContent = conversasNaoLidas;
        if (btnAtivas) btnAtivas.textContent = conversasAtivas;
        if (btnEscaladas) btnEscaladas.textContent = conversasEscaladas;
        if (btnResolvidas) btnResolvidas.textContent = conversasResolvidas;
      }
      
      // Marcar todas como lidas
      async function marcarTodasLidas() {
        if (confirm('Marcar todas as conversas como lidas?')) {
          try {
            const response = await fetch('../sistema.php?api=1&endpoint=admin&action=marcar_todas_lidas', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' }
            });
            
            const result = await response.json();
            
            if (result.success) {
              // Atualizar interface para todas as conversas
              document.querySelectorAll('.conversation-item').forEach(item => {
                item.setAttribute('data-nao-lidas', '0');
                
                const indicator = item.querySelector('.unread-indicator');
                if (indicator) indicator.remove();
                
                const unreadCount = item.querySelector('.unread-count');
                if (unreadCount) unreadCount.remove();
                
                // Feedback visual
                item.style.transition = 'all 0.3s ease';
                item.style.backgroundColor = 'var(--color-success)';
                setTimeout(() => {
                  item.style.backgroundColor = '';
                }, 1000);
              });
              
              mostrarToast('Todas as conversas foram marcadas como lidas!');
              
              // Atualizar contadores
              atualizarContadoresFiltros();
              if (window.atualizarContadorMensagens) {
                setTimeout(window.atualizarContadorMensagens, 500);
              }
            }
          } catch (error) {
            console.error('Erro:', error);
          }
        }
      }
    </script>

    <style>

      
      /* CSS específico para avatar da DAIze */
      .message-bubble.ia .message-avatar {
        background: linear-gradient(135deg, #ff6b9d, #c44569) !important;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        position: relative;
        overflow: hidden;
      }
      
      .message-bubble.ia .message-avatar img {
        transition: all 0.3s ease;
      }
      
      .message-bubble.ia .message-avatar img:hover {
        transform: scale(1.1);
      }
      
      /* Fallback emoji styling */
      .message-bubble.ia .message-avatar:not(:has(img)) {
        font-size: 1.2rem;
        background: linear-gradient(135deg, #ff6b9d, #c44569);
      }
    </style>

    <script src="../../js/contador-auto.js"></script>
  </body>
</html>





