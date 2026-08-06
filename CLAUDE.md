# CLAUDE.md — Prodmais UMC

Sistema de gestão de produção científica da Universidade de Mogi das Cruzes (UMC). Indexa, busca e exporta produção acadêmica dos programas de pós-graduação via Lattes, ORCID e OpenAlex.

---

## Stack

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| Backend | PHP + Apache | 8.2 |
| Banco principal | MySQL | 8.0 |
| Busca full-text | Elasticsearch | 8.10.4 |
| Frontend | HTML + CSS + Vanilla JS | — |
| Containerização | Docker + Docker Compose | — |
| Dependências PHP | Composer | — |

---

## Arquitetura

```
Prodmais/
├── public/           # Document root (Apache aponta aqui)
│   ├── api/          # Endpoints REST (search, export, upload, health)
│   ├── css/          # Folhas de estilo
│   ├── js/           # JavaScript vanilla
│   └── *.php         # Páginas da aplicação
├── src/              # Classes de serviço PSR-4 (namespace App\)
├── config/           # Configurações (config.php, config_umc.php, umc_config.php)
├── sql/              # Schemas MySQL (schema.sql, schema_auth.sql)
├── bin/              # Scripts CLI (indexer.php, install.php, migrate_es_to_mysql.php)
├── plugins/          # Plugins estilo WordPress (HookManager + PluginLoader)
├── data/             # Runtime — NÃO versionar conteúdo (logs, uploads, cache)
├── docs/             # Documentação técnica e guias de deploy
└── .specify/         # Specs locais — nunca sobe para produção
```

### Estrutura modular de `src/` (arquitetura Clean)

```
src/
├── Core/                     # Infraestrutura transversal
│   ├── Anonymizer.php        # Anonimização de dados
│   ├── HookManager.php       # Sistema de hooks (actions/filters)
│   └── PluginLoader.php      # Carregador de plugins
│
├── Domain/                   # Regras de negócio
│   ├── Importers/            # LattesImporter, LattesParser
│   ├── Reports/              # CapesReportGenerator
│   ├── Security/             # AuthManager, LgpdComplianceService
│   ├── Services/             # ExportService, InstitutionalDashboard, LogService, UmcProgramService
│   └── Validation/           # ProductionValidator, UmcValidationSystem
│
├── Infrastructure/           # Adaptadores externos
│   ├── Database/             # DatabaseService (PDO MySQL/SQLite)
│   ├── Elasticsearch/        # ElasticsearchService, JsonStorageService
│   ├── External/             # OpenAlexFetcher, OrcidFetcher, BrCrisIntegration
│   └── Storage/              # PdfParser
│
└── View/                     # Sistema de componentes e páginas
    ├── Component.php         # Classe base de componente
    ├── Components/           # Footer, HeroSection, Navbar, StatCard
    └── Pages/
        ├── Auth/             # LoginPage, ForgotPasswordPage, ResetPasswordPage, ChangePasswordPage
        ├── Dashboard/        # AdminPage, DashboardPage, ImportLattesPage
        ├── Search/           # HomePage, ResearchersPage, PPGPage, PPGsPage, ResultPage, ProjectsPage, PresearchPage
        └── Static/           # PrivacyPolicyPage, TermsOfUsePage
```

### Classes críticas

| Classe | Caminho | Responsabilidade |
|--------|---------|-----------------|
| `AuthManager` | `Domain/Security/` | Login, BCrypt, brute-force, sessões seguras |
| `LattesImporter` | `Domain/Importers/` | Importação de XML do Lattes com skip inteligente |
| `ElasticsearchService` | `Infrastructure/Elasticsearch/` | Cliente ES 8.x, fallback para MySQL |
| `DatabaseService` | `Infrastructure/Database/` | ORM simples PDO MySQL/SQLite |
| `HookManager` | `Core/` | Sistema de hooks actions/filters |
| `LgpdComplianceService` | `Domain/Security/` | Conformidade LGPD, anonimização, auditoria |
| `ExportService` | `Domain/Services/` | Exportação BibTeX, RIS, CSV, JSON, XML |
| `OpenAlexFetcher` | `Infrastructure/External/` | Enriquecimento via API OpenAlex |

