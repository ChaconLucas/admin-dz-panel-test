/**
 * D&Z Chat System - JavaScript Consolidado
 * Todas as funcionalidades em um arquivo organizado
 */

// =================== CONFIGURAÇÕES GLOBAIS ===================
const CONFIG = {
  API_BASE: "../src/php/sistema.php",
  ENDPOINTS: {
    CLIENT: "?api=1&endpoint=client",
    ADMIN: "?api=1&endpoint=admin",
    AUTH: "?api=1&endpoint=auth",
  },
  POLL_INTERVAL: 3000, // 3 segundos
  MESSAGE_TIMEOUT: 30000, // 30 segundos
};

// =================== UTILITÁRIOS ===================
class Utils {
  static async fetchAPI(url, options = {}) {
    try {
      const response = await fetch(url, {
        headers: {
          "Content-Type": "application/json",
          ...options.headers,
        },
        ...options,
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      return await response.json();
    } catch (error) {
      console.error("API Error:", error);
      throw error;
    }
  }

  static showAlert(elementId, message, type = "danger") {
    const alertElement = document.getElementById(elementId);
    if (alertElement) {
      alertElement.textContent = message;
      alertElement.className = `alert alert-${type}`;
      alertElement.style.display = "block";

      setTimeout(() => {
        alertElement.style.display = "none";
      }, 5000);
    }
  }

  static isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }

  static formatTime(timestamp) {
    return new Date(timestamp).toLocaleTimeString("pt-BR", {
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  static formatDate(timestamp) {
    return new Date(timestamp).toLocaleDateString("pt-BR", {
      day: "2-digit",
      month: "2-digit",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  static scrollToBottom(element) {
    setTimeout(() => {
      element.scrollTop = element.scrollHeight;
    }, 100);
  }

  static adjustTextareaHeight(textarea) {
    textarea.style.height = "auto";
    textarea.style.height = Math.min(textarea.scrollHeight, 100) + "px";
  }
}

// =================== CLIENTE CHAT ===================
class ChatClient {
  constructor() {
    this.conversaId = null;
    this.initializeElements();
    this.attachEventListeners();
  }

  initializeElements() {
    this.formSection = document.getElementById("form-section");
    this.chatContainer = document.getElementById("chat-container");
    this.chatMessages = document.getElementById("chat-messages");
    this.chatInput = document.getElementById("chat-input");
    this.sendBtn = document.getElementById("send-btn");
    this.startBtn = document.getElementById("start-btn");
    this.loading = document.getElementById("loading");
    this.typingIndicator = document.getElementById("typing-indicator");
    this.chatForm = document.getElementById("chat-form");
  }

  attachEventListeners() {
    if (this.chatForm) {
      this.chatForm.addEventListener("submit", (e) => this.handleFormSubmit(e));
    }

    if (this.sendBtn) {
      this.sendBtn.addEventListener("click", () => this.sendMessage());
    }

    if (this.chatInput) {
      this.chatInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });
      this.chatInput.addEventListener("input", () => {
        Utils.adjustTextareaHeight(this.chatInput);
      });
    }
  }

  async handleFormSubmit(e) {
    e.preventDefault();

    const formData = new FormData(this.chatForm);
    const data = {
      nome: formData.get("nome").trim(),
      email: formData.get("email").trim(),
      mensagem: formData.get("mensagem").trim(),
    };

    if (!this.validateForm(data)) return;

    this.setLoadingState(true);

    try {
      const result = await Utils.fetchAPI(
        CONFIG.API_BASE +
          CONFIG.ENDPOINTS.CLIENT +
          "&action=start_conversation",
        {
          method: "POST",
          body: JSON.stringify(data),
        }
      );

      if (result.success) {
        this.conversaId = result.conversa_id;
        this.showChat();
        this.addMessage("user", data.mensagem, data.nome);
        this.addMessage("assistant", result.resposta_ia, "Atendente D&Z");
        Utils.scrollToBottom(this.chatMessages);
      } else {
        throw new Error(result.error || "Erro desconhecido");
      }
    } catch (error) {
      Utils.showAlert(
        "error-message",
        "Erro ao conectar. Tente novamente em alguns momentos."
      );
    } finally {
      this.setLoadingState(false);
    }
  }

  validateForm(data) {
    if (!data.nome || !data.email || !data.mensagem) {
      Utils.showAlert("error-message", "Por favor, preencha todos os campos.");
      return false;
    }

    if (!Utils.isValidEmail(data.email)) {
      Utils.showAlert("error-message", "Por favor, insira um e-mail válido.");
      return false;
    }

    return true;
  }

  async sendMessage() {
    const message = this.chatInput.value.trim();

    if (!message || !this.conversaId) return;

    this.addMessage("user", message, "Você");
    this.chatInput.value = "";
    Utils.adjustTextareaHeight(this.chatInput);
    Utils.scrollToBottom(this.chatMessages);

    this.setInputState(false);
    this.showTypingIndicator();

    try {
      const result = await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.CLIENT + "&action=send_message",
        {
          method: "POST",
          body: JSON.stringify({
            conversa_id: this.conversaId,
            mensagem: message,
          }),
        }
      );

      this.hideTypingIndicator();

      if (result.success) {
        this.addMessage("assistant", result.resposta, "Atendente D&Z");
      } else {
        throw new Error(result.error || "Erro ao enviar mensagem");
      }
    } catch (error) {
      this.hideTypingIndicator();
      this.addMessage(
        "assistant",
        "Desculpe, estamos com dificuldades técnicas. Tente novamente em alguns instantes.",
        "Sistema"
      );
    } finally {
      this.setInputState(true);
      this.chatInput.focus();
      Utils.scrollToBottom(this.chatMessages);
    }
  }

  showChat() {
    this.formSection.style.display = "none";
    this.chatContainer.style.display = "block";
    this.chatInput.focus();
  }

  addMessage(type, content, sender) {
    const messageDiv = document.createElement("div");
    messageDiv.className = `message ${type}`;

    const avatar = document.createElement("div");
    avatar.className = "message-avatar";
    avatar.textContent = sender.charAt(0).toUpperCase();

    const bubble = document.createElement("div");
    bubble.className = "message-bubble";
    bubble.textContent = content;

    messageDiv.appendChild(avatar);
    messageDiv.appendChild(bubble);

    this.chatMessages.appendChild(messageDiv);
    Utils.scrollToBottom(this.chatMessages);
  }

  setLoadingState(loading) {
    if (this.startBtn) {
      this.startBtn.disabled = loading;
      this.startBtn.innerHTML = loading
        ? '<div class="typing-dots"><span></span><span></span><span></span></div> Conectando...'
        : '<i class="material-icons">chat</i> Iniciar Conversa';
    }

    if (this.loading) {
      this.loading.style.display = loading ? "block" : "none";
    }
  }

  setInputState(enabled) {
    if (this.chatInput) this.chatInput.disabled = !enabled;
    if (this.sendBtn) this.sendBtn.disabled = !enabled;
  }

  showTypingIndicator() {
    if (this.typingIndicator) {
      this.typingIndicator.style.display = "block";
      Utils.scrollToBottom(this.chatMessages);
    }
  }

  hideTypingIndicator() {
    if (this.typingIndicator) {
      this.typingIndicator.style.display = "none";
    }
  }
}

// =================== ADMIN DASHBOARD ===================
class ChatAdmin {
  constructor() {
    this.conversaAtiva = null;
    this.pollInterval = null;
    this.initializeElements();
    this.attachEventListeners();
    this.startPolling();
    this.carregarEstatisticas();
  }

  initializeElements() {
    this.conversasList = document.getElementById("conversas-list");
    this.mensagensContainer = document.getElementById("mensagens-container");
    this.adminInput = document.getElementById("admin-input");
    this.sendAdminBtn = document.getElementById("send-admin-btn");
    this.escalarBtn = document.getElementById("escalar-btn");
    this.resolverBtn = document.getElementById("resolver-btn");
    this.statsContainer = document.getElementById("stats-container");
  }

  attachEventListeners() {
    if (this.sendAdminBtn) {
      this.sendAdminBtn.addEventListener("click", () =>
        this.enviarMensagemAdmin()
      );
    }

    if (this.adminInput) {
      this.adminInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          this.enviarMensagemAdmin();
        }
      });
    }

    if (this.escalarBtn) {
      this.escalarBtn.addEventListener("click", () => this.escalarParaHumano());
    }

    if (this.resolverBtn) {
      this.resolverBtn.addEventListener("click", () => this.resolverConversa());
    }
  }

  async carregarConversas() {
    try {
      const conversas = await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.ADMIN + "&action=get_conversations"
      );

      this.renderConversas(conversas);
    } catch (error) {
      console.error("Erro ao carregar conversas:", error);
    }
  }

