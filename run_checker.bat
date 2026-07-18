@echo off
:loop
curl -s "https://model.zya.me/checker_api.php?key=moodle_tracker_2024_secret" > nul 2>&1
timeout /t 60 /nobreak > nul
goto loop
