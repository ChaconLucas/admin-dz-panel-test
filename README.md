# 🚀 D&Z Admin Panel - Sistema Completo de Administração e Chat com IA

**Sistema profissional de painel administrativo integrado com chat inteligente usando Groq API, interface moderna com tema rosa/pink da marca e funcionalidades avançadas.**

## ✨ Funcionalidades Principais

### 🔐 Sistema de Autenticação Seguro

- ✅ Login/logout com hash de senhas bcrypt
- ✅ Sessões protegidas e validação de acesso
- ✅ Redirecionamento automático para não autenticados
- ✅ Gerenciamento de usuários admin completo

### 💬 Chat com IA Avançado

- ✅ **Interface moderna** com design rosa/pink da marca
- ✅ **Sistema de filtros:** All, Unread, Active, Escalated, Resolved
- ✅ **Contador de mensagens em tempo real** (PHP + JavaScript)
- ✅ **Groq API integrada** (llama-3.3-70b-versatile)
- ✅ **Ações rápidas:** marcar como não lido, deletar conversas
- ✅ **Status visual** para mensagens lidas/não lidas
- ✅ **Escalação para atendimento humano**
- ✅ **Histórico completo** de conversas

### 📊 Dashboard Administrativo

- ✅ **Painel responsivo** com sidebar dinâmica
- ✅ **Tema dark/light** com transições suaves
- ✅ **Navegação intuitiva** entre módulos
- ✅ **Cards informativos** com estatísticas
- ✅ **Interface mobile-friendly**

### 👥 Gerenciamento CRUD Completo

- ✅ **Usuários:** criar, editar, excluir com validações
- ✅ **Produtos:** gestão completa de catálogo
- ✅ **Clientes:** cadastro e histórico
- ✅ **Pedidos:** controle de vendas
- ✅ **Analytics:** relatórios e métricas

### 🎨 Design Moderno

