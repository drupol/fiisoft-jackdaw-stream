<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation\Terminating;

use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Internal\ResultProvider;
use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Internal\FinalOperation;
use FiiSoft\Jackdaw\Reducer\Reducer;
use FiiSoft\Jackdaw\Reducer\Reducers;
use FiiSoft\Jackdaw\Stream;

final class Fold extends FinalOperation implements ResultProvider
{
    /** @var Reducer */
    private $reducer;
    
    /**
     * @param Stream $stream
     * @param mixed $initial
     * @param Reducer|callable $reducer
     */
    public function __construct(Stream $stream, $initial, $reducer)
    {
        $this->reducer = Reducers::getAdapter($reducer);
        $this->reducer->consume($initial);
        
        parent::__construct($stream, $this->reducer, null);
    }
    
    public function handle(Signal $signal)
    {
        $this->reducer->consume($signal->item->value);
    }
    
    public function hasResult(): bool
    {
        return true;
    }
    
    public function getResult(): Item
    {
        return $this->reducer->getResult();
    }
}