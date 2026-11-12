<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\IndexNow\Models\IndexNowClient;
use Osmium\Services\IndexNow\Models\IndexNowKeyFile;
use Osmium\Services\IndexNow\Models\IndexNowLog;
use Osmium\Services\IndexNow\Models\IndexNowLogRows;
use Osmium\Services\IndexNow\Models\IndexNowSettings;
use Osmium\Services\IndexNow\Models\IndexNowSite;
use Osmium\Services\IndexNow\Models\IndexNowStatus;
use Osmium\Services\IndexNow\Models\IndexNowSubmitter;

/**
 * IndexNow: one switch, a status line, and an Advanced section - a full-page form POST/redirect flow.
 *
 * Routes:
 *   - index() → /admin/indexnow/  (GET shows the page; POST turns it on or off, resends every page or makes a new key)
 */
class IndexNowController extends AdminController
{
    public function index(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost) $this->handlePost();

        $settings = IndexNowSettings::get();
        $site = IndexNowSite::fromSite($this->osmium->config->site);
        $log = new IndexNowLog($this->osmium->dataSource);

        $this->data['admin']['settings'] = $settings;
        $this->data['admin']['status'] = IndexNowStatus::describe($settings, $site->isLive);
        $this->data['admin']['resendWait'] = IndexNowSettings::submitAllWait($settings['lastSubmitAll'], \time());
        $this->data['admin']['keyUrl'] = $settings['key'] === '' ? '' : $site->baseUrl . '/' . $settings['key'] . '.txt';
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/indexnow.php';
        $this->data['admin']['log'] = IndexNowLogRows::present($log->recent());
        $this->data['admin']['message'] = $_SESSION['indexnow_message'] ?? null;
        $this->data['admin']['error'] = $_SESSION['indexnow_error'] ?? null;
        unset($_SESSION['indexnow_message'], $_SESSION['indexnow_error']);

        $this->setView('indexnow/index.phtml');
    }

    private function handlePost(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) $this->finish(error: 'Invalid form submission. Please try again.');

        $message = match ((string) ($_POST['intent'] ?? '')) {
            'enable' => $this->enable(),
            'disable' => $this->disable(),
            'resend' => $this->resend(),
            'new_key' => $this->newKey(),
            default => null,
        };

        $this->finish(message: $message, error: $message === null ? 'Unknown action.' : null);
    }

    private function enable(): string
    {
        $settings = IndexNowSettings::get();
        $settings['key'] = $settings['key'] === '' ? IndexNowSettings::newKey() : $settings['key'];
        $settings['enabled'] = true;

        $this->writeKeyFile($settings['key']);
        IndexNowSettings::save($settings);
        $this->admin->model->changelog->log(description: 'Turned IndexNow on', recordType: 'settings');

        $isLive = IndexNowSite::fromSite($this->osmium->config->site)->isLive;
        $firstTime = $settings['lastSubmitAll'] === 0;
        if ($isLive && $firstTime) return $this->sendAll($settings);

        return 'IndexNow is on.';
    }

    private function disable(): string
    {
        $settings = IndexNowSettings::get();
        $settings['enabled'] = false;

        IndexNowSettings::save($settings);
        $this->admin->model->changelog->log(description: 'Turned IndexNow off', recordType: 'settings');

        return 'IndexNow is off.';
    }

    private function resend(): string
    {
        $settings = IndexNowSettings::get();
        $wait = IndexNowSettings::submitAllWait($settings['lastSubmitAll'], \time());
        $coolingDown = $wait > 0;
        if ($coolingDown) $this->finish(error: 'All pages were sent in the last 24 hours. Try again in ' . \ceil($wait / 3600) . ' hours.');

        return $this->sendAll($settings);
    }

    private function newKey(): string
    {
        $settings = IndexNowSettings::get();
        $oldKey = $settings['key'];
        $settings['key'] = IndexNowSettings::newKey();

        $this->writeKeyFile($settings['key']);
        IndexNowKeyFile::remove(documentRoot: $this->documentRoot(), key: $oldKey);
        IndexNowSettings::save($settings);
        $this->admin->model->changelog->log(description: 'Made a new IndexNow key', recordType: 'settings');

        return 'New key made and its file written.';
    }

    /** @param array{enabled: bool, key: string, lastSubmitAll: int, lastSubmitAllCount: int} $settings */
    private function sendAll(array $settings): string
    {
        $site = IndexNowSite::fromSite($this->osmium->config->site);
        $redirected = $this->osmium->model->redirect->activePaths();
        $pages = $this->osmium->model->page->getSitemap(mode: 'indexable', redirectedPaths: $redirected);
        $urls = \array_map(fn(array $page): string => $site->pageUrl((string) $page['url']), $pages);

        $submitter = new IndexNowSubmitter(
            site: $site,
            log: new IndexNowLog($this->osmium->dataSource),
            client: new IndexNowClient(),
            documentRoot: $this->documentRoot(),
            settings: $settings,
        );
        $httpCode = $submitter->submit(urls: $urls, reason: 'all pages');

        $sent = $httpCode === 200 || $httpCode === 202;
        if (!$sent) $this->finish(error: $this->notSentMessage($httpCode));

        $settings['lastSubmitAll'] = \time();
        $settings['lastSubmitAllCount'] = \count($urls);
        IndexNowSettings::save($settings);
        $this->admin->model->changelog->log(description: 'Sent all pages to IndexNow', recordType: 'settings');

        return \count($urls) . ' pages sent to IndexNow.';
    }

    private function notSentMessage(int $httpCode): string
    {
        $nothingSent = $httpCode === 0;
        if ($nothingSent) return 'Nothing was sent. This must be the live site and IndexNow must be reachable.';

        return 'IndexNow answered ' . $httpCode . ': ' . IndexNowClient::describe($httpCode) . '.';
    }

    private function writeKeyFile(string $key): void
    {
        $written = IndexNowKeyFile::ensure(documentRoot: $this->documentRoot(), key: $key);
        if (!$written) $this->finish(error: 'Could not write the key file to the web root. Check the folder is writable.');
    }

    private function finish(?string $message = null, ?string $error = null): never
    {
        if ($message !== null) $_SESSION['indexnow_message'] = $message;
        if ($error !== null) $_SESSION['indexnow_error'] = $error;

        $this->redirect('indexnow/');
        exit;
    }

    private function documentRoot(): string
    {
        return (string) $_SERVER['DOCUMENT_ROOT'];
    }
}
