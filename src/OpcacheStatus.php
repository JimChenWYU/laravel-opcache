<?php

namespace Appstract\Opcache;

use Appstract\Opcache\Format\Byte;
use Appstract\Opcache\Format\Trimmer;

/**
 * Class OpcacheStatus
 */
class OpcacheStatus
{
    /**
     * @var string The colors of the graphs
     */
    const DARK_GREEN = '#16a085';
    const RED        = '#e74c3c';
    const GREEN      = '#2ecc71';

    /**
     * @var \Appstract\Opcache\Format\Byte Formatter of byte values
     */
    private $byteFormatter;
    /**
     * @var array The (unfiltered) output of opcache_get_status()
     */
    private $statusData;

    /**
     * Creates instance
     */
    public function __construct(Byte $byteFormatter, array $statusData)
    {
        $this->byteFormatter = $byteFormatter;
        $this->statusData    = $statusData;
    }

    /**
     * Gets the status info of OpCache
     *
     * @return array The status info
     */
    public function getStatusInfo()
    {
        return [
            'opcache_enabled'     => $this->statusData['opcache_enabled'],
            'cache_full'          => $this->statusData['cache_full'],
            'restart_pending'     => $this->statusData['restart_pending'],
            'restart_in_progress' => $this->statusData['restart_in_progress'],
        ];
    }

    /**
     * Gets the memory info of OpCache
     *
     * @return array The memory info
     */
    public function getMemoryInfo()
    {
        $memory = $this->statusData['memory_usage'];

        return [
            'used_memory'               => $this->byteFormatter->format($memory['used_memory']),
            'free_memory'               => $this->byteFormatter->format($memory['free_memory']),
            'wasted_memory'             => $this->byteFormatter->format($memory['wasted_memory']),
            'current_wasted_percentage' => round($memory['current_wasted_percentage'], 2) . '%',
        ];
    }

    /**
     * Gets the memory info formatted to build a graph
     *
     * @return string JSON encoded memory info
     */
    public function getGraphMemoryInfo()
    {
        $memory = $this->statusData['memory_usage'];

        return json_encode([
            [
                'value' => $memory['used_memory'],
                'color' => self::DARK_GREEN,
                'label' => trans('opcache::messages.graph.memory.used'),
            ],
            [
                'value' => $memory['free_memory'],
                'color' => self::GREEN,
                'label' => trans('opcache::messages.graph.memory.free'),
            ],
            [
                'value' => $memory['wasted_memory'],
                'color' => self::RED,
                'label' => trans('opcache::messages.graph.memory.wasted'),
            ],
        ]);
    }

    /**
     * Gets the key statistics formatted to build a graph
     *
     * @return string JSON encoded key statistics
     */
    public function getGraphKeyStatsInfo()
    {
        $stats = $this->statusData['opcache_statistics'];

        return json_encode([
            [
                'value' => $stats['num_cached_scripts'],
                'color' => self::DARK_GREEN,
                'label' => trans('opcache::messages.graph.keys.scripts'),
            ],
            [
                'value' => $stats['max_cached_keys'] - $stats['num_cached_keys'],
                'color' => self::GREEN,
                'label' => trans('opcache::messages.graph.keys.free'),
            ],
            [
                'value' => $stats['num_cached_keys'] - $stats['num_cached_scripts'],
                'color' => self::RED,
                'label' => trans('opcache::messages.graph.keys.wasted'),
            ],
        ]);
    }

    /**
     * Gets the hit statistics formatted to build a graph
     *
     * @return string JSON encoded hit statistics
     */
    public function getGraphHitStatsInfo()
    {
        $stats = $this->statusData['opcache_statistics'];

        return json_encode([
            [
                'value' => $stats['hits'],
                'color' => self::GREEN,
                'label' => trans('opcache::messages.graph.hits.hits'),
            ],
            [
                'value' => $stats['misses'],
                'color' => self::RED,
                'label' => trans('opcache::messages.graph.hits.misses'),
            ],
            [
                'value' => $stats['blacklist_misses'],
                'color' => self::DARK_GREEN,
                'label' => trans('opcache::messages.graph.hits.blacklist'),
            ],
        ]);
    }

