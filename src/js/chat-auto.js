/**
 * Script para atualização automática das mensagens na página de chat
 */

let ultimaMensagemId = 0;
let conversaAtiva = null;
let atualizandoMensagens = false;
let atualizandoConversas = false;

// Função global para ser chamada quando uma conversa é selecionada
window.definirConversaAtiva = function (conversaId) {
  console.log("🎯 Conversa ativa definida:", conversaId);
  conversaAtiva = conversaId;
  ultimaMensagemId = 0; // Reset para carregar todas as mensagens
};

// Função global para verificar mensagens (chamada pelo sistema existente)
window.verificarMensagensConversa = function (conversaId) {
  console.log("🔍 Verificando mensagens da conversa:", conversaId);

  const url = `api-mensagens.php?conversa_id=${conversaId}&ultima_id=0`;

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      console.log("📨 Resposta da API:", data);

      if (data.success && data.mensagens.length > 0) {
        console.log(
          `✅ Conversa ${conversaId} tem ${data.mensagens.length} mensagens`,
        );

        // Verificar se a área de mensagens está visível
        const chatArea = document.querySelector("#mensagens-container");
        if (!chatArea) {
          console.log("❌ Área de mensagens não encontrada");
          return;
        }

        // Verificar mensagens que não estão na tela
        const mensagensNaTela = document.querySelectorAll("[data-message-id]");
        const idsNaTela = Array.from(mensagensNaTela).map((m) =>
          parseInt(m.getAttribute("data-message-id")),
        );

        let novasMensagens = 0;
        data.mensagens.forEach((mensagem) => {
          if (!idsNaTela.includes(mensagem.id)) {
            console.log("➕ Adicionando nova mensagem:", mensagem.conteudo);
            adicionarMensagemAoChat(mensagem);
            novasMensagens++;
          }
        });

        if (novasMensagens > 0) {
          console.log(`🎉 ${novasMensagens} mensagens novas adicionadas!`);
          // Fazer scroll para baixo
          setTimeout(() => {
            chatArea.scrollTop = chatArea.scrollHeight;
          }, 100);
        } else {
          console.log("ℹ️ Nenhuma mensagem nova encontrada");
        }
      } else {
        console.log("⚠️ Nenhuma mensagem retornada pela API");
      }
    })
    .catch((error) => {
      console.log("❌ Erro ao verificar mensagens:", error);
    });
};

// Função para detectar conversa ativa automaticamente
function detectarConversaAtiva() {
  // Estratégia 1: Verificar se há uma conversa visível
  const conversaVisivel = document.querySelector("#conversa-ativa");
  if (conversaVisivel && conversaVisivel.style.display !== "none") {
    // Estratégia 2: Procurar por URL parameter primeiro
    const urlParams = new URLSearchParams(window.location.search);
    const conversaIdUrl = urlParams.get("conversa_id");
    if (conversaIdUrl) {
      console.log("Conversa detectada via URL:", conversaIdUrl);
      conversaAtiva = conversaIdUrl;
      return conversaAtiva;
    }

    // Estratégia 3: Procurar conversa com classe ativa
    const conversaAtivaSidebar = document.querySelector(
      ".conversation-item.active, .conversation-item.selected",
    );
    if (conversaAtivaSidebar) {
      const novoId = conversaAtivaSidebar.getAttribute("data-id");
      if (novoId) {
        console.log("Conversa detectada via classe ativa:", novoId);
        conversaAtiva = novoId;
        return conversaAtiva;
      }
    }

    // Estratégia 4: Usar window.conversaAtual se definida globalmente
    if (window.conversaAtual) {
      console.log(
        "Conversa detectada via variável global:",
        window.conversaAtual,
      );
      conversaAtiva = window.conversaAtual;
      return conversaAtiva;
    }
  }

  console.log("Nenhuma conversa ativa detectada");
  return conversaAtiva;
}

