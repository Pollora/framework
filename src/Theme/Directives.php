<?php

declare(strict_types=1);

namespace Pollen\Theme;

return [
    'usercan' => fn ($expression) => "<?php if( User::current()->can({$expression}) ): ?>",
    'endusercan' => fn () => '<?php endif; ?>',
    'loop' => fn () => '<?php if (have_posts()) { while (have_posts()) { the_post(); ?>',
    'endloop' => fn () => '<?php }} ?>',
    'template' => function ($expression) {
        $args = array_map(fn ($arg) => trim($arg, '\/\'\" ()'), explode(',', $expression));

        if (isset($args[1]) && is_callable($args[1])) {
            $args[1] = call_user_func($args[1]);
        }
        $path = isset($args[1]) ? $args[0].'-'.$args[1] : $args[0];

        $data = 3 === count($args) ? array_pop($args) : '[]';

        return "<?php if (\$__env->exists('{$path}')) { echo \$__env->make('{$path}', {$data}, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); } else { echo \$__env->make('{$args[0]}', {$data}, \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); } ?>";
    },
    'gravityform' => function ($expression) {
        if (function_exists('gravity_form')) {
            return '<div><b>Gravity form is not installed or activated</b></div>';
        }

        return "<?php gravity_form({$expression}); ?>";
    },
];
