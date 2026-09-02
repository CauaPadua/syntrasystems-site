<?php
/**
 * Aquapulse — biblioteca de ícones em SVG (traço linear, estilo Lucide).
 *
 * Todos os ícones são puramente decorativos: recebem aria-hidden="true" e
 * focusable="false" para não serem anunciados por leitores de tela. O
 * significado é sempre transmitido pelo texto que acompanha o ícone.
 */

/**
 * Devolve o markup SVG de um ícone.
 *
 * @param string $name  Identificador do ícone.
 * @param string $class Classes CSS adicionais aplicadas ao <svg>.
 */
function aq_icon(string $name, string $class = ''): string
{
    static $paths = [
        'shield-check'  => '<path d="M12 3 4.5 6v5.5c0 4.4 3.1 8.2 7.5 9.5 4.4-1.3 7.5-5.1 7.5-9.5V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
        'zap'           => '<path d="M13 2 4.5 13H11l-1 9 8.5-11H12l1-9Z"/>',
        'users'         => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7.5" r="3.5"/><path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16.5 4.13a4 4 0 0 1 0 6.74"/>',
        'chart-up'      => '<path d="M3 21h18"/><path d="M6 21v-6"/><path d="M11 21V9"/><path d="M16 21v-9"/><path d="M14 4h6v6"/><path d="M20 4l-7.5 7.5"/>',
        'chart-bars'    => '<path d="M3 21h18"/><path d="M7 21v-8"/><path d="M12 21V6"/><path d="M17 21v-5"/>',
        'scale'         => '<path d="M12 3v18"/><path d="M7 6.5h10"/><path d="M8 21h8"/><path d="m5 8-3 6h6L5 8Z"/><path d="m19 8-3 6h6l-3-6Z"/>',
        'droplet'       => '<path d="M12 3.5c3.2 3.3 5.5 6 5.5 8.9A5.5 5.5 0 0 1 12 18a5.5 5.5 0 0 1-5.5-5.6c0-2.9 2.3-5.6 5.5-8.9Z"/>',
        'waves'         => '<path d="M2 7.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/><path d="M2 13c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/><path d="M2 18.5c1.7-1.6 3.3-1.6 5 0s3.3 1.6 5 0 3.3-1.6 5 0 3.3 1.6 5 0"/>',
        'leaf'          => '<path d="M20 4c0 9-5.2 13.5-11 13.5A5 5 0 0 1 4 12.5C4 6.8 9.5 4 20 4Z"/><path d="M4.5 20C7 15 11 11.5 16 9.5"/>',
        'gear'          => '<circle cx="12" cy="12" r="3.2"/><path d="M19.4 14.5a1.6 1.6 0 0 0 .32 1.77l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.6 1.6 0 0 0-1.77-.32 1.6 1.6 0 0 0-.97 1.47V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1.05-1.47 1.6 1.6 0 0 0-1.77.32l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.6 1.6 0 0 0 .32-1.77 1.6 1.6 0 0 0-1.47-.97H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.47-1.05 1.6 1.6 0 0 0-.32-1.77l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.6 1.6 0 0 0 1.77.32H9a1.6 1.6 0 0 0 .97-1.47V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 .97 1.47 1.6 1.6 0 0 0 1.77-.32l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.6 1.6 0 0 0-.32 1.77V9a1.6 1.6 0 0 0 1.47.97H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5.97Z"/>',
        'grid'          => '<rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/>',
        'bell'          => '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5Z"/><path d="M13.7 19.5a2 2 0 0 1-3.4 0"/>',
        'clock'         => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/>',
        'signal'        => '<path d="M4.9 4.9a10 10 0 0 0 0 14.2"/><path d="M19.1 4.9a10 10 0 0 1 0 14.2"/><path d="M8 8a5.5 5.5 0 0 0 0 8"/><path d="M16 8a5.5 5.5 0 0 1 0 8"/><circle cx="12" cy="12" r="1.6"/>',
        'target'        => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/>',
        'info'          => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11.5v5"/><path d="M12 7.8h.01"/>',
        'sparkle'       => '<path d="M12 3.2 13.9 8l4.8 1.9-4.8 1.9L12 16.6l-1.9-4.8L5.3 9.9 10.1 8 12 3.2Z"/><path d="M18.5 16.2l.7 1.7 1.8.7-1.8.7-.7 1.7-.7-1.7-1.8-.7 1.8-.7.7-1.7Z"/>',
        'user'          => '<circle cx="12" cy="8" r="3.8"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'arrow-right'   => '<path d="M4.5 12h15"/><path d="m13.5 6 6 6-6 6"/>',
        'chevron-right' => '<path d="m9.5 5.5 6.5 6.5-6.5 6.5"/>',
        'menu'          => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'close'         => '<path d="m6 6 12 12"/><path d="m18 6-12 12"/>',
        'mail'          => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3.2 7.1 8 5.4a1.6 1.6 0 0 0 1.6 0l8-5.4"/>',
        'lock'          => '<rect x="4.5" y="10.5" width="15" height="9.5" rx="2.5"/><path d="M8 10.5V7.6a4 4 0 0 1 8 0v2.9"/>',
        'eye'           => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/>',
        'eye-off'       => '<path d="M10.7 6.1A9.4 9.4 0 0 1 12 6c6 0 9.5 6 9.5 6a17.6 17.6 0 0 1-2.7 3.5"/><path d="M6.5 6.8A17.3 17.3 0 0 0 2.5 12S6 18 12 18a9.3 9.3 0 0 0 4.3-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m3 3 18 18"/>',
        'log-in'        => '<path d="M14.5 3.5h3a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2h-3"/><path d="m10 16 4-4-4-4"/><path d="M14 12H4.5"/>',
        'log-out'       => '<path d="M9.5 3.5h-3a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h3"/><path d="m15 16 4-4-4-4"/><path d="M19 12H9.5"/>',
        'shield-lock'   => '<path d="M12 3 4.5 6v5.5c0 4.4 3.1 8.2 7.5 9.5 4.4-1.3 7.5-5.1 7.5-9.5V6L12 3Z"/><rect x="9.4" y="11.2" width="5.2" height="4.6" rx="1.1"/><path d="M10.6 11.2v-1.1a1.4 1.4 0 0 1 2.8 0v1.1"/>',
        'alert-circle'  => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.8v4.7"/><path d="M12 16.2h.01"/>',
        'check-circle'  => '<path d="M20.5 11.3V12a8.5 8.5 0 1 1-5-7.77"/><path d="m8.6 11.6 3 3 8.9-9"/>',
        'arrow-left'    => '<path d="M19.5 12h-15"/><path d="m10.5 6-6 6 6 6"/>',
    ];

    if (!isset($paths[$name])) {
        return '';
    }

    $classAttr = trim('aq-icon ' . $class);

    return '<svg class="' . htmlspecialchars($classAttr, ENT_QUOTES) . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
}

/** Imprime o ícone diretamente. */
function aq_the_icon(string $name, string $class = ''): void
{
    echo aq_icon($name, $class);
}
