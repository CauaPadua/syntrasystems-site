# o que eu mandaria para o Claude

Quero iniciar a etapa do sistema interno do Aquapulse. Implemente todas as telas do dashboard usando as imagens da pasta `references/dashboard` como referência visual oficial.

O objetivo é reproduzir com alta fidelidade o layout, a hierarquia, as proporções, os componentes e a identidade visual das referências, mas com uma interface real e funcional. Não use as capturas como plano de fundo e não transforme gráficos, menus, cards, tabelas ou mapas em imagens estáticas.

## contexto e limites desta etapa

- O projeto utiliza somente PHP 8.0, HTML5, CSS3 e JavaScript puro.
- O ambiente local é XAMPP.
- Não introduza React, Vue, Angular, Next.js, TypeScript, Node.js como requisito de execução ou qualquer framework de front-end.
- É permitido usar bibliotecas leves de navegador para necessidades específicas: Chart.js para gráficos, Leaflet para mapas e SVGs Lucide para ícones.
- A landing page e a tela de login existentes devem ser preservadas.
- Não redesenhe nem reescreva a landing page ou o login.
- Integre o acesso ao dashboard ao fluxo de autenticação já existente.
- Não implemente banco de dados nesta etapa.
- Não crie tabelas, migrations, arquivos SQL, conexão PDO/MySQL nem credenciais de banco.
- Os dados devem vir de uma API PHP funcional que, provisoriamente, leia dados simulados determinísticos. A futura equipe de banco substituirá apenas a fonte dos dados, sem precisar refazer as telas ou alterar o contrato da API.
- Não pare depois de apresentar um plano. Inspecione, implemente, teste e entregue o resultado completo, salvo se existir um bloqueio real.

## inspeção obrigatória antes de alterar

Antes de escrever código:

1. Inspecione toda a estrutura atual do projeto.
2. Leia os arquivos da landing page, login, configuração, includes, CSS e JavaScript existentes.
3. Verifique como a sessão e o redirecionamento após o login funcionam atualmente.
4. Localize e examine individualmente todas as imagens em `references/dashboard`.
5. Leia `MAPA-DE-REFERENCIAS.md` e `design/design-tokens.css`, se estiverem presentes na raiz do material de referência.
6. Localize o logotipo correto em `assets/brand/logo-aquapulse.png` ou reutilize o ativo correto que já exista no projeto.
7. Identifique os padrões existentes de organização do projeto e adapte a solução a eles. Não recrie o projeto do zero.
8. Execute uma verificação inicial da landing page e do login para registrar que continuam funcionando antes das mudanças.

Use estas referências:

- `references/dashboard/visao-geral/01-visao-geral-represa-selecionada.png`
- `references/dashboard/visao-geral/02-visao-geral-todas-as-represas.png`
- `references/dashboard/monitoramento/01-volume-de-vazao.png`
- `references/dashboard/monitoramento/02-nivel-do-reservatorio.png`
- `references/dashboard/monitoramento/03-ph.png`
- `references/dashboard/monitoramento/04-volume-armazenado.png`
- `references/dashboard/monitoramento/05-precipitacao.png`
- `references/dashboard/monitoramento/06-previsao-duracao-agua.png`
- `references/dashboard/monitoramento/07-situacao-operacional.png`
- `references/dashboard/monitoramento/08-comparativo-vazao.png`
- `references/dashboard/demais-telas/03-relatorios.png`
- `references/dashboard/demais-telas/04-niveis.png`
- `references/dashboard/demais-telas/05-mapas.png`
- `references/dashboard/demais-telas/06-alertas.png`
- `references/dashboard/demais-telas/07-configuracoes.png`

## regra sobre referências e ativos

