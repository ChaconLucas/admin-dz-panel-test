# 🎯 D&Z Admin - Sistema Completo de Gestão

**Painel administrativo moderno e completo para gerenciamento de produtos, vendedores, pedidos, chat com IA e sistema avançado de logs de auditoria.**

---

## 📋 **Índice**

1. [Sobre o Projeto](#-sobre-o-projeto)
2. [Tecnologias Utilizadas](#-tecnologias-utilizadas)
3. [Estrutura do Projeto](#-estrutura-do-projeto)
4. [Instalação e Configuração](#-instalação-e-configuração)
5. [Funcionalidades Principais](#-funcionalidades-principais)
6. [Sistema de Logs](#-sistema-de-logs)
7. [Como Usar](#-como-usar)
8. [Configurações Avançadas](#-configurações-avançadas)
9. [Segurança](#-segurança)
10. [Suporte](#-suporte)

---

## 🚀 **Sobre o Projeto**

O **D&Z Admin** é um sistema completo de administração desenvolvido para pequenas e médias empresas que precisam de:

- **Gestão de Produtos**: Cadastro, edição, controle de estoque e preços
- **Chat Inteligente**: Sistema de chat com IA integrada (Groq API)
- **Painel de Vendedores**: Controle de equipe e performance
- **Sistema de Logs**: Auditoria completa de todas as ações
- **Dashboard Analytics**: Métricas e gráficos em tempo real
- **Interface Moderna**: Design responsivo com tema D&Z

---

## 💻 **Tecnologias Utilizadas**

### **Backend:**

- ![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white) **PHP 7.4+**
- ![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white) **MySQL 8.0**
- ![XAMPP](https://img.shields.io/badge/XAMPP-Local_Server-FB7A24?style=flat&logo=xampp&logoColor=white) **XAMPP**

### **Frontend:**

- ![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat&logo=html5&logoColor=white) **HTML5**
- ![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat&logo=css3&logoColor=white) **CSS3** (Grid/Flexbox)
- ![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=flat&logo=javascript&logoColor=black) **JavaScript ES6+**
- ![Chart.js](https://img.shields.io/badge/Chart.js-Charts-FF6384?style=flat&logo=chart.js&logoColor=white) **Chart.js**

### **APIs Externas:**

- ![Groq](https://img.shields.io/badge/Groq-AI_Chat-000000?style=flat&logo=ai&logoColor=white) **Groq API** (Chat IA)
- ![Material](https://img.shields.io/badge/Material-Icons-757575?style=flat&logo=material-design&logoColor=white) **Material Symbols**

---

## 📁 **Estrutura do Projeto**

```
admin-teste/
├── 📂 PHP/                     # Core PHP
│   ├── acoes.php              # CRUD de usuários
│   ├── conexao.php            # Conexão MySQL
│   └── ...
├── 📂 src/                     # Código fonte principal
│   ├── 📂 css/                # Estilos
│   │   ├── dashboard.css      # Tema principal
│   │   ├── dashboard-cards.css
│   │   └── dashboard-sections.css
│   ├── 📂 js/                 # JavaScript
│   │   ├── dashboard.js       # Funcionalidades principais
│   │   ├── chat-auto.js       # Chat automático
│   │   └── mensagens-simples.js
│   └── 📂 php/                # Módulos PHP
│       ├── sistema.php        # API principal
│       ├── auto_log.php       # Sistema de logs
│       └── 📂 dashboard/      # Páginas do painel
│           ├── index.php      # Dashboard principal
│           ├── products.php   # Gestão de produtos
│           ├── all-logs.php   # Visualizar logs
│           ├── menssage.php   # Chat/mensagens
│           └── ...
├── 📂 config/                 # Configurações
├── 📂 uploads/                # Uploads de arquivos
├── 📂 assets/                 # Recursos estáticos
└── 📄 README.md              # Este arquivo
```

---

## 🛠 **Instalação e Configuração**

### **1. Pré-requisitos:**

- **XAMPP** instalado
- **PHP 7.4+**
- **MySQL 8.0+**
- **Navegador** moderno

### **2. Clone o Projeto:**

```bash
cd C:\XAMPP-install\htdocs\
git clone [repositório] admin-teste
```

### **3. Configuração do Banco:**

```bash
# Acesse o MySQL
mysql -u root -p

# Execute o script de criação
source admin-teste/sql/criar_tabelas_dashboard.sql;
```

### **4. Configuração do PHP:**

```php
// config/config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'seu_banco');
define('DB_USER', 'root');
define('DB_PASS', '');
define('GROQ_API_KEY', 'sua_chave_groq');
```

### **5. Iniciar Servidor:**

```bash
# Inicie o XAMPP
# Acesse: http://localhost/admin-teste
```

---

## 🎯 **Funcionalidades Principais**

### **📊 Dashboard Principal**

- **Cards de Métricas**: Vendas, pedidos, produtos em tempo real
- **Gráficos Interativos**: Chart.js com dados dinâmicos
- **Timeline de Atividades**: Últimas ações do sistema

### **🛍 Gestão de Produtos**

- ✅ Cadastro completo com imagens múltiplas
- ✅ Controle de estoque e preços promocionais
- ✅ Categorização e SKU único
- ✅ Edição inline com AJAX
- ✅ Upload de imagens com preview

### **💬 Sistema de Chat Inteligente**

- 🤖 **IA Integrada**: Groq API para respostas automáticas
- 📱 **Interface Moderna**: Design tipo WhatsApp
- 🔔 **Notificações**: Tempo real para novas mensagens
- 💾 **Histórico Completo**: Todas as conversas salvas

### **👥 Gestão de Vendedores**

- 📋 Cadastro de equipe de vendas
- 📈 Métricas individuais de performance
- 🎯 Atribuição de leads e clientes
- 📊 Relatórios de vendas por vendedor

### **🛒 Gestão de Pedidos**

- 📦 Controle completo de pedidos
- 🚚 Integração com sistema de frete
- 💳 Status de pagamento
- 📋 Histórico de compras

### **📋 Gestão de Revendedores**

- 🏪 Cadastro de revendedores
- 📊 Controle de comissões
- 🎯 Sistema de leads
- 📈 Acompanhamento de performance

---

## 📋 **Sistema de Logs**

### **🔍 Auditoria Completa:**

- ✅ **Todas as ações** são registradas automaticamente
- ✅ **Quem fez**, **quando** e **o que mudou**
- ✅ **Valores antes/depois** para alterações
- ✅ **IP do usuário** e timestamp brasileiro

### **📊 Visualização de Logs:**

- 🔍 **Pesquisa avançada**: Nome, ação, IP, data
- 📄 **Paginação inteligente**: 20 itens por página
- 📥 **Export CSV**: Download de relatórios
- 🗑 **Exclusão seletiva**: Limpar logs antigos

### **Exemplos de Logs:**

```
João alterou preço do produto Notebook de R$ 2.500,00 para R$ 2.300,00
Maria excluiu usuário Carlos Silva (email: carlos@email.com)
Admin criou produto Smartphone Galaxy (categoria: eletrônicos)
```

---

## 🎮 **Como Usar**

### **1. Primeiro Acesso:**

```bash
# Acesse: http://localhost/admin-teste
# Login: admin / Senha: sua_senha
```

### **2. Configurar Sistema:**

1. **Configurações**: Acesse `settings.php`
2. **API Keys**: Configure Groq API
3. **Usuários**: Crie usuários administrativos
4. **Produtos**: Cadastre primeiros produtos

### **3. Operação Diária:**

1. **Dashboard**: Monitore métricas
2. **Produtos**: Gerencie estoque
3. **Chat**: Responda clientes
4. **Logs**: Auditoria de ações

---

## ⚙ **Configurações Avançadas**

### **🎨 Personalização Visual:**

```css
/* src/css/dashboard.css */
:root {
  --color-primary: #28a745; /* Verde D&Z */
  --color-danger: #ff1493; /* Rosa D&Z */
  --color-success: #28a745; /* Verde sucesso */
}
```

### **🤖 Configurar Chat IA:**

```php
// config/config.php
define('GROQ_API_KEY', 'gsk_sua_chave_aqui');
define('GROQ_MODEL', 'mixtral-8x7b-32768');
define('GROQ_TEMPERATURE', 0.7);
```

### **📊 Métricas Dashboard:**

```php
// Personalizar cards do dashboard
// src/php/dashboard/index.php - linha 120+
```

### **🔔 Notificações:**

```javascript
// Intervalo de atualização (ms)
const UPDATE_INTERVAL = 2000; // 2 segundos
```

---

## 🔐 **Segurança**

### **🛡 Proteções Implementadas:**

- ✅ **Hash de Senhas**: bcrypt para todas as senhas
- ✅ **Prepared Statements**: SQL injection prevention
- ✅ **CSRF Protection**: Tokens de validação
- ✅ **XSS Prevention**: htmlspecialchars em outputs
- ✅ **Session Security**: Validação de sessões

### **📋 Logs de Segurança:**

- ✅ **Todas as ações** são auditadas
- ✅ **IPs registrados** para rastreamento
- ✅ **Tentativas de acesso** logadas
- ✅ **Exclusões críticas** com backup

### **🔧 Configurações de Segurança:**

```php
// PHP configurações recomendadas
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // HTTPS only
```

---

## 🚨 **Solução de Problemas**

### **❌ Problemas Comuns:**

#### **1. Erro de Conexão MySQL:**

```bash
# Verifique se MySQL está rodando
net start mysql80

# Teste conexão
mysql -u root -p
```

#### **2. Erro PHP Fatal:**

```bash
# Verifique sintaxe
php -l arquivo.php

# Ative error reporting
ini_set('display_errors', 1);
```

#### **3. Chat IA não responde:**

```php
// Verifique API key
var_dump(GROQ_API_KEY);

// Teste conectividade
curl "https://api.groq.com/openai/v1/models"
```

#### **4. Upload de imagens falha:**

```bash
# Verifique permissões
chmod 755 uploads/
chmod 755 uploads/produtos/
```

---

## 📞 **Suporte**

### **🆘 Precisa de Ajuda?**

- 📧 **Email**: suporte@dz.com
- 💬 **Chat**: Sistema interno do painel
- 📱 **WhatsApp**: (11) 99999-9999
- 🌐 **Website**: www.dz.com.br

### **🐛 Reportar Bugs:**

1. Descreva o problema
2. Inclua logs de erro
3. Passos para reproduzir
4. Screenshot se possível

### **📈 Melhorias:**

- Sugestões são bem-vindas!
- Fork o projeto e contribua
- Documentação sempre atualizada

---

## 📄 **Licença**

Este projeto é propriedade da **D&Z** e está sob licença proprietária.
Todos os direitos reservados © 2026 D&Z Sistemas.

---

## 🎉 **Créditos**

- **Desenvolvimento**: Equipe D&Z
- **Design**: Sistema próprio D&Z
- **IA Integration**: Groq API
- **Icons**: Google Material Symbols
- **Charts**: Chart.js

---

**🚀 Sistema D&Z Admin - Transformando gestão em resultados!**

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
