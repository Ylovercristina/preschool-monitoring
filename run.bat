@echo off
title Preschool Monitoring System - LittleSteps
echo ============================================================
echo   Preschool Monitoring System
echo   Authors: Rhysa A. Caruz, Cristine Joy B. Jaojao, Xyrha Viel Sacal
echo ============================================================
echo.

set PHP_EXE=php
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\Users\LENOVO\php\php.exe" (
        set PHP_EXE="C:\Users\LENOVO\php\php.exe"
    ) else (
        echo [ERROR] PHP executable not found. Please install PHP or ensure it is in PATH.
        pause
        exit /b 1
    )
)

echo Starting PHP Built-in Server on http://localhost:8000 ...
echo Press Ctrl+C anytime to stop the server.
echo.

start http://localhost:8000
%PHP_EXE% -S localhost:8000

pause
