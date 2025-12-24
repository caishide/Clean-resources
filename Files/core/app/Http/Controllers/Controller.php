<?php

namespace App\Http\Controllers;

use Laramin\Utility\Onumoti;

abstract class Controller
{
    public function __construct()
    {
        $className = get_called_class();
        Onumoti::mySite($this,$className);
    }

    /**
     * Get the middleware groups that should be applied to the controller.
     *
     * @return array<int, class-string|string>
     */
    public static function middleware(): array
    {
        return [];
    }

}
