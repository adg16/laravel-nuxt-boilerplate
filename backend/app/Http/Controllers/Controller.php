<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Escape LIKE wildcards so user-supplied filter text is matched literally.
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