// Função para atualizar mensagens - versão mais robusta
function atualizarMensagens() {
  if (atualizandoMensagens) return;

  atualizandoMensagens = true;

  // Detectar conversa ativa
  const conversaId = detectarConversaAtiva() || conversaAtiva;

  // Se não encontrou conversa ativa, tentar todas as conversas visíveis
  if (!conversaId) {
    console.log("Tentando atualizar todas as conversas visíveis");
    atualizarTodasConversasVisiveis();
    atualizandoMensagens = false;
    return;
  }

  console.log("Atualizando mensagens da conversa:", conversaId);

  const url = `api-mensagens.php?conversa_id=${conversaId}&ultima_id=${ultimaMensagemId}`;

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      console.log("Resposta das mensagens:", data);
      if (data.success && data.mensagens.length > 0) {
        const chatArea = document.querySelector("#mensagens-container");
        if (!chatArea) {
          console.log("Área de mensagens não encontrada");
          return;
        }

        const scrollToBottom =
          chatArea.scrollTop >=
          chatArea.scrollHeight - chatArea.clientHeight - 100;

        // Adicionar novas mensagens
        data.mensagens.forEach((mensagem) => {
          console.log("Adicionando mensagem:", mensagem);
          adicionarMensagemAoChat(mensagem);
          ultimaMensagemId = Math.max(ultimaMensagemId, mensagem.id);
        });

        // Fazer scroll para baixo se estava próximo do final
        if (scrollToBottom) {
          setTimeout(() => {
            chatArea.scrollTop = chatArea.scrollHeight;
          }, 100);
        }

        // Marcar mensagens como lidas se não são do admin
        marcarMensagensComoLidas();

        // Atualizar contador da conversa
        atualizarContadorConversa();
      }
    })
    .catch((error) => {
      console.log("Erro ao atualizar mensagens:", error);
    })
    .finally(() => {
      atualizandoMensagens = false;
    });
}

// Função para atualizar todas as conversas visíveis quando não detecta uma específica
function atualizarTodasConversasVisiveis() {
  console.log("Verificando conversas visíveis para atualização");

  // Buscar todas as conversas que têm mensagens não lidas
  const conversasComMensagens = document.querySelectorAll(
    '.conversation-item[data-nao-lidas]:not([data-nao-lidas="0"])',
  );

  conversasComMensagens.forEach((conversa) => {
    const conversaId = conversa.getAttribute("data-id");
    if (conversaId) {
      console.log("Atualizando conversa:", conversaId);
      // Força uma verificação para esta conversa
      forcarAtualizacaoConversa(conversaId);
    }
  });
}

// Função para forçar atualização de uma conversa específica
function forcarAtualizacaoConversa(conversaId) {
  const url = `api-mensagens.php?conversa_id=${conversaId}&ultima_id=0`;

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.mensagens.length > 0) {
        console.log(
          `Conversa ${conversaId} tem ${data.mensagens.length} mensagens`,
        );

        // Se esta conversa está ativa no momento, adicionar as mensagens
        const chatArea = document.querySelector("#mensagens-container");
        const conversaVisivel = document.querySelector("#conversa-ativa");

        if (
          chatArea &&
          conversaVisivel &&
          conversaVisivel.style.display !== "none"
        ) {
          // Verificar se é a conversa atual baseada no contexto
          const mensagemMaisRecente = data.mensagens[data.mensagens.length - 1];
          if (
            mensagemMaisRecente &&
            !document.querySelector(
              `[data-message-id="${mensagemMaisRecente.id}"]`,
            )
          ) {
            console.log(
              "Adicionando mensagem da conversa forçada:",
              mensagemMaisRecente.conteudo,
            );
            adicionarMensagemAoChat(mensagemMaisRecente);
          }
        }
      }
    })
    .catch((error) => {
      console.log("Erro ao forçar atualização da conversa:", error);
    });
}

