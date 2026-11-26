@echo off
setlocal EnableExtensions DisableDelayedExpansion
title Folio - Facturacion electronica
pushd "%~dp0" || goto :sin_carpeta

for %%I in (php.exe) do set "FOLIO_PHP=%%~$PATH:I"
if not defined FOLIO_PHP if exist "%SystemDrive%\xampp\php\php.exe" set "FOLIO_PHP=%SystemDrive%\xampp\php\php.exe"
if not defined FOLIO_PHP (
    echo No se encontro php.exe. Instala PHP 8.1 o superior, o agrega tu carpeta de PHP al PATH.
    goto :fallo
)
if not exist "router.php" goto :incompleto
if not exist "config.php" goto :incompleto
if not exist "wsdl\FacturacionService.wsdl" goto :incompleto

"%FOLIO_PHP%" -r "exit(PHP_VERSION_ID >= 80100 ? 0 : 1);"
if errorlevel 1 (
    echo Este proyecto requiere PHP 8.1 o superior. Ejecutable encontrado: "%FOLIO_PHP%"
    goto :fallo
)

rem Cargar solo los modulos que falten, sin editar el php.ini del equipo.
set "FOLIO_MODULOS="
for %%M in (soap openssl mbstring) do call :modulo %%M
set "FOLIO_EXTDIR="
call :requisitos >nul 2>&1
if not errorlevel 1 goto :configuracion
for %%I in ("%FOLIO_PHP%") do set FOLIO_EXTDIR=-d "extension_dir=%%~dpIext"
call :requisitos
if errorlevel 1 goto :fallo

:configuracion
set "FOLIO_HOST="
set "FOLIO_URL="
for %%I in ("%FOLIO_PHP%") do set "PATH=%%~dpI;%PATH%"
for /f "tokens=1,2" %%H in ('php.exe -r "$c=require 'config.php'; $u=parse_url($c['base_url']); echo $u['host'], ':', $u['port'], ' ', $c['base_url'];"') do (
    set "FOLIO_HOST=%%H"
    set "FOLIO_URL=%%I"
)
if not defined FOLIO_URL (
    echo No se pudo leer base_url en config.php.
    goto :fallo
)

rem Identificar Folio por su contrato y una consulta SOAP sin escrituras.
set "FOLIO_SONDA=$c=require 'config.php';"
set "FOLIO_SONDA=%FOLIO_SONDA% $wsdl=@file_get_contents($c['endpoint_url'].'?wsdl');"
set "FOLIO_SONDA=%FOLIO_SONDA% if ($wsdl===false || $wsdl!==file_get_contents($c['wsdl_path'])) exit(1);"
set "FOLIO_SONDA=%FOLIO_SONDA% $s=new SoapClient($c['wsdl_path'], ['location'=>$c['endpoint_url'], 'cache_wsdl'=>WSDL_CACHE_NONE, 'connection_timeout'=>2]);"
set "FOLIO_SONDA=%FOLIO_SONDA% $r=$s->ConsultarRangosNumeracion(['nit'=>'900123456']);"
set "FOLIO_SONDA=%FOLIO_SONDA% $html=@file_get_contents($c['base_url'].'/index.php');"
set "FOLIO_SONDA=%FOLIO_SONDA% exit($r->valido && $r->prefijo==='SETP' && is_string($html) && str_contains($html,'Folio') ? 0 : 1);"

"%FOLIO_PHP%" -r "$s=@stream_socket_client('tcp://'.getenv('FOLIO_HOST'), $n, $m, 0.5); exit($s ? 0 : 1);"
if errorlevel 1 goto :arrancar
call :responde
if not errorlevel 1 (
    echo Folio ya esta en ejecucion.
    goto :abrir
)
echo %FOLIO_HOST% esta ocupado. La aplicacion que responde no paso la comprobacion de Folio.
echo Si iniciaste PHP sin SOAP, cierra esa instancia y vuelve a ejecutar este archivo.
goto :fallo

:arrancar
echo Iniciando el catalogo y el servicio SOAP en %FOLIO_HOST% ...
start "Folio - Servidor PHP y SOAP" "%FOLIO_PHP%" %FOLIO_EXTDIR% %FOLIO_MODULOS% -S "%FOLIO_HOST%" router.php
for /l %%I in (1,1,12) do (
    call :responde
    if not errorlevel 1 goto :abrir
    "%FOLIO_PHP%" -r "usleep(250000);"
)
echo El servidor no respondio correctamente. Revisa la ventana del servidor PHP.
goto :fallo

:abrir
echo Catalogo: %FOLIO_URL%/index.php
start "" "%FOLIO_URL%/index.php"
if errorlevel 1 (
    echo Abre la direccion anterior en tu navegador.
    goto :fallo
)
popd
exit /b 0

:modulo
"%FOLIO_PHP%" -r "exit(extension_loaded('%~1') ? 0 : 1);"
if errorlevel 1 set "FOLIO_MODULOS=%FOLIO_MODULOS% -d extension=%~1"
exit /b 0

:requisitos
"%FOLIO_PHP%" %FOLIO_EXTDIR% %FOLIO_MODULOS% -r "foreach (['soap','dom','libxml','SimpleXML','openssl','mbstring'] as $m) { if (!extension_loaded($m)) { fwrite(STDERR, 'Falta la extension PHP: '.$m.PHP_EOL); exit(1); } }"
exit /b %errorlevel%

:responde
"%FOLIO_PHP%" %FOLIO_EXTDIR% %FOLIO_MODULOS% -d default_socket_timeout=2 -r "%FOLIO_SONDA%" >nul 2>&1
exit /b %errorlevel%

:incompleto
echo Extrae el proyecto completo y deja este archivo junto a config.php y router.php.
:fallo
popd
:sin_carpeta
pause
exit /b 1
