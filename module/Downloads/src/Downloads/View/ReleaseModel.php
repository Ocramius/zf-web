<?php
namespace Downloads\View;

use InvalidArgumentException;
use Traversable;

/**
 * Returns information about the latest Zend Framework
 * release download.
 */
class ReleaseModel
{
    const ARCHIVE_TAR = 'tar.gz';
    const ARCHIVE_ZIP = 'zip';

    protected $languages;
    protected $mostRecentVersion;
    protected $products;
    protected $releaseBasePath;
    protected $sortedVersions;
    protected $versions;

    public function __construct($releaseBasePath, $versions, $languages, $products)
    {
        $this->releaseBasePath = $releaseBasePath;

        if (!is_array($versions) && !$versions instanceof Traversable) {
            throw new InvalidArgumentException('Invalid versions provided');
        }
        if ($versions instanceof Traversable) {
            $versions = iterator_to_array($versions);
        }
        $this->versions = $versions;

        if (!is_array($languages) && !$languages instanceof Traversable) {
            throw new InvalidArgumentException('Invalid languages provided');
        }
        $this->languages = array();
        foreach ($languages  as $data) {
            if (!is_array($data) && !$data instanceof Traversable) {
                throw new InvalidArgumentException('Invalid language specification provided');
            }

            if ($data instanceof Traversable) {
                $data = iterator_to_array($data);
            }
            $this->languages[] = $data;
        }

        if (!is_array($products) && !$products instanceof Traversable) {
            throw new InvalidArgumentException('Invalid products provided');
        }
        $this->products = array();
        foreach ($products as $product => $data) {
            if (!is_array($data) && !$data instanceof Traversable) {
                throw new InvalidArgumentException('Invalid product specification provided');
            }

            if ($data instanceof Traversable) {
                $data = iterator_to_array($data);
            }
            $this->products[$product] = $data;
        }
    }

    public function getCurrentStableVersion($version = null)
    {
        if (null === $version) {
            return $this->findMostRecentVersion();
        }

        if (!strstr($version, '.')) {
            $next     = ($version + 1) . '.0.0';
            $version .= '.0.0';
            return $this->findMostRecentVersionInSeries($version, $next);
        }

        list($major, $minor) = explode('.', $version, 2);
        if (strstr($minor, '.')) {
            throw new \DomainException(sprintf(
                'Invalid version "%s" provided to %s; must be a major or minor version only',
                $version,
                __METHOD__
            ));
        }
        $start = $version . '.0';
        $end   = sprintf('%d.%d.0', $major, ($minor + 1));
        return $this->findMostRecentVersionInSeries($start, $end);
    }

    public function isStable($version)
    {
        $version = $this->normalizeVersion($version);
        if (preg_match('/(a|alpha|b|beta|rc|dev)/', $version)) {
            return false;
        }
        return true;
    }

    public function isVersionStable($version)
    {
        return !preg_match('/(a|alpha|b|beta|rc)(\d+[a-z]?)?$/i', $version);
    }

    protected function versionCompare($a, $b)
    {
        $cmp = version_compare(strtolower($a), strtolower($b));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcmp($a, $b);
    }

    public function normalizeVersion($version)
    {
        $version = strtolower($version);

        if (strstr($version, 'pr')) {
            $version = str_replace('pr', 'alpha', $version);
        }
        return $version;
    }

    protected function findMostRecentVersion()
    {
        if ($this->mostRecentVersion) {
            return $this->mostRecentVersion;
        }
        $versions = $this->sortVersions();

        foreach ($versions as $version) {
            if ($this->isStable($version)) {
                $this->mostRecentVersion = $version;
                break;
            }
        }

        return $this->mostRecentVersion;
    }

    protected function findMostRecentVersionInSeries($start, $end)
    {
        $versions = $this->sortVersions();
        $versions = array_reverse($versions);
        $current  = false;
        foreach ($versions as $version) {
            if (version_compare($version, $end, 'ge')) {
                break;
            }
            if (version_compare($version, $start, 'lt')) {
                continue;
            }
            if (!$this->isStable($version)) {
                continue;
            }

            $current = $version;
        }
        return $current;
    }

    protected function sortVersions()
    {
        if ($this->sortedVersions) {
            return $this->sortedVersions;
        }
        $versions = array_keys($this->versions);
        array_walk($versions, array($this, 'normalizeVersion'));
        usort($versions, 'version_compare');
        $this->sortedVersions = array_reverse($versions);
        return $this->sortedVersions;
    }
}
