<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Migration\Step\TierPrice;

use Migration\Resource;
use Migration\Logger\Logger;
use Migration\App\ProgressBar;
use Migration\Reader\MapInterface;

/**
 * Class Integrity
 */
class Integrity extends \Migration\App\Step\AbstractIntegrity
{
    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var Resource\Source
     */
    protected $source;

    /**
     * @var Resource\Destination
     */
    protected $destination;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ProgressBar\LogLevelProcessor
     */
    protected $progress;

    /**
     * @param Helper $helper
     * @param Logger $logger
     * @param ProgressBar\LogLevelProcessor $progress
     * @param Resource\Source $source
     * @param Resource\Destination $destination
     */
    public function __construct(
        Helper $helper,
        Logger $logger,
        ProgressBar\LogLevelProcessor $progress,
        Resource\Source $source,
        Resource\Destination $destination
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
        $this->progress = $progress;
        $this->source = $source;
        $this->destination = $destination;
    }

    /**
     * {@inheritdoc}
     */
    public function perform()
    {
        $this->progress->start($this->getIterationsCount());
        $this->check($this->helper->getSourceDocumentFields(), MapInterface::TYPE_SOURCE);
        $this->check($this->helper->getDestinationDocumentFields(), MapInterface::TYPE_DEST);
        $this->progress->finish();
        return $this->checkForErrors();
    }

    /**
     * Get iterations count for step
     *
     * @return int
     */
    protected function getIterationsCount()
    {
        return count($this->helper->getSourceDocumentFields()) + count($this->helper->getDestinationDocumentFields());
    }

    /**
     * @param array $tableFields
     * @param string $sourceType
     * @return void
     */
    protected function check($tableFields, $sourceType)
    {

        foreach ($tableFields as $documentName => $fieldsData) {

            $source     = $this->getResource($sourceType);
            $document   = $source->getDocument($documentName);
            $structure  = array_keys($document->getStructure()->getFields());

            $structureDiff = array_diff($fieldsData, $structure);
            if (!empty($structureDiff)) {
                $message = sprintf(
                    '%s table does not contain field(s): %s',
                    $documentName,
                    '"' . implode('", "', $structureDiff) . '"'
                );
                $this->logger->error($message);
            }
            $this->progress->advance();
        }
    }

    /**
     * @param string $sourceType
     * @return Resource\Destination|Resource\Source
     */
    protected function getResource($sourceType)
    {
        $map = [
            MapInterface::TYPE_SOURCE   => $this->source,
            MapInterface::TYPE_DEST     => $this->destination,
        ];
        return $map[$sourceType];
    }
}