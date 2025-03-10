<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Reducer;

use FiiSoft\Jackdaw\Condition\Condition;
use FiiSoft\Jackdaw\Discriminator\Discriminator;
use FiiSoft\Jackdaw\Filter\Filter;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Mapper\Mapper;
use FiiSoft\Jackdaw\Predicate\Predicate;
use FiiSoft\Jackdaw\Reducer\Internal\MultiReducer;

final class Reducers
{
    /**
     * @param Reducer|callable|array $reducer
     */
    public static function getAdapter($reducer): Reducer
    {
        if ($reducer instanceof Reducer) {
            return $reducer;
        }
    
        if (\is_callable($reducer)) {
            if (\is_string($reducer)) {
                switch ($reducer) {
                    case 'min':
                    case '\min':
                        return self::min();
                    case 'max':
                    case '\max':
                        return self::max();
                    case 'array_sum':
                    case '\array_sum':
                        return self::sum();
                    case 'implode':
                    case '\implode':
                        return self::concat();
                    case 'count':
                    case '\count':
                        return self::count();
                    default:
                        //noop
                }
            }
            
            return self::generic($reducer);
        }
        
        if (\is_array($reducer)) {
            return new MultiReducer($reducer);
        }
    
        throw new \InvalidArgumentException('Invalid param reducer');
    }
    
    /**
     * @param callable $reducer This accepts two arguments: accumulator and current value
     */
    public static function generic(callable $reducer): Reducer
    {
        return new GenericReducer($reducer);
    }
    
    public static function sum(): Reducer
    {
        return new Sum();
    }
    
    public static function min(): Reducer
    {
        return new Min();
    }
    
    public static function max(): Reducer
    {
        return new Max();
    }
    
    public static function minMax(): Reducer
    {
        return new MinMax();
    }
    
    public static function average(?int $roundPrecision = null): Reducer
    {
        return new Average($roundPrecision);
    }
    
    public static function concat(string $separator = ''): Reducer
    {
        return new Concat($separator);
    }
    
    public static function longest(): Reducer
    {
        return new Longest();
    }
    
    public static function shortest(): Reducer
    {
        return new Shortest();
    }
    
    public static function count(): Reducer
    {
        return new Count();
    }
    
    /**
     * @param Discriminator|Condition|Predicate|Filter|Mapper|callable|array|null $discriminator
     */
    public static function countUnique($discriminator = null, int $mode = Check::VALUE): Reducer
    {
        return new CountUnique($discriminator, $mode);
    }
}