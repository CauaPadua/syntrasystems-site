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
 *
 * Todos os métodos são estáticos: a classe não guarda estado, só aplica regras.
 * Usada por OverviewService, MonitoringService, pelo mapa e pelos alertas.
 */

declare(strict_types=1);

namespace Aquapulse\Services;

final class StatusRules
{
    public const LEVEL_ATTENTION = 80.0;                                  // a partir de 80% de ocupação a represa entra em "atenção"
    public const LEVEL_CRITICAL  = 90.0;                                  // acima de 90% passa a "crítico" (risco de vertimento)

    public const PH_MIN = 6.5;                                            // faixa de pH considerada adequada para a água: de 6,5...
    public const PH_MAX = 8.5;                                            // ...até 8,5 (fora disso, "atenção")

    /**
     * Classifica um nível percentual.
     *
     * @param float $levelPct ocupação da represa, de 0 a 100
     * @return string 'normal' | 'attention' | 'critical'
     */
    public static function fromLevel(float $levelPct): string
    {
        if ($levelPct > self::LEVEL_CRITICAL) {                           // estritamente acima de 90%: exatamente 90% ainda é "atenção"
            return 'critical';
        }
        if ($levelPct >= self::LEVEL_ATTENTION) {                         // de 80% (inclusive) até 90%
            return 'attention';
        }
        return 'normal';                                                  // abaixo de 80%
    }

    /** Classifica um valor de pH. */
    public static function fromPh(float $ph): string
    {
        return ($ph >= self::PH_MIN && $ph <= self::PH_MAX) ? 'normal' : 'attention'; // dentro da faixa 6,5–8,5 (inclusive) é normal; o pH nunca gera "crítico"
    }

    /**
     * Descreve um status para a interface: rótulo em português + ícone.
     *
     * O front-end usa `key` para escolher a classe CSS (cor), `label` para o texto
     * e `icon` para o nome do ícone — assim a informação não depende só da cor.
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
            case 'offline':                                               // sensor/estação sem comunicação
                return ['key' => 'offline', 'label' => 'Offline', 'icon' => 'wifi-off'];
            case 'normal':
            default:                                                      // qualquer valor desconhecido é tratado como normal
                return ['key' => 'normal', 'label' => 'Normal', 'icon' => 'check-circle'];
        }
    }

    /**
     * Frase que explica a situação operacional de uma represa.
     *
     * Fica aqui, junto das faixas, para que a mensagem nunca contradiga o
     * status exibido no mesmo cartão — era o caso de um texto fixo dizendo
     * "todas as condições dentro dos limites" mesmo com o nível em atenção.
     */
    public static function levelNote(string $status): string
    {
        switch ($status) {
            case 'critical':
                return 'Nível acima de ' . self::pct(self::LEVEL_CRITICAL) . '; ação imediata recomendada';   // "Nível acima de 90%; ..."
            case 'attention':
                return 'Nível acima de ' . self::pct(self::LEVEL_ATTENTION) . '; acompanhamento recomendado'; // "Nível acima de 80%; ..."
            default:
                return 'Todas as condições dentro dos limites';
        }
    }

    /** Formata um limite percentual sem casas decimais desnecessárias. */
    private static function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',') . '%'; // 80.0 -> "80,0" -> tira o "0" -> tira a "," -> "80%"; 82.5 -> "82,5%"
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
            case 'new':      return 'Novo';                               // alerta ainda não visto por ninguém
            case 'analysis': return 'Em análise';                         // alguém da equipe está tratando
            case 'resolved': return 'Resolvido';
            default:         return $status;                              // valor desconhecido é exibido como veio
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
