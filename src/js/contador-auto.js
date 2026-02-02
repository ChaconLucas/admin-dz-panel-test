/**
 * Script para atualização automática do contador de mensagens
 */

// Função para atualizar contador de mensagens não lidas
function atualizarContador() {
  fetch("api-contador.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        // Atualizar todos os contadores na página
        const contadores = document.querySelectorAll(".message-count");
        contadores.forEach((contador) => {
          const valorAnterior = parseInt(contador.textContent) || 0;

          contador.textContent = data.nao_lidas;
          contador.setAttribute("data-count", data.nao_lidas);

          // Forçar cor rosa quando há mensagens
          if (data.nao_lidas > 0) {
            contador.style.background = "#ff00d4 !important";
            contador.style.color = "white !important";
            contador.style.display = "inline-block !important";
          } else {
            contador.style.display = "none";
          }

          // Animar mudança apenas se houve alteração
          if (valorAnterior !== data.nao_lidas) {
            contador.style.transform = "scale(1.2)";
            contador.style.transition = "transform 0.2s ease";

            setTimeout(() => {
              contador.style.transform = "scale(1)";
            }, 200);
          }
        });

        // Atualizar título da página se houver mensagens
        if (data.nao_lidas > 0) {
          document.title = `(${data.nao_lidas}) Dashboard - D&Z`;
        } else {
          document.title = "Dashboard - D&Z";
        }
      }
    })
    .catch((error) => {
      console.log("Erro ao atualizar contador:", error);
    });
}

// Inicializar quando o DOM estiver pronto
document.addEventListener("DOMContentLoaded", function () {
  // Atualizar imediatamente
  atualizarContador();

  // Atualizar a cada 5 segundos
  setInterval(atualizarContador, 5000);

  // Atualizar também quando a página ganha foco
  window.addEventListener("focus", atualizarContador);

  // Atualizar quando a aba se torna visível (Page Visibility API)
  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) {
      atualizarContador();
    }
  });
});
