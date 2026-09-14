<?php
/**
 * Aquapulse — componentes reutilizáveis do sistema interno.
 *
 * Toda a repetição visual do dashboard vive aqui. As páginas chamam funções
 * com parâmetros em vez de duplicar HTML.
 *
 * Regra: toda saída dinâmica passa por htmlspecialchars (aq_h).
 *
 * Padrão usado pelos componentes: o PHP desenha a "casca" com marcadores
 * data-* (data-kpi, data-field, data-chart-summary, data-state...). O JavaScript
 * de cada tela encontra esses marcadores e preenche os valores vindos da API.
 * A maioria das funções RETORNA uma string de HTML; a página faz echo dela.
 */

declare(strict_types=1);

/**
 * Escapa para saída segura em HTML.
 * Converte < > & " ' em entidades, impedindo que um texto vindo de dados seja
 * interpretado como tag ou script (proteção contra XSS).
 */
function aq_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); // ENT_QUOTES escapa aspas simples e duplas; ENT_SUBSTITUTE troca bytes inválidos em vez de devolver vazio
}

/** Imprime texto escapado. */
function aq_e(?string $value): void
{
    echo aq_h($value);                                                    // atalho: escapa e imprime
}

/**
 * Ícone de dica (tooltip acessível).
 *
 * @param string $text texto da dica; vai em data-tip (lido pelo CSS/JS) e em aria-label (leitores de tela)
 */
function aq_tip(string $text): string
{
    return '<button class="aq-tip" type="button" data-tip="' . aq_h($text) . '"'
         . ' aria-label="' . aq_h($text) . '">' . aq_icon('info') . '</button>'; // é um <button> para poder receber foco pelo teclado
}

/**
 * Cabeçalho de card padronizado.
 *
 * @param array{title:string,icon?:string,tip?:string,sub?:string,actions?:string} $o
 *        actions = HTML já pronto (ex.: seletor de período); NÃO é escapado, por isso só recebe HTML gerado pelo próprio sistema
 */
function aq_card_head(array $o): string
{
    $html  = '<div class="aq-card__head"><div class="aq-card__title">';
    if (!empty($o['icon'])) {                                             // ícone opcional antes do título
        $html .= aq_icon($o['icon']);
    }
    $html .= '<span>' . aq_h($o['title']) . '</span>';
    if (!empty($o['tip'])) {                                              // dica opcional ao lado do título
        $html .= aq_tip($o['tip']);
    }
    $html .= '</div>';
    if (!empty($o['actions'])) {                                          // área de ações à direita do título
        $html .= '<div class="aq-card__actions">' . $o['actions'] . '</div>';
    }
    $html .= '</div>';
    if (!empty($o['sub'])) {                                              // subtítulo opcional abaixo do cabeçalho
        $html .= '<p class="aq-card__sub">' . aq_h($o['sub']) . '</p>';
    }
    return $html;
}

/**
 * Card de KPI. Os valores são preenchidos pelo JavaScript via data-field.
 *
 * Exemplo: id "level" gera spans com data-field="level.value", "level.unit",
 * "level.foot" — o JS escreve neles os campos kpis.level.* da resposta da API.
 *
 * @param array{
 *   id:string, label:string, icon:string, tone?:string, unit?:string,
 *   tip?:string, foot?:string, ring?:bool, badge?:bool
 * } $o
 *   tone  = variação de cor do ícone; ring = mostra anel de porcentagem; badge = espaço para badge de status
 */
