<?php
namespace App\Controllers;

use Core\BaseController;
use Core\File;
use Core\Error;
use Core\ErrorHandler;
use App\Tools\SimpleTemplater;
use App\Tools\SimpleMarkdownHtml;

class HtmlController extends BaseController {
    // The File class
    private File $file;

    // The templater
    private SimpleTemplater $templater;

    // The markdown to html converter
    private SimpleMarkdownHtml $mdToHtml;

    // Loaded static pages indexed by slug
    private array $pages = [];

    // Menu markup snippet
    private string $menu = '';

    /**
     * Handles HTML content requests.
     *
     * @param array $request The routing data package.
     * @return bool True if handled; otherwise false.
     */
    public function handle($request): bool {
        $this->file = $this->container->get(File::class);
        $this->templater = $this->container->get(SimpleTemplater::class);
        $this->mdToHtml = $this->container->get(SimpleMarkdownHtml::class);

        $this->templater->setTemplate($this->file->read('app/assets/template.html'));

        // assign defaults
        $this->templater->assign('title', 'Delegating App Base');
        $this->templater->assign('description', '');

        // load menu and pages
        $this->loadPages();
        $this->templater->assign('menu', $this->menu);

        try {
            $this->delegateRoute('/readme!', [$this, 'displayReadme'], $request);
            $this->delegateRoute('/license!', [$this, 'displayLicense'], $request);

            foreach ($this->pages as $slug => $page) {
                $this->delegateRoute($slug . '!', function($req) use ($page) {
                    return $this->displayPage($page);
                }, $request);
            }
            
            $path = $request['route'];
            throw new Error(
                'user',
                "Asset not found",
                "Asset {$path} not found",
                ['path' => $path],
                404
            );
        } catch (Error $error) {
            if($error->getBlame() === 'user'){
                $this->displayError($error);
            } else {
                $eh = $this->container->get(ErrorHandler::class);
                $eh->handleException($error, 'html_system');
            }
            
        }
        return true;
    }

    public function displayReadme($request): bool {
        $readme = $this->file->read('README.md');
        $readmeHtml = $this->mdToHtml->parse($readme);
        $this->templater->assign('title', 'README');
        $this->templater->assign('description', '');
        $this->templater->assign('content', $readmeHtml);

        header('Content-Type: text/html; charset=UTF-8');
        echo $this->templater->render();
        return true;
    }

    public function displayLicense($request): bool {
        $license = $this->file->read('LICENSE.md');
        $licenseHtml = $this->mdToHtml->parse($license);
        $this->templater->assign('title', 'License');
        $this->templater->assign('description', '');
        $this->templater->assign('content', $licenseHtml);

        header('Content-Type: text/html; charset=UTF-8');
        echo $this->templater->render();
        return true;
    }

    public function displayError($error): never{
        $errorHtml = "";
        $this->templater->assign('title', 'Error');
        $this->templater->assign('description', '');
        $this->templater->assign('content', $errorHtml);

        header('Content-Type: text/html; charset=UTF-8');
        http_response_code($error->getHttpCode());
        echo $this->templater->render();
        die();
    }

    private function loadPages(): void {
        $files = $this->file->listDirectory('app/pages');
        foreach ($files as $name) {
            if (pathinfo($name, PATHINFO_EXTENSION) !== 'html') {
                continue;
            }
            if ($name === 'menu.html') {
                $this->menu = $this->file->read('app/pages/' . $name);
                continue;
            }
            $contents = $this->file->read('app/pages/' . $name);
            if (preg_match('/^---\s*(.*?)\s*---\s*(.*)$/s', $contents, $matches)) {
                $meta = $this->parseFrontMatter($matches[1]);
                $slug = $meta['slug'] ?? '/' . pathinfo($name, PATHINFO_FILENAME);
                $this->pages[$slug] = [
                    'title' => $meta['title'] ?? 'Delegating App Base',
                    'description' => $meta['description'] ?? '',
                    'html' => $matches[2]
                ];
            }
        }
    }

    private function parseFrontMatter(string $text): array {
        $data = [];
        $lines = preg_split('/\r?\n/', trim($text));
        foreach ($lines as $line) {
            if (preg_match('/^([A-Za-z0-9_-]+):\s*"?(.*?)"?$/', $line, $m)) {
                $data[$m[1]] = $m[2];
            }
        }
        return $data;
    }

    private function displayPage(array $page): bool {
        $this->templater->assign('title', $page['title']);
        $this->templater->assign('description', $page['description']);
        $this->templater->assign('content', $page['html']);

        header('Content-Type: text/html; charset=UTF-8');
        echo $this->templater->render();
        return true;
    }
}