  renderConversas(conversas) {
    if (!this.conversasList) return;

    this.conversasList.innerHTML = "";

    conversas.forEach((conversa) => {
      const div = document.createElement("div");
      div.className = `conversation-item ${
        conversa.id == this.conversaAtiva ? "active" : ""
      }`;
      div.onclick = () => this.selecionarConversa(conversa.id);

      div.innerHTML = `
                <div class="conversation-header">
                    <span class="conversation-name">${
                      conversa.usuario_nome
                    }</span>
                    <span class="conversation-time">${Utils.formatTime(
                      conversa.created_at
                    )}</span>
                    ${
                      conversa.nao_lidas > 0
                        ? `<span class="unread-badge">${conversa.nao_lidas}</span>`
                        : ""
                    }
                </div>
                <div class="conversation-preview">
                    ${conversa.ultima_mensagem || "Sem mensagens"}
                </div>
                <small>Status: ${this.getStatusLabel(conversa.status)}</small>
            `;

      this.conversasList.appendChild(div);
    });
  }

  getStatusLabel(status) {
    const labels = {
      ativa: "🟢 Ativa",
      aguardando_humano: "🟡 Aguardando",
      resolvida: "✅ Resolvida",
    };
    return labels[status] || status;
  }

  async selecionarConversa(conversaId) {
    this.conversaAtiva = conversaId;
    this.carregarMensagens(conversaId);
    this.carregarConversas(); // Atualizar lista para remover badge de não lidas
  }

