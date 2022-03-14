<?php declare(strict_types=1);

namespace FiiSoft\Jackdaw\Consumer;

use FiiSoft\Jackdaw\Internal\Check;

final class ResourceWriter implements Consumer
{
    /** @var resource */
    private $resource;
    
    private int $mode;
    
    /**
     * @param resource $resource
     * @param int $mode
     */
    public function __construct($resource, int $mode = Check::VALUE)
    {
        if (\is_resource($resource)) {
            $this->resource = $resource;
        } else {
            throw new \InvalidArgumentException('Invalid param resource');
        }
        
        $this->mode = $mode;
    }
    
    /**
     * @param mixed $value anything that can be casted to string
     * @param mixed $key anything that can be casted to string
     * @return void
     */
    public function consume($value, $key): void
    {
        switch ($this->mode) {
            case Check::VALUE:
                \fwrite($this->resource, (string) $value);
            break;
            
            case Check::KEY:
                \fwrite($this->resource, (string) $key);
            break;
            
            default:
                \fwrite($this->resource, $key.':'.$value);
        }
    }
}