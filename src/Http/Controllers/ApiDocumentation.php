<?php

namespace Cronqvist\Api\Http\Controllers;

use Cronqvist\Api\Services\OpenApi\GenerateDoc;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ApiDocumentation
{
    /**
     * OpenAPI 3.0 endpoint
     *
     * A route is not provided for this controller. If you want to use this one, please add a route in 'routes/api.php'.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function index(Request $request)
    {
        $doc = app()->make(GenerateDoc::class);
        $array = $doc->toArray();

        // Accept ?paths=/v3/api/users,/v3/api/customers* or ?paths[]=...
        $paths = collect(Arr::wrap($request->input('paths')))
            ->flatMap(fn ($v) => Str::of($v)->explode(','))
            ->map('trim')
            ->filter()
            ->unique();

        if($paths->isNotEmpty()) {
            $prefixes = $paths->filter(fn($p) => Str::endsWith($p, '*'))
                ->map(fn($p) => Str::beforeLast($p, '*'))
                ->values()->all();
            $exacts = $paths->reject(fn($p) => Str::endsWith($p, '*'))
                ->values()->all();

            $array['paths'] = collect($array['paths'])->filter(
                fn($value, $path) => in_array($path, $exacts) || Str::startsWith($path, $prefixes)
            )->all();
        }

        return response()->json($array);
    }
}
