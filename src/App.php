<?php

declare(strict_types=1);

namespace BacklinkChecker;

use BacklinkChecker\Config\Config;
use BacklinkChecker\Config\EnvLoader;
use BacklinkChecker\Controllers\ApiController;
use BacklinkChecker\Controllers\WebController;
use BacklinkChecker\Database\Database;
use BacklinkChecker\Database\Migrator;
use BacklinkChecker\Domain\Url\LinkClassifier;
use BacklinkChecker\Domain\Url\UrlNormalizer;
use BacklinkChecker\Http\RateLimiter;
use BacklinkChecker\Http\Request;
use BacklinkChecker\Http\Responder;
use BacklinkChecker\Http\Response;
use BacklinkChecker\Http\Router;
use BacklinkChecker\Http\SessionManager;
use BacklinkChecker\I18n\LocaleDetector;
use BacklinkChecker\I18n\Translator;
use BacklinkChecker\Logging\JsonLogger;
use BacklinkChecker\Providers\AhrefsMetricsProvider;
use BacklinkChecker\Providers\MajesticMetricsProvider;
use BacklinkChecker\Providers\MozMetricsProvider;
use BacklinkChecker\Providers\SemrushMetricsProvider;
use BacklinkChecker\Security\Csrf;
use BacklinkChecker\Security\PasswordHasher;
use BacklinkChecker\Security\TokenService;
use BacklinkChecker\Services\ActivityService;
use BacklinkChecker\Services\AnchorAnalysisService;
use BacklinkChecker\Services\AuditService;
use BacklinkChecker\Services\AuthService;
use BacklinkChecker\Services\BacklinkAnalyzerService;
use BacklinkChecker\Services\CompetitorService;
use BacklinkChecker\Services\DisavowService;
use BacklinkChecker\Services\ExportService;
use BacklinkChecker\Services\HealthScoreService;
use BacklinkChecker\Services\HttpClient;
use BacklinkChecker\Services\ImportService;
use BacklinkChecker\Services\NotificationService;
use BacklinkChecker\Services\ProjectService;
use BacklinkChecker\Services\ProviderCacheService;
use BacklinkChecker\Services\QueueService;
use BacklinkChecker\Services\ReportService;
use BacklinkChecker\Services\RetentionService;
use BacklinkChecker\Services\SavedViewService;
use BacklinkChecker\Services\ScanService;
use BacklinkChecker\Services\ScheduleService;
use BacklinkChecker\Services\SettingsService;
use BacklinkChecker\Services\TelemetryService;
use BacklinkChecker\Services\TokenAuthService;
use BacklinkChecker\Services\TwoFactorService;
use BacklinkChecker\Services\UpdaterService;
use BacklinkChecker\Services\VelocityService;
use BacklinkChecker\Services\WebhookService;
use BacklinkChecker\Support\ViewRenderer;

final class App
{
    private Config $config;
    private Database $db;
    private JsonLogger $logger;
    private SessionManager $session;
    private Router $router;
    private Responder $responder;
    private RateLimiter $rateLimiter;
    private WebController $web;
    private ApiController $api;
    private ScanService $scanService;
    private QueueService $queue;
    private ScheduleService $scheduleService;
    private WebhookService $webhookService;
    private RetentionService $retentionService;
    private UpdaterService $updaterService;
    private Translator $translator;
    private ReportService $reportService;
    private HealthScoreService $healthScoreService;
    private VelocityService $velocityService;
    private AnchorAnalysisService $anchorAnalysisService;