// Função para atualizar lista de conversas
function atualizarConversas() {
  if (atualizandoConversas) return;

  atualizandoConversas = true;

  console.log("Buscando conversas atualizadas...");
  fetch("api-conversas.php")
    .then((response) => response.json())
    .then((data) => {
      console.log("Dados recebidos:", data);
      if (data.success) {
        // Atualizar contadores nos filtros
        atualizarContadoresFiltros(data.stats);

        // Atualizar indicadores visuais nas conversas
        atualizarIndicadoresConversas(data.conversas);
      }
    })
    .catch((error) => {
      console.log("Erro ao atualizar conversas:", error);
    })
    .finally(() => {
      atualizandoConversas = false;
    });
}

// Função para atualizar contadores dos filtros
function atualizarContadoresFiltros(stats) {
  const contadores = {
    todas: stats.total,
    nao_lidas: stats.nao_lidas,
    ativa: stats.ativas,
    aguardando_humano: stats.aguardando_humano,
    resolvida: stats.resolvidas,
  };

  Object.keys(contadores).forEach((filtro) => {
    const elemento = document.querySelector(`[onclick*="'${filtro}'"] .count`);
    if (elemento) {
      elemento.textContent = contadores[filtro];
    }
  });
}

// Função para atualizar indicadores visuais das conversas
function atualizarIndicadoresConversas(conversas) {
  conversas.forEach((conversa) => {
    const item = document.querySelector(`[data-id="${conversa.id}"]`);
    if (item) {
      // Atualizar atributos
      item.setAttribute("data-nao-lidas", conversa.nao_lidas);
      item.setAttribute("data-status", conversa.status);

      // Atualizar indicador de não lidas
      const indicator = item.querySelector(".unread-indicator");
      if (conversa.nao_lidas > 0 && !indicator) {
        // Adicionar indicador se não existe
        const avatar = item.querySelector(".conversation-avatar");
        if (avatar) {
          const newIndicator = document.createElement("div");
          newIndicator.className = "unread-indicator";
          avatar.appendChild(newIndicator);

          // Animar aparição
          newIndicator.style.transform = "scale(0)";
          setTimeout(() => {
            newIndicator.style.transform = "scale(1)";
            newIndicator.style.transition = "transform 0.2s ease";
          }, 10);
        }
      } else if (conversa.nao_lidas === 0 && indicator) {
        // Remover indicador se não há mensagens não lidas
        indicator.style.transform = "scale(0)";
        setTimeout(() => indicator.remove(), 200);
      }

      // Atualizar preview da última mensagem se fornecido
      if (conversa.ultima_mensagem) {
        const preview = item.querySelector(".conversation-preview");
        if (preview) {
          preview.textContent =
            conversa.ultima_mensagem.substring(0, 40) + "...";
        }
      }

      // Atualizar timestamp
      if (conversa.updated_at) {
        const timeElement = item.querySelector(".conversation-time");
        if (timeElement) {
          const time = new Date(conversa.updated_at);
          timeElement.textContent = time.toLocaleTimeString("pt-BR", {
            hour: "2-digit",
            minute: "2-digit",
          });
        }
      }
    }
  });
}

