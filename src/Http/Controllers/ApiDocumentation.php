<?php

namespace Cronqvist\Api\Http\Controllers;

use Cronqvist\Api\Services\OpenApi\GenerateDoc;

class ApiDocumentation
{
    /**
     * OpenAPI 3.0 endpoint
     *
     * A route is not provided for this controller. If you want to use this one, please add a route in 'routes/api.php'.
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function index()
    {
        $doc = app()->make(GenerateDoc::class);
        return response()->json($doc->toArray());
    }
}
