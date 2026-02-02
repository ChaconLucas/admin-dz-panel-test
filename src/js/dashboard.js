const sideMenu = document.querySelector("aside");
const menuBtn = document.querySelector("#menu-btn");
const closeBtn = document.querySelector("#close-btn");
const themeToggler = document.querySelector(".theme-toggler");

//mostrar sidebar
menuBtn.addEventListener("click", () => {
  sideMenu.style.display = "block";
});

//fehcar sidebar
closeBtn.addEventListener("click", () => {
  sideMenu.style.display = "none";
});

//mudar tema
themeToggler.addEventListener("click", () => {
  // Adicionar um pequeno feedback visual
  themeToggler.style.transform = "scale(0.95)";
  setTimeout(() => {
    themeToggler.style.transform = "scale(1)";
  }, 100);

  // Mudança de tema com transição suave
  document.body.classList.toggle("dark-theme-variables");
  const isDark = document.body.classList.contains("dark-theme-variables");

  // Persistir preferência
  localStorage.setItem("darkTheme", isDark ? "true" : "false");

  // Animar os ícones do toggle
  const sunIcon = themeToggler.querySelector("span:nth-child(1)");
  const moonIcon = themeToggler.querySelector("span:nth-child(2)");

  sunIcon.classList.toggle("active");
  moonIcon.classList.toggle("active");

  // Adicionar uma pequena rotação aos ícones
  sunIcon.style.transform = sunIcon.classList.contains("active")
    ? "rotate(0deg)"
    : "rotate(180deg)";
  moonIcon.style.transform = moonIcon.classList.contains("active")
    ? "rotate(0deg)"
    : "rotate(-180deg)";
});

// Restore persisted theme on load
document.addEventListener("DOMContentLoaded", () => {
  const saved = localStorage.getItem("darkTheme");

  if (saved === "true") {
    document.body.classList.add("dark-theme-variables");
    const s = themeToggler.querySelector("span:nth-child(1)");
    const m = themeToggler.querySelector("span:nth-child(2)");
    if (s) s.classList.remove("active");
    if (m) m.classList.add("active");
  } else if (saved === "false") {
    // ensure the default state is light
    document.body.classList.remove("dark-theme-variables");
    const s = themeToggler.querySelector("span:nth-child(1)");
    const m = themeToggler.querySelector("span:nth-child(2)");
    if (s) s.classList.add("active");
    if (m) m.classList.remove("active");
  }
});

/* Sidebar: ensure only one item is marked active (panel) and persist active by pathname */
document.addEventListener("DOMContentLoaded", function () {
  const sidebarLinks = document.querySelectorAll("aside .sidebar a");
  if (!sidebarLinks.length) return;

  // Helper to remove active marks
  const clearActive = () =>
    sidebarLinks.forEach((l) => l.classList.remove("panel", "active"));

  // Determine current path (filename)
  const currentPath = window.location.pathname.split("/").pop() || "index.html";

  // Try to highlight by matching href to current path
  let matched = false;
  sidebarLinks.forEach((link) => {
    const href = link.getAttribute("href")
      ? link.getAttribute("href").split("/").pop()
      : "";
    if (href && href === currentPath) {
      clearActive();
      link.classList.add("panel");
      matched = true;
    }
  });

  // If no match by path, try the saved href from localStorage
  if (!matched) {
    const saved = localStorage.getItem("sidebarActiveHref");
    if (saved) {
      const savedLink = Array.from(sidebarLinks).find(
        (l) => l.getAttribute("href") === saved,
      );
      if (savedLink) {
        clearActive();
        savedLink.classList.add("panel");
      }
    }
  }

  // Attach click handlers to persist selection and update UI immediately
  sidebarLinks.forEach((link) => {
    link.addEventListener("click", function () {
      clearActive();
      this.classList.add("panel");
      if (this.getAttribute("href")) {
        localStorage.setItem("sidebarActiveHref", this.getAttribute("href"));
      }
    });
  });

  // Inicializar contador de mensagens
  console.log("🚀 Iniciando sistema de contador de mensagens...");
  atualizarContadorMensagens();

  // Atualizar contador a cada 30 segundos
  setInterval(atualizarContadorMensagens, 30000);
});

// Função para atualizar o contador de mensagens não lidas
window.atualizarContadorMensagens = async function () {
  try {
    const response = await fetch(
      "../sistema.php?api=1&endpoint=admin&action=get_stats",
    );
    if (response.ok) {
      const stats = await response.json();
      console.log("📊 Stats recebidas:", stats);
      const messageCountElements = document.querySelectorAll(".message-count");
      console.log("🎯 Elementos encontrados:", messageCountElements.length);

      if (messageCountElements.length === 0) {
        console.warn("⚠️ Nenhum elemento .message-count encontrado!");
        return;
      }

      messageCountElements.forEach((element) => {
        const novasNaoLidas = stats.nao_lidas || 0;
        console.log(
          "🔢 Atualizando contador:",
          element.textContent,
          "->",
          novasNaoLidas,
        );

        // Animação de atualização apenas se o número mudou
        if (element.textContent != novasNaoLidas) {
          element.style.transform = "scale(1.2)";
          element.style.background = "var(--color-warning)";

          setTimeout(() => {
            element.textContent = novasNaoLidas;
            element.style.transform = "scale(1)";
            element.style.background =
              novasNaoLidas > 0
                ? "var(--color-danger)"
                : "var(--color-info-light)";
          }, 150);
        } else {
          element.textContent = novasNaoLidas;
          element.style.background =
            novasNaoLidas > 0
              ? "var(--color-danger)"
              : "var(--color-info-light)";
        }

        // Ocultar contador se não há mensagens
        element.style.display = novasNaoLidas > 0 ? "block" : "none";
      });

      console.log("📧 Contador de mensagens atualizado:", stats.nao_lidas);
    } else {
      console.error(
        "❌ Response não OK:",
        response.status,
        response.statusText,
      );
      const errorText = await response.text();
      console.error("❌ Error response:", errorText);
    }
  } catch (error) {
    console.error("❌ Erro ao atualizar contador de mensagens:", error);
  }
};
