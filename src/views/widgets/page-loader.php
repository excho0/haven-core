<?php

/**
 * Render a loading skeleton block with dark mode support.
 *
 * @param array $args {
 *     Optional. Arguments for customizing the loader.
 *
 *     @type int    $count    Number of skeleton cards. Default 3.
 *     @type string $id       HTML ID for the wrapper. Default 'loading-skeleton'.
 *     @type string $on_ready JavaScript callback function name to call when loading is hidden.
 * }
 */
function havencore_render_loader(array $args = [])
{
    $count = $args['count'] ?? 3;
    $id = $args['id'] ?? 'loading-skeleton';
    $onReady = $args['on_ready'] ?? null;

    ob_start();
    ?>
    <style>
        @media (prefers-color-scheme: dark) {
            #<?= esc_attr($id) ?> .bg-white {
                background-color: #1f2937 !important; /* Tailwind's gray-800 */
            }
            #<?= esc_attr($id) ?> .bg-gray-200 {
                background-color: #374151 !important; /* Tailwind's gray-700 */
            }
            #<?= esc_attr($id) ?> .bg-gray-300 {
                background-color: #4b5563 !important; /* Tailwind's gray-600 */
            }
            #<?= esc_attr($id) ?> .shadow-sm,
            #<?= esc_attr($id) ?> .shadow {
                box-shadow: none !important;
            }
        }
    </style>

    <div id="<?= esc_attr($id) ?>">
        <!-- Dummy Header -->
        <div class="top-0 sm:top-10 md:top-10 lg:top-10 z-10 bg-white shadow-sm py-8 px-6 flex justify-between items-center"></div>

        <!-- Dummy Cards -->
        <div class="p-6 space-y-4 animate-pulse">
            <?php for ($i = 0; $i < $count; $i++): ?>
                <div class="bg-white shadow rounded-xl p-4 space-y-4">
                    <div class="h-5 bg-gray-200 rounded w-1/3"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                    </div>
                    <div class="h-10 bg-gray-300 rounded w-1/4 mt-4"></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            const loader = document.getElementById('<?= esc_js($id) ?>');
            if (loader) {
                loader.style.display = 'none';
                <?php if ($onReady): ?>
                    if (typeof <?= $onReady ?> === 'function') <?= $onReady ?>(loader);
                <?php endif; ?>
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