- As capturas em `references` servem somente para comparação visual durante o desenvolvimento.
- Elas não podem ser copiadas para a pasta pública nem exibidas dentro da página final.
- Use o logotipo correto `Aquapulse`. Nunca use ou escreva `Aqualpulse`.
- Não recorte ícones das referências. Reutilize a função ou arquivo de ícones SVG já existente no projeto e amplie-o com ícones Lucide quando necessário.
- Não recorte mapas das referências. Implemente um mapa real com Leaflet e OpenStreetMap.
- Não recorte gráficos das referências. Recrie todos com Chart.js.
- Cards, badges, botões, filtros, tabelas, menus, tooltips e indicadores devem ser HTML/CSS reais.
- Caso o projeto já tenha um sistema de ícones próprio, mantenha apenas um sistema para evitar inconsistência.

## arquitetura esperada

Respeite a estrutura existente, mas mantenha separação clara entre front-end, back-end e fonte de dados. Se ainda não houver organização equivalente, use uma estrutura semelhante a esta:

```text
assets/
  css/
    dashboard.css
    dashboard-responsive.css
  js/
    api-client.js
    dashboard-shell.js
    filters.js
    charts.js
    maps.js
    pages/
  vendor/
    chartjs/
    leaflet/
dashboard/
  index.php
  includes/
    sidebar.php
    topbar.php
    context-filter.php
    status-components.php
  pages/
api/
  index.php ou endpoints PHP separados
app/
  Contracts/
  Repositories/
    Mock/
  Services/
  Support/
data/
  mock/
docs/
  api-contract.md
```

Não force exatamente esses nomes se o projeto já possuir uma arquitetura coerente. O importante é:

- as views não conterem grandes blocos de dados simulados;
- o JavaScript consumir a API PHP com `fetch`;
- os endpoints não misturarem HTML com JSON;
- os dados simulados ficarem fora da pasta pública, quando possível;
- a fonte simulada implementar uma interface que depois poderá receber uma implementação com banco;
- componentes compartilhados não serem duplicados em cada página.

Crie uma abstração equivalente a `MonitoringRepositoryInterface` e uma implementação `MockMonitoringRepository`. Controllers e services devem depender da interface, não diretamente dos arquivos simulados. Deixe documentado que a futura implementação de banco deverá criar, por exemplo, `PdoMonitoringRepository`, mantendo os mesmos métodos e respostas.

## integração com login e sessão

- Preserve o login atual e reutilize a sessão PHP já criada.
- Depois de uma autenticação válida, direcione o usuário para a Visão geral do dashboard.
- Usuários sem sessão válida que tentarem abrir qualquer página do sistema devem ser redirecionados para o login.
- Endpoints da API também devem verificar a sessão; quando não houver autorização, retornar HTTP 401 com JSON, nunca uma página HTML.
- O logout deve encerrar a sessão e retornar ao login.
- Não coloque senha, token ou dado de autenticação em JavaScript ou `localStorage`.
- Não crie um segundo sistema de autenticação se já houver um funcionando.
- Como não existe banco nesta etapa, mantenha o mecanismo de usuário demonstrativo já existente. Se ele ainda estiver incompleto, finalize-o apenas como modo de desenvolvimento claramente isolado, sem substituir o contrato futuro de autenticação.

## rotas e navegação

Adapte as rotas ao roteador atual. Caso não exista roteador, implemente rotas PHP simples que funcionem no XAMPP sem exigir configuração complexa de Apache.

O sistema precisa ter destinos independentes para:

- Visão geral;
- Monitoramento / Volume de vazão;
- Monitoramento / Nível do reservatório;
- Monitoramento / pH;
- Monitoramento / Volume armazenado;
- Monitoramento / Precipitação;
- Monitoramento / Previsão de duração da água;
- Monitoramento / Situação operacional;
- Monitoramento / Comparativo de vazão;
- Relatórios;
- Níveis;
- Mapas;
- Alertas;
- Configurações.

Todos os itens do menu devem funcionar. O item `Monitoramento` abre e fecha um submenu usando a seta. O submenu precisa:

