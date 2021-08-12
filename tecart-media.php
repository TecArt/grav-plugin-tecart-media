<?php
namespace Grav\Plugin;

use Grav\Common\Data\Blueprint;
use Grav\Common\Data\Blueprints;
use Grav\Common\Grav;
use Grav\Common\Data;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class TecArtMediaPlugin
 * @package Grav\Plugin
 */
class TecArtMediaPlugin extends Plugin
{
    protected $folder               = 'tecart-media';
    protected $filename             = 'tecart-media.md';
    protected $filemanagerDirectory = 'tecart-media-filemanager';
    protected $filemanager          = 'tinyfilemanager.php';
    protected $routes               = ['tecart-media'];

    /**
     * @return array
     *
     * The getSubscribedEvents() gives the core a list of events
     *     that the plugin wants to listen to. The key of each
     *     array section is the event that the plugin listens to
     *     and the value (in the form of an array) contains the
     *     callable (or function) as well as the priority. The
     *     higher the number the higher the priority.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized'  => ['onPluginsInitialized', 0],
            'onTwigSiteVariables'   => ['onTwigSiteVariables',   0],
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized(): void
    {
        // Don't proceed if we are in the admin plugin
        if ($this->isAdmin()) {
            $this->enable([
                'onPagesInitialized'        => ['onPagesInitialized', 0],
                'onAdminTwigTemplatePaths'  => ['onAdminTwigTemplatePaths', 0],
                'onGetPageTemplates'        => ['onGetPageTemplates', 0],
                'onAdminMenu'               => ['onAdminMenu', 0],
            ]);

            // copy filemanager script to the grav root
            // If the directory doesn't exist yet, create the directory
            $uri = $this->grav['uri'];
            if (strpos($uri->uri(), "admin/tecart-media") !== false) {
                if (!is_dir($this->filemanagerDirectory)) {
                    mkdir($this->filemanagerDirectory, 0777, true);
                    $src = 'plugin://' . $this->name . '/vendor/'.$this->filemanagerDirectory;
                    $this->copy_directory($src,$this->filemanagerDirectory);
                }
            }
        }
    }

    /**
     * Create tecart-media folder if not exists
     *
     * @return void
     */
    public function onPagesInitialized(): void
    {
        $uri = $this->grav['uri'];

        //do nothing if not  TecArt Media in Admin Menu is clicked
        if (strpos($uri->uri(), "admin/tecart-media") !== false) {

            //very important the migration  1.6 -> 1.7 which has a section on breaking changes for Admin plugins:
            $this->grav['admin']->enablePages();

            $pages = $this->grav['pages'];
            $page = $pages->dispatch('/' . $this->folder);

            if (!$page) {

                $directory = $this->grav['locator']->base . '/user/pages/' . $this->folder;

                // If the directory doesn't exist yet, create the directory
                if (!file_exists($directory)) {
                    mkdir($directory, 0777, true);
                }

                $contents = 'Tecart Media';
                file_put_contents($directory . '/' . $this->filename, $contents);

                $page = new Page();
                $page->init(new \SplFileInfo($directory . '/' . $this->filename));
                $page->slug($this->filename);
                $page->template($this->folder);
                $page->routable(false);
                $page->visible(false);
                $page->published(false);
                $page->route('/admin'.$this->folder);
                $page->url('/admin'.$this->folder);
            }
        }

        //add page to ignore folders in system yaml to hide in page list
        $ignore_folders = (array)$this->config->get('system.pages.ignore_folders');
        if (!in_array($this->folder, $ignore_folders, true)) {
            $ignore_folders[] = $this->folder;
            $this->config->set( 'system.pages.ignore_folders', $ignore_folders);
        }
    }

    /**
     * Create admin menu link
     *
     * @return void
     */
    public function onAdminMenu(): void
    {
        if ($this->isAdmin()) {
            //set authorization to make filemanager visible for editors
            $this->grav['twig']->plugins_hooked_nav['TecArt Media'] = [
                'route' => $this->routes[0],
                'icon' => 'fa-globe',
                'authorize' => [
                    "0" => "admin",
                    "1" => "admin.login",
                    "2" => "admin.super",
                    "3" => "admin.tecart-media",
                ],
            ];
        }
    }

    /**
     * Add blueprint directory.
     *
     * @param Event $event
     * @return void
     */
    public function onGetPageTemplates(Event $event) : void
    {
        $types = $event->types;
        $types->scanBlueprints('plugin://' . $this->name . 'admin/blueprints');
    }

    /**
     * Get admin page template
     *
     * @param Event $event
     * @return void
     */
    public function onAdminTwigTemplatePaths(Event $event) : void
    {
        $paths = $event['paths'];
        $paths[] = __DIR__ . DS . 'admin/templates';
        $event['paths'] = $paths;
    }

    /**
     * Get admin page template twig vars
     *
     * @return void
     */
    public function onTwigSiteVariables() : void
    {
        if ($this->isAdmin()) {

            $uri  = $this->grav['uri'];

            if (strpos($uri->uri(), "admin/tecart-media") !== false) {

                $twig = $this->grav['twig'];

                $vars = [];
                $pathToFilemanager = $uri->base().'/'.$this->filemanagerDirectory.'/'.$this->filemanager;

                $vars['filemanager_path']  = $pathToFilemanager;
                $vars['filemanager_exists'] = file_exists($this->grav['locator']->base.'/'.$this->filemanagerDirectory.'/'.$this->filemanager);

                $twig->twig_vars = array_merge($twig->twig_vars, $vars);
            }
        }
    }

    /**
     * this function copies all files and sub directories to $destination folder
     *
     * @param $src
     * @param $dst
     * @return void
     */
    private function copy_directory($src,$dst) : void
    {
        $dir = opendir($src);
        while(( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    $this->recursive_copy($src .'/'. $file, $dst .'/'. $file);
                }
                else {
                    copy($src .'/'. $file,$dst .'/'. $file);
                }
            }
        }
        closedir($dir);
    }
}
