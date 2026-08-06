# 🚀 Prodmais UMC

![Versão](https://img.shields.io/badge/vers%C3%A3o-2.2.0-blue)
![Arquitetura](https://img.shields.io/badge/arquitetura-modular-green)
![PHP](https://img.shields.io/badge/PHP-8.2-777bb4)
![Docker](https://img.shields.io/badge/Docker-ready-2496ed)

Sistema para gestão e análise da produção científica dos Programas de Pós-Graduação da **Universidade de Mogi das Cruzes** — indexa, busca e exporta currículos Lattes, integrando ORCID e OpenAlex.

Projeto de Iniciação Científica (PIVIC 2024/2025), orientação do Prof. Me. Leandro Miranda de Almeida e coorientação do Prof. Dr. Fabiano Bezerra Menegidio.

---

## 👨‍💻 Desenvolvimento
**Matheus Lucindo** — Desenvolvedor Principal · **João Victor Alexandre Almeida**

---

## ⚡ Início Rápido (desenvolvimento local)

```powershell
# Iniciar o ecossistema completo (MySQL + Elasticsearch + Kibana + PHP/Apache)
.\scripts\INICIAR.ps1

# Acessar a aplicação
http://localhost:8080
```

*   **Elasticsearch:** `http://localhost:9200`
*   **Kibana:** `http://localhost:5601`
*   **phpMyAdmin:** `http://localhost:8081`

Não existe usuário admin padrão pré-cadastrado — crie o primeiro usuário via
`php bin/install.php` ou por um `INSERT` direto na tabela `usuarios_admin`
(veja `sql/schema_auth.sql`).

---

## ☁️ Produção

A aplicação roda em **Render** (build via `Dockerfile.render`), com dados em
**AWS RDS MySQL** e **AWS OpenSearch**. Em desenvolvimento local, a busca usa
Elasticsearch de verdade via Docker Compose — em produção, AWS OpenSearch (um
fork compatível mantido pela AWS, não Elasticsearch propriamente dito).

Guia de migração para infraestrutura institucional:
[`docs/GUIA_TRANSFERENCIA_INFRAESTRUTURA.md`](docs/GUIA_TRANSFERENCIA_INFRAESTRUTURA.md).

---

## 🏗️ Arquitetura Modular

*   **`src/Core/`**: Extensibilidade via Hook Manager (estilo WordPress).
*   **`src/Infrastructure/`**: Camada de dados (Elasticsearch/OpenSearch, MySQL, integrações externas).
*   **`src/Domain/`**: Regras de negócio, importação Lattes, segurança e LGPD.
*   **`src/View/`**: Sistema de componentes e páginas modulares.

---

## 🌟 Principais Funcionalidades
- ✅ **Busca full-text** com autocomplete de pesquisadores, filtros por tipo, Qualis, PPG e período.
- ✅ **Importação Lattes**: parser para currículos XML (artigos, livros, capítulos, eventos), com upload individual ou em lote.
- ✅ **Integrações**: ORCID (enriquece o perfil automaticamente na importação) e OpenAlex (métricas por DOI) ativas; BrCris implementado, aguardando credencial institucional da IBICT.
- ✅ **Gestão de usuários**: aprovação de cadastros, troca de papel, exclusão de contas, notificação por e-mail (Resend).
- ✅ **LGPD**: relatório de impacto (DPIA), logs de auditoria, anonimização e Termo de Ciência e Consentimento.
- ✅ **Exportação**: BibTeX, RIS, CSV, JSON e XML via API (`/api/export.php`).
- ✅ **Guia do Usuário** completo embutido no painel administrativo.

---

## 📂 Organização
- **`/public`**: Arquivos estáticos e entry-points (document root do Apache).
- **`/src`**: Código-fonte organizado por camadas (Core, Domain, Infrastructure, View).
- **`/data`**: Armazenamento local de logs, bancos SQLite e backups (não persiste no Render Free — sem disco persistente).
- **`/docs`**: Documentação técnica, manual do usuário e guias de deploy.

---

## 🎨 Design System

A identidade visual do Prodmais (cores, tipografia, componentes, padrões de UI) está documentada em:

**[design-system-fawn-three.vercel.app](https://design-system-fawn-three.vercel.app/)**

Use essa referência antes de criar ou alterar qualquer tela do sistema, para manter consistência visual entre as páginas.

---

## 📞 Suporte & Contato
- **Universidade de Mogi das Cruzes**

---
© 2026 **Prodmais UMC**