- mostrar as oito opções;
- indicar visualmente a opção ativa;
- usar um botão real com `aria-expanded` e `aria-controls`;
- permanecer aberto quando uma das páginas internas estiver ativa;
- funcionar com mouse, teclado e toque;
- preservar o estado durante a navegação quando fizer sentido.

## shell visual compartilhado

Recrie fielmente o shell visto nas referências:

- sidebar fixa azul-marinho em desktop, aproximadamente 264 px;
- gradiente ou variação muito sutil de azul no menu lateral;
- logotipo Aquapulse no topo;
- item ativo em azul royal com cantos arredondados;
- ícones lineares consistentes;
- cartão `Sistema seguro` no rodapé da sidebar;
- cabeçalho superior branco com título, subtítulo, data, última atualização e perfil do usuário;
- fundo geral cinza muito claro;
- conteúdo com espaçamento regular de aproximadamente 16 px entre cards;
- cards brancos, borda clara, raio aproximado de 14 px e sombra muito leve;
- textos principais em azul-marinho;
- azul royal para ações e dados principais;
- verde para normal, âmbar para atenção e vermelho para crítico;
- tipografia Manrope, usando a mesma fonte já adotada pela landing page; mantenha fallback adequado;
- layout desktop de referência otimizado para 1536 × 1024, sem exigir zoom do navegador.

Use `design/design-tokens.css` como orientação inicial. Ajuste os valores após comparação visual, mas mantenha as cores e medidas centralizadas em variáveis CSS. Não espalhe valores de cor repetidos pelos arquivos.

## componentes reutilizáveis obrigatórios

Crie componentes ou includes reutilizáveis para:

- sidebar e submenu;
- topbar;
- seletor de empresa;
- seletor de represa;
- seletor de período;
- card de KPI;
- badge de status;
- cabeçalho de card;
- estado de carregamento;
- estado sem dados;
- estado de erro;
- tabela responsiva;
- painel de alertas;
- tooltip de informação;
- contêiner de gráfico;
- confirmação e feedback de ações.

Evite duplicar HTML e configuração de gráficos entre as telas. Use funções com parâmetros para gerar variações.

## API PHP sem banco de dados

Implemente uma API PHP funcional na mesma origem do site. O navegador não deve acessar arquivos JSON diretamente. O front-end usa `fetch` e os endpoints PHP leem a fonte simulada.

