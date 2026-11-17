{{-- Vistas del médico: usan utilidades Tailwind (CDN) dentro del layout común, sin el reset (preflight)
     para no alterar el estilo base del sistema. La escala "emerald" se mapea a la paleta CIES. --}}
<style>
    :where(.vista-medico) [class*="border"] { border-style: solid; border-width: 0; }
    :where(.vista-medico) button { background: transparent; border: 0; font: inherit; color: inherit; cursor: pointer; }
    :where(.vista-medico) input, :where(.vista-medico) select, :where(.vista-medico) textarea { font: inherit; }
    :where(.vista-medico) .grid { margin-bottom: 0; gap: 0; }
    :where(.vista-medico) h1, :where(.vista-medico) h2, :where(.vista-medico) h3 { margin-bottom: 0; }
</style>
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        corePlugins: { preflight: false },
        theme: {
            extend: {
                colors: {
                    cies: '#4c7d5f',
                    ciesdark: '#3b6449',
                    naranja: '#cc783c',
                    bglight: '#f6f8f7',
                    borderlight: '#e2e8f0',
                    emerald: {
                        50: '#eef5f0', 100: '#dcebe1', 200: '#bcd8c5', 300: '#93bda1', 400: '#6a9c7c',
                        500: '#4c7d5f', 600: '#43704f', 700: '#3b6449', 800: '#2d4c39', 900: '#24402f',
                    },
                },
            },
        },
    };
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
