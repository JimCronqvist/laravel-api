<?php

namespace Cronqvist\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Exception;

class ApiMergeController extends BaseController
{
    /**
     * HTTP status code to be returned in the response
     *
     * @var int
     */
    protected $statusCode = 200;


    /**
     * Send multiple requests for resources in a single request
     *
     * Examples:
     * GET /api/merge?request[]=/api/user/1&request[]=/api/user/2
     *
     * POST/PUT/PATCH/DELETE /api/merge
     * {
     *   "request": [
     *      {
     *          "url": "/api/users/1",
     *          "data": { "name": "Test" }
     *      },
     *      ...
     *   ]
     * }
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function merge(Request $request)
    {
        $reqs = (array) $request->input('request', []);
        if(!count($reqs)) {
            throw new BadRequestHttpException("Missing required parameter 'request'.");
        }
        if(count($reqs) > 50) {
            throw new BadRequestHttpException("Too many requests are being passed on.");
        }

        $responses = [];
        foreach($reqs as $key => $req) {
            try {
                list($url, $query, $data) = $this->parseRequest($req);
            } catch (Exception $exception) {
                throw new BadRequestHttpException('Could not parse the request.', $exception);
            }

            $responses[$key] = $this->request($url, $query, $data);
        }

        return response()->json(['data' => $responses], $this->statusCode);
    }

    protected function request($url, $query, $data)
    {
        $fakeRequest = request()->duplicate($query, $data, null, null, null, [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
                'REQUEST_URI' => $url
            ] + request()->server->all()
        );
        $fakeRequest->setRequestFormat('application/json');
        $fakeRequest->setJson(new ParameterBag($data));

        /** @var $response JsonResponse */
        $response = app()->handle($fakeRequest);

        if($response->exception) {
            return $this->convertExceptionToArray($response->exception);
        }

        return $response->getOriginalContent();
    }

    protected function parseRequest($value) : array
    {
        $url = (string) (is_array($value) ? ($value['url'] ?? null) : $value);
        $data = (array) (is_array($value) ? ($value['data'] ?? []) : []);

        $url = rtrim(parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY), '?');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return [$url, $query, $data];
    }

    /**
     * Convert the given exception to an array.
     *
     * @param  \Exception  $e
     * @return array
     */
    protected function convertExceptionToArray(Exception $e)
    {
        $this->statusCode = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        return config('app.debug') ? [
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => collect($e->getTrace())->map(function ($trace) {
                return Arr::except($trace, ['args']);
            })->all(),
        ] : [
            'message' => $e instanceof HttpExceptionInterface ? $e->getMessage() : 'Server Error',
        ];
    }
}