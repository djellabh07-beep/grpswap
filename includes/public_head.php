<?php
/**
 * includes/public_head.php
 *
 * Design system for pages BEFORE login (login.php, signup.php,
 * verify_email.php) that can't use includes/header.php (that file
 * requires a logged-in user — course directory, nav, avatar, etc).
 *
 * This is a deliberate duplicate of header.php's Tailwind config/fonts/
 * animation styles, not a shared include FROM header.php — kept
 * separate so a future edit to the authenticated app's header never
 * accidentally touches these public pages, and vice versa. If the
 * design system changes, both files need the same edit.
 *
 * Usage: `<?php require __DIR__ . '/includes/public_head.php'; ?>`
 * inside a page's own <head>, in place of a bare Tailwind CDN script tag.
 */
?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Poppins:wght@600;700&family=Inter:wght@400;600&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Poppins:wght@100..900&display=swap">

<script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                "colors": {
                    "tertiary-container": "#4c3706",
                    "on-tertiary-fixed-variant": "#594312",
                    "inverse-surface": "#2f2e43",
                    "on-tertiary-container": "#bfa066",
                    "on-secondary-fixed": "#00201c",
                    "surface-tint": "#425e91",
                    "on-error": "#ffffff",
                    "outline-variant": "#c4c6d0",
                    "primary-container": "#1b3a6b",
                    "secondary-fixed-dim": "#6fd8c8",
                    "on-tertiary-fixed": "#261900",
                    "on-tertiary": "#ffffff",
                    "tertiary": "#322200",
                    "primary": "#002452",
                    "secondary-container": "#8cf5e4",
                    "on-secondary": "#ffffff",
                    "on-surface": "#1a1a2e",
                    "on-secondary-fixed-variant": "#005048",
                    "error": "#ba1a1a",
                    "secondary-fixed": "#8cf5e4",
                    "on-error-container": "#93000a",
                    "on-primary-container": "#89a5dd",
                    "background": "#fcf8ff",
                    "inverse-on-surface": "#f2efff",
                    "outline": "#747780",
                    "primary-fixed": "#d7e2ff",
                    "on-primary-fixed-variant": "#294678",
                    "on-primary-fixed": "#001a40",
                    "on-secondary-container": "#007166",
                    "surface-container-highest": "#e2e0fc",
                    "on-surface-variant": "#44474f",
                    "surface-container-low": "#f5f2ff",
                    "surface": "#fcf8ff",
                    "error-container": "#ffdad6",
                    "tertiary-fixed-dim": "#e3c285",
                    "tertiary-fixed": "#ffdea3",
                    "surface-variant": "#e2e0fc",
                    "surface-container-high": "#e8e5ff",
                    "surface-container": "#efecff",
                    "surface-container-lowest": "#ffffff",
                    "on-background": "#1a1a2e",
                    "surface-dim": "#dad7f3",
                    "surface-bright": "#fcf8ff",
                    "primary-fixed-dim": "#acc7ff",
                    "secondary": "#006a60",
                    "on-primary": "#ffffff",
                    "inverse-primary": "#acc7ff",
                    "brand-teal": "#2A9D8F",
                    "brand-teal-light": "#E8F7F5"
                },
                "borderRadius": {
                    "DEFAULT": "0.125rem",
                    "lg": "0.25rem",
                    "xl": "0.5rem",
                    "full": "0.75rem"
                },
                "spacing": {
                    "margin-desktop": "64px",
                    "container-max": "1280px",
                    "touch-target": "48px",
                    "margin-mobile": "16px",
                    "gutter": "24px",
                    "unit": "8px"
                },
                "fontFamily": {
                    "headline-sm": ["Poppins"],
                    "headline-md": ["Poppins"],
                    "arabic-h1": ["Cairo"],
                    "body-md": ["Inter"],
                    "display-lg": ["Poppins"],
                    "display-lg-mobile": ["Poppins"],
                    "body-lg": ["Inter"],
                    "label-md": ["Inter"],
                    "arabic-body": ["Cairo"]
                },
                "fontSize": {
                    "headline-sm": ["24px", {"lineHeight": "1.3", "fontWeight": "600"}],
                    "headline-md": ["32px", {"lineHeight": "1.3", "fontWeight": "600"}],
                    "arabic-h1": ["36px", {"lineHeight": "1.4", "fontWeight": "700"}],
                    "body-md": ["18px", {"lineHeight": "1.6", "fontWeight": "400"}],
                    "display-lg": ["48px", {"lineHeight": "1.2", "fontWeight": "700"}],
                    "display-lg-mobile": ["36px", {"lineHeight": "1.2", "fontWeight": "700"}],
                    "body-lg": ["20px", {"lineHeight": "1.6", "fontWeight": "400"}],
                    "label-md": ["16px", {"lineHeight": "1.4", "letterSpacing": "0.02em", "fontWeight": "600"}],
                    "arabic-body": ["20px", {"lineHeight": "1.8", "fontWeight": "400"}]
                }
            }
        }
    }
</script>

<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .pulse-animation {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(0.98); }
    }
    .active-glow {
        box-shadow: 0 0 15px rgba(0, 106, 96, 0.1);
    }
</style>