    /**
     * Gets the statistics info
     *
     * @return array The statistics info
     */
    public function getStatsInfo()
    {
        if (!$this->statusData['opcache_enabled']) {
            return [
                [
                    'num_cached_scripts'   => 0,
                    'num_cached_keys'      => 0,
                    'max_cached_keys'      => 0,
                    'hits'                 => 0,
                    'misses'               => 0,
                    'blacklist_misses'     => 0,
                    'blacklist_miss_ratio' => 'n/a',
                ],
                [
                    'opcache_hit_rate'     => 'n/a',
                    'start_time'           => 'n/a',
                    'last_restart_time'    => 'n/a',
                    'oom_restarts'         => 'n/a',
                    'hash_restarts'        => 'n/a',
                    'manual_restarts'      => 'n/a',
                ],
            ];
        }

        $stats = $this->statusData['opcache_statistics'];

        $lastRestartTime = null;

        if ($stats['last_restart_time']) {
            $lastRestartTime = now()->setTimestamp($stats['last_restart_time'])->format('H:i:s d-m-Y');
        }

        return [
            [
                'num_cached_scripts'   => $stats['num_cached_scripts'],
                'num_cached_keys'      => $stats['num_cached_keys'],
                'max_cached_keys'      => $stats['max_cached_keys'],
                'hits'                 => $stats['hits'],
                'misses'               => $stats['misses'],
                'blacklist_misses'     => $stats['blacklist_misses'],
                'blacklist_miss_ratio' => round($stats['blacklist_miss_ratio'], 2),
            ],
            [
                'opcache_hit_rate'     => round($stats['opcache_hit_rate'], 2) . '%',
                'start_time'           => now()->setTimestamp($stats['start_time'])->format('H:i:s d-m-Y'),
                'last_restart_time'    => $lastRestartTime,
                'oom_restarts'         => $stats['oom_restarts'],
                'hash_restarts'        => $stats['hash_restarts'],
                'manual_restarts'      => $stats['manual_restarts'],
            ],
        ];
    }

    /**
     * Gets the cached scripts
     *
     * @return array List of the cached scripts
     */
    public function getCachedScripts()
    {
        if (!isset($this->statusData['scripts'])) {
            return [];
        }

        $scripts = [];

        foreach ($this->statusData['scripts'] as $script) {
            if (isset($script['timestamp']) && $script['timestamp'] === 0) {
                continue;
            }

            $timestamp = 'N/A';

            if (isset($script['timestamp'])) {
                $timestamp = now()->setTimestamp($script['timestamp'])->format('H:i:s d-m-Y');
            }

            $scripts[] = [
                'full_path'           => $script['full_path'],
                'hits'                => $script['hits'],
                'memory_consumption'  => $this->byteFormatter->format($script['memory_consumption']),
                'last_used_timestamp' => now()->setTimestamp($script['last_used_timestamp'])->format('H:i:s d-m-Y'),
                'timestamp'           => $timestamp,
            ];
        }

        usort($scripts, [$this, 'sortCachedScripts']);

        return $scripts;
    }

    /**
     * Gets the cached scripts for the overview (with trimmed prefix)
     *
     * @param \Appstract\Opcache\Format\Trimmer $trimmer The prefix trimmer
     *
     * @return array List of the cached scripts
     */
    public function getCachedScriptsForOverview(Trimmer $trimmer)
    {
        return $trimmer->trim($this->getCachedScripts());
    }

    /**
     * Sorts the lists of cached scripts
     *
     * @param array $a Array to compare
     * @param array $b Array to compare
     *
     * @return int The direction of the sort
     */
    private function sortCachedScripts(array $a, array $b)
    {
        return strcmp($a['full_path'], $b['full_path']);
    }
}