function aq_kpi(array $o): string
{
    $tone = $o['tone'] ?? '';
    $toneClass = $tone !== '' ? ' aq-kpi__icon--' . $tone : '';          // ex.: " aq-kpi__icon--green"

    $html  = '<article class="aq-card aq-kpi" data-kpi="' . aq_h($o['id']) . '">';
    $html .= '<div class="aq-kpi__head">';
    $html .= '<span class="aq-kpi__icon' . $toneClass . '" aria-hidden="true">' . aq_icon($o['icon']) . '</span>'; // ícone decorativo: escondido de leitores de tela
    $html .= '<div class="aq-kpi__body">';

    $html .= '<h3 class="aq-kpi__label">' . aq_h($o['label']);
    if (!empty($o['tip'])) {
        $html .= aq_tip($o['tip']);
    }
    $html .= '</h3>';

    $html .= '<p class="aq-kpi__value"><span data-field="' . aq_h($o['id']) . '.value">—</span>'; // "—" aparece até o JavaScript receber o valor
    if (!empty($o['unit'])) {
        $html .= '<span class="aq-kpi__unit" data-field="' . aq_h($o['id']) . '.unit">' . aq_h($o['unit']) . '</span>';
    }
    $html .= '</p>';

    $html .= '</div>';

    $html .= '</div>';

    $html .= '<div class="aq-kpi__foot">'
           . '<span data-field="' . aq_h($o['id']) . '.foot">' . aq_h($o['foot'] ?? '') . '</span>'; // nota de rodapé do card (ex.: "Média nas últimas 24h")
    if (!empty($o['badge'])) {
        $html .= '<span data-field="' . aq_h($o['id']) . '.badge"></span>';
    }

    // O anel fica no rodapé, ao lado da nota — no cabeçalho ele disputaria a
    // largura com o valor e o texto acabava por baixo do anel em telas densas.
    if (!empty($o['ring'])) {
        $html .= '<div class="aq-ring" data-ring="' . aq_h($o['id']) . '" aria-hidden="true">'
               . '<svg viewBox="0 0 46 46" width="46" height="46">'
               . '<circle class="aq-ring__track" cx="23" cy="23" r="19" fill="none" stroke-width="5"/>'   // trilho cinza do anel
               . '<circle class="aq-ring__fill" cx="23" cy="23" r="19" fill="none" stroke-width="5"'     // arco colorido que representa a porcentagem
               . ' stroke-dasharray="119.4" stroke-dashoffset="119.4"/></svg>'                           // 119,4 ≈ 2π×19 (circunferência): offset igual ao total = anel vazio; o JS diminui o offset
               . '<span class="aq-ring__text" data-field="' . aq_h($o['id']) . '.ring"></span></div>';
    }

    $html .= '</div>';

    $html .= '</article>';
    return $html;
}

/**
 * Contêiner de gráfico com altura definida e descrição textual acessível.
 *
 * @param array{id:string,size?:string,desc?:string,axis?:string} $o
 *        id   = id do <canvas>, usado por charts.js para desenhar
 *        size = sm | md | lg (altura do gráfico, definida no CSS)
 *        desc = descrição do gráfico para leitores de tela
 *        axis = rótulo do eixo exibido acima do gráfico (ex.: "m³/s")
 */
function aq_chart(array $o): string
{
    $size = $o['size'] ?? 'md';
    $html = '';

    if (!empty($o['axis'])) {
        $html .= '<p class="aq-chart__axis">' . aq_h($o['axis']) . '</p>';
    }

    $html .= '<div class="aq-chart aq-chart--' . aq_h($size) . '">'        // o contêiner com altura fixa impede o gráfico de "crescer" indefinidamente
           . '<canvas id="' . aq_h($o['id']) . '" role="img"'              // role="img": o canvas é tratado como imagem pelos leitores de tela
           . ' aria-label="' . aq_h($o['desc'] ?? 'Gráfico de dados do monitoramento.') . '"></canvas>'
           . '</div>';

    // alternativa textual para leitores de tela
    $html .= '<p class="aq-visually-hidden" data-chart-summary="' . aq_h($o['id']) . '"></p>'; // o JS escreve aqui um resumo em texto dos dados do gráfico

    return $html;
}

/**
 * Legenda manual (usada quando a legenda do Chart.js é desligada).
 *
 * @param array<int,array{label:string,color:string,style?:string}> $items
 *        style = line (traço), square (quadrado) ou dashed (tracejado)
 * @param bool $plain versão sem fundo/borda
 */
