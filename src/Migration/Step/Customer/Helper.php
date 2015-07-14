<?php
/**
 * Copyright � 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Migration\Step\Customer;

use Migration\Resource\Adapter\Mysql;
use Migration\Resource;
use Migration\Reader\GroupsFactory;
use Migration\Resource\Record;

class Helper
{
    /**
     * @var array
     */
    protected $documentAttributeTypes;

    /**
     * @var Resource\Source
     */
    protected $source;

    /**
     * @var \Migration\Reader\Groups
     */
    protected $readerGroups;

    /**
     * @var \Migration\Reader\Groups
     */
    protected $readerAttributes;

    /**
     * @var []
     */
    protected $eavAttributes;

    /**
     * @var []
     */
    protected $skipAttributes;

    /**
     * @var []
     */
    protected $sourceDocuments;

    /**
     * Helper constructor.
     */
    public function __construct(
        Resource\Source $source,
        GroupsFactory $groupsFactory
    ) {
        $this->source = $source;
        $this->readerAttributes = $groupsFactory->create('customer_attribute_groups_file');
        $this->readerGroups = $groupsFactory->create('customer_document_groups_file');
        $this->sourceDocuments = $this->readerGroups->getGroup('source_documents');
    }

    /**
     * @param string $document
     * @return string|null
     * @throws \Migration\Exception
     */
    public function getAttributeType($document)
    {
        if (empty($this->documentAttributeTypes)) {
            $entities = array_keys($this->readerGroups->getGroup('eav_entities'));
            foreach ($entities as $entity) {
                $documents = $this->readerGroups->getGroup($entity);
                foreach ($documents as $item => $key) {
                    $this->documentAttributeTypes[$item] = $entity;
                    $this->initEavEntity($entity, $item, $key);
                }
            }
        }
        return isset($this->documentAttributeTypes[$document]) ? $this->documentAttributeTypes[$document] : null;
    }

    /**
     * @param string $attributeType
     * @param string $sourceDocName
     * @param [] $recordData
     * @return bool
     */
    public function isSkipRecord($attributeType, $sourceDocName, $recordData)
    {
        if (!isset($this->sourceDocuments[$sourceDocName])
            || $this->sourceDocuments[$sourceDocName] != 'value_id'
            || !isset($recordData['attribute_id'])
        ) {
            return false;
        }
        return isset($this->skipAttributes[$attributeType][$recordData['attribute_id']]);
    }

    /**
     * @param string $attributeType
     * @param string $sourceDocName
     * @param Record\Collection $destinationRecords
     */
    public function updateAttributeData($attributeType, $sourceDocName, $destinationRecords)
    {
        if (!isset($this->sourceDocuments[$sourceDocName]) || $this->sourceDocuments[$sourceDocName] != 'entity_id') {
            return;
        }
        $records = [];
        /** @var Record $record */
        foreach ($destinationRecords as $record) {
            $records[] = $record->getValue('entity_id');
        }

        /** @var Mysql $adapter */
        $adapter = $this->source->getAdapter();
        foreach (array_keys($this->readerAttributes->getGroup($sourceDocName)) as $attribute) {
            $eavTableSuffix = '_' . $this->eavAttributes[$attributeType][$attribute]['backend_type'];
            $query = $adapter->getSelect()
                ->from(
                    [
                        'et' => $this->source->addDocumentPrefix($sourceDocName . $eavTableSuffix)
                    ],
                    [
                        'entity_id',
                        'value'
                    ]
                )
                ->where('et.entity_id IN (?)', $records)
                ->where('et.attribute_id = ?', $this->eavAttributes[$attributeType][$attribute]['attribute_id']);
            $attributeData = $query->getAdapter()->fetchAll($query);
            $attributeDataById = [];
            foreach ($attributeData as $entityData) {
                $attributeDataById[$entityData['entity_id']] = $entityData;
            }
            /** @var Record $record */
            foreach ($destinationRecords as $record) {
                $entityId = $record->getValue('entity_id');
                $value = isset($attributeDataById[$entityId]['value']) ? $attributeDataById[$entityId]['value'] : null;
                $record->setValue($attribute, $value);
            }
        }
    }

    /**
     * @param string $document
     * @return int
     */
    public function getSourceRecordsCount($document)
    {
        if ($this->sourceDocuments[$document] == 'entity_id') {
            return $this->source->getRecordsCount($document);
        }
        $attributeType = $this->getAttributeType($document);

        /** @var Mysql $adapter */
        $adapter = $this->source->getAdapter();
        $query = $adapter->getSelect()
            ->from(
                [
                    'et' => $this->source->addDocumentPrefix($document)
                ],
                'COUNT(*)'
            )
            ->where('et.attribute_id NOT IN (?)', array_keys($this->skipAttributes[$attributeType]));
        $count = $query->getAdapter()->fetchOne($query);

        return $count;
    }

    /**
     * @param string $attributeType
     * @param string $document
     * @param string $key
     * @throws \Migration\Exception
     */
    protected function initEavEntity($attributeType, $document, $key)
    {
        if ($key != 'entity_id') {
            return;
        }
        $this->initEavAttributes($attributeType);
        foreach (array_keys($this->readerAttributes->getGroup($document)) as $attribute) {
            if (!isset($this->eavAttributes[$attributeType][$attribute]['attribute_id'])) {
                if (isset($this->eavAttributes[$attributeType])) {
                    $message = sprintf('Attribute %s does not exist in the type %s', $attribute, $attributeType);
                } else {
                    $message = sprintf('Attribute type %s does not exist', $attributeType);
                }
                throw new \Migration\Exception($message);
            }
            $attributeId = $this->eavAttributes[$attributeType][$attribute]['attribute_id'];
            $this->skipAttributes[$attributeType][$attributeId] = true;
        }
    }

    /**
     * @param string $attributeType
     */
    protected function initEavAttributes($attributeType)
    {
        if (isset( $this->eavAttributes[$attributeType])) {
            return;
        }

        /** @var Mysql $adapter */
        $adapter = $this->source->getAdapter();
        $query = $adapter->getSelect()
            ->from(
                ['et' => $this->source->addDocumentPrefix('eav_entity_type')],
                []
            )->join(
                ['ea' => $this->source->addDocumentPrefix('eav_attribute')],
                'et.entity_type_id = ea.entity_type_id',
                [
                    'attribute_id',
                    'backend_type',
                    'attribute_code',
                    'entity_type_id'
                ]
            )->where(
                'et.entity_type_code = ?',
                $attributeType
            );
        $attributes = $query->getAdapter()->fetchAll($query);

        foreach ($attributes as $attribute) {
            $this->eavAttributes[$attributeType][$attribute['attribute_code']] = $attribute;
            $this->eavAttributes[$attributeType][$attribute['attribute_id']] = $attribute;
        }
    }
}