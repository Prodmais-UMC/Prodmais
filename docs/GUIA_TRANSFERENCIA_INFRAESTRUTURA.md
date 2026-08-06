# Guia de Transferência de Infraestrutura — Prodmais UMC

Este guia existe porque a infraestrutura atual (AWS RDS + AWS OpenSearch + Render) está provisionada
na conta pessoal do desenvolvedor. Quando a instituição decidir assumir os custos, este documento
descreve o passo a passo pra migrar tudo para uma conta AWS institucional sem perder dados nem
derrubar o sistema em produção por muito tempo.

**Este arquivo não contém nenhuma credencial.** Os valores reais (senhas, endpoints, IDs) estão em
`CREDENCIAIS.html`, na raiz do projeto — arquivo local, `.gitignore`d, nunca sobe pro repositório.

---

## 1. Visão geral do que precisa migrar

| Componente | O que é | Onde está hoje |
|---|---|---|
| Banco relacional | AWS RDS MySQL (`prodmais_umc`) | Conta AWS pessoal, região us-east-2 |
| Motor de busca | AWS OpenSearch Service (domínio `prodmais-umc`) | Conta AWS pessoal, região us-east-2 |
| Aplicação (PHP/Apache) | Web service no Render | Conta Render pessoal |
| Domínio | Subdomínio gratuito `*.onrender.com` | Sem domínio próprio ainda |
| Código-fonte | Repositório GitHub | Organização `Prodmais-UMC` |
| Alertas | Webhook de Discord | Canal pessoal |

---

## 2. Pré-requisitos antes de começar

- [ ] Conta AWS da instituição criada (com billing próprio da instituição) e ID da conta (12 dígitos) em mãos.
- [ ] Acesso IAM com permissão de administrador nessa conta (ou alguém do TI que execute os passos junto).
- [ ] Confirmar se a instituição vai usar Render, ou outra hospedagem — os passos de app (seção 5) mudam conforme a escolha.
- [ ] **Senha master do OpenSearch recuperada e anotada em `CREDENCIAIS.html`** — hoje está marcada como pendente, precisa resolver antes de seguir com a seção 4.
- [ ] Reservar uma janela de manutenção (mesmo que curta) — a troca de endpoint do banco/busca implica em uma pausa breve da aplicação.

---

## 3. Migrar o banco de dados (AWS RDS MySQL)

RDS suporta compartilhamento de snapshot entre contas AWS diferentes — não precisa dump/restore manual.

1. **Na conta atual (pessoal):**
   - Console RDS → Snapshots → **Take snapshot** da instância `prodmais-umc-mysql` → aguarde ficar `available`.
   - Selecione o snapshot criado → **Actions → Share snapshot** → em "DB snapshot visibility", marque **Private** → adicione o **AWS Account ID da instituição**.
   - ⚠️ Se o snapshot estiver criptografado com a chave KMS padrão da conta, o compartilhamento cross-account falha silenciosamente na hora de restaurar. Se isso acontecer, recriptografe o snapshot com uma chave KMS customer-managed e conceda permissão de uso dessa chave para a conta da instituição antes de compartilhar.

2. **Na conta da instituição:**
   - Console RDS → Snapshots → aba **Shared with me** → o snapshot compartilhado aparece ali.
   - Selecione-o → **Copy** (isso cria uma cópia própria dentro da conta da instituição, necessária antes de restaurar).
   - Com a cópia pronta → **Actions → Restore snapshot** → escolha classe de instância (o Free Tier `db.t4g.micro` cobre o volume atual), VPC e Security Group novos.
   - Anote o novo endpoint gerado (formato `prodmais-umc-mysql.XXXXXXX.<região>.rds.amazonaws.com`).

3. Ajuste o Security Group da nova instância pra liberar a porta `3306` apenas para o IP/serviço da aplicação (Render ou outra hospedagem) — nunca `0.0.0.0/0`.

---

## 4. Migrar o motor de busca (AWS OpenSearch)

OpenSearch não tem um botão de "compartilhar domínio entre contas" tão direto quanto o RDS. O caminho mais
simples e confiável pra um volume de dados como o do Prodmais (não é big data) é **reindexar via API**, sem
depender de operações no console da AWS:

1. **Criar o domínio novo** na conta da instituição, replicando a configuração atual (ver `CREDENCIAIS.html`
   para tipo de instância, storage e versão do engine usados hoje) — inclusive o Fine-Grained Access Control
   com um usuário master novo (não reaproveitar a senha antiga).