    public function __construct(private readonly string $rootPath)
    {
        EnvLoader::load($this->rootPath);
        $this->config = Config::build($this->rootPath);
        date_default_timezone_set($this->config->string('APP_TIMEZONE', 'UTC'));

        $this->logger = new JsonLogger($this->config->string('LOG_ABSOLUTE_PATH'));
        $this->db = new Database($this->config->string('DB_ABSOLUTE_PATH'));

        $migrator = new Migrator($this->db, $this->rootPath . '/migrations', $this->logger);
        $migrator->migrate();

        $this->session = new SessionManager($this->config);
        $this->session->start();
        $this->rateLimiter = new RateLimiter($this->db);

        $passwordHasher = new PasswordHasher();
        $csrf = new Csrf();
        $auth = new AuthService($this->db, $passwordHasher, $this->config);
        $auth->bootstrapDefaults();

        $normalizer = new UrlNormalizer();
        $http = new HttpClient($this->config);
        $providerCache = new ProviderCacheService($this->db);
        $mozProvider = new MozMetricsProvider($this->config, $http, $providerCache);
        $ahrefsProvider = new AhrefsMetricsProvider($this->config, $http, $providerCache);
        $semrushProvider = new SemrushMetricsProvider($this->config, $http, $providerCache);
        $majesticProvider = new MajesticMetricsProvider($this->config, $http, $providerCache);

        $queue = new QueueService($this->db, $this->config);
        $settings = new SettingsService($this->db);
        $telemetry = new TelemetryService($this->config, $this->db);
        $audit = new AuditService($this->db);
        $updater = new UpdaterService($this->config, $this->db, $settings, $queue, $this->logger, $this->rootPath);

        $projectService = new ProjectService($this->db, $normalizer);
        $savedViews = new SavedViewService($this->db);
        $scheduleService = new ScheduleService($this->db);
        $notificationService = new NotificationService($this->db, $this->config, $http, $queue);

        $analyzer = new BacklinkAnalyzerService($http, $normalizer, new LinkClassifier());

        $scanService = new ScanService(
            $this->db,
            $this->config,
            $normalizer,
            $queue,
            $analyzer,
            $mozProvider,
            $notificationService,
            $telemetry
        );

        $exports = new ExportService($this->db, $this->config);
        $tokenService = new TokenService($this->db);
        $tokenAuth = new TokenAuthService($tokenService, $auth);

        $webhookService = new WebhookService($http, $this->db, $this->config);

        // New feature services
        $competitorService = new CompetitorService($this->db);
        $healthScoreService = new HealthScoreService($this->db);
        $disavowService = new DisavowService($this->db);
        $anchorAnalysisService = new AnchorAnalysisService($this->db);
        $velocityService = new VelocityService($this->db);
        $importService = new ImportService($normalizer, $scanService);
        $reportService = new ReportService($this->db, $this->config);
        $twoFactorService = new TwoFactorService($this->db);
        $activityService = new ActivityService($this->db);

        $localeDetector = new LocaleDetector();
        $supportedLocales = [
            'en-US', 'tr-TR', 'es-ES', 'fr-FR', 'de-DE', 'it-IT', 'pt-BR', 'nl-NL', 'ru-RU', 'ar-SA',
        ];

        $detectedLocale = $localeDetector->detect(
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
            $supportedLocales,
            $this->config->string('APP_DEFAULT_LOCALE', 'en-US')
        );

        if ($this->session->user() === null && empty($_SESSION['guest_locale'])) {
            $_SESSION['guest_locale'] = $detectedLocale;
        }

        $translator = new Translator(
            $this->rootPath . '/resources/lang',
            $supportedLocales,
            $this->config->string('APP_FALLBACK_LOCALE', 'en-US')
        );

        $viewRenderer = new ViewRenderer($this->rootPath . '/templates');

        $this->web = new WebController(
            $this->db,
            $this->session,
            $auth,
            $projectService,
            $scanService,
            $exports,
            $savedViews,
            $scheduleService,
            $settings,
            $audit,
            $tokenService,
            $csrf,
            $translator,
            $viewRenderer,
            $updater,
            $competitorService,
            $healthScoreService,
            $disavowService,
            $anchorAnalysisService,
            $velocityService,
            $importService,
            $reportService,
            $twoFactorService,
            $activityService
        );

        $this->api = new ApiController(
            $auth,
            $tokenService,
            $tokenAuth,
            $projectService,
            $scanService,
            $scheduleService,
            $exports,
            $webhookService,
            $this->db,
            $competitorService,
            $healthScoreService,
            $disavowService,
            $anchorAnalysisService,
            $velocityService,
            $importService
        );

        $this->router = new Router();
        $this->registerRoutes();

        $this->responder = new Responder();

        $this->scanService = $scanService;
        $this->queue = $queue;
        $this->scheduleService = $scheduleService;
        $this->webhookService = $webhookService;
        $this->retentionService = new RetentionService($this->db, $this->config);
        $this->updaterService = $updater;
        $this->translator = $translator;
        $this->reportService = $reportService;
        $this->healthScoreService = $healthScoreService;
        $this->velocityService = $velocityService;
        $this->anchorAnalysisService = $anchorAnalysisService;
    }

