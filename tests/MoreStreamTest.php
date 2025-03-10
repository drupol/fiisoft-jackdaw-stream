<?php declare(strict_types=1);

namespace FiiSoft\Test\Jackdaw;

use FiiSoft\Jackdaw\Collector\Collectors;
use FiiSoft\Jackdaw\Comparator\Comparators;
use FiiSoft\Jackdaw\Consumer\Consumers;
use FiiSoft\Jackdaw\Discriminator\Discriminators;
use FiiSoft\Jackdaw\Filter\Filters;
use FiiSoft\Jackdaw\Internal\Check;
use FiiSoft\Jackdaw\Mapper\Mappers;
use FiiSoft\Jackdaw\Operation\Internal\AssertionFailed;
use FiiSoft\Jackdaw\Producer\Producers;
use FiiSoft\Jackdaw\Reducer\Reducers;
use FiiSoft\Jackdaw\Registry\Registry;
use FiiSoft\Jackdaw\Stream;
use PHPUnit\Framework\TestCase;

final class MoreStreamTest extends TestCase
{
    public function test_trim(): void
    {
        self::assertSame(['foo', 'bar'], Stream::from([' foo ', ' bar '])->trim()->toArray());
    }
    
    public function test_assert(): void
    {
        $this->expectException(AssertionFailed::class);
        
        Stream::from(['a', 1, 'b'])
            ->assert(Filters::isString())
            ->run();
    }
    
    public function test_omitBy(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Sue', 'age' => 22],
            ['id' => 9, 'name' => 'Chris', 'age' => 17],
            ['id' => 6, 'name' => 'Joanna', 'age' => 15],
            ['id' => 5, 'name' => 'Chris', 'age' => 24],
            ['id' => 7, 'name' => 'Sue', 'age' => 18],
        ];
        
        $ids = Stream::from($rowset)
            ->omitBy('age', Filters::lessThan(18))
            ->extract('id')
            ->toArray();
        