- ✅ **Paleta rosa/pink** da marca (#ff00d4, #ff6b9d, #ffccf9)
- ✅ **Google Material Symbols** para ícones
- ✅ **Animações CSS** e transições fluidas
- ✅ **Layout responsivo** para todos dispositivos
- ✅ **Compatibilidade** com temas dark/light

## 🛠️ Tecnologias Utilizadas

- **Backend:** PHP 8.0+ com PDO e prepared statements
- **Database:** MySQL/MariaDB com estrutura otimizada
- **Frontend:** HTML5, CSS3, JavaScript Vanilla
- **API IA:** Groq API (llama-3.3-70b-versatile)
- **Icons:** Google Material Symbols Sharp
- **Ambiente:** XAMPP (Apache + MySQL + PHP)

## 📁 Estrutura do Projeto Organizada

```
admin-teste/
├── src/                          # 📁 CÓDIGO FONTE POR LINGUAGEM
│   ├── php/
│   │   ├── sistema.php          # 🔥 Backend consolidado completo
│   │   └── dashboard/           # Páginas do painel admin
│   │       ├── menssage.php     # Interface moderna de chat
│   │       ├── index.php        # Dashboard principal
│   │       ├── products.php     # Gestão produtos
│   │       ├── customers.php    # Gestão clientes
│   │       ├── orders.php       # Gestão pedidos
│   │       └── settings.php     # Configurações
│   │
│   ├── css/
│   │   ├── dashboard.css        # Estilos do painel
│   │   ├── modern-chat.css      # 🎨 Estilos modernos do chat
│   │   └── style-legacy.css     # Estilos base
│   │
│   ├── js/
│   │   ├── dashboard.js         # 🚀 JavaScript consolidado
│   │   └── sistema.js           # Funcionalidades auxiliares
│   │
│   └── html/
│       └── chat-cliente.html    # Interface cliente
│
├── config/
│   └── config.php              # ⚙️ Configurações centralizadas
│
├── public/
│   ├── index.html              # Página inicial
│   └── admin.html              # Dashboard público
│
├── Login_v3/                   # Sistema de login estilizado
├── PHP/                        # Scripts legados (compatibilidade)
├── .env.example               # Template de configurações
├── .gitignore                 # Arquivos ignorados pelo git
└── README.md                  # Esta documentação
```

## ⚙️ Configuração e Instalação

### 1. **Pré-requisitos**

- XAMPP com PHP 8.0+ e MySQL
- Conta na Groq API (gratuita)
- Navegador moderno com suporte a ES6+

### 2. **Configuração do Banco**

```sql
-- Criar banco de dados
CREATE DATABASE teste_dz;

-- Tabelas principais
CREATE TABLE conversas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_nome VARCHAR(255) NOT NULL,
    usuario_email VARCHAR(255) NOT NULL,
    status ENUM('ativa', 'resolvida', 'escalada') DEFAULT 'ativa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE mensagens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    conversa_id INT NOT NULL,
    remetente ENUM('cliente', 'admin', 'ia') NOT NULL,
    conteudo TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lida BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (conversa_id) REFERENCES conversas(id)
);

CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    data_nascimento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3. **Configuração de Ambiente**

```bash
# Copiar arquivo de exemplo
cp .env.example .env

# Editar com suas configurações
GROQ_API_KEY=sua_chave_groq_aqui
DB_HOST=localhost
DB_NAME=teste_dz
DB_USER=root
DB_PASS=
DEBUG_MODE=true
```

### 4. **Acesso ao Sistema**

```
# Dashboard Principal
http://localhost/admin-teste/src/php/dashboard/

# Chat Admin (Interface Moderna)
http://localhost/admin-teste/src/php/dashboard/menssage.php

# Chat Cliente
http://localhost/admin-teste/src/html/chat-cliente.html

# Login Admin
http://localhost/admin-teste/Login_v3/login.html
```

## 🎯 API Endpoints Disponíveis

### **Chat Cliente**

```javascript
// Iniciar nova conversa
POST sistema.php?api=1&endpoint=client&action=start_conversation
{
  "nome": "Cliente",
  "email": "cliente@email.com",
  "mensagem": "Preciso de ajuda"
}

// Enviar mensagem
POST sistema.php?api=1&endpoint=client&action=send_message
{
  "conversa_id": 123,
  "mensagem": "Nova mensagem"
}
```

### **Chat Admin**

```javascript
// Listar conversas com filtros
GET sistema.php?api=1&endpoint=admin&action=get_conversations&filter=unread

// Obter mensagens de conversa
GET sistema.php?api=1&endpoint=admin&action=get_messages&conversa_id=123

// Enviar resposta admin
POST sistema.php?api=1&endpoint=admin&action=send_admin_message
{
  "conversa_id": 123,
  "mensagem": "Resposta do administrador"
}

// Marcar como não lida
POST sistema.php?api=1&endpoint=admin&action=marcarComoNaoLida
{
  "conversa_id": 123
}

// Deletar conversa
POST sistema.php?api=1&endpoint=admin&action=deletarConversa
{
  "conversa_id": 123
}

// Escalar para humano
POST sistema.php?api=1&endpoint=admin&action=escalar_conversa
{
  "conversa_id": 123
}
```

### **Sistema de Contadores**

```javascript
// Contador em tempo real
GET sistema.php?api=1&endpoint=admin&action=get_message_count&filter=unread
// Retorna: {"count": 5, "filter": "unread"}
```

## 🌟 Funcionalidades Especiais

### **Sistema de Filtros Inteligente**

- **All:** Todas as conversas
- **Unread:** Apenas não lidas
- **Active:** Conversas ativas
- **Escalated:** Escaladas para humanos
- **Resolved:** Conversas resolvidas

### **Interface Responsiva**

- **Desktop:** Layout completo com sidebar
- **Tablet:** Adaptação otimizada
- **Mobile:** Interface touch-friendly

### **Tema da Marca**

- **Cores primárias:** Rosa/pink gradiente
- **Transições:** Suaves entre dark/light
- **Consistência:** Visual em todos módulos

## 🔧 Desenvolvimento e Manutenção

### **Estrutura Modular**

- Backend consolidado em `sistema.php`
- Frontend componentizado
- CSS organizado por funcionalidade
- JavaScript modular e reutilizável

### **Segurança Implementada**

- Configurações sensíveis em `.env`
- Prepared statements contra SQL injection
- Validação de entrada em todos endpoints
- Sistema de sessões seguro

### **Performance Otimizada**

- Polling eficiente para atualizações
- Cache inteligente de consultas
- Carregamento assíncrono de dados
- Minificação de assets

## 🚀 Deploy e Produção

### **Checklist de Deploy**

- ✅ Configurar `.env` com chaves de produção
- ✅ Ajustar permissões de arquivos (644/755)
- ✅ Configurar SSL/HTTPS
- ✅ Otimizar configurações do MySQL
- ✅ Configurar backups automáticos

### **Monitoramento**

- Logs de erro em `error_log`
- Métricas de uso da API Groq
- Performance do banco de dados
- Tempo de resposta das requisições

## 📞 Suporte e Contribuição

Este sistema foi desenvolvido com foco em:

- **Facilidade de uso** para administradores
- **Interface intuitiva** para clientes
- **Manutenção simplificada** para desenvolvedores
- **Escalabilidade** para crescimento futuro

Para dúvidas ou melhorias, consulte a documentação inline no código ou abra uma issue no repositório.

---

**Desenvolvido com ❤️ para D&Z** | **Versão 2.0** | **PHP 8.0+** | **Groq API Integration**
