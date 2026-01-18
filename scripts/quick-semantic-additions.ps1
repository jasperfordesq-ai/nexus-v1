# Quick Semantic HTML Additions
# Adds main tags to remaining key pages

$pages = @(
    @{
        file = "c:\Home Directory\views\modern\feed\index.php"
        label = "Content feed"
    },
    @{
        file = "c:\Home Directory\views\modern\wallet\index.php"
        label = "Wallet"
    },
    @{
        file = "c:\Home Directory\views\modern\settings\index.php"
        label = "Settings"
    },
    @{
        file = "c:\Home Directory\views\modern\volunteering\index.php"
        label = "Volunteering opportunities"
    },
    @{
        file = "c:\Home Directory\views\modern\blog\index.php"
        label = "Blog"
    },
    @{
        file = "c:\Home Directory\views\modern\groups\show.php"
        label = "Group details"
    }
)

$filesUpdated = 0

foreach ($page in $pages) {
    if (-not (Test-Path $page.file)) {
        Write-Host "Not found: $($page.file)" -ForegroundColor Yellow
        continue
    }

    $content = Get-Content $page.file -Raw

    # Skip if already has main tag
    if ($content -match '<main id="main-content"') {
        Write-Host "Already done: $($page.file)" -ForegroundColor Green
        continue
    }

    # Find where to insert opening tag (after last PHP close before first HTML div)
    if ($content -match '(\?>\s*\n)(<div class="[^"]*container[^"]*")') {
        $openingTag = "`r`n<main id=`"main-content`" role=`"main`" aria-label=`"$($page.label)`">`r`n"
        $content = $content -replace '(\?>\s*\n)(<div class="[^"]*container[^"]*")', "`$1$openingTag`$2"

        # Find where to insert closing tag (before footer include)
        if ($content -match '(</script>\s*\n)(<\?php\s+require.* footer\.php)') {
            $content = $content -replace '(</script>\s*\n)(<\?php\s+require.* footer\.php)', "`$1</main>`r`n`r`n`$2"
        }

        Set-Content -Path $page.file -Value $content -NoNewline
        $filesUpdated++
        Write-Host "Updated: $($page.file)" -ForegroundColor Cyan
    } else {
        Write-Host "Pattern not matched: $($page.file)" -ForegroundColor Yellow
    }
}

Write-Host " "
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Files updated: $filesUpdated" -ForegroundColor Green
Write-Host "================================" -ForegroundColor Cyan