### Padrão de retorno dos métodos de serviço

```php
return ['sucesso' => bool, 'mensagem' => string, 'dados' => mixed];
```

### Endpoints da API (`public/api/`)

| Arquivo | Rota | Descrição |
|---------|------|-----------|
| `search.php` | GET `/api/search` | Busca full-text (ES com fallback MySQL) |
| `researchers.php` | GET/POST `/api/researchers` | CRUD de pesquisadores |
| `upload_and_index.php` | POST `/api/upload_and_index` | Upload XML + indexação |
| `export.php` | GET `/api/export` | Exportação multi-formato |
| `umc_dashboard.php` | GET `/api/umc_dashboard` | Dados do dashboard |
| `umc_filters.php` | GET `/api/umc_filters` | Opções de filtro UMC |
| `filter_values.php` | GET `/api/filter_values` | Valores disponíveis por filtro |
| `validation.php` | POST `/api/validation` | Validação de dados |
| `health.php` | GET `/api/health` | Health check de todos os serviços |

---

## Comandos de Desenvolvimento

### Docker (stack completo)

```powershell
.\scripts\INICIAR.ps1          # Sobe MySQL + ES + Kibana + phpMyAdmin + PHP/Apache
.\scripts\PARAR.ps1            # Para todos os containers
.\scripts\VERIFICAR.ps1        # Verifica saúde dos serviços
.\scripts\REBUILD.ps1          # Rebuild forçado das imagens
.\scripts\INICIAR_LOCAL.ps1    # Desenvolvimento sem Docker
```

```bash
# Ou manualmente:
docker-compose up -d
docker-compose -f docker-compose.prod.yml up -d   # produção
docker-compose down
docker-compose logs -f web
```

### PHP / Composer

```bash
composer install          # Instalar dependências
composer update           # Atualizar dependências (apenas em dev)
php bin/install.php       # Setup inicial do banco
php bin/indexer.php       # Indexar currículos no Elasticsearch
php bin/migrate_es_to_mysql.php   # Migrar dados ES → MySQL
php bin/atualizar_contadores.php  # Atualizar contadores em cache
```

### Testes E2E (Cypress)

```bash
npm test                  # Headless (CI)
npm run test:open         # GUI interativo
npm run test:headed       # Browser visível
npm run test:screenshots  # Apenas screenshots
```

### Portas padrão

| Serviço | Porta |
|---------|-------|
| Web (PHP/Apache) | 8080 |
| MySQL | 3307 |
| Elasticsearch | 9200 |
| Kibana | 5601 |
| phpMyAdmin | 8081 |

---

## Banco de Dados

### Schemas

- `sql/schema.sql` — Tabelas principais
- `sql/schema_auth.sql` — Autenticação e auditoria

### Tabelas principais

| Tabela | Descrição |
|--------|-----------|
| `pesquisadores` | Perfis de pesquisadores (Lattes ID, ORCID, PPG) |
| `producoes` | Produções científicas (artigos, livros, patentes) |
| `projetos` | Projetos de pesquisa |
| `ppgs` | Programas de pós-graduação UMC |
| `usuarios_admin` | Usuários do sistema (BCrypt, bloqueio por tentativas) |
| `tokens_recuperacao_senha` | Tokens one-time para reset de senha (TTL 1h) |
| `log_login` | Auditoria de tentativas de login |

### Índices Elasticsearch

| Índice | Conteúdo |
|--------|----------|
| `prodmais_umc` | Produções científicas |
| `prodmais_umc_cv` | Currículos Lattes completos |
| `prodmais_umc_ppg` | Programas de pós-graduação |
| `prodmais_umc_projetos` | Projetos de pesquisa |
| `qualis` | Classificação Qualis CAPES |

---

## Convenções e Padrões

### Commits (obrigatório em português)

Prefixos aceitos: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `perf`, `security`