Formato padrão de sucesso:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "source": "mock",
    "generated_at": "2024-05-22T09:30:00-03:00",
    "company_id": "all",
    "reservoir_id": "all",
    "period": "7d"
  },
  "error": null
}
```

Formato padrão de erro:

```json
{
  "success": false,
  "data": null,
  "meta": {},
  "error": {
    "code": "INVALID_RESERVOIR",
    "message": "A represa informada não existe ou não está disponível."
  }
}
```

Requisitos da API:

- sempre enviar `Content-Type: application/json; charset=utf-8`;
- utilizar os status HTTP adequados: 200, 400, 401, 404 e 500;
- validar empresa, represa, período e métrica por allowlist;
- não exibir stack trace ou caminho interno ao usuário;
- responder de forma consistente em todos os endpoints;
- utilizar horário `America/Sao_Paulo` para datas demonstrativas;
- manter IDs estáveis;
- não gerar números aleatórios a cada atualização;
- documentar todos os endpoints em `docs/api-contract.md`.

Implemente contratos equivalentes aos seguintes recursos, adaptando os caminhos ao projeto:

```text
GET /api/companies
GET /api/reservoirs?company_id={id|all}
GET /api/overview?company_id={id|all}&reservoir_id={id|all}&period={period}
GET /api/monitoring/flow?reservoir_id={id}&period=24h
GET /api/monitoring/level?reservoir_id={id}&period=7d
GET /api/monitoring/ph?reservoir_id={id}&period=24h
GET /api/monitoring/storage?reservoir_id={id}&period=30d
GET /api/monitoring/precipitation?reservoir_id={id}&period=7d
GET /api/monitoring/duration?reservoir_id={id}&horizon=90d
GET /api/monitoring/operation?reservoir_id={id}
GET /api/monitoring/flow-comparison?reservoir_id={id}&current={range}&previous={range}
GET /api/reports?reservoir_id={id|all}&status={status}&period={period}
GET /api/alerts?reservoir_id={id|all}&severity={severity}&status={status}
GET /api/map/reservoirs?company_id={id|all}
GET /api/settings
```

Se o projeto não utiliza reescrita de URL, endpoints como `api/overview.php` são aceitáveis. Priorize funcionamento confiável no XAMPP.

## dados simulados coerentes

Crie dados fixos e consistentes para, no mínimo, duas empresas e três represas. Use estes valores centrais para que os totais e telas não se contradigam:

| represa | nível | volume | vazão | pH | precipitação 24h | duração | situação |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Represa Santa Clara | 82,4% | 1.234 hm³ | 56,2 m³/s | 7,2 | 18,6 mm | 84 dias | Atenção |
| Represa Rio Verde | 76,1% | 980 hm³ | 43,8 m³/s | 7,4 | 12,3 mm | 96 dias | Normal |
| Represa Serra Azul | 77,3% | 740 hm³ | 32,5 m³/s | 7,3 | 8,7 mm | 91 dias | Normal |

Na seleção `Todas as represas`, os valores consolidados devem ser:

- 3 represas monitoradas e online;
- volume total armazenado: 2.954 hm³;
- nível médio: 78,6%;
- vazão total: 132,5 m³/s;
- pH médio: 7,3;
- situação: 2 normais e 1 em atenção.

Crie séries temporais coerentes com os valores finais mostrados nos cards. O último ponto de cada gráfico deve corresponder ao KPI atual da respectiva tela. Não use valores incompatíveis entre cards, gráficos e tabelas.

Inclua coordenadas simuladas distintas para as três represas. Identifique claramente no código e na documentação que as coordenadas são demonstrativas e deverão ser substituídas pelo banco de dados.

## comportamento dos filtros

### visão geral

- Mostrar filtro de empresa e filtro de represa.
- Empresa deve aceitar `Todas as empresas`.
- Represa deve aceitar `Todas as represas`.
- Quando `Todas as represas` estiver selecionado, mostrar dados consolidados e comparação entre as três represas conforme a referência `02-visao-geral-todas-as-represas.png`.
- Quando uma represa específica estiver selecionada, mostrar somente seus dados conforme `01-visao-geral-represa-selecionada.png`.
- Ao selecionar uma empresa, listar somente as represas pertencentes a ela.
- Se a represa atualmente selecionada não pertencer à nova empresa, redefinir a seleção de forma previsível.

### telas de monitoramento

- Cada tela deve possuir o filtro visível `Represa analisada`.
- O usuário precisa escolher uma represa específica para análises detalhadas.
- Não consolidar automaticamente métricas detalhadas quando `Todas as represas` estiver selecionado na Visão geral.
- Preserve a última represa escolhida durante a navegação entre as oito páginas.
- Armazene apenas IDs de contexto em `sessionStorage` ou parâmetros de URL; não armazene dados sensíveis.
- Atualize cards, gráficos, tabelas, status e mapa ao trocar a represa.
- Exiba skeleton/loading durante a busca.
- Cancele requisições anteriores com `AbortController` quando o usuário alterar filtros rapidamente.
- Em erro, mantenha a estrutura da tela e ofereça `Tentar novamente`.

## visão geral

Implemente as duas situações da referência.

Quando uma represa estiver selecionada:

- KPIs de nível atual, volume armazenado, vazão, precipitação e situação;
- gráfico de nível;
- localização da represa;
- alertas recentes;
- relatórios recentes.

Quando `Todas as represas` estiver selecionado:

- seis KPIs consolidados;
- barras horizontais comparando o nível das represas;
- linha de vazão consolidada dos últimos sete dias;
- donut da situação geral;
- contagem de alertas;
- tabela `Resumo de todas as represas`;
- mapa com os três marcadores;
- alertas prioritários.

Mantenha a regra visual de nível utilizada na referência consolidada:

- normal: até 80%;
- atenção: de 80% a 90%;
- crítico: acima de 90%.

Não dependa somente de cor. Exiba texto e ícone de status.

## monitoramento: volume de vazão

Reproduza `01-volume-de-vazao.png`:

- KPIs de vazão atual, afluência, defluência e saldo hídrico;
- linha dupla de afluência e defluência nas últimas 24 horas;
- medidor semicircular de condição;
- barras de média diária dos últimos sete dias;
- lista de sensores;
- tabela de últimas leituras.

Unidade principal: `m³/s`. Tooltips devem apresentar hora, série, valor e unidade.

## monitoramento: nível do reservatório

Reproduza `02-nivel-do-reservatorio.png`:

- KPIs de nível atual, cota atual, variação diária e capacidade disponível;
- gráfico histórico do nível;
- linha tracejada de atenção;
- linha tracejada de nível crítico;
- visual de capacidade do reservatório;
- tendência para os próximos sete dias;
- faixas operacionais;
- tabela de leituras.

Use o plugin de anotação do Chart.js ou um plugin próprio para as linhas de limite. Não simule limites com elementos desalinhados sobre o canvas.

## monitoramento: pH

Reproduza `03-ph.png`:

- pH atual, mínimo, máximo e condição;
- linha das últimas 24 horas;
- faixa ideal translúcida de 6,5 a 8,5;
- medidor semicircular de 0 a 14, com marcador em 7;
- médias dos últimos sete dias;
- pontos de coleta;
- últimas leituras.

O pH não possui unidade. Use vírgula na apresentação em português e número normalizado no JSON.

## monitoramento: volume armazenado

Reproduza `04-volume-armazenado.png`:

- volume atual, capacidade total, ocupação e volume disponível;
- gráfico de área dos últimos 30 dias;
- linha de capacidade máxima;
- indicador circular de ocupação;
- balanço hídrico diário com entrada e saída;
- distribuição da capacidade;
- histórico de volume;
- insight de ganho acumulado.

Unidade: `hm³`.

## monitoramento: precipitação

Reproduza `05-precipitacao.png`:

- precipitação em 24 horas;
- acumulado de sete dias;
- acumulado mensal;
- intensidade atual;
- barras diárias mais linha de acumulado;
- condição atual;
- distribuição na bacia;
- previsão de cinco dias;
- estações pluviométricas;
- aviso meteorológico.

Unidade: `mm`.

## monitoramento: previsão de duração da água

Reproduza `06-previsao-duracao-agua.png`:

- duração estimada;
- volume útil;
- consumo médio;
- confiabilidade da previsão;
- projeção para 90 dias;
- cenário de consumo atual;
- cenário de consumo elevado;
- cenário de economia de 10%;
- linha de reserva técnica;
- estimativa atual;
- cards comparativos dos cenários;
- fatores considerados;
- histórico das estimativas;
- insight do ganho possível com economia.

A projeção é demonstrativa. Organize a função de cálculo em um service separado para que o back-end possa substituir o algoritmo posteriormente.

## monitoramento: situação operacional

Reproduza `07-situacao-operacional.png`:

- situação geral;
- sensores online;
- comportas operacionais;
- alertas ativos;
- visão dos sistemas conectados;
- disponibilidade geral;
- disponibilidade de telemetria, comunicação e energia;
- status dos componentes;
- eventos recentes;
- próximas manutenções.

O botão `Abrir chamado` pode funcionar em modo de demonstração, abrindo um modal e mantendo o registro somente durante a sessão. Identifique claramente no código que a persistência será implementada depois.

## monitoramento: comparativo de vazão

Reproduza `08-comparativo-vazao.png`:

- seletores independentes de período atual e período anterior;
- vazão média atual, anterior, variação e maior diferença;
- linhas comparando os dois períodos;
- barras horizontais de diferença por dia;
- valores positivos em verde e negativos em âmbar;
- afluência versus defluência;
- resumo da comparação;
- tabela diária;
- insight final.

Não permita intervalos inválidos e apresente mensagem clara ao usuário.

## relatórios

Reproduza a referência de Relatórios:

- cards de resumo;
- filtros por represa, período, tipo e status;
- tabela de histórico;
- relatórios agendados;
- ações visuais de gerar e baixar;
- formatos PDF e CSV indicados na interface.

Sem banco, use os dados simulados. O download CSV pode ser funcional no navegador. Para PDF, implemente uma versão simples imprimível ou deixe a ação em modo demonstrativo com mensagem honesta; não crie uma falsa integração.

## níveis

Reproduza a referência de Níveis como visão histórica ampla:

- indicadores gerais;
- histórico do nível;
- capacidade e faixas;
- tendência;
- registros;
- comparação mensal;
- limites configurados.

Reutilize os mesmos dados e funções da página detalhada de nível. Não mantenha duas fontes de dados diferentes.

## mapas

Implemente um mapa real com Leaflet e tiles do OpenStreetMap:

- marcadores para todas as represas permitidas pela empresa selecionada;
- cor ou ícone conforme Normal, Atenção ou Crítico;
- popup com nome, localização, nível, vazão e situação;
- zoom automático para enquadrar os marcadores;
- seleção de represa pelo marcador;
- painel lateral da represa selecionada;
- controles de zoom;
- legenda de status;
- fallback visual claro quando os tiles não carregarem ou o computador estiver sem internet.

As coordenadas virão inicialmente da API simulada. Não acesse o banco diretamente pelo navegador. Não use Google Maps nem peça chave paga nesta etapa.

## alertas

Reproduza a central de alertas:

- cards de contagem;
- filtros por represa, prioridade e status;
- lista/tabela de alertas;
- painel de detalhes;
- linha do tempo;
- ações de reconhecer e resolver;
- configurações de notificação;
- gráfico de alertas dos últimos sete dias.

Enquanto não houver banco, ações de reconhecer/resolver podem persistir somente em `sessionStorage`, marcadas como comportamento demonstrativo. Não finja persistência permanente.

## configurações

Reproduza a referência de Configurações:

- abas Geral, Empresas e represas, Usuários e permissões, Limites e alertas, Notificações e Segurança;
- lista de empresas;
- represas vinculadas;
- preferências de unidade;
- intervalo de atualização;
- indicadores habilitados;
- limites principais.

Nesta etapa, os campos podem ser editados e preservados somente durante a sessão. Mostre feedback de sucesso, mas deixe claro no código e na documentação que a persistência definitiva depende do banco.

## Chart.js e padrão dos gráficos

Use Chart.js 4. Se for viável, mantenha a biblioteca em `assets/vendor` para o projeto funcionar no XAMPP sem depender de CDN. Se usar CDN, centralize a inclusão e documente a dependência.

Configuração visual compartilhada:

- azul principal: `#0B5BEA`;
- azul secundário: `#6EA8FE` ou equivalente ajustado à referência;
- azul de preenchimento com transparência baixa;
- verde: `#16A34A`;
- âmbar: `#F59E0B`;
- vermelho: `#EF4444`;
- grade: azul acinzentado com opacidade aproximada de 8% a 10%;
- texto dos eixos: tom secundário da interface;
- linhas principais com aproximadamente 2,5 px;
- `tension` em torno de 0,3 a 0,35;
- pontos pequenos e aumento no hover;
- barras com cantos arredondados;
- donuts sem bordas brancas excessivas e com `cutout` aproximado de 68% a 74%;
- animação curta e discreta;
- `responsive: true` e `maintainAspectRatio: false`;
- legenda externa ou inferior quando houver múltiplas séries;
- tooltips com título, valor formatado em português e unidade;
- nada de efeitos 3D, gradientes pesados ou cores neon.

