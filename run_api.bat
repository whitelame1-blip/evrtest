@echo off
title AI Listening API Server
color 0b

:: 1. Move to the project root (where the venv is)
cd /d "%~dp0"

:: 2. Activate the Virtual Environment
if exist venv_stable\Scripts\activate (
    call venv_stable\Scripts\activate
) else (
    echo [ERROR] Virtual environment not found!
    pause
    exit
)

:: 3. Set PYTHONPATH to the root so internal imports work
set PYTHONPATH=%cd%

:: 4. Run the server script directly
echo Starting AI Listening Dispatcher...
python api_server/main.py

:: 5. Keep window open if it stops
echo.
echo [SERVER STOPPED]
pause