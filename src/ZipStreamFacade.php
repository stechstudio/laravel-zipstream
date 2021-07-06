<?php

namespace STS\ZipStream;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \STS\ZipStream\ZipStream create(?string $name = null, array $files = [])
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