        self::assertSame([2, 5, 7], $ids);
    }
    
    public function test_castToString_simple_values(): void
    {
        self::assertSame(['1', '2', '3'], Stream::from([1, 2, 3])->castToString()->toArray());
    }
    
    public function test_castToString_fields_in_arrays(): void
    {
        $rowset = [
            ['id' => 2, 'age' => 22],
            ['id' => 9, 'age' => 17],
            ['id' => 6, 'age' => 15],
            ['id' => 5, 'age' => 24],
            ['id' => 7, 'age' => 18],
        ];
        
        $expected = [
            ['id' => 2, 'age' => '22'],
            ['id' => 9, 'age' => '17'],
            ['id' => 6, 'age' => '15'],
            ['id' => 5, 'age' => '24'],
            ['id' => 7, 'age' => '18'],
        ];
        
        self::assertSame($expected, Stream::from($rowset)->castToString('age')->toArray());
    }
    
    public function test_castToBool_simple_values(): void
    {
        self::assertSame(
            [true, false, false, false, true],
            Stream::from([1, 0, '', '0', '1'])->castToBool()->toArray()
        );
    }
    
    public function test_castToBool_fields_in_arrays(): void
    {
        $rowset = [
            ['id' => 2, 'age' => null],
            ['id' => 9, 'age' => 17],
            ['id' => 6, 'age' => 0],
            ['id' => 5, 'age' => ''],
        ];
        
        $expected = [
            ['id' => 2, 'age' => false],
            ['id' => 9, 'age' => true],
            ['id' => 6, 'age' => false],
            ['id' => 5, 'age' => false],
        ];
        
        self::assertSame($expected, Stream::from($rowset)->castToBool('age')->toArray());
    }
    
    public function test_rename(): void
    {
        $rowset = [
            ['id' => 2, 'sex' => 'female'],
            ['id' => 9, 'sex' => 'male'],
            ['id' => 6, 'sex' => null],
            ['id' => 5, 'sex' => 'male'],
        ];
        
        $expected = [
            ['id' => 2, 'gender' => 'female'],
            ['id' => 9, 'gender' => 'male'],
            ['id' => 6, 'gender' => null],
            ['id' => 5, 'gender' => 'male'],
        ];
        
        self::assertSame($expected, Stream::from($rowset)->rename('sex', 'gender')->toArray());
    }
    
    public function test_remap(): void
    {
        $rowset = [
            ['id' => 2, 'sex' => 'female'],
            ['id' => 9, 'sex' => 'male'],
            ['id' => 6, 'sex' => null],
            ['id' => 5, 'sex' => 'male'],
        ];
        
        $expected = [
            ['id' => 2, 'gender' => 'female'],
            ['id' => 9, 'gender' => 'male'],
            ['id' => 6, 'gender' => null],
            ['id' => 5, 'gender' => 'male'],
        ];
        
        self::assertSame($expected, Stream::from($rowset)->remap(['sex' => 'gender'])->toArray());
    }
    
    public function test_gatherWhile(): void
    {
        $data = [5, 2, 4, 'a', 2, 3, 'b'];
        
        $sumOfFirstIntegers = Stream::from($data)->gatherWhile('is_int')->map(Reducers::sum())->first();
        
        self::assertSame(11, $sumOfFirstIntegers->get());
    }
    
    public function test_gatherUntil(): void
    {
        $data = [5, 2, 4, 'a', 2, 3, 'b'];
        
        $sumOfFirstIntegers = Stream::from($data)->gatherUntil('is_string')->map(Reducers::sum())->first();
        
        self::assertSame(11, $sumOfFirstIntegers->get());
    }
    
    public function test_onError_can_accept_callback(): void
    {
        $errorHandled = false;
        
        $result = Stream::from([1, 'a', 2])
            ->onError(static function () use (&$errorHandled): bool {
                $errorHandled = true;
                return true; //continue
            })
            ->map(static fn($v) => $v * 2)
            ->toArray();
        
        self::assertTrue($errorHandled);
        self::assertSame([2, 4], $result);
    }
    
    public function test_onError_allows_to_replace_all_previously_set_handlers(): void
    {
        $errorHandled = false;
        
        $result = Stream::from([1, 'a', 2])
            ->onError(static function () use (&$errorHandled): bool {
                $errorHandled = true;
                return true; //continue
            })
            ->onError(static function () use (&$errorHandled): bool {
                $errorHandled = true;
                return false; //abort
            }, true)
            ->map(static fn($v) => $v * 2)
            ->toArray();
        
        self::assertTrue($errorHandled);
        self::assertSame([2], $result);
    }
    
    public function test_onError_throws_exception_when_invalid_handler_is_passed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid param handler');
        
        Stream::empty()->onError(15);
    }
    
    public function test_onSuccess_can_accept_callback(): void
    {
        $flag = 0;
        
        Stream::empty()
            ->onSuccess(static function () use (&$flag): void {
                $flag = 1;
            })
            ->run();
        
        self::assertSame(1, $flag);
    }
    
    public function test_onSuccess_allows_to_replace_all_previously_set_handlers(): void
    {
        $flag = 0;
        
        Stream::empty()
            ->onSuccess(static function () use (&$flag): void {
                $flag = 2;
            })
            ->onSuccess(static function () use (&$flag): void {
                $flag = 1;
            }, true)
            ->run();
        
        self::assertSame(1, $flag);
    }
    
    public function test_onFinish_can_accept_callback(): void
    {
        $flag = 0;
        
        Stream::empty()
            ->onFinish(static function () use (&$flag): void {
                $flag = 1;
            })
            ->run();
        
        self::assertSame(1, $flag);
    }
    
    public function test_onFinish_allows_to_replace_all_previously_set_handlers(): void
    {
        $flag = 0;
        
        Stream::empty()
            ->onFinish(static function () use (&$flag): void {
                $flag = 2;
            })
            ->onFinish(static function () use (&$flag): void {
                $flag = 1;
            }, true)
            ->run();
        
        self::assertSame(1, $flag);
    }
    
    public function test_cannot_add_operation_to_stream_that_has_already_started(): void
    {
        //Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot add operation to a stream that has already started');
        
        //Arrange
        $stream = Stream::from([1, 2, 3]);
        $stream = $stream->limit(2);
        $stream->run();
        
        //Act
        $stream->filter('is_int');
    }
    
    public function test_fork_with_tail(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd'])
            ->fork('is_string', Stream::empty()->tail(2)->collect(true))
            ->toArrayAssoc();
        
        self::assertSame([
            true => ['c', 'd'],
            false => [2, 3],
        ], $result);
    }
    
    public function test_fork_with_shuffle_and_count(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd'])
            ->fork('is_string', Stream::empty()->shuffle()->count())
            ->toArrayAssoc();
        
        self::assertSame([
            true => 4,
            false => 3,
        ], $result);
    }
    
    public function test_fork_with_shuffle_and_redue(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd'])
            ->fork('is_string', Stream::empty()->shuffle()->collect())
            ->toArrayAssoc();
        
        self::assertCount(2, $result);
        self::assertArrayHasKey(1, $result);
        self::assertArrayHasKey(0, $result);
        
        self::assertCount(4, $result[1]);
        self::assertCount(3, $result[0]);
    }
    
    public function test_fork_with_scan(): void
    {
        $result = Stream::from([4, 1, 5, 2, 6, 3, 7])
            ->fork(
                Discriminators::evenOdd(),
                Stream::empty()->scan(0, Reducers::sum())->collect(true)
            )
            ->toArrayAssoc();
        
        self::assertSame([
            'even' => [0, 4, 6, 12],
            'odd' => [0, 1, 6, 9, 16],
        ], $result);
    }
    
    public function test_fork_with_flat(): void
    {
        $result = Stream::from([4, 1, 5, 2, 6, 3, 7])
            ->chunk(2)
            ->fork(
                '\count',
                Stream::empty()->flat()->reduce(Reducers::sum())
            )
            ->toArrayAssoc();
        
        self::assertSame([
            2 => 21,
            1 => 7,
        ], $result);
    }
    
    public function test_fork_with_collectKey(): void
    {
        $collector = Collectors::default();
        
        $result = Stream::from(['A' => 1, 'b' => 2, 'C' => 3, 'd' => 4])
            ->fork(
                Discriminators::getAdapter(Filters::onlyIn(['A', 'C']), Check::KEY),
                Stream::empty()->collectKeysIn($collector)->castToString()->reduce(Reducers::concat())
            )
            ->toArrayAssoc();
        
        self::assertSame([
            true => '13',
            false => '24',
        ], $result);
        
        self::assertSame('AbCd', $collector->toString(''));
    }
    
    public function test_fork_with_collectIn(): void
    {
        $collector = Collectors::default();
        
        $result = Stream::from(['A', 'b', 'C', 'd'])
            ->fork(
                Filters::onlyIn(['A', 'C']),
                Stream::empty()->collectIn($collector)->reduce(Reducers::concat())
            )
            ->toArrayAssoc();
        
        self::assertSame([
            true => 'AC',
            false => 'bd',
        ], $result);
        
        self::assertSame('AbCd', $collector->toString(''));
    }
    
    public function test_fork_with_last(): void
    {
        $result = Stream::from(['A', 'b', 'C', 'd'])
            ->fork(
                Filters::onlyIn(['A', 'C']),
                Stream::empty()->last()
            )
            ->toArrayAssoc();
        
        self::assertSame([
            true => 'C',
            false => 'd',
        ], $result);
    }
    
    public function test_fork_with_first(): void
    {
        $result = Stream::from(['A', 'b', 'C', 'd'])
            ->fork(
                Filters::onlyIn(['A', 'C']),
                Stream::empty()->first()
            )
            ->toArrayAssoc();
        
        self::assertSame([
            true => 'A',
            false => 'b',
        ], $result);
    }
    
    public function test_collectKeys(): void
    {
        $data = ['a', 'v', 'c', 'r', 's', 'd'];
        
        $keys = Stream::from($data)->flip()->collectKeys();
        
        self::assertSame($data, $keys->get());
        self::assertSame($data, $keys->toArray());
        self::assertSame($data, $keys->toArrayAssoc());
    }
    
    public function test_chunk_with_one_element_in_each_chunk(): void
    {
        $result = Stream::from([6,2,3])->chunk(1, true)->toArray();
        
        self::assertSame([[6], [2], [3]], $result);
    }
    
    public function test_send_value_to_variable_by_reference(): void
    {
        Stream::from(['a', 'b', 'c'])
            ->call(Consumers::sendValueTo($value))
            ->forEach(static function ($current) use (&$value): void {
                self::assertSame($current, $value);
            });
    }
    
    public function test_send_key_to_variable_by_reference(): void
    {
        Stream::from(['a', 'b', 'c'])
            ->call(Consumers::sendKeyTo($key))
            ->forEach(static function ($v, $k) use (&$key): void {
                self::assertSame($k, $key);
            });
    }
    
    public function test_send_key_and_value_to_variables_by_reference(): void
    {
        Stream::from(['a', 'b', 'c'])
            ->call(Consumers::sendValueKeyTo($value, $key))
            ->forEach(static function ($v, $k) use (&$value, &$key): void {
                self::assertSame($v, $value);
                self::assertSame($k, $key);
            });
    }
    
    public function test_stream_with_fork_in_fork_1(): void
    {
        $rowset = $this->flatRowset();
        
        $result = Stream::from($rowset)
            ->forkBy(
                'sex',
                Stream::empty()
                    ->remove('sex')
                    ->mapKey(static fn(array $row): string => $row['age'] >= 18 ? 'adults' : 'kids')
                    ->fork(
                        Discriminators::byKey(),
                        Stream::empty()->reindexBy('id', true)->sortBy('age')->collect()
                    )
                    ->collect()
            )
            ->collect();
        
        $expected = $this->nestedDataStructure();
        
        self::assertCount(2, $result);
        self::assertSame(2, $result->count());
        self::assertSame($expected, $result->get());
        self::assertSame($expected, $result->toArrayAssoc());
        
        self::assertSame(['female', 'male'], $result->stream()->flip()->collect(true)->get());
        self::assertSame(['female', 'male'], $result->stream()->flip()->collect(true)->toArray());
    }
    
    public function test_stream_with_fork_in_fork_2(): void
    {
        $rowset = $this->flatRowset();
        
        $result = Stream::from($rowset)
            ->forkBy(
                'sex',
                Stream::empty()
                    ->remove('sex')
                    ->fork(
                        static fn(array $row): string => $row['age'] >= 18 ? 'adults' : 'kids',
                        Stream::empty()->reindexBy('id', true)->sortBy('age')->collect()
                    )
                    ->collect()
            )
            ->collect();
        
        $expected = $this->nestedDataStructure();
        
        self::assertCount(2, $result);
        self::assertSame(2, $result->count());
        self::assertSame($expected, $result->get());
        self::assertSame($expected, $result->toArrayAssoc());
        
        self::assertSame(['female', 'male'], $result->stream()->flip()->collect(true)->get());
        self::assertSame(['female', 'male'], $result->stream()->flip()->collect(true)->toArray());
    }
    
    public function test_iterate_over_nested_structure_1(): void
    {
        $data = $this->nestedDataStructure();
        
        $sex = null;
        
        $collector = Stream::empty()
            ->map(static function (array $row, $key) use (&$sex): array {
                return [
                    'id' => $key,
                    'sex' => $sex,
                    'age' => $row['age'],
                    'name' => $row['name'],
                ];
            })
            ->collect(true);
        
        
        Stream::from($data)
            ->call(Consumers::sendKeyTo($sex))
            ->flat(2)
            ->feed($collector)
            ->run();
            
        $expected = $this->flatRowset();
        
        self::assertSame($expected, $collector->get());
    }
    
    public function test_iterate_over_nested_structure_2(): void
    {
        $data = $this->nestedDataStructure();
        
        $rowset = [];
        
        Stream::from($data)
            ->forEach(static function (array $bySex, string $sex) use (&$rowset): void {
                Stream::from($bySex)
                    ->flat(1)
                    ->forEach(static function (array $row, int $id) use (&$rowset, $sex): void {
                        $rowset[] = [
                            'id' => $id,
                            'sex' => $sex,
                            'age' => $row['age'],
                            'name' => $row['name'],
                        ];
                    });
            });
        
        $expected = $this->flatRowset();
        
        self::assertSame($expected, $rowset);
    }
    
    public function test_iterate_over_nested_structure_3(): void
    {
        $data = $this->nestedDataStructure();
        
        $rowset = Stream::from($data)
            ->call(Consumers::sendKeyTo($sex))
            ->flat(2)
            ->map(static function (array $row, $key) use (&$sex): array {
                return [
                    'id' => $key,
                    'sex' => $sex,
                    'age' => $row['age'],
                    'name' => $row['name'],
                ];
            })
            ->toArray();
        
        $expected = $this->flatRowset();
        
        self::assertSame($expected, $rowset);
    }
    
    public function test_iterate_over_nested_structure_4(): void
    {
        $data = $this->nestedDataStructure();
        
        $rowset = Stream::from($data)
            ->call(Consumers::sendKeyTo($sex))
            ->flat(2)
            ->append('id', Mappers::key())
            ->append('sex', Mappers::readFrom($sex))
            ->extract(['id', 'sex', 'age', 'name'])
            ->toArray();
        
        $expected = $this->flatRowset();
        
        self::assertSame($expected, $rowset);
    }
    
    public function test_use_registry_for_temporary_variables(): void
    {
        $data = $this->nestedDataStructure();
        $reg = Registry::new();
        
        $rowset = Stream::from($data)
            ->remember($reg->key('sex'))
            ->flat(2)
            ->append('id', Mappers::key())
            ->append('sex', $reg->read('sex'))
            ->extract(['id', 'sex', 'age', 'name'])
            ->toArray();
        
        $expected = $this->flatRowset();
        
        self::assertSame($expected, $rowset);
    }
    
    public function test_fizzbuzz(): void
    {
        $result = Stream::from(Producers::sequentialInt(1, 1, 30))
            ->mapKey(static fn(int $n): int => ($n % 3 === 0 ? 2 : 0) | ($n % 5 === 0 ? 1 : 0))
            ->map(static fn(int $n, int $k): string => (string) [$n, 'Buzz', 'Fizz', 'Fizz Buzz'][$k])
            ->reduce(Reducers::concat(', '))
            ->get();
        
        $expected = '1, 2, Fizz, 4, Buzz, Fizz, 7, 8, Fizz, Buzz, 11, Fizz, 13, 14, Fizz Buzz, 16, 17, Fizz, 19, '
            .'Buzz, Fizz, 22, 23, Fizz, Buzz, 26, Fizz, 28, 29, Fizz Buzz';
        
        self::assertSame($expected, $result);
    }
    
    public function test_fibonacci(): void
    {
        $collector = Stream::empty()->reduce(Reducers::concat(', '));
        
        Stream::of(1)
            ->reindex(1)
            ->scan(0, Reducers::sum())
            ->feed($collector)
            ->until(34, Check::KEY)
            ->loop(true);
        
        $expected = '0, 1, 1, 2, 3, 5, 8, 13, 21, 34, 55, 89, 144, 233, 377, 610, 987, 1597, 2584, 4181, 6765, '
            .'10946, 17711, 28657, 46368, 75025, 121393, 196418, 317811, 514229, 832040, 1346269, 2178309, 3524578';
        
        self::assertSame($expected, $collector->get());
    }
    
    public function test_collatz(): void
    {
        $collector = Stream::empty()->reduce(Reducers::concat(', '));
        
        Stream::from($queue = Producers::queue([304]))
            ->feed($collector)
            ->while(Filters::greaterThan(1))
            ->mapWhen(
                static fn(int $n): bool => ($n & 1) === 0,
                static fn(int $n): int => $n >> 1,
                static fn(int $n): int => (3 * $n + 1)
            )
            ->call($queue)
            ->run();
        
        $expected = '304, 152, 76, 38, 19, 58, 29, 88, 44, 22, 11, 34, 17, 52, 26, 13, 40, 20, 10, 5, 16, 8, 4, 2, 1';
        
        self::assertSame($expected, $collector->get());
    }
    
    public function test_stream_with_two_forks_in_pipeline(): void
    {
        $stream = Stream::from($this->flatRowset())
            ->fork(
                Discriminators::byField('sex'),
                Stream::empty()->collect(true)
            )
            ->omit('male', Check::KEY)
            ->flat(1)
            ->fork(
                static fn(array $row): string => $row['age'] >= 18 ? 'adult' : 'kid',
                Stream::empty()->collect(true)
            )
            ->omit('kid', Check::KEY)
            ->flat(1)
            ->sortBy('age desc')
            ->extract(['id', 'name'])
            ->remap(['id' => 0, 'name' => 1])
            ->collect(true)
        ;
        
        $expected = [
            [2, 'Sue'],
            [7, 'Cate'],
        ];

        self::assertSame($expected, $stream->get());
    }

    private function flatRowset(): array
    {
        return [
            ['id' => 7, 'sex' => 'female', 'age' => 18, 'name' => 'Cate'],
            ['id' => 2, 'sex' => 'female', 'age' => 22, 'name' => 'Sue'],
            ['id' => 6, 'sex' => 'female', 'age' => 15, 'name' => 'Joanna'],
            ['id' => 4, 'sex' => 'male', 'age' => 15, 'name' => 'Paul'],
            ['id' => 9, 'sex' => 'male', 'age' => 17, 'name' => 'Chris'],
            ['id' => 5, 'sex' => 'male', 'age' => 24, 'name' => 'John'],
        ];
    }
    
    private function nestedDataStructure(): array
    {
        return [
            'female' => [
                'adults' => [
                    7 => ['age' => 18, 'name' => 'Cate'],
                    2 => ['age' => 22, 'name' => 'Sue'],
                ],
                'kids' => [
                    6 => ['age' => 15, 'name' => 'Joanna'],
                ],
            ],
            'male' => [
                'kids' => [
                    4 => ['age' => 15, 'name' => 'Paul'],
                    9 => ['age' => 17, 'name' => 'Chris'],
                ],
                'adults' => [
                    5 => ['age' => 24, 'name' => 'John'],
                ],
            ],
        ];
    }
    
    public function test_reverse_with_collect(): void
    {
        $result = Stream::from(['a', 'b', 'c', 'd'])
            ->reverse()
            ->collect(true);
        
        self::assertSame(['d', 'c', 'b', 'a'], $result->get());
    }
    
    public function test_reverse_with_first(): void
    {
        $result = Stream::from(['a', 'b', 'c', 'd'])
            ->reverse()
            ->first();
        
        self::assertSame('d', $result->get());
    }
    
    public function test_reverse_with_last(): void
    {
        $result = Stream::from(['a', 'b', 'c', 'd'])
            ->reverse()
            ->last();
        
        self::assertSame('a', $result->get());
    }
    
    public function test_gather_with_flat_and_collect(): void
    {
        $result = Stream::from([5, 2, 8, 0, 'a', 3, 1, 'b', 4])
            ->gatherUntil('is_string')
            ->flat(1)
            ->collect();
        
        self::assertSame([5, 2, 8, 0], $result->get());
        self::assertSame([5, 2, 8, 0], $result->toArray());
        self::assertSame([5, 2, 8, 0], $result->toArrayAssoc());
    }
    
    public function test_gather_with_reduce_to_single_value(): void
    {
        $result = Stream::from([5, 2, 8, 0, 'a', 3, 1, 'b', 4])
            ->gatherUntil('is_string')
            ->map(Reducers::sum())
            ->first();
        
        self::assertSame(15, $result->get());
        self::assertSame([15], $result->toArray());
        self::assertSame([15], $result->toArrayAssoc());
    }
    
    public function test_gather_with_first(): void
    {
        $result = Stream::from([5, 2, 8, 0, 'a', 3, 1, 'b', 4])
            ->gatherUntil('is_string')
            ->first();
        
        self::assertSame([5, 2, 8, 0], $result->get());
        self::assertSame([5, 2, 8, 0], $result->toArray());
        self::assertSame([5, 2, 8, 0], $result->toArrayAssoc());
    }
    
    public function test_sort_limited_with_first(): void
    {
        $result = Stream::from([6, 3, 8, 7, 0, 9, 1, 2, 5, 4])
            ->sort()
            ->limit(5)
            ->first();
        
        self::assertSame(0, $result->get());
    }
    
    public function test_tail_with_last(): void
    {
        $result = Stream::from([6, 2, 4, 9, 7, 0, 1, 2])
            ->tail(5)
            ->last();
        
        self::assertSame(2, $result->get());
        self::assertSame(7, $result->key());
    }
    
    public function test_stacked_fork_and_last(): void
    {
        $result = Stream::from([1, 'a', 2, 'b', 3, 'c'])
            ->fork('is_string', Stream::empty()->reduce(Reducers::concat()))
            ->last();
        
        self::assertSame('abc', $result->get());
    }
    
    public function test_shuffle_with_last(): void
    {
        $data = [1, 2, 3, 4, 5, 6];
        
        $result = Stream::from($data)
            ->shuffle()
            ->last();
        
        self::assertContains($result->get(), $data);
    }
    
    public function test_sortLimited_with_last(): void
    {
        $result = Stream::from([6, 2, 9, 7, 1, 3, 4, 5])
            ->sort()
            ->limit(5)
            ->last();
        
        self::assertSame(5, $result->get());
    }
    
    public function test_sort_with_last(): void
    {
        $result = Stream::from([6, 2, 9, 7, 1, 3, 4, 5])
            ->sort()
            ->last();
        
        self::assertSame(9, $result->get());
    }
    
    public function test_sort_with_isEmpty(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->isEmpty()->get());
    }
    
    public function test_gather_with_isEmpty(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->isEmpty()->get());
    }
    
    public function test_reverse_with_isEmpty(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->isEmpty()->get());
    }
    
    public function test_sort_with_hasOnly(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasOnly([3, 2])->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasOnly([3, 2], Check::KEY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasOnly([3, 2], Check::ANY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasOnly([3, 2], Check::BOTH)->get());
    }
    
    public function test_gather_with_hasOnly(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasOnly([3, 2])->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->flat()->hasOnly([3, 2], Check::KEY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasOnly([3, 2], Check::ANY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasOnly([3, 2], Check::BOTH)->get());
    }
    
    public function test_reverse_with_hasOnly(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasOnly([3, 2])->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasOnly([3, 2], Check::KEY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasOnly([3, 2], Check::ANY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasOnly([3, 2], Check::BOTH)->get());
    }
    
    public function test_sort_with_hasEvery(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasEvery([3, 2, 4])->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasEvery([3, 2, 4], Check::KEY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasEvery([3, 2, 4], Check::ANY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->sort()->hasEvery([3, 2, 4], Check::BOTH)->get());
    }
    
    public function test_gather_with_hasEvery(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasEvery([3, 2, 4])->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasEvery([3, 2, 4], Check::KEY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasEvery([3, 2, 4], Check::ANY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->gather()->hasEvery([3, 2, 4], Check::BOTH)->get());
    }
    
    public function test_reverse_with_hasEvery(): void
    {
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasEvery([3, 2, 4])->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasEvery([3, 2, 4], Check::KEY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasEvery([3, 2, 4], Check::ANY)->get());
        self::assertFalse(Stream::from([6, 3, 7, 2])->reverse()->hasEvery([3, 2, 4], Check::BOTH)->get());
    }
    
    public function test_fork_with_discriminator_alternately(): void
    {
        $discriminator = Discriminators::alternately(['foo', 'bar']);
        
        $result = Stream::from([6, 2, 8, 1, 4, 9, 0, 2, 5])
            ->fork(
                clone $discriminator,
                Stream::empty()
                    ->fork(
                        clone $discriminator,
                        Stream::empty()->reduce(Reducers::sum())
                    )
                    ->first()
            )
            ->collect();
 
        self::assertSame([
            'foo' => 6 + 8 + 4 + 0 + 5,
            'bar' => 2 + 1 + 9 + 2,
        ], $result->get());
    }
    
    /**
     * @dataProvider getAllPossibleTypesOfSourceForStream
     */
    public function test_execute_stream_with_all_possible_kinds_of_source(string $mode): void
    {
        //Arrange
        $stmt = $this->getMockBuilder(\PDOStatement::class)->getMock();
        $stmt->expects(self::exactly(3))->method('fetch')->willReturnOnConsecutiveCalls(-1, -2, false);
        
        $elements = [
            Stream::from(['a', 1, 'b', 2])->onlyStrings(),
            [3, 4],
            Producers::tokenizer(' ', 'c d'),
            Stream::from([3, 2, 1, 5])->chunk(2)->map('array_sum')->collect(),
            new \ArrayIterator(['e', 'f']),
            5 => 10,
            6 => 'foo',
            static fn(): array => ['n', 'p'],
            $stmt,
            Producers::combinedFrom(['e', 'b'], [2, 8]),
            \fopen(__FILE__, 'rb'),
        ];
        
        $expected = ['a', 'b', 3, 4, 'c', 'd', 5, 6, 'e', 'f', 10, 'foo', 'n', 'p', -1, -2, 2, 8];
        
        $thisFileLines = \file(__FILE__);
        if (\is_array($thisFileLines)) {
            $expected[] = \trim($thisFileLines[0]);
        }
        
        //Act
        switch ($mode) {
            case 'of':
                $stream = Stream::of(...$elements);
            break;
            case 'from':
                $stream = Stream::from(Producers::from($elements));
            break;
            case 'join':
            default:
                //join() doesn't accept scalar values, so convert them into array
                unset($elements[6]);
                $elements[5] = [10, 'foo'];
                $stream = Stream::empty()->join(...$elements);
        }
        
        $result = $stream->reindex()
            ->limit(19)
            ->mapWhen('is_string', 'trim')
            ->toArray();
        
        //Assert
        self::assertSame($expected, $result);
    }
    
    public function getAllPossibleTypesOfSourceForStream(): array
    {
        return [
            'of' => ['of'],
            'from' => ['from'],
            'join' => ['join'],
        ];
    }
    
    public function test_filterWhen_with_omitWhen(): void
    {
        $result = Stream::from([-2, 'aa', 5, 'foo', 0, 'bar', 1, 'doze'])
            ->filterWhen('is_int', Filters::greaterThan(0))
            ->omitWhen('is_string', Filters::length()->ne(3))
            ->groupBy(static fn($v): string => \is_int($v) ? 'integers' : 'strings', true)
            ->toArray();
        
        self::assertSame([
            'integers' => [5, 1],
            'strings' => ['foo', 'bar'],
        ], $result);
    }
    
    public function test_filterWhen(): void
    {
        $result = Stream::from([-2, 'aa', 5, 'foo', 0, 'bar', 1, 'doze'])
            ->filterWhen('is_int', Filters::greaterThan(0))
            ->groupBy(static fn($v): string => \is_int($v) ? 'integers' : 'strings', true)
            ->toArray();
        
        self::assertSame([
            'strings' => ['aa', 'foo', 'bar', 'doze'],
            'integers' => [5, 1],
        ], $result);
    }
    
    public function test_omitWhen(): void
    {
        $result = Stream::from([-2, 'aa', 5, 'foo', 0, 'bar', 1, 'doze'])
            ->omitWhen('is_string', Filters::length()->ne(3))
            ->groupBy(static fn($v): string => \is_int($v) ? 'integers' : 'strings', true)
            ->toArray();
        
        self::assertSame([
            'integers' => [-2, 5, 0, 1],
            'strings' => ['foo', 'bar'],
        ], $result);
    }
    
    public function test_Tail_Reverse_GroupBy(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd', 4, 'e', 5, 'f', 6, 'g', 7])
            ->tail(6)
            ->reverse()
            ->groupBy(static fn($v): string => \is_string($v) ? 'str' : 'int');
            
        self::assertSame([
            'int' => [13 => 7, 11 => 6, 9 => 5],
            'str' => [12 => 'g', 10 => 'f', 8 => 'e'],
        ], $result->toArray());
    }
    
    public function test_Tail_Reverse_GroupBy_reindex(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd', 4, 'e', 5, 'f', 6, 'g', 7])
            ->tail(6)
            ->reverse()
            ->groupBy(static fn($v): string => \is_string($v) ? 'str' : 'int', true);
            
        self::assertSame([
            'int' => [7, 6, 5],
            'str' => ['g', 'f', 'e'],
        ], $result->toArray());
    }
    
    public function test_Tail_Reverse_reindex_GroupBy(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd', 4, 'e', 5, 'f', 6, 'g', 7])
            ->tail(6)
            ->reverse()
            ->reindex()
            ->groupBy(static fn($v): string => \is_string($v) ? 'str' : 'int');
            
        self::assertSame([
            'int' => [7, 2 => 6, 4 => 5],
            'str' => [1 => 'g', 3 => 'f', 5 => 'e'],
        ], $result->toArray());
    }
    
    public function test_Reverse_Tail_GroupBy(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3, 'd', 4, 'e', 5, 'f', 6, 'g', 7])
            ->reverse()
            ->tail(6)
            ->groupBy(static fn($v): string => \is_string($v) ? 'str' : 'int', true);
            
        self::assertSame([
            'int' => [3, 2, 1],
            'str' => ['c', 'b', 'a'],
        ], $result->toArray());
    }
    
    public function test_Shuffle_Tail_CollectIn(): void
    {
        Stream::from(['a', 'b', 'c', 'd'])
            ->shuffle()
            ->tail(2)
            ->collectIn($collector = Collectors::default())
            ->run();
        
        self::assertSame(2, $collector->count());
     
        self::assertTrue($collector->stream()->hasOnly(['a', 'b', 'c', 'd'])->get());
    }
    
    public function test_Gather_Tail_CollectIn(): void
    {
        Stream::from(['a', 'b', 'c', 'd'])
            ->gather()
            ->tail(2)
            ->collectIn($collector = Collectors::default())
            ->run();
        
        self::assertSame(1, $collector->count());
        self::assertSame([['a', 'b', 'c', 'd']], $collector->getData());
    }
    
    public function test_Reverse_Gather_reindex(): void
    {
        $result = Stream::from(['a' => 1, 2 => 'b', 'c' => 3])
            ->reverse()
            ->gather(true)
            ->toArrayAssoc();
        
        self::assertSame([
            [3, 'b', 1]
        ], $result);
    }
    
    public function test_Reverse_Gather_preserveKeys(): void
    {
        $result = Stream::from(['a' => 1, 2 => 'b', 'c' => 3])
            ->reverse()
            ->gather()
            ->toArrayAssoc();
        
        self::assertSame([
            ['c' => 3, 2 => 'b', 'a' => 1]
        ], $result);
    }
    
    public function test_Fork_Gather_reindex(): void
    {
        $result = Stream::from(['a' => 1, 2 => 'b', 'c' => 3])
            ->fork(Discriminators::alternately(['foo', 'bar']), Stream::empty()->collect())
            ->gather(true)
            ->toArrayAssoc();
        
        self::assertSame([
            [
                ['a' => 1, 'c' => 3],
                [2 => 'b'],
            ]
        ], $result);
    }
    
    public function test_Fork_Gather_preserveKeys(): void
    {
        $result = Stream::from(['a' => 1, 2 => 'b', 'c' => 3])
            ->fork(Discriminators::alternately(['foo', 'bar']), Stream::empty()->collect())
            ->gather()
            ->toArrayAssoc();
        
        self::assertSame([
            [
                'foo' => ['a' => 1, 'c' => 3],
                'bar' => [2 => 'b'],
            ]
        ], $result);
    }
    
    public function test_Sort_Gather_reindex(): void
    {
        $result = Stream::from(['a' => 1, 2 => 'b', 'c' => 3])
            ->sort()
            ->gather(true)
            ->toArrayAssoc();
        
        self::assertSame([
            ['b', 1, 3]
        ], $result);
    }
    
    public function test_Sort_Gather_preserveKeys(): void
    {
        $result = Stream::from(['a' => 1, 2 => 'b', 'c' => 3])
            ->sort()
            ->gather()
            ->toArrayAssoc();
        
        self::assertSame([
            [2 => 'b', 'a' => 1, 'c' => 3]
        ], $result);
    }
    
    public function test_Reverse_GroupBy_with_preserve_keys(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->reverse()
            ->groupBy('is_string');
        
        self::assertSame([
            0 => [5 => 3, 3 => 2, 1 => 1],
            1 => [4 => 'c', 2 => 'b', 0 => 'a'],
        ], $result->toArray());
    }
    
    public function test_Sort_GroupBy_preserveKeys(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->sort()
            ->groupBy('is_string');
        
        self::assertSame([
            1 => [0 => 'a', 2 => 'b', 4 => 'c'],
            0 => [1 => 1, 3 => 2, 5 => 3],
        ], $result->toArray());
    }
    
    public function test_Sort_GroupBy_reindex(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->sort()
            ->groupBy('is_string', true);
        
        self::assertSame([
            1 => ['a', 'b', 'c'],
            0 => [1, 2, 3],
        ], $result->toArray());
    }
    
    public function test_Fork_GroupBy_preserveKeys(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->fork('is_string', Stream::empty()->collect(true))
            ->groupBy(Discriminators::byKey(), false);
        
        self::assertSame([
            1 => [1 => ['a', 'b', 'c']],
            0 => [0 => [1, 2, 3]],
        ], $result->toArray());
    }
    
    public function test_Fork_GroupBy_reindex(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->fork('is_string', Stream::empty()->collect(true))
            ->groupBy(Discriminators::byKey(), true);
        
        self::assertSame([
            1 => [['a', 'b', 'c']],
            0 => [[1, 2, 3]],
        ], $result->toArray());
    }
    
    public function test_Gather_GroupBy_reindex(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->gather()
            ->groupBy(static fn($v, $k): bool => (bool) $k);
        
        self::assertSame([
            [
                ['a', 1, 'b', 2, 'c', 3]
            ]
        ], $result->toArray());
    }
    
    public function test_Reverse_Filter(): void
    {
        $result = Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->reverse()
            ->filter('is_string')
            ->toArray();
        
        self::assertSame(['c', 'b', 'a'], $result);
    }
    
    public function test_Reverse_Unique_Shuffle_Filter(): void
    {
        $result = Stream::from(['d', 2, 'a', 4, 'c', 1, 'b', 2, 'd', 1, 'a', 3, 'c', 4, 'b', 3])
            ->reverse()
            ->unique()
            ->shuffle()
            ->filter('is_string')
            ->toArray();
        
        self::assertCount(4, $result);
        
        self::assertTrue(Stream::from($result)->hasOnly(['a', 'b', 'c', 'd'])->get());
    }
    
    public function test_tail_sortLimited(): void
    {
        $result = Stream::from([8, 2, 3, 1, 4, 0, 2, 9, 3, 5])
            ->tail(5)
            ->best(3)
            ->toArray();
        
        self::assertSame([0, 2, 3], $result);
    }
    
    public function test_sort_sortLimited(): void
    {
        $result = Stream::from([8, 2, 3, 1, 4, 0, 2, 9, 3, 5])
            ->sort()
            ->rsort()
            ->limit(3)
            ->toArray();
        
        self::assertSame([9, 8, 5], $result);
    }
    
    public function test_stacked_sort_operations(): void
    {
        $data = [4 => 5, 8 => 2, 2 => 6, 3 => 5, 7 => 3, 5 => 2, 6 => 1, 1 => 3, 0 => 6];
        
        $result = Stream::from($data)
            ->sort()
            ->rsort(null, Check::KEY)
            ->toArrayAssoc();
        
        $expected = [
            8 => 2,
            7 => 3,
            6 => 1,
            5 => 2,
            4 => 5,
            3 => 5,
            2 => 6,
            1 => 3,
            0 => 6,
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_sort_by_value_and_key_in_the_same_time(): void
    {
        $data = [4 => 5, 8 => 2, 2 => 6, 3 => 5, 7 => 3, 5 => 2, 6 => 1, 1 => 3, 0 => 6];
        
        $result = Stream::from($data)
            ->sort(static fn($v1, $v2, $k2, $k1): int => $v1 <=> $v2 ?: $k1 <=> $k2, Check::BOTH)
            ->toArrayAssoc();
        
        $expected = [
            6 => 1,
            8 => 2,
            5 => 2,
            7 => 3,
            1 => 3,
            4 => 5,
            3 => 5,
            2 => 6,
            0 => 6,
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_skipWhile(): void
    {
        $result = Stream::from([2, 3, 2, 7, 3, 5, 4])
            ->skipWhile(Filters::lessThan(5))
            ->toArray();
        
        self::assertSame([7, 3, 5, 4], $result);
    }
    
    public function test_skipUntil(): void
    {
        $result = Stream::from([2, 3, 2, 7, 3, 5, 4])
            ->skipUntil(Filters::greaterThan(5))
            ->toArray();
        
        self::assertSame([7, 3, 5, 4], $result);
    }
    
    public function test_sort_with_Reverse_comparator(): void
    {
        $result = Stream::from([2, 3, 2, 7, 3, 5, 4])
            ->sort(Comparators::reverse())
            ->toArray();
        
        self::assertSame([7, 5, 4, 3, 3, 2, 2], $result);
    }
    
    public function test_sort_with_Reverse_comparator_values_and_keys(): void
    {
        $data = [
            2 => 'a',
            3 => 'c',
            1 => 'b',
            7 => 'a',
            4 => 'b',
            5 => 'c',
            6 => 'a',
            8 => 'b',
            9 => 'c',
        ];
        
        $result = Stream::from($data)
            ->sort(Comparators::reverse(), Check::BOTH)
            ->toArrayAssoc();
        
        $expected = [
            9 => 'c',
            5 => 'c',
            3 => 'c',
            8 => 'b',
            4 => 'b',
            1 => 'b',
            7 => 'a',
            6 => 'a',
            2 => 'a',
        ];
        
        self::assertSame($expected, $result);
    }
    
    /**
     * @dataProvider getDataForTestFindUptrends
     */
    public function test_find_uptrends(array $dataset, array $expected): void
    {
        $result = Stream::from($dataset)
            ->accumulateUptrends(null, true)
            ->toArray();
        
        self::assertSame($expected, $result);
    }
    
    public function getDataForTestFindUptrends(): array
    {
        //dataset, expected
        return [
            [
                [4, 3, 2, 4, 5, 7, 6, 7, 8, 6, 5, 3, 3, 5, 2, 4, 1, 3, 5, 8, 9],
                [[2, 4, 5, 7], [6, 7, 8], [3, 5], [2, 4], [1, 3, 5, 8, 9]],
            ], [
                [],
                [],
            ], [
                [1, 0, 0, 0, 0, -1],
                [],
            ], [
                [1, 2, 3],
                [[1, 2, 3]],
            ],
        ];
    }
    
    /**
     * @dataProvider getDataForTestFindDowntrends
     */
    public function test_find_downtrends(array $dataset, array $expected): void
    {
        $result = Stream::from($dataset)
            ->accumulateDowntrends(null, true)
            ->toArray();
        
        self::assertSame($expected, $result);
    }
    
    public function getDataForTestFindDowntrends(): array
    {
        //dataset, expected
        return [
            [
                [4, 3, 2, 4, 5, 7, 6, 7, 8, 6, 5, 3, 3, 5, 2, 4, 1, 3, 5, 8, 9],
                [[4, 3, 2], [7, 6], [8, 6, 5, 3], [5, 2], [4, 1]],
            ], [
                [1, 2, 3, 4, 5],
                [],
            ], [
                [1, 2, 3, 4, 5, 4],
                [[5, 4]],
            ], [
                [1, 2, 3, 4, 5, 4, 5],
                [[5, 4]],
            ],
        ];
    }
    
    /**
     * @dataProvider getDataForTestFindLocalMaxima
     */
    public function test_find_local_maxima(array $data, array $expected): void
    {
        self::assertSame($expected, Stream::from($data)->onlyMaxima()->toArrayAssoc());
    }
    
    public function getDataForTestFindLocalMaxima(): array
    {
        return [
            //data, expected
            [
                [4, 3, 2, 4, 5, 7, 6, 7, 8, 6, 5, 3, 3, 5, 2, 4, 1, 3, 5, 5, 3, 2, 4, 4, 5, 8, 9],
                [
                    0 => 4,
                    5 => 7,
                    8 => 8,
                    13 => 5,
                    15 => 4,
                    26 => 9,
                ]
            ], [
                [1, 2, 2, 3, 4, 5, 5, 6], [7 => 6]
            ], [
                [6, 5, 5, 4, 3, 2, 2, 1], [0 => 6]
            ], [
                [], []
            ],
        ];
    }
    
    public function test_find_local_minima_with_limits(): void
    {
        $result = Stream::from([
            0 => 2, 3, 4,
            3 => 3, 2, 4, 5,
            7 => 7, 6, 7, 8,
            11 => 6, 5, 3, 3,
            15 => 5, 2, 4, 1, 3,
            20 => 5, 5, 3, 2, 4,
            25 => 4, 5, 8, 9
            ])
            ->onlyMinima()
            ->toArrayAssoc();
        
        self::assertSame([
            0 => 2,
            4 => 2,
            8 => 6,
            16 => 2,
            18 => 1,
            23 => 2,
        ], $result);
    }
    
    public function test_find_local_minima_without_limits(): void
    {
        $result = Stream::from([
            0 => 2, 3, 4,
            3 => 3, 2, 4, 5,
            7 => 7, 6, 7, 8,
            11 => 6, 5, 3, 3,
            15 => 5, 2, 4, 1, 3,
            20 => 5, 5, 3, 2, 4,
            25 => 4, 5, 8, 9
            ])
            ->onlyMinima(null, false)
            ->toArrayAssoc();
        
        self::assertSame([
            4 => 2,
            8 => 6,
            16 => 2,
            18 => 1,
            23 => 2,
        ], $result);
    }
    
    /**
     * @dataProvider getDataForTestFindLocalExtremaIncludeLimits
     */
    public function test_find_local_extrema_include_limits(array $data, array $expected): void
    {
        $result = Stream::from($data)
            ->onlyExtrema()
            ->toArray();
        
        self::assertSame($expected, $result);
    }
    
    public function getDataForTestFindLocalExtremaIncludeLimits(): array
    {
        return [
            //data, expected
            [[3, 2, 4, 5, 3], [3, 2, 5, 3]],
            [[3, 4, 5, 3], [3, 5, 3]],
            [[1, 2, 2, 2, 3], [1, 3]],
            [[5, 4, 3, 3, 2], [5, 2]],
            [[4, 4, 3, 3, 3], []],
            [[3, 3, 3], []],
            [[3, 3, 3, 5], [5]],
            [[3, 3, 3, 5, 5], []],
            [[4, 3, 3, 5, 5], [4]],
            [[4, 3, 3, 5, 5, 2], [4, 2]],
            [[4, 3, 3, 5, 5, 6], [4, 6]],
            [[6, 4, 3, 2, 4, 5, 7], [6, 2, 7]],
            [[], []],
        ];
    }
    
    /**
     * @dataProvider getDataForTestFindLocalExtremaWithoutLimits
     */
    public function test_find_local_extrema_without_limits(array $data, array $expected): void
    {
        self::assertSame($expected, Stream::from($data)->onlyExtrema(null, false)->toArray());
    }
    
    public function getDataForTestFindLocalExtremaWithoutLimits(): array
    {
        return [
            [[3, 2, 4, 5, 3], [2, 5]],
            [[6, 4, 3, 2, 4, 5, 7], [2]],
            [[6, 4, 3, 2, 1], []],
            [[3, 2, 2, 1, 1, 0], []],
            [[], []],
        ];
    }
    
    public function test_MultiReduce(): void
    {
        $result = Stream::from([1,2,3,4])
            ->reduce([
                'min' => Reducers::min(),
                'max' => Reducers::max(),
                'sum' => Reducers::sum(),
                'cnt' => Reducers::count(),
                'avg' => Reducers::average(),
            ]);
        
        self::assertSame([
            'min' => 1,
            'max' => 4,
            'sum' => 10,
            'cnt' => 4,
            'avg' => 2.5,
        ], $result->get());
    }
    
    public function test_omit_repetitions_in_elements(): void
    {
        $data = [5, 2, 2, 1, 1, 3, 3, 3, 2, 2, 3, 3, 5, 5, 5];
        
        $result = Stream::from($data)->omitReps()->toArray();
        
        self::assertSame([5, 2, 1, 3, 2, 3, 5], $result);
    }
    
    public function test_MultiMapper_1(): void
    {
        $result = Stream::of('The brOwn quicK PythoN jumps OVER the lAzY Panther')
            ->flatMap(Mappers::split())
            ->map([
                'original' => Mappers::value(),
                'uppercase' => '\strtoupper',
                'number' => Mappers::key(),
            ])
            ->find(static fn(array $entry): bool => $entry['original'] === $entry['uppercase']);
        
        self::assertTrue($result->found());
        self::assertSame('{"original":"OVER","uppercase":"OVER","number":5}', $result->toJsonAssoc());
    }
    
    public function test_MultiMapper_2(): void
    {
        $result = Stream::from([5, 7, 2, 5, 1, 3, 4, 2, 8, 9, 1, 2])
            ->chunk(3, true)
            ->map([
                'chunk' => Mappers::value(),
                'avg' => Reducers::average(),
            ])
            ->find(Filters::filterBy('avg', 'is_int'));
        
        self::assertTrue($result->found());
        
        self::assertSame([
            'chunk' => [5, 1, 3],
            'avg' => 3,
        ], $result->get());
    }
    
    public function test_sort_by_value_and_key_with_callbacks(): void
    {
        $expected = [
            'e' => 4, 'h' => 4, 't' => 4, 'n' => 3, 'o' => 3, 'p' => 3, 'r' => 3, 'a' => 2, 'u' => 2, 'y' => 2
        ];
        
        $shuffledData = Stream::from($expected)->shuffle()->toArrayAssoc();
        
        $result = Stream::from($shuffledData)
            ->sort(Comparators::multi(
                static fn(int $v1, int $v2, string $k1, string $k2): int => $v2 <=> $v1,
                static fn(int $v1, int $v2, string $k1, string $k2): int => $k1 <=> $k2,
            ), Check::BOTH)
            ->toArrayAssoc();
        
        self::assertSame($expected, $result);
    }
    
    public function test_sort_by_valueDesc_keyAsc_by_dedicated_comparator(): void
    {
        $expected = [
            'e' => 4, 'h' => 4, 't' => 4, 'n' => 3, 'o' => 3, 'p' => 3, 'r' => 3, 'a' => 2, 'u' => 2, 'y' => 2
        ];
        
        $shuffledData = Stream::from($expected)->shuffle()->toArrayAssoc();
        
        $result = Stream::from($shuffledData)
            ->sort(Comparators::valueDescKeyAsc())
            ->toArrayAssoc();
        
        self::assertSame($expected, $result);
    }
    
    public function test_sort_by_valueAsc_keyDesc_by_dedicated_comparator(): void
    {
        $expected = [
            'y' => 2, 'u' => 2, 'a' => 2, 'r' => 3, 'p' => 3, 'o' => 3, 'n' => 3, 't' => 4, 'h' => 4, 'e' => 4
        ];
        
        $shuffledData = Stream::from($expected)->shuffle()->toArrayAssoc();
        
        $result = Stream::from($shuffledData)
            ->sort(Comparators::valueAscKeyDesc())
            ->toArrayAssoc();
        
        self::assertSame($expected, $result);
    }
    
    public function test_sort_by_valueAsc_keyAsc_by_default_comparator(): void
    {
        $expected = [
            'a' => 2, 'u' => 2, 'y' => 2, 'n' => 3, 'o' => 3, 'p' => 3, 'r' => 3, 'e' => 4, 'h' => 4, 't' => 4,
        ];
        
        $shuffledData = Stream::from($expected)->shuffle()->toArrayAssoc();
        
        $result = Stream::from($shuffledData)
            ->sort(null, Check::BOTH)
            ->toArrayAssoc();
        
        self::assertSame($expected, $result);
    }
    
    public function test_sort_by_valueDesc_keyDesc_by_default_comparator(): void
    {
        $expected = [
            't' => 4, 'h' => 4, 'e' => 4, 'r' => 3, 'p' => 3, 'o' => 3, 'n' => 3, 'y' => 2, 'u' => 2, 'a' => 2,
        ];
        
        $shuffledData = Stream::from($expected)->shuffle()->toArrayAssoc();
        
        $result = Stream::from($shuffledData)
            ->rsort(null, Check::BOTH)
            ->toArrayAssoc();
        
        self::assertSame($expected, $result);
    }
    
    public function test_rsort_with_valueAscKeyDesc_works_as_sort_with_valueDescKeyAsc(): void
    {
        $data = [
            'n' => 3, 'a' => 2, 'r' => 3, 'y' => 2, 'e' => 4, 'o' => 3, 't' => 4, 'p' => 3, 'h' => 4, 'u' => 2,
        ];
        
        $result1 = Stream::from($data)
            ->rsort(Comparators::valueAscKeyDesc())
            ->toArrayAssoc();
        
        $result2 = Stream::from($data)
            ->sort(Comparators::valueDescKeyAsc())
            ->toArrayAssoc();
        
        self::assertSame($result1, $result2);
    }
    
    public function test_best_with_valueAscKeyDesc_works_as_worst_with_valueDescKeyAsc(): void
    {
        $data = [
            'n' => 3, 'a' => 2, 'r' => 3, 'y' => 2, 'e' => 4, 'o' => 3, 't' => 4, 'p' => 3, 'h' => 4, 'u' => 2,
        ];
        
        $result1 = Stream::from($data)
            ->best(10, Comparators::valueAscKeyDesc())
            ->toArrayAssoc();
        
        $result2 = Stream::from($data)
            ->worst(10, Comparators::valueDescKeyAsc())
            ->toArrayAssoc();
        
        self::assertSame($result1, $result2);
    }
    
    public function test_findMax(): void
    {
        $data = [
            ['id' => 7, 'name' => 'Sue', 'age' => 17, 'sex' => 'female'],
            ['id' => 9, 'name' => 'Chris', 'age' => 26, 'sex' => 'male'],
            ['id' => 2, 'name' => 'Kate', 'age' => 35, 'sex' => 'female'],
            ['id' => 5, 'name' => 'Chris', 'age' => 20, 'sex' => 'male'],
            ['id' => 6, 'name' => 'Joanna', 'age' => 30, 'sex' => 'female'],
            ['id' => 10, 'name' => 'Tom', 'age' => 35, 'sex' => 'male'],
            ['id' => 1, 'name' => 'Sue', 'age' => 20, 'sex' => 'female'],
        ];
        
        $result = Stream::from($data)
            ->findMax(3, Filters::filterBy('sex', 'female'))
            ->toArray();
        
        $expected = [
            ['id' => 7, 'name' => 'Sue', 'age' => 17, 'sex' => 'female'],
            ['id' => 2, 'name' => 'Kate', 'age' => 35, 'sex' => 'female'],
            ['id' => 6, 'name' => 'Joanna', 'age' => 30, 'sex' => 'female'],
        ];
        
        self::assertSame($expected, $result);
    }
    
    public function test_filter_only_nonDecreasing_values(): void
    {
        $data = [3, 2, 3, 4, 4, 3, 1, 5, 6, 5, 4, 7, 8];
        
        $result = Stream::from($data)->increasingValues()->toArray();
        
        self::assertSame([3, 3, 4, 4, 5, 6, 7, 8], $result);
    }
    
    public function test_filter_only_nonIncreasing_values(): void
    {
        $data = [8, 9, 8, 6, 5, 7, 5, 3, 4, 1, 3];
        
        $result = Stream::from($data)->decreasingValues()->toArray();
        
        self::assertSame([8, 8, 6, 5, 5, 3, 1], $result);
    }
    
    public function test_dispatch_with_limit(): void
    {
        $data = ['a', 1, 'b', 2, 'c', 3, 'd', 4];
        
        $threeStrings = Stream::empty()->limit(3)->collect();
        $twoInts = Stream::empty()->limit(2)->collect();
        
        $stream = Stream::from($data)
            ->dispatch('is_string', [
                1 => $threeStrings,
                0 => $twoInts,
            ]);
        
        self::assertSame(8, $stream->count()->get());
        self::assertSame(['a', 'b', 'c'], $threeStrings->toArray());
        self::assertSame([1, 2], $twoInts->toArray());
    }
    
    public function test_dispatch_throws_excpetion_when_loop_is_detected(): void
    {
        //Assert
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Looped message sending is not supported in Dispatch operation');
        
        //Arrange
        $stream = Stream::from([5, 'a', 2, 'b', 4,])->onlyIntegers();
        $discriminator = '\is_string';
        $handlers = [Reducers::sum(), $stream];
        
        //Act
        $stream->dispatch($discriminator, $handlers);
    }
    
    public function test_classify(): void
    {
        $data = ['a', 1, 'b', 2, 'c', 3, 'd'];
        
        $result = Stream::from($data)
            ->classify(Discriminators::yesNo('is_string', 'strings', 'other'))
            ->flip()
            ->reduce(Reducers::countUnique());
        
        self::assertSame(['strings' => 4, 'other' => 3], $result->toArrayAssoc());
    }
    
    public function test_use_Discriminator_as_Mapper(): void
    {
        $data = [6, 2, 4, 3, 1, 2, 5];
        
        $result = Stream::from($data)
            ->map(Discriminators::evenOdd())
            ->reduce(Reducers::countUnique());
        
        self::assertSame(['even' => 4, 'odd' => 3], $result->toArrayAssoc());
    }
    
    
    public function test_Categorize(): void
    {
        $isAdult = static fn(array $row): string => $row['age'] >= 18 ? 'adults' : 'kids';
        
        $result = Stream::from($this->flatRowset())
            ->categorize($isAdult)
            ->fork(
                Discriminators::byKey(),
                Stream::empty()->flat(1)->extract('id')->reduce(Reducers::concat(','))
            )
            ->toArrayAssoc();
        
        self::assertSame([
            'adults' => '7,2,5',
            'kids' => '6,4,9',
        ], $result);
    }
    
    public function test_categorizeBy(): void
    {
        $isAdult = static fn(int $age): bool => $age >= 18;
        
        $women = [];
        $boys = [];
        
        Stream::from($this->flatRowset())
            ->categorizeBy('sex')
            ->dispatch(Discriminators::byKey(), [
                'female' => Stream::empty()
                    ->map(Mappers::arrayColumn('age', 'id'))
                    ->map(Filters::generic($isAdult))
                    ->map('\array_keys')
                    ->putIn($women),
                'male' => Stream::empty()
                    ->map(Mappers::arrayColumn('age', 'id'))
                    ->map(Filters::NOT($isAdult))
                    ->map('\array_keys')
                    ->putIn($boys)
            ])
            ->run();
        
        self::assertSame([7, 2], $women);
        self::assertSame([4, 9], $boys);
    }
    
    public function test_putIn_allows_to_put_current_value_in_some_variable(): void
    {
        $var = null;
        $result = [];
        
        Stream::from(['a', 'b', 'c'])
            ->putIn($var)
            ->forEach(static function () use (&$result, &$var): void {
                $result[] = $var;
            });
        
        self::assertSame(['a', 'b', 'c'], $result);
    }
    
    public function test_putIn_allows_to_put_current_key_in_some_variable(): void
    {
        $var = null;
        $result = [];
        
        Stream::from(['a', 'b', 'c'])
            ->putIn($var, Check::KEY)
            ->forEach(static function () use (&$result, &$var): void {
                $result[] = $var;
            });
        
        self::assertSame([0, 1, 2], $result);
    }
    
    public function test_putIn_throws_exception_when_param_mode_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only simple VALUE or KEY mode is supported');
        
        $var = null;
        Stream::empty()->putIn($var, Check::BOTH);
    }
    
    public function test_StoreIn_can_preserve_keys(): void
    {
        $letters = [];
        
        Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->onlyStrings()
            ->storeIn($letters)
            ->run();
        
        self::assertSame(['a', 2 => 'b', 4 => 'c'], $letters);
    }
    
    public function test_StoreIn_can_reindex_keys(): void
    {
        $letters = [];
        
        Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->onlyStrings()
            ->storeIn($letters, true)
            ->run();
        
        self::assertSame(['a', 'b', 'c'], $letters);
    }
    
    public function test_StoreIn_can_also_handle_all_ArrayAccess_instances(): void
    {
        $letters = new \ArrayObject();
        
        Stream::from(['a', 1, 'b', 2, 'c', 3])
            ->onlyStrings()
            ->storeIn($letters)
            ->run();
        
        self::assertSame(['a', 2 => 'b', 4 => 'c'], $letters->getArrayCopy());
    }
    
    public function test_Segregate(): void
    {
        $data = [1, 5, 2, 3, 2, 7, 4, 1, 6, 3, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];

        $result = Stream::from($data)->segregate(3)->toArray();

        self::assertSame([
            [0 => 1, 7 => 1, 13 => 1],
            [2 => 2, 4 => 2, 11 => 2, 15 => 2, 17 => 2, 20 => 2],
            [3 => 3, 9 => 3, 16 => 3, 19 => 3],
        ], $result);
    }
    
    public function test_Segregate_with_Limit(): void
    {
        $data = [1, 5, 2, 3, 2, 7, 4, 1, 6, 3, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)->segregate()->limit(3)->toArray();
        
        self::assertSame([
            [0 => 1, 7 => 1, 13 => 1],
            [2 => 2, 4 => 2, 11 => 2, 15 => 2, 17 => 2, 20 => 2],
            [3 => 3, 9 => 3, 16 => 3, 19 => 3],
        ], $result);
    }
    
    public function test_Segregate_with_Limit_zero(): void
    {
        $data = [1, 5, 2, 3, 2, 7, 4, 1, 6, 3, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)->segregate()->limit(0)->toArray();
        
        self::assertSame([], $result);
    }
    
    public function test_Segregate_with_First(): void
    {
        $data = [5, 2, 3, 2, 7, 4, 1, 1, 6, 3, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)->segregate(3)->first();
        
        self::assertSame(0, $result->key());
        self::assertSame([6 => 1, 7 => 1, 13 => 1], $result->get());
    }
    
    public function test_Segregate_with_Last(): void
    {
        $data = [1, 5, 2, 3, 2, 7, 4, 1, 6, 3, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)->segregate(3)->last();
        
        self::assertSame(2, $result->key());
        self::assertSame([3 => 3, 9 => 3, 16 => 3, 19 => 3], $result->get());
    }
    
    public function test_Segregate_with_Count(): void
    {
        $data = [5, 2, 3, 2, 7, 4, 1, 6, 3, 1, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)->segregate(3)->count();
        
        self::assertSame(3, $result->get());
    }
    
    public function test_Segregate_throws_exception_when_number_of_buckets_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid param buckets');
        
        Stream::empty()->segregate(0);
    }
    
    public function test_Segregate_with_no_elements_on_input(): void
    {
        self::assertEmpty(Stream::empty()->segregate()->toArray());
    }
    
    public function test_Reverse_Reindex_Segregate(): void
    {
        $data = [5, 2, 3, 2, 7, 4, 1, 6, 3, 1, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)->reverse()->reindex()->segregate(3)->toArray();
        
        self::assertSame([
            [10 => 1, 14 => 1, 17 => 1],
            [3 => 2, 6 => 2, 8 => 2, 12 => 2, 20 => 2, 22 => 2],
            [4 => 3, 7 => 3, 15 => 3, 21 => 3],
        ], $result);
    }
    
    public function test_Reverse_Segregate(): void
    {
        $data = [0 => 5, 1 => 2, 2 => 3, 3 => 2, 4 => 4, 5 => 1, 6 => 3];
        
        $result = Stream::from($data)->reverse()->segregate(3)->toArray();
        
        self::assertSame([
            [5 => 1],
            [3 => 2, 1 => 2],
            [6 => 3, 2 => 3],
        ], $result);
    }
    
    public function test_Categorize_with_Segregate(): void
    {
        $data = [5, 2, 3, 2, 7, 4, 1, 6, 3, 1];
        
        $result = Stream::from($data)
            ->categorize(Discriminators::evenOdd(), true)
            ->segregate()
            ->toArray();
        
        self::assertSame([
            ['even' => [2, 2, 4, 6]],
            ['odd' => [5, 3, 7, 1, 3, 1]],
        ], $result);
    }
    
    public function test_Sort_with_Segregate(): void
    {
        $data = [5, 2, 3, 2, 7, 4, 1, 6, 3, 1, 5, 2, 4, 1, 7, 2, 3, 2, 6, 3, 2, 5, 8, 4];
        
        $result = Stream::from($data)
            ->sort()
            ->segregate(3)
            ->toArray();
        
        self::assertSame([
            [6 => 1, 9 => 1, 13 => 1],
            [15 => 2, 3 => 2, 20 => 2, 17 => 2, 11 => 2, 1 => 2],
            [2 => 3, 19 => 3, 8 => 3, 16 => 3],
        ], $result);
    }
    
    public function test_Reverse_Reindex(): void
    {
        $result = Stream::from(['a', 'b', 'c', 'd'])->reverse()->reindex()->toArrayAssoc();
        
        self::assertSame(['d', 'c', 'b', 'a'], $result);
    }
    
    public function test_FilterBy_can_handle_ArrayAccess_implementations(): void
    {
        $rowset = [
            ['id' => 2, 'name' => 'Sue', 'age' => 22],
            ['id' => 9, 'name' => 'Chris', 'age' => 17],
            ['id' => 6, 'name' => 'Joanna', 'age' => 15],
            ['id' => 5, 'name' => 'Chris', 'age' => 24],
            ['id' => 7, 'name' => 'Sue', 'age' => 18],
        ];

        $result = Stream::from($rowset)
            ->map(static fn(array $row): \ArrayObject => new \ArrayObject($row))
            ->filterBy('name', 'Chris')
            ->map(static fn(\ArrayObject $row): array => $row->getArrayCopy())
            ->toArrayAssoc();
            
        self::assertSame([
            1 => ['id' => 9, 'name' => 'Chris', 'age' => 17],
            3 => ['id' => 5, 'name' => 'Chris', 'age' => 24],
        ], $result);
    }
    
    public function test_Reducer_can_be_used_as_Consumer(): void
    {
        $reducer = Reducers::sum();
        
        Stream::from([1, 'a', 2, 'b', 3, 'd', 4])->callWhen('is_int', $reducer)->run();
        
        self::assertSame(10, $reducer->result());
    }
    
    public function test_gatherWhile_with_stacked_producers(): void
    {
        $stream = Stream::from(['a b', 'c d'])
            ->tokenize()
            ->call($counter = Consumers::counter())
            ->gatherWhile(fn(): bool => $counter->count() <= 3, true);
        
        $result = $stream->toArray();
        
        self::assertSame([['a', 'b', 'c']], $result);
        self::assertSame(4, $counter->count());
    }
    
    public function test_gatherUntil_with_stacked_producers(): void
    {
        $stream = Stream::from(['a b', 'c d'])
            ->tokenize()
            ->call($counter = Consumers::counter())
            ->gatherUntil(fn(): bool => $counter->count() > 3, true);
        
        $result = $stream->toArray();
        
        self::assertSame([['a', 'b', 'c']], $result);
        self::assertSame(4, $counter->count());
    }
    
    public function test_gather_with_stacked_producers_and_limit(): void
    {
        $stream = Stream::from(['a b', 'c d'])
            ->tokenize()
            ->call($counter = Consumers::counter())
            ->limit(3)
            ->gather(true);
        
        $result = $stream->toArray();
        
        self::assertSame([['a', 'b', 'c']], $result);
        self::assertSame(3, $counter->count());
    }
    
    public function test_UnpackTuple(): void
    {
        $result = Stream::from([3 => 'a', 2 => 'b', 1 => 'c'])
            ->makeTuple()
            ->map(static fn(array $t): array => [$t[0] + 1, \strtoupper($t[1])])
            ->unpackTuple()
            ->toArrayAssoc();
        
        self::assertSame([4 => 'A', 3 => 'B', 2 => 'C'], $result);
    }
    
    public function test_Zip_without_sources(): void
    {
        $result = Stream::from([3 => 'a', 2 => 'b', 1 => 'c'])->zip()->toArrayAssoc();
        
        self::assertSame([
            3 => ['a'],
            2 => ['b'],
            1 => ['c'],
        ], $result);
    }
    
    /**
     * @dataProvider getDataForTestZipWithOneSource
     */
    public function test_Zip_with_one_source($source): void
    {
        $result = Stream::from([3 => 'a', 2 => 'b', 1 => 'c'])
            ->zip($source)
            ->toArrayAssoc();
        
        self::assertSame([
            3 => ['a', 2],
            2 => ['b', 4],
            1 => ['c', 6],
        ], $result);
    }
    
    public function getDataForTestZipWithOneSource(): array
    {
        return [
            'array' => [[2, 4, 6]],
            'producer' => [Producers::sequentialInt(2, 2)],
            'callable' => [static fn(): array => ['z' => 2, 4, 3 => 6]],
            'stream' => [Stream::from(['2', '4', '6'])->castToInt()],
        ];
    }
    
    public function test_CountIn(): void
    {
        $count = 0;
        
        $result = Stream::from(['a', 'b', 1, 'c', 2, 4])
            ->onlyStrings()
            ->countIn($count)
            ->toString('');
        
        self::assertSame('abc', $result);
        self::assertSame(3, $count);
    }
}