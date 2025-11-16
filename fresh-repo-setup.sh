#!/bin/bash

# Fresh Repository Setup Script
# This script creates a clean git repository without any previous history

echo "==============================================="
echo "WooCommerce InstaxChange - Fresh Repo Setup"
echo "==============================================="
echo ""

# Check if we're in the right directory
if [ ! -f "wc-instaxchange.php" ]; then
    echo "❌ Error: Not in plugin directory. Please cd to wc-instaxchange folder first."
    exit 1
fi

echo "⚠️  Warning: This will remove all git history and create a fresh repository."
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "❌ Aborted by user."
    exit 0
fi

echo ""
read -p "Enter your new GitHub repository URL (e.g., https://github.com/username/repo-name.git): " repo_url

if [ -z "$repo_url" ]; then
    echo "❌ Error: Repository URL is required."
    exit 1
fi

echo ""
echo "📝 Step 1: Removing old git history..."
rm -rf .git
echo "✅ Old git history removed"

echo ""
echo "📝 Step 2: Initializing fresh repository..."
git init
echo "✅ Fresh repository initialized"

echo ""
echo "📝 Step 3: Staging all files..."
git add .
echo "✅ All files staged"

echo ""
echo "📝 Step 4: Creating initial commit..."
git commit -m "Initial commit - WooCommerce InstaxChange Payment Gateway v2.0.0

Features:
- Multiple payment methods (cards, wallets, regional, crypto)
- BLIK payment support for Polish market
- Production-grade security with webhook verification
- Rate limiting and DoS protection
- Environment detection (production/development)
- Configuration validation system
- Admin error notifications
- WooCommerce Blocks support
- Theme compatibility layer

Security:
- HMAC-SHA256 webhook signature verification
- Rate limiting on all AJAX endpoints
- No demo mode in production
- Input validation and CSRF protection

Production readiness: 95%"
echo "✅ Initial commit created"

echo ""
echo "📝 Step 5: Adding remote repository..."
git remote add origin "$repo_url"
echo "✅ Remote repository added"

echo ""
echo "📝 Step 6: Renaming branch to main..."
git branch -M main
echo "✅ Branch renamed to main"

echo ""
echo "📝 Step 7: Pushing to GitHub..."
git push -u origin main

if [ $? -eq 0 ]; then
    echo ""
    echo "==============================================="
    echo "✅ SUCCESS! Fresh repository created"
    echo "==============================================="
    echo ""
    echo "Repository URL: $repo_url"
    echo "Branch: main"
    echo "Commits: 1"
    echo "Contributors: Only you"
    echo ""
    echo "Next steps:"
    echo "1. Visit your repository on GitHub"
    echo "2. Verify contributors show only your name"
    echo "3. Delete old repository if desired"
    echo ""
else
    echo ""
    echo "❌ Error: Push failed. Please check your credentials and try again."
    echo ""
    echo "Troubleshooting:"
    echo "1. Ensure you have access to the repository"
    echo "2. Generate a personal access token at: https://github.com/settings/tokens"
    echo "3. Try: git push -u origin main"
    exit 1
fi
