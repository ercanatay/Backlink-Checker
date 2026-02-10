<?php

declare(strict_types=1);

namespace BacklinkChecker\Controllers;

use BacklinkChecker\Database\Database;
use BacklinkChecker\Exceptions\ValidationException;
use BacklinkChecker\Http\Request;
use BacklinkChecker\Http\Response;
use BacklinkChecker\Http\SessionManager;
use BacklinkChecker\I18n\Translator;
use BacklinkChecker\Security\Csrf;
use BacklinkChecker\Security\TokenService;
use BacklinkChecker\Services\AuditService;
use BacklinkChecker\Services\AuthService;
use BacklinkChecker\Services\ExportService;
use BacklinkChecker\Services\ProjectService;
use BacklinkChecker\Services\SavedViewService;
use BacklinkChecker\Services\ScanService;
use BacklinkChecker\Services\ScheduleService;
use BacklinkChecker\Services\SettingsService;
use BacklinkChecker\Services\UpdaterService;
use BacklinkChecker\Support\ViewRenderer;

final class WebController
{
    public function __construct(
        private readonly Database $db,
        private readonly SessionManager $session,
        private readonly AuthService $auth,
        private readonly ProjectService $projects,
        private readonly ScanService $scans,
        private readonly ExportService $exports,
        private readonly SavedViewService $savedViews,
        private readonly ScheduleService $schedules,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
        private readonly TokenService $tokens,
        private readonly Csrf $csrf,
        private readonly Translator $translator,
        private readonly ViewRenderer $views,
        private readonly UpdaterService $updater
    ) {
    }

    public function home(Request $request): Response
    {
        if ($this->session->user() === null) {
            return Response::redirect('/login');
        }

        return Response::redirect('/dashboard');
    }

    public function login(Request $request): Response
    {
        if ($request->method() === 'GET') {
            return Response::html($this->render('login', ['error' => null]));
        }

        $this->requireCsrf($request);

        $email = trim((string) ($request->post['email'] ?? ''));
        $password = (string) ($request->post['password'] ?? '');

        $user = $this->auth->authenticate($email, $password);
        if ($user === null) {
            return Response::html($this->render('login', ['error' => $this->t('auth.failed')]), 401);
        }

        $this->session->login($user);
        $this->audit->log((int) $user['id'], 'auth.login', 'user', (string) $user['id'], [], $request->ip(), $request->userAgent());

        return Response::redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->requireCsrf($request);

        $user = $this->session->user();
        if ($user !== null) {
            $this->audit->log((int) $user['id'], 'auth.logout', 'user', (string) $user['id'], [], $request->ip(), $request->userAgent());
        }

        $this->session->logout();

        return Response::redirect('/login');
    }

    public function dashboard(Request $request): Response
    {
        $user = $this->requireUser();
        $projects = $this->projects->listForUser((int) $user['id']);
        $telemetry = $this->settings->get('telemetry.enabled', ['enabled' => false]);
        $retention = $this->settings->get('retention.days', ['days' => 90]);

        $latestScans = [];
        if ($projects !== []) {
            $latestScans = $this->scans->listScansByProject((int) $projects[0]['id'], 10);
        }

        return Response::html($this->render('dashboard', [
            'projects' => $projects,
            'latestScans' => $latestScans,
            'error' => null,
            'flash' => $this->pullFlash(),
            'settingsForm' => [
                'telemetry_enabled' => (bool) ($telemetry['enabled'] ?? false),
                'retention_days' => max(1, (int) ($retention['days'] ?? 90)),
            ],
            'updaterConfig' => $this->updater->config(),
            'updaterState' => $this->updater->state(),
        ]));
    }