```
feat: Adicionar página de cadastro com aprovação admin
fix: Corrigir parser XML para campos de bolsista opcionais
docs: Atualizar guia de deploy OCI com configuração SSL
refactor: Extrair validação de e-mail para método estático em AuthManager
test: Adicionar teste Cypress para fluxo completo de cadastro
chore: Atualizar elasticsearch/elasticsearch para 8.11
security: Remover credenciais hardcoded do login.php
```

**Nunca** commitar com mensagem vaga: `;`, `update`, `fix`, `wip`.

### Formato de Pull Request

Título em português, curto e com prefixo semântico:
```
feat: Cadastro de usuários com aprovação administrativa
```

Body obrigatório:

```markdown
## Resumo
- O que foi feito e por quê (não o que o código faz — isso está no diff)

## Tipo de mudança
- [ ] feat — nova funcionalidade
- [ ] fix — correção de bug
- [ ] refactor — sem mudança de comportamento
- [ ] docs — documentação
- [ ] security — correção de segurança

## Como testar
1. Subir stack: `.\scripts\INICIAR.ps1`
2. Acessar http://localhost:8080/...
3. Realizar ação X e verificar Y

## Checklist
- [ ] Testes Cypress passando (`npm test`)
- [ ] Sem credenciais hardcoded
- [ ] Sem dados em `.env.production` commitados
- [ ] LGPD: dados pessoais tratados via `LgpdComplianceService`
- [ ] Input validado com `filter_input()` ou prepared statements
```

### Branches

```
main          ← produção, apenas merge via PR aprovado
feat/<nome>   ← nova funcionalidade
fix/<nome>    ← correção de bug
docs/<nome>   ← apenas documentação
```

Nunca commitar direto em `main`. Squash merge preferido para manter histórico limpo.

### Versionamento (tags Git)

Cada PR mergeado em `main` recebe uma tag SemVer (`vMAJOR.MINOR.PATCH`):
- `PATCH` — correção de bug
- `MINOR` — nova funcionalidade
- `MAJOR` — mudança que quebra compatibilidade

```bash
git tag -a vX.Y.Z -m "descrição da entrega"
git push origin vX.Y.Z
```

Rollback: `git checkout vX.Y.Z` ou apontar o deploy da plataforma para a tag.

### Estilo de código PHP

- Classes em CamelCase: `AuthManager`, `LattesImporter`
- Métodos em camelCase: `solicitarRecuperacaoSenha()`
- Constantes em SCREAMING_SNAKE: `MAX_TENTATIVAS`
- Sempre usar prepared statements — zero concatenação de SQL
- Sempre validar input externo com `filter_input()` ou `FILTER_*`
- Erros internos: `error_log()` — nunca exibir stack trace para o usuário

---

## Regras de Segurança (nunca violar)

1. **Nunca credenciais hardcoded** — sempre `.env` ou `config/config.php` (excluído do git)
2. **Sempre usar `AuthManager`** para autenticação — o `login.php` com array estático é legado e deve ser migrado
3. **Sempre prepared statements** — zero SQL por concatenação de string
4. **Sempre BCrypt** para senhas: `password_hash($senha, PASSWORD_BCRYPT)`
5. **Nunca commitar `.env.production`** — use secrets do servidor ou variáveis de ambiente do Docker
6. **LGPD**: dados pessoais de pesquisadores passam por `LgpdComplianceService` antes de qualquer export
7. **Sessões**: regenerar ID a cada 30min, timeout de 2h de inatividade — já implementado em `AuthManager::iniciarSessaoSegura()`
8. **Brute-force**: 5 tentativas → bloqueio de 15min — já implementado em `AuthManager::login()`

---

## Deploy Gratuito

### OCI Always Free

Oracle Cloud oferece permanentemente (não é trial):
- 4 vCPU ARM Ampere A1 + 24GB RAM
- Suficiente para MySQL + Elasticsearch + PHP/Apache sem degradação

