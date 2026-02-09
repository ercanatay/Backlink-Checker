<?php

declare(strict_types=1);

namespace BacklinkChecker\Services;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Logging\JsonLogger;

final class UpdaterService
{
    private const CONFIG_KEY = 'updater.config';
    private const STATE_KEY = 'updater.state';
    private const STABLE_TAG_PATTERN = '/^v?\d+\.\d+\.\d+$/';

    /** @var callable(array<int, string>, string): array{exit_code: int, stdout: string, stderr: string} */
    private $commandRunner;

    /** @var callable(string): array{ok: bool, status: int, body: array<string, mixed>, error: string|null} */
    private $jsonFetcher;

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly SettingsService $settings,
        private readonly QueueService $queue,
        private readonly JsonLogger $logger,
        private readonly string $rootPath,
        ?callable $commandRunner = null,
        ?callable $jsonFetcher = null
    ) {
        $this->commandRunner = $commandRunner ?? fn(array $command, string $cwd): array => $this->executeCommand($command, $cwd);
        $this->jsonFetcher = $jsonFetcher ?? fn(string $url): array => $this->fetchJson($url);
    }

    /**
     * @return array{enabled: bool, interval_minutes: int}
     */
    public function config(): array
    {
        $raw = $this->settings->get(self::CONFIG_KEY, []);
        $config = $this->normalizeConfig($raw);
        if ($raw === []) {
            $this->settings->set(self::CONFIG_KEY, $config);
        }

        return $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = $this->normalizeState($this->settings->get(self::STATE_KEY, []));

        if ($state['current_commit'] === '' || $state['current_branch'] === '' || $state['current_version'] === '') {
            $state = array_merge($state, $this->safeCurrentRepositoryState());
        }

        return $state;
    }

    public function hasPendingJobs(): bool
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM jobs WHERE type IN (?, ?) AND status IN (?, ?)',
            ['updater.check', 'updater.apply', 'queued', 'running']
        );

        return (int) ($row['c'] ?? 0) > 0;
    }

    public function enqueueCheck(string $trigger = 'manual'): ?int
    {
        if ($this->hasPendingJobs()) {
            return null;
        }

        $jobId = $this->queue->enqueue('updater.check', ['trigger' => $trigger]);
        $this->writeState(['last_job_id' => $jobId]);

        return $jobId;
    }

    public function enqueueApply(string $trigger = 'manual'): ?int
    {
        if ($this->hasPendingJobs()) {
            return null;
        }

        $jobId = $this->queue->enqueue('updater.apply', ['trigger' => $trigger]);
        $this->writeState(['last_job_id' => $jobId]);

        return $jobId;
    }

    public function enqueuePeriodicCheckIfDue(): ?int
    {
        $config = $this->config();
        if (!$config['enabled']) {
            return null;
        }

        if ($this->hasPendingJobs()) {
            return null;
        }

        $state = $this->state();
        $lastCheckedAt = (string) ($state['last_checked_at'] ?? '');
        if ($lastCheckedAt !== '') {
            $lastCheckedTs = strtotime($lastCheckedAt);
            if ($lastCheckedTs !== false) {
                $nextAt = $lastCheckedTs + ($config['interval_minutes'] * 60);
                if (time() < $nextAt) {
                    return null;
                }
            }
        }

        return $this->enqueueCheck('scheduler');
    }

    /**
     * @return array{ok: bool, status: string, state: array<string, mixed>, error: string|null}
     */
    public function runCheck(): array
    {
        $now = gmdate('c');
        $this->logger->info('updater.check.started', ['time' => $now]);

        $lock = $this->acquireLock();
        if ($lock === null) {
            $state = $this->writeState([
                'last_checked_at' => $now,
                'last_check_status' => 'check_error',
                'last_check_error' => 'updater_lock_busy',
            ]);

            return ['ok' => false, 'status' => 'check_error', 'state' => $state, 'error' => 'updater_lock_busy'];
        }

        try {
            $repoState = $this->currentRepositoryState();
            $this->expectSuccess($this->runAllowedCommand('git_fetch_tags'), 'git_fetch_tags');

            $repository = $this->githubRepository();
            if ($repository === null) {
                throw new \RuntimeException('origin_remote_not_supported');
            }

            $releaseResult = $this->latestRelease($repository['owner'], $repository['repo']);
            if (!$releaseResult['ok']) {
                throw new \RuntimeException($releaseResult['error'] ?? 'release_fetch_failed');
            }

            $release = $releaseResult['body'];
            $tag = (string) ($release['tag_name'] ?? '');
            if (!$this->isStableTag($tag)) {
                throw new \RuntimeException('latest_release_is_not_stable_semver');
            }

            if ((bool) ($release['draft'] ?? false) || (bool) ($release['prerelease'] ?? false)) {
                throw new \RuntimeException('latest_release_is_not_stable');
            }

            $latestCommit = trim($this->expectSuccess($this->runAllowedCommand('git_rev_list_tag', ['tag' => $tag]), 'git_rev_list_tag')['stdout']);
            if ($latestCommit === '') {
                throw new \RuntimeException('release_tag_commit_not_found');
            }

            $comparison = $this->compareCommits((string) $repoState['current_commit'], $latestCommit);

            $status = match ($comparison) {
                'equal' => 'up_to_date',
                'ancestor' => 'ahead_of_release',
                default => 'update_available',
            };

            $state = $this->writeState(array_merge($repoState, [
                'latest_version' => $tag,
                'latest_url' => (string) ($release['html_url'] ?? ''),
                'update_available' => $status === 'update_available',
                'last_checked_at' => $now,
                'last_check_status' => $status,
                'last_check_error' => null,
            ]));

            $this->logger->info('updater.check.completed', [
                'status' => $status,
                'current_commit' => (string) $repoState['current_commit'],
                'latest_commit' => $latestCommit,
                'latest_version' => $tag,
            ]);

            return ['ok' => true, 'status' => $status, 'state' => $state, 'error' => null];
        } catch (\Throwable $e) {
            $state = $this->writeState(array_merge($this->safeCurrentRepositoryState(), [
                'last_checked_at' => $now,
                'last_check_status' => 'check_error',
                'last_check_error' => $e->getMessage(),
            ]));

            $this->logger->error('updater.check.failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 'check_error', 'state' => $state, 'error' => $e->getMessage()];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array{ok: bool, status: string, state: array<string, mixed>, error: string|null, backup_path: string|null}
     */
    public function runApply(): array
    {
        $now = gmdate('c');
        $this->logger->info('updater.apply.started', ['time' => $now]);

        $lock = $this->acquireLock();
        if ($lock === null) {
            $state = $this->writeState([
                'last_apply_at' => $now,
                'last_apply_status' => 'failed',
                'last_apply_error' => 'updater_lock_busy',
                'rollback_performed' => false,
                'restart_required' => false,
            ]);

            return [
                'ok' => false,
                'status' => 'failed',
                'state' => $state,
                'error' => 'updater_lock_busy',
                'backup_path' => null,
            ];
        }

        $preCommit = '';
        $backupPath = null;

        try {
            $preState = $this->currentRepositoryState();
            $preCommit = (string) $preState['current_commit'];

            $dirty = trim($this->expectSuccess($this->runAllowedCommand('git_status_porcelain'), 'git_status_porcelain')['stdout']);
            if ($dirty !== '') {
                $state = $this->writeState(array_merge($preState, [
                    'last_apply_at' => $now,
                    'last_apply_status' => 'failed',
                    'last_apply_error' => 'dirty_repo',
                    'rollback_performed' => false,
                    'restart_required' => false,
                ]));

                $this->logger->warning('updater.apply.failed', ['error' => 'dirty_repo']);

                return [
                    'ok' => false,
                    'status' => 'failed',
                    'state' => $state,
                    'error' => 'dirty_repo',
                    'backup_path' => null,
                ];
            }

            $backupPath = $this->backupDatabase();

            $composerBefore = $this->fileHash($this->rootPath . '/composer.lock');
            $this->expectSuccess($this->runAllowedCommand('git_pull_ff_only'), 'git_pull_ff_only');
            $composerAfter = $this->fileHash($this->rootPath . '/composer.lock');

            if ($composerBefore !== $composerAfter && $composerAfter !== null) {
                $this->expectSuccess($this->runAllowedCommand('composer_install'), 'composer_install');
            }

            $this->expectSuccess($this->runAllowedCommand('migrate'), 'migrate');

            $postState = $this->safeCurrentRepositoryState();
            $state = $this->writeState(array_merge($postState, [
                'update_available' => false,
                'last_apply_at' => $now,
                'last_apply_status' => 'success',
                'last_apply_error' => null,
                'rollback_performed' => false,
                'restart_required' => true,
            ]));

            $this->logger->info('updater.apply.completed', [
                'status' => 'success',
                'current_commit' => (string) ($postState['current_commit'] ?? ''),
            ]);

            return [
                'ok' => true,
                'status' => 'success',
                'state' => $state,
                'error' => null,
                'backup_path' => $backupPath,
            ];
        } catch (\Throwable $e) {
            $rollbackErrors = [];
            $rollbackPerformed = false;

            try {
                if ($preCommit !== '') {
                    $this->expectSuccess($this->runAllowedCommand('git_reset_hard', ['commit' => $preCommit]), 'git_reset_hard');
                }
                if ($backupPath !== null) {
                    $this->restoreDatabase($backupPath);
                }

                $rollbackPerformed = true;
            } catch (\Throwable $rollbackError) {
                $rollbackErrors[] = $rollbackError->getMessage();
            }

            $postRollbackState = $this->safeCurrentRepositoryState();
            $errorMessage = $e->getMessage();
            if ($rollbackErrors !== []) {
                $errorMessage .= '; rollback_error=' . implode('|', $rollbackErrors);
            }

            $status = $rollbackPerformed ? 'rolled_back' : 'failed';

            $state = $this->writeState(array_merge($postRollbackState, [
                'last_apply_at' => $now,
                'last_apply_status' => $status,
                'last_apply_error' => $errorMessage,
                'rollback_performed' => $rollbackPerformed,
                'restart_required' => false,
            ]));

            $this->logger->error('updater.apply.failed', [
                'error' => $e->getMessage(),
                'rollback_performed' => $rollbackPerformed,
                'rollback_errors' => $rollbackErrors,
            ]);

            if ($rollbackPerformed) {
                $this->logger->warning('updater.apply.rolled_back', ['commit' => $preCommit]);
            }

            return [
                'ok' => false,
                'status' => $status,
                'state' => $state,
                'error' => $errorMessage,
                'backup_path' => $backupPath,
            ];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function currentRepositoryState(): array
    {
        $commit = trim($this->expectSuccess($this->runAllowedCommand('git_rev_parse_head'), 'git_rev_parse_head')['stdout']);
        $branch = trim($this->expectSuccess($this->runAllowedCommand('git_current_branch'), 'git_current_branch')['stdout']);

        return [
            'current_commit' => $commit,
            'current_branch' => $branch,
            'current_version' => $this->detectCurrentVersion(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeCurrentRepositoryState(): array
    {
        try {
            return $this->currentRepositoryState();
        } catch (\Throwable) {
            return [
                'current_commit' => '',
                'current_branch' => '',
                'current_version' => '',
            ];
        }
    }

    private function detectCurrentVersion(): string
    {
        $result = $this->runAllowedCommand('git_tag_points_at_head');
        if ($result['exit_code'] === 0) {
            $tags = preg_split('/\r\n|\r|\n/', trim($result['stdout'])) ?: [];
            $stable = array_values(array_filter($tags, fn(string $tag): bool => $this->isStableTag(trim($tag))));
            if ($stable !== []) {
                usort($stable, static fn(string $a, string $b): int => version_compare(ltrim($b, 'v'), ltrim($a, 'v')));
                return trim($stable[0]);
            }
        }

        $describe = $this->runAllowedCommand('git_describe_latest_tag');
        if ($describe['exit_code'] === 0) {
            $tag = trim($describe['stdout']);
            if ($this->isStableTag($tag)) {
                return $tag;
            }
        }

        return '';
    }

    private function isStableTag(string $tag): bool
    {
        return preg_match(self::STABLE_TAG_PATTERN, trim($tag)) === 1;
    }

    /**
     * @return array{owner: string, repo: string}|null
     */
    private function githubRepository(): ?array
    {
        $result = $this->expectSuccess($this->runAllowedCommand('git_origin_url'), 'git_origin_url');
        $origin = trim($result['stdout']);

        $patterns = [
            '#^https?://github\\.com/([^/]+)/([^/]+?)(?:\\.git)?$#i',
            '#^git@github\\.com:([^/]+)/([^/]+?)(?:\\.git)?$#',
            '#^ssh://git@github\\.com/([^/]+)/([^/]+?)(?:\\.git)?$#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $origin, $matches) === 1) {
                return ['owner' => $matches[1], 'repo' => $matches[2]];
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null}
     */
    private function latestRelease(string $owner, string $repo): array
    {
        $url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', rawurlencode($owner), rawurlencode($repo));

        /** @var callable(string): array{ok: bool, status: int, body: array<string, mixed>, error: string|null} $fetcher */
        $fetcher = $this->jsonFetcher;
        return $fetcher($url);
    }

    private function compareCommits(string $headCommit, string $latestCommit): string
    {
        if ($headCommit === $latestCommit) {
            return 'equal';
        }

        $mergeBase = $this->runAllowedCommand('git_merge_base_is_ancestor', [
            'ancestor' => $latestCommit,
            'descendant' => $headCommit,
        ]);

        if ($mergeBase['exit_code'] === 0) {
            return 'ancestor';
        }

        if ($mergeBase['exit_code'] === 1) {
            return 'diverged';
        }

        throw new \RuntimeException('git_merge_base_failed: ' . trim($mergeBase['stderr']));
    }

    /**
     * @return resource|null
     */
    private function acquireLock()
    {
        $lockPath = $this->rootPath . '/storage/updater/update.lock';
        $dir = dirname($lockPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fp = fopen($lockPath, 'c+');
        if ($fp === false) {
            return null;
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return null;
        }

        return $fp;
    }

    /**
     * @param resource|null $lock
     */
    private function releaseLock($lock): void
    {
        if (!is_resource($lock)) {
            return;
        }

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    private function backupDatabase(): string
    {
        $dbPath = $this->config->string('DB_ABSOLUTE_PATH');
        if ($dbPath === '' || !is_file($dbPath)) {
            throw new \RuntimeException('database_file_not_found');
        }

        $this->db->pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');

        $backupDir = $this->rootPath . '/storage/updater/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $backupPath = sprintf('%s/app.sqlite.%s.bak', $backupDir, gmdate('Ymd_His'));

        if (!copy($dbPath, $backupPath)) {
            throw new \RuntimeException('database_backup_failed');
        }

        return $backupPath;
    }

    private function restoreDatabase(string $backupPath): void
    {
        $dbPath = $this->config->string('DB_ABSOLUTE_PATH');
        if ($dbPath === '' || !is_file($backupPath)) {
            throw new \RuntimeException('database_restore_source_missing');
        }

        $this->db->pdo()->exec('PRAGMA wal_checkpoint(TRUNCATE)');

        if (!copy($backupPath, $dbPath)) {
            throw new \RuntimeException('database_restore_failed');
        }
    }

    /**
     * @return array{enabled: bool, interval_minutes: int}
     */
    private function normalizeConfig(array $value): array
    {
        $enabled = (bool) ($value['enabled'] ?? true);
        $interval = (int) ($value['interval_minutes'] ?? 60);

        return [
            'enabled' => $enabled,
            'interval_minutes' => max(1, $interval),
        ];
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function normalizeState(array $value): array
    {
        $lastCheckError = array_key_exists('last_check_error', $value) ? $value['last_check_error'] : null;
        $lastApplyError = array_key_exists('last_apply_error', $value) ? $value['last_apply_error'] : null;

        return [
            'current_version' => (string) ($value['current_version'] ?? ''),
            'current_commit' => (string) ($value['current_commit'] ?? ''),
            'current_branch' => (string) ($value['current_branch'] ?? ''),
            'latest_version' => (string) ($value['latest_version'] ?? ''),
            'latest_url' => (string) ($value['latest_url'] ?? ''),
            'update_available' => (bool) ($value['update_available'] ?? false),
            'last_checked_at' => (string) ($value['last_checked_at'] ?? ''),
            'last_check_status' => (string) ($value['last_check_status'] ?? ''),
            'last_check_error' => $lastCheckError !== null ? (string) $lastCheckError : null,
            'last_apply_at' => (string) ($value['last_apply_at'] ?? ''),
            'last_apply_status' => (string) ($value['last_apply_status'] ?? ''),
            'last_apply_error' => $lastApplyError !== null ? (string) $lastApplyError : null,
            'rollback_performed' => (bool) ($value['rollback_performed'] ?? false),
            'restart_required' => (bool) ($value['restart_required'] ?? false),
            'last_job_id' => (int) ($value['last_job_id'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $patch
     * @return array<string, mixed>
     */
    private function writeState(array $patch): array
    {
        $current = $this->normalizeState($this->settings->get(self::STATE_KEY, []));
        $merged = $this->normalizeState(array_merge($current, $patch));
        $this->settings->set(self::STATE_KEY, $merged);

        return $merged;
    }

    /**
     * @param array{exit_code: int, stdout: string, stderr: string} $result
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function expectSuccess(array $result, string $commandName): array
    {
        if ($result['exit_code'] === 0) {
            return $result;
        }

        throw new \RuntimeException($commandName . '_failed: ' . trim($result['stderr']));
    }

    /**
     * @param array<string, string> $context
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function runAllowedCommand(string $commandName, array $context = []): array
    {
        $command = match ($commandName) {
            'git_fetch_tags' => ['git', 'fetch', '--tags', '--prune', 'origin'],
            'git_rev_parse_head' => ['git', 'rev-parse', 'HEAD'],
            'git_current_branch' => ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            'git_status_porcelain' => ['git', 'status', '--porcelain'],
            'git_pull_ff_only' => ['git', 'pull', '--ff-only'],
            'git_tag_points_at_head' => ['git', 'tag', '--points-at', 'HEAD'],
            'git_describe_latest_tag' => ['git', 'describe', '--tags', '--abbrev=0'],
            'git_origin_url' => ['git', 'remote', 'get-url', 'origin'],
            'composer_install' => ['composer', 'install', '--no-interaction', '--no-progress'],
            'migrate' => ['php', 'bin/migrate.php'],
            'git_rev_list_tag' => ['git', 'rev-list', '-n', '1', $this->safeTag($context['tag'] ?? '')],
            'git_merge_base_is_ancestor' => ['git', 'merge-base', '--is-ancestor', $this->safeCommit($context['ancestor'] ?? ''), $this->safeCommit($context['descendant'] ?? '')],
            'git_reset_hard' => ['git', 'reset', '--hard', $this->safeCommit($context['commit'] ?? '')],
            default => throw new \RuntimeException('command_not_allowed: ' . $commandName),
        };

        /** @var callable(array<int, string>, string): array{exit_code: int, stdout: string, stderr: string} $runner */
        $runner = $this->commandRunner;
        return $runner($command, $this->rootPath);
    }

    private function safeTag(string $tag): string
    {
        $normalized = trim($tag);
        if (!$this->isStableTag($normalized)) {
            throw new \RuntimeException('invalid_release_tag');
        }

        return $normalized;
    }

    private function safeCommit(string $commit): string
    {
        $normalized = trim($commit);
        if (preg_match('/^[0-9a-f]{7,40}$/i', $normalized) !== 1) {
            throw new \RuntimeException('invalid_commit_hash');
        }

        return $normalized;
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, error: string|null}
     */
    private function fetchJson(string $url): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/vnd.github+json',
                'User-Agent: BacklinkCheckerUpdater/2.0',
            ],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'status' => $status, 'body' => [], 'error' => $error !== '' ? $error : 'http_error'];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'status' => $status, 'body' => [], 'error' => 'invalid_json'];
        }

        if ($status < 200 || $status >= 300) {
            $message = (string) ($decoded['message'] ?? ('http_status_' . $status));
            return ['ok' => false, 'status' => $status, 'body' => $decoded, 'error' => $message];
        }

        return ['ok' => true, 'status' => $status, 'body' => $decoded, 'error' => null];
    }

    /**
     * @param array<int, string> $command
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    private function executeCommand(array $command, string $cwd): array
    {
        $pipes = [];
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('process_start_failed');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'exit_code' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }

    private function fileHash(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $hash = hash_file('sha256', $path);

        return $hash !== false ? $hash : null;
    }
}
