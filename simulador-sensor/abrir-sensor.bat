@echo off
rem Liga o simulador de sensores (porta 8100, ou a proxima livre) e abre a tela no navegador.
rem O iniciar.bat ja chama este arquivo; ele tambem funciona sozinho.
title Simulador de sensores - NAO FECHE
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
if "%PORT%"=="" set PORT=8000

if not exist "%PHP%" (
  echo XAMPP nao encontrado em C:\xampp. Ajuste a linha PHP no inicio deste arquivo.
  pause
  exit /b 1
)

rem Aberto sozinho (sem o iniciar.bat): escolhe a porta. Livre: usa. Ja e o simulador: so abre
rem a tela. Ocupada por outro programa: usa a proxima livre. Nada e encerrado.
if "%LIGAR_SENSORES%"=="" (
  "%PHP%" "%~dp0..\backend\portas.php" "%TEMP%\aquapulse-sensores.bat" sensores
  if errorlevel 1 ( pause & exit /b 1 )
  call "%TEMP%\aquapulse-sensores.bat"
)
if "%AQUAPULSE_URL%"=="" set AQUAPULSE_URL=http://localhost:%PORT%

if "%LIGAR_SENSORES%"=="0" (
  echo O simulador de sensores ja esta ligado. Abrindo a tela: http://localhost:%SENSOR_PORT%/
  start "" http://localhost:%SENSOR_PORT%/
  timeout /t 5 >nul
  exit /b 0
)

rem Os sensores leem o cadastro e os ultimos valores do sistema: espera o site responder (ate 15 s).
rem "localhost" (e nao 127.0.0.1): o servidor do PHP pode escutar so no endereco IPv6.
echo Aguardando o site em %AQUAPULSE_URL% ...
"%PHP%" -r "$fim = time() + 15; while (time() < $fim && !@fsockopen('localhost', %PORT%, $n, $m, 1)) usleep(300000);"

rem Cada vez que o simulador e ligado, os sensores recomecam dos ultimos valores do banco.
if exist dados\estado.json del dados\estado.json

echo.
echo ============================================================
echo  SIMULADOR DE SENSORES - DEIXE ESTA JANELA ABERTA
echo  Tela:            http://localhost:%SENSOR_PORT%/
echo  A estacao busca: http://localhost:%SENSOR_PORT%/api/leituras
echo  Fechar esta janela desliga os sensores.
echo ============================================================
echo.

rem Abre a tela assim que o servidor abaixo estiver no ar (antes disso o navegador daria erro).
start "" /b "%PHP%" -r "$fim = time() + 15; while (time() < $fim && !@fsockopen('localhost', %SENSOR_PORT%, $n, $m, 1)) usleep(300000); exec('explorer http://localhost:%SENSOR_PORT%/');"
"%PHP%" -S localhost:%SENSOR_PORT% router.php
pause
