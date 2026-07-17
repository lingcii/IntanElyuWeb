@echo off
setlocal enabledelayedexpansion

echo ==========================================================
echo Starting Intan Elyu Tourism Management System Servers
echo Universal "Anywhere" Access Mode
echo ==========================================================
echo.

:: 1. Check if Ngrok is authenticated
echo Checking Ngrok Authentication...
findstr "authtoken" "%LOCALAPPDATA%\ngrok\ngrok.yml" >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo [!] Ngrok is not authenticated.
    echo To access your app anywhere, you need a free Ngrok account.
    echo Get your Authtoken here: https://dashboard.ngrok.com/get-started/your-authtoken
    echo.
    set /p NGROK_TOKEN="Please paste your Ngrok Authtoken here: "
    if "!NGROK_TOKEN!"=="" (
        echo You must provide a token to continue. Exiting.
        pause
        exit /b
    )
    ngrok config add-authtoken !NGROK_TOKEN!
    echo Authtoken saved successfully!
    echo.
)

:: 2. Start Local Servers
echo Starting Backend...
start cmd /k "cd backend && php artisan serve"

echo Starting Mobile Frontend...
start cmd /k "cd Frontend\Mobile && npm run start"

echo Starting Admin Website...
start cmd /k "cd Frontend\Website && php -S localhost:4000"

:: 3. Start Ngrok Tunnels
echo Starting Ngrok Tunnels...
:: We start ngrok in a new window so it stays open
start cmd /k "ngrok start --all --config="%LOCALAPPDATA%\ngrok\ngrok.yml" --config=ngrok-multi.yml"

:: 4. Run the Auto-Configurator
echo.
echo Waiting for tunnels to establish...
:: Wait 3 seconds
ping 127.0.0.1 -n 4 > nul

echo.
echo Running Auto-Configurator to inject URLs into your project...
node configure-tunnels.js

echo.
echo ==========================================================
echo EVERYTHING IS RUNNING!
echo.
echo IMPORTANT NEXT STEP:
echo Since your "Anywhere" URLs have changed, you must push this 
echo new configuration to your Android Phone.
echo.
echo Please go to Android Studio and click the green RUN (Play) button!
echo ==========================================================
echo.
pause
