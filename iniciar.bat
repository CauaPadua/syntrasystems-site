@echo off
rem Aquapulse - liga o projeto inteiro com um duplo clique (XAMPP em C:\xampp).
rem   1. cria backend\.env se nao existir
rem   2. cria o banco aquapulse_dev, as tabelas e os dados de exemplo se ainda nao existirem
rem   3. atualiza as tabelas, se esta versao do projeto trouxe novas, e completa o historico
rem      ate agora, se o projeto ficou dias desligado
rem   4. escolhe as portas: 8000 (site) e 8100 (sensores). Se uma estiver ocupada por outro
rem      programa, usa a proxima livre; se o Aquapulse ja estiver ligado, so abre as telas.
rem   5. abre a janela "Simulador de sensores" e a janela "Estacao de telemetria"
rem   6. liga o site nesta janela e abre a pagina inicial e o simulador no navegador
rem Antes: ligue o MySQL no painel do XAMPP (o Apache so e preciso para o phpMyAdmin).

title Aquapulse - site (NAO FECHE)
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
set MYSQL=C:\xampp\mysql\bin\mysql.exe

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

rem Banco criado por uma versao anterior do projeto: aplica so as tabelas que faltam.
"%PHP%" backend\database\migrate.php migrate
if errorlevel 1 (
  echo.
  echo Nao foi possivel atualizar as tabelas do banco. Copie a mensagem acima.
  pause & exit /b 1
)

rem Se o projeto ficou dias desligado, o historico parou: completa ate agora (nao apaga nada).
"%PHP%" backend\database\migrate.php completar

rem Escolhe as portas (nada que ja esta rodando no computador e encerrado).
"%PHP%" backend\portas.php "%TEMP%\aquapulse-portas.bat"
if errorlevel 1 ( pause & exit /b 1 )
call "%TEMP%\aquapulse-portas.bat"

rem Sensores e estacao, cada um na sua janela ("call" + caminho completo: funciona em qualquer pasta).
if "%LIGAR_SENSORES%"=="1" start "Simulador de sensores" /d "%~dp0simulador-sensor" cmd /c call "%~dp0simulador-sensor\abrir-sensor.bat"
if "%LIGAR_SENSORES%"=="0" start "" http://localhost:%SENSOR_PORT%/
if "%LIGAR_ESTACAO%"=="1" start "Estacao de telemetria" /d "%~dp0" cmd /k "%PHP%" estacao\estacao.php --url=http://localhost:%PORT% --sensores=http://localhost:%SENSOR_PORT%

echo.
echo ================================================================
echo  AQUAPULSE - DEIXE AS TRES JANELAS ABERTAS
echo   Site:            http://localhost:%PORT%/            (esta janela)
echo   Login e painel:  http://localhost:%PORT%/login.php
echo   Sensores:        http://localhost:%SENSOR_PORT%               (janela "Simulador de sensores")
echo   Estacao:         janela "Estacao de telemetria"
echo   Banco:           MySQL, banco aquapulse_dev  (phpMyAdmin: http://localhost/phpmyadmin)
echo  Contas: demo@aquapulse.local ^(administrador^), gestor@aquapulse.local, operador@aquapulse.local
echo  Fechar esta janela desliga o site.
echo ================================================================

rem O site ja estava ligado em outra janela: so abre a pagina inicial.
if "%LIGAR_SITE%"=="0" (
  start "" http://localhost:%PORT%/
  echo.
  echo O Aquapulse ja estava ligado: abri as telas. Esta janela fecha sozinha.
  timeout /t 10 >nul
  exit /b 0
)

rem Abre a pagina inicial assim que o site abaixo estiver no ar.
start "" /b "%PHP%" -r "$fim = time() + 15; while (time() < $fim && !@fsockopen('localhost', %PORT%, $n, $m, 1)) usleep(300000); exec('explorer http://localhost:%PORT%/');"
"%PHP%" -S localhost:%PORT% -t .