Crie helpers compartilhados para:

- formatar números em `pt-BR`;
- acrescentar unidades;
- criar preenchimento em gradiente;
- gerar configuração de eixos;
- gerar tooltips;
- criar e destruir instâncias;
- atualizar datasets sem duplicar canvas;
- desenhar texto central em donuts;
- criar medidores semicirculares;
- criar linhas de limites/anotações.

Cada gráfico deve ficar dentro de um contêiner com altura definida. Destrua a instância anterior antes de recriá-la após mudança de filtro. Nenhuma navegação pode gerar o erro `Canvas is already in use`.

## atualização de dados

- Exiba `Atualizado há 2 min` ou valor vindo do `meta.generated_at`.
- Implemente atualização manual pelo ícone de recarregar.
- Implemente atualização automática simulada conforme a configuração, sem modificar aleatoriamente os dados.
- Pause atualizações automáticas quando a aba do navegador estiver oculta e retome quando voltar.
- Não faça polling agressivo. Cinco minutos é o padrão demonstrativo.
- Se uma resposta chegar depois que o usuário mudou a represa, descarte a resposta antiga.

## responsividade

As imagens são a referência desktop. Crie também comportamento adequado para:

- 1536 px e 1440 px: composição equivalente às referências;
- 1280 px: manter sidebar e reduzir colunas sem cortar conteúdo;
- 1024 px e tablet: sidebar recolhível e grades de duas colunas;
- 768 px: drawer lateral e cards reorganizados;
- 375 px: uma coluna, filtros empilhados, tabelas com rolagem horizontal controlada e gráficos legíveis.

