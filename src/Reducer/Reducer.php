<?php

namespace FiiSoft\Jackdaw\Reducer;

use FiiSoft\Jackdaw\Internal\ResultProvider;

interface Reducer extends ResultProvider
{
    /**
     * @param mixed $value
     * @return void
     */
    public function consume($value);
    
    /**
     * @return mixed|null
     */
    public function result();
}