    public function run(?Request $request = null): void
    {
        $request = $request ?? Request::fromGlobals();
        $correlationId = $request->header('X-Correlation-Id') ?: \BacklinkChecker\Support\Uuid::v4();

        if ($this->config->bool('SECURITY_FORCE_HTTPS', false)
            && ($request->server['HTTPS'] ?? 'off') !== 'on'
            && php_sapi_name() !== 'cli-server') {
            $this->responder->emit(Response::redirect('https://' . $request->host() . $request->path(), 301), $this->securityHeaders($correlationId));
            return;
        }

        try {
            $isApiLogin = $request->path() === '/api/v1/auth/login' && $request->method() === 'POST';

            if (str_starts_with($request->path(), '/api/')) {
                if ($isApiLogin) {
                    if (!$this->rateLimiter->hit('login:' . $request->ip(), $this->config->int('RATE_LIMIT_LOGIN_PER_15_MIN', 10), 900)) {
                        $this->emitJsonError('rate_limited', 'Too many login attempts', 429, $correlationId);
                        return;
                    }
                } else {
                    if (!$this->rateLimiter->hit('api:' . $request->ip(), $this->config->int('RATE_LIMIT_API_PER_MIN', 120), 60)) {
                        $this->emitJsonError('rate_limited', 'Too many API requests', 429, $correlationId);
                        return;
                    }
                }
            }

            if ($request->path() === '/login' && $request->method() === 'POST') {
                if (!$this->rateLimiter->hit('login:' . $request->ip(), $this->config->int('RATE_LIMIT_LOGIN_PER_15_MIN', 10), 900)) {
                    $this->responder->emit(Response::html('<h1>Too many login attempts</h1>', 429), $this->securityHeaders($correlationId));
                    return;
                }
            }

            $matched = $this->router->match($request);
            if ($matched === null) {
                $response = Response::html('<h1>Not Found</h1>', 404);
                $this->responder->emit($response, $this->securityHeaders($correlationId));
                return;
            }

            $handler = $matched['handler'];
            $params = $matched['params'];
            $response = $handler($request, $params);

            $this->responder->emit($response, $this->securityHeaders($correlationId));
        } catch (\RuntimeException $e) {
            if (str_starts_with($request->path(), '/api/')) {
                $isAuth = $e->getMessage() === 'Unauthorized' || $e->getMessage() === 'Authentication required';
                $code = $isAuth ? 401 : 400;
                $label = $isAuth ? 'unauthorized' : 'runtime_error';
                $msg = $isAuth ? $e->getMessage() : 'Request failed';
                $this->emitJsonError($label, $msg, $code, $correlationId);
                return;
            }

            if ($e->getMessage() === 'Authentication required') {
                $this->responder->emit(Response::redirect('/login'), $this->securityHeaders($correlationId));
                return;
            }

            $this->responder->emit(
                Response::html('<h1>Error</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>', 400),
                $this->securityHeaders($correlationId)
            );
        } catch (\Throwable $e) {
            $this->logger->error('request_failed', [
                'message' => $e->getMessage(),
                'trace' => $this->config->bool('APP_DEBUG', false) ? $e->getTraceAsString() : null,
                'path' => $request->path(),
                'correlation_id' => $correlationId,
            ]);

            if (str_starts_with($request->path(), '/api/')) {
                $this->emitJsonError('server_error', 'Unexpected server error', 500, $correlationId);
                return;
            }

            $message = $this->config->bool('APP_DEBUG', false) ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') : 'Unexpected server error';
            $this->responder->emit(Response::html('<h1>Error</h1><p>' . $message . '</p>', 500), $this->securityHeaders($correlationId));
        }
    }

    public function queue(): QueueService
    {
        return $this->queue;
    }

    public function scans(): ScanService
    {
        return $this->scanService;
    }

    public function schedules(): ScheduleService
    {
        return $this->scheduleService;
    }

