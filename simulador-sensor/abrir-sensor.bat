@echo off
rem Liga o simulador de sensores e abre a tela no navegador.
rem O iniciar.bat ja chama este arquivo; ele tambem funciona sozinho.
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
if "%SENSOR_PORT%"=="" set SENSOR_PORT=8100
if "%PORT%"=="" set PORT=8000
if "%AQUAPULSE_URL%"=="" set AQUAPULSE_URL=http://localhost:%PORT%

if not exist "%PHP%" (
  echo XAMPP nao encontrado em C:\xampp. Ajuste a linha PHP no inicio deste arquivo.
  pause
  exit /b 1
)

rem Se ja estiver ligado em outra janela, so abre a tela.
netstat -ano | findstr /R /C:":%SENSOR_PORT% .*LISTENING" >nul
if not errorlevel 1 (
  echo O simulador de sensores ja esta ligado. Abrindo a tela...
  start "" http://localhost:%SENSOR_PORT%/
  timeout /t 3 >nul
  exit /b 0
)

rem Os sensores leem o cadastro e os ultimos valores do sistema: espera o site (ate 10 s).
echo Aguardando o site em %AQUAPULSE_URL% ...
"%PHP%" -r "for ($i = 0; $i < 40 && !@fsockopen('127.0.0.1', %PORT%, $n, $m, 1); $i++) usleep(250000);"

rem Cada vez que o simulador e ligado, os sensores comecam do zero, com os ultimos valores do banco.
if exist dados\estado.json del dados\estado.json

echo.
echo Simulador de sensores no ar: http://localhost:%SENSOR_PORT%/
echo A estacao consulta:          http://localhost:%SENSOR_PORT%/api/leituras
echo Feche esta janela para desligar os sensores.
echo.

start "" http://localhost:%SENSOR_PORT%/
"%PHP%" -S localhost:%SENSOR_PORT% router.php
pause