  async carregarMensagens(conversaId) {
    try {
      const mensagens = await Utils.fetchAPI(
        CONFIG.API_BASE +
          CONFIG.ENDPOINTS.ADMIN +
          `&action=get_messages&conversa_id=${conversaId}`
      );

      this.renderMensagens(mensagens);
    } catch (error) {
      console.error("Erro ao carregar mensagens:", error);
    }
  }

  renderMensagens(mensagens) {
    if (!this.mensagensContainer) return;

    this.mensagensContainer.innerHTML = "";

    mensagens.forEach((msg) => {
      const div = document.createElement("div");
      div.className = `message ${
        msg.remetente === "admin" ? "admin" : msg.remetente
      }`;

      const time = Utils.formatTime(msg.timestamp);
      const sender = this.getSenderLabel(msg.remetente);

      div.innerHTML = `
                <div class="message-header">
                    <strong>${sender}</strong>
                    <small>${time}</small>
                </div>
                <div class="message-content">${msg.conteudo}</div>
            `;

      this.mensagensContainer.appendChild(div);
    });

    Utils.scrollToBottom(this.mensagensContainer);
  }

  getSenderLabel(remetente) {
    const labels = {
      usuario: "👤 Cliente",
      ia: "🤖 IA",
      admin: "👨‍💼 Admin",
      sistema: "⚙️ Sistema",
    };
    return labels[remetente] || remetente;
  }