    public function webhooks(): WebhookService
    {
        return $this->webhookService;
    }

    public function retention(): RetentionService
    {
        return $this->retentionService;
    }

    public function db(): Database
    {
        return $this->db;
    }

    public function logger(): JsonLogger
    {
        return $this->logger;
    }

    public function translator(): Translator
    {
        return $this->translator;
    }

    public function updater(): UpdaterService
    {
        return $this->updaterService;
    }

    public function reports(): ReportService
    {
        return $this->reportService;
    }

    public function healthScore(): HealthScoreService
    {
        return $this->healthScoreService;
    }

    public function velocity(): VelocityService
    {
        return $this->velocityService;
    }

    public function anchorAnalysis(): AnchorAnalysisService
    {
        return $this->anchorAnalysisService;
    }

    private function registerRoutes(): void
    {
        // Web routes
        $this->router->add('GET', '/', fn(Request $r, array $p = []) => $this->web->home($r));
        $this->router->add('GET', '/index.php', fn(Request $r, array $p = []) => $this->web->home($r));
        $this->router->add('POST', '/index.php', fn(Request $r, array $p = []) => $this->web->home($r));
        $this->router->add('GET', '/login', fn(Request $r, array $p = []) => $this->web->login($r));
        $this->router->add('POST', '/login', fn(Request $r, array $p = []) => $this->web->login($r));
        $this->router->add('POST', '/logout', fn(Request $r, array $p = []) => $this->web->logout($r));
        $this->router->add('GET', '/dashboard', fn(Request $r, array $p = []) => $this->web->dashboard($r));
        $this->router->add('POST', '/projects', fn(Request $r, array $p = []) => $this->web->createProject($r));
        $this->router->add('GET', '/projects/{id}', fn(Request $r, array $p) => $this->web->showProject($r, $p));
        $this->router->add('POST', '/projects/{id}/members', fn(Request $r, array $p) => $this->web->addProjectMember($r, $p));
        $this->router->add('POST', '/projects/{id}/notifications', fn(Request $r, array $p) => $this->web->addProjectNotification($r, $p));
        $this->router->add('POST', '/projects/{id}/scans', fn(Request $r, array $p) => $this->web->createScan($r, $p));
        $this->router->add('POST', '/projects/{id}/saved-views', fn(Request $r, array $p) => $this->web->saveView($r, $p));
        $this->router->add('POST', '/projects/{id}/schedules', fn(Request $r, array $p) => $this->web->createSchedule($r, $p));
        $this->router->add('GET', '/scans/{id}', fn(Request $r, array $p) => $this->web->showScan($r, $p));
        $this->router->add('POST', '/scans/{id}/cancel', fn(Request $r, array $p) => $this->web->cancelScan($r, $p));
        $this->router->add('GET', '/scans/{scanId}/export', fn(Request $r, array $p) => $this->web->exportScan($r, $p));
        $this->router->add('GET', '/exports/{id}', fn(Request $r, array $p) => $this->web->downloadExport($r, $p));
        $this->router->add('POST', '/settings', fn(Request $r, array $p = []) => $this->web->saveSettings($r));
        $this->router->add('POST', '/settings/updater/check', fn(Request $r, array $p = []) => $this->web->postUpdaterCheck($r));
        $this->router->add('POST', '/settings/updater/apply', fn(Request $r, array $p = []) => $this->web->postUpdaterApply($r));
        $this->router->add('POST', '/api-tokens', fn(Request $r, array $p = []) => $this->web->createApiToken($r));

        // Competitor routes
        $this->router->add('POST', '/projects/{id}/competitors', fn(Request $r, array $p) => $this->web->addCompetitor($r, $p));
        $this->router->add('POST', '/projects/{id}/competitors/delete', fn(Request $r, array $p) => $this->web->removeCompetitor($r, $p));

        // Disavow routes
        $this->router->add('POST', '/projects/{id}/disavow', fn(Request $r, array $p) => $this->web->addDisavowRule($r, $p));
        $this->router->add('POST', '/projects/{id}/disavow/delete', fn(Request $r, array $p) => $this->web->removeDisavowRule($r, $p));
        $this->router->add('GET', '/projects/{id}/disavow/download', fn(Request $r, array $p) => $this->web->downloadDisavowFile($r, $p));

        // Import route
        $this->router->add('POST', '/projects/{id}/import', fn(Request $r, array $p) => $this->web->importUrls($r, $p));

        // Report routes
        $this->router->add('POST', '/projects/{id}/reports', fn(Request $r, array $p) => $this->web->createReport($r, $p));

        // 2FA routes
        $this->router->add('POST', '/settings/2fa/enable', fn(Request $r, array $p = []) => $this->web->enable2fa($r));
        $this->router->add('POST', '/settings/2fa/disable', fn(Request $r, array $p = []) => $this->web->disable2fa($r));

        // Activity log route
        $this->router->add('GET', '/activity', fn(Request $r, array $p = []) => $this->web->activityLog($r));

        // API routes
        $this->router->add('POST', '/api/v1/auth/login', fn(Request $r, array $p = []) => $this->api->login($r));
        $this->router->add('POST', '/api/v1/projects', fn(Request $r, array $p = []) => $this->api->createProject($r));
        $this->router->add('POST', '/api/v1/scans', fn(Request $r, array $p = []) => $this->api->createScan($r));
        $this->router->add('GET', '/api/v1/scans/{scanId}', fn(Request $r, array $p) => $this->api->showScan($r, $p));
        $this->router->add('GET', '/api/v1/scans/{scanId}/results', fn(Request $r, array $p) => $this->api->scanResults($r, $p));
        $this->router->add('POST', '/api/v1/scans/{scanId}/cancel', fn(Request $r, array $p) => $this->api->cancelScan($r, $p));
        $this->router->add('POST', '/api/v1/schedules', fn(Request $r, array $p = []) => $this->api->createSchedule($r));
        $this->router->add('GET', '/api/v1/exports/{exportId}', fn(Request $r, array $p) => $this->api->downloadExport($r, $p));
        $this->router->add('POST', '/api/v1/webhooks/test', fn(Request $r, array $p = []) => $this->api->testWebhook($r));

        // API v2 routes
        $this->router->add('GET', '/api/v2/scans/{scanId}', fn(Request $r, array $p) => $this->api->showScanV2($r, $p));
        $this->router->add('GET', '/api/v2/scans/{scanId}/results', fn(Request $r, array $p) => $this->api->scanResultsV2($r, $p));
        $this->router->add('GET', '/api/v2/scans/{scanId}/health', fn(Request $r, array $p) => $this->api->scanHealth($r, $p));
        $this->router->add('GET', '/api/v2/scans/{scanId}/anchors', fn(Request $r, array $p) => $this->api->scanAnchors($r, $p));
        $this->router->add('GET', '/api/v2/scans/{scanId}/velocity', fn(Request $r, array $p) => $this->api->scanVelocity($r, $p));
        $this->router->add('GET', '/api/v2/projects/{projectId}/competitors', fn(Request $r, array $p) => $this->api->listCompetitors($r, $p));
        $this->router->add('POST', '/api/v2/projects/{projectId}/competitors', fn(Request $r, array $p) => $this->api->addCompetitor($r, $p));
        $this->router->add('GET', '/api/v2/projects/{projectId}/disavow', fn(Request $r, array $p) => $this->api->listDisavow($r, $p));
        $this->router->add('POST', '/api/v2/projects/{projectId}/disavow', fn(Request $r, array $p) => $this->api->addDisavow($r, $p));
        $this->router->add('POST', '/api/v2/projects/{projectId}/import', fn(Request $r, array $p) => $this->api->importUrls($r, $p));

        // Operational route
        $this->router->add('GET', '/health', fn(Request $r, array $p = []) => Response::json(['status' => 'ok', 'time' => gmdate('c')]));
    }

    /**
     * @return array<string, string>
     */
    private function securityHeaders(string $correlationId): array
    {
        return [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self';",
            'X-Correlation-Id' => $correlationId,
        ];
    }

    private function emitJsonError(string $code, string $message, int $status, string $correlationId): void
    {
        $this->responder->emit(
            Response::json([
                'error' => [
                    'code' => $code,
                    'message' => $message,
                    'correlation_id' => $correlationId,
                ],
            ], $status),
            $this->securityHeaders($correlationId)
        );
    }
}
