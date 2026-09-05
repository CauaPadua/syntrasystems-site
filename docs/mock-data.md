# Dados simulados do sistema interno

Esta etapa **não implementa banco de dados**. Todo o sistema interno é
alimentado por uma fonte de dados simulada, determinística e somente leitura.

---

## Onde os dados ficam

```
backend/storage/mock/monitoring.php     ← única fonte de verdade
backend/src/Repositories/Mock/MockMonitoringRepository.php   ← lê e deriva
backend/src/Contracts/MonitoringRepositoryInterface.php      ← o contrato
```

`backend/` fica fora da pasta pública e é bloqueada por `.htaccess`: o arquivo
de dados não é acessível pelo navegador.

`monitoring.php` é um `return [...]` PHP puro com as chaves:

| Chave | Conteúdo |
| --- | --- |
| `companies` | duas empresas (`hidrovale`, `aguas-do-norte`) |
| `reservoirs` | três represas com todos os valores âncora |
| `sensors` | sensores por represa (id, tipo, local, status) |
| `ph_points` | pontos de coleta de pH |
| `rain_stations` | estações pluviométricas |
| `alerts` | cinco ocorrências (uma já resolvida) |
| `reports` | oito relatórios em três status |
| `scheduled_reports` | quatro agendamentos |
| `operation_events` | eventos operacionais recentes |
| `maintenances` | manutenções programadas |
| `settings` | preferências, limites, indicadores e canais |

---

## Valores âncora das represas

Todos os cartões, gráficos e tabelas derivam destes números. Nenhum valor é
recalculado de forma divergente em outro ponto do sistema.

| Represa | Nível | Volume | Capacidade | Vazão | pH | Chuva 24h | Duração | Situação |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Santa Clara (RSC-001) | 82,4 % | 1.234 hm³ | 1.500 hm³ | 56,2 m³/s | 7,2 | 18,6 mm | 84 dias | Atenção |
| Rio Verde (RRV-002) | 76,1 % | 980 hm³ | 1.288 hm³ | 43,8 m³/s | 7,4 | 12,3 mm | 96 dias | Normal |
| Serra Azul (RSA-003) | 77,3 % | 740 hm³ | 957 hm³ | 32,5 m³/s | 7,3 | 8,7 mm | 91 dias | Normal |

Consolidado (as três represas): **3 monitoradas / 3 online**, **2.954 hm³**,
**78,6 %** de nível médio, **132,5 m³/s** de vazão total, **pH 7,3**,
**2 normais + 1 em atenção**.

---

## Como as séries são geradas

O repositório não guarda séries prontas: ele as **deriva** dos valores âncora
com uma função determinística (`wave()`), semeada pelo `crc32` do identificador
da represa e da métrica.

```php
$osc = sin(($i * 0.9) + $seed * 6.283) * $amplitude
     + sin(($i * 0.37) + $seed * 3.14) * ($amplitude * 0.45);
$osc *= (1 - $t * $t);          // a oscilação some perto do fim
...
$values[$count - 1] = round($end, $decimals);   // crava o KPI atual
```

Três consequências importantes:

1. **Nada é aleatório.** Não há `rand()`, `mt_rand()`, `shuffle()` nem `time()`
   em lugar algum. A mesma requisição devolve sempre a mesma resposta — apertar
   *Atualizar* não muda os números, como não mudaria com dados reais parados.
2. **Gráfico e cartão nunca se contradizem.** O último ponto de cada série é,
   por construção, o valor do KPI daquela métrica.
3. **A forma da curva é estável por represa.** Cada represa tem sua própria
   semente, então Santa Clara e Rio Verde têm desenhos diferentes e constantes.

---

## Relógio da demonstração

`backend/src/Support/Clock.php` fixa o instante da demonstração:

```php
public const DEMO_MODE    = true;
public const DEMO_INSTANT = '2024-05-22 09:30:00';   // America/Sao_Paulo
```

Todas as datas exibidas (topo da página, tabelas, alertas, agendamentos) saem
daí, o que mantém as telas idênticas às da especificação em qualquer dia.

Para voltar ao relógio real basta `DEMO_MODE = false`: `Clock::now()` passa a
usar a hora do servidor e **nenhum outro arquivo muda**.

O rótulo *"Atualizado há 2 min"* é calculado no servidor (`ApiResponse` →
`meta.updated_label`) justamente porque o relógio é fixo: um cálculo no
navegador compararia 2024 com a data real da máquina.

---

## O que é demonstrativo na interface

Estas ações existem para mostrar o fluxo completo, mas **não persistem** nada —
valem só para a sessão do navegador (`sessionStorage`) e estão sinalizadas na
própria tela com um aviso:

| Tela | Ação demonstrativa |
| --- | --- |
| Alertas | "Assumir alerta" e "Marcar como resolvido" |
| Relatórios | "Gerar novo relatório" (o PDF usa a impressão do navegador; o CSV é montado no próprio navegador) |
| Configurações | preferências, limites, adicionar empresa/represa, salvar |
| Operacional | "Abrir chamado" |

A previsão de duração da água (`DurationForecastService`) é um **algoritmo
demonstrativo** isolado num único serviço, com os métodos `estimateDays()` e
`project()` marcados para substituição pelo modelo real.