// Função para adicionar mensagem ao chat
function adicionarMensagemAoChat(mensagem) {
  const chatArea = document.querySelector("#mensagens-container");
  if (!chatArea) {
    console.log("Área de mensagens não encontrada");
    return;
  }

  // Verificar se a mensagem já existe
  if (document.querySelector(`[data-message-id="${mensagem.id}"]`)) {
    console.log("Mensagem já existe:", mensagem.id);
    return;
  }

  console.log("Adicionando nova mensagem ao chat:", mensagem.conteudo);

  const messageDiv = document.createElement("div");
  messageDiv.className = `message-bubble ${
    mensagem.remetente === "admin"
      ? "admin"
      : mensagem.remetente === "usuario"
        ? "client"
        : "ia"
  }`;
  messageDiv.setAttribute("data-message-id", mensagem.id);

  const timestamp = new Date(mensagem.timestamp).toLocaleTimeString("pt-BR", {
    hour: "2-digit",
    minute: "2-digit",
  });

  const avatar =
    mensagem.remetente === "admin"
      ? '<img src="../../../assets/images/logo.png" alt="Admin">'
      : mensagem.remetente.charAt(0).toUpperCase();

  messageDiv.innerHTML = `
    <div class="message-avatar">
      ${avatar}
    </div>
    <div class="message-content">
      <p>${mensagem.conteudo}</p>
      <span class="message-time">${timestamp}</span>
    </div>
  `;

  // Animar entrada da nova mensagem
  messageDiv.style.opacity = "0";
  messageDiv.style.transform = "translateY(20px)";
  messageDiv.style.background = "#f0f0f0";

  chatArea.appendChild(messageDiv);

  // Animar entrada
  setTimeout(() => {
    messageDiv.style.transition = "all 0.3s ease";
    messageDiv.style.opacity = "1";
    messageDiv.style.transform = "translateY(0)";

    // Remover destaque após animação
    setTimeout(() => {
      messageDiv.style.background = "";
    }, 2000);
  }, 10);

  console.log("Mensagem adicionada com sucesso!");
}

// Função para marcar mensagens como lidas
function marcarMensagensComoLidas() {
  if (!conversaAtiva) return;

  fetch("../sistema.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: `action=marcar_como_lidas&conversa_id=${conversaAtiva}`,
  }).catch((error) => {
    console.log("Erro ao marcar como lidas:", error);
  });
}

// Função para atualizar contador da conversa específica
function atualizarContadorConversa() {
  if (!conversaAtiva) return;

  const conversaItem = document.querySelector(`[data-id="${conversaAtiva}"]`);
  if (conversaItem) {
    const indicator = conversaItem.querySelector(".unread-indicator");
    if (indicator) {
      indicator.remove(); // Remove o indicador de não lidas
    }
    conversaItem.setAttribute("data-nao-lidas", "0");
  }
}

// Função para inicializar uma conversa
function inicializarConversa(conversaId) {
  conversaAtiva = conversaId;

  // Encontrar a última mensagem para saber de onde continuar
  const mensagens = document.querySelectorAll("[data-message-id]");
  ultimaMensagemId = 0;
  mensagens.forEach((msg) => {
    const id = parseInt(msg.getAttribute("data-message-id"));
    if (id > ultimaMensagemId) {
      ultimaMensagemId = id;
    }
  });

  // Começar a atualizar mensagens
  atualizarMensagens();
}

