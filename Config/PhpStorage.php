<?php

namespace MakinaCorpus\Drupal\Sf\Config;

use Drupal\Component\Utility\OpCodeCache;
use Drupal\Core\Config\StorageInterface;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class PhpStorage implements StorageInterface
{
    private $storageDir;
    private $collection;

    /**
     * Default constructor
     */
    public function __construct($storageDir, $collection = StorageInterface::DEFAULT_COLLECTION)
    {
        $this->collection = $collection;
        $this->storageDir = $storageDir;
    }

    /**
     * Ensures that target directory exists and is writable
     */
    private function ensureTargetDirectory($directory)
    {
        $filesystem = new Filesystem();
        $directory = rtrim($directory);

        if (!$filesystem->exists($directory)) {
            $filesystem->mkdir($directory, 0750);
        } else {
            if (!is_dir($directory)) {
                throw new IOException(sprintf('File is not a directory "%s"', $directory), 0, null, $directory);
            }
            if (!is_writable($directory)) {
                throw new IOException(sprintf('Directory is not writable "%s"', $directory), 0, null, $directory);
            }
        }
    }

    /**
     * Storage directory property does not include the collection name
     *
     * @return string
     */
    private function getStorageDirectory()
    {
        return rtrim($this->storageDir, '/') . '/' . ($this->collection ? $this->collection : 'default');
    }

    /**
     * Get target file
     *
     * @param string $name
     *
     * @return string
     */
    private function getTargetFile($name)
    {
        return $this->getStorageDirectory() . '/' . $name . '.php';
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        return file_exists($this->getTargetFile($name));
    }

    /**
     * {@inheritdoc}
     */
    public function read($name)
    {
        $data = @include $this->getTargetFile($name);

        if (!is_array($data)) {
            return false;
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
            $ret[$name] = $this->read($name);
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function write($name, array $data)
    {
        $this->ensureTargetDirectory($this->storageDir);
        $this->ensureTargetDirectory($this->getStorageDirectory());

        $target = $this->getTargetFile($name);

        $filesystem = new FileSystem();
        $filesystem->dumpFile($target, '<?php return ' . var_export($data, true) . ';');

        OpCodeCache::invalidate($pathname);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($name)
    {
        $target = $this->getTargetFile($name);

        $filesystem = new FileSystem();
        $filesystem->remove($target);

        OpCodeCache::invalidate($pathname);
    }

    /**
     * {@inheritdoc}
     */
    public function rename($name, $new_name)
    {
        $this->ensureTargetDirectory($this->storageDir);
        $this->ensureTargetDirectory($this->getStorageDirectory());

        $source = $this->getTargetFile($name);
        $destination = $target = $this->getTargetFile($new_name);

        $filesystem = new FileSystem();
        $filesystem->rename($source, $destination);

        OpCodeCache::invalidate($source);
        OpCodeCache::invalidate($destination);
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
        $filesystem = new FileSystem();
        $directory = $this->getStorageDirectory();

        if (!$filesystem->exists($directory)) {
            return [];
        }

        // Original comment from Drupal:
        // glob() directly calls into libc glob(), which is not aware of PHP stream
        // wrappers. Same for \GlobIterator (which additionally requires an absolute
        // realpath() on Windows).
        // @see https://github.com/mikey179/vfsStream/issues/2
        $files    = scandir($directory);
        $names    = [];
        $pattern  = '/^' . preg_quote($prefix, '/') . '.*' . preg_quote('.php', '/') . '$/';

        foreach ($files as $file) {
            if ($file[0] !== '.' && preg_match($pattern, $file)) {
                $names[] = basename($file, '.php');
            }
        }

        return $names;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAll($prefix = '')
    {
        $files = $this->listAll($prefix);

        if ($files) {
            foreach ($files as $name) {
                $this->delete($name);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function createCollection($collection)
    {
        return new self($this->storageDir, $collection);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllCollectionNames()
    {
        $collections = [];

        foreach (new \DirectoryIterator($this->getStorageDirectory()) as $fileinfo) {
            if ($fileinfo->isDir() && !$fileinfo->isDot()) {
                $collections[] = $fileinfo->getBasename();
            }
        }

        return $collections;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionName()
    {
        return $this->collection;
    }
}
