<?php declare(strict_types=1);

namespace FiiSoft\Test\Jackdaw;

use FiiSoft\Jackdaw\Collector\Collectors;
use FiiSoft\Jackdaw\Comparator\Comparators;
use FiiSoft\Jackdaw\Consumer\Consumer;
use FiiSoft\Jackdaw\Consumer\Consumers;
use FiiSoft\Jackdaw\Discriminator\Discriminator;
use FiiSoft\Jackdaw\Filter\Filter;
use FiiSoft\Jackdaw\Filter\Filters;
use FiiSoft\Jackdaw\Handler\OnError;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Internal\SignalHandler;
use FiiSoft\Jackdaw\Mapper\Internal\StatelessMapper;
use FiiSoft\Jackdaw\Mapper\Mappers;
use FiiSoft\Jackdaw\Operation\Internal\LastOperation;
use FiiSoft\Jackdaw\Predicate\Predicates;
use FiiSoft\Jackdaw\Producer\Producers;
use FiiSoft\Jackdaw\Reducer\Reducers;
use FiiSoft\Jackdaw\Stream;
use FiiSoft\Jackdaw\StreamMaker;
use PHPUnit\Framework\TestCase;

final class StreamTest extends TestCase
{
    public function test_toArray_numerical_by_default(): void
    {
        self::assertSame([1, 2, 3], Stream::from(['a' => 1, 'b' => 2, 'c' => 3])->toArray());
    }
    
