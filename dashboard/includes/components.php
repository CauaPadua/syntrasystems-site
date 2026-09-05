<?php
/**
 * Aquapulse — componentes reutilizáveis do sistema interno.
 *
 * Toda a repetição visual do dashboard vive aqui. As páginas chamam funções
 * com parâmetros em vez de duplicar HTML.
 *
 * Regra: toda saída dinâmica passa por htmlspecialchars (aq_h).
 */

declare(strict_types=1);

/** Escapa para saída segura em HTML. */
function aq_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Imprime texto escapado. */
function aq_e(?string $value): void
{
    echo aq_h($value);
}

/**
 * Ícone de dica (tooltip acessível).
 */
function aq_tip(string $text): string
{
    return '<button class="aq-tip" type="button" data-tip="' . aq_h($text) . '"'
         . ' aria-label="' . aq_h($text) . '">' . aq_icon('info') . '</button>';
}

/**
 * Cabeçalho de card padronizado.
 *
 * @param array{title:string,icon?:string,tip?:string,sub?:string,actions?:string} $o
 */
function aq_card_head(array $o): string
{
    $html  = '<div class="aq-card__head"><div class="aq-card__title">';
    if (!empty($o['icon'])) {
        $html .= aq_icon($o['icon']);
    }
    $html .= '<span>' . aq_h($o['title']) . '</span>';
    if (!empty($o['tip'])) {
        $html .= aq_tip($o['tip']);
    }
    $html .= '</div>';
    if (!empty($o['actions'])) {
        $html .= '<div class="aq-card__actions">' . $o['actions'] . '</div>';
    }
    $html .= '</div>';
    if (!empty($o['sub'])) {
        $html .= '<p class="aq-card__sub">' . aq_h($o['sub']) . '</p>';
    }
    return $html;
}

/**
 * Card de KPI. Os valores são preenchidos pelo JavaScript via data-field.
 *
 * @param array{
 *   id:string, label:string, icon:string, tone?:string, unit?:string,
 *   tip?:string, foot?:string, ring?:bool, badge?:bool
 * } $o
 */
