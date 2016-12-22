<?php

namespace MakinaCorpus\Drupal\Sf\Config;

use Drupal\Core\Config\StorageException;
use Drupal\Core\Config\StorageInterface;

class FallbackStorage implements StorageInterface
{
    private $collection;
    private $fast;
    private $slow;

    /**
     * Default constructor
     *
     * @param StorageInterface $fast
     * @param StorageInterface $slow
     */
    public function __construct(StorageInterface $fast, StorageInterface $slow, $collection = StorageInterface::DEFAULT_COLLECTION)
    {
        if ($fast->getCollectionName() !== $fast->getCollectionName() || $fast->getCollectionName() !== $collection) {
            throw new StorageException("Collection mismatch");
        }

        $this->fast = $fast;
        $this->slow = $slow;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        if (!$this->fast->exists($name)) {
            return $this->slow->exists($name);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        $data = null;

        try {
            $data = $this->fast->read($name);

            if (isset($data['_empty'])) {
                return false;
            }

            if (!is_array($data)) {
                throw new StorageException();
            }

        } catch (StorageException $e) {
            $data = $this->slow->read($name);

            if (!is_array($data)) {
                $this->fast->write($name, ['_empty']);

                return false;
            }

            $this->fast->write($name, $data);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function readMultiple(array $names)
    {
        $ret = [];

        foreach ($names as $name) {
            $data = $this->read($name);
            if (is_array($data)) {
                $ret[$name] = $data;
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function write($name, array $data)
    {
        // Write the slow one first, since it's the consistent one, this ensures
        // that on fast backend write fail, it does not looses the data
        $this->slow->write($name, $data);
        $this->fast->write($name, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $this->slow->delete($name);
        $this->fast->delete($name);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name)
    {
        $this->slow->rename($name, $new_name);
        $this->fast->rename($name, $new_name);
    }

    /**
     * {@inheritdoc}
     */
    public function encode($data)
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function decode($raw)
    {
        return $raw;
    }

    /**
     * {@inheritdoc}
     */
    public function listAll($prefix = '')
    {
        return $this->slow->listAll();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll($prefix = '')
    {
        $this->slow->deleteAll($prefix);
        $this->fast->deleteAll($prefix);
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collection)
    {
        return new self(
            $this->fast->createCollection($collection),
            $this->slow->createCollection($collection),
            $collection
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAllCollectionNames()
    {
        $this->slow->getAllCollectionNames();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionName()
    {
        return $this->collection;
    }
}
