# Documentação Técnica — Plugin Ticket Dashboard

**Versão:** 1.0.0  
**Compatibilidade GLPI:** 11.0.0 – 11.99.99  
**PHP mínimo:** 8.2  
**Licença:** GPLv2+  
**Repositório:** https://github.com/hugoaac/glpi-ticketdashboard

---

## Sumário

1. [Visão Geral](#visão-geral)
2. [Estrutura de Diretórios](#estrutura-de-diretórios)
3. [Instalação e Desinstalação](#instalação-e-desinstalação)
4. [Arquitetura](#arquitetura)
   - [setup.php](#setupphp)
   - [hook.php](#hookphp)
   - [inc/dashboard.class.php](#incdashboardclassphp)
   - [inc/widget.class.php](#incwidgetclassphp)
   - [inc/dataprovider.class.php](#incdataproviderclassphp)
   - [front/dashboard.php](#frontdashboardphp)
   - [front/builder.php](#frontbuilderphp)
   - [ajax/data.php](#ajaxdataphp)
   - [ajax/builder.php](#ajaxbuilderphp)
5. [Banco de Dados](#banco-de-dados)
6. [Tipos de Widget](#tipos-de-widget)
7. [Endpoints AJAX](#endpoints-ajax)
8. [Segurança](#segurança)
9. [Dependências Front-end](#dependências-front-end)
10. [Fluxos de Uso](#fluxos-de-uso)

---

## Visão Geral

O **Ticket Dashboard** é um plugin para GLPI 11.x que fornece um painel analítico customizável para gestão de chamados. Ele permite monitorar estatísticas de tickets, conformidade com SLA/TIT, carga de trabalho por técnico/grupo e outros indicadores por meio de widgets interativos com filtragem em tempo real.

Principais funcionalidades:
- Dashboard por usuário com conjunto de widgets configurável
- 10 tipos de widgets (contadores, barras, tabelas cruzadas, cards de status)
- Filtros por período, tipo, grupo, técnico e prioridade
- Auto-refresh configurável
- Interface de construção com drag & drop para reordenar e adicionar/remover widgets
- Criação automática do dashboard padrão no primeiro acesso

---

## Estrutura de Diretórios

```
ticketdashboard/
├── setup.php                        # Inicialização e metadados do plugin
├── hook.php                         # Criação/remoção de tabelas
├── inc/
│   ├── dashboard.class.php          # Modelo de dashboard (CommonDBTM)
│   ├── widget.class.php             # Registro de tipos de widgets
│   └── dataprovider.class.php       # Motor de consultas e agregação de dados
├── front/
│   ├── dashboard.php                # Página principal do painel
│   └── builder.php                  # Interface de configuração de widgets
├── ajax/
│   ├── data.php                     # Endpoint: dados dos widgets
│   └── builder.php                  # Endpoint: adicionar/remover widgets
└── .github/
    └── workflows/release.yml        # Pipeline de release automático
```

---

## Instalação e Desinstalação

### Instalação (`plugin_ticketdashboard_install`)

Definida em [hook.php](hook.php). Cria duas tabelas no banco de dados se ainda não existirem:

```sql
-- Dashboards por usuário
CREATE TABLE glpi_plugin_ticketdashboard_dashboards (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL DEFAULT '',
    users_id      INT UNSIGNED NOT NULL DEFAULT 0,
    is_default    TINYINT(1)   NOT NULL DEFAULT 0,
    date_creation TIMESTAMP NULL DEFAULT NULL,
    date_mod      TIMESTAMP NULL DEFAULT NULL,
    KEY users_id (users_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Widgets associados a cada dashboard
CREATE TABLE glpi_plugin_ticketdashboard_widgets (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    dashboards_id INT UNSIGNED NOT NULL DEFAULT 0,
    widget_type   VARCHAR(50)  NOT NULL DEFAULT '',
    position      INT          NOT NULL DEFAULT 0,
    config        TEXT,
    date_mod      TIMESTAMP NULL DEFAULT NULL,
    KEY dashboards_id (dashboards_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Desinstalação (`plugin_ticketdashboard_uninstall`)

Remove ambas as tabelas com `DROP TABLE IF EXISTS`.

---

## Arquitetura

### setup.php

Ponto de entrada do plugin. Implementa as quatro funções exigidas pelo GLPI:

| Função | Responsabilidade |
|--------|-----------------|
| `plugin_version_ticketdashboard()` | Retorna metadados (nome, versão, autor, compatibilidade) |
| `plugin_ticketdashboard_check_prerequisites()` | Valida versão do GLPI e do PHP |
| `plugin_ticketdashboard_check_config()` | Valida configuração (sempre retorna `true`) |
| `plugin_init_ticketdashboard()` | Registra o plugin como CSRF-compliant e adiciona item de menu |

**Integração de menu:** O plugin é registrado sob o grupo `helpdesk` apontando para `PluginTicketdashboardDashboard`.

---

### hook.php

Contém exclusivamente as funções de instalação/desinstalação. Usa `DBmysql::tableExists()` para instalação idempotente.

---

### inc/dashboard.class.php

**Classe:** `PluginTicketdashboardDashboard extends CommonDBTM`  
**Tabela:** `glpi_plugin_ticketdashboard_dashboards`

#### Métodos principais

| Método | Descrição |
|--------|-----------|
| `getOrCreateDefault()` | Retorna o dashboard padrão do usuário logado; se não existir, cria um novo com os 8 widgets padrão |
| `getUserDashboards()` | Lista todos os dashboards do usuário atual |
| `getGroupsForFilter()` | Retorna grupos com `is_assign=1` para o dropdown de filtro |
| `getTechniciansForFilter($filters)` | Retorna técnicos com tickets atribuídos no período filtrado |
| `getUserDefaultGroupId()` | Retorna o primeiro grupo de atribuição do usuário para pré-seleção |

#### Controle de acesso

Usa o direito `'ticket'` do GLPI — o usuário precisa ter permissão de leitura em tickets para acessar o plugin.

---

### inc/widget.class.php

**Classe:** `PluginTicketdashboardWidget extends CommonDBTM`  
**Tabela:** `glpi_plugin_ticketdashboard_widgets`

Registro central de todos os tipos de widget disponíveis.

#### Métodos principais

| Método | Descrição |
|--------|-----------|
| `getWidgetTypes()` | Retorna array com metadados de todos os 10 tipos de widget |
| `getForDashboard($dashboards_id)` | Lista widgets de um dashboard ordenados por `position` |
| `addDefaults($dashboards_id)` | Insere o conjunto padrão de 8 widgets |
| `addToDashboard($dashboards_id, $type)` | Adiciona um widget; impede duplicatas |

---

### inc/dataprovider.class.php

**Classe:** `PluginTicketdashboardDataProvider`

Motor central de consultas SQL. Usa o DBAL do GLPI (`DBmysqlIterator`, `QueryExpression`) para construir queries parametrizadas.

#### Despacho de dados

```
getData($widget_type, $filters)
  ├── total_tickets      → getTotalTickets()
  ├── total_incidents    → getTypeCount(1)
  ├── total_requests     → getTypeCount(2)
  ├── by_group           → getByGroup()
  ├── by_technician      → getByTechnician()
  ├── sla_compliance     → getSLACompliance()
  ├── tit_compliance     → getTITCompliance()
  ├── by_status          → getByStatus()
  ├── by_origin          → getByOrigin()
  └── tech_origin_matrix → getTechOriginMatrix()
```

#### Filtros suportados

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `date_from` | string (YYYY-MM-DD) | Data de início (filtro em `glpi_tickets.date`) |
| `date_to` | string (YYYY-MM-DD) | Data de fim (inclui até 23:59:59) |
| `ticket_type` | int (1 ou 2) | 1=Incidente, 2=Requisição |
| `groups_id` | int | ID do grupo de atribuição |
| `users_id` | int | ID do técnico atribuído |
| `priority` | int (1–6) | Prioridade do ticket |

#### Restrição de entidade

Todas as queries respeitam `getEntitiesRestrictCriteria('glpi_tickets')`, garantindo que o usuário visualize apenas tickets de sua organização.

#### Métodos de consulta

| Método | Retorno |
|--------|---------|
| `getTotalTickets()` | `{"value": N}` |
| `getTypeCount($type)` | `{"value": N, "total": T, "pct": P}` |
| `getByGroup()` | `{"labels": [...], "data": [...]}` — top 15 |
| `getByTechnician()` | `{"labels": [...], "data": [...]}` — top 15 |
| `getByStatus()` | `{"new": N, "attending": N, "pending": N, "solved": N}` |
| `getSLACompliance()` | `{"labels": [...], "on_time": [...], "late": [...]}` |
| `getTITCompliance()` | `{"labels": [...], "on_time": [...], "late": [...]}` |
| `getByOrigin()` | `{"labels": [...], "data": [...], "pct": [...]}` |
| `getTechOriginMatrix()` | `{"headers": [...], "rows": [[...]]}` |

#### Geração de cores

O método `seedColor($str)` usa `crc32()` para gerar uma cor HSL consistente e determinística a partir de uma string (nome do técnico/grupo).

---

### front/dashboard.php

Página principal do painel. Exige autenticação via `Session::checkLoginUser()`.

#### Estrutura HTML

1. **Cabeçalho** — título do dashboard e botão para o Builder
2. **Card de filtros** — 7 controles:
   - Data início (padrão: 1º do mês atual)
   - Data fim (padrão: último dia do mês atual)
   - Tipo de ticket (Todos/Incidente/Requisição)
   - Grupo (pré-seleciona o grupo padrão do usuário)
   - Técnico
   - Prioridade
   - Auto-refresh (desativado / 30s / 1min / 5min / 10min)
3. **Grade de widgets** — layout Bootstrap responsivo com spinners de carregamento

#### JavaScript — funções principais

| Função | Descrição |
|--------|-----------|
| `getFilters()` | Coleta todos os valores dos filtros |
| `buildQueryString(filters)` | Serializa os filtros para query string URL |
| `loadWidget(el)` | Dispara requisição AJAX para um widget específico |
| `renderWidget(body, type, data)` | Seleciona e executa o renderizador adequado ao tipo |
| `loadAllWidgets()` | Aciona `loadWidget` para todos os widgets da grade |
| `applyRefresh()` | Configura o intervalo de auto-refresh |

#### Renderizadores por tipo de visualização

| Tipo | Visualização |
|------|-------------|
| `number` | Número grande centralizado com label |
| `number_pct` | Número + badge de percentual + label |
| `status_cards` | 4 cards coloridos (novo / atendendo / pendente / resolvido) |
| `matrix_table` | Tabela HTML (técnicos × origens) |
| `bar` | Gráfico de barras horizontais (Chart.js) |
| `stacked_bar` | Barras horizontais empilhadas: no prazo vs. atrasado |
| `empty` | Mensagem "Sem dados" |

---

### front/builder.php

Interface de configuração do dashboard. Permite adicionar, remover e reordenar widgets.

#### Estrutura HTML

- **Coluna esquerda (col-md-5):** widgets disponíveis com botão "Adicionar"
- **Coluna direita (col-md-7):** widgets ativos com alça de drag e botão "Remover"
- **Rodapé de formulário:** botão "Salvar ordem" (visível após reordenação)

#### Funcionamento

- **Reordenação:** drag & drop nativo (HTML5) com feedback visual; ao soltar, gera inputs ocultos `order[N]` com os IDs na nova sequência
- **Adicionar widget:** requisição AJAX POST para `ajax/builder.php`; atualiza UI sem recarregar a página
- **Remover widget:** requisição AJAX POST com confirmação via `confirm()`
- **Salvar ordem:** POST tradicional com CSRF token; redireciona de volta para o builder após salvar

---

### ajax/data.php

**Método HTTP:** GET  
**Resposta:** `application/json`

Retorna os dados de um widget individual. Todos os parâmetros de filtro são passados via query string.

**Parâmetros:**

| Parâmetro | Obrigatório | Descrição |
|-----------|-------------|-----------|
| `widget_type` | Sim | Chave do tipo de widget |
| `date_from` | Não | Data de início (YYYY-MM-DD) |
| `date_to` | Não | Data de fim (YYYY-MM-DD) |
| `ticket_type` | Não | 1 ou 2 |
| `groups_id` | Não | ID do grupo |
| `users_id` | Não | ID do técnico |
| `priority` | Não | 1–6 |

**Exemplo de resposta (by_status):**
```json
{
  "new": 12,
  "attending": 34,
  "pending": 5,
  "solved": 89
}
```

**Resposta de erro:**
```json
{"error": "mensagem de erro"}
```

---

### ajax/builder.php

**Método HTTP:** POST  
**Corpo:** JSON  
**Headers obrigatórios:**
- `X-Requested-With: XMLHttpRequest`
- `X-Glpi-Csrf-Token: <token>`

**Ação `add`:**
```json
// Request
{"action": "add", "widget_type": "by_status"}

// Response
{"success": true, "id": 42}
```

**Ação `remove`:**
```json
// Request
{"action": "remove", "widget_id": 42}

// Response
{"success": true}
```

**Resposta de erro:**
```json
{"success": false, "error": "mensagem de erro"}
```

---

## Banco de Dados

### Tabelas do plugin

#### `glpi_plugin_ticketdashboard_dashboards`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | INT UNSIGNED PK | Identificador |
| `name` | VARCHAR(255) | Nome do dashboard |
| `users_id` | INT UNSIGNED | FK → `glpi_users.id` |
| `is_default` | TINYINT(1) | 1 = dashboard padrão do usuário |
| `date_creation` | TIMESTAMP | Data de criação |
| `date_mod` | TIMESTAMP | Data de modificação |

#### `glpi_plugin_ticketdashboard_widgets`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | INT UNSIGNED PK | Identificador |
| `dashboards_id` | INT UNSIGNED | FK → `dashboards.id` |
| `widget_type` | VARCHAR(50) | Chave do tipo de widget |
| `position` | INT | Ordem de exibição (0-based) |
| `config` | TEXT | Configuração JSON (reservado) |
| `date_mod` | TIMESTAMP | Data de modificação |

### Tabelas do GLPI utilizadas (leitura)

| Tabela | Uso |
|--------|-----|
| `glpi_tickets` | Fonte primária de dados |
| `glpi_users` | Nomes dos técnicos |
| `glpi_groups` | Nomes dos grupos |
| `glpi_groups_tickets` | Relacionamento ticket ↔ grupo (type=2: atribuído) |
| `glpi_tickets_users` | Relacionamento ticket ↔ técnico (type=2: atribuído) |
| `glpi_requesttypes` | Origens/tipos de solicitação |

---

## Tipos de Widget

| Chave | Título | Visualização | Descrição |
|-------|--------|-------------|-----------|
| `total_tickets` | Total de Tickets | Número | Contagem simples de tickets |
| `total_incidents` | Incidentes | Número + % | Incidentes com percentual do total |
| `total_requests` | Requisições | Número + % | Requisições com percentual do total |
| `by_group` | Por Grupo | Barras horizontais | Top 15 grupos por volume |
| `by_technician` | Por Técnico | Barras horizontais | Top 15 técnicos por volume |
| `sla_compliance` | Conformidade SLA | Barras empilhadas | No prazo vs. atrasado por prioridade |
| `tit_compliance` | Conformidade TIT | Barras empilhadas | Tempo de 1º atendimento por prioridade |
| `by_status` | Por Status | 4 cards | Novo / Atendendo / Pendente / Resolvido |
| `by_origin` | Por Origem | Barras horizontais | Volume por tipo de solicitação |
| `tech_origin_matrix` | Matriz Técnico × Origem | Tabela HTML | Cruzamento técnico por origem |

---

## Endpoints AJAX

| Método | Arquivo | Autenticação | Descrição |
|--------|---------|-------------|-----------|
| GET | `ajax/data.php` | Session | Retorna dados de um widget |
| POST | `ajax/builder.php` | Session + CSRF | Adiciona/remove widget |
| POST | `front/builder.php` | Session + CSRF | Salva nova ordem dos widgets |

---

## Segurança

| Medida | Implementação |
|--------|--------------|
| Autenticação | `Session::checkLoginUser()` em todas as páginas e endpoints |
| Autorização | Verificação do direito `'ticket'` via `Session::haveRight()` |
| CSRF | Plugin registrado como `csrf_compliant`; token validado em POSTs AJAX |
| Injeção SQL | DBAL do GLPI com queries parametrizadas e `QueryExpression` |
| Restrição de entidade | `getEntitiesRestrictCriteria()` em todas as queries |
| Validação de entrada | Tipos numéricos convertidos com `(int)`, datas validadas com `DateTime`, `widget_type` validado contra whitelist |

---

## Dependências Front-end

| Biblioteca | Versão | Origem | Uso |
|------------|--------|--------|-----|
| Chart.js | 4.4.0 | CDN | Gráficos de barras simples e empilhados |
| Bootstrap 5 | (via GLPI) | GLPI core | Layout, cards, botões, dropdowns |

Instâncias de Chart.js são armazenadas em `chartInstances` e destruídas antes de re-renderização para evitar vazamentos de memória.

---

## Fluxos de Uso

### Visualizar dashboard

```
Usuário acessa Helpdesk → Ticket Dashboard → Painel
    │
    ├─ [Primeiro acesso] → Cria dashboard padrão com 8 widgets
    │
    ├─ Carrega lista de widgets do dashboard
    ├─ Renderiza grade com spinners
    ├─ Para cada widget: GET ajax/data.php?widget_type=...&<filtros>
    └─ Renderiza visualização com dados recebidos
```

### Configurar dashboard

```
Usuário clica "Construtor de Dashboard"
    │
    ├─ Exibe widgets disponíveis (esquerda) e ativos (direita)
    │
    ├─ Adicionar widget → POST ajax/builder.php {action:"add", widget_type:"..."}
    │       └─ Widget aparece na lista ativa sem recarregar
    │
    ├─ Remover widget → POST ajax/builder.php {action:"remove", widget_id:N}
    │       └─ Widget removido da lista ativa sem recarregar
    │
    └─ Reordenar (drag & drop) → POST front/builder.php {save_order, order[0..N]}
            └─ Posições salvas; página recarrega
```

### Fluxo de dados (DataProvider)

```
ajax/data.php recebe filtros via GET
    │
    ├─ Instancia PluginTicketdashboardDataProvider
    ├─ Chama getData($widget_type, $filters)
    ├─ buildWhere($filters) → cláusula WHERE parametrizada
    │       ├─ Filtro de data → glpi_tickets.date BETWEEN ...
    │       ├─ Filtro de tipo → glpi_tickets.type = ...
    │       ├─ Filtro de grupo → JOIN glpi_groups_tickets
    │       ├─ Filtro de técnico → JOIN glpi_tickets_users
    │       ├─ Filtro de prioridade → glpi_tickets.priority = ...
    │       └─ Restrição de entidade → getEntitiesRestrictCriteria()
    ├─ Executa query via DBmysqlIterator
    └─ Retorna JSON formatado
```