2. **Aplicar os mappings** — rodar a mesma rotina que já existe no projeto
   (`src/View/Pages/Search/InitElasticsearchPage.php`, que chama `applyMappings()`) apontando pro domínio novo,
   pra garantir que os campos `.keyword` (usados em ordenação/filtro) já nasçam corretos — evita o bug de
   `"No mapping found for X.keyword"` que já apareceu neste projeto.
3. **Reindexar os dados** — script simples (pode ser PHP usando o próprio `OpenSearch\ClientBuilder` já
   presente no `composer.json`, ou Python com `opensearch-py`) que:
   - abre um `scroll` (ou Point-in-Time) no domínio antigo, índice por índice (`prodmais_umc`,
     `prodmais_umc_cv`, `prodmais_umc_ppg`, `prodmais_umc_projetos`, `qualis`);
   - envia cada lote via `_bulk` pro domínio novo.
   - Isso funciona entre contas AWS diferentes sem burocracia, porque é só HTTP com as credenciais de cada
     lado — não depende de snapshot nem de IAM cross-account.
4. **Validar contagem de documentos** — `GET /_count` em cada índice, nos dois domínios, e comparar.
5. Só depois de validar, apontar a aplicação pro domínio novo (seção 5) e desligar o domínio antigo.

> Alternativa avançada (só se o volume de dados crescer muito no futuro): snapshot manual pra um bucket S3 e
> restore cross-account via bucket policy. Mais rápido para grandes volumes, mas exige configuração de IAM
> mais delicada — não foi testada neste projeto, então valide a documentação oficial da AWS no momento em
> que for necessário.

---

## 5. Atualizar a aplicação com os novos endpoints

Onde quer que a aplicação fique hospedada (Render institucional, ou outra plataforma escolhida pela
instituição), as variáveis de ambiente a atualizar são:

```
MYSQL_HOST=<novo endpoint RDS>
MYSQL_DB=prodmais_umc
MYSQL_USER=<novo usuário>
MYSQL_PASS=<nova senha>
ELASTICSEARCH_HOST=https://<novo endpoint OpenSearch>
```

Depois de atualizar:
1. Redeploy do serviço.
2. Testar `GET /api/health` — deve reportar MySQL e Elasticsearch como `ok`.
3. Testar um login e uma busca de ponta a ponta antes de considerar a migração concluída.

---

## 6. Transferir o domínio (se a instituição comprar um domínio oficial)

1. Registrar o domínio em nome da instituição (não em nome pessoal).
2. Na hospedagem (Render ou outra), adicionar o **Custom Domain** — a plataforma vai fornecer um registro
   CNAME (ou A, dependendo do caso) pra apontar no DNS do domínio.
3. Criar esse registro no painel de DNS de quem administra o domínio da instituição.
4. Aguardar propagação (pode levar até 72h) e validar o certificado SSL emitido automaticamente pela
   hospedagem.

---

## 7. Transferir a titularidade do repositório

O código já está na organização `Prodmais-UMC` no GitHub. Falta apenas:
1. Adicionar a conta institucional (TI da UMC, ou quem for o responsável técnico) como **Owner** da
   organização (Settings → People → Invite member → papel Owner).
2. Você pode manter seu acesso como colaborador, ou sair depois — decisão sua, sem pressa.

---

## 8. Desligar os recursos da conta pessoal

**Só depois de validar que tudo está rodando 100% na infraestrutura nova:**
- RDS: Delete instance (desmarcar "Create final snapshot" só se tiver certeza que não precisa mais de backup).
- OpenSearch: Delete domain.
- Render: Delete service (ou apenas remover o billing pessoal, se a instituição for assumir o mesmo serviço).

---

## 9. Checklist rápido pro dia da migração

- [ ] Snapshot do RDS compartilhado e restaurado na conta nova
- [ ] Domínio OpenSearch novo criado, com mappings aplicados
- [ ] Dados reindexados e contagem de documentos validada
- [ ] Variáveis de ambiente da aplicação atualizadas
- [ ] `/api/health` retornando OK
- [ ] Login + busca testados manualmente
- [ ] Domínio institucional (se houver) apontado e com SSL ativo
- [ ] Organização GitHub com a instituição como Owner
- [ ] Recursos antigos (conta pessoal) desligados só após validação completa
