<script>
    (() => {
        const html = document.documentElement
        const storageKey = 'filament-theme'
        const fallbackKey = 'theme'

        const storedTheme =
            localStorage.getItem(storageKey) ??
            localStorage.getItem(fallbackKey) ??
            'dark'

        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
        const shouldUseDark = storedTheme === 'dark' || (storedTheme === 'system' && prefersDark)

        html.classList.toggle('dark', shouldUseDark)
    })()
</script>