    public function createProject(Request $request): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        try {
            $projectId = $this->projects->create(
                (int) $user['id'],
                (string) ($request->post['name'] ?? ''),
                (string) ($request->post['root_domain'] ?? ''),
                (string) ($request->post['description'] ?? '')
            );
            $this->audit->log((int) $user['id'], 'project.create', 'project', (string) $projectId);
            $this->flash('success', 'Project created');
            return Response::redirect('/projects/' . $projectId);
        } catch (ValidationException $e) {
            return Response::html($this->render('dashboard', [
                'projects' => $this->projects->listForUser((int) $user['id']),
                'latestScans' => [],
                'error' => $e->getMessage(),
                'flash' => null,
                'settingsForm' => [
                    'telemetry_enabled' => false,
                    'retention_days' => 90,
                ],
                'updaterConfig' => $this->updater->config(),
                'updaterState' => $this->updater->state(),
            ]), 422);
        }
    }

    /**
     * @param array<string, string> $params
     */
    public function showProject(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $projectId = (int) ($params['id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        $scans = $this->scans->listScansByProject($projectId);
        $members = $this->projects->members($projectId);
        $notifications = $this->projects->notifications($projectId);
        $savedViews = $this->savedViews->listForUser((int) $user['id'], $projectId);
        $schedules = $this->schedules->listByProject($projectId);

        return Response::html($this->render('project', [
            'project' => $project,
            'scans' => $scans,
            'members' => $members,
            'notifications' => $notifications,
            'savedViews' => $savedViews,
            'schedules' => $schedules,
            'error' => null,
            'flash' => $this->pullFlash(),
        ]));
    }

    /**
     * @param array<string, string> $params
     */
    public function addProjectMember(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $projectId = (int) ($params['id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        if (!in_array((string) ($project['membership_role'] ?? ''), ['admin'], true) && !in_array('admin', $user['roles'] ?? [], true)) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        try {
            $this->projects->addOrUpdateMember(
                $projectId,
                (string) ($request->post['email'] ?? ''),
                (string) ($request->post['role'] ?? 'viewer')
            );
            $this->audit->log((int) $user['id'], 'project.member.upsert', 'project', (string) $projectId);
            $this->flash('success', 'Member updated');
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
        }

        return Response::redirect('/projects/' . $projectId);
    }

    /**
     * @param array<string, string> $params
     */
    public function addProjectNotification(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $projectId = (int) ($params['id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        if (!in_array((string) ($project['membership_role'] ?? ''), ['admin', 'editor'], true) && !in_array('admin', $user['roles'] ?? [], true)) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        try {
            $this->projects->addNotification(
                $projectId,
                (int) $user['id'],
                (string) ($request->post['channel'] ?? ''),
                (string) ($request->post['destination'] ?? ''),
                (string) ($request->post['secret'] ?? '')
            );
            $this->flash('success', 'Notification added');
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
        }

        return Response::redirect('/projects/' . $projectId);
    }

    /**
     * @param array<string, string> $params
     */
    public function createScan(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $projectId = (int) ($params['id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        if (!in_array((string) ($project['membership_role'] ?? ''), ['admin', 'editor'], true) && !in_array('admin', $user['roles'] ?? [], true)) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        $urls = preg_split('/\r\n|\r|\n/', (string) ($request->post['urls'] ?? '')) ?: [];

        try {
            $scanId = $this->scans->createScan(
                $projectId,
                (int) $user['id'],
                (string) ($request->post['root_domain'] ?? $project['root_domain']),
                $urls,
                (string) ($request->post['provider'] ?? 'moz')
            );
            $this->audit->log((int) $user['id'], 'scan.create', 'scan', (string) $scanId, ['project_id' => $projectId]);
            $this->flash('success', 'Scan queued');
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
        }

        return Response::redirect('/projects/' . $projectId);
    }

    /**
     * @param array<string, string> $params
     */
    public function showScan(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $scanId = (int) ($params['id'] ?? 0);
        $scan = $this->scans->findScan($scanId);
        if ($scan === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        $project = $this->projects->findForUser((int) $scan['project_id'], (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        $filters = [
            'status' => (string) ($request->query['status'] ?? ''),
            'link_type' => (string) ($request->query['link_type'] ?? ''),
            'search' => (string) ($request->query['search'] ?? ''),
            'sort' => (string) ($request->query['sort'] ?? 'id_desc'),
        ];

        $results = $this->scans->results($scanId, $filters);
        $trend = $this->scans->trendAgainstPrevious($scanId);

        return Response::html($this->render('scan', [
            'project' => $project,
            'scan' => $scan,
            'results' => $results,
            'trend' => $trend,
            'filters' => $filters,
            'flash' => $this->pullFlash(),
        ]));
    }

    /**
     * @param array<string, string> $params
     */
    public function cancelScan(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $scanId = (int) ($params['id'] ?? 0);
        $scan = $this->scans->findScan($scanId);
        if ($scan === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        $project = $this->projects->findForUser((int) $scan['project_id'], (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        $this->scans->cancelScan($scanId);
        $this->audit->log((int) $user['id'], 'scan.cancel', 'scan', (string) $scanId);
        $this->flash('success', $this->t('scan.cancelled'));

        return Response::redirect('/scans/' . $scanId);
    }

    /**
     * @param array<string, string> $params
     */
    public function exportScan(Request $request, array $params): Response
    {
        $user = $this->requireUser();

        $scanId = (int) ($params['scanId'] ?? 0);
        $format = strtolower((string) ($request->query['format'] ?? 'csv'));

        $scan = $this->scans->findScan($scanId);
        if ($scan === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        $project = $this->projects->findForUser((int) $scan['project_id'], (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        $exportId = $this->exports->requestExport((int) $scan['id'], (int) $project['id'], (int) $user['id'], $format);

        return Response::redirect('/exports/' . $exportId);
    }

    /**
     * @param array<string, string> $params
     */
    public function downloadExport(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $exportId = (int) ($params['id'] ?? 0);

        $export = $this->exports->findForProject($exportId, (int) ($request->query['project_id'] ?? 0));
        if ($export === null) {
            // Try by any project the user can access
            $export = $this->findAccessibleExport($exportId, (int) $user['id']);
        }

        $filePath = (string) ($export['file_path'] ?? '');
        if ($export === null || $filePath === '' || !is_file($filePath) || !$this->exports->isValidExportPath($filePath)) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        $mime = match ((string) $export['format']) {
            'json' => 'application/json; charset=utf-8',
            'txt' => 'text/plain; charset=utf-8',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv; charset=utf-8',
        };

        $filename = basename($filePath);

        return new Response(
            200,
            (string) file_get_contents($filePath),
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    /**
     * @param array<string, string> $params
     */
    public function saveView(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);
        $projectId = (int) ($params['id'] ?? 0);

        $this->savedViews->save(
            (int) $user['id'],
            $projectId,
            (string) ($request->post['name'] ?? 'Default View'),
            [
                'status' => (string) ($request->post['status'] ?? ''),
                'link_type' => (string) ($request->post['link_type'] ?? ''),
                'search' => (string) ($request->post['search'] ?? ''),
                'sort' => (string) ($request->post['sort'] ?? 'id_desc'),
            ]
        );

        $this->flash('success', 'Saved view added');
        return Response::redirect('/projects/' . $projectId);
    }

    /**
     * @param array<string, string> $params
     */
    public function createSchedule(Request $request, array $params): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $projectId = (int) ($params['id'] ?? 0);
        $project = $this->projects->findForUser($projectId, (int) $user['id']);
        if ($project === null) {
            return Response::html($this->renderError(404, $this->t('errors.not_found')), 404);
        }

        $targets = preg_split('/\r\n|\r|\n/', (string) ($request->post['targets'] ?? '')) ?: [];

        try {
            $this->schedules->create(
                $projectId,
                (int) $user['id'],
                (string) ($request->post['name'] ?? 'Scheduled Scan'),
                (string) ($request->post['root_domain'] ?? $project['root_domain']),
                $targets,
                (string) ($request->post['rrule'] ?? 'FREQ=HOURLY;INTERVAL=24'),
                (string) ($request->post['timezone'] ?? 'UTC')
            );
            $this->flash('success', 'Schedule created');
        } catch (ValidationException $e) {
            $this->flash('error', $e->getMessage());
        }

        return Response::redirect('/projects/' . $projectId);
    }

    public function createApiToken(Request $request): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        $name = trim((string) ($request->post['name'] ?? 'Default Token'));
        $scopesRaw = trim((string) ($request->post['scopes'] ?? 'scans:read'));
        $scopes = array_values(array_filter(array_map('trim', explode(',', $scopesRaw))));

        $token = $this->tokens->createToken((int) $user['id'], $name, $scopes);

        $_SESSION['new_token'] = $token['plain'];
        $this->flash('success', $this->t('api.token_created'));

        return Response::redirect('/dashboard');
    }

    public function saveSettings(Request $request): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);

        // Locale is per-user; telemetry and retention are global (admin-only)
        $locale = (string) ($request->post['locale'] ?? 'en-US');
        $telemetryEnabled = isset($request->post['telemetry']) && $request->post['telemetry'] === '1';
        $retentionDays = max(1, (int) ($request->post['retention_days'] ?? 90));

        if (in_array($locale, $this->translator->supported(), true)) {
            $this->auth->updateLocale((int) $user['id'], $locale);
            $_SESSION['user']['locale'] = $locale;
        }

        if ($this->isAdmin($user)) {
            $this->settings->set('telemetry.enabled', ['enabled' => $telemetryEnabled]);
            $this->settings->set('retention.days', ['days' => $retentionDays]);
        }

        $this->flash('success', $this->t('settings.saved'));

        return Response::redirect('/dashboard');
    }

    public function postUpdaterCheck(Request $request): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);
        if (!$this->isAdmin($user)) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        $jobId = $this->updater->enqueueCheck('web');
        if ($jobId === null) {
            $this->flash('error', $this->t('updater.job_pending'));
            return Response::redirect('/dashboard');
        }

        $this->audit->log((int) $user['id'], 'updater.check.requested', 'updater', (string) $jobId, [], $request->ip(), $request->userAgent());
        $this->flash('success', $this->t('updater.check_enqueued'));

        return Response::redirect('/dashboard');
    }

    public function postUpdaterApply(Request $request): Response
    {
        $user = $this->requireUser();
        $this->requireCsrf($request);
        if (!$this->isAdmin($user)) {
            return Response::html($this->renderError(403, $this->t('errors.forbidden')), 403);
        }

        $jobId = $this->updater->enqueueApply('web');
        if ($jobId === null) {
            $this->flash('error', $this->t('updater.job_pending'));
            return Response::redirect('/dashboard');
        }

        $this->audit->log((int) $user['id'], 'updater.apply.requested', 'updater', (string) $jobId, [], $request->ip(), $request->userAgent());
        $this->flash('success', $this->t('updater.apply_enqueued'));

        return Response::redirect('/dashboard');
    }

    private function render(string $view, array $data = []): string
    {
        $user = $this->session->user();
        $locale = (string) ($user['locale'] ?? 'en-US');
        $isRtl = $this->translator->isRtl($locale);

        return $this->views->render($view, array_merge($data, [
            't' => fn(string $key, array $params = []): string => $this->translator->trans($key, $params, $locale),
            'csrfToken' => $this->csrf->token(),
            'user' => $user,
            'locale' => $locale,
            'supportedLocales' => $this->translator->supported(),
            'isRtl' => $isRtl,
            'newToken' => $_SESSION['new_token'] ?? null,
        ]));
    }

    private function renderError(int $status, string $message): string
    {
        return $this->views->render('error', [
            'status' => $status,
            'message' => $message,
            'csrfToken' => $this->csrf->token(),
            'user' => $this->session->user(),
            'locale' => (string) (($this->session->user()['locale'] ?? 'en-US')),
            'supportedLocales' => $this->translator->supported(),
            'isRtl' => $this->translator->isRtl((string) (($this->session->user()['locale'] ?? 'en-US'))),
            't' => fn(string $key, array $params = []): string => $this->translator->trans($key, $params, (string) (($this->session->user()['locale'] ?? 'en-US'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function requireUser(): array
    {
        $user = $this->session->user();
        if ($user === null) {
            throw new \RuntimeException('Authentication required');
        }

        return $user;
    }

    private function requireCsrf(Request $request): void
    {
        $token = (string) ($request->post['_csrf'] ?? '');
        if (!$this->csrf->validate($token)) {
            throw new \RuntimeException($this->t('errors.csrf'));
        }
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * @return array<string, string>|null
     */
    private function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        if (!is_array($flash)) {
            return null;
        }

        return [
            'type' => (string) ($flash['type'] ?? 'info'),
            'message' => (string) ($flash['message'] ?? ''),
        ];
    }

    private function t(string $key, array $params = []): string
    {
        $locale = (string) (($this->session->user()['locale'] ?? 'en-US'));

        return $this->translator->trans($key, $params, $locale);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isAdmin(array $user): bool
    {
        $roles = $user['roles'] ?? [];
        if (!is_array($roles)) {
            return false;
        }

        return in_array('admin', $roles, true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAccessibleExport(int $exportId, int $userId): ?array
    {
        $export = $this->db->fetchOne(
            'SELECT e.* FROM exports e JOIN project_members pm ON pm.project_id = e.project_id WHERE e.id = ? AND pm.user_id = ?',
            [$exportId, $userId]
        );

        return $export;
    }
}
