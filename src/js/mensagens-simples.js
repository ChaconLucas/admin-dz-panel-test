/**
 * Sistema simples de atualização de mensagens em tempo real
 */

let conversaAtualSelecionada = null;

// Função chamada quando uma conversa é selecionada
window.definirConversaAtiva = function (conversaId) {
  console.log("🔥 CONVERSA ATIVA:", conversaId);
  conversaAtualSelecionada = conversaId;

  // Verificar mensagens imediatamente
  verificarNovasMensagens();
};

// Função principal que verifica novas mensagens
function verificarNovasMensagens() {
  if (!conversaAtualSelecionada) {
    console.log("❌ Nenhuma conversa selecionada");
    return;
  }

  console.log(
    "🔍 VERIFICANDO MENSAGENS DA CONVERSA:",
    conversaAtualSelecionada
  );

  const url = `api-mensagens.php?conversa_id=${conversaAtualSelecionada}`;

  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      console.log("📦 DADOS RECEBIDOS:", data);

      if (data.success && data.mensagens && data.mensagens.length > 0) {
        console.log(`✅ ENCONTRADAS ${data.mensagens.length} MENSAGENS`);

        const container = document.querySelector("#mensagens-container");
        if (!container) {
          console.log("❌ CONTAINER NÃO ENCONTRADO");
          return;
        }

        // Verificar quais mensagens já estão na tela
        const mensagensExistentes = Array.from(
          container.querySelectorAll("[data-message-id]")
        ).map((el) => parseInt(el.getAttribute("data-message-id")));

        console.log("📋 MENSAGENS JÁ NA TELA:", mensagensExistentes);

        let adicionadas = 0;
        data.mensagens.forEach((msg) => {
          if (!mensagensExistentes.includes(msg.id)) {
            console.log("➕ NOVA MENSAGEM:", msg.conteudo);
            adicionarMensagemSimples(msg);
            adicionadas++;
          }
        });

        console.log(`🎉 ${adicionadas} MENSAGENS ADICIONADAS`);

        if (adicionadas > 0) {
          // Scroll para baixo
          container.scrollTop = container.scrollHeight;
        }
      } else {
        console.log("⚠️ NENHUMA MENSAGEM NA RESPOSTA");
      }
    })
    .catch((error) => {
      console.log("💥 ERRO:", error);
    });
}

// Função simples para adicionar mensagem
function adicionarMensagemSimples(mensagem) {
  const container = document.querySelector("#mensagens-container");
  if (!container) return;

  const div = document.createElement("div");
  div.className = `message-bubble ${
    mensagem.remetente === "admin" ? "admin" : "client"
  }`;
  div.setAttribute("data-message-id", mensagem.id);

  const time = new Date(mensagem.timestamp).toLocaleTimeString("pt-BR", {
    hour: "2-digit",
    minute: "2-digit",
  });

  div.innerHTML = `
        <div class="message-avatar">
            ${
              mensagem.remetente === "admin"
                ? '<img src="../../../assets/images/logo.png" alt="Admin">'
                : mensagem.remetente.charAt(0).toUpperCase()
            }
        </div>
        <div class="message-content">
            <p>${mensagem.conteudo}</p>
            <span class="message-time">${time}</span>
        </div>
    `;

  // Destacar nova mensagem temporariamente
  div.style.backgroundColor = "#e3f2fd";
  div.style.border = "2px solid #2196f3";

  container.appendChild(div);

  // Remover destaque após 3 segundos
  setTimeout(() => {
    div.style.backgroundColor = "";
    div.style.border = "";
  }, 3000);

  console.log("✅ MENSAGEM ADICIONADA AO DOM");
}

// Verificar mensagens a cada 2 segundos
console.log("🚀 INICIANDO MONITORAMENTO AUTOMÁTICO");
setInterval(() => {
  if (conversaAtualSelecionada) {
    console.log("⏰ VERIFICAÇÃO AUTOMÁTICA...");
    verificarNovasMensagens();
  }
}, 2000);

// Para debug manual
window.forcarVerificacao = () => verificarNovasMensagens();

console.log("🎯 SISTEMA DE MENSAGENS EM TEMPO REAL CARREGADO");