    public function test_toArray_preserve_keys(): void
    {
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], Stream::from(['a' => 1, 'b' => 2, 'c' => 3])->toArray(true));
    }
    
    public function test_toArrayAssoc(): void
    {
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3], Stream::from(['a' => 1, 'b' => 2, 'c' => 3])->toArrayAssoc());
    }
    
    public function test_toArray_empty_stream(): void
    {
        self::assertSame([], Stream::empty()->toArray());
    }
    
    public function test_toString_default(): void
    {
        self::assertSame('1,2,3', Stream::from([1, 2, 3])->toString());
    }
    
    public function test_toString_custom_separator(): void
    {
        self::assertSame('1|2|3', Stream::from([1, 2, 3])->toString('|'));
    }
    
    public function test_toString_empty_string(): void
    {
        self::assertSame('', Stream::empty()->toString());
    }
    
    public function test_toJson_default(): void
    {
        self::assertSame('[1,"2"]', Stream::from(['a' => 1, '2'])->toJson());
    }
    
    public function test_toJson_with_flags(): void
    {
        self::assertSame('[1,2]', Stream::from([1, 2])->toJson(\JSON_NUMERIC_CHECK, true));
    }
    
    public function test_toJson_preserve_keys(): void
    {
        self::assertSame('{"a":1,"b":2}', Stream::from(['a' => 1, 'b' => 2])->toJson(0, true));
    }
    
    public function test_toJsonAssoc(): void
    {
        self::assertSame('{"a":1,"b":2}', Stream::from(['a' => 1, 'b' => 2])->toJsonAssoc());
    }
    
    public function test_toJson_drop_keys(): void
    {
        self::assertSame('[1,2]', Stream::from(['a' => 1, 'b' => 2])->toJson(0, false));
    }
    
    public function test_limit(): void
    {
        self::assertSame([1, 2], Stream::from([1, 2, 3, 4])->limit(2)->toArray());
    }
    
    public function test_skip(): void
    {
        self::assertSame([3, 4], Stream::from([1, 2, 3, 4])->skip(2)->toArray());
    }
    
    public function test_filter_notNull(): void
    {
        self::assertSame([1, 2], Stream::from([1, null, 2])->notNull()->toArray());
    }
    
    public function test_filter_noEmpty(): void
    {
        self::assertSame([1, 2, 3], Stream::from(['', 1, 0, 2, false, 3, []])->notEmpty()->toArray());
    }
    
    public function test_filter_without_default(): void
    {
        self::assertSame([2, 3], Stream::from([1, 2, 3, 4])->without([0, 1, 4])->toArray());
    }
    
    public function test_filter_without_variant(): void
    {
        $a = ['value' => 15];
        $b = ['value' => 3];
        $c = ['value' => 9];
        
        self::assertSame([$b], Stream::from([$a, $b, $c])->without([$a, $c])->toArray());
    }
    
    public function test_filter_only_default(): void
    {
        self::assertSame([2, 3], Stream::from([1, 2, 3, 4])->only([0, 2, 3])->toArray());
    }
    
    public function test_filter_only_variant(): void
    {
        $a = ['value' => 15];
        $b = ['value' => 3];
        $c = ['value' => 9];
        $d = ['value' => 10];
    
        self::assertSame([$b], Stream::from([$a, $b, $c])->only([$b, $d])->toArray());
    }
    
    public function test_filter_only_check_keys(): void
    {
        $input = ['a' => 5, 'b' => 2, 'c' => 4, 'd' => 6, 'e' => 3];
        self::assertSame([2, 6], Stream::from($input)->only(['b', 'd', 'f'], Check::KEY)->toArray());
    }
    
    public function test_filter_only_check_any(): void
    {
        $input = ['a' => 5, 'b' => 2, 'c' => 4, 'd' => 6, 'e' => 3];
        self::assertSame([5, 6], Stream::from($input)->only(['d', 'f', 5], Check::ANY)->toArray());
    }
    
    public function test_filter_only_check_both(): void
    {
        $input = ['a' => 5, 'b' => 2, 'c' => 4, 'd' => 6, 'e' => 3];
        self::assertSame([5], Stream::from($input)->only(['a', 'd', 5, 2], Check::BOTH)->toArray());
    }
    
    public function test_filter_greaterThan(): void
    {
        self::assertSame([7, 5], Stream::from([2, 7, 1, 5, 4, 3])->greaterThan(4)->toArray());
    }
    
    public function test_filter_greaterOrEqual(): void
    {
        self::assertSame([7, 5, 4], Stream::from([2, 7, 1, 5, 4, 3])->greaterOrEqual(4)->toArray());
    }
    
    public function test_filter_lessThan(): void
    {
        self::assertSame([2, 1, 3], Stream::from([2, 7, 1, 5, 4, 3])->lessThan(4)->toArray());
    }
    
    public function test_filter_lessOrEqual(): void
    {
        self::assertSame([2, 1, 4, 3], Stream::from([2, 7, 1, 5, 4, 3])->lessOrEqual(4)->toArray());
    }
    
    public function test_filter_onlyNumeric(): void
    {
        self::assertSame([3, '5', 14.0], Stream::from(['a', 3, false, '5', [], 14.0])->onlyNumeric()->toArray());
    }
    
    public function test_filter_onlyIntegers(): void
    {
        self::assertSame([3], Stream::from(['a', 3, false, '5', [], 14.0])->onlyIntegers()->toArray());
    }
    
    public function test_filter_onlyStrings(): void
    {
        self::assertSame(['a', '5'], Stream::from(['a', 3, false, '5', [], 14.0])->onlyStrings()->toArray());
    }
    
    public function test_filter_can_accept_name_of_function(): void
    {
        self::assertSame([3], Stream::from(['a', 3, false, '5', [], 14.0])->filter('is_int')->toArray());
    }
    
    public function test_filter_can_accept_callable(): void
    {
        $filter = function ($value) {
            return \is_float($value);
        };
        
        self::assertSame([14.0], Stream::from(['a', 3, false, '5', [], 14.0])->filter($filter)->toArray());
    }
    
    public function test_filter_can_accept_Filter_instance(): void
    {
        $filter = new class implements Filter {
            public function isAllowed($value, $key, int $mode = Check::VALUE): bool {
                return \is_bool($value);
            }
        };
    
        self::assertSame([false], Stream::from(['a', 3, false, '5', [], 14.0])->filter($filter)->toArray());
    }
    
    public function test_omit_can_accept_name_of_function(): void
    {
        self::assertSame(
            [3, false, [], 14.0],
            Stream::from(['a', 3, false, '5', [], 14.0])->omit('is_string')->toArray()
        );
    }
    
    public function test_omit_can_accept_callable(): void
    {
        $filter = function ($value) {
            return \is_scalar($value);
        };
        
        self::assertSame([[]], Stream::from(['a', 3, false, '5', [], 14.0])->omit($filter)->toArray());
    }
    
    public function test_omit_can_accept_Filter_instance(): void
    {
        $filter = new class implements Filter {
            public function isAllowed($value, $key, int $mode = Check::VALUE): bool {
                return \is_bool($value) || \is_array($value);
            }
        };
    
        self::assertSame(
            ['a', 3, '5', 14.0],
            Stream::from(['a', 3, false, '5', [], 14.0])->omit($filter)->toArray()
        );
    }
    
    public function test_omit_can_check_keys(): void
    {
        self::assertSame(
            ['a', false, '5', 14.0],
            Stream::from(['a', 'k1' => 3, false, '5', 'k2' => [], 14.0])->omit('is_string', Check::KEY)->toArray()
        );
    }
    
    public function test_omit_can_check_any(): void
    {
        self::assertSame(
            [false, 14.0],
            Stream::from(['a', 'k1' => 3, false, '5', 'k2' => [], 14.0])->omit('is_string', Check::ANY)->toArray()
        );
    }
    
    public function test_omit_can_check_both(): void
    {
        $stream = Stream::from(['a', 'k1' => 3, false, 'u' => '5', 'k2' => [], 14.0])->omit('is_string', Check::BOTH);
        self::assertSame(['a', 'k1' => 3, false, 'k2' => [], 14.0], $stream->toArrayAssoc());
    }
    
    public function test_castToInt(): void
    {
        self::assertSame([3, 0, 5, 14], Stream::from([3, false, '5', 14.0])->castToInt()->toArray());
    }
    
    public function test_cast_field_in_row_using_callable(): void
    {
        $rowset = [
            ['id' => '1', 'name' => 'Joe'],
            ['id' => '2', 'name' => 'Bill'],
        ];
        
        $expected = [
            ['id' => 1, 'name' => 'Joe'],
            ['id' => 2, 'name' => 'Bill'],
        ];
        
        self::assertSame($expected, Stream::from($rowset)
            ->map(function (array $row) {
                $row['id'] = (int) $row['id'];
                return $row;
            })
            ->toArray()
        );
    }
    
    public function test_cast_field_in_row_using_mapper(): void
    {
        $rowset = [
            ['id' => '1', 'name' => 'Joe'],
            ['id' => '2', 'name' => 'Bill'],
        ];
    
        $expected = [
            ['id' => 1, 'name' => 'Joe'],
            ['id' => 2, 'name' => 'Bill'],
        ];
        
        self::assertSame($expected, Stream::from($rowset)->map(Mappers::toInt('id'))->toArray());
    }
    
    public function test_cast_field_in_row_using_method(): void
    {
        $rowset = [
            ['id' => '1', 'name' => 'Joe'],
            ['id' => '2', 'name' => 'Bill'],
        ];
    
        $expected = [
            ['id' => 1, 'name' => 'Joe'],
            ['id' => 2, 'name' => 'Bill'],
        ];
        
        self::assertSame($expected, Stream::from($rowset)->castToInt('id')->toArray());
    }
    
    public function test_map_can_accept_name_of_function(): void
    {
        self::assertSame([3, 1, 2, 4], Stream::from(['ccc', 'a', 'bb', 'dddd'])->map('strlen')->toArray());
    }
    
    public function test_map_can_accept_callable(): void
    {
        $mapper = function ($value, $key) {
            return $key.':'.$value;
        };
        
        self::assertSame(['0:a', '1:b', '2:c'], Stream::from(['a', 'b', 'c'])->map($mapper)->toArray());
    }
    
    public function test_map_can_accept_Mapper_isntance(): void
    {
        $mapper = new class extends StatelessMapper {
            public function map($value, $key) {
                return 2 * $value;
            }
        };
        
        self::assertSame([2, 4, 6], Stream::from([1, 2, 3])->map($mapper)->toArray());
    }
    
    public function test_collectIn_not_preserving_keys(): void
    {
        //given
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $stream = Stream::from($array);
        $buffer = new \ArrayObject();
        
        //when
        $stream->collectIn($buffer, true)->run();
        
        //then
        self::assertSame(\array_values($array), $buffer->getArrayCopy());
    }
    
    public function test_collectIn_with_preserving_keys(): void
    {
        //given
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $stream = Stream::from($array);
        $buffer = new \ArrayObject();
        
        //when
        $stream->collectIn($buffer)->run();
        
        //then
        self::assertSame($array, $buffer->getArrayCopy());
    }
    
    public function test_call_can_accept_callable(): void
    {
        //given
        $data = [];
        $consumer = static function ($value, $key) use (&$data) {
            $data[] = ['key' => $key, 'value' => $value];
        };
    
        $array = ['a' => 1, 'b' => 2];
        $stream = Stream::from($array)->call($consumer);
        
        //when
        $stream->run();
        
        //then
        self::assertSame([
            ['key' => 'a', 'value' => 1],
            ['key' => 'b', 'value' => 2],
        ], $data);
    }
    
    public function test_call_can_accept_consumer(): void
    {
        //given
        $consumer = new class implements Consumer {
            public array $data = [];
            
            public function consume($value, $key): void {
                $this->data[] = ['key' => $key, 'value' => $value];
            }
        };
        
        $array = ['a' => 1, 'b' => 2];
        $stream = Stream::from($array)->call($consumer);
        
        //when
        $stream->run();
        
        //then
        self::assertSame([
            ['key' => 'a', 'value' => 1],
            ['key' => 'b', 'value' => 2],
        ], $consumer->data);
    }
    
    public function test_fork(): void
    {
        $data = [5,2,7,9,2,3,1,0,8,23,1,14,23,13,7,34,2,8,1,22,1,0,78,4,6,2,3];
        
        $stream = Stream::from($data)
            ->lessOrEqual(20)
            ->fork(
                Filters::number()->isEven(),
                Stream::empty()->unique()->collect(true)
            );
        
        $expected = [
            [5,7,9,3,1,13],
            [2,0,8,14,4,6],
        ];
        
        self::assertSame($expected, $stream->toArray());
    }
    
    public function test_fork_throws_exception_on_invalid_type_of_prototype(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only ForkCollaborator prototype is supported');
        
        $prototype = $this->getMockForAbstractClass(LastOperation::class);
        
        Stream::empty()->fork('is_string', $prototype);
    }
    
    public function test_join_can_accept_another_source_of_data_for_stream(): void
    {
        self::assertSame(['a', 'b', 'x', 'y'], Stream::from(['a', 'b'])->join(['x', 'y'])->toArray());
    }
    
    public function test_unique_values(): void
    {
        self::assertSame([6, 3, 8], Stream::from([6, 3, 6, 8, 3])->unique()->toArray());
    }
    
    public function test_unique_keys(): void
    {
        $keys = ['a', 'b', true, 'a', 'c', true, 'a'];
        
        self::assertSame(
            [0 => 'a', 1 => 'b', 2 => true, 4 => 'c'],
            Stream::from([0, 1, 2, 3, 4, 5, 6])
                ->mapKey(static fn(int $value) => $keys[$value]) //to produce non-unique keys in stream
                ->unique(null, Check::KEY)
                ->flip()
                ->toArrayAssoc()
        );
    }
    
    public function test_unique_with_keys_of_other_types(): void
    {
        $result = Stream::from([1, 2, 3, 4])
            ->mapKey(static fn(int $v): string => ($v & 1) === 0 ? 'b' : 'a')
            ->unique(null, Check::KEY)
            ->toArrayAssoc();
        
        self::assertSame(['a' => 1, 'b' => 2], $result);
    }
    
    public function test_unique_any(): void
    {
        //given
        $keys =   [0,  'b', 2, 1,   'a', 1,  'b',   2,    'c'];
        $values = ['a', 3,  2, 'a', 'b', 'b', true, true, 'a'];
        self::assertCount(\count($values), $keys);
        
        $producer = static function () use ($values, $keys) {
            for ($i = 0, $j = \count($values); $i < $j; ++$i) {
                yield $keys[$i] => $values[$i];
            }
        };
        
        //when
        $actual = Stream::from($producer)
            ->unique(null, Check::ANY)
            ->makeTuple()
            ->toArray();
        
        //then
        self::assertSame([
            [0, 'a'], ['b', 3], [2, 2], [1, 'a'], ['a', 'b'], ['b', true], ['c', 'a']
        ], $actual);
    }
    
    public function test_unique_both(): void
    {
        //given
        $keys =   [0,  'b', 2, 1,   'a', 1,  'b',   2,    'c'];
        $values = ['a', 3,  2, 'a', 'b', 'b', true, true, 'a'];
        
        //when
        $actual = Stream::from(Producers::combinedFrom($keys, $values))
            ->unique(null, Check::BOTH)
            ->makeTuple()
            ->toArray();
        
        //then
        self::assertSame([
            [0, 'a'], ['b', 3], [2, 2], ['a', 'b'],
        ], $actual);
    }
    
    public function test_sort_values(): void
    {
        self::assertSame([1, 2, 3, 4, 5], Stream::from([3, 5, 1, 4, 2])->sort()->toArray());
    }
    
    public function test_sort_keys(): void
    {
        self::assertSame(
            ['a' => 2, 'z' => 1],
            Stream::from(['z' => 1, 'a' => 2])->sort(null, Check::KEY)->toArray(true)
        );
    }
    
    public function test_rsort_values(): void
    {
        self::assertSame([5, 4, 3, 2, 1], Stream::from([3, 5, 1, 4, 2])->rsort()->toArray());
    }
    
    public function test_rsort_keys(): void
    {
        self::assertSame(
            ['z' => 1, 'a' => 2],
            Stream::from(['z' => 1, 'a' => 2])->rsort(null, Check::KEY)->toArray(true)
        );
    }
    
    public function test_reverse(): void
    {
        self::assertSame([4, 3, 2, 1], Stream::from([1, 2, 3, 4])->reverse()->toArray());
    }
    
    public function test_shuffle(): void
    {
        $arr = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27];
        
        for ($i = 0; $i < 10; ++$i) {
            $result = Stream::from($arr)->shuffle()->toArray();
            if ($result !== $arr) {
                break;
            }
        }
    
        if ($i === 10) {
            self::fail('shuffle doesny work!');
        }
        
        self::assertCount(\count($arr), $result);
    
        foreach ($arr as $v) {
            self::assertContains($v, $arr);
        }
    }
    
    public function test_reindex(): void
    {
        self::assertSame([1,2,3], Stream::from(['a' => 1, 'b' => 2, 'c' => 3])->reindex()->toArray(true));
    }
    
    public function test_flip(): void
    {
        self::assertSame(['a' => 0, 'b' => 1, 'c' => 2], Stream::from(['a', 'b', 'c'])->flip()->toArrayAssoc());
    }
    
    public function test_count(): void
    {
        self::assertSame(4, Stream::from(['a', 'b', 'c', 'd'])->count()->get());
    }
    
    public function test_reduce_can_accept_name_of_function(): void
    {
        self::assertSame(2, Stream::from([5, 2, 7])->reduce('min')->get());
    }
    
    public function test_reduce_can_accept_callable(): void
    {
        $reducer = static function ($acc, $value) {
            return $acc + $value;
        };
        
        self::assertSame(14, Stream::from([5, 2, 7])->reduce($reducer)->get());
    }
    
    public function test_reduce_can_accept_Reducer_instance(): void
    {
        self::assertSame(5, Stream::from([2, 4, 6, 8])->reduce(Reducers::average())->get());
    }
    
    public function test_stream_isEmpty(): void
    {
        self::assertTrue(Stream::empty()->isEmpty()->get());
        self::assertFalse(Stream::from(['a'])->isEmpty()->get());
    }
    
    public function test_stream_isNotEmpty(): void
    {
        self::assertTrue(Stream::from(['a'])->isNotEmpty()->get());
        self::assertFalse(Stream::empty()->isNotEmpty()->get());
    }
    
    public function test_get_first_value(): void
    {
        self::assertSame(4, Stream::from([4, 5])->first()->get());
    }
    
    public function test_get_first_key(): void
    {
        self::assertSame(0, Stream::from([4, 5])->first()->key());
    }
    
    public function test_get_default_first_element_if_stream_empty(): void
    {
        self::assertNull(Stream::empty()->first()->get());
        self::assertSame('a', Stream::empty()->first()->getOrElse('a'));
        self::assertSame('a', Stream::empty()->firstOrElse('a')->get());
    }
    
    public function test_getOrElse_replaces_firstOrElse(): void
    {
        self::assertSame('b', Stream::empty()->firstOrElse('a')->getOrElse('b'));
    }
    
    public function test_get_first_element(): void
    {
        self::assertSame([0, 4], Stream::from([4, 5])->first()->tuple());
    }
    
    public function test_get_last_value(): void
    {
        self::assertSame(5, Stream::from([4, 5])->last()->get());
        self::assertSame(5, Stream::from([4, 5])->lastOrElse('a')->get());
    }
    
    public function test_get_last_key(): void
    {
        self::assertSame(1, Stream::from([4, 5])->last()->key());
        self::assertSame(1, Stream::from([4, 5])->lastOrElse('a')->key());
    }
    
    public function test_get_last_element(): void
    {
        self::assertSame([1, 5], Stream::from([4, 5])->last()->tuple());
        self::assertSame([1, 5], Stream::from([4, 5])->lastOrElse('a')->tuple());
    }
    
    public function test_get_default_last_element_if_stream_empty(): void
    {
        self::assertNull(Stream::empty()->last()->get());
        self::assertSame('a', Stream::empty()->last()->getOrElse('a'));
        self::assertSame('a', Stream::empty()->lastOrElse('a')->get());
    }
    
    public function test_getOrElse_replaces_lastOrElse(): void
    {
        self::assertSame('b', Stream::empty()->lastOrElse('a')->getOrElse('b'));
    }
    
    public function test_forEach(): void
    {
        //given
        $data = [];
        $consumer = static function ($value, $key) use (&$data) {
            $data[] = $key.':'.$value;
        };
        
        $stream = Stream::from(['a', 'b', 'c']);
        
        //when
        $stream->forEach($consumer);
    
        //then
        self::assertSame(['0:a', '1:b', '2:c'], $data);
    }
    
    public function test_stream_can_be_iterable_as_array(): void
    {
        $stream = Stream::from(['a', 'b', 'c']);
        
        $data = [];
        foreach ($stream as $value) {
            $data[] = $value;
        }
        
        self::assertSame(['a', 'b', 'c'], $data);
    }
    
    public function test_mapKey(): void
    {
        self::assertSame(
            [5 => 'a', 6 => 'b'],
            Stream::from(['a', 'b'])->mapKey(static fn($value, $key) => $key + 5)->toArray(true)
        );
    }
    
    public function test_has_can_accept_any_value(): void
    {
        self::assertFalse(Stream::from(['1', '2', '3'])->has('4')->get());
        self::assertTrue(Stream::from(['1', '2', '3'])->has('2')->get());
    }
    
    public function test_has_can_accept_name_of_function(): void
    {
        self::assertFalse(Stream::from(['1', '2', '3'])->has('is_int')->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->has('is_int')->get());
    }
    
    public function test_has_can_accept_callable(): void
    {
        $predicate = static fn($v) => \is_int($v);
        
        self::assertFalse(Stream::from(['1', '2', '3'])->has($predicate)->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->has($predicate)->get());
    }
    
    public function test_has_can_accept_Predicate_instance(): void
    {
        $predicate = Predicates::value(2);
        
        self::assertFalse(Stream::from(['1', '2', '3'])->has($predicate)->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->has($predicate)->get());
    }
    
    public function test_hasAny(): void
    {
        self::assertFalse(Stream::from(['1', '2', '3'])->hasAny([1, 2, 3])->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->hasAny([1, 2, 3])->get());
    }
    
    public function test_hasEvery(): void
    {
        self::assertFalse(Stream::from(['1', '2', '3'])->hasEvery(['1', '5'])->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->hasEvery(['1', '3'])->get());
    }
    
    public function test_has_key(): void
    {
        self::assertFalse(Stream::from(['a', 'b', 'c'])->has(4, Check::KEY)->get());
        self::assertTrue(Stream::from(['a', 'b', 'c'])->has(2, Check::KEY)->get());
    }
    
    public function test_hasAny_key(): void
    {
        self::assertFalse(Stream::from(['1', '2', '3'])->hasAny([3, 4], Check::KEY)->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->hasAny([1, 2], Check::KEY)->get());
    }
    
    public function test_hasEvery_key(): void
    {
        self::assertFalse(Stream::from(['1', '2', '3'])->hasEvery([1, 3], Check::KEY)->get());
        self::assertTrue(Stream::from(['1', 2, '3'])->hasAny([1, 2], Check::KEY)->get());
    }
    
    public function test_collectKeys(): void
    {
        //given
        $array = ['a' => 1, 'b' => 2, 'c' => 3];
        $stream = Stream::from($array);
        $buffer = new \ArrayObject();
    
        //when
        $stream->collectKeysIn($buffer)->run();
    
        //then
        self::assertSame(\array_keys($array), $buffer->getArrayCopy());
    }
    
    public function test_find_value(): void
    {
        $item = Stream::from(['a' => 1, 'b' => 2, 'c' => 3])->find(2);
    
        self::assertTrue($item->found());
        self::assertSame('b', $item->key());
        self::assertSame(2, $item->get());
    }
    
    public function test_find_key(): void
    {
        $item = Stream::from(['a' => 1, 'b' => 2, 'c' => 3])->find('b', Check::KEY);
    
        self::assertTrue($item->found());
        self::assertSame('b', $item->key());
        self::assertSame(2, $item->get());
    }
    
    public function test_find_can_accept_name_of_function(): void
    {
        $item = Stream::from(['a' => '1', 'b' => 2, 'c' => '3'])->find('is_int', Check::ANY);
    
        self::assertTrue($item->found());
        self::assertSame('b', $item->key());
        self::assertSame(2, $item->get());
    }
    
    public function test_find_can_check_both(): void
    {
        $item = Stream::from(['a' => '1', 'b' => 2, 6, 'c' => '3'])->find('is_int', Check::BOTH);
    
        self::assertTrue($item->found());
        self::assertSame(0, $item->key());
        self::assertSame(6, $item->get());
    }
    
    public function test_find_can_accept_callable(): void
    {
        $predicate = static fn($v, $k) => \is_string($k) && \is_int($v);
        $item = Stream::from(['a' => '1', 'b' => 2, 6, 'c' => '3'])->find($predicate);
    
        self::assertTrue($item->found());
        self::assertSame('b', $item->key());
        self::assertSame(2, $item->get());
    }
    
    public function test_fold_can_accept_callable(): void
    {
        $reducer = static fn($acc, $val) => $acc - $val;
        self::assertSame(7, Stream::from([1, 1, 1])->fold(10, $reducer)->get());
    }
    
    public function test_fold_can_accept_Reducer_instance(): void
    {
        self::assertSame(10, Stream::from([1, 1, 1])->fold(7, Reducers::sum())->get());
    }
    
    public function test_chunk_can_reindex_keys(): void
    {
        $inputData = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'g' => 7, 'h' => 8];
        
        $expected = [
            [1, 2, 3],
            [4, 5, 6],
            [7, 8],
        ];
        
        self::assertSame($expected, Stream::from($inputData)->chunk(3, true)->toArray());
    }
    
    public function test_chunk_assoc_preserve_keys(): void
    {
        $inputData = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'g' => 7, 'h' => 8];
        
        $expected = [
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['d' => 4, 'e' => 5, 'f' => 6],
            ['g' => 7, 'h' => 8],
        ];
        
        self::assertSame($expected, Stream::from($inputData)->chunk(3)->toArray());
    }
    
    public function test_scan(): void
    {
        self::assertSame([0, 1, 3, 6, 10], Stream::from([1, 2, 3, 4])->scan(0, Reducers::sum())->toArray());
    }
    
    public function test_scan_empty(): void
    {
        $result = Stream::from([])->scan(0, Reducers::sum())->toArray();
        
        self::assertSame([0], $result);
    }
    
    public function test_scan_with_while(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4, 5, 6, 7, 8, 9]);
        
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()
                ->while(Filters::lessOrEqual(4), Check::KEY)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
        
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()
                ->while(Filters::lessOrEqual(5), Check::VALUE)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    }
    
    public function test_scan_with_until(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()
                ->until(Filters::greaterThan(4), Check::KEY)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()
                ->until(Filters::greaterThan(5), Check::VALUE)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    }
    
    public function test_scan_with_filter(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()->filter(Filters::lessOrEqual(4), Check::KEY)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()->filter(Filters::lessOrEqual(5), Check::VALUE)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    }
    
    public function test_scan_with_omit(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()->omit(Filters::greaterThan(4), Check::KEY)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()->omit(Filters::greaterThan(5), Check::VALUE)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    }
    
    public function test_scan_with_limit(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    
        self::assertSame(
            [0, 1, 3, 6, 10],
            $stream->start()
                ->scan(0, Reducers::sum())
                ->limit(5)
                ->toArray()
        );
    
        self::assertSame(
            [0, 1, 3, 6, 10, 15],
            $stream->start()
                ->limit(5)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    }
    
    public function test_scan_with_skip(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    
        self::assertSame(
            [15, 21, 28, 36, 45],
            $stream->start()
                ->scan(0, Reducers::sum())
                ->skip(5)
                ->toArray()
        );
    
        self::assertSame(
            [0, 6, 13, 21, 30],
            $stream->start()
                ->skip(5)
                ->scan(0, Reducers::sum())
                ->toArray()
        );
    }
    
    public function test_flat_level_default(): void
    {
        $stream = Stream::from([
            ['a' => 1],
            'b',
            ['c' => ['d' => 2]]
        ]);
        
        self::assertSame('[1,"b",2]', $stream->flat()->toJson());
    }
    
    public function test_flat_level_1(): void
    {
        $stream = Stream::from([
            ['a' => 1],
            'b',
            ['c' => ['d' => 2]]
        ]);
        
        self::assertSame('[1,"b",{"d":2}]', $stream->flat(1)->toJson());
    }
    
    public function test_flatMap_default(): void
    {
        $result = Stream::from([['the'], ['quick'], ['brown'], ['fox'], ['jumps']])
            ->flatMap(static fn($x) => $x)
            ->toString();
        
        self::assertSame('the,quick,brown,fox,jumps', $result);
    }
    
    public function test_flatMap_level_default(): void
    {
        $stream = Stream::from([
            ['a' => 1],
            'b',
            ['c' => ['d' => 2]]
        ]);
    
        self::assertSame('[1,"b",2]', $stream->flatMap(static fn($x) => $x)->toJson());
    }
    
    public function test_flatMap_level_1(): void
    {
        $stream = Stream::from([
            ['a' => 1],
            'b',
            ['c' => ['d' => 2]]
        ]);
    
        self::assertSame('[1,"b",{"d":2}]', $stream->flatMap(static fn($x) => $x, 1)->toJson());
    }
    
    public function test_map_with_extract(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $json = Stream::from($rowset)
            ->map(Mappers::extract(['name', 'age']))
            ->toJson();
        
        self::assertSame('[{"name":"Kate","age":35},{"name":"Chris","age":26},{"name":"Joanna","age":18}]', $json);
    }
    
    public function test_extract(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $json = Stream::from($rowset)
            ->extract(['name', 'age'])
            ->toJson();
        
        self::assertSame('[{"name":"Kate","age":35},{"name":"Chris","age":26},{"name":"Joanna","age":18}]', $json);
    }
    
    public function test_map_extract_single_field_flatten(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $json = Stream::from($rowset)
            ->map(Mappers::extract('name'))
            ->toJson();
    
        self::assertSame('["Kate","Chris","Joanna"]', $json);
    }
    
    public function test_extract_single_field_flatten(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $json = Stream::from($rowset)
            ->extract('name')
            ->toJson();
    
        self::assertSame('["Kate","Chris","Joanna"]', $json);
    }
    
    public function test_map_extract_single_field_as_array(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $json = Stream::from($rowset)
            ->map(Mappers::extract(['name']))
            ->toJson();
    
        self::assertSame('[{"name":"Kate"},{"name":"Chris"},{"name":"Joanna"}]', $json);
    }
    
    public function test_extract_single_field_as_array(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $json = Stream::from($rowset)
            ->extract(['name'])
            ->toJson();
    
        self::assertSame('[{"name":"Kate"},{"name":"Chris"},{"name":"Joanna"}]', $json);
    }
    
    public function test_split(): void
    {
        $result = Stream::from(['the quick brown fox', 'jumps over the lazy dog'])
            ->split()
            ->toJson();
        
        self::assertSame('[["the","quick","brown","fox"],["jumps","over","the","lazy","dog"]]', $result);
    }
    
    public function test_map_split(): void
    {
        $result = Stream::from(['the quick brown fox', 'jumps over the lazy dog'])
            ->map(Mappers::split())
            ->toJson();
        
        self::assertSame('[["the","quick","brown","fox"],["jumps","over","the","lazy","dog"]]', $result);
    }
    
    public function test_while(): void
    {
        Stream::of('a', 'v', 3, 'z')
            ->call($before = Consumers::counter())
            ->while('is_string')
            ->call($after = Consumers::counter())
            ->run();
        
        self::assertSame(3, $before->count());
        self::assertSame(2, $after->count());
    }
    
    public function test_until(): void
    {
        $count = 0;
    
        Stream::of('a', 'v', 3, 'z')
            ->until('is_int')
            ->countIn($count)
            ->run();
    
        self::assertSame(2, $count);
    }
    
    public function test_groupBy_using_Discriminator_instance(): void
    {
        $collection = Stream::from(['y', 5, 'c', 3, 'z', 8])->groupBy(new class implements Discriminator {
            public function classify($value, $key) {
                if (\is_int($value)) {
                    return $value > 5 ? 'big_numbers' : 'small_numbers';
                }
    
                return \is_string($value) ? 'strings' : 'unknown';
            }
        });
    
        self::assertSame([8], $collection->get('big_numbers')->toArray());
        self::assertSame([5, 3], $collection->get('small_numbers')->toArray());
        self::assertSame(['y', 'c', 'z'], $collection->get('strings')->toArray());
        self::assertSame([], $collection->get('unknown')->toArray());
    }
    
    public function test_groupBy_using_Filter_instance(): void
    {
        $greaterThanFive = Stream::of(8,3,6,5,0,2,8,3,1,6,8,3,2,5,9)->groupBy(Filters::greaterThan(5));
        
        self::assertSame([8,6,8,6,8,9], $greaterThanFive->get(true)->toArray());
        self::assertSame([3,5,0,2,3,1,3,2,5], $greaterThanFive->get(false)->toArray());
    }
    
    public function test_groupBy_using_callable(): void
    {
        $integers = Stream::from(['y', 5, 'c', 3, 'z', 8])->groupBy('is_int');
        
        self::assertSame([5, 3, 8], $integers->get(true)->toArray());
        self::assertSame(['y', 'c', 'z'], $integers->get(false)->toArray());
    }
    
    public function test_groupBy_with_empty_group(): void
    {
        $streams = Stream::from(['y', 5, 'c', 3, 'z', 2])->groupBy(static fn($v) => \is_int($v) && $v > 5);
        
        self::assertTrue($streams->get(true)->notFound());
        self::assertFalse($streams->get(true)->found());
        
        self::assertTrue($streams->get(false)->found());
        self::assertSame(6, $streams->get(false)->count());
    }
    
    public function test_groupBy_for_many_groups(): void
    {
        $grouped = Stream::from(['y', 5, 'c', 3, ['a' => 5], 'z', 8, ['b' => 3], 2])
            ->groupBy(function ($item) {
                switch (true) {
                    case \is_array($item): return 'rows';
                    case \is_int($item): return 'integers';
                    case \is_string($item): return 'strings';
                    default: return 'other';
                }
            });
        
        self::assertSame('[{"a":5},{"b":3}]', $grouped->get('rows')->toJson());
        self::assertSame('[5,3,8,2]', $grouped->get('integers')->toJson());
        self::assertSame('["y","c","z"]', $grouped->get('strings')->toJson());
        self::assertSame('[]', $grouped->get('other')->toJson());
    }
    
    public function test_GroupBy_with_preserve_keys(): void
    {
        $integers = Stream::from(['y', 5, 'c', 3, 'z', 8])->groupBy('is_int');
        
        self::assertSame([1 => 5, 3 => 3, 5 => 8], $integers->get(true)->toArrayAssoc());
        self::assertSame([0 => 'y', 2 => 'c', 4 => 'z'], $integers->get(false)->toArrayAssoc());
    }
    
    public function test_GroupBy_throws_exception_when_classifier_is_invalid(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Value returned from discriminator is inappropriate (got object of type stdClass)'
        );
        
        Stream::from([1])->groupBy(static fn($v): object => new \stdClass())->toArray();
    }
    
    public function test_cloning_is_prohibited(): void
    {
        $this->expectException(\Error::class);
        
        /* @var $stream \ArrayObject */
        $stream = Stream::empty();
        
        $other = clone $stream;
        $other->exchangeArray([]);
    }
    
    public function test_without_with_one_value(): void
    {
        self::assertSame([1, 3], Stream::from([1, 2, 3])->without([2])->toArray());
    }
    
    public function test_only_with_one_value(): void
    {
        self::assertSame([2], Stream::from([1, 2, 3])->only([2])->toArray());
    }
    
    public function test_join_many_sources(): void
    {
        self::assertSame([1, 2, 3, 4], Stream::empty()->join([1, 2])->join([3, 4])->toArray());
    }
    
    public function test_feed(): void
    {
        //given
        $buffer = new \ArrayObject();
        $stream = Stream::empty()->collectIn($buffer);
    
        //when
        Stream::from([1, 2, 3, 4])->feed($stream)->run();
    
        //then
        self::assertSame([1, 2, 3, 4], $buffer->getArrayCopy());
    }
    
    public function test_feed_another_stream(): void
    {
        $collector = Collectors::default();
        
        $second = Stream::empty()->limit(2)->collectIn($collector);
        Stream::from([1, 2, 3, 4, 5])->feed($second)->run();
        
        self::assertSame([1, 2], $collector->getData());
    }
    
    public function test_feed_many_streams(): void
    {
        $stream = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd', 4]);
        
        $first = Stream::empty()->onlyIntegers()->collect();
        $second = Stream::empty()->onlyStrings()->collect();
        $third = Stream::empty()->while(false)->collect();
        
        $stream->feed($first, $second)->feed($third)
            ->chunk(2)
            ->concat('');
        
        self::assertSame('a1,b2,c3,d4', $stream->toString());
        
        self::assertSame([1, 2, 3, 4], $first->toArray());
        self::assertSame(['a', 'b', 'c', 'd'], $second->toArray());
        
        self::assertSame([1 => 1, 3 => 2, 5 => 3, 7 => 4], $first->toArrayAssoc());
        self::assertSame([0 => 'a', 2 => 'b', 4 => 'c', 6 => 'd'], $second->toArrayAssoc());
        
        self::assertSame('', $third->toString());
    }
    
    public function test_feed_operation(): void
    {
        $sdata = ['a', 'b'];
        
        $collector = Stream::of($sdata, Producers::sequentialInt(10, -1, 2))
            ->join(['foo', 'bar'])
            ->join(Producers::tokenizer(' ', 'the quick'))
            ->collect(true);
        
        $stream = Stream::from([1, 2])->join([7, 8])->feed($collector);
        $stream->run();
        
        self::assertSame(
            [1, 2, 7, 8, 'a', 'b', 10, 9, 'foo', 'bar', 'the', 'quick'],
            $collector->get()
        );
    }
    
    public function test_feed_stream_itself(): void
    {
        $stream = Stream::of(1);
        
        $stream
            ->limit(3)
            ->reindex()
            ->collectIn($collector = Collectors::default())
            ->map(fn(int $v): int => $v + 1)
            ->feed($stream);
        
        $stream->run();
        
        self::assertSame([1, 2, 3], $collector->getData());
    }
    
    public function test_feed_throws_exception_on_empty_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty arguments');
        
        Stream::empty()->feed();
    }
    
    public function test_feed_throws_exception_on_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only StrimPipe is supported');
        
        Stream::empty()->feed(new class implements SignalHandler {});
    }
    
    public function test_stream_cannot_be_executed_more_than_onece(): void
    {
        $this->expectException(\LogicException::class);
        
        $stream = Stream::empty();
        $stream->run();
        $stream->run();
    }
    
    public function test_unique_value_with_comparator(): void
    {
        $given = ['a','b','a','c','d','n','a'];
        $expected = ['a','b','c','d','n'];
        
        $comparator = static fn(string $first, string $second): int => $first <=> $second;
        $actual = Stream::from($given)->unique($comparator)->toArray();
        
        self::assertSame($expected, $actual);
    }
    
    public function test_unique_key_with_comparator(): void
    {
        $given = [['a' => 1],['b' => 2],['a' => 3],['c' => 4],['d' => 5],['n' => 6],['a' => 7]];
        $expected = ['a','b','c','d','n'];
        
        $comparator = static fn(string $first, string $second): int => $first <=> $second;
        $actual = Stream::from($given)->flat()->unique($comparator, Check::KEY)->flip()->toArray();
        
        self::assertSame($expected, $actual);
    }
    
    public function test_unique_both_key_and_value_with_comparator(): void
    {
        $given = [['a' => 1],['b' => 2],['a' => 3],['c' => 2],['d' => 5],['n' => 1],['o' => 2]];
        $expected = ['a' => 1, 'b' => 2, 'd' => 5];
    
        $comparator = static function ($v1, $v2, $k1, $k2): int {
            return ($v1 <=> $v2) === 0
                || ($k1 <=> $k2) === 0
                || ($v1 <=> $k2) === 0
                || ($v2 <=> $k1) === 0 ? 0 : 1;
        };
        
        $actual = Stream::from($given)->flat()->unique($comparator, Check::BOTH)->toArrayAssoc();
        self::assertSame($expected, $actual);
    }
    
    public function test_unique_key_or_value_with_comparator(): void
    {
        $given = [['a' => 1],['b' => 2],['a' => 3],['c' => 'c'],['d' => 5],['n' => 1],['o' => 2]];
        $expected = ['a' => 3, 'b' => 2, 'c' => 'c', 'd' => 5, 'n' => 1, 'o' => 2];
    
        $comparator = static fn($v1, $v2): int => $v1 <=> $v2;
        $actual = Stream::from($given)->flat()->unique($comparator, Check::ANY)->toArrayAssoc();
        
        self::assertSame($expected, $actual);
    }
    
    public function test_unique_of_array_as_value_without_comparator(): void
    {
        $result = Stream::from([[5], [7], [5], [7]])->unique()->toArray();
        
        self::assertCount(2, $result);
    }
    
    public function test_chunk_throws_exception_on_invalid_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Stream::from([1, 2])->chunk(0);
    }
    
    public function test_flat_throws_exception_on_invalid_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Stream::from([1, 2])->flat(-1);
    }
    
    public function test_limit_throws_exception_on_invalid_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Stream::from([1, 2])->limit(-1);
    }
    
    public function test_skip_throws_exception_on_invalid_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Stream::from([1, 2])->skip(-1);
    }
    
    public function test_limit_zero_prevents_execution(): void
    {
        $counter = Consumers::counter();
        Stream::from([1, 2])->limit(0)->call($counter)->run();
        
        self::assertSame(0, $counter->count());
    }
    
    public function test_sort_by_keys_with_comparator(): void
    {
        $data = ['c' => 1, 'a' => 2, 'd' => 3, 'b' => 4];
        $actual = Stream::from($data)->sort(Comparators::default(), Check::KEY)->toArrayAssoc();
        
        self::assertSame(['a' => 2, 'b' => 4, 'c' => 1, 'd' => 3], $actual);
    }
    
    public function test_sort_both_without_comparator(): void
    {
        $data = ['c' => 1, 'a' => 2, 'd' => 1, 'b' => 2];
        $actual = Stream::from($data)->sort(null, Check::BOTH)->toArrayAssoc();
    
        self::assertSame(['c' => 1, 'd' => 1, 'a' => 2, 'b' => 2], $actual);
    }
    
    public function test_sort_both_with_comparator(): void
    {
        $data = ['c' => 1, 'a' => 2, 'd' => 1, 'b' => 2];
        $actual = Stream::from($data)->sort(Comparators::default(), Check::BOTH)->toArrayAssoc();
    
        self::assertSame(['c' => 1, 'd' => 1, 'a' => 2, 'b' => 2], $actual);
    }
    
    public function test_filterBy(): void
    {
        $stream = Stream::from([
            ['id' => 4, 'name' => 'Joe'],
            ['id' => 5, 'name' => 'Cristine'],
        ]);
    
        $actual = $stream->filterBy('name', Filters::length()->ge(5))->toArray();
        self::assertSame([['id' => 5, 'name' => 'Cristine']], $actual);
    }
    
    public function test_sortBy(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 9, 'name' => 'Chris', 'age' => 26],
            ['id' => 6, 'name' => 'Joanna', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 7, 'name' => 'Sue', 'age' => 17],
        ];
    
        $actual = Stream::from($rowset)->sortBy('age asc', 'name desc', 'id')->toArray();
        
        $expected = [
            ['id' => 7, 'name' => 'Sue', 'age' => 17],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 9, 'name' => 'Chris', 'age' => 26],
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 6, 'name' => 'Joanna', 'age' => 35],
        ];
        
        self::assertSame($expected, $actual);
    }
    
    public function test_remove_with_single_field(): void
    {
        $stream = Stream::from([
            ['id' => 4, 'name' => 'Joe', 'age' => 25],
        ]);
        
        self::assertSame([['name' => 'Joe', 'age' => 25]], $stream->remove('id')->toArray());
    }
    
    public function test_remove_with_single_field_as_array(): void
    {
        $stream = Stream::from([
            ['id' => 4, 'name' => 'Joe', 'age' => 25],
        ]);
        
        self::assertSame([['name' => 'Joe', 'age' => 25]], $stream->remove(['id'])->toArray());
    }
    
    public function test_remove_with_two_fields(): void
    {
        $stream = Stream::from([
            ['id' => 4, 'name' => 'Joe', 'age' => 25],
        ]);
        
        self::assertSame([['name' => 'Joe']], $stream->remove('id', 'age')->toArray());
    }
    
    public function test_remove_with_two_fields_as_array(): void
    {
        $stream = Stream::from([
            ['id' => 4, 'name' => 'Joe', 'age' => 25],
        ]);
        
        self::assertSame([['name' => 'Joe']], $stream->remove(['id', 'age'])->toArray());
    }
    
    public function test_append(): void
    {
        $stream = Stream::from([4, 3])
            ->mapKey(Mappers::simple('value'))
            ->append('double', static fn(int $v) => 2 * $v);
    
        self::assertSame([
            ['value' => 4, 'double' => 8],
            ['value' => 3, 'double' => 6],
        ], $stream->toArray());
    }
    
    public function test_first(): void
    {
        $item = Stream::from([5, 2, 8])->first();
        
        self::assertTrue($item->found());
        self::assertFalse($item->notFound());
        
        self::assertSame(5, $item->get());
        self::assertSame(0, $item->key());
        
        self::assertSame('5', $item->toString());
        self::assertSame('5', $item->toJson());
        self::assertSame('[5]', $item->toJsonAssoc());
        self::assertSame([5], $item->toArray());
        self::assertSame([5], $item->toArrayAssoc());
        self::assertSame([0, 5], $item->tuple());
    }
    
    public function test_last(): void
    {
        $item = Stream::from([5, 2, 8])->last();
        
        self::assertTrue($item->found());
        self::assertFalse($item->notFound());
        
        self::assertSame(8, $item->get());
        self::assertSame(2, $item->key());
        
        self::assertSame('8', $item->toString());
        self::assertSame('8', $item->toJson());
        self::assertSame('{"2":8}', $item->toJsonAssoc());
        self::assertSame([8], $item->toArray());
        self::assertSame([2 => 8], $item->toArrayAssoc());
        self::assertSame([2, 8], $item->tuple());
    }
    
    public function test_last_default(): void
    {
        $item = Stream::empty()->lastOrElse('d');
        
        self::assertFalse($item->found());
        self::assertTrue($item->notFound());
        
        self::assertSame('d', $item->get());
        self::assertSame(0, $item->key());
        
        self::assertSame('d', $item->toString());
        self::assertSame('"d"', $item->toJson());
        self::assertSame('["d"]', $item->toJsonAssoc());
        self::assertSame(['d'], $item->toArray());
        self::assertSame(['d'], $item->toArrayAssoc());
        self::assertSame([0, 'd'], $item->tuple());
    }
    
    public function test_unable_to_chain_operation_after_terminating_one(): void
    {
        $this->expectException(\LogicException::class);
        
        $stream = Stream::empty();
        $stream->fold(1, Reducers::average());
        $stream->limit(4);
    }
    
    public function test_find_lazy(): void
    {
        $stream = Stream::from([5, 'a', 2]);
        $item = $stream->find('is_string');
    
        self::assertTrue($item->found());
        self::assertFalse($item->notFound());
    
        self::assertSame('a', $item->get());
        self::assertSame(1, $item->key());
    
        self::assertSame('a', $item->toString());
        self::assertSame('"a"', $item->toJson());
        self::assertSame('{"1":"a"}', $item->toJsonAssoc());
        self::assertSame(['a'], $item->toArray());
        self::assertSame([1 => 'a'], $item->toArrayAssoc());
        self::assertSame([1, 'a'], $item->tuple());
    }
    
    public function test_count_lazy(): void
    {
        $stream = Stream::from([5, 'a', 2]);
        $count = $stream->filter('is_string')->count();
    
        self::assertTrue($count->found());
        self::assertFalse($count->notFound());
    
        self::assertSame(1, $count->get());
        self::assertSame(0, $count->key());
    
        self::assertSame('1', $count->toString());
        self::assertSame('1', $count->toJson());
        self::assertSame('[1]', $count->toJsonAssoc());
        self::assertSame([1], $count->toArray());
        self::assertSame([1], $count->toArrayAssoc());
        self::assertSame([0, 1], $count->tuple());
    }
    
    public function test_collect(): void
    {
        $result = Stream::from([1, 2, 3, 4])->collect();
        
        self::assertTrue($result->found());
        self::assertSame([1, 2, 3, 4], $result->get());
        self::assertSame([1, 2, 3, 4], $result->toArray());
        self::assertSame([1, 2, 3, 4], $result->toArrayAssoc());
        self::assertSame('[1,2,3,4]', $result->toJson());
        self::assertSame('[1,2,3,4]', $result->toJsonAssoc());
        self::assertSame([0, [1, 2, 3, 4]], $result->tuple());
        
        $counter = 0;
        $result->call(function (array $data) use (&$counter) {
            $counter = \count($data);
        });
        
        self::assertSame(4, $counter);
    }
    
    public function test_aggregate(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $result = Stream::from($rowset)->flat()->aggregate(['id', 'age'])->toArrayAssoc();
        
        $expected = [
            ['id' => 2, 'age' => 35],
            ['id' => 5, 'age' => 26],
            ['id' => 8, 'age' => 18],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_aggregate_with_single_key(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        $result = Stream::from($rowset)->flat()->aggregate(['name'])->toArrayAssoc();
    
        $expected = [
            ['name' => 'Kate'],
            ['name' => 'Chris'],
            ['name' => 'Joanna'],
        ];
    
        self::assertSame($expected, $result);
    }
    
    public function test_aggregate_with_no_key_in_stream(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 8, 'name' => 'Joanna', 'age' => 18],
        ];
    
        self::assertSame([], Stream::from($rowset)->flat()->aggregate(['foo'])->toArrayAssoc());
    }
    
    public function test_onlyWith_without_nulls(): void
    {
        $rowset = [
            ['id' => 3, 'name' => 'Bob'],
            ['id' => 3, 'name' => null],
            ['id' => 3],
        ];
        
        $result = Stream::from($rowset)->onlyWith(['name'])->remove('id')->toJsonAssoc();
        
        self::assertSame('[{"name":"Bob"}]', $result);
    }
    
    public function test_onlyWith_with_nulls(): void
    {
        $rowset = [
            ['id' => 3, 'name' => 'Bob'],
            ['id' => 3, 'name' => null],
            ['id' => 3],
        ];
        
        $result = Stream::from($rowset)->onlyWith('name', true)->remove('id')->toJsonAssoc();
        
        self::assertSame('[{"name":"Bob"},{"name":null}]', $result);
    }
    
    public function test_call_different_number_of_times(): void
    {
        Stream::from([1, 2, 3])
            ->callOnce($onlyOnce = Consumers::counter())
            ->callMax(2, $maxTwice = Consumers::counter())
            ->call($all = Consumers::counter())
            ->run();
        
        self::assertSame(1, $onlyOnce->count());
        self::assertSame(2, $maxTwice->count());
        self::assertSame(3, $all->count());
    }
    
    public function test_callWhen(): void
    {
        Stream::from([1, 'a', 2, 'b', 3])
            ->callWhen('is_int', $countInts = Consumers::counter(), $countOthers = Consumers::counter())
            ->run();
        
        self::assertSame(3, $countInts->count());
        self::assertSame(2, $countOthers->count());
    }
    
    public function test_mapWhen(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->mapWhen('is_string', 'strtoupper', static fn(int $n) => $n * 2)
            ->toArray();
        
        self::assertSame(['A', 2, 'B', 4, 'C', 6], $result);
    }
    
    public function test_mapWhen_with_Predicate(): void
    {
        $result = Stream::from([1, 2, 3])
            ->mapWhen(Predicates::inArray([1, 3]), static fn(int $n): int => $n * 2)
            ->toArray();
        
        self::assertSame([2, 2, 6], $result);
    }
    
    public function test_complete(): void
    {
        $rowset = [
            ['id' => 3, 'name' => 'Ole'],
            ['id' => 7, 'name' => null],
            ['id' => 2],
        ];
        
        $result = Stream::from($rowset)->complete('name', 'anonymous')->toArray();
        
        $expected = [
            ['id' => 3, 'name' => 'Ole'],
            ['id' => 7, 'name' => 'anonymous'],
            ['id' => 2, 'name' => 'anonymous'],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_moveTo_creates_array(): void
    {
        self::assertSame('[{"num":1},{"num":2}]', Stream::from([1, 2])->moveTo('num')->toJson());
    }
    
    public function test_tail_short(): void
    {
        self::assertSame([4, 5], Stream::from([1, 2, 3, 4, 5])->tail(2)->toArray());
    }
    
    public function test_tail_long(): void
    {
        self::assertSame([1, 2, 3], Stream::from([1, 2, 3])->tail(6)->toArray());
    }
    
    public function test_limit_after_limit(): void
    {
        self::assertSame([1, 2], Stream::from([1, 2, 3, 4, 5])->limit(4)->limit(2)->toArray());
    }
    
    public function test_skip_after_skip(): void
    {
        self::assertSame([4, 5], Stream::from([1, 2, 3, 4, 5])->skip(1)->skip(2)->toArray());
    }
    
    public function test_reverse_after_reverse(): void
    {
        self::assertSame([1, 2], Stream::from([1, 2])->reverse()->reverse()->toArray());
    }
    
    public function test_reindex_after_reindex(): void
    {
        self::assertSame([1, 2], Stream::from(['a' => 1, 'b' => 2])->reindex()->reindex()->toArrayAssoc());
    }
    
    public function test_flip_after_flip(): void
    {
        self::assertSame(['a' => 1, 'b' => 2], Stream::from(['a' => 1, 'b' => 2])->flip()->flip()->toArrayAssoc());
    }
    
    public function test_shuffle_after_shuffle(): void
    {
        self::assertSame(3, Stream::from([1, 2, 3])->shuffle()->shuffle()->count()->get());
    }
    
    public function test_tail_after_tail(): void
    {
        self::assertSame([4], Stream::from([1, 2, 3, 4])->tail(3)->tail(1)->toArray());
    }
    
    public function test_flat_after_flat(): void
    {
        $expected = [
            'c' => 1,
            'd' => 2,
            'e' => 3,
            'g' => 4,
            'j' => 5,
            'k' => 6,
            'l' => 7,
            'n' => 8,
            'p' => 9,
            'q' => 10,
            's' => 11,
            't' => 12,
            'u' => 13,
            'w' => 14,
            'y' => 15,
            'z' => 16,
        ];
    
        $data = $this->getDataForFlatTest();
        
        self::assertSame($expected, Stream::from($data)->flat()->toArrayAssoc());
        self::assertSame($expected, Stream::from($data)->flat(1)->flat(1)->flat(1)->toArrayAssoc());
    }
    
    private function getDataForFlatTest(): array
    {
        return [
            [ //first
                'a' => [
                    'b' => [
                        'c' => 1,
                        'd' => 2,
                    ],
                    'e' => 3,
                    'f' => [
                        'g' => 4,
                    ]
                ],
                'h' => [
                    'i' => [
                        'j' => 5,
                        'k' => 6,
                    ],
                ],
            ], [ //second
                'l' => 7,
            ], [ //third
                'm' => [
                    'n' => 8,
                    'o' => [
                        'p' => 9,
                        'q' => 10,
                    ],
                    'r' => [
                        's' => 11,
                    ],
                    't' => 12,
                ],
                'u' => 13,
                'v' => [
                    'w' => 14,
                    'x' => [
                        'y' => 15,
                    ],
                    'z' => 16,
                ],
            ],
        ];
    }
    
    public function test_best(): void
    {
        self::assertSame([1, 2], Stream::from([6, 2, 8, 1, 7, 9, 2, 5, 4])->best(2)->toArray());
    }
    
    public function test_worst(): void
    {
        self::assertSame([9, 8], Stream::from([6, 2, 8, 1, 7, 9, 2, 5, 4])->worst(2)->toArray());
    }
    
    public function test_sortBy_with_limit(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 9, 'name' => 'Chris', 'age' => 26],
            ['id' => 6, 'name' => 'Joanna', 'age' => 35],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
            ['id' => 7, 'name' => 'Sue', 'age' => 17],
        ];
    
        $actual = Stream::from($rowset)->sortBy('age asc', 'name desc', 'id')->limit(2)->toArray();
    
        $expected = [
            ['id' => 7, 'name' => 'Sue', 'age' => 17],
            ['id' => 5, 'name' => 'Chris', 'age' => 26],
        ];
    
        self::assertSame($expected, $actual);
    }
    
    public function test_sort_with_limit(): void
    {
        self::assertSame([1, 2], Stream::from([3, 5, 1, 4, 2])->sort()->limit(2)->toArray());
    }
    
    public function test_best_with_limit(): void
    {
        self::assertSame([1, 2], Stream::from([5, 2, 8, 1, 6, 9, 7, 3])->best(4)->limit(2)->toArray());
    }
    
    public function test_MapFieldWhen(): void
    {
        $result = Stream::from([['key' => 'foo'], ['key' => 3]])
            ->mapFieldWhen('key', 'is_string', 'strtoupper', static fn(int $n): int => $n * 2)
            ->toArray();
        
        self::assertSame([['key' => 'FOO'], ['key' => 6]], $result);
    }
    
    public function test_MapFieldWhen_throws_exception_when_value_is_not_array(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unable to map field key because value is string');
        
        Stream::from(['this is not array'])
            ->mapFieldWhen('key', 'is_string', 'strtoupper')
            ->run();
    }
    
    public function test_SortLimited_reversed_with_custom_comparator_to_sort_by_value(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->worst(3, static fn(string $first, string $second): int => $first <=> $second)
            ->toArray();
        
        self::assertSame(['e', 'd', 'c'], $result);
    }
    
    public function test_SortLimited_with_default_comparator_to_sort_by_key(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->best(3, null, Check::KEY)
            ->toArray();
        
        self::assertSame(['d', 'a', 'b'], $result);
    }
    
    public function test_SortLimited_reversed_with_default_comparator_to_sort_by_key(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->worst(3, null, Check::KEY)
            ->toArray();
        
        self::assertSame(['e', 'c', 'b'], $result);
    }
    
    public function test_SortLimited_with_custom_comparator_to_sort_by_key(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->best(3, static fn(int $first, int $second): int => $first <=> $second, Check::KEY)
            ->toArray();
        
        self::assertSame(['d', 'a', 'b'], $result);
    }
    
    public function test_SortLimited_reversed_with_custom_comparator_to_sort_by_key(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->worst(3, static fn(int $first, int $second): int => $first <=> $second, Check::KEY)
            ->toArray();
        
        self::assertSame(['e', 'c', 'b'], $result);
    }
    
    public function test_SortLimited_with_default_comparator_to_sort_by_value_and_key(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->best(3, null, Check::BOTH)
            ->toArray();
        
        self::assertSame(['a', 'b', 'c'], $result);
    }
    
    public function test_SortLimited_reversed_with_default_comparator_to_sort_by_value_and_key(): void
    {
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])
            ->worst(3, null, Check::BOTH)
            ->toArray();
        
        self::assertSame(['e', 'd', 'c'], $result);
    }
    
    public function test_SortLimited_with_custom_comparator_to_sort_by_value_and_key(): void
    {
        $comparator = static fn(string $v1, string $v2, int $k1, int $k2): int => $v1 <=> $v2 ?: $k1 <=> $k2;
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])->best(3, $comparator, Check::BOTH)->toArray();
        
        self::assertSame(['a', 'b', 'c'], $result);
    }
    
    public function test_SortLimited_reversed_with_custom_comparator_to_sort_by_value_and_key(): void
    {
        $comparator = static fn(string $v1, string $v2, int $k1, int $k2): int => $v1 <=> $v2 ?: $k1 <=> $k2;
        $result = Stream::from(['d', 'a', 'b', 'c', 'e'])->worst(3, $comparator, Check::BOTH)->toArray();
        
        self::assertSame(['e', 'd', 'c'], $result);
    }
    
    public function test_Fold_with_callback(): void
    {
        $result = Stream::from([2, 3, 4])
            ->castToFloat()
            ->fold(0.5, static fn(float $result, float $value): float => $result * $value)
            ->get();
        
        self::assertSame(12.0, $result);
    }
    
    public function test_hasOnly(): void
    {
        self::assertFalse(Stream::from([3, 1, 2, 1, 3, 2, 4, 1, 2, 3])->hasOnly([1, 2, 3])->get());
        self::assertTrue(Stream::from([3, 1, 2, 1, 3, 2, 1, 2, 3])->hasOnly([1, 2, 3])->get());
    }
    
    public function test_hasOnly_key(): void
    {
        self::assertFalse(Stream::from([3, 1, 2, 1,])->hasOnly([0, 1, 2], Check::KEY)->get());
        self::assertTrue(Stream::from([3, 1, 2])->hasOnly([0, 1, 2], Check::KEY)->get());
    }
    
    public function test_HasOnly_to_check_value_or_key(): void
    {
        $data = ['a' => 1, 'b' => 'a', 'c' => 1, 'd' => 2];
        
        self::assertFalse(Stream::from($data)->hasOnly(['a', 1], Check::ANY)->get());
        self::assertTrue(Stream::from($data)->hasOnly(['a', 1, 2], Check::ANY)->get());
        self::assertTrue(Stream::from($data)->hasOnly(['a', 1, 'd'], Check::ANY)->get());
    }
    
    public function test_HasOnly_to_check_value_and_key(): void
    {
        $data = ['a' => 1, 'b' => 'a', 'c' => 1, 'd' => 2];
        
        self::assertFalse(Stream::from($data)->hasOnly(['a', 1], Check::BOTH)->get());
        self::assertFalse(Stream::from($data)->hasOnly(['a', 'b', 'c', 'd', 1], Check::BOTH)->get());
        self::assertTrue(Stream::from($data)->hasOnly(['a', 'b', 'c', 'd', 1, 2], Check::BOTH)->get());
    }
    
    public function test_HasEvery_to_check_value_or_key(): void
    {
        self::assertFalse(Stream::from(['a' => 1, 'b' => 'c'])->hasEvery(['b', 2], Check::ANY)->get());
        self::assertTrue(Stream::from(['a' => 1, 'b' => 'c'])->hasEvery(['b', 1], Check::ANY)->get());
    }
    
    public function test_HasEvery_to_check_value_and_key(): void
    {
        self::assertFalse(Stream::from(['a' => 'b', 'b' => 'a'])->hasEvery(['a', 1], Check::BOTH)->get());
        self::assertTrue(Stream::from(['a' => 'b', 'b' => 'a'])->hasEvery(['b', 'a'], Check::BOTH)->get());
    }
    
    public function test_Result_allows_to_transform_stream_result_without_affecting_it(): void
    {
        $result = Stream::from([1, 2, 3, 4])
            ->reduce('array_sum')
            ->transform(static fn(int $sum): int => $sum * 2);
        
        self::assertSame(20, $result->get());
        
        $result->transform(static fn(int $sum) => $sum / 2);
        
        self::assertSame(5, $result->get());
    }
    
    public function test_Result_allows_to_produce_result_by_callable_when_empty_was_stream(): void
    {
        $result = Stream::from([1, 2, 3, 4])
            ->filter(Filters::number()->ge(10))
            ->reduce('array_sum')
            ->transform(static fn(int $sum): int => $sum * 2);
        
        self::assertFalse($result->found());
        self::assertNull($result->get());
        self::assertSame(5, $result->getOrElse(static fn(): int => 5));
    }
    
    public function test_callable_is_not_executed_when_result_is_available(): void
    {
        $result = Stream::from([1, 2, 3, 4])->reduce('array_sum');
        
        self::assertTrue($result->found());
        self::assertSame(10, $result->get());
        self::assertSame(10, $result->getOrElse(static fn(): int => 5));
    }
    
    public function test_Result_allows_to_cast_iterable_object_to_string(): void
    {
        $result = Stream::from([1, 2, 3])
            ->find(2)
            ->transform(static fn(int $n): \ArrayObject => new \ArrayObject(\array_fill(0, $n, 'a')));
            
        self::assertSame('a,a', $result->toString());
    }
    
    public function test_Result_allows_to_transform_iterable_to_array(): void
    {
        $result = Stream::from([1, 2, 3])
            ->find(2)
            ->transform(static fn(int $n): \ArrayObject => new \ArrayObject(\array_fill(0, $n, 'a')));
            
        self::assertSame(['a', 'a'], $result->toArray());
    }
    
    public function test_Tokenize_throws_exception_when_value_is_not_string(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation tokenize requires string value, but got integer');
        
        Stream::from([1, 2, 3])->tokenize(' ')->run();
    }
    
    public function test_Tokenize(): void
    {
        $data = ['ala bama', 'okla homa'];
        $expected = ['ala', 'bama', 'okla', 'homa'];
        
        //1
        self::assertSame($expected, Stream::from($data)->tokenize()->toArray());
        self::assertSame($expected, Stream::from($data)->tokenize()->reindex()->toArrayAssoc());
        
        //2
        Stream::from($data)
            ->tokenize()
            ->collectIn($collector = Collectors::default())
            ->run();
            
        self::assertSame($expected, $collector->getData());
        
        //3
        $tokens = Stream::from($data)->tokenize()->collect(true);
        $tokens->run();
        
        self::assertSame($expected, $tokens->get());
        self::assertSame($expected, $tokens->toArray());
        self::assertSame($expected, $tokens->toArrayAssoc());
        
        //4
        $tokens = Stream::from($data)->tokenize()->reindex()->collect();
        
        self::assertSame($expected, $tokens->get());
        self::assertSame($expected, $tokens->toArray());
        self::assertSame($expected, $tokens->toArrayAssoc());
    }
    
    public function test_sortBy_integer_keys(): void
    {
        $rowset = [
            [2, 'Kate', 35],
            [9, 'Chris', 29],
            [6, 'Joanna', 35],
            [5, 'Chris', 26],
            [7, 'Sue', 17],
            [3, 'Kate', 22],
        ];
    
        self::assertSame([
            [5, 'Chris', 26],
            [9, 'Chris', 29],
            [6, 'Joanna', 35],
            [3, 'Kate', 22],
            [2, 'Kate', 35],
            [7, 'Sue', 17],
        ], Stream::from($rowset)->sortBy(1, 2)->toArray());
    
        self::assertSame([
            [5, 'Chris', 26],
            [9, 'Chris', 29],
            [6, 'Joanna', 35],
            [3, 'Kate', 22],
            [2, 'Kate', 35],
            [7, 'Sue', 17],
        ], Stream::from($rowset)->sortBy('1 asc', '2 asc')->toArray());
    
        self::assertSame([
            [9, 'Chris', 29],
            [5, 'Chris', 26],
            [6, 'Joanna', 35],
            [2, 'Kate', 35],
            [3, 'Kate', 22],
            [7, 'Sue', 17],
        ], Stream::from($rowset)->sortBy(1, '2 desc')->toArray());
    
        self::assertSame([
            [7, 'Sue', 17],
            [2, 'Kate', 35],
            [3, 'Kate', 22],
            [6, 'Joanna', 35],
            [9, 'Chris', 29],
            [5, 'Chris', 26],
        ], Stream::from($rowset)->sortBy('1 desc', '2 desc')->toArray());
    
        self::assertSame([
            [5, 'Chris', 26],
            [9, 'Chris', 29],
        ], Stream::from($rowset)->sortBy('1 desc', '2 desc')->reverse()->limit(2)->toArray());
    }
    
    public function test_extractWhen(): void
    {
        $readings = [
            [2, 5, -1, 7, 3, -1, 1],
            [4, null, -1, 5, null, null, 1],
            [3, 5, null, -1, 5, 4, 3],
        ];
        
        $averages = Stream::from($readings)
            ->extractWhen(Filters::isInt())
            ->extractWhen(Filters::greaterThan(-1))
            ->extractWhen(Filters::lessThan(10))
            ->map(Reducers::average())
            ->toArray();
        
        self::assertSame([
            (2 + 5 + 7 + 3 + 1) / 5,
            (4 + 5 + 1) / 3,
            (3 + 5 + 5 + 4 + 3) / 5,
        ], $averages);
    }
    
    public function test_removeWhen(): void
    {
        $readings = [
            [2, 5, -1, 7, 3, -1, 1],
            [4, null, -1, 5, null, null, 1],
            [3, 5, null, -1, 5, 4, 3],
        ];
    
        $expected = [
            (2 + 5 + 7 + 3 + 1) / 5,
            (4 + 5 + 1) / 3,
            (3 + 5 + 5 + 4 + 3) / 5,
        ];
    
        self::assertSame($expected, Stream::from($readings)
            ->removeWhen(Filters::OR(Filters::NOT('is_int'), Filters::lessThan(0), Filters::greaterThan(9)))
            ->map(Reducers::average())
            ->toArray());
    
        self::assertSame($expected, Stream::from($readings)
            ->removeWhen(Filters::NOT('is_int'))
            ->removeWhen(Filters::lessThan(0))
            ->removeWhen(Filters::greaterThan(9))
            ->map(Reducers::average())
            ->toArray());
    }
    
    public function test_map_row_using_Filter_to_remove_invalid_values_from_them(): void
    {
        $readings = [
            [2, 5, -1, 7, 3, -1, 1],
            [4, null, -1, 5, null, null, 1],
            [3, 5, null, -1, 5, 4, 3],
        ];
        
        $expected = [
            (2 + 5 + 7 + 3 + 1) / 5,
            (4 + 5 + 1) / 3,
            (3 + 5 + 5 + 4 + 3) / 5,
        ];
        
        self::assertSame($expected, Stream::from($readings)
            ->map(Filters::AND('\is_int', Filters::greaterOrEqual(0), Filters::lessOrEqual(9)))
            ->map(Reducers::average())
            ->toArray()
        );
    }
    
    public function test_extractWhen_throws_exception_when_element_is_not_iterable(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Iterable value is required but got integer');
        
        Stream::from([5, 6, 8])->extractWhen('is_string')->run();
    }
    
    public function test_loop_with_limit(): void
    {
        Stream::of(1)
            ->reindex()
            ->collectIn($collector = Collectors::default())
            ->limit(3)
            ->map(static fn(int $n): int => $n + 1)
            ->loop(true);
        
        self::assertSame(\range(1, 3), $collector->getData());
    }
    
    public function test_loop_with_until(): void
    {
        Stream::of(1)
            ->collectIn($collector = Collectors::default(), true)
            ->map(static fn(int $n): int => $n + 1)
            ->until(4)
            ->loop(true);
        
        self::assertSame(\range(1, 3), $collector->getData());
    }
    
    public function test_loop_with_filter(): void
    {
        $stream = Stream::of(1, 3, Producers::sequentialInt(4, 1, 1))
            ->collectIn($collector = Collectors::default(), true)
            ->map(static fn($n): int => $n * 2)
            ->lessThan(10)
            ->loop();

        $stream->run();

        self::assertSame([1, 2, 4, 8, 3, 6, 4, 8], $collector->getData());
    }
    
    public function test_concat(): void
    {
        self::assertSame('abc', Stream::from(['a', 'b', 'c'])->reduce('implode')->get());
    }
    
    public function test_each_gather_makes_nested_array(): void
    {
        $stream = StreamMaker::from([1, 2, 3, 4]);
    
        //1 gather
        $result = null;
        $stream->start()->gather()->call(static function (array $all) use (&$result) {
            $result = $all;
        })->run();
        
        self::assertSame([1,2,3,4], $result);
    
        //2 gathers
        $result = null;
        $stream->start()->gather()->gather()->call(static function (array $all) use (&$result) {
            $result = $all;
        })->run();
        
        self::assertSame([[1,2,3,4]], $result);
    
        //3 gathers
        $result = null;
        $stream->start()->gather()->gather()->gather()->call(static function (array $all) use (&$result) {
            $result = $all;
        })->run();
        
        self::assertSame([[[1,2,3,4]]], $result);
    }
    
    public function test_gather_with_preserve_keys(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
    
        $result = null;
        $stream->gather()->call(static function (array $all) use (&$result) {
            $result = $all;
        })->run();
     
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $result);
    }
    
    public function test_gather_with_reindex(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
    
        $result = null;
        $stream->reindex()->gather(true)->call(static function (array $all) use (&$result) {
            $result = $all;
        })->run();
     
        self::assertSame([1, 2, 3, 4], $result);
    }
    
    public function test_gather_with_keys_preserve_and_flat_level_1(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->gather()->flat(1)->collectIn($collector)->run();
        
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $collector->getData());
    }
    
    public function test_gather_with_keys_preserve_and_flat_level_full(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->gather()->flat()->collectIn($collector)->run();
        
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $collector->getData());
    }
    
    public function test_gather_with_keys_preserve_and_flat_level_limited(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->gather()->flat(3)->collectIn($collector)->run();
        
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4], $collector->getData());
    }
    
    public function test_gather_with_reindex_and_flat_level_1(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->gather(true)->flat(1)->collectIn($collector)->run();
        
        self::assertSame([1, 2, 3, 4], $collector->getData());
    }
    
    public function test_gather_with_reindex_and_flat_level_full(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->gather(true)->flat()->collectIn($collector)->run();
        
        self::assertSame([1, 2, 3, 4], $collector->getData());
    }
    
    public function test_gather_with_reindex_and_flat_level_limited(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->gather(true)->flat(3)->collectIn($collector)->run();
        
        self::assertSame([1, 2, 3, 4], $collector->getData());
    }
    
    public function test_gather_pushes_collected_data_to_next_operation_when_stream_is_terminated(): void
    {
        $stream = Stream::from(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        
        $collector = Collectors::default();
        $stream->while(Filters::lessThan(3))->gather()->collectIn($collector)->run();
        
        self::assertSame([['a' => 1, 'b' => 2]], $collector->getData());
    }
    
    public function test_collect_while(): void
    {
        self::assertSame(
            [1, 2],
            Stream::from([1, 2, 3, 4])->collectWhile(Filters::lessThan(3))->toArray()
        );
    }
    
    public function test_collect_until(): void
    {
        self::assertSame(
            [1, 2],
            Stream::from([1, 2, 3, 4])->collectUntil(Filters::greaterThan(2))->toArray()
        );
    }
    
    public function test_make_tuple(): void
    {
        self::assertSame(
            '[[0,1],[1,2]]',
            Stream::from([1, 2, 3, 4])->limit(2)->makeTuple()->toJson()
        );
        
        self::assertSame(
            '[[0,1],[1,2]]',
            Stream::from([1, 2, 3, 4])->makeTuple()->limit(2)->toJson()
        );
    
        self::assertSame(
            '[{"key":1,"value":2},{"key":2,"value":3}]',
            Stream::from([1, 2, 3, 4])->skip(1)->makeTuple(true)->limit(2)->toJson()
        );
    }
    
    public function test_gather_on_empty_stream(): void
    {
        self::assertSame('', Stream::empty()->gather()->toString());
    }
    
    public function test_reindex_throws_exception_when_step_is_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid param step');
        
        Stream::from([1, 2])->reindex(0, 0)->run();
    }
    
    public function test_reverse_on_empty_stream(): void
    {
        self::assertSame('', Stream::empty()->reverse()->toString());
    }
    
    public function test_shuffle_on_empty_stream(): void
    {
        self::assertSame('', Stream::empty()->shuffle()->toString());
    }
    
    public function test_shuffle_chunked_values(): void
    {
        $data = \range(1, 1000);
        $chunkSize = 100;
    
        $result = Stream::from($data)->shuffle($chunkSize)->toArray();
    
        for ($i = 0; $i < 1000; $i += $chunkSize) {
            self::assertNotSame(\array_slice($data, $i, $chunkSize), \array_slice($result, $i, $chunkSize));
        }
    }
    
    public function test_shuffle_throws_exception_when_chunk_size_is_less_than_1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid param chunkSize');
        
        Stream::from([1, 2])->shuffle(0)->run();
    }
    
    public function test_SortLimited_on_empty_stream(): void
    {
        self::assertSame('', Stream::empty()->best(10)->toString());
    }
    
    public function test_reindexBy_many_field_use_last_one(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 9, 'name' => 'Chris', 'age' => 26],
        ];
        
        $result = Stream::from($rowset)
            ->reindexBy('name')
            ->reindexBy('id')
            ->extract(['name', 'age'])
            ->toArrayAssoc();
        
        $expected = [
            2 => ['name' => 'Kate', 'age' => 35],
            9 => ['name' => 'Chris', 'age' => 26],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_mapKey_will_not_use_mapper_Key_and_map_will_not_use_mapper_Value(): void
    {
        self::assertSame(
            ['a', 'b', 'c'],
            Stream::from(['a', 'b', 'c'])->map(Mappers::value())->mapKey(Mappers::key())->toArrayAssoc()
        );
    }
    
    public function test_map_key_to_value_and_then_value_to_key_is_optimized(): void
    {
        self::assertSame(
            [0, 1, 2],
            Stream::from(['a', 'b', 'c'])->map(Mappers::key())->mapKey(Mappers::value())->toArrayAssoc()
        );
    }
    
    /**
     * @dataProvider getDataForTestMapKeyValueThrowsExcetionWhenResultOfMapperIsNotValid
     */
    public function test_MapKeyValue_throws_excetion_when_result_of_mapper_is_not_valid($mapper): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Result returned from KeyValue mapper is invalid');
        
        Stream::from(['a', 'b', 'c'])->mapKV($mapper)->run();
    }
    
    public function getDataForTestMapKeyValueThrowsExcetionWhenResultOfMapperIsNotValid(): \Generator
    {
        $mappers = [
            static fn(): array => [], //empty array
            static fn($value): array => ['a', 'b'], //too many elements
            static fn($value, $key): array => [$key => $value, 'something'], //too many elements
        ];
    
        foreach ($mappers as $mapper) {
            yield [$mapper];
        }
    }
    
    public function test_MapKeyValue_can_use_Mapper_to_compute_value(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Kate', 'age' => 35],
            ['id' => 9, 'name' => 'Chris', 'age' => 26],
        ];
        
        $result = Stream::from($rowset)
            ->reindexBy('id', true)
            ->toArrayAssoc();
        
        $expected = [
            2 => ['name' => 'Kate', 'age' => 35],
            9 => ['name' => 'Chris', 'age' => 26],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_chunkBy_throws_exception_when_value_returned_from_discriminator_is_invalid(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Unsupported value was returned from discriminator (got object of type stdClass)'
        );
    
        Stream::from([1])->chunkBy(static fn($v): object => new \stdClass())->run();
    }
    
    public function test_chunkBy_can_preserve_keys_of_elements_in_stream(): void
    {
        $data = ['a', 'e', 12, 'b', 'd', 8, 9, 6, 'c'];
        $result = Stream::from($data)->chunkBy('is_string')->toArray();
        
        $expected = [
            [0 => 'a', 1 => 'e'],
            [2 => 12],
            [3 => 'b', 4 => 'd'],
            [5 => 8, 6 => 9, 7 => 6],
            [8 => 'c'],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_chunkBy_can_reindex_keys_of_elements_in_stream(): void
    {
        $data = ['a', 'e', 12, 'b', 'd', 8, 9, 6, 'c'];
        $result = Stream::from($data)->chunkBy('is_string', true)->toArray();
        
        $expected = [
            ['a', 'e'],
            [12],
            ['b', 'd'],
            [8, 9, 6],
            ['c'],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_reduce_operation_can_also_count_number_of_elements_in_stream(): void
    {
        $data = ['a', 'b', 'c', 'd', 'e'];
        
        self::assertSame(5, Stream::from($data)->reduce('count')->get());
        self::assertSame(5, Stream::from($data)->reduce('\count')->get());
        self::assertSame(5, Stream::from($data)->reduce(Reducers::count())->get());
    }
    
    public function test_accumulate_can_reindex_keys(): void
    {
        $stream = Stream::from([5,8,2,1,4,2,6,3,7,2,8,4,1,4,5,6,2,3,7])
            ->accumulate(Filters::number()->isEven(), true, Check::VALUE);
        
        $expected = [
            [8,2],
            [4,2,6],
            [2,8,4],
            [4],
            [6,2],
        ];
        
        self::assertSame($expected, $stream->toArray());
    }
    
    public function test_accumulate_can_preserve_keys(): void
    {
        $stream = Stream::from([5,8,2,1,4,2,6,3,7,2,8,4,1,4,5,6,2,3,7])
            ->accumulate(Filters::number()->isEven());
        
        $expected = [
            [1 => 8, 2],
            [4 => 4, 2, 6],
            [9 => 2, 8, 4],
            [13 => 4],
            [15 => 6, 2],
        ];
        
        self::assertSame($expected, $stream->toArray());
    }
    
    public function test_separate_can_preserve_keys(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Sue', 'age' => 22],
            ['id' => 9, 'name' => 'Chris', 'age' => 17],
            ['id' => 6, 'name' => 'Joanna', 'age' => 15],
            ['id' => 5, 'name' => 'Chris', 'age' => 24],
            ['id' => 7, 'name' => 'Sue', 'age' => 18],
        ];
        
        $adults = Stream::from($rowset)
            ->reindexBy('id', true)
            ->separateBy(Filters::filterBy('age', Filters::lessOrEqual(18)))
            ->toArray();
    
        $expected = [
            [
                2 => ['name' => 'Sue', 'age' => 22],
            ], [
                5 => ['name' => 'Chris', 'age' => 24],
            ],
        ];
        
        self::assertSame($expected, $adults);
    }
    
    public function test_mapWhen_with_value_as_mapper_has_no_effect(): void
    {
        $data = ['foo', 'bar'];
        
        $result = Stream::from($data)
            ->mapWhen('is_string', Mappers::value(), Mappers::value())
            ->toArray();
        
        self::assertSame($data, $result);
    }
    
    public function test_mapFieldWhen_with_value_as_mapper_has_no_effect(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Sue', 'age' => 22],
            ['id' => 9, 'name' => 'Chris', 'age' => 17],
        ];
        
        $result = Stream::from($rowset)
            ->mapFieldWhen('name', 'is_string', Mappers::value(), Mappers::value())
            ->toArray();
        
        self::assertSame($rowset, $result);
        
        Stream::from($rowset)->filter('is_int')->limit(4)->first()->get();
    }
    
    public function test_stream_can_abort_processing_silently_on_error(): void
    {
        $counter = 0;
        
        $data = Stream::from([1, 5, 2, 7, 9, 2, 4, 7, 8, 3, 2])
            ->onError(OnError::abort())
            ->call(function () use (&$counter) {
                if ($counter++ === 3) {
                    throw new \RuntimeException();
                }
            })
            ->collect()
            ->get();
        
        self::assertSame([1, 5, 2], $data);
    }
}