function aq_legend(array $items, bool $plain = false): string
{
    $html = '<div class="aq-legend' . ($plain ? ' aq-legend--plain' : '') . '">';
    foreach ($items as $i) {                                              // um item de legenda por série do gráfico
        $style = $i['style'] ?? 'line';
        $class = 'aq-legend__key';
        if ($style === 'square') {
            $class .= ' aq-legend__key--square';
        } elseif ($style === 'dashed') {
            $class .= ' aq-legend__key--dashed';
        }
        $css = $style === 'dashed'
            ? 'color:' . aq_h($i['color'])                                // tracejado é desenhado com a cor do texto (borda currentColor no CSS)
            : 'background:' . aq_h($i['color']);                          // os demais são preenchidos com a cor da série
        $html .= '<span class="aq-legend__item"><span class="' . $class . '" style="' . $css . '" aria-hidden="true"></span>'
               . aq_h($i['label']) . '</span>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Os três estados obrigatórios de um bloco de dados:
 * carregando, sem dados e erro de comunicação.
 *
 * O JavaScript alterna a visibilidade conforme o resultado da requisição.
 *
 * @param string $scope nome do bloco; o JS encontra os estados por data-scope
 */
function aq_states(string $scope): string
{
    $s = aq_h($scope);

    return '<div class="aq-state" data-state="loading" data-scope="' . $s . '" hidden>'      // 1) carregando: esqueleto cinza animado
             . '<span class="aq-skeleton aq-skeleton--title"></span>'
             . '<span class="aq-skeleton aq-skeleton--text" style="width:80%"></span>'
             . '<span class="aq-skeleton aq-skeleton--text" style="width:60%"></span>'
             . '<span class="aq-visually-hidden" role="status">Carregando dados…</span>'   // anunciado a quem usa leitor de tela
           . '</div>'
         . '<div class="aq-state" data-state="empty" data-scope="' . $s . '" hidden>'         // 2) sem dados para os filtros escolhidos
             . '<span class="aq-state__icon" aria-hidden="true">' . aq_icon('table') . '</span>'
             . '<p class="aq-state__title">Sem dados para o período</p>'
             . '<p class="aq-state__text">Não há registros para os filtros selecionados. Ajuste o período ou a represa.</p>'
           . '</div>'
         . '<div class="aq-state aq-state--error" data-state="error" data-scope="' . $s . '" hidden>' // 3) erro ao falar com a API
             . '<span class="aq-state__icon" aria-hidden="true">' . aq_icon('alert-triangle') . '</span>'
             . '<p class="aq-state__title">Não foi possível carregar</p>'
             . '<p class="aq-state__text" data-error-message>Verifique sua conexão e tente novamente.</p>' // o JS troca pela mensagem vinda da API
             . '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-retry>'            // botão que repete a requisição
                 . aq_icon('refresh') . '<span>Tentar novamente</span></button>'
           . '</div>';
}

/**
 * Badge de status. `status` = normal | attention | critical | info | neutral.
 * Sempre acompanha texto — o status nunca depende só da cor.
 *
 * @param string $icon ícone específico; vazio usa o ícone padrão do status
 */
function aq_badge(string $label, string $status = 'normal', string $icon = ''): string
{
    $map = [                                                              // ícone padrão de cada status
        'normal'    => 'check-circle',
        'attention' => 'alert-triangle',
        'critical'  => 'alert-circle',
        'info'      => 'info',
    ];
    $ic = $icon !== '' ? $icon : ($map[$status] ?? '');                   // "neutral" não tem ícone

    return '<span class="aq-badge aq-badge--' . aq_h($status) . '">'      // a classe do status define a cor
         . ($ic !== '' ? aq_icon($ic) : '')
         . '<span>' . aq_h($label) . '</span></span>';
}

/**
 * Campo de seleção rotulado.
 *
 * @param array{id:string,label:string,options?:array<string,string>,value?:string,disabled?:bool} $o
 *        options = ['valor' => 'texto exibido']; value = opção marcada inicialmente
 */
function aq_select(array $o): string
{
    $id = aq_h($o['id']);
    $html = '<div class="aq-field">'
          . '<label class="aq-field__label" for="' . $id . '">' . aq_h($o['label']) . '</label>' // "for" liga o rótulo ao select (clicar no texto foca o campo)
          . '<select class="aq-select" id="' . $id . '" name="' . $id . '"'
          . (!empty($o['disabled']) ? ' disabled' : '') . '>';

    foreach ($o['options'] ?? [] as $value => $text) {                    // uma <option> por item
        $selected = (isset($o['value']) && (string) $value === $o['value']) ? ' selected' : ''; // marca a opção igual ao valor inicial
        $html .= '<option value="' . aq_h((string) $value) . '"' . $selected . '>' . aq_h($text) . '</option>';
    }

    $html .= '</select></div>';
    return $html;
}

/**
 * Seletor compacto de período, para o cabeçalho de um gráfico.
 *
 * Os valores seguem a allowlist da API (Validator::PERIODS); quem reage à
 * troca é o script da tela, que recarrega os dados pelo mesmo endpoint.
 */
function aq_period_picker(string $id, string $value = '7d'): string
{
    $options = ['24h' => '24 horas', '7d' => '7 dias', '30d' => '30 dias', '90d' => '90 dias'];

    $html = '<label class="aq-visually-hidden" for="' . aq_h($id) . '">Período do gráfico</label>' // rótulo invisível, mas lido por leitores de tela
          . '<select class="aq-select aq-select--sm" id="' . aq_h($id) . '" data-period-picker>';

    foreach ($options as $v => $text) {
        $selected = ((string) $v === $value) ? ' selected' : '';
        $html .= '<option value="' . aq_h((string) $v) . '"' . $selected . '>' . aq_h($text) . '</option>';
    }

    return $html . '</select>';
}

/** Abre um wrapper de tabela responsiva (rolagem própria, nunca da página). */
function aq_table_open(string $label): string
{
    return '<div class="aq-table-wrap" tabindex="0" role="region" aria-label="' . aq_h($label) . '">'; // tabindex="0" permite rolar a tabela pelo teclado
}

/** Fecha o wrapper aberto por aq_table_open(). */
function aq_table_close(): string
{
    return '</div>';
}

/**
 * Barra de contexto das telas detalhadas de Monitoramento.
 *
 * Todas as oito telas usam a mesma estrutura: represa analisada, código,
 * indicador de telemetria e um seletor de período/horizonte.
 *
 * O seletor de represa sai vazio: monitor-page.js o preenche com as represas da API.
 *
 * @param array{periods?:array<string,string>,period_label?:string,period_id?:string,period_value?:string} $o
 */
function aq_monitor_bar(array $o = []): string
{
    $periods = $o['periods'] ?? ['24h' => 'Últimas 24 horas', '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias']; // opções padrão de período
    $periodId = $o['period_id'] ?? 'filtro-periodo';

    $html  = '<section class="aq-context" aria-label="Contexto da análise">';
    $html .= aq_select(['id' => 'filtro-represa', 'label' => 'Represa analisada', 'options' => []]); // opções preenchidas pelo JavaScript

    $html .= '<div class="aq-field"><span class="aq-field__label">Código</span>'
           . '<strong style="font-size:1rem;line-height:42px" data-field="reservoir.code">—</strong></div>'; // código da represa (data.reservoir.code)

    $html .= '<div class="aq-field"><span class="aq-field__label">&nbsp;</span>'  // rótulo vazio só para alinhar com os outros campos
           . '<span class="aq-status-text" style="line-height:42px">'
           . '<span class="aq-dot aq-dot--normal" data-field-class="reservoir.telemetry"></span>' // bolinha colorida; o JS troca a classe conforme online/partial
           . '<span data-field="reservoir.telemetry">—</span></span></div>';

    $html .= '<span class="aq-context__spacer"></span>';                  // empurra o seletor de período para a direita

    $html .= aq_select([
        'id'      => $periodId,
        'label'   => $o['period_label'] ?? 'Período',
        'options' => $periods,
        'value'   => $o['period_value'] ?? null,
    ]);
    $html .= '</section>';
    return $html;
}
