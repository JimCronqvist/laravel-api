<?php

namespace Cronqvist\Api\Exception;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class SafeExceptionResponse
{
    protected ?Throwable $exception = null;

    protected array $headersToRemove = [
        'set-cookie',
        'x-debug-token',
        'x-debug-token-link',
        'content-type',
        'content-length',
        'content-encoding',
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
        (new RegisterErrorViewPaths)();

        $status = $response->getStatusCode() ?: 500;
        $message = Response::$statusTexts[$status] ?? 'Server Error';

        if($this->exception) {
            $message = $this->exception->getMessage();
        }

        $view = $this->errorView($status);

        $exception = new HttpException($status, $message);
        return new Response(
            View::make($view, [
                'exception' => $exception,
            ])->render(),
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    protected function errorView(int $status): string
    {
        $view = "errors::{$status}";

        if (!view()->exists($view)) {
            $view = 'errors::'.substr((string) $status, 0, 1).'xx';
        }

        if (!view()->exists($view)) {
            $view = 'errors::500';
        }

        return $view;
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

    public function setException(Throwable $exception): static
    {
        $this->exception = $exception;

        return $this;
    }
}
