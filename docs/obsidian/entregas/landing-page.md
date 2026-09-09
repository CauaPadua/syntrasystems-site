---
projeto: Aquapulse
tipo: entrega
papel: Frontend
responsavel: A definir
status: concluido
tags:
  - aquapulse
  - papel/frontend
  - tipo/entrega
---

# Landing page

Função responsável: [[frontend]]

## O que é

Página institucional pública (etapa 1), montada por `require` a partir de
quatro seções independentes. Os textos ficam fora do HTML, em constantes.

## Evidência no projeto

- `index.php` — documento principal
- `includes/sections/hero.php` (101 linhas)
- `includes/sections/informacoes.php` (42 linhas)
- `includes/sections/sistema.php` (49 linhas)
- `includes/sections/vantagens.php` (67 linhas)
- `includes/header.php`, `includes/footer.php`
- `includes/config.php` — copy em constantes (`AQ_NAV`, `AQ_HERO_HIGHLIGHTS`, `AQ_INFO_CARDS`, `AQ_SYSTEM_POINTS`)
- `assets/js/main.js` (214 linhas), `assets/css/style.css` (1.090 linhas)
- `validation/screenshots/hero-1440.png`, `informacoes-1440.png`, `sistema-1440.png`, `vantagens-1440.png`
- Commit `8db3bde` — "Adiciona estrutura inicial do AquaPulse"

## Situação

Concluída. Dois pontos em aberto, ambos deliberados:

- **Solicitar demonstração** é um `button` com aviso de indisponibilidade
  (`includes/sections/vantagens.php:57`) — sem rota quebrada.
- O painel da seção "sistema" é imagem estática
  (`assets/images/dashboard-aquapulse.webp`), não o dashboard funcional.

## Relacionadas

[[frontend]] · [[conteudo-institucional]] · [[responsividade]] · [[status-atual]] · [[tarefas]]
