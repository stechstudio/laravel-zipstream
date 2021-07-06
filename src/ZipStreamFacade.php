<?php

namespace STS\ZipStream;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \STD\ZipStream\ZipStream create(string|null $name, array $files = [])
 *
 * @see \STS\ZipStream\ZipStream
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
