---
projeto: Aquapulse
tipo: hub
tags:
  - aquapulse
  - tipo/hub
---

# Aquapulse

Sistema de monitoramento de represas — landing page, login e sistema interno.
Documentação viva do projeto, escrita a partir do código.

Domínio: `syntrasystems.com.br` · Publicação automática a cada `push` em `main`.

---

## A equipe

[[equipe]] — as seis funções, com cor própria no gráfico.

| Função | Cor | Núcleo | Entregas |
| --- | --- | --- | --- |
| Frontend | `#00D9FF` | [[frontend]] | 10 |
| Backend | `#8B5CF6` | [[backend]] | 10 |
| Banco de Dados | `#22C55E` | [[banco-de-dados]] | 4 |
| Analista de Qualidade | `#FF9F1C` | [[analista-de-qualidade]] | 6 |
| Analista de Negócios | `#FF4ECD` | [[analista-de-negocios]] | 6 |
| Granmaster | `#FFD166` | [[granmaster]] | 6 |

Tabela completa em [[registro-de-entregas]] · cores e ajustes do gráfico em
[[legenda-do-grafico]].

---

## Documentação técnica

| Nota | Assunto |
| --- | --- |
| [[status-atual]] | Onde o projeto está hoje, tela a tela, e os erros conhecidos nos gráficos |
| [[arquitetura]] | As três camadas, o caminho de uma requisição e a estrutura de pastas |
| [[frontend]] | Landing page, login e as 14 telas do sistema interno |
| [[backend]] | Sessão, autenticação, services, repositórios e validação |
| [[api]] | Os 18 endpoints, envelopes, parâmetros e códigos de erro |
| [[banco-de-dados]] | Dados simulados de hoje e o ponto preparado para a troca |
| [[decisoes]] | Escolhas de projeto e o motivo de cada uma |
| [[tarefas]] | O que ainda precisa ser feito |

---

## Situação atual

- **Landing page** — concluída (`index.php`, quatro seções).
- **Login** — concluído e testado, sobre repositório simulado.
- **Dashboard** — 14 telas criadas, com dados simulados.
- **API** — 18 endpoints em PHP funcionando, sobre dados simulados.
- **Banco de dados** — ainda não implementado; ponto de troca preparado.
- **Correção dos gráficos** — em andamento, ainda não commitada.

Detalhes e números em [[status-atual]].

---

## Em uma frase

Três camadas independentes: o navegador só fala com `api/v1/` por JSON, e a
camada de dados é trocável por uma linha em `Container.php` — por isso conectar
o banco não exige mexer em tela, JavaScript nem endpoint.

---

## Documentos originais

Estas notas resumem e conectam os documentos técnicos em `docs/`, que seguem
sendo a referência detalhada:

| Documento | Assunto |
| --- | --- |
| [`api-contract.md`](../api-contract.md) | contrato da API de autenticação |
| [`api-monitoring.md`](../api-monitoring.md) | contrato da API do sistema interno |
| [`mock-data.md`](../mock-data.md) | como os dados simulados funcionam |
| [`database-handoff.md`](../database-handoff.md) | guia para a equipe de banco de dados |
| [`login-stage.md`](../login-stage.md) | etapa do login |

A especificação da etapa 3 está em
`aquapulse-kit-claude-sistema/PROMPT-CLAUDE-CODE.md` (743 linhas).

---

## Como executar

```bash
php -S localhost:8000
```

No XAMPP (Windows), da raiz do projeto:

```powershell
& C:\xampp\php\php.exe -S localhost:8000 -t .
```

Credencial local: `demo@aquapulse.local` / `Aquapulse@123`.
Requer PHP 8.0+ com as extensões `session` e `json`.