Não reduza a fonte até ficar ilegível. Não permita rolagem horizontal na página inteira. Tabelas podem usar um wrapper próprio. No celular, mantenha a ordem: título, filtros, KPIs, visualizações principais e detalhes.

## acessibilidade

- Use HTML semântico.
- Todo botão deve ser realmente um `button` quando não navegar.
- Links devem ter destino real.
- Campos devem ter `label` associado.
- Ícones decorativos devem usar `aria-hidden="true"`.
- Botões somente com ícone devem possuir `aria-label`.
- Use foco visível consistente.
- Garanta contraste suficiente.
- Status não pode depender somente da cor.
- Para canvas, ofereça descrição textual curta ou tabela equivalente.
- Respeite `prefers-reduced-motion`.

## segurança e qualidade do PHP

- Escape toda saída dinâmica com `htmlspecialchars`.
- Valide parâmetros recebidos pela API.
- Não confie em IDs enviados pelo navegador.
- Não use `eval`.
- Não exponha diretórios internos, dados de sessão ou mensagens técnicas.
- Configure cookies de sessão com `HttpOnly` e `SameSite` compatíveis com o ambiente atual.
- Não habilite CORS aberto; front e API estarão na mesma origem.
- Não armazene segredos no repositório.
- Para ações POST simuladas, valide método, JSON e CSRF conforme o padrão existente.
- Mantenha compatibilidade com PHP 8.0.30.

