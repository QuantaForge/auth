<?php

namespace QuantaForge\Auth\Events;

use QuantaForge\Http\Request;

class Lockout
{
    /**
     * The throttled request.
     *
     * @var \QuantaForge\Http\Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param  \QuantaForge\Http\Request  $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
