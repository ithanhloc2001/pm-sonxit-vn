@echo off
setlocal enabledelayedexpansion

:: Tham so dau tien "auto" / "silent" => chay nen, khong mo Explorer
:: (web va cron truyen "auto"; chay tay double-click thi mo Explorer nhu cu)
set "silent_mode="
if /i "%~1"=="auto" set "silent_mode=1"
if /i "%~1"=="silent" set "silent_mode=1"

:: ==========================================
:: 1. CAU HINH MAC DINH (FALLBACK)
:: ==========================================
set "enable_code=true"
set "winrar_exe=C:\Program Files\WinRAR\WinRar.exe"
set "code_source=C:\KUNLOC\diy\www"
set "code_dest=C:\KUNLOC\diy\backups"
set "code_zip_prefix=backup_diy_"

set "enable_sql=true"
set "sqldump_exe=docker exec -i mysql_container mysqldump"
set "sql_dest=C:\KUNLOC\diy\db"
set "sql_folder_prefix="

:: Thong tin dang nhap DB (KHONG hardcode trong script - lay tu config/bien moi truong)
:: Co the dat trong backup-config.json: "db_user", "db_password"
:: hoac qua bien moi truong: BACKUP_DB_USER, BACKUP_DB_PASSWORD
set "db_user=root"
set "db_password="

:: ==========================================
:: 2. DOC CAU HINH TU FILE JSON (NEU CO)
:: ==========================================
set "config_file=%~dp0backup-config.json"

if exist "%config_file%" (
    echo [INFO] Dang doc cau hinh tu: %config_file%
    for /f "usebackq delims=" %%a in (`powershell -NoProfile -Command "$json = Get-Content -Raw -LiteralPath '%config_file%' | ConvertFrom-Json; $json.PSObject.Properties | Where-Object { $_.Value -is [string] -or $_.Value -is [bool] } | ForEach-Object { $_.Name + '=' + $_.Value }"`) do (
        for /f "tokens=1* delims==" %%k in ("%%a") do set "config_%%k=%%l"
    )
    if defined config_enable_code set "enable_code=!config_enable_code!"
    if defined config_winrar_exe set "winrar_exe=!config_winrar_exe!"
    if defined config_code_source set "code_source=!config_code_source!"
    if defined config_code_dest set "code_dest=!config_code_dest!"
    if defined config_code_zip_prefix set "code_zip_prefix=!config_code_zip_prefix!"

    if defined config_enable_sql set "enable_sql=!config_enable_sql!"
    if defined config_sqldump_exe set "sqldump_exe=!config_sqldump_exe!"
    if defined config_sql_dest set "sql_dest=!config_sql_dest!"
    if defined config_sql_folder_prefix set "sql_folder_prefix=!config_sql_folder_prefix!"

    if defined config_db_user set "db_user=!config_db_user!"
    if defined config_db_password set "db_password=!config_db_password!"
)

:: Bien moi truong co do uu tien cao nhat (de tranh ghi password vao file config)
if defined BACKUP_DB_USER set "db_user=%BACKUP_DB_USER%"
if defined BACKUP_DB_PASSWORD set "db_password=%BACKUP_DB_PASSWORD%"

:: Chuan hoa lai cac bien su dung trong script
set "source_code=!code_source!"
set "sql_temp_folder=!sql_dest!"
set "backup_dest=!code_dest!"

:: Tu dong phat hien chay Docker hay local
set "use_docker="
echo !sqldump_exe! | findstr /i "docker" >nul
if not errorlevel 1 set "use_docker=1"

if not defined use_docker (
    rem Neu cau hinh la file local
    if not exist "!sqldump_exe!" (
        echo [WARNING] Khong tim thay mysqldump cuc bo tai "!sqldump_exe!". Kiem tra lai duong dan hoac dung Docker.
    )
)

:: Kiem tra su ton tai cua WinRAR, neu khong co se tu dong dung PowerShell
if not exist "!winrar_exe!" (
    echo [WARNING] Khong tim thay WinRAR tai "!winrar_exe!". He thong se tu dong su dung PowerShell de nen file ZIP.
    set "winrar_exe="
)