## estados obrigatórios

Implemente e teste em todos os módulos relevantes:

- carregando;
- conteúdo carregado;
- sem dados para o período;
- erro de comunicação;
- sessão expirada;
- represa sem sensores;
- sensor offline;
- filtro inválido;
- mapa indisponível;
- ação demonstrativa sem persistência definitiva.

## documentação para as três pessoas da equipe

Crie ou atualize:

1. `docs/api-contract.md` com endpoints, parâmetros, respostas, erros e exemplos.
2. `docs/mock-data.md` explicando onde estão os dados simulados e como substituí-los.
3. `docs/database-handoff.md` explicando ao responsável pelo banco quais entidades e campos serão necessários, sem implementar banco agora.
4. `README.md` com execução no XAMPP, URL do projeto, login demonstrativo já existente, rotas do dashboard e dependências externas.

No handoff do banco, descreva pelo menos as entidades futuras, sem criar SQL:

- usuários;
- empresas;
- usuários por empresa e permissões;
- represas;
- sensores;
- leituras de nível;
- leituras de vazão;
- leituras de pH;
- leituras de precipitação;
- volumes armazenados;
- situações operacionais;
- alertas;
- relatórios;
- configurações e limites.

Para cada entidade, documente apenas os campos esperados pelo contrato atual, relacionamentos e quais endpoints a utilizarão.

