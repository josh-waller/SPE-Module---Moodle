@echo off
setlocal ENABLEDELAYEDEXPANSION

REM SmartSPE AI API server manager (Windows)
REM Usage:
REM   manage_ai_server.bat install   - create venv and install requirements
REM   manage_ai_server.bat start     - start server in background
REM   manage_ai_server.bat stop      - stop server
REM   manage_ai_server.bat restart   - restart server
REM   manage_ai_server.bat status    - show server status

set SCRIPT_DIR=%~dp0
set VENV_DIR=%SCRIPT_DIR%venv
set PY_EXE=
set PID_FILE=%SCRIPT_DIR%ai_server.pid
set LOG_FILE=%SCRIPT_DIR%ai_server.log
set PSH=powershell -NoLogo -NoProfile -ExecutionPolicy Bypass

if "%1"=="" goto :menu
set CMD=%1

REM Detect Python (prefer python, fallback py)
for %%P in (python.exe) do (
  where %%P >nul 2>nul
  if !errorlevel! == 0 set PY_EXE=python
)
if "%PY_EXE%"=="" (
  where py >nul 2>nul && set PY_EXE=py
)

if "%CMD%"=="install" goto :install
if "%CMD%"=="start" goto :start
if "%CMD%"=="stop" goto :stop
if "%CMD%"=="restart" goto :restart
if "%CMD%"=="status" goto :status
goto :help

:install
echo [AI] Setting up virtual environment and dependencies...
if "%PY_EXE%"=="" (
  echo [AI][ERROR] Python not found in PATH. Please install Python 3.11+ and retry.
  exit /b 1
)

if not exist "%VENV_DIR%" (
  echo [AI] Creating venv at "%VENV_DIR%" ...
  %PY_EXE% -m venv "%VENV_DIR%"
)

call "%VENV_DIR%\Scripts\activate.bat"
set VENV_PY="%VENV_DIR%\Scripts\python.exe"
if exist "%SCRIPT_DIR%requirements.txt" (
  echo [AI] Installing requirements...
  %VENV_PY% -m pip install --upgrade pip
  %VENV_PY% -m pip install -r "%SCRIPT_DIR%requirements.txt"
  echo [AI] Ensuring CPU-only PyTorch build on Windows (pinned)...
  %VENV_PY% -m pip uninstall -y torch torchvision torchaudio 2>nul >nul
  %VENV_PY% -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.2.2 torchvision==0.17.2 torchaudio==2.2.2
) else (
  echo [AI][WARN] requirements.txt not found. Skipping.
)
echo [AI] Install complete.
exit /b 0

:start
if exist "%PID_FILE%" (
  call :status
  if not errorlevel 1 (
    echo [AI] Server already running. Use restart to reload.
    exit /b 0
  )
)
if not exist "%VENV_DIR%\Scripts\activate.bat" (
  echo [AI] No venv found. Running install first...
  call %~f0 install || exit /b 1
)
echo [AI] Starting server on port 8000 ...
%PSH% -Command "Set-Location '%SCRIPT_DIR%'; $p = Start-Process -FilePath '%VENV_DIR%\Scripts\python.exe' -ArgumentList 'ai_api_server.py' -WindowStyle Minimized -PassThru; Start-Sleep -Seconds 1; $p.Id | Out-File -FilePath '%PID_FILE%' -Encoding ascii;" 1>>"%LOG_FILE%" 2>&1
if exist "%PID_FILE%" goto :started
echo [AI][WARN] Could not determine server PID. It may still be running. Check "%LOG_FILE%".
exit /b 0

:started
echo [AI] Server started. PID: %PID_FILE% contents:
type "%PID_FILE%"
exit /b 0

:stop
if not exist "%PID_FILE%" (
  echo [AI] No PID file found. Attempting to find server window...
  for /f "tokens=2" %%A in ('tasklist /v ^| findstr /i "AI_API_Server"') do taskkill /PID %%A /T /F
  exit /b 0
)
set /p RUNPID=<"%PID_FILE%"
if "%RUNPID%"=="" (
  echo [AI] Empty PID file. Removing.
  del "%PID_FILE%" >nul 2>nul
  exit /b 0
)
echo [AI] Stopping PID %RUNPID% ...
taskkill /PID %RUNPID% /T /F >nul 2>nul
del "%PID_FILE%" >nul 2>nul
echo [AI] Stopped.
exit /b 0

:restart
call %~f0 stop
call %~f0 start
exit /b %errorlevel%

:status
if exist "%PID_FILE%" (
  set /p RUNPID=<"%PID_FILE%"
  if not "%RUNPID%"=="" (
    tasklist /FI "PID eq %RUNPID%" | find "%RUNPID%" >nul && (
      echo [AI] Running (PID %RUNPID%).
      exit /b 0
    )
  )
)
REM Fallback: look for window by title
tasklist /v | findstr /i "AI_API_Server" >nul && (
  echo [AI] Running (window matched, no PID file).
  exit /b 0
)
echo [AI] Not running.
exit /b 1

:menu
cls
echo ========================================
echo   SmartSPE AI Server Manager
echo ========================================
echo.
echo 1. Install AI Server (first time setup)
echo 2. Start AI Server
echo 3. Stop AI Server
echo 4. Restart AI Server
echo 5. Check Server Status
echo 6. Exit
echo.
set /p choice="Enter your choice (1-6): "

if "%choice%"=="1" goto :install
if "%choice%"=="2" goto :start
if "%choice%"=="3" goto :stop
if "%choice%"=="4" goto :restart
if "%choice%"=="5" (
  call :status
  echo.
  pause
  goto :menu
)
if "%choice%"=="6" exit /b 0
echo Invalid choice. Try again.
timeout /t 2 >nul
goto :menu

:help
echo Usage: %~n0 ^<install^|start^|stop^|restart^|status^>
exit /b 1


