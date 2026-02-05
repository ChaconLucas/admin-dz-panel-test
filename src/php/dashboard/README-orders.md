# Gestão de Pedidos - D&Z Admin

## Visão Geral

A página de Gestão de Pedidos foi desenvolvida seguindo rigorosamente o padrão visual da D&Z, mantendo sidebar e header intactos. A página oferece funcionalidades completas para gerenciar pedidos, desde a visualização até o processamento de reembolsos.

## Funcionalidades Implementadas

### 1. Cabeçalho de Filtros e Abas

- ✅ Filtro de data (início e fim)
- ✅ Abas de navegação:
  - Todos os Pedidos
  - Aguardando Pagamento
  - Pagos
  - Enviados
  - Reembolsos
- ✅ Busca por Nome, CPF ou Nº do Pedido

### 2. Tabela Dinâmica com Integração de Fluxo

- ✅ Listagem de pedidos com dados do banco
- ✅ Integração com sistema de Gestão de Fluxo para status e cores
- ✅ Colunas: ID, Data, Cliente, Valor Total, Status (com badge colorido), Ações
- ✅ Botão "Ver Detalhes" na cor rosa #ff00cc

### 3. Modal de Detalhes do Pedido

Ao clicar em "Ver Detalhes", abre modal com:

- ✅ **Dados do Cliente**: Nome, E-mail, Telefone (WhatsApp)
- ✅ **Endereço de Entrega**: Endereço completo, cidade, CEP
- ✅ **Informações do Pedido**: Data, valor, status, observações
- ✅ **Itens do Pedido**: Lista detalhada com produtos, quantidades e valores

### 4. Gestão de Reembolso

- ✅ Aba específica para pedidos com status de reembolso
- ✅ Botão "Processar Reembolso" para pedidos elegíveis
- ✅ Atualização automática de status no banco de dados

### 5. Design e Interface

- ✅ Cores conforme especificado (rosa #ff00cc para destaques)
- ✅ Badges de status com cores da Gestão de Fluxo
- ✅ Interface responsiva
- ✅ Tema dark/light compatível
- ✅ Animações e transições suaves

## Configuração e Instalação

### 1. Pré-requisitos

- PHP 7.4 ou superior
- MySQL/MariaDB
- Servidor web (Apache/Nginx)
- Estrutura existente da D&Z Admin

### 2. Atualização do Banco de Dados

Para garantir que todas as funcionalidades funcionem corretamente:

1. Execute o arquivo `update-orders-schema.php` uma única vez:

   ```
   http://seudominio.com/admin-teste/src/php/dashboard/update-orders-schema.php
   ```

2. Ou execute manualmente o SQL em `sql/criar_tabelas_dashboard.sql`

### 3. Configuração de Permissões

Certifique-se que o usuário do banco tenha permissões para:

- SELECT, INSERT, UPDATE nas tabelas: `pedidos`, `clientes`, `produtos`, `itens_pedido`, `status_fluxo`
- CREATE TABLE (apenas para primeira execução)

## Estrutura de Dados

### Principais Tabelas

- `pedidos` - Dados principais dos pedidos
- `clientes` - Informações dos clientes
- `itens_pedido` - Produtos do pedido
- `status_fluxo` - Status e suas configurações (cores, automações)
- `pedidos_historico_status` - Histórico de mudanças (criada automaticamente)

### Campos Importantes

- `pedidos.status` - Status atual (integrado com Gestão de Fluxo)
- `pedidos.forma_pagamento` - Pix, Cartão, Boleto
- `pedidos.status_pagamento` - Pendente, Aprovado, Pago, Estornado

## API/AJAX Endpoints

A página implementa os seguintes endpoints AJAX:

### `POST orders.php`

- `action=buscar_pedidos` - Lista pedidos com filtros
- `action=buscar_detalhes_pedido` - Detalhes completos de um pedido
- `action=processar_reembolso` - Processa reembolso de pedido

## Personalização

### Cores dos Status

As cores são gerenciadas pela Gestão de Fluxo (`status_fluxo.cor_hex`). Para alterar:

1. Acesse Gestão de Fluxo no admin
2. Edite os status desejados
3. As mudanças refletirão automaticamente nos pedidos

### Campos Adicionais

Para adicionar campos ao modal de detalhes:

1. Adicione o campo na tabela `pedidos`
2. Inclua no SQL de busca em `buscarDetalhes()`
3. Adicione na função `renderizarDetalhesPedido()` no JavaScript

## Segurança

- ✅ Verificação de sessão em todas as requisições
- ✅ Prepared statements para prevenir SQL injection
- ✅ Validação de entrada em todos os formulários
- ✅ Logs de auditoria para ações críticas

## Responsividade

A interface é totalmente responsiva:

- Desktop: Layout de 3 colunas completo
- Tablet: Layout adaptado com elementos empilhados
- Mobile: Interface otimizada para telas pequenas

## Suporte e Manutenção

### Logs

Todas as ações importantes são registradas automaticamente através do sistema `auto_log.php`.

### Debug

Para debug, ative os logs de erro do PHP e verifique:

- Console do navegador para erros JavaScript
- Logs do servidor para erros PHP
- Network tab para requisições AJAX

### Problemas Comuns

1. **Pedidos não aparecem**: Verifique se existem dados nas tabelas
2. **Modal não abre**: Verifique erros no console JavaScript
3. **Cores não aparecem**: Verifique se a Gestão de Fluxo tem status cadastrados

## Próximas Melhorias (Sugestões)

- [ ] Exportação de pedidos para Excel/PDF
- [ ] Notificações em tempo real
- [ ] Integração com APIs de frete
- [ ] Histórico completo de status
- [ ] Dashboard com métricas de pedidos

---

**Desenvolvido para D&Z Admin Dashboard**
_Versão: 1.0 - Fevereiro 2026_
