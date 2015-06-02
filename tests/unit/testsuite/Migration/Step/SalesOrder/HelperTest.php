<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Migration\Step\SalesOrder;

use Migration\Reader;
use Migration\Resource\Source;

/**
 * Class Helper
 */
class HelperTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Source|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $source;

    /**
     * @var Helper
     */
    protected $helper;

    public function setUp()
    {
        $this->source = $this->getMockBuilder('\Migration\Resource\Source')
            ->setMethods(['getAdapter', 'addDocumentPrefix'])
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new Helper($this->source);
    }

    public function testGetSourceAttributes()
    {
        $entity = [
            0 => [
                'entity_id' => 1,
                'value' => 'entity_value'
            ]
        ];
        $mySqlAdapter = $this->getMock(
            '\Migration\Resource\Adapter\Mysql',
            ['getSelect', 'loadDataFromSelect'],
            [],
            '',
            false
        );
        $dbSelect = $this->getMock('\Magento\Framework\DB\Select', ['from', 'where'], [], '', false);
        $mySqlAdapter->expects($this->any())->method('getSelect')->willReturn($dbSelect);
        $this->source->expects($this->any())->method('getAdapter')->willReturn($mySqlAdapter);
        $this->source->expects($this->any())->method('addDocumentPrefix')->willReturnArgument(0);
        $dbSelect->expects($this->any())->method('from')->willReturnSelf();
        $dbSelect->expects($this->any())->method('where')->willReturnSelf();
        $mySqlAdapter->expects($this->once())->method('loadDataFromSelect')->willReturn($entity);
        $this->assertEquals($entity, $this->helper->getSourceAttributes('eav_attribute'));
    }

    public function testGetEavAttributes()
    {
        $eavAttributes = ['reward_points_balance_refunded', 'reward_salesrule_points'];
        $this->assertEquals($eavAttributes, $this->helper->getEavAttributes());
    }

    public function testGetDocumentList()
    {
        $documentList = ['sales_flat_order' => 'sales_order'];
        $this->assertEquals($documentList, $this->helper->getDocumentList());
    }

    public function testGetDestEavDocument()
    {
        $destEavDocument = 'eav_entity_int';
        $this->assertEquals($destEavDocument, $this->helper->getDestEavDocument());
    }
}