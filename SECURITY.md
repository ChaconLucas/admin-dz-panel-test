# 🔒 IMPORTANTE - SEGURANÇA

## Dados Sensíveis Protegidos

Antes de fazer commit, os seguintes dados sensíveis foram protegidos:

### ✅ Feito:

- Token do Melhor Envio removido do código
- Criado `.env.example` com template
- Adicionado aviso de segurança no código

### 🚨 Para Produção:

1. **Criar arquivo `.env` local** com seus tokens reais
2. **Configurar token via interface** (campos de senha no admin)
3. **Usar variáveis de ambiente** no servidor de produção

### 📁 Arquivos Ignorados pelo Git:

- `.env` (configurações locais)
- `*.log` (logs do sistema)
- `cache/` (arquivos temporários)

### 🛠 Como Configurar:

1. Copie `.env.example` para `.env`
2. Substitua `seu_token_aqui` pelo token real
3. Configure via admin panel (recomendado)

### 💡 Tokens podem ser inseridos via:

- Interface admin (campos com ícones de olho)
- Arquivo `.env` (desenvolvimento)
- Variáveis de ambiente (produção)

**⚠️ NUNCA faça commit de tokens reais!**
