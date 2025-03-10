<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation;

use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Internal\BaseOperation;
use FiiSoft\Jackdaw\Operation\Internal\Limitable;

final class Limit extends BaseOperation implements Limitable
{
    private int $limit;
    private int $count = 0;
    
    public function __construct(int $limit)
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException('Invalid param limit');
        }
    
        $this->limit = $limit;
    }
    
    public function handle(Signal $signal): void
    {
        if ($this->limit === 0) {
            $signal->limitReached($this);
        } else {
            if ($this->count === $this->limit) {
                $signal->stop();
                return;
            }
            
            if (++$this->count === $this->limit) {
                $signal->limitReached($this);
            }
    
            $this->next->handle($signal);
        }
    }
    
    public function applyLimit(int $limit): bool
    {
        $this->limit = \min($this->limit, $limit);
        
        return true;
    }
    
    public function limit(): int
    {
        return $this->limit;
    }
}