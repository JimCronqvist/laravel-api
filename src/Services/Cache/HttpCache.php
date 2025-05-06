<?php

namespace Cronqvist\Api\Services\Cache;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use DateTimeInterface;

class HttpCache
{
    /**
     * Check if content has changed - to determine if a '304 Not Modified' response should be given
     *
     * @param \DateTimeInterface|string $lastModified
     * @param Request $request
     * @return bool
     */
    public static function isNotModified($updatedAt, Request $request = null): bool
    {
        $request = $request ?: request();
        $updatedAt = $updatedAt instanceof DateTimeInterface ? $updatedAt : Carbon::parse($updatedAt);
        return response()
            ->noContent()
            ->setLastModified($updatedAt)
            ->isNotModified($request);
    }

    /**
     * Return a 304 Not Modified response if content hasn't changed
     *
     * @return Response
     */
    public static function notModified(): Response
    {
        return response()->make(null, 304);
    }
}
