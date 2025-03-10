<?php declare(strict_types=1);

namespace FiiSoft\Test\Jackdaw;

use FiiSoft\Jackdaw\Comparator\Comparators;
use FiiSoft\Jackdaw\Comparator\GenericComparator;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Internal\Helper;
use FiiSoft\Jackdaw\Internal\Item;
use FiiSoft\Jackdaw\Operation\Segregate\Bucket;
use FiiSoft\Jackdaw\Operation\Strategy\Unique\FullAssocChecker;
use PHPUnit\Framework\TestCase;

final class OtherTest extends TestCase
{
    public function test_iterate_over_nested_arrays_without_oryginal_keys(): void
    {
        $arr = ['a', ['b', 'c',], 'd', ['e', ['f', ['g', 'h',], 'i'], [[['j'], 'k',], 'l',], 'm',], 'n'];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($arr),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        $flatten = \iterator_to_array($iterator, false);
        $asString = \implode(',', $flatten);
        
        self::assertSame('a,b,c,d,e,f,g,h,i,j,k,l,m,n', $asString);
    }
    
    public function test_Check_can_validate_param_mode(): void
    {
        self::assertSame(Check::VALUE, Check::getMode(Check::VALUE));
        self::assertSame(Check::KEY, Check::getMode(Check::KEY));
        self::assertSame(Check::BOTH, Check::getMode(Check::BOTH));
        self::assertSame(Check::ANY, Check::getMode(Check::ANY));
    }
    