```bash
# Na VM OCI, com Docker instalado:
git clone https://github.com/<user>/Prodmais.git
cd Prodmais
cp .env.example .env   # Configurar variáveis
docker-compose -f docker-compose.prod.yml up -d
```

Guias detalhados: `docs/OCI_DEPLOY_GUIDE.md` e `docs/DEPLOY_OCI.md`

Railway e Render foram avaliados e descartados como opção — hospedagem é feita
exclusivamente na OCI Always Free (VM sempre gratuita, sem os limites de uso
de plataformas PaaS gratuitas).

### Variáveis de ambiente obrigatórias em produção

Template completo: `.env.example` (73 variáveis documentadas)

```ini
MYSQL_HOST=db
MYSQL_DB=prodmais_umc
MYSQL_USER=prodmais
MYSQL_PASS=<senha-forte>
ES_HOST=elasticsearch:9200
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<seu-dominio>
SESSION_SECURE=true
```

---

## Workflow Spec-Kit (fases de especificação)

Specs ficam em `.specify/` — adicionado ao `.gitignore`, nunca sobe para produção.

### Instalação (Python 3.11+ necessário)

```powershell
pip install pipx
pipx ensurepath
pipx install git+https://github.com/github/spec-kit.git
specify init prodmais --integration claude
specify version   # Confirmar instalação
```

### Estrutura de uma spec

```
.specify/specs/<id>/
├── idealization.md   # Fase 1: O que construir e por quê
├── requirements.md   # Fase 2: Requisitos funcionais e não-funcionais
├── planning.md       # Fase 3: Arquitetura e abordagem técnica
└── tasks.md          # Fase 4: Tarefas atômicas executáveis
```

### Comandos spec-kit

```bash
specify init <nome>      # Fase 1 — Idealization
specify specify          # Fase 2 — Requirements
specify plan             # Fase 3 — Planning
specify tasks            # Fase 4 — Tasks
specify analyze          # Validação de consistência entre artefatos
```

---

## Controle de Consumo de Tokens (Claude)

Calibrar o esforço de IA ao tamanho real da tarefa:

| Tipo de tarefa | Estratégia |
|---------------|-----------|
| Typo, rename, um arquivo | Agente único, sem sub-agentes |
| Nova página ou endpoint | 1 agente Explore + implementação direta |
| Feature completa (form + API + DB) | 2-3 agentes Explore em paralelo, depois Plan |
| Auditoria, refactor de arquitetura | Workflow com `pipeline()` e verificação adversarial |

Não spawnar agentes para tarefas que cabem em uma leitura de arquivo. Não usar Workflow para mudanças de 1-2 arquivos.

---

## Programas de Pós-Graduação (UMC)

| Programa | Código CAPES |
|----------|-------------|
| Biotecnologia | 33002010191P0 |
| Engenharia Biomédica | 33002010192P0 |
| Políticas Públicas | 33002010193P0 |
| Ciência e Tecnologia em Saúde | 33002010194P0 |

---

## Integrações Externas

| API | Classe | Descrição |
|-----|--------|-----------|
| OpenAlex | `OpenAlexFetcher` | Métricas de citação e metadados |
| ORCID | `OrcidFetcher` | Perfis via Consórcio CAPES-ORCID |
| BrCris | `BrCrisIntegration` | Plataforma brasileira de pesquisa |
| Lattes (CNPq) | `LattesImporter` / `LattesParser` | Importação de currículos XML |

---

## Pontos de Atenção (débito técnico conhecido)

- `.env.production` foi removido do tracking do Git, mas as credenciais antigas (MySQL local/OCI) continuam recuperáveis no histórico (commit `c0f1742`) — avaliar `git filter-repo` antes de expandir acesso à organização do repositório
- Produção usa AWS OpenSearch com Fine-Grained Access Control (não o `xpack.security.enabled=false` do Elasticsearch local de dev)
- Sem backup automatizado confirmado no RDS/OpenSearch — validar retenção de snapshot antes de repassar a infraestrutura para a instituição (ver `docs/GUIA_TRANSFERENCIA_INFRAESTRUTURA.md`)
