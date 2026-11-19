@echo off
rem Aquapulse - liga o projeto inteiro com um duplo clique (XAMPP em C:\xampp).
rem   1. cria backend\.env se nao existir
rem   2. cria o banco aquapulse_dev, as tabelas e os dados de exemplo se ainda nao existirem
rem   3. completa o historico ate agora, se o projeto ficou dias desligado
rem   4. abre o simulador de sensores    (janela "Simulador de sensores", tela em http://localhost:8100)
rem   5. abre a estacao de telemetria    (janela "Estacao de telemetria": envia as leituras ao banco)
rem   6. liga o site em http://localhost:8000 (nesta janela) e abre o login
rem Antes: ligue o MySQL no painel do XAMPP.

cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
set MYSQL=C:\xampp\mysql\bin\mysql.exe
if "%PORT%"=="" set PORT=8000

if not exist "%PHP%" (
  echo XAMPP nao encontrado em C:\xampp. Ajuste PHP e MYSQL no inicio deste arquivo.
  pause & exit /b 1
)

if not exist backend\.env (
  echo Criando backend\.env ...
  (
    echo DB_HOST=127.0.0.1
    echo DB_PORT=3306
    echo DB_NAME=aquapulse_dev
    echo DB_USER=root
    echo DB_PASS=
    echo INGEST_API_KEY=aquapulse-equipamento-local
  ) > backend\.env
)

"%MYSQL%" -u root -e "USE aquapulse_dev" >nul 2>&1
if errorlevel 1 (
  echo Criando o banco aquapulse_dev ^(pode levar alguns segundos^) ...
  "%PHP%" backend\database\migrate.php install
  if errorlevel 1 (
    echo.
    echo Nao foi possivel criar o banco. O MySQL esta ligado no painel do XAMPP?
    pause & exit /b 1
  )
)

rem Se o projeto ficou dias desligado, o historico parou: completa ate agora (nao apaga nada).
"%PHP%" backend\database\migrate.php completar

rem Sensores e estacao, cada um na sua janela. O simulador espera o site subir para ler o cadastro.
start "Simulador de sensores" /d "%~dp0simulador-sensor" cmd /c abrir-sensor.bat
start "Estacao de telemetria" cmd /k "%PHP%" estacao\estacao.php --url=http://localhost:%PORT%

echo.
echo Site no ar: http://localhost:%PORT%/login.php   ^(feche esta janela para desligar o site^)
echo Contas: demo@aquapulse.local ^(administrador^), operador@aquapulse.local, suspenso@aquapulse.local
echo Sensores: janela "Simulador de sensores", tela em http://localhost:8100
echo Estacao:  janela "Estacao de telemetria" ^(so pode haver uma ligada^)
start "" http://localhost:%PORT%/login.php
"%PHP%" -S localhost:%PORT% -t .
