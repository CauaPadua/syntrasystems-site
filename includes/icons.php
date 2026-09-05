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

        /* ---- ícones do sistema interno (dashboard) ---- */
        'home'          => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9.5V20h13V9.5"/><path d="M9.75 20v-5.5h4.5V20"/>',
        'activity'      => '<path d="M2.5 12h4l2.5-7 5 14 2.5-7h5"/>',
        'file-text'     => '<path d="M14 3.5H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5L14 3.5Z"/><path d="M14 3.5V9h5"/><path d="M9 13h6"/><path d="M9 16.5h6"/>',
        'layers-list'   => '<rect x="4" y="3.5" width="16" height="17" rx="2.5"/><path d="M8 8h8"/><path d="M8 12h8"/><path d="M8 16h5"/>',
        'map'           => '<path d="m3.5 6.5 5.5-2.5 6 2.5 5.5-2.5v13l-5.5 2.5-6-2.5-5.5 2.5Z"/><path d="M9 4v13"/><path d="M15 6.5v13"/>',
        'map-pin'       => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
        'chevron-down'  => '<path d="m6 9.5 6 6 6-6"/>',
        'chevron-up'    => '<path d="m6 14.5 6-6 6 6"/>',
        'refresh'       => '<path d="M20.5 11.5a8.5 8.5 0 0 0-14.6-5"/><path d="M3.5 12.5a8.5 8.5 0 0 0 14.6 5"/><path d="M3.5 5.5v5h5"/><path d="M20.5 18.5v-5h-5"/>',
        'calendar'      => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3v4"/><path d="M16 3v4"/><path d="M3.5 10h17"/>',
        'filter'        => '<path d="M3.5 5h17l-6.5 8v6l-4 2v-8L3.5 5Z"/>',
        'download'      => '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 20.5h15"/>',
        'more-vertical' => '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
        'external-link' => '<path d="M14 4.5h5.5V10"/><path d="M19.5 4.5 11 13"/><path d="M18 13.5v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2h5"/>',
        'search'        => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'plus'          => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'edit'          => '<path d="M4 20h4l10-10a2.5 2.5 0 0 0-3.5-3.5L4.5 16.5V20Z"/><path d="m13.5 7 3.5 3.5"/>',
        'save'          => '<path d="M5 4.5h11L19.5 8v11.5a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-14a1 1 0 0 1 1-1Z"/><path d="M8 4.5v5h7v-5"/><path d="M8 20.5v-6h8v6"/>',
        'alert-triangle'=> '<path d="M12 4 2.8 19.5h18.4L12 4Z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
        'wifi-off'      => '<path d="m3 3 18 18"/><path d="M12 18.5h.01"/><path d="M8.4 14.6a5 5 0 0 1 3-1.4"/><path d="M5 11.2a10 10 0 0 1 3.6-2.3"/><path d="M19 11.2a10 10 0 0 0-6.6-2.7"/>',
        'radio'         => '<circle cx="12" cy="12" r="1.8"/><path d="M8.5 8.5a5 5 0 0 0 0 7"/><path d="M15.5 15.5a5 5 0 0 0 0-7"/><path d="M5.8 5.8a9 9 0 0 0 0 12.4"/><path d="M18.2 18.2a9 9 0 0 0 0-12.4"/>',
        'gate'          => '<path d="M3.5 20.5V7l8.5-3.5L20.5 7v13.5"/><path d="M3.5 11h17"/><path d="M3.5 15.5h17"/><path d="M8.5 8.7v11.8"/><path d="M15.5 8.7v11.8"/>',
        'wrench'        => '<path d="M15.5 3.5a5 5 0 0 0-4.6 7l-7 7 2.6 2.6 7-7a5 5 0 0 0 6.4-6.4l-3 3-2.6-2.6 3-3a5 5 0 0 0-1.8-.6Z"/>',
        'cloud-rain'    => '<path d="M6.5 15.5a4 4 0 0 1 .6-8 5.5 5.5 0 0 1 10.5 1.6 3.5 3.5 0 0 1-.6 6.4"/><path d="M8.5 18v2.5"/><path d="M12 18.5v2.5"/><path d="M15.5 18v2.5"/>',
        'cloud-sun'     => '<circle cx="8" cy="7" r="2.6"/><path d="M8 2v1.6"/><path d="M8 10.4V12"/><path d="M3 7h1.6"/><path d="M11.4 7H13"/><path d="M10.5 17.5a3.5 3.5 0 0 1 .5-7 4.8 4.8 0 0 1 9.2 1.4 3 3 0 0 1-.7 5.6Z"/>',
        'box'           => '<path d="m12 3.5 8 4v9l-8 4-8-4v-9l8-4Z"/><path d="m4 7.5 8 4 8-4"/><path d="M12 11.5v9"/>',
        'arrow-down-circle' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 8v8"/><path d="m8.5 12.5 3.5 3.5 3.5-3.5"/>',
        'arrow-up-circle'   => '<circle cx="12" cy="12" r="8.5"/><path d="M12 16V8"/><path d="m8.5 11.5 3.5-3.5 3.5 3.5"/>',
        'gauge'         => '<path d="M20.5 15a8.5 8.5 0 1 0-17 0"/><path d="m12 12 4-3.5"/><circle cx="12" cy="12" r="1.4"/>',
        'ruler'         => '<rect x="4" y="3.5" width="8" height="17" rx="2"/><path d="M12 7H9"/><path d="M12 10.5h-2"/><path d="M12 14h-3"/><path d="M12 17.5h-2"/>',
        'building'      => '<path d="M4.5 20.5V5a1.5 1.5 0 0 1 1.5-1.5h7A1.5 1.5 0 0 1 14.5 5v15.5"/><path d="M14.5 9.5h4a1 1 0 0 1 1 1v10"/><path d="M8 7.5h3"/><path d="M8 11h3"/><path d="M8 14.5h3"/><path d="M3 20.5h18"/>',
        'shield'        => '<path d="M12 3 4.5 6v5.5c0 4.4 3.1 8.2 7.5 9.5 4.4-1.3 7.5-5.1 7.5-9.5V6L12 3Z"/>',
        'table'         => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17"/><path d="M9 9.5v10"/>',
        'locate'        => '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="7.5"/><path d="M12 2v2.5"/><path d="M12 19.5V22"/><path d="M2 12h2.5"/><path d="M19.5 12H22"/>',
        'layers'        => '<path d="m12 3.5 8.5 4.5L12 12.5 3.5 8Z"/><path d="m3.5 12.5 8.5 4.5 8.5-4.5"/><path d="m3.5 16.5 8.5 4.5 8.5-4.5"/>',
        'user-cog'      => '<circle cx="10" cy="8" r="3.5"/><path d="M3.5 20a6.5 6.5 0 0 1 9.5-5.8"/><circle cx="17.5" cy="17.5" r="2.5"/><path d="M17.5 13.8v1.2"/><path d="M17.5 20v1.2"/><path d="m14.9 16 1 .6"/><path d="m20.1 19 1 .6"/>',
        'minus'         => '<path d="M5 12h14"/>',
        'printer'       => '<path d="M7 9V4h10v5"/><rect x="4" y="9" width="16" height="7" rx="2"/><path d="M7 14h10v6H7z"/>',
        'trending-down' => '<path d="M14 20h6v-6"/><path d="m20 20-8.5-8.5-4 4L2.5 10"/>',
        'sliders'       => '<path d="M4 8h9"/><path d="M17 8h3"/><path d="M4 16h4"/><path d="M12 16h8"/><circle cx="15" cy="8" r="2"/><circle cx="10" cy="16" r="2"/>',
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
