<?php

namespace Cronqvist\Api\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ApiMergeController extends BaseController
{
    public function merge(Request $request)
    {
        $reqs = (array) $request->input('request', []);
        if(!count($reqs)) {
            throw new BadRequestHttpException("Missing required parameter 'request'.");
        }

        $responses = [];
        foreach($reqs as $key => $req) {
            list($url, $data) = $this->parseRequest($req);
            $fakeRequest = Request::create($url, $request->getMethod(), $data);
            $response = app()->handle($fakeRequest);
            /** @var $response JsonResponse */
            $responses[$key] = $response->getOriginalContent();
        }
        return ['data' => $responses];
    }

    protected function parseRequest($value) : array
    {
        $url = (string) (is_object($value) ? ($value['url'] ?? null) : $value);
        $data = (array) (is_object($value) ? ($value['data'] ?? []) : []);

        $url = rtrim(parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY), '?');

        return [$url, $data];
    }
}