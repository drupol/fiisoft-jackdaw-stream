<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Mapper;

use FiiSoft\Jackdaw\Internal\Helper;

final class GenericMapper implements Mapper
{
    /** @var callable */
    private $mapper;
    
    /** @var int */
    private $numOfArgs;
    
    public function __construct(callable $mapper)
    {
        $this->mapper = $mapper;
        $this->numOfArgs = Helper::getNumOfArgs($mapper);
    }
    
    public function map($value, $key)
    {
        $map = $this->mapper;
    
        switch ($this->numOfArgs) {
            case 1:
                return $map($value);
            case 2:
                return $map($value, $key);
            default:
                throw Helper::wrongNumOfArgsException('Mapper', $this->numOfArgs, 1, 2);
        }
    }
}