// Inicializar quando o DOM estiver pronto
document.addEventListener("DOMContentLoaded", function () {
  // Verificar se estamos na página de mensagens
  if (window.location.pathname.includes("menssage.php")) {
    // Detectar clique em conversas para inicializar
    document.addEventListener("click", function (e) {
      const conversaItem = e.target.closest(".conversation-item[data-id]");
      if (conversaItem) {
        const conversaId = conversaItem.getAttribute("data-id");
        inicializarConversa(conversaId);
      }
    });

    // Se já há uma conversa ativa (detectar pela URL ou elemento ativo)
    const conversaAtual = document.querySelector(
      ".conversa-ativa, .active-conversation",
    );
    if (conversaAtual) {
      const conversaId =
        conversaAtual.getAttribute("data-conversa-id") ||
        new URLSearchParams(window.location.search).get("conversa_id");
      if (conversaId) {
        inicializarConversa(conversaId);
      }
    }

    // Atualizar conversas imediatamente
    atualizarConversas();

    // Tentar detectar conversa ativa imediatamente
    setTimeout(() => {
      detectarConversaAtiva();
      if (conversaAtiva) {
        atualizarMensagens();
      }
    }, 1000);

    // Função simples que sempre tenta atualizar
    function verificarMensagensNovas() {
      console.log("Verificando mensagens novas...");

      // Se há uma área de mensagens visível, tentar atualizar
      const chatArea = document.querySelector("#mensagens-container");
      const conversaVisivel = document.querySelector("#conversa-ativa");

      if (
        chatArea &&
        conversaVisivel &&
        conversaVisivel.style.display !== "none"
      ) {
        // Tentar diferentes estratégias para encontrar a conversa
        let conversaId = null;

        // 1. URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        conversaId = urlParams.get("conversa_id");

        // 2. Variável global
        if (!conversaId && window.conversaAtual) {
          conversaId = window.conversaAtual;
        }

        // 3. Conversa ativa na sidebar
        if (!conversaId) {
          const ativa = document.querySelector(
            ".conversation-item.active[data-id], .conversation-item.selected[data-id]",
          );
          if (ativa) {
            conversaId = ativa.getAttribute("data-id");
          }
        }

        // 4. Primeira conversa com mensagens não lidas
        if (!conversaId) {
          const comMensagens = document.querySelector(
            '.conversation-item[data-nao-lidas]:not([data-nao-lidas="0"])',
          );
          if (comMensagens) {
            conversaId = comMensagens.getAttribute("data-id");
          }
        }

        if (conversaId) {
          console.log("Verificando mensagens para conversa:", conversaId);
          verificarMensagensConversa(conversaId);
        } else {
          console.log("Nenhuma conversa encontrada para verificar");
        }
      }
    }

    // Função para verificar mensagens de uma conversa específica
    function verificarMensagensConversa(conversaId) {
      const url = `api-mensagens.php?conversa_id=${conversaId}&ultima_id=0`;

      fetch(url)
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.mensagens.length > 0) {
            console.log(
              `Conversa ${conversaId} tem ${data.mensagens.length} mensagens`,
            );

            // Verificar se há mensagens novas que não estão na tela
            const mensagensNaTela =
              document.querySelectorAll("[data-message-id]");
            const idsNaTela = Array.from(mensagensNaTela).map((m) =>
              parseInt(m.getAttribute("data-message-id")),
            );

            data.mensagens.forEach((mensagem) => {
              if (!idsNaTela.includes(mensagem.id)) {
                console.log("Nova mensagem encontrada:", mensagem.conteudo);
                adicionarMensagemAoChat(mensagem);
              }
            });
          }
        })
        .catch((error) => console.log("Erro ao verificar mensagens:", error));
    }

    // Atualizar mensagens a cada 2 segundos usando a nova função
    setInterval(verificarMensagensNovas, 2000);

    // Atualizar conversas a cada 4 segundos
    setInterval(atualizarConversas, 4000);

    // Atualizar quando a janela ganha foco
    window.addEventListener("focus", function () {
      detectarConversaAtiva();
      atualizarMensagens();
      atualizarConversas();
    });

    // Observar mudanças no DOM para detectar quando uma conversa é aberta
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (
          mutation.type === "attributes" &&
          mutation.attributeName === "style"
        ) {
          const conversaVisivel = document.querySelector("#conversa-ativa");
          if (conversaVisivel && conversaVisivel.style.display !== "none") {
            detectarConversaAtiva();
            atualizarMensagens();
          }
        }
      });
    });

    const conversaContainer = document.querySelector("#conversa-ativa");
    if (conversaContainer) {
      observer.observe(conversaContainer, {
        attributes: true,
        attributeFilter: ["style"],
      });
    }
  }
});

// Sistema simples de monitoramento contínuo
setInterval(() => {
  if (conversaAtiva) {
    console.log("⏰ Verificação automática - Conversa ativa:", conversaAtiva);
    if (window.verificarMensagensConversa) {
      window.verificarMensagensConversa(conversaAtiva);
    }
  }
}, 3000); // A cada 3 segundos
