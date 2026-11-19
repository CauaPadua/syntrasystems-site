/*
 * Aquapulse — estação de telemetria com ESP32 (exemplo para hardware real)
 *
 * Faz com sensores de verdade o mesmo que estacao/estacao.php faz com os sensores
 * do simulador: lê, carimba com o horário, guarda na memória e envia o pacote JSON
 * para POST /api/v1/ingest/batch.php com o cabeçalho X-Api-Key. O servidor não
 * precisa de nenhuma mudança para receber os dados desta placa.
 *
 * Exemplo de referência: não foi testado em hardware. Os valores de calibração
 * (abaixo) precisam ser medidos no local.
 *
 * Material do protótipo:
 *   ESP32 DevKit
 *   JSN-SR04T   ultrassônico à prova d'água -> nível       (eco em 5 V: use divisor de tensão para 3,3 V)
 *   PH-4502C    módulo + sonda de pH        -> pH          (se alimentado em 5 V, use divisor na saída)
 *   DS18B20     termômetro à prova d'água   -> temperatura (resistor de 4,7 kΩ entre dados e 3,3 V)
 *   pluviômetro basculante (contato reed)  -> chuva
 * Vazão em barragens é medida com sensores Doppler de grande porte; no protótipo
 * ela fica de fora, mas entraria no pacote do mesmo jeito.
 *
 * Bibliotecas (Arduino IDE > Gerenciar bibliotecas): OneWire e DallasTemperature.
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <time.h>

/* ------------------------------------------------------------ configuração */
const char* WIFI_NOME  = "nome-da-rede";
const char* WIFI_SENHA = "senha-da-rede";
const char* SERVIDOR   = "http://192.168.0.10:8000/api/v1/ingest/batch.php";  // IP do computador com o site
const char* CHAVE_API  = "aquapulse-equipamento-local";                        // INGEST_API_KEY de backend/.env

// Códigos dos equipamentos no cadastro (tabela sensors).
const char* ID_NIVEL = "SEN-NIV-01";
const char* ID_PH    = "SEN-PH-01";
const char* ID_TEMP  = "SEN-TMP-01";
const char* ID_CHUVA = "SEN-PLU-01";
const char* ID_ESTACAO = "EST-RSC-001";
char ligadaEm[26] = "";  // horário em que a placa ligou (vai em cada envio: o servidor percebe sensor parado)

const unsigned long INTERVALO_MS = 5000;  // um ciclo a cada 5 s

/* ------------------------------------------------------------------ pinos */
const int PINO_TRIG  = 5;    // JSN-SR04T: disparo
const int PINO_ECHO  = 18;   // JSN-SR04T: eco
const int PINO_PH    = 34;   // saída analógica do PH-4502C
const int PINO_TEMP  = 4;    // DS18B20
const int PINO_CHUVA = 27;   // reed do pluviômetro, ligado ao GND

/* ------------------------------------------------- calibração (medir no local) */
const float ALTURA_SENSOR_CM = 300.0;  // do sensor até o fundo (represa vazia)
const float ALTURA_CHEIA_CM  = 250.0;  // altura da água com a represa a 100 %
const float PH_TENSAO_EM_7   = 2.50;   // tensão com a sonda na solução de pH 7
const float PH_VOLTS_POR_PH  = 0.18;   // inclinação (medir com a solução de pH 4)
const float MM_POR_PULSO     = 0.2;    // chuva de cada basculada

OneWire oneWire(PINO_TEMP);
DallasTemperature termometro(&oneWire);

/* ------------------------------------------- pluviômetro: conta os pulsos */
volatile unsigned long pulsosDeChuva = 0;
volatile unsigned long ultimoPulsoMs = 0;

void IRAM_ATTR contarPulso() {
  unsigned long agora = millis();
  if (agora - ultimoPulsoMs > 200) {  // ignora o "repique" do contato
    pulsosDeChuva++;
    ultimoPulsoMs = agora;
  }
}

/* ------------------------- memória: leituras esperando o servidor confirmar */
struct Leitura {
  const char* id;
  char quando[26];  // 2026-11-19T11:32:18-03:00
  float valor;
};
const int MEMORIA_MAXIMA = 300;
Leitura memoria[MEMORIA_MAXIMA];
int naMemoria = 0;

void guardar(const char* id, const char* quando, float valor) {
  if (isnan(valor)) return;  // sensor não respondeu: nada a guardar

  if (naMemoria == MEMORIA_MAXIMA) {  // cheia: descarta a mais antiga
    memmove(&memoria[0], &memoria[1], sizeof(Leitura) * (MEMORIA_MAXIMA - 1));
    naMemoria--;
  }
  memoria[naMemoria].id = id;
  strncpy(memoria[naMemoria].quando, quando, sizeof(memoria[naMemoria].quando));
  memoria[naMemoria].valor = valor;
  naMemoria++;
}

