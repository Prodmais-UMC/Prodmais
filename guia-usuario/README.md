# Prodmais UMC — Guia do Usuário

**No ar em: [guia-usuario.vercel.app](https://guia-usuario.vercel.app/)**

Site estático de documentação, no mesmo estilo dos Claude Code Docs: barra
lateral de navegação, sumário "Nesta seção" à direita, busca e tema
claro/escuro. Sem build step — é HTML + CSS + JS puro, assim como o
`design-system/`.

## Ver localmente

```bash
cd guia-usuario
python3 -m http.server 8000
# http://localhost:8000
```

## Publicar no Vercel

1. [vercel.com/new](https://vercel.com/new) → importe o repositório `Prodmais-UMC/Prodmais`
2. Em **Root Directory**, selecione `guia-usuario`
3. Framework Preset: **Other** (site estático, sem build)
4. Deploy

## Manter atualizado

O conteúdo também existe (resumido) dentro do próprio painel administrativo
do Prodmais UMC, na aba "Guia do Usuário". Ao atualizar uma funcionalidade do
sistema, atualize os dois lugares — este site é a versão completa e
compartilhável; a aba do admin é a versão rápida, sem sair do sistema.