:: LAY THOI GIAN
for /f "usebackq tokens=*" %%i in (`powershell -NoProfile -Command "Get-Date -Format 'HH_mm_ss_by_dd_MM_yyyy'"`) do set "filename_time=%%i"

:: Thiet lap ten file backup
if not defined code_zip_prefix set "code_zip_prefix=backup_htdocs_sql_"
set "filename=!code_zip_prefix!!filename_time!.zip"
set "masked_filename=!code_zip_prefix!!filename_time!.loc"
set "temp_zip=%~dp0temp_backup.zip"
set "temp_loc=%~dp0!masked_filename!"

:: Thiet lap ten file SQL
set "sql_file=!sql_folder_prefix!data_backup.sql"

echo ==========================================
echo THONG TIN BACKUP:
echo - WinRAR: !winrar_exe! (Neu trong: dung PowerShell ZIP)
echo - Source Code: !source_code!
echo - Backup Dest: !backup_dest!
echo - SQL Dump Exe: !sqldump_exe!
echo - SQL Temp Folder: !sql_temp_folder!
echo - SQL Backup File: !sql_file!
echo - Output ZIP File: !filename!
echo ==========================================

:: ==========================================
:: 3. TAO THU MUC CAN THIET
:: ==========================================
if /i "!enable_sql!"=="true" (
    if not exist "!sql_temp_folder!" mkdir "!sql_temp_folder!"
)
if /i "!enable_code!"=="true" (
    if not exist "!backup_dest!" mkdir "!backup_dest!"
)

:: ==========================================
:: 4. BUOC 1: XUAT SQL TRUC TIEP (HOAC QUA DOCKER)
:: ==========================================
set "sql_ok="
if /i "!enable_sql!"=="true" (
    echo [1/4] Dang xuat SQL...

    rem Xay dung tham so dang nhap mot cach an toan
    set "cred=-u !db_user!"
    if defined db_password set "cred=!cred! -p!db_password!"

    if defined use_docker (
        echo Chay mysqldump trong Docker: !sqldump_exe!
        !sqldump_exe! !cred! --all-databases > "!sql_temp_folder!\!sql_file!"
    ) else (
        echo Chay mysqldump cuc bo: "!sqldump_exe!"
        "!sqldump_exe!" !cred! --all-databases --force --ignore-table=mysql.db > "!sql_temp_folder!\!sql_file!"
    )

    rem Kiem tra file SQL ton tai VA khac rong
    if not exist "!sql_temp_folder!\!sql_file!" (
        echo [LOI] Khong the tao file SQL. Kiem tra MySQL/Docker dang bat hay tat!
        exit /b 1
    )
    for %%S in ("!sql_temp_folder!\!sql_file!") do set "sql_size=%%~zS"
    if "!sql_size!"=="0" (
        echo [LOI] File SQL rong - mysqldump that bai. Kiem tra thong tin dang nhap / Docker!
        del /f /q "!sql_temp_folder!\!sql_file!" >nul 2>&1
        exit /b 1
    )
    set "sql_ok=1"
    echo [OK] Xuat SQL thanh cong: !sql_file! (!sql_size! bytes)
) else (
    echo [INFO] Bo qua buoc xuat SQL, enable_sql = !enable_sql!
)

