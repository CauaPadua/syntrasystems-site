---
projeto: Aquapulse
tipo: entrega
papel: Analista de Negócios
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/negocios
  - tipo/entrega
---

# Requisitos do sistema

Função responsável: [[analista-de-negocios]]

## O que é

A especificação escrita do sistema interno: contexto, limites, arquitetura
esperada, comportamento de cada tela, critérios de aceite e o que deve ser
validado antes de considerar pronto.

## Evidência no projeto

- `aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md` — **743 linhas**, com
  seções por tela e por assunto
- `aquapulse-kit-claude-sistema/LEIA-ME.md` — como o pacote entra no projeto
- `aquapulse-kit-claude-sistema/MAPA-DE-REFERENCIAS.md`

## Limites definidos como requisito

- Somente PHP 8.0, HTML5, CSS3 e JavaScript puro; ambiente local XAMPP.
- Sem React, Vue, Angular, Next.js, TypeScript ou Node.js como requisito de execução.
- Permitidas apenas bibliotecas leves de navegador: Chart.js, Leaflet e SVGs Lucide.
- Landing page e login existentes **devem ser preservados**, não redesenhados.
- **Não implementar banco de dados** nesta etapa — sem tabelas, migrations, SQL,
  PDO/MySQL ou credenciais.
- Os dados vêm de uma API PHP que lê dados simulados determinísticos, de modo
  que a futura equipe de banco substitua apenas a fonte.

## Situação

Concluído — a especificação existe, está versionada e foi cumprida.

## Relacionadas

[[analista-de-negocios]] · [[definicao-das-telas]] · [[criterios-operacionais]] · [[criterios-de-aceite]] · [[arquitetura]]
