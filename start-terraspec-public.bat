@echo off
title TerraSpec - public link
rem Starts MySQL, Apache (TerraSpec in production mode on http://127.0.0.1:8080) and a
rem Cloudflare quick tunnel. The public link is printed below as https://....trycloudflare.com
rem (it changes every time this runs). Close this window to take the public link offline.
cd /d D:\TerraSpec

if exist public\hot (
    echo public\hot exists: stop "npm run dev" / "composer run dev" first,
    echo otherwise the public site tries to load scripts from the development server.
    pause
    exit /b 1
)

tasklist | find /i "mysqld.exe" >nul || start "MySQL" /min C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
tasklist | find /i "httpd.exe" >nul || start "Apache" /min C:\xampp\apache\bin\httpd.exe

echo.
echo Starting the public link. Look for a line with https://....trycloudflare.com below.
echo On this PC the system is also at http://127.0.0.1:8080
echo.
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel --no-autoupdate --url http://127.0.0.1:8080
pause
