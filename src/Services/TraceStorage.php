<?php

namespace Akhtar\LaravelDebugTracer\Services;

use Carbon\CarbonImmutable;

class TraceStorage
{
    public function __construct(private readonly string $storagePath)
    {
        $this->ensureDirectoryExists();
    }

    public function createSessionMeta(string $sessionId, string $token, string $status, CarbonImmutable $expiresAt): array
    {
        $token = (string) normalize_debug_token($token);

        $meta = [
            'session_id' => $sessionId,
            'token' => $token,
            'status' => $status,
            'expires_at' => $expiresAt->toIso8601String(),
            'created_at' => CarbonImmutable::now('UTC')->toIso8601String(),
        ];

        file_put_contents($this->metaPath($sessionId), json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($this->logPath($sessionId), '', FILE_APPEND | LOCK_EX);

        return $meta;
    }

    public function getSessionMeta(string $sessionId): ?array
    {
        $path = $this->metaPath($sessionId);

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function appendEvent(string $sessionId, array $event): void
    {
        $meta = $this->getSessionMeta($sessionId);
        if (! $meta || ($meta['status'] ?? null) !== 'active') {
            return;
        }

        if ($this->isExpired($meta)) {
            $this->deleteSessionFiles($sessionId);

            return;
        }

        $payload = array_merge($event, [
            'session_id' => $sessionId,
            'timestamp' => CarbonImmutable::now('UTC')->toIso8601String(),
        ]);

        file_put_contents(
            $this->logPath($sessionId),
            json_encode($payload, JSON_UNESCAPED_SLASHES).PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function findActiveSessionForToken(string $token): ?array
    {
        foreach ($this->allMetaFiles() as $metaFile) {
            $meta = json_decode((string) file_get_contents($metaFile), true);
            if (! is_array($meta)) {
                continue;
            }

            if (normalize_debug_token($meta['token'] ?? null) !== normalize_debug_token($token)) {
                continue;
            }

            if (($meta['status'] ?? null) !== 'active') {
                continue;
            }

            if ($this->isExpired($meta)) {
                $this->deleteSessionFiles((string) ($meta['session_id'] ?? ''));

                continue;
            }

            return $meta;
        }

        return null;
    }

    public function clearAllSessions(): int
    {
        $deleted = 0;

        foreach (glob($this->storagePath.'/*') ?: [] as $path) {
            if (is_file($path) && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function deleteSessionFiles(string $sessionId): void
    {
        if ($sessionId === '') {
            return;
        }

        @unlink($this->metaPath($sessionId));
        @unlink($this->logPath($sessionId));
    }

    public function cleanupExpiredAndStoppedSessions(): int
    {
        $deleted = 0;

        foreach ($this->allMetaFiles() as $metaFile) {
            $meta = json_decode((string) file_get_contents($metaFile), true);
            if (! is_array($meta)) {
                @unlink($metaFile);
                $deleted++;

                continue;
            }

            $sessionId = (string) ($meta['session_id'] ?? '');
            $status = $meta['status'] ?? null;
            $expired = $this->isExpired($meta);

            if (! $expired && $status !== 'stopped') {
                continue;
            }

            $this->deleteSessionFiles($sessionId);
            $deleted++;
        }

        return $deleted;
    }

    public function logPath(string $sessionId): string
    {
        return $this->storagePath.'/'.$sessionId.'.log';
    }

    public function metaPath(string $sessionId): string
    {
        return $this->storagePath.'/'.$sessionId.'.meta.json';
    }

    private function allMetaFiles(): array
    {
        return glob($this->storagePath.'/*.meta.json') ?: [];
    }

    private function isExpired(array $meta): bool
    {
        $expiresAt = $meta['expires_at'] ?? null;

        return ! $expiresAt || CarbonImmutable::parse($expiresAt)->isPast();
    }

    private function ensureDirectoryExists(): void
    {
        if (! is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }
}
