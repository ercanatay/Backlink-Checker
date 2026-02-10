<?php

declare(strict_types=1);

namespace BacklinkChecker\Controllers;

use BacklinkChecker\Database\Database;
use BacklinkChecker\Http\Request;
use BacklinkChecker\Http\Response;
use BacklinkChecker\Security\TokenService;
use BacklinkChecker\Services\AuthService;
use BacklinkChecker\Services\ExportService;
use BacklinkChecker\Services\ProjectService;
use BacklinkChecker\Services\ScanService;
use BacklinkChecker\Services\ScheduleService;
use BacklinkChecker\Exceptions\ValidationException;
use BacklinkChecker\Services\TokenAuthService;
use BacklinkChecker\Services\WebhookService;

final class ApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TokenService $tokens,
        private readonly TokenAuthService $tokenAuth,
        private readonly ProjectService $projects,
        private readonly ScanService $scans,
        private readonly ScheduleService $schedules,
        private readonly ExportService $exports,
        private readonly WebhookService $webhooks,
        private readonly Database $db
    ) {
    }

    public function login(Request $request): Response
    {
        $payload = $request->isJson() ? $request->json() : $request->post;
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $name = (string) ($payload['token_name'] ?? 'API Token');
        $scopes = $payload['scopes'] ?? ['*'];
        if (!is_array($scopes)) {
            $scopes = ['*'];
        }

        $user = $this->auth->authenticate($email, $password);
        if ($user === null) {
            return $this->error('auth_failed', 'Invalid credentials', 401);
        }

        $token = $this->tokens->createToken((int) $user['id'], $name, array_values($scopes));

        return Response::json([
            'token' => $token['plain'],
            'token_id' => $token['id'],
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'roles' => $user['roles'],
            ],
        ], 201);
    }

    public function createProject(Request $request): Response
    {
        $user = $this->requireTokenUser($request, ['projects:write']);
        $payload = $request->isJson() ? $request->json() : $request->post;

        try {
            $projectId = $this->projects->create(
                (int) $user['id'],
                (string) ($payload['name'] ?? ''),
                (string) ($payload['root_domain'] ?? ''),
                (string) ($payload['description'] ?? '')
            );

            return Response::json(['project_id' => $projectId], 201);
        } catch (ValidationException $e) {
            return $this->error('validation_error', $e->getMessage(), 422);
        } catch (\Throwable) {
            return $this->error('server_error', 'Unexpected server error', 500);
        }
    }

    public function createScan(Request $request): Response
    {
        $user = $this->requireTokenUser($request, ['scans:write']);
        $payload = $request->isJson() ? $request->json() : $request->post;

        $projectId = (int) ($payload['project_id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return $this->error('forbidden', 'Project access denied', 403);
        }

        $urls = $payload['urls'] ?? [];
        if (is_string($urls)) {
            $urls = preg_split('/\r\n|\r|\n/', $urls) ?: [];
        }
        if (!is_array($urls)) {
            $urls = [];
        }

        try {
            $scanId = $this->scans->createScan(
                $projectId,
                (int) $user['id'],
                (string) ($payload['root_domain'] ?? $project['root_domain']),
                array_map(static fn($v): string => (string) $v, $urls),
                (string) ($payload['provider'] ?? 'moz')
            );

            return Response::json(['scan_id' => $scanId], 202);
        } catch (ValidationException $e) {
            return $this->error('validation_error', $e->getMessage(), 422);
        } catch (\Throwable) {
            return $this->error('server_error', 'Unexpected server error', 500);
        }
    }

    /**
     * @param array<string, string> $params
     */
    public function showScan(Request $request, array $params): Response
    {
        $user = $this->requireTokenUser($request, ['scans:read']);
        $scanId = (int) ($params['scanId'] ?? 0);

        $scan = $this->scans->findScan($scanId);
        if ($scan === null) {
            return $this->error('not_found', 'Scan not found', 404);
        }

        $project = $this->projects->findForUser((int) $scan['project_id'], (int) $user['id']);
        if ($project === null) {
            return $this->error('forbidden', 'Project access denied', 403);
        }

        return Response::json(['scan' => $scan]);
    }

    /**
     * @param array<string, string> $params
     */
    public function scanResults(Request $request, array $params): Response
    {
        $user = $this->requireTokenUser($request, ['scans:read']);
        $scanId = (int) ($params['scanId'] ?? 0);

        $scan = $this->scans->findScan($scanId);
        if ($scan === null) {
            return $this->error('not_found', 'Scan not found', 404);
        }

        $project = $this->projects->findForUser((int) $scan['project_id'], (int) $user['id']);
        if ($project === null) {
            return $this->error('forbidden', 'Project access denied', 403);
        }

        $filters = [
            'status' => (string) ($request->query['status'] ?? ''),
            'link_type' => (string) ($request->query['link_type'] ?? ''),
            'search' => (string) ($request->query['search'] ?? ''),
            'sort' => (string) ($request->query['sort'] ?? 'id_desc'),
        ];

        $results = $this->scans->results($scanId, $filters);

        return Response::json(['scan_id' => $scanId, 'results' => $results]);
    }

    /**
     * @param array<string, string> $params
     */
    public function cancelScan(Request $request, array $params): Response
    {
        $user = $this->requireTokenUser($request, ['scans:write']);
        $scanId = (int) ($params['scanId'] ?? 0);

        $scan = $this->scans->findScan($scanId);
        if ($scan === null) {
            return $this->error('not_found', 'Scan not found', 404);
        }

        $project = $this->projects->findForUser((int) $scan['project_id'], (int) $user['id']);
        if ($project === null) {
            return $this->error('forbidden', 'Project access denied', 403);
        }

        $this->scans->cancelScan($scanId);

        return Response::json(['status' => 'cancelled']);
    }

    public function createSchedule(Request $request): Response
    {
        $user = $this->requireTokenUser($request, ['schedules:write']);
        $payload = $request->isJson() ? $request->json() : $request->post;

        $projectId = (int) ($payload['project_id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return $this->error('forbidden', 'Project access denied', 403);
        }

        $targets = $payload['targets'] ?? [];
        if (!is_array($targets)) {
            $targets = preg_split('/\r\n|\r|\n/', (string) $targets) ?: [];
        }

        try {
            $scheduleId = $this->schedules->create(
                $projectId,
                (int) $user['id'],
                (string) ($payload['name'] ?? 'Scheduled Scan'),
                (string) ($payload['root_domain'] ?? $project['root_domain']),
                array_map(static fn($v): string => (string) $v, $targets),
                (string) ($payload['rrule'] ?? 'FREQ=HOURLY;INTERVAL=24'),
                (string) ($payload['timezone'] ?? 'UTC')
            );

            return Response::json(['schedule_id' => $scheduleId], 201);
        } catch (ValidationException $e) {
            return $this->error('validation_error', $e->getMessage(), 422);
        } catch (\Throwable) {
            return $this->error('server_error', 'Unexpected server error', 500);
        }
    }

    /**
     * @param array<string, string> $params
     */
    public function downloadExport(Request $request, array $params): Response
    {
        $user = $this->requireTokenUser($request, ['exports:read']);
        $exportId = (int) ($params['exportId'] ?? 0);

        $export = $this->db->fetchOne(
            'SELECT e.* FROM exports e JOIN project_members pm ON pm.project_id = e.project_id WHERE e.id = ? AND pm.user_id = ?',
            [$exportId, $user['id']]
        );
        $filePath = (string) ($export['file_path'] ?? '');
        if ($export === null || $filePath === '' || !is_file($filePath) || !$this->exports->isValidExportPath($filePath)) {
            return $this->error('not_found', 'Export not found', 404);
        }

        $mime = match ((string) $export['format']) {
            'json' => 'application/json; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv; charset=utf-8',
        };

        return new Response(200, (string) file_get_contents($filePath), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . basename($filePath) . '"',
        ]);
    }

    public function testWebhook(Request $request): Response
    {
        $this->requireTokenUser($request, ['webhooks:test']);
        $payload = $request->isJson() ? $request->json() : $request->post;

        $notification = [
            'id' => 0,
            'destination' => (string) ($payload['url'] ?? ''),
            'secret' => (string) ($payload['secret'] ?? ''),
        ];

        if ($notification['destination'] === '') {
            return $this->error('validation_error', 'url is required', 422);
        }

        $result = $this->webhooks->deliver($notification, [
            'event' => 'webhook.test',
            'timestamp' => gmdate('c'),
            'scan' => ['id' => 0, 'project_id' => 0, 'status' => 'test'],
        ]);

        return Response::json($result, $result['success'] ? 200 : 502);
    }

    /**
     * @param array<int, string> $scopes
     * @return array<string, mixed>
     */
    private function requireTokenUser(Request $request, array $scopes): array
    {
        $user = $this->tokenAuth->authenticateBearer($request->header('Authorization'), $scopes);
        if ($user === null) {
            throw new \RuntimeException('Unauthorized');
        }

        return $user;
    }

    private function error(string $code, string $message, int $status): Response
    {
        return Response::json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
