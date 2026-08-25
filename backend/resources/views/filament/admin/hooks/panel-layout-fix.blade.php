<style>
    /* Filament hides .fi-main-ctn at opacity:0 until Alpine runs; show content if JS fails or loads late. */
    .fi-panel-admin .fi-main-ctn {
        display: flex !important;
        flex-direction: column;
        opacity: 1 !important;
        min-height: calc(100dvh - 4rem);
    }

    .fi-panel-admin .fi-sidebar-close-overlay[x-cloak] {
        display: none !important;
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var reveal = function () {
            document.querySelectorAll('.fi-panel-admin .fi-main-ctn').forEach(function (el) {
                el.style.display = 'flex';
                el.style.flexDirection = 'column';
                el.style.opacity = '1';
            });
        };

        reveal();
        document.addEventListener('livewire:init', reveal);
        setTimeout(reveal, 250);
        setTimeout(reveal, 1500);

        window.addEventListener('load', function () {
            if (typeof window.Livewire === 'undefined') {
                console.error(
                    'Livewire did not load. Hard-refresh with Ctrl+Shift+R or clear site data for 127.0.0.1:8000.',
                );
            }
        });
    });
</script>
