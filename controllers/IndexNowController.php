<?php

declare(strict_types=1);

namespace Osmium\Services\IndexNow\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\IndexNow\Models\IndexNowClient;
use Osmium\Services\IndexNow\Models\IndexNowKeyFile;
use Osmium\Services\IndexNow\Models\IndexNowLog;
use Osmium\Services\IndexNow\Models\IndexNowSettings;
use Osmium\Services\IndexNow\Models\IndexNowSite;
use Osmium\Services\IndexNow\Models\IndexNowSubmitter;

/**
 * IndexNow settings and history - a full-page form POST/redirect flow, matching the Clarity service.
 *
 * Routes:
 *   - index() → /admin/indexnow/  (GET shows the page, POST saves, makes a new key or submits every page)
 */
class IndexNowController extends AdminController
{
    public function index(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost) $this->handlePost();

        $settings = IndexNowSettings::get();
        $site = IndexNowSite::fromSite($this->osmium->config->site);

        $this->data['admin']['settings'] = $settings;
        $this->data['admin']['isLive'] = $site->isLive;
        $this->data['admin']['keyUrl'] = $settings['key'] === '' ? '' : $site->baseUrl . '/' . $settings['key'] . '.txt';
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/indexnow.php';
        $this->data['admin']['log'] = (new IndexNowLog($this->osmium->dataSource))->recent();
        $this->data['admin']['message'] = $_SESSION['indexnow_message'] ?? null;
        $this->data['admin']['error'] = $_SESSION['indexnow_error'] ?? null;
        unset($_SESSION['indexnow_message'], $_SESSION['indexnow_error']);

        $this->setView('indexnow/index.phtml');
    }

    private function handlePost(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) $this->finish(error: 'Invalid form submission. Please try again.');

        $intent = (string) ($_POST['intent'] ?? 'save');

        $message = match ($intent) {
            'save' => $this->save(isset($_POST['enabled'])),
            'new_key' => $this->newKey(),
            'submit_all' => $this->submitAll(),
            default => null,
        };

        $this->finish(message: $message, error: $message === null ? 'Unknown action.' : null);
    }

    private function save(bool $enabled): string
    {
        $settings = IndexNowSettings::get();
        $key = $settings['key'] === '' ? IndexNowSettings::newKey() : $settings['key'];

        $keyFileWritten = IndexNowKeyFile::ensure(documentRoot: $this->documentRoot(), key: $key);
        if (!$keyFileWritten) $this->finish(error: 'Could not write the key file to the web root. Check the folder is writable.');

        IndexNowSettings::save(enabled: $enabled, key: $key);
        $this->admin->model->changelog->log(description: 'Updated IndexNow settings', recordType: 'settings');

        return $enabled ? 'Saved. IndexNow is on.' : 'Saved. IndexNow is off.';
    }

    private function newKey(): string
    {
        $settings = IndexNowSettings::get();
        $newKey = IndexNowSettings::newKey();

        $keyFileWritten = IndexNowKeyFile::ensure(documentRoot: $this->documentRoot(), key: $newKey);
        if (!$keyFileWritten) $this->finish(error: 'Could not write the key file to the web root. Check the folder is writable.');

        IndexNowKeyFile::remove(documentRoot: $this->documentRoot(), key: $settings['key']);
        IndexNowSettings::save(enabled: $settings['enabled'], key: $newKey);
        $this->admin->model->changelog->log(description: 'Made a new IndexNow key', recordType: 'settings');

        return 'New key made and its file written.';
    }

    private function submitAll(): string
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
            settings: IndexNowSettings::get(),
        );
        $httpCode = $submitter->submit(urls: $urls, reason: 'all pages');
        $this->admin->model->changelog->log(description: 'Submitted all pages to IndexNow', recordType: 'settings');

        $sent = $httpCode === 200 || $httpCode === 202;
        if (!$sent) $this->finish(error: $this->notSentMessage($httpCode));

        return \count($urls) . ' pages sent. ' . IndexNowClient::describe($httpCode) . '.';
    }

    private function notSentMessage(int $httpCode): string
    {
        $nothingSent = $httpCode === 0;
        if ($nothingSent) return 'Nothing was sent. IndexNow must be on, this must be the live site, and IndexNow must be reachable.';

        return 'IndexNow answered ' . $httpCode . ': ' . IndexNowClient::describe($httpCode) . '.';
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
