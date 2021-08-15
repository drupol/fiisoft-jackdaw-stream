<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Operation;

use FiiSoft\Jackdaw\Comparator\Comparator;
use FiiSoft\Jackdaw\Comparator\Comparators;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Internal\Signal;
use FiiSoft\Jackdaw\Operation\Internal\BaseOperation;
use FiiSoft\Jackdaw\Operation\Internal\Limitable;
use FiiSoft\Jackdaw\Operation\State\SortLimited\BufferNotFull;
use FiiSoft\Jackdaw\Operation\State\SortLimited\State;
use SplHeap;
use SplMaxHeap;
use SplMinHeap;

final class SortLimited extends BaseOperation implements Limitable
{
    /** @var Comparator|null  */
    private $comparator = null;
    
    /** @var bool */
    private $reversed;
    
    /** @var int */
    private $mode;
    
    /** @var int */
    private $limit;
    
    /** @var SplHeap<Item> */
    private $items;
    
    /** @var State */
    private $state;
    
    /**
     * @param int $limit
     * @param Comparator|callable|null $comparator
     * @param int $mode
     * @param bool $reversed
     */
    public function __construct(
        int $limit,
        $comparator = null,
        int $mode = Check::VALUE,
        bool $reversed = false
    ) {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Invalid param limit');
        }
        
        $this->comparator = Comparators::getAdapter($comparator);
        $this->mode = Check::getMode($mode);
        $this->limit = $limit;
        $this->reversed = $reversed;
        
        $this->prepareToWork();
    }
    
    public function handle(Signal $signal)
    {
        $this->state->hold($signal->item);
    }
    
    public function streamingFinished(Signal $signal)
    {
        $signal->restartFrom($this->next, \array_reverse(\iterator_to_array($this->items, false)));
    }
    
    public function reverseOrder()
    {
        $this->reversed = !$this->reversed;
        $this->prepareToWork();
    }
    
    private function prepareToWork()
    {
        $this->items = $this->createHeap();
        $this->state = new BufferNotFull($this, $this->items, $this->limit);
    }
    
    private function createHeap(): SplHeap
    {
        switch ($this->mode) {
            case Check::VALUE:
                if ($this->comparator === null) {
                    if ($this->reversed) {
                        return new class extends SplMinHeap {
                            /**
                             * @param Item $value1
                             * @param Item $value2
                             * @return int
                             */
                            public function compare($value1, $value2) {
                                return $value2->value <=> $value1->value;
                            }
                        };
                    }
                    
                    return new class extends SplMaxHeap {
                        /**
                         * @param Item $value1
                         * @param Item $value2
                         * @return int
                         */
                        public function compare($value1, $value2) {
                            return $value1->value <=> $value2->value;
                        }
                    };
                }
                
                if ($this->reversed) {
                    return new class ($this->comparator) extends SplMinHeap {
                        /** @var Comparator */
                        private $comparator;
                        
                        public function __construct(Comparator $comparator) {
                            $this->comparator = $comparator;
                        }
                        
                        /**
                         * @param Item $value1
                         * @param Item $value2
                         * @return int
                         */
                        public function compare($value1, $value2) {
                            return $this->comparator->compare($value2->value, $value1->value);
                        }
                    };
                }
                
                return new class ($this->comparator) extends SplMaxHeap {
                    /** @var Comparator */
                    private $comparator;
                    
                    public function __construct(Comparator $comparator) {
                        $this->comparator = $comparator;
                    }
                    
                    /**
                     * @param Item $value1
                     * @param Item $value2
                     * @return int
                     */
                    public function compare($value1, $value2) {
                        return $this->comparator->compare($value1->value, $value2->value);
                    }
                };
            
            case Check::KEY:
                if ($this->comparator === null) {
                    if ($this->reversed) {
                        return new class extends SplMinHeap {
                            /**
                             * @param Item $value1
                             * @param Item $value2
                             * @return int
                             */
                            public function compare($value1, $value2) {
                                return $value2->key <=> $value1->key;
                            }
                        };
                    }
                    
                    return new class extends SplMaxHeap {
                        /**
                         * @param Item $value1
                         * @param Item $value2
                         * @return int
                         */
                        public function compare($value1, $value2) {
                            return $value1->key <=> $value2->key;
                        }
                    };
                }
                
                if ($this->reversed) {
                    return new class ($this->comparator) extends SplMinHeap {
                        /** @var Comparator */
                        private $comparator;
                        
                        public function __construct(Comparator $comparator) {
                            $this->comparator = $comparator;
                        }
                        
                        /**
                         * @param Item $value1
                         * @param Item $value2
                         * @return int
                         */
                        public function compare($value1, $value2) {
                            return $this->comparator->compare($value2->key, $value1->key);
                        }
                    };
                }
                
                return new class ($this->comparator) extends SplMaxHeap {
                    /** @var Comparator */
                    private $comparator;
                    
                    public function __construct(Comparator $comparator) {
                        $this->comparator = $comparator;
                    }
                    
                    /**
                     * @param Item $value1
                     * @param Item $value2
                     * @return int
                     */
                    public function compare($value1, $value2) {
                        return $this->comparator->compare($value1->key, $value2->key);
                    }
                };
            
            default:
                if ($this->comparator === null) {
                    if ($this->reversed) {
                        return new class extends SplMinHeap {
                            /**
                             * @param Item $value1
                             * @param Item $value2
                             * @return int
                             */
                            public function compare($value1, $value2) {
                                return $value2->value <=> $value1->value ?: $value2->key <=> $value1->key;
                            }
                        };
                    }
                    
                    return new class extends SplMaxHeap {
                        /**
                         * @param Item $value1
                         * @param Item $value2
                         * @return int
                         */
                        public function compare($value1, $value2) {
                            return $value1->value <=> $value2->value ?: $value1->key <=> $value2->key;
                        }
                    };
                }
                
                if ($this->reversed) {
                    return new class ($this->comparator) extends SplMinHeap {
                        /** @var Comparator */
                        private $comparator;
                        
                        public function __construct(Comparator $comparator) {
                            $this->comparator = $comparator;
                        }
                        
                        /**
                         * @param Item $value1
                         * @param Item $value2
                         * @return int
                         */
                        public function compare($value1, $value2) {
                            return $this->comparator->compareAssoc(
                                $value2->value, $value1->value, $value2->key, $value1->key
                            );
                        }
                    };
                }
                
                return new class ($this->comparator) extends SplMaxHeap {
                    /** @var Comparator */
                    private $comparator;
                    
                    public function __construct(Comparator $comparator) {
                        $this->comparator = $comparator;
                    }
                    
                    /**
                     * @param Item $value1
                     * @param Item $value2
                     * @return int
                     */
                    public function compare($value1, $value2) {
                        return $this->comparator->compareAssoc(
                            $value1->value, $value2->value, $value1->key, $value2->key
                        );
                    }
                };
        }
    }
    
    public function applyLimit(int $limit)
    {
        $this->limit = \min($this->limit, $limit);
        $this->state->setLength($this->limit);
    }
    
    public function limit(): int
    {
        return $this->limit;
    }
    
    public function transitTo(State $state)
    {
        $this->state = $state;
    }
}