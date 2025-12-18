<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

return [
    'usercan' => fn (string $expression): string => sprintf('<?php if( User::current()->can(%s) ): ?>', $expression),
    'endusercan' => fn (): string => '<?php endif; ?>',
    'template' => function ($expression): string {
        $args = array_map(fn ($arg): string => trim($arg, '\/\'\" ()'), explode(',', (string) $expression));

        if (isset($args[1]) && is_callable($args[1])) {
            $args[1] = call_user_func($args[1]);
        }

        $path = isset($args[1]) ? $args[0].'-'.$args[1] : $args[0];

        $data = count($args) === 3 ? array_pop($args) : '[]';

        return sprintf('<?php if ($__env->exists(\'%s\')) { echo $__env->make(\'%s\', %s, '.\Illuminate\Support\Arr::class.'::except(get_defined_vars(), [\'__data\', \'__path\']))->render(); } else { echo $__env->make(\'%s\', %s, '.\Illuminate\Support\Arr::class."::except(get_defined_vars(), ['__data', '__path']))->render(); } ?>", $path, $path, $data, $args[0], $data);
    },
    'gravityform' => function (string $expression): string {
        if (! function_exists('gravity_form')) {
            return '<div><b>Gravity form is not installed or activated</b></div>';
        }

        return sprintf('<?php gravity_form(%s); ?>', $expression);
    },
];
