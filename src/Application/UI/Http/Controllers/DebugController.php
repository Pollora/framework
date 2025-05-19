<?php

declare(strict_types=1);

namespace Pollora\Application\UI\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Pollora\Application\Application\Services\DebugService;

/**
 * Controller for debug-related functionality.
 */
class DebugController extends Controller
{
    /**
     * @var DebugService The debug service
     */
    private DebugService $debugService;

    /**
     * Constructor.
     *
     * @param  DebugService  $debugService  The debug service
     */
    public function __construct(DebugService $debugService)
    {
        $this->debugService = $debugService;
    }

    /**
     * Get the current debug status.
     *
     * @return JsonResponse Response with debug status
     */
    public function status(): JsonResponse
    {
        return new JsonResponse([
            'debug' => $this->debugService->isDebugMode(),
        ]);
    }
}