  async enviarMensagemAdmin() {
    if (!this.conversaAtiva || !this.adminInput) return;

    const mensagem = this.adminInput.value.trim();
    if (!mensagem) return;

    try {
      const result = await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.ADMIN + "&action=send_admin_message",
        {
          method: "POST",
          body: JSON.stringify({
            conversa_id: this.conversaAtiva,
            mensagem: mensagem,
          }),
        }
      );

      if (result.success) {
        this.adminInput.value = "";
        this.carregarMensagens(this.conversaAtiva);
      }
    } catch (error) {
      console.error("Erro ao enviar mensagem:", error);
    }
  }

  async escalarParaHumano() {
    if (!this.conversaAtiva) return;

    if (confirm("Deseja escalar esta conversa para atendimento humano?")) {
      try {
        const result = await Utils.fetchAPI(
          CONFIG.API_BASE + CONFIG.ENDPOINTS.ADMIN + "&action=escalar_humano",
          {
            method: "POST",
            body: JSON.stringify({
              conversa_id: this.conversaAtiva,
            }),
          }
        );

        if (result.success) {
          this.carregarMensagens(this.conversaAtiva);
          this.carregarConversas();
        }
      } catch (error) {
        console.error("Erro ao escalar conversa:", error);
      }
    }
  }

  async resolverConversa() {
    if (!this.conversaAtiva) return;

    if (confirm("Deseja marcar esta conversa como resolvida?")) {
      try {
        const result = await Utils.fetchAPI(
          CONFIG.API_BASE +
            CONFIG.ENDPOINTS.ADMIN +
            "&action=resolver_conversa",
          {
            method: "POST",
            body: JSON.stringify({
              conversa_id: this.conversaAtiva,
            }),
          }
        );

        if (result.success) {
          this.carregarMensagens(this.conversaAtiva);
          this.carregarConversas();
        }
      } catch (error) {
        console.error("Erro ao resolver conversa:", error);
      }
    }
  }

  async carregarEstatisticas() {
    try {
      const stats = await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.ADMIN + "&action=get_stats"
      );

      this.renderEstatisticas(stats);
    } catch (error) {
      console.error("Erro ao carregar estatísticas:", error);
    }
  }

  renderEstatisticas(stats) {
    if (!this.statsContainer) return;

    this.statsContainer.innerHTML = `
            <div class="stat-card">
                <h3>${stats.total_conversas || 0}</h3>
                <p>Total Conversas</p>
            </div>
            <div class="stat-card">
                <h3>${stats.conversas_ativas || 0}</h3>
                <p>Conversas Ativas</p>
            </div>
            <div class="stat-card">
                <h3>${stats.mensagens_hoje || 0}</h3>
                <p>Mensagens Hoje</p>
            </div>
            <div class="stat-card">
                <h3>${stats.nao_lidas || 0}</h3>
                <p>Não Lidas</p>
            </div>
        `;
  }

  startPolling() {
    this.pollInterval = setInterval(() => {
      this.carregarConversas();
      this.carregarEstatisticas();

      if (this.conversaAtiva) {
        this.carregarMensagens(this.conversaAtiva);
      }
    }, CONFIG.POLL_INTERVAL);
  }

  stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
  }
}

// =================== AUTH MANAGER ===================
class AuthManager {
  static async login(email, senha) {
    try {
      const result = await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.AUTH + "&action=login",
        {
          method: "POST",
          body: JSON.stringify({ email, senha }),
        }
      );

      return result;
    } catch (error) {
      return { success: false, error: "Erro de conexão" };
    }
  }

  static async logout() {
    try {
      await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.AUTH + "&action=logout",
        { method: "POST" }
      );

      window.location.href = "login.html";
    } catch (error) {
      console.error("Erro ao fazer logout:", error);
    }
  }

  static async verify() {
    try {
      const result = await Utils.fetchAPI(
        CONFIG.API_BASE + CONFIG.ENDPOINTS.AUTH + "&action=verify"
      );

      return result.logado;
    } catch (error) {
      return false;
    }
  }
}

// =================== INICIALIZAÇÃO AUTOMÁTICA ===================
document.addEventListener("DOMContentLoaded", () => {
  // Detectar tipo de página e inicializar classe apropriada
  if (document.getElementById("chat-form")) {
    // Página cliente
    new ChatClient();
  } else if (document.getElementById("conversas-list")) {
    // Página admin
    new ChatAdmin();
  }

  // Inicializar tooltips e outros componentes globais se necessário
  initializeGlobalComponents();
});

function initializeGlobalComponents() {
  // Auto-ajustar textareas
  document.querySelectorAll("textarea").forEach((textarea) => {
    textarea.addEventListener("input", () => {
      Utils.adjustTextareaHeight(textarea);
    });
  });

  // Botão de logout se existir
  const logoutBtn = document.getElementById("logout-btn");
  if (logoutBtn) {
    logoutBtn.addEventListener("click", (e) => {
      e.preventDefault();
      AuthManager.logout();
    });
  }
}