/* ---------------------------------------------------------------- sensores */
float lerNivel() {
  digitalWrite(PINO_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(PINO_TRIG, HIGH);
  delayMicroseconds(20);
  digitalWrite(PINO_TRIG, LOW);

  unsigned long eco = pulseIn(PINO_ECHO, HIGH, 30000);  // tempo de ida e volta (µs)
  if (eco == 0) return NAN;                             // sem eco: sensor com problema

  float distanciaCm = eco * 0.0343 / 2.0;               // velocidade do som: 0,0343 cm/µs
  float alturaDaAgua = ALTURA_SENSOR_CM - distanciaCm;
  return constrain(alturaDaAgua / ALTURA_CHEIA_CM * 100.0, 0.0, 100.0);
}

float lerPh() {
  long soma = 0;
  for (int i = 0; i < 20; i++) {  // média de 20 leituras reduz o ruído
    soma += analogRead(PINO_PH);
    delay(10);
  }
  float volts = soma / 20.0 / 4095.0 * 3.3;
  return 7.0 + (PH_TENSAO_EM_7 - volts) / PH_VOLTS_POR_PH;
}

float lerTemperatura() {
  termometro.requestTemperatures();
  float c = termometro.getTempCByIndex(0);
  return c == DEVICE_DISCONNECTED_C ? NAN : c;
}

float lerChuva() {
  noInterrupts();
  unsigned long pulsos = pulsosDeChuva;
  pulsosDeChuva = 0;
  interrupts();
  return pulsos * MM_POR_PULSO;
}

/* ---------------------------------------------------------- horário (NTP) */
bool horarioAtual(char* texto, size_t tamanho) {
  struct tm agora;
  if (!getLocalTime(&agora, 100)) return false;  // ainda não sincronizou
  strftime(texto, tamanho, "%Y-%m-%dT%H:%M:%S-03:00", &agora);
  return true;
}

/* ------------------------------------------------------------------ envio */
void transmitir() {
  if (naMemoria == 0) return;

  if (WiFi.status() != WL_CONNECTED) {
    Serial.printf("sem sinal: %d leituras guardadas na memoria\n", naMemoria);
    return;
  }

  int quantas = min(naMemoria, 50);  // pacotes pequenos cabem na RAM da placa
  String json = "{\"readings\":[";
  for (int i = 0; i < quantas; i++) {
    if (i > 0) json += ",";
    json += "{\"equipment_id\":\"" + String(memoria[i].id) +
            "\",\"recorded_at\":\"" + String(memoria[i].quando) +
            "\",\"value\":" + String(memoria[i].valor, 2) + "}";
  }
  json += "],\"station\":{\"id\":\"" + String(ID_ESTACAO) + "\",\"started_at\":\"" + String(ligadaEm) +
          "\",\"equipment\":[\"" + String(ID_NIVEL) + "\",\"" + String(ID_PH) + "\",\"" + String(ID_TEMP) +
          "\",\"" + String(ID_CHUVA) + "\"]}}";

  HTTPClient http;
  http.begin(SERVIDOR);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-Api-Key", CHAVE_API);
  int status = http.POST(json);
  String resposta = http.getString();
  http.end();

  // 200 = o servidor conferiu cada leitura (gravada ou recusada por erro dela).
  // Se alguma deu erro interno (status 500), a placa reenvia o pacote inteiro:
  // o banco recusa as repetidas, então reenviar nunca duplica dados.
  if (status == 200 && resposta.indexOf("\"status\":500") < 0) {
    memmove(&memoria[0], &memoria[quantas], sizeof(Leitura) * (naMemoria - quantas));
    naMemoria -= quantas;
    Serial.printf("enviado: %d leituras (%d ainda na memoria)\n", quantas, naMemoria);
  } else {
    Serial.printf("falha no envio (HTTP %d): %d leituras continuam na memoria\n", status, naMemoria);
  }
}

/* ------------------------------------------------------------------ início */
void setup() {
  Serial.begin(115200);

  pinMode(PINO_TRIG, OUTPUT);
  pinMode(PINO_ECHO, INPUT);
  pinMode(PINO_CHUVA, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(PINO_CHUVA), contarPulso, FALLING);
  analogReadResolution(12);
  termometro.begin();

  WiFi.begin(WIFI_NOME, WIFI_SENHA);
  configTime(-3 * 3600, 0, "a.st1.ntp.br", "pool.ntp.org");  // horário oficial, fuso de Brasília
}

void loop() {
  static unsigned long ultimoCiclo = 0;
  if (millis() - ultimoCiclo < INTERVALO_MS) return;
  ultimoCiclo = millis();

  char quando[26];
  if (!horarioAtual(quando, sizeof(quando))) {
    Serial.println("aguardando o horario (NTP)...");
    return;
  }
  if (ligadaEm[0] == '\0') {
    strncpy(ligadaEm, quando, sizeof(ligadaEm));  // primeiro horário válido = quando a estação ligou
  }

  guardar(ID_NIVEL, quando, lerNivel());
  guardar(ID_PH,    quando, lerPh());
  guardar(ID_TEMP,  quando, lerTemperatura());
  guardar(ID_CHUVA, quando, lerChuva());

  transmitir();
}
