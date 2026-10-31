<?php declare(strict_types=1);

namespace PixelPerfect\SalesEmail\Test\Unit\Model\Config\Source;

use Magento\Cms\Model\ResourceModel\Block\Collection;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory;
use Magento\Framework\DataObject;
use PHPUnit\Framework\TestCase;
use PixelPerfect\SalesEmail\Model\Config\Source\CmsBlock;

class CmsBlockTest extends TestCase
{
    public function testOnlyActiveBlocksAndDeduplicatedByIdentifier(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())->method('addFieldToFilter')->with('is_active', 1)->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['identifier' => 'a', 'title' => 'A']),
            new DataObject(['identifier' => 'a', 'title' => 'A']),
            new DataObject(['identifier' => 'b', 'title' => 'B']),
        ]));
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $values = array_column((new CmsBlock($factory))->toOptionArray(), 'value');

        $this->assertSame(['', 'a', 'b'], $values);
    }
}
