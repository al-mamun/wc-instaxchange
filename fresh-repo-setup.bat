@echo off
REM Fresh Repository Setup Script for Windows
REM This script creates a clean git repository without any previous history

echo ===============================================
echo WooCommerce InstaxChange - Fresh Repo Setup
echo ===============================================
echo.

REM Check if we're in the right directory
if not exist "wc-instaxchange.php" (
    echo Error: Not in plugin directory. Please cd to wc-instaxchange folder first.
    pause
    exit /b 1
)

echo Warning: This will remove all git history and create a fresh repository.
set /p confirm="Are you sure you want to continue? (yes/no): "

if /i not "%confirm%"=="yes" (
    echo Aborted by user.
    pause
    exit /b 0
)

echo.
set /p repo_url="Enter your new GitHub repository URL (e.g., https://github.com/username/repo-name.git): "

if "%repo_url%"=="" (
    echo Error: Repository URL is required.
    pause
    exit /b 1
)

echo.
echo Step 1: Removing old git history...
rd /s /q .git 2>nul
echo Old git history removed

echo.
echo Step 2: Initializing fresh repository...
git init
echo Fresh repository initialized

echo.
echo Step 3: Staging all files...
git add .
echo All files staged

echo.
echo Step 4: Creating initial commit...
git commit -m "Initial commit - WooCommerce InstaxChange Payment Gateway v2.0.0" -m "Features:" -m "- Multiple payment methods (cards, wallets, regional, crypto)" -m "- BLIK payment support for Polish market" -m "- Production-grade security with webhook verification" -m "- Rate limiting and DoS protection" -m "- Environment detection (production/development)" -m "- Configuration validation system" -m "- Admin error notifications" -m "- WooCommerce Blocks support" -m "- Theme compatibility layer" -m "" -m "Security:" -m "- HMAC-SHA256 webhook signature verification" -m "- Rate limiting on all AJAX endpoints" -m "- No demo mode in production" -m "- Input validation and CSRF protection" -m "" -m "Production readiness: 95%%"
echo Initial commit created

echo.
echo Step 5: Adding remote repository...
git remote add origin %repo_url%
echo Remote repository added

echo.
echo Step 6: Renaming branch to main...
git branch -M main
echo Branch renamed to main

echo.
echo Step 7: Pushing to GitHub...
git push -u origin main

if %errorlevel% equ 0 (
    echo.
    echo ===============================================
    echo SUCCESS! Fresh repository created
    echo ===============================================
    echo.
    echo Repository URL: %repo_url%
    echo Branch: main
    echo Commits: 1
    echo Contributors: Only you
    echo.
    echo Next steps:
    echo 1. Visit your repository on GitHub
    echo 2. Verify contributors show only your name
    echo 3. Delete old repository if desired
    echo.
) else (
    echo.
    echo Error: Push failed. Please check your credentials and try again.
    echo.
    echo Troubleshooting:
    echo 1. Ensure you have access to the repository
    echo 2. Generate a personal access token at: https://github.com/settings/tokens
    echo 3. Try: git push -u origin main
)

pause
