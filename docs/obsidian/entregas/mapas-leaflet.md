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

# Mapas (Leaflet)

Função responsável: [[frontend]]

## O que é

Mapa real e interativo com a localização das represas — não é captura de tela.
Sem chave paga e sem Google Maps.

## Evidência no projeto

- `assets/js/maps.js` (162 linhas) — `AqMap`
- `assets/js/pages/maps-page.js` (172 linhas)
- `dashboard/mapas.php`
- `assets/vendor/leaflet/` — biblioteca, CSS e ícones de marcador versionados
- `api/v1/map/reservoirs.php` — fornece `markers[]`
- `validation/screenshots/dashboard/mapas.png`

## Detalhes

- Tiles do OpenStreetMap, com atribuição.
- Zoom automático para enquadrar todos os marcadores.
- As coordenadas vêm da API — o navegador nunca acessa a fonte de dados
  diretamente.

## Situação

Concluído. As coordenadas atuais são **demonstrativas** (região de Rio
Claro/SP) e devem vir do cadastro real quando o banco entrar
([[planejamento-das-entidades]]).

## Relacionadas

[[frontend]] · [[endpoints-da-api]] · [[banco-de-dados]] · [[validacao-das-telas]]