## validação visual obrigatória

Depois da implementação:

1. Inicie o projeto no XAMPP ou no servidor PHP local compatível.
2. Abra cada rota do dashboard.
3. Capture ou inspecione cada página em 1536 × 1024.
4. Compare lado a lado com a referência correspondente.
5. Ajuste sidebar, largura dos cards, espaçamentos, fontes, cores, tamanhos dos gráficos e alinhamentos.
6. Não considere concluído se houver elementos cortados, desalinhados, sobrepostos ou ilegíveis.
7. Verifique 1440, 1280, 1024, 768 e 375 px.
8. Verifique console do navegador sem erros.
9. Verifique rede sem 404, 500 ou requisições duplicadas desnecessárias.
10. Teste teclado, submenu, filtros, troca de represa, troca de período, tooltips, atualização, mapa e logout.

## validação técnica obrigatória

- Execute lint de todos os arquivos PHP usando `C:\xampp\php\php.exe -l` no Windows, se `php` não estiver no PATH.
- Confirme HTTP 200 nas páginas autorizadas.
- Confirme redirecionamento para login quando não houver sessão.
- Confirme 401 JSON nos endpoints quando a sessão estiver ausente.
- Confirme que a landing page continua abrindo normalmente.
- Confirme que o login continua funcionando.
- Confirme que todas as referências permanecem intocadas.
- Confirme que nenhum arquivo SQL, migration ou conexão de banco foi criado.
- Confirme que os gráficos são canvas reais e o mapa é Leaflet real.
- Confirme que não há captura de tela usada como fundo do dashboard.

## critérios de aceite

O trabalho somente estará concluído quando:

- todas as 15 referências tiverem uma tela correspondente;
- as duas versões da Visão geral funcionarem pelo filtro;
- o submenu Monitoramento abrir e todas as oito opções navegarem;
- todas as páginas detalhadas tiverem seletor de represa;
- filtros atualizarem todos os componentes relacionados;
- gráficos estiverem funcionais e visualmente próximos às referências;
- o mapa real mostrar os marcadores simulados;
- a API PHP fornecer todos os dados simulados;
- o dashboard estiver protegido pela sessão existente;
- o código não depender de banco;
- a camada mock estiver isolada e pronta para troca;
- landing e login continuarem intactos;
- não houver erros PHP, JavaScript, HTTP ou responsivos;
- a documentação para front-end, back-end e banco estiver completa.

## relatório final esperado

Ao terminar, responda com:

1. resumo objetivo do que foi implementado;
2. estrutura final criada;
3. lista exata de arquivos criados e alterados;
4. rotas disponíveis;
5. endpoints da API;
6. como os dados simulados funcionam;
7. como o responsável pelo banco deverá substituir a camada mock;
8. dependências usadas;
9. verificações executadas e resultados;
10. limitações demonstrativas que ainda dependem do banco;
11. confirmação explícita de que landing e login foram preservados;
12. confirmação explícita de que não foi implementado banco de dados.

Não faça commit nem push sem autorização. Não apague arquivos existentes. Se encontrar alterações do usuário que não pertencem a esta etapa, preserve-as.
