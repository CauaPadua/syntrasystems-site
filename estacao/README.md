# Estação de telemetria

Em uma represa de verdade, os sensores ficam espalhados pela barragem e ligados por cabo
a uma **estação de telemetria** (*datalogger*). É a estação que lê os sensores, guarda as
leituras e as envia ao servidor por 4G, rádio ou satélite. Este programa faz esse papel.

```
 sensores                       estação de telemetria                     servidor
 (simulador-sensor/)            (estacao/)                                (site + banco)
 ──────────────────             ──────────────────────────                ─────────────────────
 nível, vazões, pH,  ──lê──▶    1. busca as leituras novas    ──envia──▶  API valida e grava
 temperatura, chuva             2. guarda na memória                      alertas automáticos
                                3. envia o pacote JSON                    painel
                                4. confere a resposta
```

| Peça real | No projeto |
| --- | --- |
| Os sensores e o cabo até a estação | [simulador de sensores](../simulador-sensor/README.md) e `src/LeitorDeSensores.php` |
| A estação (memória, coleta, envio) | `src/Estacao.php` |
| O modem (fala com o servidor) | `src/Api.php` |
| — | `estacao.php`: liga uma estação por represa e mostra tudo na janela |

O `iniciar.bat` liga a estação na janela **"Estação de telemetria"**. Só pode haver uma
ligada por vez: duas enviariam cada leitura duas vezes.

## O ciclo (a cada 5 s)

1. **Busca** no simulador de sensores as leituras novas (`GET /api/leituras?apos=N`).
   Cada uma vem com o código do equipamento e o **horário em que foi medida**.
2. **Guarda** na memória.
3. **Envia** tudo o que está na memória em um único pacote:

```http
POST /api/v1/ingest/batch.php
Content-Type: application/json
X-Api-Key: <chave das estações, INGEST_API_KEY em backend/.env>

{ "readings": [
    { "equipment_id": "SEN-NIV-01", "recorded_at": "2026-11-19T13:01:58-03:00", "value": 82.4 },
    { "equipment_id": "SEN-VAZ-01", "recorded_at": "2026-11-19T13:01:58-03:00", "value": 56.2 },
    { "equipment_id": "SEN-NIV-11", "recorded_at": "2026-11-19T13:02:03-03:00", "value": -5 }
  ],
  "station": { "id": "EST-RSC-001", "started_at": "2026-11-19T13:00:00-03:00",
               "equipment": ["SEN-NIV-01", "SEN-VAZ-01", "SEN-VAZ-02", "SEN-PH-01", "SEN-TMP-01", "SEN-PLU-01"] } }
```

`station` diz ao servidor quais sensores a estação lê e desde quando ela está ligada. Com
isso o servidor percebe um sensor que **parou**: se ficar 2 min sem leitura com a estação
ligada, abre o alerta "Equipamento sem leituras". Quando não há leituras novas, a estação
manda só o `station` (o aviso de "estou ligada"), e assim até uma represa com todos os
sensores parados vira alerta. Logo depois de ligar, ninguém é acusado: a contagem começa
quando a estação liga.

4. **Confere a resposta**, que vem leitura por leitura:

```json
{ "received": 3, "stored": 2, "rejected": 1,
  "results": [
    { "index": 0, "ok": true,  "classification": "attention", "alert_id": "ALT-1001" },
    { "index": 1, "ok": true,  "classification": "normal",    "alert_id": null },
    { "index": 2, "ok": false, "status": 422, "code": "VALUE_OUT_OF_RANGE", "message": "Valor fora da faixa..." } ],
  "communication_alerts": [] }
```

| Resposta | O que a estação faz |
| --- | --- |
| gravada | tira da memória |
| recusada (4xx: valor impossível, equipamento em manutenção...) | descarta e mostra **RECUSADA** na janela |
| repetida (já tinha chegado antes) | tira da memória |
| servidor fora do ar ou erro 5xx | **mantém na memória** e tenta de novo no próximo ciclo |

