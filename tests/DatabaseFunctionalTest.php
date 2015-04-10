<?php

namespace MongoDB\Tests;

use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Model\CollectionInfo;
use InvalidArgumentException;

/**
 * Functional tests for the Database class.
 */
class DatabaseFunctionalTest extends FunctionalTestCase
{
    private $database;

    public function setUp()
    {
        parent::setUp();

        $this->database = new Database($this->manager, $this->getDatabaseName());
        $this->database->drop();
    }

    public function testCreateCollection()
    {
        $that = $this;
        $basicCollectionName = $this->getCollectionName() . '.basic';

        $commandResult = $this->database->createCollection($basicCollectionName);
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionExists($basicCollectionName, function(CollectionInfo $info) use ($that) {
            $that->assertFalse($info->isCapped());
        });

        $cappedCollectionName = $this->getCollectionName() . '.capped';
        $cappedCollectionOptions = array(
            'capped' => true,
            'max' => 100,
            'size' => 1048576,
        );

        $commandResult = $this->database->createCollection($cappedCollectionName, $cappedCollectionOptions);
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionExists($cappedCollectionName, function(CollectionInfo $info) use ($that) {
            $that->assertTrue($info->isCapped());
            $that->assertEquals(100, $info->getCappedMax());
            $that->assertEquals(1048576, $info->getCappedSize());
        });
    }

    public function testDrop()
    {
        $writeResult = $this->manager->executeInsert($this->getNamespace(), array('x' => 1));
        $this->assertEquals(1, $writeResult->getInsertedCount());

        $commandResult = $this->database->drop();
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionCount($this->getNamespace(), 0);
    }

    public function testDropCollection()
    {
        $writeResult = $this->manager->executeInsert($this->getNamespace(), array('x' => 1));
        $this->assertEquals(1, $writeResult->getInsertedCount());

        $commandResult = $this->database->dropCollection($this->getCollectionName());
        $this->assertCommandSucceeded($commandResult);
        $this->assertCollectionCount($this->getNamespace(), 0);
    }

    public function testListCollections()
    {
        $commandResult = $this->database->createCollection($this->getCollectionName());
        $this->assertCommandSucceeded($commandResult);

        $collections = $this->database->listCollections();
        $this->assertInstanceOf('MongoDB\Model\CollectionInfoIterator', $collections);

        foreach ($collections as $collection) {
            $this->assertInstanceOf('MongoDB\Model\CollectionInfo', $collection);
        }
    }

    public function testListCollectionsWithFilter()
    {
        $commandResult = $this->database->createCollection($this->getCollectionName());
        $this->assertCommandSucceeded($commandResult);

        $collectionName = $this->getCollectionName();
        $options = array('filter' => array('name' => $collectionName));

        $collections = $this->database->listCollections($options);
        $this->assertInstanceOf('MongoDB\Model\CollectionInfoIterator', $collections);
        $this->assertCount(1, $collections);

        foreach ($collections as $collection) {
            $this->assertInstanceOf('MongoDB\Model\CollectionInfo', $collection);
            $this->assertEquals($collectionName, $collection->getName());
        }
    }

    /**
     * Asserts that a collection with the given name exists in the database.
     *
     * An optional $callback may be provided, which should take a CollectionInfo
     * argument as its first and only parameter. If a CollectionInfo matching
     * the given name is found, it will be passed to the callback, which may
     * perform additional assertions.
     *
     * @param callable $callback
     */
    private function assertCollectionExists($collectionName, $callback = null)
    {
        if ($callback !== null && ! is_callable($callback)) {
            throw new InvalidArgumentException('$callback is not a callable');
        }

        $collections = $this->database->listCollections();

        $foundCollection = null;

        foreach ($collections as $collection) {
            if ($collection->getName() === $collectionName) {
                $foundCollection = $collection;
                break;
            }
        }

        $this->assertNotNull($foundCollection, sprintf('Found %s collection in the database', $collectionName));

        if ($callback !== null) {
            call_user_func($callback, $foundCollection);
        }
    }
}
