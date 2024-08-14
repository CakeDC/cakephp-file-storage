<?php
declare(strict_types=1);

namespace Burzum\FileStorage\Storage;

use Burzum\FileStorage\Storage\PathBuilder\BasePathBuilder;
use Cake\Core\Configure;
use Cake\Utility\Text;
use InvalidArgumentException;
use Laminas\Diactoros\UploadedFile;
use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Utility methods for which I could not find a better place
 *
 * @author Florian Krämer
 * @copyright 2012 - 2017 Florian Krämer
 * @license MIT
 */
class StorageUtils
{
    /**
     * Return file extension from a given filename.
     *
     * @param string $name File name
     * @param bool|bool $realFile
     * @link http://php.net/manual/en/function.pathinfo.php
     * @return false|string string or false
     */
    public static function fileExtension(string $name, bool $realFile = false)
    {
        if ($realFile) {
            $result = pathinfo($name, PATHINFO_EXTENSION);
            if (empty($result)) {
                return false;
            }
        }

        return substr(strrchr($name, '.'), 1);
    }

    /**
     * Builds a semi-random path based on a given string to avoid having
     * thousands of files or directories in one directory. This would result in
     * a slowdown on most file systems.
     *
     * Works up to 5 level deep
     *
     * @deprecated Use the randomPath() method from the BasePathBuilder instead.
     * @link http://php.net/manual/en/function.crc32.php
     * @link https://www.box.com/blog/crc32-checksums-the-good-the-bad-and-the-ugly/
     * @param mixed $string
     * @param int|int $level 1 to 5
     * @return null|string
     * @throws \InvalidArgumentException
     */
    public static function randomPath($string, int $level = 3): ?string
    {
        if (!$string) {
            throw new InvalidArgumentException('First argument is not a string!');
        }

        return (new BasePathBuilder())->randomPath($string, $level, 'sha1');
    }

    /**
     * Helper method to trim last trailing slash in file path
     *
     * @param string $path Path to trim
     * @return string Trimmed path
     */
    public static function trimPath(string $path): string
    {
        $len = strlen($path);
        if ($path[$len - 1] == '\\' || $path[$len - 1] == '/') {
            $path = substr($path, 0, $len - 1);
        }

        return $path;
    }

    /**
     * Converts windows to linux paths and vice versa
     *
     * @param string $path Path
     * @return string
     */
    public static function normalizePath($path): string
    {
        if (DS == '\\') {
            return str_replace('/', '\\', $path);
        }

        return str_replace('\\', '/', $path);
    }

    /**
     * Method to normalize the annoying inconsistency of the $_FILE array structure
     *
     * @link http://de2.php.net/manual/en/features.file-upload.multiple.php#53240
     * @param array|null $files Files array
     * @return array Empty array if $_FILE is empty, if not normalize array of Filedata.{n}
     */
    public static function normalizeGlobalFilesArray(?array $files = null): array
    {
        if (empty($files)) {
            $files = $_FILES;
        }

        $array = [];
        $fileCount = count($files['name']);
        $fileKeys = array_keys($files);

        for ($i = 0; $i < $fileCount; $i++) {
            foreach ($fileKeys as $key) {
                $array[$i][$key] = $files[$key][$i];
            }
        }

        return $array;
    }

    /**
     * Serializes and then hashes an array of operations that are applied to an image
     *
     * @param array $operations
     * @return string
     */
    public static function hashOperations(array $operations): string
    {
        static::ksortRecursive($operations);

        return substr(md5(serialize($operations)), 0, 8);
    }

    /**
     * Generates the hashes for the different image version configurations.
     *
     * @param string|array $configPath
     * @return array
     */
    public static function generateHashes($configPath = 'FileStorage'): array
    {
        if (is_array($configPath)) {
            $imageSizes = $configPath;
        } else {
            $imageSizes = Configure::read($configPath . '.imageSizes');
        }
        if ($imageSizes === null) {
            throw new RuntimeException(sprintf('Image processing configuration in `%s` is missing!', $configPath . '.imageSizes'));
        }

        static::ksortRecursive($imageSizes);
        foreach ($imageSizes as $model => $version) {
            foreach ($version as $name => $operations) {
                Configure::write($configPath . '.imageHashes.' . $model . '.' . $name, static::hashOperations($operations));
            }
        }

        return Configure::read($configPath . '.imageHashes');
    }

    /**
     * Recursive ksort() implementation
     *
     * @param array $array Array
     * @param int $sortFlags Sort flags
     * @return bool
     * @link https://gist.github.com/601849
     */
    public static function ksortRecursive(array &$array, $sortFlags = SORT_REGULAR): bool
    {
        ksort($array, $sortFlags);
        foreach ($array as &$arr) {
            if (is_array($arr)) {
                static::ksortRecursive($arr, $sortFlags);
            }
        }

        return true;
    }

    /**
     * Returns UploadedFileInterface object from the file path
     */
    public static function fileToUploadedFileObject(string $file, $fileName = null): UploadedFileInterface
    {
        return new UploadedFile(
            $file,
            filesize($file),
            UPLOAD_ERR_OK,
            $fileName ?? basename($file),
            (new FinfoMimeTypeDetector())->detectMimeTypeFromFile($file)
        );
    }

    /**
     * Creates a temporary file name and checks the tmp path, creates one if required.
     *
     * This method is thought to be used to generate tmp file locations for use cases
     * like audio or image process were you need copies of a file and want to avoid
     * conflicts. By default the tmp file is generated using cakes TMP constant +
     * folder if passed and a uuid as filename.
     *
     * @param string|null $folder
     * @param bool|bool $checkAndCreatePath
     * @return string For example /var/www/app/tmp/<uuid> or /var/www/app/tmp/<my-folder>/<uuid>
     */
    public static function createTmpFile(?string $folder = null, bool $checkAndCreatePath = true): string
    {
        if ($folder === null) {
            $folder = TMP;
        }
        if ($checkAndCreatePath === true && !is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        return $folder . Text::uuid();
    }

    /**
     * Gets the hash of a file contents.
     *
     * You can use this to compare if you got two times the same file uploaded.
     */
    public static function getFileContentHash(string $fileContent, string $method = 'sha1'): string
    {
        if ($method === 'md5') {
            return md5($fileContent);
        }
        if ($method === 'sha1') {
            return sha1($fileContent);
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid hash method "%s" provided!',
            $method
        ));
    }
}