Para ver o pacote de verdade: `php estacao/estacao.php --represa=santa-clara --pacote`.

## A janela da estação

```
ESTAÇÃO      REPRESA        NÍVEL   ENTRADA     SAÍDA     pH   ÁGUA   CHUVA   ENVIO
                                %      m³/s      m³/s            °C      mm
-- ciclo 12 · 13:02:31
EST-RSC-001  Santa Clara     87,0      56,1      49,7   7,20   22,8     0,0   6 gravadas
     Nível do reservatório 87,0 % [CRÍTICO]  ->  alerta ALT-1001
EST-RRV-002  Rio Verde       -5,0      44,2      39,3   7,44   22,1     5,0   5 gravadas, 1 RECUSADA
     RECUSADA: SEN-NIV-11 = -5,00 -> 422 VALUE_OUT_OF_RANGE: Valor fora da faixa aceita...
EST-RSA-003  Serra Azul      69,3      30,6        ---   7,29   21,9     0,0   5 gravadas
     sem leitura: SEN-VAZ-22: sem resposta (falha no sensor)
     ALERTA ALT-1003: SEN-VAZ-22 está sem enviar leituras há mais de 2 min (Equipamento sem leituras)
```

`---` = o sensor não respondeu. As linhas de baixo mostram alertas, recusas e sensores
parados, no momento em que acontecem.

## Por que o dado que chega ao banco é confiável

- **Cada leitura identifica o equipamento.** A represa e a grandeza não vêm no envio:
  saem do cadastro (`sensors`). Um equipamento não consegue gravar dado de outra represa.
- **Só estações autorizadas enviam:** a API confere a chave `X-Api-Key`.
- **O horário é o da medição,** não o do envio. Leituras que ficaram na memória entram
  no lugar certo do gráfico.
- **A API valida tudo:** valor dentro da faixa física, equipamento ativo, horário válido
  (nem no futuro, nem mais velho que 7 dias).
- **Nada se perde e nada duplica:** sem comunicação, a estação guarda; quando volta,
  reenvia. O banco tem chave única (equipamento + grandeza + horário), então um reenvio
  nunca grava a mesma leitura duas vezes.
- **Falhas viram alerta:** um equipamento que fica 2 min sem nenhuma leitura aceita, com a
  estação ligada, ganha o alerta "Equipamento sem leituras".

## Falhas da própria estação

| Situação | O que acontece |
| --- | --- |
| Perda de sinal (`--queda-rede`: ciclos 4 a 11) | a estação mostra `SEM SINAL: N leituras guardadas na memória` e, quando o sinal volta, envia todas: o gráfico não fica com buraco |
| Site desligado | `FALHA (o servidor não respondeu)`, guarda tudo e envia quando o site voltar |
| Simulador de sensores desligado | `simulador de sensores fora do ar`, nenhuma leitura; quando ele volta, a estação reconecta sozinha e recebe também o que foi medido no intervalo |

## E com sensores de verdade?

`esp32/estacao_esp32.ino` é a mesma estação para uma placa **ESP32** com sensores reais
(ultrassônico de nível, sonda de pH, termômetro DS18B20 e pluviômetro). Ela monta o
mesmo pacote JSON e chama o mesmo endereço com a mesma chave: para trocar a estação
virtual por uma real, **o servidor não precisa de nenhuma mudança**. É um exemplo de
referência, não testado em hardware; a calibração precisa ser medida no local.

## Opções

```
php estacao/estacao.php --ajuda
  --url=URL           endereço do site                     (padrão: http://localhost:8000)
  --sensores=URL      endereço do simulador de sensores    (padrão: http://localhost:8100)
  --represa=ID        liga só a estação desta represa
  --equipamento=COD   só estes sensores
  --intervalo=SEG     segundos entre ciclos                (padrão: 5)
  --ciclos=N          para depois de N ciclos
  --queda-rede        simula a perda do sinal (ciclos 4 a 11)
  --pacote            mostra o JSON de cada envio
  --valor=X           com --equipamento: envia um valor escolhido e encerra
```
