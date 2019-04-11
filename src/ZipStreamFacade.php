<?php

namespace STS\ZipStream;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Stechstudio\LaravelZipstream\Skeleton\SkeletonClass
 */
class ZipStreamFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'zipstream';
    }
}
