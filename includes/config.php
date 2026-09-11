<?php
/**
 * Aquapulse — conteúdo institucional da landing page.
 *
 * Centraliza os textos e a navegação para manter o markup enxuto e permitir
 * ajustes de copy sem mexer na estrutura HTML das seções.
 */

const AQ_SITE_NAME = 'Aquapulse';
const AQ_SITE_TAGLINE = 'Monitoramento de represas';
const AQ_ASSETS = 'assets';

/** Itens do menu principal (âncoras internas da própria página). */
const AQ_NAV = [
    ['label' => 'Início',      'href' => '#inicio'],
    ['label' => 'Sobre',       'href' => '#sistema'],
    ['label' => 'Importância', 'href' => '#informacoes'],
    ['label' => 'Contato',     'href' => '#solicitar-demonstracao'],
];

/** Pontos de apoio da seção "Sistema". */
const AQ_SYSTEM_POINTS = [
    [
        'icon'  => 'grid',
        'title' => 'Dados organizados em uma visão unificada',
        'text'  => 'Todas as informações essenciais em um único lugar, com clareza e contexto.',
    ],
    [
        'icon'  => 'bell',
        'title' => 'Alertas que ajudam na tomada de decisão',
        'text'  => 'Notificações inteligentes que antecipam riscos e apoiam a resposta da equipe.',
    ],
    [
        'icon'  => 'shield-check',
        'title' => 'Informações confiáveis para equipes técnicas',
        'text'  => 'Dados precisos e atualizados que fortalecem o planejamento e a gestão da operação.',
    ],
];

/** Escapa texto para saída segura em HTML. */
function aq_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Imprime texto escapado. */
function aq_out(?string $value): void
{
    echo aq_e($value);
}

/** Monta o caminho de um arquivo dentro de assets/. */
function aq_asset(string $path): string
{
    return AQ_ASSETS . '/' . ltrim($path, '/');
}
