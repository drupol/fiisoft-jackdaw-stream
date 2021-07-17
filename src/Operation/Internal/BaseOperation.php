<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation\Internal;

use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Operation;

abstract class BaseOperation implements Operation
{
    protected ?Operation $next = null;
    
    final public function setNext(Operation $next): Operation
    {
        if ($this->next !== null) {
            $next->setNext($this->next);
        }
        
        $this->next = $next;
        return $next;
    }
    
    public function streamingFinished(Signal $signal): void
    {
        $this->next->streamingFinished($signal);
    }
    
    public function isLazy(): bool
    {
        return false;
    }
}