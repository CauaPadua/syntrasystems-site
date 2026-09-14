<?php
/**
 * Aquapulse — conteúdo institucional da landing page.
 *
 * Centraliza os textos e a navegação para manter o markup enxuto e permitir
 * ajustes de copy sem mexer na estrutura HTML das seções.
 *
 * Incluído por index.php e login.php (não pelo dashboard).
 */

const AQ_SITE_NAME = 'Aquapulse';                                         // nome do produto usado em títulos, alt de imagens e rodapé
const AQ_SITE_TAGLINE = 'Monitoramento de represas';                      // slogan curto
const AQ_ASSETS = 'assets';                                               // pasta dos arquivos estáticos (relativa à raiz do site)

/** Itens do menu principal (âncoras internas da própria página). */
const AQ_NAV = [                                                          // usado no cabeçalho (header.php) e no rodapé (footer.php)
    ['label' => 'Início',      'href' => '#inicio'],                      // "#id" rola até o elemento com esse id na mesma página
    ['label' => 'Sobre',       'href' => '#sistema'],
    ['label' => 'Importância', 'href' => '#informacoes'],
    ['label' => 'Contato',     'href' => '#solicitar-demonstracao'],
];

/** Pontos de apoio da seção "Sistema". */
const AQ_SYSTEM_POINTS = [                                                // lista de benefícios exibida em includes/sections/sistema.php
    [
        'icon'  => 'grid',                                                // nome do ícone em includes/icons.php
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

/**
 * Escapa texto para saída segura em HTML.
 * Atenção: aqui aq_e() DEVOLVE a string; no dashboard (components.php) existe
 * outra aq_e() que IMPRIME. As duas nunca são carregadas na mesma página.
 */
function aq_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); // troca < > & " ' por entidades: impede injeção de HTML/JS (XSS)
}

/** Imprime texto escapado. */
function aq_out(?string $value): void
{
    echo aq_e($value);
}

/** Monta o caminho de um arquivo dentro de assets/. */
function aq_asset(string $path): string
{
    return AQ_ASSETS . '/' . ltrim($path, '/');                           // ltrim tira uma "/" inicial para não gerar "assets//css"
}
