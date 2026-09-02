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

/** Destaques exibidos abaixo do conteúdo principal da hero. */
const AQ_HERO_HIGHLIGHTS = [
    [
        'icon'  => 'shield-check',
        'title' => 'Mais segurança',
        'text'  => 'Alertas inteligentes para prevenir riscos e eventos críticos.',
    ],
    [
        'icon'  => 'chart-up',
        'title' => 'Decisões melhores',
        'text'  => 'Dados confiáveis para uma gestão eficiente e responsável.',
    ],
    [
        'icon'  => 'leaf',
        'title' => 'Sustentabilidade',
        'text'  => 'Preserve a água, proteja comunidades e garanta o futuro.',
    ],
];

/** Faixa de atributos no rodapé da hero. */
const AQ_HERO_BADGES = [
    ['icon' => 'signal',  'label' => 'Monitoramento contínuo'],
    ['icon' => 'clock',   'label' => 'Dados em tempo real'],
    ['icon' => 'bell',    'label' => 'Alertas inteligentes'],
    ['icon' => 'leaf',    'label' => 'Gestão sustentável'],
];

/** Cartões da seção "Informações". */
const AQ_INFO_CARDS = [
    [
        'icon'  => 'shield-check',
        'title' => 'Segurança e prevenção',
        'text'  => 'Identificar riscos com antecedência permite agir antes que problemas aconteçam, protegendo vidas, comunidades e o meio ambiente.',
    ],
    [
        'icon'  => 'gear',
        'title' => 'Eficiência operacional',
        'text'  => 'Acompanhe o comportamento da represa e otimize operações com base em dados confiáveis, reduzindo custos e aumentando a eficiência.',
    ],
    [
        'icon'  => 'leaf',
        'title' => 'Sustentabilidade',
        'text'  => 'Promova o uso responsável da água e contribua para a preservação dos recursos naturais para as próximas gerações.',
    ],
    [
        'icon'  => 'chart-up',
        'title' => 'Decisões estratégicas',
        'text'  => 'Informação de qualidade para planejamento, conformidade e governança, com confiança e transparência.',
    ],
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

/** Cartões da seção "Vantagens". */
const AQ_ADVANTAGES = [
    [
        'icon'  => 'shield-check',
        'title' => 'Mais segurança para operações',
        'text'  => 'Reduza riscos com dados contínuos e alertas precisos para decisões seguras e em tempo real.',
    ],
    [
        'icon'  => 'zap',
        'title' => 'Resposta mais rápida a cenários críticos',
        'text'  => 'Antecipe eventos, atue com agilidade e minimize impactos com informações confiáveis na hora certa.',
    ],
    [
        'icon'  => 'users',
        'title' => 'Mais confiança para equipes gestoras',
        'text'  => 'Tenha visibilidade da condição da represa e tome decisões com confiança e respaldo técnico.',
    ],
    [
        'icon'  => 'chart-bars',
        'title' => 'Melhor base para planejamento',
        'text'  => 'Transforme dados em inteligência para planejar manutenções, investir com eficiência e ampliar a resiliência.',
    ],
    [
        'icon'  => 'scale',
        'title' => 'Apoio à conformidade e governança',
        'text'  => 'Facilite auditorias, atenda exigências e fortaleça a governança com registros organizados e rastreáveis.',
    ],
    [
        'icon'  => 'droplet',
        'title' => 'Uso responsável dos recursos hídricos',
        'text'  => 'Promova a preservação dos recursos hídricos com uma operação eficiente e impacto positivo para a sociedade.',
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
