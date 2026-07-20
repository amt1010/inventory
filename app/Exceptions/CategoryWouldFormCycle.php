<?php

namespace App\Exceptions;

use App\Models\Category;
use RuntimeException;

/**
 * Thrown when a category is about to be saved with a parent that would make it
 * (directly or transitively) its own ancestor. The Filament forms already
 * exclude such options from the parent select; this is the model-level backstop
 * that keeps a cycle out of the database no matter how the save is triggered.
 */
class CategoryWouldFormCycle extends RuntimeException
{
    public static function for(Category $category): self
    {
        $name = filled($category->name) ? $category->name : 'This category';

        return new self("“{$name}” cannot be its own ancestor — choose a different parent category.");
    }
}
