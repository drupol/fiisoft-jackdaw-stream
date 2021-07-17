<?php declare(strict_types=1);

namespace FiiSoft\Test\Jackdaw;

use FiiSoft\Jackdaw\Internal\StreamCollection;
use PHPUnit\Framework\TestCase;

final class StreamCollectionTest extends TestCase
{
    /** @var StreamCollection */
    private $collection;
    
    /** @var array */
    private $initialData = [
        'numbers' => [6, 3, 7, 9],
        'words' => ['the', 'quick', 'brown', 'fox'],
    ];
    
    protected function setUp()
    {
        $this->collection = new StreamCollection($this->initialData);
    }
    
    public function test_get_existing_group()
    {
        self::assertSame('[6,3,7,9]', $this->collection->get('numbers')->toJson());
    }
    
    public function test_get_nonexisting_group()
    {
        self::assertSame('[]', $this->collection->get('unknown_group')->toJson());
    }
    
    public function test_get_returns_the_same_stream_each_time_when_is_called()
    {
        $first = $this->collection->get('numbers');
        $second = $this->collection->get('numbers');
        
        self::assertSame($second, $first);
    }
    
    public function test_stream_returned_from_collection_should_be_reusable()
    {
        $numbers = $this->collection->get('numbers');
        
        self::assertSame('[6,3,7,9]', $numbers->toJson());
        self::assertSame('[6,3,7,9]', $numbers->toJson());
    }
    
    public function test_get_all_as_array()
    {
        self::assertSame($this->initialData, $this->collection->toArray());
    }
    
    public function test_make_json_from_all()
    {
        self::assertSame(\json_encode($this->initialData), $this->collection->toJson());
    }
    
    public function test_it_throws_exception_on_invalid_argument_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $this->collection->get(15.55);
    }
    
    public function test_collection_is_iterable()
    {
        for ($i = 0; $i < 2; ++$i) {
            foreach ($this->collection as $key => $stream) {
                if ($key === 'numbers' || $key === 'words') {
                    self::assertSame($this->initialData[$key], $stream->toArray());
                } else {
                    self::fail('Unknown key: '.$key);
                }
            }
        }
    }
}