    public function test_Check_throws_exception_when_param_mode_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        Check::getMode(0);
    }
    
    public function test_how_ArrayObject_handles_isset_on_null_values(): void
    {
        $data = ['a' => 5, 'b' => null];
        $obj = new \ArrayObject($data);
        
        self::assertTrue(isset($obj['a']));
        self::assertFalse(isset($obj['b']));
        self::assertFalse(isset($obj['c']));
        
        self::assertNotEmpty($obj['a']);
        self::assertEmpty($obj['b']);
        self::assertTrue(empty($obj['c']));
        
        self::assertTrue($obj->offsetExists('a'));
        self::assertTrue($obj->offsetExists('b'));
        self::assertFalse($obj->offsetExists('c'));
    }
    
    public function test_how_SplHeap_acts(): void
    {
        $heap = new class extends \SplHeap {
            protected function compare($value1, $value2) {
                return $value2 <=> $value1;
            }
        };
    
        foreach ([6,2,8,4,5,9,1,7,3] as $value) {
            $heap->insert($value);
        }
        
        self::assertSame(9, $heap->count());
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], \iterator_to_array($heap, false));
        
        self::assertEmpty(\iterator_to_array($heap, false));
    
        self::assertSame(0, $heap->count());
        $heap->rewind();
        self::assertSame(0, $heap->count());
        
        self::assertEmpty(\iterator_to_array($heap, false));
        self::assertFalse($heap->isCorrupted());
        
        foreach ([6,2,8,4,5,9,1,7,3] as $value) {
            $heap->insert($value);
        }
    
        self::assertSame(9, $heap->count());
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], \iterator_to_array($heap, false));
    
        self::assertFalse($heap->isCorrupted());
    
    
        foreach ([6,2,8,4,5,9,1,7,3] as $value) {
            $heap->insert($value);
        }
    
        self::assertSame(9, $heap->count());
        
        self::assertSame(1, $heap->top());
        self::assertSame(9, $heap->count());
        
        self::assertSame(1, $heap->extract());
        self::assertSame(8, $heap->count());
    }
    
    public function test_reversed_limited_sort_with_SplHeap(): void
    {
        $heap = new class extends \SplHeap {
            public function compare($value2, $value1) {
                return $value1 <=> $value2;
            }
        };
    
        foreach (['b', 'a', 'd'] as $value) {
            $heap->insert($value);
        }
    
        foreach (['c', 'e'] as $value) {
            if ($heap->compare($value, $heap->top()) < 0) {
                $heap->extract();
                $heap->insert($value);
            }
        }
    
        self::assertSame(3, $heap->count());
        $result = \array_reverse(\iterator_to_array($heap, false));
        
        self::assertSame(['e', 'd', 'c'], $result);
    }
    
    public function test_straight_limited_sort_with_SplHeap(): void
    {
        $heap = new class extends \SplHeap {
            public function compare($value1, $value2) {
                return $value1 <=> $value2;
            }
        };
    
        foreach (['b', 'a', 'd'] as $value) {
            $heap->insert($value);
        }
    
        foreach (['c', 'e'] as $value) {
            if ($heap->compare($value, $heap->top()) < 0) {
                $heap->extract();
                $heap->insert($value);
            }
        }
    
        self::assertSame(3, $heap->count());
        $result = \array_reverse(\iterator_to_array($heap, false));
        
        self::assertSame(['a', 'b', 'c'], $result);
    }
    
    public function test_what_is_type_of_php_int_max_divided_by_small_number(): void
    {
        $divisors = [
            1 => 'integer', 2 => 'double', 3 => 'double', 4 => 'double', 5 => 'double', 6 => 'double',
            7 => 'integer', 8 => 'double', 9 => 'double', 10 => 'double', 11 => 'double', 12 => 'double',
            13 => 'double',  14 => 'double', 15 => 'double', 16 => 'double', 17 => 'double', 18 => 'double',
            19 => 'double',  20 => 'double', 21 => 'double', 22 => 'double', 23 => 'double', 24 => 'double',
            49  => 'integer', 73 => 'integer', 127 => 'integer', 337 => 'integer', 511 => 'integer',
            889 => 'integer', 2359 => 'integer', 3576 => 'double', 3577 => 'integer', 3578 => 'double',
        ];
    
        foreach ($divisors as $divisor => $type) {
            $actual = \PHP_INT_MAX / $divisor;
            self::assertSame($type, \gettype($actual), 'number: '.$divisor);
    
            if ($type === 'integer') {
                self::assertIsInt($actual);
            } else {
                self::assertIsFloat($actual);
            }
        }
    }
    
    public function test_how_SplFixedArray_works(): void
    {
        $obj = new \SplFixedArray(15);
        
        self::assertSame(15, $obj->count());
        self::assertSame(15, $obj->getSize());
        
        $obj[0] = 'a';
    
        self::assertSame(15, $obj->count());
        self::assertSame(15, $obj->getSize());
    }
    
    public function test_Helper_has_method_to_create_exception_with_message_that_describes_problem(): void
    {
        self::assertSame(
            'Something have to accept 0 arguments, but requires 1',
            Helper::wrongNumOfArgsException('Something', 1)->getMessage()
        );
        
        self::assertSame(
            'Something have to accept 0 arguments, but requires 1',
            Helper::wrongNumOfArgsException('Something', 1, 0)->getMessage()
        );
        
        self::assertSame(
            'Something have to accept 1 arguments, but requires 0',
            Helper::wrongNumOfArgsException('Something', 0, 1)->getMessage()
        );
        
        self::assertSame(
            'Something have to accept 1 or 2 arguments, but requires 0',
            Helper::wrongNumOfArgsException('Something', 0, 1, 2)->getMessage()
        );
    }
    
    public function test_use_count_as_callback_in_array_map(): void
    {
        $data = [
            'x' => ['a', 'c', 's'],
            'y' => ['g', 'j'],
            'z' => ['j', 'w', 'c', 'g'],
        ];
        
        $expected = ['x' => 3, 'y' => 2, 'z' => 4];
        self::assertSame($expected, \array_map('count', $data));
        self::assertSame($expected, \array_map('\count', $data));
    }
    
    public function test_compare_letter_a_with_digit_0_using_spaceship_operator(): void
    {
        if (\version_compare(\PHP_VERSION, '8.0.0') === -1) {
            // :(
            self::assertSame(0, 'a' <=> 0);
        } else {
            // :)
            self::assertSame(1, 'a' <=> 0);
        }
    }
    
    public function test_put_various_values_as_keys_in_array(): void
    {
        $arr = [
            true => 'a',
            false => 'b',
            3 => 'c',
            '15.55' => 'd',
            18.43 => 'e',
        ];
        
        self::assertSame([
            1 => 'a',
            0 => 'b',
            3 => 'c',
            '15.55' => 'd',
            18 => 'e'
        ], $arr);
    }
    
    public function test_Bucket_can_reindex_keys(): void
    {
        $bucket = new Bucket(true);
        $bucket->add(new Item('a', 'b'));
        
        self::assertSame(['b'], $bucket->data);
    }
    
    public function test_remove_elements_from_double_linked_list_of_buckets(): void
    {
        //given
        $second = new Bucket();
        $second->add(new Item(2, 'b'));
        
        $third = $second->append(new Item(3, 'c'));
        $first = $second->prepend(new Item(1, 'a'));
        
        self::assertSame([1 => 'a'], $first->data);
        self::assertSame([2 => 'b'], $second->data);
        self::assertSame([3 => 'c'], $third->data);
        
        //when
        $second->clear();
        
        //then
        self::assertEmpty($second->data);
        
        //when
        $first->clear();
        
        //then
        self::assertEmpty($first->data);
        
        //when
        $third->clear();
        
        //then
        self::assertEmpty($third->data);
    }
    
    public function test_usort(): void
    {
        $data = ['d', 'c', 'b', 'e', 'a'];
        \usort($data, static fn(string $a, string $b): int => $a <=> $b);
        
        self::assertSame(['a', 'b', 'c', 'd', 'e'], $data);
    }
    
    public function test_working_with_ArrayAccess(): void
    {
        $object = new class implements \ArrayAccess {
            private array $storage = [];
            
            public function offsetExists($offset): bool {
                return \array_key_exists($offset, $this->storage);
            }
            
            public function offsetGet($offset) {
                return $this->storage[$offset] ?? null;
            }
            
            public function offsetSet($offset, $value): void {
                $this->storage[$offset] = $value;
            }
            
            public function offsetUnset($offset): void {
                unset($this->storage[$offset]);
            }
        };
        
        self::assertFalse(isset($object['foo']));
        
        $object['foo'] = 'bar';
        
        self::assertTrue(isset($object['foo']));
        self::assertSame('bar', $object['foo']);
        
        $object['bar'] = null;
        
        self::assertTrue(isset($object['bar'])); //it depends on implementation
        self::assertNull($object['bar']);
    }
    
    public function test_working_with_ArrayObject(): void
    {
        $object = new \ArrayObject();
        
        self::assertFalse(isset($object['foo']));
        
        $object['foo'] = 'bar';
        
        self::assertTrue(isset($object['foo']));
        self::assertSame('bar', $object['foo']);
        
        $object['bar'] = null;
        
        self::assertFalse(isset($object['bar'])); //it depends on implementation
        self::assertNull($object['bar']);
    }
    
    public function test_FullAssocChecker_throws_exception_when_Comparator_is_invalid(): void
    {
        //Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FullAssocChecker can work only with four-argument callable');
        
        //Arrange
        $comparator = Comparators::getAdapter('is_string');
        self::assertInstanceOf(GenericComparator::class, $comparator);
        
        //Act
        new FullAssocChecker($comparator);
    }
}