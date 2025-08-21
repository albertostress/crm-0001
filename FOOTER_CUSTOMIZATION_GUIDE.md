# EspoCRM Footer/Watermark Customization Guide
## How to Replace "© EspoCRM" with Custom Branding

### Overview
This guide documents the complete process to replace the default EspoCRM watermark with custom branding text in a Docker-based EspoCRM installation. The solution is upgrade-safe as it only uses the `/client/custom/` directory.

---

## Problem Analysis
EspoCRM displays "© EspoCRM" in multiple locations:
1. Login page footer
2. Main application footer (after login)
3. Sidebar footer (in some themes)

The watermark is rendered as:
```html
<p class="credit small">© 2025
    <a href="https://www.espocrm.com">EspoCRM</a>
</p>
```

---

## Solution: 3-Layer Approach

### Layer 1: CSS Override
**File:** `/client/custom/res/css/custom.css`

```css
/* Remove completamente o watermark EspoCRM/Evertec em TODOS os lugares */

/* Página de login - esconde o link */
.credit.small a,
p.credit.small a,
.credit a {
    display: none !important;
}

/* Página de login - adiciona novo texto */
.credit.small::after,
p.credit.small::after,
.credit::after {
    content: "© 2025 EVERTEC CRM — Todos os direitos reservados" !important;
    display: block !important;
    text-align: center !important;
}

/* Sidebar após login */
.layout__sidebar .credit,
.layout__sidebar .copyright,
.sidebar .credit,
.sidebar .copyright {
    display: none !important;
}

/* Adiciona texto no sidebar */
.layout__sidebar::after,
.sidebar::after {
    content: "© 2025 EVERTEC CRM — Todos os direitos reservados";
    display: block;
    text-align: center;
    opacity: .8;
    padding: 10px 0;
    font-size: 13px;
    color: #bbb;
}
```

### Layer 2: JavaScript Dynamic Replacement
**File:** `/client/custom/lib/custom-footer.js`

```javascript
// Custom Footer Replacement
(function() {
    'use strict';
    
    function replaceFooter() {
        // Seleciona todos os elementos de crédito
        const selectors = [
            '.credit.small',
            '.credit',
            'p.credit',
            '#footer .credit',
            'footer .credit'
        ];
        
        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                if (element.textContent.includes('EspoCRM') || 
                    element.textContent.includes('Evertec') ||
                    element.innerHTML.includes('espocrm.com')) {
                    element.innerHTML = '© 2025 EVERTEC CRM — Todos os direitos reservados';
                    element.style.textAlign = 'center';
                }
            });
        });
    }
    
    // Executar quando o DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', replaceFooter);
    } else {
        replaceFooter();
    }
    
    // Executar periodicamente para pegar conteúdo dinâmico
    setInterval(replaceFooter, 500);
    
    // Observar mudanças no DOM
    const observer = new MutationObserver(replaceFooter);
    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})();
```

### Layer 3: EspoCRM Metadata Configuration
**File:** `/custom/Espo/Custom/Resources/metadata/app/client.json`

```json
{
    "cssList": [
        "__APPEND__",
        "client/custom/res/css/custom.css"
    ],
    "scriptList": [
        "__APPEND__",
        "client/custom/lib/custom-footer.js"
    ]
}
```

---

## Step-by-Step Implementation

### 1. Create Directory Structure
```bash
# Create necessary directories
mkdir -p client/custom/res/css
mkdir -p client/custom/lib
mkdir -p custom/Espo/Custom/Resources/metadata/app
```

### 2. Create the Files
Create the three files mentioned above with the provided content.

### 3. Copy to Docker Container
```bash
# Copy custom client files
docker cp client/custom espocrm:/var/www/html/client/

# Copy metadata configuration
docker cp custom/Espo espocrm:/var/www/html/custom/

# Fix permissions
docker exec espocrm chown -R www-data:www-data /var/www/html/client/custom
docker exec espocrm chown -R www-data:www-data /var/www/html/custom/Espo
```

### 4. Clear Cache and Rebuild
```bash
# Clear all caches
docker exec espocrm rm -rf /var/www/html/data/cache/*
docker exec espocrm rm -rf /var/www/html/client/lib/templates.tpl

# Rebuild EspoCRM
docker exec espocrm php /var/www/html/rebuild.php

# Clear cache again
docker exec espocrm php /var/www/html/clear_cache.php
```

### 5. Restart Container
```bash
docker restart espocrm
```

### 6. Clear Browser Cache
- Press `Ctrl + F5` (Windows/Linux) or `Cmd + Shift + R` (Mac)
- Or open in Incognito/Private mode

---

## Troubleshooting

### If changes don't appear:

1. **Verify files are in container:**
```bash
docker exec espocrm ls -la /var/www/html/client/custom/res/css/
docker exec espocrm ls -la /var/www/html/client/custom/lib/
```

2. **Check if custom CSS is loading:**
Open browser DevTools (F12) → Network tab → Look for `custom.css`

3. **Force template recompilation:**
```bash
docker exec espocrm touch /var/www/html/client/lib/templates.tpl
docker exec espocrm php /var/www/html/rebuild.php
```

4. **Check for JavaScript errors:**
Open browser DevTools (F12) → Console tab

### Common Issues:

- **404 error for templates.tpl:** Create empty file:
```bash
docker exec espocrm touch /var/www/html/client/lib/templates.tpl
docker exec espocrm chown www-data:www-data /var/www/html/client/lib/templates.tpl
```

- **Changes revert after login:** The JavaScript solution handles this by continuously monitoring and replacing the text

- **Custom files not loading:** Ensure the metadata configuration file is correctly placed and has proper JSON syntax

---

## Why This Solution Works

1. **CSS Layer:** Provides immediate visual replacement using CSS pseudo-elements
2. **JavaScript Layer:** Actively replaces HTML content, handling dynamic content loading
3. **Metadata Configuration:** Ensures EspoCRM loads our custom files in the correct order

The combination of all three layers ensures the watermark is replaced in all scenarios:
- Initial page load
- After AJAX content updates
- After login/logout
- In dynamically generated content

---

## Customization

To use your own branding text, replace:
```
© 2025 EVERTEC CRM — Todos os direitos reservados
```

With your desired text in:
1. `custom.css` (in the `content:` properties)
2. `custom-footer.js` (in the `element.innerHTML =` line)

---

## Files Structure Summary
```
espocrm/
├── client/
│   └── custom/
│       ├── res/
│       │   └── css/
│       │       └── custom.css
│       └── lib/
│           └── custom-footer.js
└── custom/
    └── Espo/
        └── Custom/
            └── Resources/
                └── metadata/
                    └── app/
                        └── client.json
```

---

## Notes
- This solution is **upgrade-safe** - all files are in custom directories
- Works with EspoCRM 7.x and 8.x
- Tested in Docker environment
- No core files are modified

---

*Document created: December 2024*
*Last tested: EspoCRM in Docker*