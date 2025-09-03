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

        try {
            $this->delegateRoute('/!', [$this, 'displayReadme'], $request);
            $this->delegateRoute('/license!', [$this, 'displayLicense'], $request);
            
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
        $this->templater->assign('content', $readmeHtml);

        header('Content-Type: text/html; charset=UTF-8');
        echo $this->templater->render();
        return true;
    }

    public function displayLicense($request): bool {
        $license = $this->file->read('LICENSE.md');
        $licenseHtml = $this->mdToHtml->parse($license);
        $this->templater->assign('content', $licenseHtml);

        header('Content-Type: text/html; charset=UTF-8');
        echo $this->templater->render();
        return true;
    }

    public function displayError($error): never{
        $errorHtml = "";
        $this->templater->assign('content', $errorHtml);

        header('Content-Type: text/html; charset=UTF-8');
        http_response_code($error->getHttpCode());
        echo $this->templater->render();
        die();
    }
}