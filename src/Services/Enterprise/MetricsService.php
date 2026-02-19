<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Services\Enterprise;

/**
 * Metrics Service for APM/Monitoring
 *
 * Provides unified interface for sending metrics to DataDog, StatsD, or other backends.
 * Supports counters, gauges, histograms, distributions, and service checks.
 */
class MetricsService
{
    private static ?MetricsService $instance = null;
    private ?object $statsd = null;
    private bool $enabled;
    private string $prefix;
    private array $globalTags;
    private array $buffer = [];
    private int $bufferSize = 50;
    private bool $useBuffer = true;

    private function __construct()
    {
        $this->enabled = !empty(getenv('DD_AGENT_HOST')) || !empty(getenv('STATSD_HOST'));
        $this->prefix = getenv('METRICS_PREFIX') ?: 'nexus';

        $this->globalTags = [
            'env' => getenv('APP_ENV') ?: 'production',
            'service' => getenv('DD_SERVICE') ?: 'nexus-app',
            'version' => getenv('APP_VERSION') ?: '1.0.0',
        ];

        if (getenv('DD_TENANT_ID')) {
            $this->globalTags['tenant_id'] = getenv('DD_TENANT_ID');
        }

        if ($this->enabled) {
            $this->initializeClient();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the StatsD/DataDog client
     */
    private function initializeClient(): void
    {
        $host = getenv('DD_AGENT_HOST') ?: getenv('STATSD_HOST') ?: 'localhost';
        $port = (int) (getenv('DD_DOGSTATSD_PORT') ?: getenv('STATSD_PORT') ?: 8125);

        try {
            // Use DataDog DogStatsD if available
            if (class_exists('DataDog\DogStatsd')) {
                $this->statsd = new \DataDog\DogStatsd([
                    'host' => $host,
                    'port' => $port,
                    'global_tags' => $this->formatTags($this->globalTags),
                ]);
            } else {
                // Fallback to simple UDP implementation
                $this->statsd = new class($host, $port) {
                    private $socket;
                    private string $host;
                    private int $port;

                    public function __construct(string $host, int $port)
                    {
                        $this->host = $host;
                        $this->port = $port;
                        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                    }

                    public function send(string $message): void
                    {
                        if ($this->socket) {
                            @socket_sendto($this->socket, $message, strlen($message), 0, $this->host, $this->port);
                        }
                    }

                    public function __destruct()
                    {
                        if ($this->socket) {
                            socket_close($this->socket);
                        }
                    }
                };
            }
        } catch (\Exception $e) {
            error_log("Failed to initialize metrics client: " . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Check if metrics are enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Increment a counter
     */
    public function increment(string $metric, array $tags = [], int $value = 1): void
    {
        $this->sendMetric($metric, $value, 'c', $tags);
    }

    /**
     * Decrement a counter
     */
    public function decrement(string $metric, array $tags = [], int $value = 1): void
    {
        $this->sendMetric($metric, -$value, 'c', $tags);
    }

    /**
     * Set a gauge value
     */
    public function gauge(string $metric, float $value, array $tags = []): void
    {
        $this->sendMetric($metric, $value, 'g', $tags);
    }

    /**
     * Record a histogram value
     */
    public function histogram(string $metric, float $value, array $tags = []): void
    {
        $this->sendMetric($metric, $value, 'h', $tags);
    }

    /**
     * Record a distribution value
     */
    public function distribution(string $metric, float $value, array $tags = []): void
    {
        $this->sendMetric($metric, $value, 'd', $tags);
    }

    /**
     * Record a timing value (in milliseconds)
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $this->sendMetric($metric, $milliseconds, 'ms', $tags);
    }

    /**
     * Time a callable and record the duration
     */
    public function time(string $metric, callable $callback, array $tags = [])
    {
        $start = microtime(true);
        $status = 'success';

        try {
            $result = $callback();
            return $result;
        } catch (\Throwable $e) {
            $status = 'error';
            $tags['error_type'] = get_class($e);
            throw $e;
        } finally {
            $duration = (microtime(true) - $start) * 1000; // Convert to ms
            $tags['status'] = $status;
            $this->histogram($metric, $duration, $tags);
        }
    }

    /**
     * Record a set value (unique occurrences)
     */
    public function set(string $metric, $value, array $tags = []): void
    {
        $this->sendMetric($metric, $value, 's', $tags);
    }

    /**
     * Send a service check
     */
    public function serviceCheck(string $name, int $status, array $options = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $tags = array_merge($this->globalTags, $options['tags'] ?? []);
        $message = $options['message'] ?? '';
        $timestamp = $options['timestamp'] ?? time();

        $check = sprintf(
            "_sc|%s.%s|%d|d:%d|#%s",
            $this->prefix,
            $name,
            $status,
            $timestamp,
            $this->formatTagsString($tags)
        );

        if ($message) {
            $check .= "|m:{$message}";
        }

        $this->send($check);
    }

    /**
     * Send an event
     */
    public function event(string $title, string $text, array $options = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $tags = array_merge($this->globalTags, $options['tags'] ?? []);
        $alertType = $options['alert_type'] ?? 'info';
        $priority = $options['priority'] ?? 'normal';
        $timestamp = $options['timestamp'] ?? time();

        $event = sprintf(
            "_e{%d,%d}:%s|%s|d:%d|p:%s|t:%s|#%s",
            strlen($title),
            strlen($text),
            $title,
            $text,
            $timestamp,
            $priority,
            $alertType,
            $this->formatTagsString($tags)
        );

        $this->send($event);
    }

    /**
     * Record a business metric
     */
    public function business(string $metric, float $value, array $tags = []): void
    {
        $tags['metric_type'] = 'business';
        $this->gauge("business.{$metric}", $value, $tags);
    }

    /**
     * Send a metric
     */
    private function sendMetric(string $metric, $value, string $type, array $tags = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $allTags = array_merge($this->globalTags, $tags);
        $metricName = "{$this->prefix}.{$metric}";

        if ($this->useBuffer) {
            $this->buffer[] = [
                'name' => $metricName,
                'value' => $value,
                'type' => $type,
                'tags' => $allTags,
            ];

            if (count($this->buffer) >= $this->bufferSize) {
                $this->flush();
            }
        } else {
            $message = sprintf(
                "%s:%s|%s|#%s",
                $metricName,
                $value,
                $type,
                $this->formatTagsString($allTags)
            );
            $this->send($message);
        }
    }

    /**
     * Flush buffered metrics
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        foreach ($this->buffer as $metric) {
            $message = sprintf(
                "%s:%s|%s|#%s",
                $metric['name'],
                $metric['value'],
                $metric['type'],
                $this->formatTagsString($metric['tags'])
            );
            $this->send($message);
        }

        $this->buffer = [];
    }

    /**
     * Send data to StatsD
     */
    private function send(string $message): void
    {
        if (!$this->statsd) {
            return;
        }

        try {
            if (method_exists($this->statsd, 'send')) {
                $this->statsd->send($message);
            }
        } catch (\Exception $e) {
            error_log("Failed to send metric: " . $e->getMessage());
        }
    }

    /**
     * Format tags array for DogStatsD
     */
    private function formatTags(array $tags): array
    {
        $formatted = [];
        foreach ($tags as $key => $value) {
            $formatted[] = "{$key}:{$value}";
        }
        return $formatted;
    }

    /**
     * Format tags as string
     */
    private function formatTagsString(array $tags): string
    {
        return implode(',', $this->formatTags($tags));
    }

    /**
     * Add global tag
     */
    public function addGlobalTag(string $key, string $value): void
    {
        $this->globalTags[$key] = $value;
    }

    /**
     * Destructor - flush remaining metrics
     */
    public function __destruct()
    {
        $this->flush();
    }
}