:: ==========================================
:: 5. BUOC 2: NEN CODE VA SQL
:: ==========================================
if /i "!enable_code!"=="true" (

    rem Xac dinh cac nguon can nen
    set "winrar_sources="!source_code!\*""
    set "sql_zip_target="

    rem Neu co SQL backup va thu muc SQL nam ngoai source_code, ta nen file SQL kem theo
    if defined sql_ok (
        set "sql_outside="
        echo "!sql_temp_folder!" | findstr /i /c:"!source_code!" >nul || set "sql_outside=1"
        if defined sql_outside (
            if exist "!sql_temp_folder!\!sql_file!" (
                set "winrar_sources=!winrar_sources! "!sql_temp_folder!\!sql_file!""
                set "sql_zip_target=!sql_temp_folder!\!sql_file!"
            )
        )
    )

    if exist "!temp_zip!" del /f /q "!temp_zip!"
    if exist "!temp_loc!" del /f /q "!temp_loc!"

    set "zip_status=1"
    if defined winrar_exe (
        echo [2/4] Dang nen source code va SQL bang WinRAR vao file: !filename!...
        "!winrar_exe!" a -t -r -ep1 -dh -m5 -x"!source_code!\uploads\*" "!temp_zip!" !winrar_sources!
        set "zip_status=!ERRORLEVEL!"
    ) else (
        echo [2/4] Dang nen source code bang PowerShell vao file: !filename!...
        powershell -NoProfile -Command "$ErrorActionPreference='Stop'; try { Get-ChildItem -LiteralPath '!source_code!' -Force | Where-Object { $_.Name -ne 'uploads' } | Compress-Archive -DestinationPath '!temp_zip!' -Force; exit 0 } catch { Write-Error $_; exit 1 }"
        set "zip_status=!ERRORLEVEL!"

        if !zip_status! EQU 0 if defined sql_zip_target (
            echo Dang nen them file SQL vao file ZIP...
            powershell -NoProfile -Command "$ErrorActionPreference='Stop'; try { Compress-Archive -LiteralPath '!sql_zip_target!' -Update -DestinationPath '!temp_zip!'; exit 0 } catch { Write-Error $_; exit 1 }"
            set "zip_status=!ERRORLEVEL!"
        )
    )

    rem ==========================================
    rem 6. BUOC 3: DONG BO UPLOADS VAO THU MUC BACKUP UPLOAD
    rem ==========================================
    echo [3/4] Dang dong bo thu muc uploads vao "!backup_dest!\UPLOAD"...
    if not exist "!backup_dest!\UPLOAD" mkdir "!backup_dest!\UPLOAD"

    robocopy "!source_code!\uploads" "!backup_dest!\UPLOAD" /E /XO /COPY:DAT /R:1 /W:1 /NFL /NDL /NP
    set "rc=!ERRORLEVEL!"
    if !rc! GEQ 8 (
        echo [LOI] ROBOCOPY gap loi - ma loi !rc!
    ) else (
        echo [OK] Dong bo uploads hoan tat - ma !rc!
    )

    rem ==========================================
    rem 7. BUOC 4: DOI DUOI FILE BACKUP (.zip -> .loc) VA DI CHUYEN
    rem ==========================================
    if !zip_status! EQU 0 (
        echo [4/4] Dang doi duoi file thanh: !masked_filename!
        ren "!temp_zip!" "!masked_filename!"

        echo Dang di chuyen file backup vao: !backup_dest!\
        if not exist "!backup_dest!" mkdir "!backup_dest!"
        if exist "!backup_dest!\!masked_filename!" del /f /q "!backup_dest!\!masked_filename!"
        move /Y "!temp_loc!" "!backup_dest!\" >nul
        echo [OK] Backup hoan tat!
    ) else (
        echo [LOI] Nen gap loi - ma loi !zip_status!
        if exist "!temp_zip!" del /f /q "!temp_zip!"
        echo [CANH BAO] Giu lai file SQL tam de tranh mat du lieu: "!sql_temp_folder!\!sql_file!"
        endlocal
        exit /b 1
    )
) else (
    echo [INFO] Bo qua buoc nen code va dong bo upload - enable_code = !enable_code!
    if defined sql_ok (
        echo [CANH BAO] enable_code=false nen file SQL KHONG duoc dong goi.
        echo            File SQL duoc giu nguyen tai: "!sql_temp_folder!\!sql_file!"
    )
)

:: ==========================================
:: 8. DON DEP FILE TAM
:: Chi xoa SQL khi da nen thanh cong (enable_code=true va zip_status=0)
:: ==========================================
if /i "!enable_code!"=="true" if /i "!enable_sql!"=="true" if defined sql_ok (
    if exist "!sql_temp_folder!\!sql_file!" del /f /q "!sql_temp_folder!\!sql_file!"
)

rem Tu dong xoa file backup cu hon 3 ngay (.loc)
rem forfiles /p "!backup_dest!" /s /m *.loc /d -3 /c "cmd /c del /f /q @path" 2>nul

echo Xong! Hay kiem tra thu muc: !backup_dest!
if not defined silent_mode explorer.exe "!backup_dest!"
exit /b 0
