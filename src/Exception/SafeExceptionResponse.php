<?php

namespace Cronqvist\Api\Exception;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SafeExceptionResponse
{
    protected array $headersToRemove = [
        'set-cookie',
        'x-debug-token',
        'x-debug-token-link',
    ];

    public function __invoke(Response $response): Response
    {
        if ($response instanceof JsonResponse) {
            return $this->json($response);
        }

        if ($this->isHtml($response)) {
            return $this->html($response);
        }

        return $this->fallback($response);
    }

    protected function json(JsonResponse $response): JsonResponse
    {
        $data = $response->getData(true);

        // Ensure nothing else unsafe for the browser exists in the trace. For example from Datadog.
        if (isset($data['trace']) && is_array($data['trace'])) {
            $data['trace'] = array_map(
                fn (array $frame) => [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'class' => $frame['class'] ?? null,
                    'function' => $frame['function'] ?? null,
                ],
                $data['trace'],
            );
        }

        return new JsonResponse($data, $response->getStatusCode(), $this->safeHeaders($response));
    }

    protected function html(Response $response): Response
    {
        return response()->view($this->errorView($response), [
            'status' => $response->getStatusCode() ?? 500,
            'exception' => null,
        ], $response->getStatusCode(), $this->safeHeaders($response));
    }

    protected function errorView(Response $response): string
    {
        $status = $response->getStatusCode();
        foreach (["errors::{$status}", 'errors::minimal', 'errors::500'] as $view) {
            if (view()->exists($view)) {
                return $view;
            }
        }

        return 'errors::500';
    }

    protected function fallback(Response $response): Response
    {
        $status = $response->getStatusCode();

        if ($this->wantsJson($response)) {
            return new JsonResponse(
                [
                    'message' => $status >= 500 ? 'Server Error' : Response::$statusTexts[$status] ?? 'Error',
                ],
                $status,
                $this->safeHeaders($response)
            );
        }

        return new Response($status >= 500 ? 'Server Error' : Response::$statusTexts[$status] ?? 'Error', $status, $this->safeHeaders($response));
    }

    protected function safeHeaders(Response $response): array
    {
        return collect($response->headers->all())
            ->except($this->headersToRemove)
            ->all();
    }

    protected function isHtml(Response $response): bool
    {
        return Str::contains(strtolower($response->headers->get('Content-Type', '')), 'text/html');
    }

    protected function wantsJson(Response $response): bool
    {
        return Str::contains(strtolower($response->headers->get('Content-Type', '')), 'json');
    }
}
