<?php
/**
 * Aquapulse — regras de classificação de status.
 *
 * Centraliza a faixa visual usada em todo o sistema para que cards, gráficos,
 * tabelas e mapa nunca classifiquem o mesmo valor de formas diferentes.
 *
 * Faixa de nível (conforme especificação da etapa):
 *   normal   : até 80%
 *   atenção  : de 80% a 90%
 *   crítico  : acima de 90%
 *
 * IMPORTANTE: o status nunca é comunicado só por cor — todo consumidor recebe
 * também `label` e `icon` para exibir texto e ícone.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

final class StatusRules
{
    public const LEVEL_ATTENTION = 80.0;
    public const LEVEL_CRITICAL  = 90.0;

    public const PH_MIN = 6.5;
    public const PH_MAX = 8.5;

    /** Classifica um nível percentual. */
    public static function fromLevel(float $levelPct): string
    {
        if ($levelPct > self::LEVEL_CRITICAL) {
            return 'critical';
        }
        if ($levelPct >= self::LEVEL_ATTENTION) {
            return 'attention';
        }
        return 'normal';
    }

    /** Classifica um valor de pH. */
    public static function fromPh(float $ph): string
    {
        return ($ph >= self::PH_MIN && $ph <= self::PH_MAX) ? 'normal' : 'attention';
    }

    /**
     * Descreve um status para a interface: rótulo em português + ícone.
     *
     * @return array{key:string,label:string,icon:string}
     */
    public static function describe(string $status): array
    {
        switch ($status) {
            case 'critical':
                return ['key' => 'critical', 'label' => 'Crítico', 'icon' => 'alert-circle'];
            case 'attention':
                return ['key' => 'attention', 'label' => 'Atenção', 'icon' => 'alert-triangle'];
            case 'info':
                return ['key' => 'info', 'label' => 'Informação', 'icon' => 'info'];
            case 'offline':
                return ['key' => 'offline', 'label' => 'Offline', 'icon' => 'wifi-off'];
            case 'normal':
            default:
                return ['key' => 'normal', 'label' => 'Normal', 'icon' => 'check-circle'];
        }
    }

    /** Rótulo em português para severidade de alerta. */
    public static function severityLabel(string $severity): string
    {
        switch ($severity) {
            case 'critical':  return 'Crítico';
            case 'attention': return 'Atenção';
            case 'info':      return 'Informação';
            default:          return 'Normal';
        }
    }

    /** Rótulo em português para status de alerta. */
    public static function alertStatusLabel(string $status): string
    {
        switch ($status) {
            case 'new':      return 'Novo';
            case 'analysis': return 'Em análise';
            case 'resolved': return 'Resolvido';
            default:         return $status;
        }
    }

    /** Rótulo em português para status de relatório. */
    public static function reportStatusLabel(string $status): string
    {
        switch ($status) {
            case 'done':       return 'Concluído';
            case 'processing': return 'Processando';
            case 'scheduled':  return 'Agendado';
            default:           return $status;
        }
    }

    /** Rótulo em português para tipo de relatório. */
    public static function reportTypeLabel(string $type): string
    {
        switch ($type) {
            case 'operational':  return 'Operacional';
            case 'hydrological': return 'Hidrológico';
            case 'quality':      return 'Qualidade';
            case 'planning':     return 'Planejamento';
            default:             return $type;
        }
    }
}
