<?php

namespace Coderden\Comments\Contracts;

interface Commentable
{
    /**
     * Get all comments for this model
     */
    public function comments();
    
    /**
     * Check if commenting is allowed for this model
     */
    public function canBeCommented(): bool;
    
    /**
     * Get the URL where comments are displayed
     */
    public function getCommentsUrl(): string;
}