function aq_kpi(array $o): string
{
    $tone = $o['tone'] ?? '';
    $toneClass = $tone !== '' ? ' aq-kpi__icon--' . $tone : '';

    $html  = '<article class="aq-card aq-kpi" data-kpi="' . aq_h($o['id']) . '">';
    $html .= '<div class="aq-kpi__head">';
    $html .= '<span class="aq-kpi__icon' . $toneClass . '" aria-hidden="true">' . aq_icon($o['icon']) . '</span>';
    $html .= '<div class="aq-kpi__body">';

    $html .= '<h3 class="aq-kpi__label">' . aq_h($o['label']);
    if (!empty($o['tip'])) {
        $html .= aq_tip($o['tip']);
    }
    $html .= '</h3>';

    $html .= '<p class="aq-kpi__value"><span data-field="' . aq_h($o['id']) . '.value">—</span>';
    if (!empty($o['unit'])) {
        $html .= '<span class="aq-kpi__unit" data-field="' . aq_h($o['id']) . '.unit">' . aq_h($o['unit']) . '</span>';
    }
    $html .= '</p>';

    $html .= '</div>';

    $html .= '</div>';

    $html .= '<div class="aq-kpi__foot">'
           . '<span data-field="' . aq_h($o['id']) . '.foot">' . aq_h($o['foot'] ?? '') . '</span>';
    if (!empty($o['badge'])) {
        $html .= '<span data-field="' . aq_h($o['id']) . '.badge"></span>';
    }

    // O anel fica no rodapé, ao lado da nota — no cabeçalho ele disputaria a
    // largura com o valor e o texto acabava por baixo do anel em telas densas.
    if (!empty($o['ring'])) {
        $html .= '<div class="aq-ring" data-ring="' . aq_h($o['id']) . '" aria-hidden="true">'
               . '<svg viewBox="0 0 46 46" width="46" height="46">'
               . '<circle class="aq-ring__track" cx="23" cy="23" r="19" fill="none" stroke-width="5"/>'
               . '<circle class="aq-ring__fill" cx="23" cy="23" r="19" fill="none" stroke-width="5"'
               . ' stroke-dasharray="119.4" stroke-dashoffset="119.4"/></svg>'
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
 */
function aq_chart(array $o): string
{
    $size = $o['size'] ?? 'md';
    $html = '';

    if (!empty($o['axis'])) {
        $html .= '<p class="aq-chart__axis">' . aq_h($o['axis']) . '</p>';
    }

    $html .= '<div class="aq-chart aq-chart--' . aq_h($size) . '">'
           . '<canvas id="' . aq_h($o['id']) . '" role="img"'
           . ' aria-label="' . aq_h($o['desc'] ?? 'Gráfico de dados do monitoramento.') . '"></canvas>'
           . '</div>';

    // alternativa textual para leitores de tela
    $html .= '<p class="aq-visually-hidden" data-chart-summary="' . aq_h($o['id']) . '"></p>';

    return $html;
}

/**
 * Legenda manual (usada quando a legenda do Chart.js é desligada).
 *
 * @param array<int,array{label:string,color:string,style?:string}> $items
 */
function aq_legend(array $items, bool $plain = false): string
{
    $html = '<div class="aq-legend' . ($plain ? ' aq-legend--plain' : '') . '">';
    foreach ($items as $i) {
        $style = $i['style'] ?? 'line';
        $class = 'aq-legend__key';
        if ($style === 'square') {
            $class .= ' aq-legend__key--square';
        } elseif ($style === 'dashed') {
            $class .= ' aq-legend__key--dashed';
        }
        $css = $style === 'dashed'
            ? 'color:' . aq_h($i['color'])
            : 'background:' . aq_h($i['color']);
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
 */
function aq_states(string $scope): string
{
    $s = aq_h($scope);

    return '<div class="aq-state" data-state="loading" data-scope="' . $s . '" hidden>'
             . '<span class="aq-skeleton aq-skeleton--title"></span>'
             . '<span class="aq-skeleton aq-skeleton--text" style="width:80%"></span>'
             . '<span class="aq-skeleton aq-skeleton--text" style="width:60%"></span>'
             . '<span class="aq-visually-hidden" role="status">Carregando dados…</span>'
           . '</div>'
         . '<div class="aq-state" data-state="empty" data-scope="' . $s . '" hidden>'
             . '<span class="aq-state__icon" aria-hidden="true">' . aq_icon('table') . '</span>'
             . '<p class="aq-state__title">Sem dados para o período</p>'
             . '<p class="aq-state__text">Não há registros para os filtros selecionados. Ajuste o período ou a represa.</p>'
           . '</div>'
         . '<div class="aq-state aq-state--error" data-state="error" data-scope="' . $s . '" hidden>'
             . '<span class="aq-state__icon" aria-hidden="true">' . aq_icon('alert-triangle') . '</span>'
             . '<p class="aq-state__title">Não foi possível carregar</p>'
             . '<p class="aq-state__text" data-error-message>Verifique sua conexão e tente novamente.</p>'
             . '<button class="aq-btn aq-btn--ghost aq-btn--sm" type="button" data-retry>'
                 . aq_icon('refresh') . '<span>Tentar novamente</span></button>'
           . '</div>';
}

/**
 * Badge de status. `status` = normal | attention | critical | info | neutral.
 * Sempre acompanha texto — o status nunca depende só da cor.
 */
function aq_badge(string $label, string $status = 'normal', string $icon = ''): string
{
    $map = [
        'normal'    => 'check-circle',
        'attention' => 'alert-triangle',
        'critical'  => 'alert-circle',
        'info'      => 'info',
    ];
    $ic = $icon !== '' ? $icon : ($map[$status] ?? '');

    return '<span class="aq-badge aq-badge--' . aq_h($status) . '">'
         . ($ic !== '' ? aq_icon($ic) : '')
         . '<span>' . aq_h($label) . '</span></span>';
}

/**
 * Campo de seleção rotulado.
 *
 * @param array{id:string,label:string,options?:array<string,string>,value?:string,disabled?:bool} $o
 */
function aq_select(array $o): string
{
    $id = aq_h($o['id']);
    $html = '<div class="aq-field">'
          . '<label class="aq-field__label" for="' . $id . '">' . aq_h($o['label']) . '</label>'
          . '<select class="aq-select" id="' . $id . '" name="' . $id . '"'
          . (!empty($o['disabled']) ? ' disabled' : '') . '>';

    foreach ($o['options'] ?? [] as $value => $text) {
        $selected = (isset($o['value']) && (string) $value === $o['value']) ? ' selected' : '';
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

    $html = '<label class="aq-visually-hidden" for="' . aq_h($id) . '">Período do gráfico</label>'
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
    return '<div class="aq-table-wrap" tabindex="0" role="region" aria-label="' . aq_h($label) . '">';
}

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
 * @param array{periods?:array<string,string>,period_label?:string,period_id?:string,period_value?:string} $o
 */
function aq_monitor_bar(array $o = []): string
{
    $periods = $o['periods'] ?? ['24h' => 'Últimas 24 horas', '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias'];
    $periodId = $o['period_id'] ?? 'filtro-periodo';

    $html  = '<section class="aq-context" aria-label="Contexto da análise">';
    $html .= aq_select(['id' => 'filtro-represa', 'label' => 'Represa analisada', 'options' => []]);

    $html .= '<div class="aq-field"><span class="aq-field__label">Código</span>'
           . '<strong style="font-size:1rem;line-height:42px" data-field="reservoir.code">—</strong></div>';

    $html .= '<div class="aq-field"><span class="aq-field__label">&nbsp;</span>'
           . '<span class="aq-status-text" style="line-height:42px">'
           . '<span class="aq-dot aq-dot--normal" data-field-class="reservoir.telemetry"></span>'
           . '<span data-field="reservoir.telemetry">—</span></span></div>';

    $html .= '<span class="aq-context__spacer"></span>';

    $html .= aq_select([
        'id'      => $periodId,
        'label'   => $o['period_label'] ?? 'Período',
        'options' => $periods,
        'value'   => $o['period_value'] ?? null,
    ]);
    $html .= '</section>';
    return $html;
}
