@echo off
title Celery Worker - IELTS Generator

:: 1. Define the path to your VENV's Python directly
set VENV_PATH=%cd%\venv_stable\Scripts
set PYTHON_EXE=%VENV_PATH%\python.exe

:: 2. Set the PYTHONPATH so it can find your tasks folder
set PYTHONPATH=%cd%;%cd%\celery_tasks

echo ---------------------------------------------------
echo ⏳ Starting Celery Worker using venv_stable...
echo ---------------------------------------------------

:: 3. Run Celery using the VENV's Python module
:: This ensures it uses the 'whisper' and 'genai' installed in the venv
"%PYTHON_EXE%" -m celery -A celery_tasks.celery_config worker --loglevel=info -P eventlet

pause