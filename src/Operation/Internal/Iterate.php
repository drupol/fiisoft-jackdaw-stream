<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation\Internal;

use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Internal\StreamIterator;

final class Iterate extends BaseOperation
{
    private StreamIterator $iterator;
    
    public function __construct(StreamIterator $iterator)
    {
        $this->iterator = $iterator;
    }
    
    public function handle(Signal $signal): void
    {
        $this->iterator->setItem($signal->item);
        
        $signal->interrupt();
    }
}